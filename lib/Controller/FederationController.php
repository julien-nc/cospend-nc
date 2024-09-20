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

use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Federation\FederationManager;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Service\FederatedProjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\OCSController;

use OCP\DB\Exception;
use OCP\Federation\ICloudIdManager;
use OCP\IAvatarManager;

use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;

/**
 *
 * @psalm-import-type CospendFederationInvite from ResponseDefinitions
 * @psalm-import-type CospendFullProjectInfo from ResponseDefinitions
 */
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

	/**
	 * Get remote user avatar
	 *
	 * Get the avatar of a remote user from its federated cloud ID
	 *
	 * @param int $size
	 * @param string $cloudId
	 * @param bool $darkTheme
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
	 *
	 * 200: The avatar has been obtained successfully
	 * 303: The remote avatar can't be obtained, fallback to a locally generated guest avatar
	 */
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
	 * @return RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
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
	 * @param int $id ID of the share
	 * @psalm-param non-negative-int $id
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_GONE, array{error: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error?: string}, array{}>
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
			$project = $this->federationManager->acceptRemoteProjectShare($user, $id);
			return new DataResponse($project);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'invitation' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			if ($e->getMessage() === 'cannot reach remote server') {
				return new DataResponse(['error' => 'remote'], Http::STATUS_GONE);
			} elseif ($e->getMessage() === 'unauthorized user') {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Decline a federation invite
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
			$this->federationManager->rejectRemoteProjectShare($user, $id);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getMessage() === 'invitation' ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			if ($e->getMessage() === 'unauthorized user') {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse();
	}

	/**
	 * Get a list of federation invites
	 *
	 * @return DataResponse<Http::STATUS_OK, list<CospendFederationInvite>, array{}>
	 * @throws Exception
	 *
	 * 200: Get list of received federation invites successfully
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
	public function getPendingShares(): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new \Exception('Unauthorized');
		}
		$invitations = $this->federationManager->getRemoteProjectShares($user, Invitation::STATE_PENDING);
		$jsonInvitations = array_map(function (Invitation $invite) {
			$json = $invite->jsonSerialize();
			unset($json['accessToken']);
			return $json;
		}, $invitations);

		return new DataResponse(array_values($jsonInvitations));
	}
}
