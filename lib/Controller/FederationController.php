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

use GuzzleHttp\Exception\ClientException;
use OC\User\NoUserException;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Attribute\SupportFederatedProject;
use OCA\Cospend\Attribute\CospendUserPermissions;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Service\CospendService;
use OCA\Cospend\Service\IProjectService;
use OCA\Cospend\Service\LocalProjectService;
use OCA\Richdocuments\Service\FederationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Constants;

use OCP\DB\Exception;

use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;

use OCP\IRequest;
use OCP\Lock\LockedException;
use OCP\Share\IManager;
use OCP\Share\IShare;

class FederationController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private FederationService $federationService,
		public ?string $userId,
	) {
		parent::__construct($appName, $request);
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
