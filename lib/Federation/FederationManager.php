<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Federation;

use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Service\FederatedProjectService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCA\Cospend\AppInfo\Application;
use OCP\DB\Exception;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IUser;
use OCP\Notification\IManager;
use SensitiveParameter;

/**
 * Class FederationManager
 *
 * FederationManager handles incoming federated projects
 */
class FederationManager {
	public const OCM_RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
	public const COSPEND_PROJECT_RESOURCE = 'cospend-project';
	public const COSPEND_PROTOCOL_NAME = 'nccospend';
	public const NOTIFICATION_SHARE_ACCEPTED = 'SHARE_ACCEPTED';
	public const NOTIFICATION_SHARE_DECLINED = 'SHARE_DECLINED';
	public const NOTIFICATION_SHARE_UNSHARED = 'SHARE_UNSHARED';
	public const TOKEN_LENGTH = 64;

	public function __construct(
		private InvitationMapper $invitationMapper,
		private BackendNotifier $backendNotifier,
		private IManager $notificationManager,
		private ICloudIdManager $cloudIdManager,
		private RestrictionValidator $restrictionValidator,
		private FederatedProjectService $federatedProjectService,
	) {
	}

	/**
	 * Check if $sharedBy is allowed to invite $shareWith
	 *
	 * @param IUser $user
	 * @param ICloudId $cloudIdToInvite
	 */
	public function isAllowedToInvite(
		IUser $user,
		ICloudId $cloudIdToInvite,
	): void {
		$this->restrictionValidator->isAllowedToInvite($user, $cloudIdToInvite);
	}

	public function addRemoteProject(
		IUser $user,
		string $remoteProjectId,
		string $remoteProjectName,
		string $remoteServerUrl,
		#[SensitiveParameter]
		string $sharedSecret,
		string $inviterCloudId,
		string $inviterDisplayName,
	): Invitation {
		try {
			$this->invitationMapper->getInvitationForUser($user->getUID(), $inviterCloudId, $remoteProjectId);
			throw new ProviderCouldNotAddShareException('Remote project already shared', '', Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException) {
			// Not invited already
		}

		$invitation = new Invitation();
		$invitation->setUserId($user->getUID());
		$invitation->setState(Invitation::STATE_PENDING);
		$invitation->setAccessToken($sharedSecret);
		$invitation->setRemoteProjectId($remoteProjectId);
		$invitation->setRemoteProjectName($remoteProjectName);
		$invitation->setRemoteServerUrl(preg_replace('/^https:\/\//i', '', $remoteServerUrl));
		$invitation->setInviterCloudId($inviterCloudId);
		$invitation->setInviterDisplayName($inviterDisplayName);
		$this->invitationMapper->insert($invitation);

		return $invitation;
	}

	protected function markNotificationProcessed(string $userId, int $shareId): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setObject('remote_cospend_share', (string)$shareId);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * @param IUser $user
	 * @param int $shareId
	 * @return array
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function acceptRemoteProjectShare(IUser $user, int $shareId): array {
		try {
			$invitation = $this->invitationMapper->getInvitationById($shareId);
		} catch (DoesNotExistException $e) {
			throw new \InvalidArgumentException('invitation');
		}
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new \Exception('unauthorized user');
		}

		if ($invitation->getState() === Invitation::STATE_ACCEPTED) {
			throw new \InvalidArgumentException('state');
		}

		$cloudId = $this->cloudIdManager->getCloudId($user->getUID(), null);

		if (
			!$this->backendNotifier->sendShareAccepted(
				$invitation->getRemoteServerUrl(), $invitation->getRemoteProjectId(), $invitation->getAccessToken(),
				$user->getDisplayName(), $cloudId->getId()
			)
		) {
			throw new \Exception('cannot reach remote server');
		}

		$invitation->setState(Invitation::STATE_ACCEPTED);
		$this->invitationMapper->update($invitation);

		$this->markNotificationProcessed($user->getUID(), $shareId);

		$this->federatedProjectService->userId = $user->getUID();
		return $this->federatedProjectService->getProjectInfoWithAccessLevel(
			$invitation->getRemoteProjectId() . '@' . $invitation->getRemoteServerUrl(),
			$user->getUID(),
		);
	}

	/**
	 * @param int $shareId
	 * @return Invitation
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getRemoteShareById(int $shareId): Invitation {
		return $this->invitationMapper->getInvitationById($shareId);
	}

	/**
	 * @param IUser $user
	 * @param int $shareId
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function rejectRemoteProjectShare(IUser $user, int $shareId): void {
		try {
			$invitation = $this->invitationMapper->getInvitationById($shareId);
		} catch (DoesNotExistException $e) {
			throw new \InvalidArgumentException('invitation');
		}

		if ($invitation->getUserId() !== $user->getUID()) {
			throw new \Exception('Unauthorized user');
		}

		if ($invitation->getState() !== Invitation::STATE_PENDING) {
			throw new \InvalidArgumentException('state');
		}

		$this->rejectInvitation($invitation, $user->getUID());
	}

	/**
	 * @param Invitation $invitation
	 * @param string $userId
	 * @throws Exception
	 */
	protected function rejectInvitation(Invitation $invitation, string $userId): void {
		$this->invitationMapper->delete($invitation);

		$cloudId = $this->cloudIdManager->getCloudId($userId, null);
		$this->backendNotifier->sendShareDeclined(
			$invitation->getRemoteServerUrl(), $invitation->getRemoteProjectId(), $invitation->getAccessToken(),
			$cloudId->getId()
		);
		$this->markNotificationProcessed($userId, $invitation->getId());
	}

	/**
	 * @param IUser $user
	 * @param int|null $state
	 * @return Invitation[]
	 * @throws Exception
	 */
	public function getRemoteProjectShares(IUser $user, ?int $state = null): array {
		return $this->invitationMapper->getInvitationsForUser($user->getUID(), $state);
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	public function getNumberOfPendingInvitationsForUser(string $userId): int {
		return $this->invitationMapper->countInvitationsForUser($userId, Invitation::STATE_PENDING);
	}
}
