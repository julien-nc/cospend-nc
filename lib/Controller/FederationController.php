<?php
/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Cospend\Controller;

use OCA\Cospend\Federation\FederationManager;
use OCA\Cospend\Service\FederatedProjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\OCSController;

use OCP\Federation\ICloudIdManager;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\IAvatarManager;

use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class FederationController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private FederationManager $federationManager,
		private FederatedProjectService $federatedProjectService,
		private ICloudIdManager $cloudIdManager,
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
		private IAvatarManager $avatarManager,
		public ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	#[NoCSRFRequired]
	public function getRemoteUserAvatar(int $size, string $cloudId, bool $darkTheme = false): FileDisplayResponse|RedirectResponse {
		try {
			$resolvedCloudId = $this->cloudIdManager->resolveCloudId($cloudId);
		} catch (\InvalidArgumentException) {
			return $this->getPlaceholderResponse('?');
		}

		$ownId = $this->cloudIdManager->getCloudId($this->userSession->getUser()->getCloudId(), null);

		/**
		 * Reach out to the remote server to get the avatar
		 */
		if ($ownId->getRemote() !== $resolvedCloudId->getRemote()) {
			try {
				return $this->federatedProjectService->getUserProxyAvatar($resolvedCloudId->getRemote(), $resolvedCloudId->getUser(), $size, $darkTheme);
			} catch (\Throwable $e) {
				// Falling back to a local "user" avatar
				return $this->getPlaceholderResponse($resolvedCloudId->getUser());
			}
		}

		/**
		 * We are the server that hosts the user, so getting it from the avatar manager
		 */
		try {
			$avatar = $this->avatarManager->getAvatar($resolvedCloudId->getUser());
			$avatarFile = $avatar->getFile($size, $darkTheme);
		} catch (\Exception) {
			return $this->getPlaceholderResponse($resolvedCloudId->getUser());
		}

		$response = new FileDisplayResponse(
			$avatarFile,
			Http::STATUS_OK,
			['Content-Type' => $avatarFile->getMimeType()],
		);
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * Get the placeholder avatar
	 *
	 * @param string $name
	 * @return RedirectResponse<Http::STATUS_OK, array{Content-Type: string}>
	 *
	 * 200: User avatar returned
	 */
	protected function getPlaceholderResponse(string $name): RedirectResponse {
		$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $name, 'size' => 44]);
		return new RedirectResponse($fallbackAvatarUrl);
	}

	/**
	 * Accept a federation invite
	 *
	 *
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, CospendProject, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_GONE, array{error: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error?: string}, array{}>
	 *
	 * 200: Invite accepted successfully
	 * 400: Invite can not be accepted (maybe it was accepted already)
	 * 404: Invite can not be found
	 * 410: Remote server could not be reached to notify about the acceptance
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	public function acceptShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		try {
			$participant = $this->federationManager->acceptRemoteRoomShare($user, $id);
		} catch (CannotReachRemoteException) {
			return new DataResponse(['error' => 'remote'], Http::STATUS_GONE);
		} catch (UnauthorizedException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'invitation' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$participant->getRoom(),
			$participant,
		));
	}

	/**
	 * Decline a federation invites
	 *
	 * 🚧 Draft: Still work in progress
	 *
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, array{error?: string}, array{}>
	 *
	 * 200: Invite declined successfully
	 * 400: Invite was already accepted, use the "Remove the current user from a room" endpoint instead
	 * 404: Invite can not be found
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	public function rejectShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		try {
			$this->federationManager->rejectRemoteRoomShare($user, $id);
		} catch (UnauthorizedException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'invitation' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	/**
	 * Get a list of federation invites
	 *
	 * 🚧 Draft: Still work in progress
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkFederationInvite>, array{}>
	 *
	 * 200: Get list of received federation invites successfully
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	public function getShares(): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new UnauthorizedException();
		}
		$invitations = $this->federationManager->getRemoteRoomShares($user);

		/** @var list<TalkFederationInvite> $data */
		$data = array_values(array_filter(array_map([$this, 'enrichInvite'], $invitations)));

		return new DataResponse($data);
	}

	/**
	 * @param Invitation $invitation
	 * @return TalkFederationInvite|null
	 */
	protected function enrichInvite(Invitation $invitation): ?array {
		try {
			$room = $this->talkManager->getRoomById($invitation->getLocalRoomId());
		} catch (RoomNotFoundException) {
			return null;
		}

		$federationInvite = $invitation->jsonSerialize();
		$federationInvite['roomName'] = $room->getName();
		$federationInvite['localToken'] = $room->getToken();
		return $federationInvite;
	}

}
