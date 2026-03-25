<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Federation;

use OCA\Cospend\Db\Project;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\AppFramework\Http;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\OCM\Exceptions\OCMProviderException;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class BackendNotifier {

	public function __construct(
		private ICloudFederationFactory $cloudFederationFactory,
		private AddressHandler $addressHandler,
		private LoggerInterface $logger,
		private ICloudFederationProviderManager $federationProviderManager,
		private IUserManager $userManager,
		private IURLGenerator $url,
		private ICloudIdManager $cloudIdManager,
		private RestrictionValidator $restrictionValidator,
		private IClientService $clientService,
	) {
	}

	/**
	 * Fetch the capabilities of a remote Nextcloud instance.
	 * Returns an empty array when the remote cannot be reached, so the caller
	 * can fall through to the normal OCM send and let that produce the error.
	 */
	private function fetchRemoteCapabilities(string $remote): array {
		$url = rtrim($remote, '/') . '/ocs/v2.php/cloud/capabilities?format=json';
		try {
			$response = $this->clientService->newClient()->get($url, [
				'headers' => ['OCS-apirequest' => 'true'],
				'timeout' => 5,
				'connect_timeout' => 5,
			]);
			$body = json_decode((string)$response->getBody(), true);
			return $body['ocs']['data']['capabilities'] ?? [];
		} catch (\Throwable $e) {
			$this->logger->debug('Could not fetch remote capabilities from {remote}: {message}', [
				'remote' => $remote,
				'message' => $e->getMessage(),
			]);
			return [];
		}
	}

	/**
	 * Check whether the remote server has Cospend federation enabled.
	 * Throws CospendBasicException when the capabilities are reachable and
	 * explicitly show federation as disabled — silently passes when the remote
	 * does not expose the capability at all (older version, non-Cospend server).
	 *
	 * @throws CospendBasicException
	 */
	private function checkRemoteCospendFederationCapabilities(string $remote): void {
		$capabilities = $this->fetchRemoteCapabilities($remote);
		if (isset($capabilities['cospend']['federation']['enabled'])
			&& $capabilities['cospend']['federation']['enabled'] === false) {
			$this->logger->warning(
				'Remote server {remote} has Cospend federation disabled',
				['remote' => $remote]
			);
			throw new CospendBasicException(
				'Remote server does not support Cospend federation',
				Http::STATUS_BAD_REQUEST
			);
		}
	}

	/**
	 * Send the invitation to the remote user to join the federated project
	 * Sent from Host server to Remote user server
	 *
	 * @return array{displayName: string, cloudId: string}|false
	 */
	public function sendRemoteShare(
		string $providerId,
		string $token,
		string $shareWith,
		IUser $sharedBy,
		string $shareType,
		Project $project,
	): array|bool {
		$invitedCloudId = $this->cloudIdManager->resolveCloudId($shareWith);

		$projectName = $project->getName();

		try {
			$this->restrictionValidator->isAllowedToInvite($sharedBy, $invitedCloudId);
		} catch (\InvalidArgumentException) {
			return false;
		}

		/** @var IUser $projectOwner */
		$projectOwner = $this->userManager->get($project->getUserId());

		$remote = $this->prepareRemoteUrl($invitedCloudId->getRemote());

		// Pre-flight: verify the remote server has Cospend federation enabled
		// before attempting to send the OCM invite.
		$this->checkRemoteCospendFederationCapabilities($remote);

		$shareWithCloudId = $invitedCloudId->getUser() . '@' . $remote;
		$share = $this->cloudFederationFactory->getCloudFederationShare(
			$shareWithCloudId,
			$projectName,
			'',
			$providerId,
			$projectOwner->getCloudId(),
			$projectOwner->getDisplayName(),
			$sharedBy->getCloudId(),
			$sharedBy->getDisplayName(),
			$token,
			$shareType,
			FederationManager::COSPEND_PROJECT_RESOURCE
		);

		$protocol = $share->getProtocol();
		$protocol['invitedCloudId'] = $invitedCloudId->getId();
		// $protocol['projectName'] = $projectName;
		$protocol['name'] = FederationManager::COSPEND_PROTOCOL_NAME;
		$share->setProtocol($protocol);

		try {
			$response = $this->federationProviderManager->sendCloudShare($share);
			if ($response->getStatusCode() === Http::STATUS_CREATED) {
				$body = $response->getBody();
				$data = json_decode((string)$body, true);
				if (isset($data['recipientUserId']) && $data['recipientUserId'] !== '') {
					$shareWithCloudId = $data['recipientUserId'] . '@' . $remote;
				}
				return [
					'displayName' => $data['recipientDisplayName'] ?: $shareWithCloudId,
					'cloudId' => $shareWithCloudId,
				];
			}

			$this->logger->warning('Failed sharing {projectId} with {shareWith}, received status code {code}\n{body}', [
				'projectId' => $project->getId(),
				'shareWith' => $shareWith,
				'code' => $response->getStatusCode(),
				'body' => (string)$response->getBody(),
			]);

			return false;
		} catch (OCMProviderException $e) {
			$this->logger->error('Failed sharing ' . $project->getId() . ' with ' . $shareWith . ', received OCMProviderException', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * The invited user accepted joining the federated project
	 * Sent from Remote user server to Host server
	 *
	 * @return bool success
	 */
	public function sendShareAccepted(
		string $remoteServerUrl,
		string $projectId,
		#[SensitiveParameter]
		string $accessToken,
		string $displayName,
		string $cloudId,
	): bool {
		$remote = $this->prepareRemoteUrl($remoteServerUrl);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_ACCEPTED,
			FederationManager::COSPEND_PROJECT_RESOURCE,
			$projectId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'message' => 'Recipient accepted the share',
				'displayName' => $displayName,
				'cloudId' => $cloudId,
			]
		);

		return $this->sendUpdateToRemote($remote, $notification) === true;
	}

	/**
	 * The invited participant declined joining the federated room
	 * Sent from Remote participant server to Host server
	 */
	public function sendShareDeclined(
		string $remoteServerUrl,
		string $projectId,
		#[SensitiveParameter]
		string $accessToken,
		string $cloudId,
	): void {
		$remote = $this->prepareRemoteUrl($remoteServerUrl);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_DECLINED,
			FederationManager::COSPEND_PROJECT_RESOURCE,
			$projectId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'message' => 'Recipient declined the share',
				'cloudId' => $cloudId,
			]
		);

		// We don't handle the return here as all local data is already deleted.
		// If the retry ever aborts due to "unknown" we are fine with it.
		$this->sendUpdateToRemote($remote, $notification);
	}

	public function sendRemoteUnShare(
		string $remoteServerUrl,
		string $projectId,
		#[SensitiveParameter]
		string $accessToken,
	): void {
		$remote = $this->prepareRemoteUrl($remoteServerUrl);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_UNSHARED,
			FederationManager::COSPEND_PROJECT_RESOURCE,
			$projectId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'message' => 'This project has been unshared',
			]
		);

		// We don't handle the return here as when the retry ever
		// aborts due to "unknown" we are fine with it.
		$this->sendUpdateToRemote($remote, $notification);
	}

	protected function sendUpdateToRemote(string $remote, ICloudFederationNotification $notification): ?bool {
		try {
			$response = $this->federationProviderManager->sendCloudNotification($remote, $notification);
			if ($response->getStatusCode() === Http::STATUS_CREATED) {
				return true;
			}

			if ($response->getStatusCode() === Http::STATUS_BAD_REQUEST) {
				$ocmBody = json_decode((string)$response->getBody(), true) ?? [];
				if (isset($ocmBody['message']) && $ocmBody['message'] === FederationManager::OCM_RESOURCE_NOT_FOUND) {
					// Remote exists but tells us the OCM notification can not be received (invalid invite data)
					// So we stop retrying
					return null;
				}
			}

			$this->logger->warning("Failed to send notification for share from $remote, received status code {code}\n{body}", [
				'code' => $response->getStatusCode(),
				'body' => (string)$response->getBody(),
			]);
		} catch (OCMProviderException $e) {
			$this->logger->error("Failed to send notification for share from $remote, received OCMProviderException", ['exception' => $e]);
		}

		return false;
	}

	protected function prepareRemoteUrl(string $remote): string {
		if (!$this->addressHandler->urlContainProtocol($remote)) {
			return 'https://' . $remote;
		}
		return $remote;
	}

	protected function getServerRemoteUrl(): string {
		$server = rtrim($this->url->getAbsoluteURL('/'), '/');
		if (str_ends_with($server, '/index.php')) {
			$server = substr($server, 0, -10);
		}

		return $server;
	}
}
