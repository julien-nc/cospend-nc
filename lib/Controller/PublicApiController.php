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

use DateTime;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Attribute\CospendPublicAuth;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Service\ProjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\OCSController;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;

class PublicApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectService $projectService,
		private ActivityManager $activityManager,
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * Delete a project
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicDeleteProject')]
	public function publicDeleteProject(string $token): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->deleteProject($publicShareInfo['projectid']);
		if (!isset($result['error'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse(['message' => $result['error']], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Clear the trashbin
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicClearTrashbin')]
	public function publicClearTrashbin(string $token): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		try {
			$this->billMapper->deleteDeletedBills($publicShareInfo['projectid']);
			return new DataResponse('');
		} catch (\Exception | \Throwable $e) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a bill
	 *
	 * @param string $token
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBill')]
	public function publicDeleteBill(string $token, int $billId, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$billObj = null;
		if ($this->billMapper->getBill($publicShareInfo['projectid'], $billId) !== null) {
			$billObj = $this->billMapper->find($billId);
		}

		$result = $this->projectService->deleteBill($publicShareInfo['projectid'], $billId, false, $moveToTrash);
		if (isset($result['success'])) {
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
		} else {
			return new DataResponse($result, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $token
	 * @param array $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBills')]
	public function publicDeleteBills(string $token, array $billIds, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if (is_null($publicShareInfo)) {
			$authorFullText = $this->trans->t('Guest access');
		} elseif ($publicShareInfo['label']) {
			$authorName = $publicShareInfo['label'];
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billid) {
			$billObj = null;
			if ($this->billMapper->getBill($publicShareInfo['projectid'], $billid) !== null) {
				$billObj = $this->billMapper->find($billid);
			}

			$result = $this->projectService->deleteBill($publicShareInfo['projectid'], $billid, false, $moveToTrash);
			if (!isset($result['success'])) {
				return new DataResponse($result, Http::STATUS_NOT_FOUND);
			} else {
				if (!is_null($billObj)) {
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_BILL, $billObj,
						ActivityManager::SUBJECT_BILL_DELETE,
						['author' => $authorFullText]
					);
				}
			}
		}
		return new DataResponse('OK');
	}

	/**
	 * Get project information
	 *
	 * @param string $token
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicProjectInfo')]
	public function publicGetProjectInfo(string $token): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$projectInfo = $this->projectService->getProjectInfo($publicShareInfo['projectid']);
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

	/**
	 * @param string $token
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param string $showDisabled
	 * @param int|null $currencyId
	 * @param int|null $payerId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetStats')]
	public function publicGetProjectStatistics(
		string $token, ?int $tsMin = null, ?int $tsMax = null,
		?int   $paymentModeId = null, ?int $categoryId = null,
		?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->getProjectStatistics(
			$publicShareInfo['projectid'], 'lowername', $tsMin, $tsMax,
			$paymentModeId, $categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId,
			$payerId
		);
		return new DataResponse($result);
	}

	/**
	 * Get project settlement info
	 *
	 * @param string $token
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetSettlement')]
	public function publicGetProjectSettlement(string $token, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->getProjectSettlement(
			$publicShareInfo['projectid'], $centeredOn, $maxTimestamp
		);
		return new DataResponse($result);
	}

	/**
	 * Get automatic settlement plan
	 *
	 * @param string $token
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicAutoSettlement')]
	public function publicAutoSettlement(
		string $token, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->autoSettlement(
			$publicShareInfo['projectid'], $centeredOn, $precision, $maxTimestamp
		);
		if (isset($result['success'])) {
			return new DataResponse('OK');
		} else {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Edit a project member
	 *
	 * @param string $token
	 * @param int $memberId
	 * @param string|null $name
	 * @param float|null $weight
	 * @param null $activated
	 * @param string|null $color
	 * @param string|null $userid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditMember')]
	public function publicEditMember(
		string $token, int $memberId, ?string $name = null, ?float $weight = null,
		$activated = null, ?string $color = null, ?string $userid = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		$result = $this->projectService->editMember(
			$publicShareInfo['projectid'], $memberId, $name, $userid, $weight, $activated, $color
		);
		if (count($result) === 0) {
			return new DataResponse(null);
		} elseif (array_key_exists('activated', $result)) {
			return new DataResponse($result);
		} else {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Edit a bill
	 *
	 * @param string $token
	 * @param int $billId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $categoryid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @param int|null $deleted
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicEditBill')]
	public function publicEditBill(
		string $token, int $billId, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->editBill(
			$publicShareInfo['projectid'], $billId, $date, $what, $payer, $payed_for,
			$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
			$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, null, $deleted
		);
		if (isset($result['edited_bill_id'])) {
			$billObj = $this->billMapper->find($billId);
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

			return new DataResponse($result['edited_bill_id']);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Edit multiple bills
	 *
	 * @param string $token
	 * @param array $billIds
	 * @param int|null $categoryid
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @param int|null $deleted
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicEditBills')]
	public function publicEditBills(
		string $token, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null, ?float $amount = null,
		?string $repeat = 'n', ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if (is_null($publicShareInfo)) {
			$authorFullText = $this->trans->t('Guest access');
		} elseif ($publicShareInfo['label']) {
			$authorName = $publicShareInfo['label'];
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		$paymentModes = $this->projectService->getCategoriesOrPaymentModes($publicShareInfo['projectid'], false);
		foreach ($billIds as $billid) {
			$result = $this->projectService->editBill(
				$publicShareInfo['projectid'], $billid, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, $paymentModes, $deleted
			);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billid);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					['author' => $authorFullText]
				);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		}
		return new DataResponse($billIds);
	}

	/**
	 * Trigger bill repetition for a specific bill
	 *
	 * @param string $token
	 * @param int $billId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicRepeatBill')]
	public function publicRepeatBill(string $token, int $billId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$bill = $this->billMapper->getBill($publicShareInfo['projectid'], $billId);
		if ($bill === null) {
			return new DataResponse('Bill not found', Http::STATUS_NOT_FOUND);
		}
		$result = $this->projectService->cronRepeatBills($billId);
		return new DataResponse($result);
	}

	/**
	 * Edit a project
	 *
	 * @param string $token
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $autoexport
	 * @param string|null $currencyname
	 * @param bool|null $deletion_disabled
	 * @param string|null $categorysort
	 * @param string|null $paymentmodesort
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicEditProject')]
	public function publicEditProject(
		string $token, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null,
		?bool $deletion_disabled = null, ?string $categorysort = null, ?string $paymentmodesort = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->editProject(
			$publicShareInfo['projectid'], $name, $contact_email, $autoexport,
			$currencyname, $deletion_disabled, $categorysort, $paymentmodesort
		);
		if (isset($result['success'])) {
			return new DataResponse('UPDATED');
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a bill
	 *
	 * @param string $token
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $categoryid
	 * @param int $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicCreateBill')]
	public function publicCreateBill(
		string $token, ?string $date = null, ?string $what = null, ?int $payer = null,
		?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->createBill(
			$publicShareInfo['projectid'], $date, $what, $payer, $payed_for, $amount,
			$repeat, $paymentmode, $paymentmodeid, $categoryid, $repeatallactive,
			$repeatuntil, $timestamp, $comment, $repeatfreq
		);
		if (isset($result['inserted_id'])) {
			$billObj = $this->billMapper->find($result['inserted_id']);
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
			return new DataResponse($result['inserted_id']);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a project member
	 *
	 * @param string $token
	 * @param string $name
	 * @param float $weight
	 * @param int $active
	 * @param string|null $color
	 * @param string|null $userid
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateMember')]
	public function publicCreateMember(
		string  $token, string $name, float $weight = 1, int $active = 1,
		?string $color = null, ?string $userid = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->createMember(
			$publicShareInfo['projectid'], $name, $weight, $active !== 0, $color, $userid
		);
		if (!isset($result['error'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get a project's bill list
	 *
	 * @param string $token
	 * @param int|null $lastchanged
	 * @param int|null $offset
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $payerId
	 * @param int|null $categoryId
	 * @param int|null $paymentModeId
	 * @param int|null $includeBillId
	 * @param string|null $searchTerm
	 * @param int|null $deleted
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBills')]
	public function publicGetBills(
		string $token, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if ($limit) {
			$bills = $this->billMapper->getBillsWithLimit(
				$publicShareInfo['projectid'], null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastchanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
			);
		} else {
			$bills = $this->billMapper->getBills(
				$publicShareInfo['projectid'], null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastchanged, null, $reverse, $payerId, $deleted
			);
		}
		$billIds = $this->projectService->getAllBillIds($publicShareInfo['projectid'], $deleted);
		$ts = (new DateTime())->getTimestamp();
		$result = [
			'nb_bills' => $this->billMapper->countBills(
				$publicShareInfo['projectid'], $payerId, $categoryId, $paymentModeId, $deleted
			),
			'bills' => $bills,
			'allBillIds' => $billIds,
			'timestamp' => $ts,
		];
		return new DataResponse($result);
	}

	/**
	 * Get a project's member list
	 *
	 * @param string $token
	 * @param int|null $lastChanged
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetMembers')]
	public function publicGetMembers(string $token, ?int $lastChanged = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$members = $this->projectService->getMembers($publicShareInfo['projectid'], null, $lastChanged);
		return new DataResponse($members);
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $token
	 * @param int $memberId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteMember')]
	public function publicDeleteMember(string $token, int $memberId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->deleteMember($publicShareInfo['projectid'], $memberId);
		if (isset($result['success'])) {
			return new DataResponse('OK');
		} else {
			return new DataResponse($result, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Create a payment mode
	 *
	 * @param string $token
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreatePaymentMode')]
	public function publicCreatePaymentMode(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->createPaymentMode(
			$publicShareInfo['projectid'], $name, $icon, $color, $order
		);
		return new DataResponse($result);
	}

	/**
	 * Edit a payment mode
	 *
	 * @param string $token
	 * @param int $pmId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditPaymentMode')]
	public function publicEditPaymentMode(
		string $token, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->editPaymentMode(
			$publicShareInfo['projectid'], $pmId, $name, $icon, $color
		);
		if (isset($result['name'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $token
	 * @param array $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSavePMOrder')]
	public function publicSavePaymentModeOrder(string $token, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if ($this->projectService->savePaymentModeOrder($publicShareInfo['projectid'], $order)) {
			return new DataResponse(true);
		} else {
			return new DataResponse(false, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $token
	 * @param int $pmId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeletePM')]
	public function publicDeletePaymentMode(string $token, int $pmId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->deletePaymentMode($publicShareInfo['projectid'], $pmId);
		if (isset($result['success'])) {
			return new DataResponse($pmId);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a category
	 *
	 * @param string $token
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateCat')]
	public function publicCreateCategory(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->createCategory(
			$publicShareInfo['projectid'], $name, $icon, $color, $order
		);
		return new DataResponse($result);
	}

	/**
	 * Edit a category
	 *
	 * @param string $token
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCat')]
	public function publicEditCategory(
		string  $token, int $categoryId,
		?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->editCategory(
			$publicShareInfo['projectid'], $categoryId, $name, $icon, $color
		);
		if (isset($result['name'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Save categories order
	 *
	 * @param string $token
	 * @param array $order
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSaveCatOrder')]
	public function publicSaveCategoryOrder(string $token, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if ($this->projectService->saveCategoryOrder($publicShareInfo['projectid'], $order)) {
			return new DataResponse(true);
		} else {
			return new DataResponse(false, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $token
	 * @param int $categoryId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCat')]
	public function publicDeleteCategory(string $token, int $categoryId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->deleteCategory($publicShareInfo['projectid'], $categoryId);
		if (isset($result['success'])) {
			return new DataResponse($categoryId);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a currency
	 *
	 * @param string $token
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateCur')]
	public function publicCreateCurrency(string $token, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->createCurrency($publicShareInfo['projectid'], $name, $rate);
		return new DataResponse($result);
	}

	/**
	 * Edit a currency
	 *
	 * @param string $token
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCur')]
	public function publicEditCurrency(string $token, int $currencyId, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->editCurrency(
			$publicShareInfo['projectid'], $currencyId, $name, $rate
		);
		if (!isset($result['message'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete a currency
	 *
	 * @param string $token
	 * @param int $currencyId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCur')]
	public function publicDeleteCurrency(string $token, int $currencyId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$result = $this->projectService->deleteCurrency($publicShareInfo['projectid'], $currencyId);
		if (isset($result['success'])) {
			return new DataResponse($currencyId);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}
}
