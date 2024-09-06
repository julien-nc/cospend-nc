<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Federation;

use Exception;
use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Db\Share;
use OCA\Cospend\Db\ShareMapper;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Cospend\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\DB\Exception as DBException;
use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\HintException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\Exceptions\ShareNotFound;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class CloudFederationProviderCospend implements ICloudFederationProvider {
	protected ?ICache $proxyCacheMessages;

	public function __construct(
		private ICloudIdManager $cloudIdManager,
		private IUserManager $userManager,
		private AddressHandler $addressHandler,
		private FederationManager $federationManager,
		private ShareMapper $shareMapper,
		private IConfig $config,
		private INotificationManager $notificationManager,
		private InvitationMapper $invitationMapper,
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getShareType(): string {
		return 'cospend-project';
	}

	/**
	 * @inheritDoc
	 * @throws HintException
	 * @throws DBException
	 */
	public function shareReceived(ICloudFederationShare $share): string {
		$federationEnabled = $this->config->getAppValue('cospend', 'federation_enabled', '0') === '1';
		if (!$federationEnabled) {
			$this->logger->debug('Received a federation invite but federation is disabled');
			throw new ProviderCouldNotAddShareException('Server does not support Cospend federation', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}
		/*
		if (!$this->appConfig->getAppValueBool('federation_incoming_enabled', true)) {
			$this->logger->warning('Received a federation invite but incoming federation is disabled');
			throw new ProviderCouldNotAddShareException('Server does not support talk federation', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}
		*/
		if (!in_array($share->getShareType(), $this->getSupportedShareTypes(), true)) {
			$this->logger->debug('Received a federation invite for invalid share type');
			throw new ProviderCouldNotAddShareException('Support for sharing with non-users not implemented yet', '', Http::STATUS_NOT_IMPLEMENTED);
			// TODO: Implement group shares
		}

		$shareSecret = $share->getShareSecret();
		$shareWith = $share->getShareWith();
		$remoteProjectId = $share->getProviderId();
		$remoteProjectName = $share->getResourceName();
//		$remoteProjectName = $share->getProtocol()['projectName'];
		$sharedByDisplayName = $share->getSharedByDisplayName();
		$sharedByFederatedId = $share->getSharedBy();
		$ownerDisplayName = $share->getOwnerDisplayName();
		$ownerFederatedId = $share->getOwner();
		if (isset($share->getProtocol()['invitedCloudId'])) {
			$localCloudId = $share->getProtocol()['invitedCloudId'];
		} else {
			$this->logger->debug('Received a federation invite without invitedCloudId, falling back to shareWith');
			$cloudId = $this->cloudIdManager->getCloudId($shareWith, null);
			$localCloudId = $cloudId->getUser() . '@' . $cloudId->getRemote();
		}
		[, $remote] = $this->addressHandler->splitUserRemote($ownerFederatedId);

		if (!$this->addressHandler->urlContainProtocol($remote)) {
			// Heal federation from before Nextcloud 29.0.4 which sends requests
			// without the protocol on the remote in case it is https://
			$remote = 'https://' . $remote;
		}

		// if no explicit information about the person who created the share was sent
		// we assume that the share comes from the owner
		if ($sharedByFederatedId === null) {
			$sharedByDisplayName = $ownerDisplayName;
			$sharedByFederatedId = $ownerFederatedId;
		}

		if ($remote && $shareSecret && $shareWith && $remoteProjectId && $remoteProjectName && $ownerDisplayName) {
			$shareWithUser = $this->userManager->get($shareWith);
			if ($shareWithUser === null) {
				$this->logger->debug('Received a federation invite for user that could not be found');
				throw new ProviderCouldNotAddShareException('User does not exist', '', Http::STATUS_BAD_REQUEST);
			} elseif (!str_starts_with($localCloudId, $shareWithUser->getUID() . '@')) {
				// Fix the user ID as we also return it via the cloud federation api response in Nextcloud 30+
				$cloudId = $this->cloudIdManager->resolveCloudId($localCloudId);
				$localCloudId = $shareWithUser->getUID() . '@' . $cloudId->getRemote();
			}

			/*
			if (!$this->config->isFederationEnabledForUserId($shareWithUser)) {
				$this->logger->debug('Received a federation invite for user that is not allowed to use Talk Federation');
				throw new ProviderCouldNotAddShareException('User does not exist', '', Http::STATUS_BAD_REQUEST);
			}
			*/

			$invite = $this->federationManager->addRemoteProject(
				$shareWithUser, $remoteProjectId, $remoteProjectName, $remote, $shareSecret, $sharedByDisplayName, $sharedByDisplayName
			);

			$this->notifyAboutNewShare(
				$shareWithUser, (string)$invite->getId(), $sharedByFederatedId, $sharedByDisplayName, $remoteProjectName, $remoteProjectId, $remote
			);
			return (string)$invite->getId();
		}

		$this->logger->debug('Received a federation invite with missing request data');
		throw new ProviderCouldNotAddShareException('required request data not found', '', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @inheritDoc
	 */
	public function notificationReceived($notificationType, $providerId, array $notification): array {
		/*
		if (!is_numeric($providerId)) {
			throw new BadRequestException(['providerId']);
		}
		*/
		switch ($notificationType) {
			case FederationManager::NOTIFICATION_SHARE_ACCEPTED:
				return $this->shareAccepted((string)$providerId, $notification);
			case FederationManager::NOTIFICATION_SHARE_DECLINED:
				return $this->shareDeclined((string)$providerId, $notification);
			case FederationManager::NOTIFICATION_SHARE_UNSHARED:
				return $this->shareUnshared((string)$providerId, $notification);
		}

		throw new BadRequestException([$notificationType]);
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws DBException
	 * @throws ShareNotFound
	 */
	private function shareAccepted(string $providerId, array $notification): array {
		$share = $this->getLocalShareAndValidate($notification['sharedSecret'], $providerId, $notification['cloudId']);
		$share->setState(Invitation::STATE_ACCEPTED);
		$this->shareMapper->update($share);
		return [];
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws DBException
	 * @throws ShareNotFound
	 */
	private function shareDeclined(string $providerId, array $notification): array {
		$share = $this->getLocalShareAndValidate($notification['sharedSecret'], $providerId, $notification['cloudId']);
		$this->shareMapper->delete($share);
		return [];
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareUnshared(string $providerId, array $notification): array {
		$invite = $this->getInviteByRemoteProjectIdAndValidate($notification['remoteServerUrl'], $providerId, $notification['sharedSecret']);
		try {
			$room = $this->manager->getRoomById($invite->getLocalRoomId());
		} catch (RoomNotFoundException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedConversation()) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		$this->invitationMapper->delete($invite);

		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $invite->getUserId());
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		} catch (ParticipantNotFoundException) {
			// Never accepted the invite
		}

		return [];
	}

	/**
	 * @throws AuthenticationFailedException
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 */
	private function getLocalShareAndValidate(
		#[SensitiveParameter]
		string $sharedSecret,
		string $projectId,
		string $userCloudId,
	): Share {
		$federationEnabled = $this->config->getAppValue('cospend', 'federation_enabled', '0') === '1';
		if (!$federationEnabled) {
			throw new ActionNotSupportedException('Server does not support Cospend federation');
		}

		try {
			$share = $this->shareMapper->getFederatedShareByProjectIdAndUserCloudId($projectId, $userCloudId);
		} catch (Exception) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}
		if ($share->getUserid() !== $sharedSecret) {
			throw new AuthenticationFailedException();
		}
		return $share;
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws DBException
	 * @throws ShareNotFound
	 * @throws MultipleObjectsReturnedException
	 */
	private function getInviteByRemoteProjectIdAndValidate(
		string $remoteServerUrl,
		string $remoteProjectId,
		#[SensitiveParameter]
		string $sharedSecret,
	): Invitation {
		$federationEnabled = $this->config->getAppValue('cospend', 'federation_enabled', '0') === '1';
		if (!$federationEnabled) {
			throw new ActionNotSupportedException('Server does not support Cospend federation');
		}

		if (!$sharedSecret) {
			throw new AuthenticationFailedException();
		}

		$remoteServerUrl = preg_replace('/^https?:\/\//i', '', $remoteServerUrl);

		try {
			return $this->invitationMapper->getByRemoteAndToken($remoteServerUrl, $remoteProjectId, $sharedSecret);
		} catch (DoesNotExistException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}
	}

	private function notifyAboutNewShare(IUser $shareWith, string $inviteId, string $sharedByFederatedId, string $sharedByName, string $remoteProjectName, string $remoteProjectId, string $remoteServerUrl): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($shareWith->getUID())
			->setDateTime(new \DateTime())
			->setObject('remote_cospend_share', $inviteId)
			->setSubject('remote_cospend_share', [
				'sharedByDisplayName' => $sharedByName,
				'sharedByFederatedId' => $sharedByFederatedId,
				'remoteProjectName' => $remoteProjectName,
				'remoteServerUrl' => $remoteServerUrl,
				'remoteProjectId' => $remoteProjectId,
			]);

		$this->notificationManager->notify($notification);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedShareTypes(): array {
		return ['user'];
	}
}
