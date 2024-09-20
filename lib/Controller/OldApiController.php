<?php
/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Controller;

use DateTime;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Attribute\CospendPublicAuth;
use OCA\Cospend\Attribute\CospendUserPermissions;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\Cospend\Service\LocalProjectService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;

use OCP\AppFramework\Http\Attribute\CORS;

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class OldApiController extends ApiController {

	public string $projectId;

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectMapper $projectMapper,
		private LocalProjectService $localProjectService,
		private ActivityManager $activityManager,
		public ?string $userId,
	) {
		parent::__construct(
			$appName, $request,
			'PUT, POST, GET, DELETE, PATCH, OPTIONS',
			'Authorization, Content-Type, Accept',
			1728000
		);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function apiPrivGetProjects(): DataResponse {
		return new DataResponse(
			$this->localProjectService->getLocalProjects($this->userId)
		);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function apiPrivGetProjects2(): DataResponse {
		return $this->apiPrivGetProjects();
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	public function apiPrivSetProjectInfo(string $projectId, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
		?string $categorysort = null, ?string $paymentmodesort = null): DataResponse {
		try {
			$this->localProjectService->editProject(
				$projectId, $name, $contact_email, $autoexport,
				$currencyname, $deletion_disabled, $categorysort, $paymentmodesort
			);
			return new DataResponse('UPDATED');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function apiPrivCreateProject(string $name, string $id, ?string $contact_email = null): DataResponse {
		try {
			$this->projectMapper->getById($id);
			return new DataResponse('project already exists', Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
		}
		try {
			$jsonProject = $this->localProjectService->createProject($name, $id, $contact_email, $this->userId);
			$projInfo = $this->localProjectService->getProjectInfo($jsonProject['id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVEL_ADMIN;
			return new DataResponse($projInfo);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param string $token
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetProjectInfo')]
	public function apiGetProjectInfo(string $token): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$projectInfo = $this->localProjectService->getProjectInfo($publicShareInfo['projectid']);
		if ($projectInfo !== null) {
			unset($projectInfo['userid']);
			// set the visible access level for frontend
			$projectInfo['myaccesslevel'] = $publicShareInfo['accesslevel'];
			return new DataResponse($projectInfo);
		}
		return new DataResponse(
			['message' => $this->trans->t('Project not found')],
			Http::STATUS_NOT_FOUND
		);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	public function apiPrivGetProjectInfo(string $projectId): DataResponse {
		$projectInfo = $this->localProjectService->getProjectInfo($projectId);
		if ($projectInfo !== null) {
			unset($projectInfo['userid']);
			$projectInfo['myaccesslevel'] = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
			return new DataResponse($projectInfo);
		}
		return new DataResponse(
			['message' => $this->trans->t('Project not found')],
			Http::STATUS_NOT_FOUND
		);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicSetProjInfo')]
	public function apiSetProjectInfo(string $token, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null,
		?bool $deletion_disabled = null, ?string $categorysort = null, ?string $paymentmodesort = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->editProject(
				$publicShareInfo['projectid'], $name, $contact_email, $autoexport,
				$currencyname, $deletion_disabled, $categorysort, $paymentmodesort
			);
			return new DataResponse('UPDATED');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetMembers')]
	public function apiGetMembers(string $token, ?int $lastchanged = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$members = $this->localProjectService->getMembers($publicShareInfo['projectid'], null, $lastchanged);
		return new DataResponse($members);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	public function apiPrivGetMembers(string $projectId, ?int $lastchanged = null): DataResponse {
		$members = $this->localProjectService->getMembers($projectId, null, $lastchanged);
		return new DataResponse($members);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBills')]
	public function apiGetBills(string $token, ?int $lastchanged = null,
		?int $offset = 0, ?int $limit = null, bool $reverse = false, ?int $deleted = 0): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		if ($limit) {
			$bills = $this->billMapper->getBillsWithLimit(
				$publicShareInfo['projectid'], null, null,
				null, null, null, null, null,
				$lastchanged, $limit, $reverse, $offset, null, null, null, $deleted
			);
		} else {
			$bills = $this->billMapper->getBillsClassic(
				$publicShareInfo['projectid'], null, null,
				null, null, null, null, null,
				$lastchanged, null, $reverse, null, $deleted
			);
		}
		return new DataResponse($bills);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBills3')]
	public function apiv3GetBills(
		string $token, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0,
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		if ($limit) {
			$bills = $this->billMapper->getBillsWithLimit(
				$publicShareInfo['projectid'], null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastchanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
			);
		} else {
			$bills = $this->billMapper->getBillsClassic(
				$publicShareInfo['projectid'], null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastchanged, null, $reverse, $payerId, $deleted
			);
		}
		$result = [
			'nb_bills' => $this->billMapper->countBills(
				$publicShareInfo['projectid'], $payerId, $categoryId, $paymentModeId, $deleted
			),
			'bills' => $bills,
		];
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	public function apiPrivGetBills(string $projectId, ?int $lastchanged = null, ?int $deleted = 0): DataResponse {
		$bills = $this->billMapper->getBillsClassic(
			$projectId, null, null, null, null, null,
			null, null, $lastchanged, null, false, null, $deleted
		);
		$billIds = $this->billMapper->getAllBillIds($projectId, $deleted);
		$ts = (new DateTime())->getTimestamp();
		return new DataResponse([
			'bills' => $bills,
			'allBillIds' => $billIds,
			'timestamp' => $ts,
		]);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBills2')]
	public function apiv2GetBills(string $token, ?int $lastchanged = null, ?int $deleted = 0): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$bills = $this->billMapper->getBillsClassic(
			$publicShareInfo['projectid'], null, null,
			null, null, null, null, null, $lastchanged,
			null, false, null, $deleted
		);
		$billIds = $this->billMapper->getAllBillIds($publicShareInfo['projectid'], $deleted);
		$ts = (new DateTime())->getTimestamp();
		return new DataResponse([
			'bills' => $bills,
			'allBillIds' => $billIds,
			'timestamp' => $ts,
		]);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicAddMember')]
	public function apiAddMember(string $token, string $name,
		float  $weight = 1, int $active = 1, ?string $color = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$member = $this->localProjectService->createMember(
				$publicShareInfo['projectid'], $name, $weight, $active !== 0, $color, null
			);
			return new DataResponse($member['id']);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data['error'] ?? '', $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicAddMember2')]
	public function apiv2AddMember(string $token, string $name, float $weight = 1, int $active = 1,
		?string $color = null, ?string $userid = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$member = $this->localProjectService->createMember(
				$publicShareInfo['projectid'], $name, $weight, $active !== 0, $color, $userid
			);
			return new DataResponse($member);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data['error'] ?? '', $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivAddMember(string $projectId, string $name, float $weight = 1, int $active = 1,
		?string $color = null, ?string $userid = null): DataResponse {
		try {
			$member = $this->localProjectService->createMember($projectId, $name, $weight, $active !== 0, $color, $userid);
			return new DataResponse($member['id']);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data['error'] ?? '', $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicAddBill')]
	public function apiAddBill(string $token, ?string $date = null, ?string $what = null, ?int $payer = null,
		?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$insertedId = $this->localProjectService->createBill(
				$publicShareInfo['projectid'], $date, $what, $payer, $payed_for, $amount,
				$repeat, $paymentmode, $paymentmodeid, $categoryid, $repeatallactive,
				$repeatuntil, $timestamp, $comment, $repeatfreq
			);
			$billObj = $this->billMapper->find($insertedId);
			if (is_null($publicShareInfo)) {
				$authorFullText = $this->trans->t('Guest access');
			} elseif ($publicShareInfo['label']) {
				$authorName = $publicShareInfo['label'];
				$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
			} else {
				$authorFullText = $this->trans->t('Share link');
			}
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_CREATE,
				['author' => $authorFullText]
			);
			return new DataResponse($insertedId);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	public function apiPrivAddBill(string  $projectId, ?string $date = null, ?string $what = null, ?int $payer = null,
		?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null): DataResponse {
		try {
			$insertedId = $this->localProjectService->createBill($projectId, $date, $what, $payer, $payed_for, $amount,
				$repeat, $paymentmode, $paymentmodeid, $categoryid, $repeatallactive,
				$repeatuntil, $timestamp, $comment, $repeatfreq);
			$billObj = $this->billMapper->find($insertedId);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_CREATE,
				[]
			);
			return new DataResponse($insertedId);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicRepeatBill')]
	public function apiRepeatBill(string $token, int $billId): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$bill = $this->billMapper->getBill($publicShareInfo['projectid'], $billId);
		if ($bill === null) {
			return new DataResponse('Bill not found', Http::STATUS_NOT_FOUND);
		}
		$result = $this->localProjectService->cronRepeatBills($billId);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicEditBill')]
	public function apiEditBill(
		string  $token, int $billid, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null,
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->editBill(
				$publicShareInfo['projectid'], $billid, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, $deleted
			);
			$billObj = $this->billMapper->find($billid);
			if (is_null($publicShareInfo)) {
				$authorFullText = $this->trans->t('Guest access');
			} elseif ($publicShareInfo['label']) {
				$authorName = $publicShareInfo['label'];
				$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
			} else {
				$authorFullText = $this->trans->t('Share link');
			}
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_UPDATE,
				['author' => $authorFullText]
			);

			return new DataResponse($billid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicEditBills')]
	public function apiEditBills(
		string $token, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null, ?float $amount = null,
		?string $repeat = 'n', ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null,
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		if (is_null($publicShareInfo)) {
			$authorFullText = $this->trans->t('Guest access');
		} elseif ($publicShareInfo['label']) {
			$authorName = $publicShareInfo['label'];
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billid) {
			try {
				$this->localProjectService->editBill(
					$publicShareInfo['projectid'], $billid, $date, $what, $payer, $payed_for,
					$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
					$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, $deleted
				);
				$billObj = $this->billMapper->find($billid);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					['author' => $authorFullText]
				);
			} catch (CospendBasicException $e) {
				return new DataResponse($e->data, $e->getCode());
			} catch (\Throwable $e) {
				return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
			}
		}
		return new DataResponse($billIds);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	public function apiPrivEditBill(
		string $projectId, int $billid, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, ?string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null,
	): DataResponse {
		try {
			$this->localProjectService->editBill(
				$projectId, $billid, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, $deleted
			);
			$billObj = $this->billMapper->find($billid);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_UPDATE,
				[]
			);

			return new DataResponse($billid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicClearTrashBin')]
	public function apiClearTrashBin(string $token): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->billMapper->deleteDeletedBills($publicShareInfo['projectid']);
			return new DataResponse('');
		} catch (\Exception|\Throwable $e) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBill')]
	public function apiDeleteBill(string $token, int $billid, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$billObj = null;
		if ($this->billMapper->getBill($publicShareInfo['projectid'], $billid) !== null) {
			$billObj = $this->billMapper->find($billid);
		}

		try {
			$this->localProjectService->deleteBill($publicShareInfo['projectid'], $billid, false, $moveToTrash, true);
			if (!is_null($billObj)) {
				if (is_null($publicShareInfo)) {
					$authorFullText = $this->trans->t('Guest access');
				} elseif ($publicShareInfo['label']) {
					$authorName = $publicShareInfo['label'];
					$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
				} else {
					$authorFullText = $this->trans->t('Share link');
				}
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_DELETE,
					['author' => $authorFullText]
				);
			}
			return new DataResponse('OK');
		} catch (\Throwable $e) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBills')]
	public function apiDeleteBills(string $token, array $billIds, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		if (is_null($publicShareInfo)) {
			$authorFullText = $this->trans->t('Guest access');
		} elseif ($publicShareInfo['label']) {
			$authorName = $publicShareInfo['label'];
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billId) {
			$billObj = null;
			if ($this->billMapper->getBill($publicShareInfo['projectid'], $billId) !== null) {
				$billObj = $this->billMapper->find($billId);
			}

			try {
				$this->localProjectService->deleteBill($publicShareInfo['projectid'], $billId, false, $moveToTrash);
				if (!is_null($billObj)) {
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_BILL, $billObj,
						ActivityManager::SUBJECT_BILL_DELETE,
						['author' => $authorFullText]
					);
				}
			} catch (\Throwable $e) {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse('OK');
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	public function apiPrivClearTrashBin(string $projectId): DataResponse {
		try {
			$this->billMapper->deleteDeletedBills($projectId);
			return new DataResponse('');
		} catch (\Exception|\Throwable $e) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	public function apiPrivDeleteBill(string $projectId, int $billid, bool $moveToTrash = true): DataResponse {
		try {
			$this->localProjectService->deleteBill($projectId, $billid, false, $moveToTrash, true);
			return new DataResponse('OK');
		} catch (\Throwable $e) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteMember')]
	public function apiDeleteMember(string $token, int $memberid): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deleteMember($publicShareInfo['projectid'], $memberid);
			return new DataResponse('OK');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivDeleteMember(string $projectId, int $memberid): DataResponse {
		try {
			$this->localProjectService->deleteMember($projectId, $memberid);
			return new DataResponse('OK');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicDeleteProject')]
	public function apiDeleteProject(string $token): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deleteProject($publicShareInfo['projectid']);
			return new DataResponse(['message' => 'DELETED']);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	public function apiPrivDeleteProject(string $projectId): DataResponse {
		try {
			$this->localProjectService->deleteProject($projectId);
			return new DataResponse(['message' => 'DELETED']);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditMember')]
	public function apiEditMember(string $token, int $memberid,
		?string $name = null, ?float $weight = null, $activated = null,
		?string $color = null, ?string $userid = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		try {
			$member = $this->localProjectService->editMember(
				$publicShareInfo['projectid'], $memberid, $name, $userid, $weight, $activated, $color
			);
			return new DataResponse($member);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivEditMember(string $projectId, int $memberid, ?string $name = null, ?float $weight = null,
		$activated = null, ?string $color = null, ?string $userid = null): DataResponse {
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		try {
			$member = $this->localProjectService->editMember($projectId, $memberid, $name, $userid, $weight, $activated, $color);
			return new DataResponse($member);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetStats')]
	public function apiGetProjectStatistics(string $token, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $categoryId = null,
		?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null,
		?int $payerId = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$result = $this->localProjectService->getStatistics(
			$publicShareInfo['projectid'], $tsMin, $tsMax,
			$paymentModeId, $categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId,
			$payerId
		);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	public function apiPrivGetProjectStatistics(string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null,
		?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null,
		?int $payerId = null): DataResponse {
		$result = $this->localProjectService->getStatistics(
			$projectId, $tsMin, $tsMax, $paymentModeId,
			$categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId, $payerId
		);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetSettlement')]
	public function apiGetProjectSettlement(string $token, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$result = $this->localProjectService->getProjectSettlement(
			$publicShareInfo['projectid'], $centeredOn, $maxTimestamp
		);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	public function apiPrivGetProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$result = $this->localProjectService->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicAutoSettlement')]
	public function apiAutoSettlement(string $token, ?int $centeredOn = null,
		int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->autoSettlement(
				$publicShareInfo['projectid'], $centeredOn, $precision, $maxTimestamp
			);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	public function apiPrivAutoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		try {
			$this->localProjectService->autoSettlement($projectId, $centeredOn, $precision, $maxTimestamp);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicAddPM')]
	public function apiAddPaymentMode(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$result = $this->localProjectService->createPaymentMode(
			$publicShareInfo['projectid'], $name, $icon, $color, $order
		);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivAddPaymentMode(string $projectId, string $name, ?string $icon = null, ?string $color = null): DataResponse {
		$result = $this->localProjectService->createPaymentMode($projectId, $name, $icon, $color);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditPM')]
	public function apiEditPaymentMode(string $token, int $pmid, ?string $name = null,
		?string $icon = null, ?string $color = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$pm = $this->localProjectService->editPaymentMode(
				$publicShareInfo['projectid'], $pmid, $name, $icon, $color
			);
			return new DataResponse($pm);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSavePmOrder')]
	public function apiSavePaymentModeOrder(string $token, array $order): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->savePaymentModeOrder($publicShareInfo['projectid'], $order);
			return new DataResponse(true);
		} catch (\Throwable $e) {
			return new DataResponse(false, Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivEditPaymentMode(string $projectId, int $pmid, ?string $name = null,
		?string $icon = null, ?string $color = null): DataResponse {
		try {
			$pm = $this->localProjectService->editPaymentMode($projectId, $pmid, $name, $icon, $color);
			return new DataResponse($pm);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeletePM')]
	public function apiDeletePaymentMode(string $token, int $pmid): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deletePaymentMode($publicShareInfo['projectid'], $pmid);
			return new DataResponse($pmid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivDeletePaymentMode(string $projectId, int $pmid): DataResponse {
		try {
			$this->localProjectService->deletePaymentMode($projectId, $pmid);
			return new DataResponse($pmid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicAddCat')]
	public function apiAddCategory(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$result = $this->localProjectService->createCategory(
			$publicShareInfo['projectid'], $name, $icon, $color, $order
		);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivAddCategory(string $projectId, string $name, ?string $icon = null, ?string $color = null): DataResponse {
		$result = $this->localProjectService->createCategory($projectId, $name, $icon, $color);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCat')]
	public function apiEditCategory(string $token, int $categoryid, ?string $name = null,
		?string $icon = null, ?string $color = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$category = $this->localProjectService->editCategory(
				$publicShareInfo['projectid'], $categoryid, $name, $icon, $color
			);
			return new DataResponse($category);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSaveCatOrder')]
	public function apiSaveCategoryOrder(string $token, array $order): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->saveCategoryOrder($publicShareInfo['projectid'], $order);
			return new DataResponse(true);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivEditCategory(string $projectId, int $categoryid, ?string $name = null,
		?string $icon = null, ?string $color = null): DataResponse {
		try {
			$category = $this->localProjectService->editCategory($projectId, $categoryid, $name, $icon, $color);
			return new DataResponse($category);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCat')]
	public function apiDeleteCategory(string $token, int $categoryid): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deleteCategory($publicShareInfo['projectid'], $categoryid);
			return new DataResponse($categoryid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivDeleteCategory(string $projectId, int $categoryid): DataResponse {
		try {
			$this->localProjectService->deleteCategory($projectId, $categoryid);
			return new DataResponse($categoryid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicAddCur')]
	public function apiAddCurrency(string $token, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$result = $this->localProjectService->createCurrency($publicShareInfo['projectid'], $name, $rate);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivAddCurrency(string $projectId, string $name, float $rate): DataResponse {
		$result = $this->localProjectService->createCurrency($projectId, $name, $rate);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCur')]
	public function apiEditCurrency(string $token, int $currencyid, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$currency = $this->localProjectService->editCurrency(
				$publicShareInfo['projectid'], $currencyid, $name, $rate
			);
			return new DataResponse($currency);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivEditCurrency(string $projectId, int $currencyid, string $name, float $rate): DataResponse {
		try {
			$currency = $this->localProjectService->editCurrency($projectId, $currencyid, $name, $rate);
			return new DataResponse($currency);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCur')]
	public function apiDeleteCurrency(string $token, int $currencyid): DataResponse {
		$publicShareInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deleteCurrency($publicShareInfo['projectid'], $currencyid);
			return new DataResponse($currencyid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	public function apiPrivDeleteCurrency(string $projectId, int $currencyid): DataResponse {
		try {
			$this->localProjectService->deleteCurrency($projectId, $currencyid);
			return new DataResponse($currencyid);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Used by MoneyBuster to check if weblogin is valid
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function apiPing(): DataResponse {
		$response = new DataResponse([$this->userId]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}
}
