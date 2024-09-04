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
use OCA\Cospend\Exception\CospendBasicException;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Service\LocalProjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\OCSController;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-import-type CospendBill from ResponseDefinitions
 * @psalm-import-type CospendFullPublicProjectInfo from ResponseDefinitions
 * @psalm-import-type CospendProjectSettlement from ResponseDefinitions
 * @psalm-import-type CospendProjectStatistics from ResponseDefinitions
 * @psalm-import-type CospendMember from ResponseDefinitions
 * @psalm-import-type CospendCurrency from ResponseDefinitions
 * @psalm-import-type CospendPaymentMode from ResponseDefinitions
 * @psalm-import-type CospendCategory from ResponseDefinitions
 */
class PublicApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private BillMapper $billMapper,
		private LocalProjectService $localProjectService,
		private ActivityManager $activityManager,
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * Delete a project
	 *
	 * @param string $token
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicDeleteProject')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicDeleteProject(string $token): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deleteProject($publicShareInfo['projectid']);
			return new DataResponse(['message' => 'DELETED']);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Clear the trash bin
	 *
	 * @param string $token
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, '', array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicClearTrashBin')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicClearTrashBin(string $token): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
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
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, '', array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicDeleteBill(string $token, int $billId, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$billObj = null;
		if ($this->billMapper->getBill($publicShareInfo['projectid'], $billId) !== null) {
			$billObj = $this->billMapper->find($billId);
		}

		try {
			$this->localProjectService->deleteBill($publicShareInfo['projectid'], $billId, false, $moveToTrash);
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
			return new DataResponse('');
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], $e->getCode());
		}
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $token
	 * @param array<int> $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, '', array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBills')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicDeleteBills(string $token, array $billIds, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		if (is_null($publicShareInfo)) {
			$authorFullText = $this->trans->t('Guest access');
		} elseif ($publicShareInfo['label']) {
			$authorName = $publicShareInfo['label'];
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billId) {
			if ($this->billMapper->getBill($publicShareInfo['projectid'], $billId) === null) {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		}

		foreach ($billIds as $billId) {
			$billObj = $this->billMapper->find($billId);
			try {
				$this->localProjectService->deleteBill($publicShareInfo['projectid'], $billId, false, $moveToTrash);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_DELETE,
					['author' => $authorFullText]
				);
			} catch (\Throwable $e) {
				return new DataResponse(['error' => $e->getMessage()], $e->getCode());
			}
		}
		return new DataResponse('');
	}

	/**
	 * Get project information
	 *
	 * @param string $token
	 * @return DataResponse<Http::STATUS_OK, CospendFullPublicProjectInfo, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicProjectInfo')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicGetProjectInfo(string $token): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
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

	/**
	 * Get statistics data
	 *
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
	 * @return DataResponse<Http::STATUS_OK, CospendProjectStatistics, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetStats')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicGetProjectStatistics(
		string $token, ?int $tsMin = null, ?int $tsMax = null,
		?int   $paymentModeId = null, ?int $categoryId = null,
		?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->getStatistics(
			$publicShareInfo['projectid'], $tsMin, $tsMax,
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
	 * @return DataResponse<Http::STATUS_OK, CospendProjectSettlement, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetSettlement')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicGetProjectSettlement(string $token, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->getProjectSettlement(
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
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicAutoSettlement')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicAutoSettlement(
		string $token, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->autoSettlement(
			$publicShareInfo['projectid'], $centeredOn, $precision, $maxTimestamp
		);
		if (isset($result['success'])) {
			return new DataResponse('');
		} else {
			return new DataResponse(['message' => $result['message']], Http::STATUS_FORBIDDEN);
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
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param int|null $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @param int|null $deleted
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicEditBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicEditBill(
		string $token, int $billId, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payedFor = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?int $repeatAllActive = null,
		?string $repeatUntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatFreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->editBill(
			$publicShareInfo['projectid'], $billId, $date, $what, $payer, $payedFor,
			$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
			$repeatAllActive, $repeatUntil, $timestamp, $comment, $repeatFreq, $deleted
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
	 * @param array<int> $billIds
	 * @param int|null $categoryId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @param int|null $deleted
	 * @return DataResponse<Http::STATUS_OK, int[], array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicEditBills')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicEditBills(
		string $token, array $billIds, ?int $categoryId = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payedFor = null, ?float $amount = null,
		?string $repeat = 'n', ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $repeatAllActive = null,
		?string $repeatUntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatFreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		if (is_null($publicShareInfo)) {
			$authorFullText = $this->trans->t('Guest access');
		} elseif ($publicShareInfo['label']) {
			$authorName = $publicShareInfo['label'];
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billId) {
			$result = $this->localProjectService->editBill(
				$publicShareInfo['projectid'], $billId, $date, $what, $payer, $payedFor,
				$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
				$repeatAllActive, $repeatUntil, $timestamp, $comment, $repeatFreq, $deleted
			);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billId);
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
	 * @return DataResponse<Http::STATUS_OK, array<array{new_bill_id: int, date_orig: string, date_repeat: string, what: string, project_name: string}>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicRepeatBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicRepeatBill(string $token, int $billId): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$bill = $this->billMapper->getBill($publicShareInfo['projectid'], $billId);
		if ($bill === null) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		$result = $this->localProjectService->cronRepeatBills($billId);
		return new DataResponse($result);
	}

	/**
	 * Edit a project
	 *
	 * @param string $token
	 * @param string|null $name
	 * @param string|null $autoExport
	 * @param string|null $currencyName
	 * @param bool|null $deletionDisabled
	 * @param string|null $categorySort
	 * @param string|null $paymentModeSort
	 * @param int|null $archivedTs
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicEditProject')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicEditProject(
		string $token, ?string $name = null,
		?string $autoExport = null, ?string $currencyName = null, ?bool $deletionDisabled = null,
		?string $categorySort = null, ?string $paymentModeSort = null, ?int $archivedTs = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		try {
			$this->localProjectService->editProject(
				$publicShareInfo['projectid'], $name, null, $autoExport,
				$currencyName, $deletionDisabled, $categorySort, $paymentModeSort, $archivedTs
			);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a bill
	 *
	 * @param string $token
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param int $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: array<string, string>}, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicCreateBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicCreateBill(
		string $token, ?string $date = null, ?string $what = null, ?int $payer = null,
		?string $payedFor = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		try {
			$insertedId = $this->localProjectService->createBill(
				$publicShareInfo['projectid'], $date, $what, $payer, $payedFor, $amount,
				$repeat, $paymentMode, $paymentModeId, $categoryId, $repeatAllActive,
				$repeatUntil, $timestamp, $comment, $repeatFreq
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

	/**
	 * Get a project's bill list
	 *
	 * @param string $token
	 * @param int|null $lastChanged
	 * @param int|null $offset
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $payerId
	 * @param int|null $categoryId
	 * @param int|null $paymentModeId
	 * @param int|null $includeBillId
	 * @param string|null $searchTerm
	 * @param int|null $deleted
	 * @return DataResponse<Http::STATUS_OK, array{nb_bills: int, allBillIds: int[], timestamp: int, bills: CospendBill[]}, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBills')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicGetBills(
		string $token, ?int $lastChanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		if ($limit) {
			$bills = $this->billMapper->getBillsWithLimit(
				$publicShareInfo['projectid'], null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastChanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
			);
		} else {
			$bills = $this->billMapper->getBillsClassic(
				$publicShareInfo['projectid'], null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastChanged, null, $reverse, $payerId, $deleted
			);
		}
		$billIds = $this->billMapper->getAllBillIds($publicShareInfo['projectid'], $deleted);
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
	 * @param string $token
	 * @param int $billId
	 * @return DataResponse<Http::STATUS_OK, CospendBill, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: The bill was successfully obtained
	 * 404: The bill was not found
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicGetBill(string $token, int $billId): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$dbBillArray = $this->billMapper->getBill($publicShareInfo['projectid'], $billId);
		if ($dbBillArray === null) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($dbBillArray);
	}

	/**
	 * Get a project's member list
	 *
	 * @param string $token
	 * @param int|null $lastChanged
	 * @return DataResponse<Http::STATUS_OK, CospendMember[], array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetMembers')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Members'])]
	public function publicGetMembers(string $token, ?int $lastChanged = null): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$members = $this->localProjectService->getMembers($publicShareInfo['projectid'], null, $lastChanged);
		return new DataResponse($members);
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $token
	 * @param int $memberId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteMember')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Members'])]
	public function publicDeleteMember(string $token, int $memberId): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		try {
			$this->localProjectService->deleteMember($publicShareInfo['projectid'], $memberId);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
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
	 * @param string|null $userId
	 * @return DataResponse<Http::STATUS_OK, ?CospendMember, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditMember')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Members'])]
	public function publicEditMember(
		string $token, int $memberId, ?string $name = null, ?float $weight = null,
		$activated = null, ?string $color = null, ?string $userId = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		try {
			$member = $this->localProjectService->editMember(
				$publicShareInfo['projectid'], $memberId, $name, $userId, $weight, $activated, $color
			);
			return new DataResponse($member);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
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
	 * @param string|null $userId
	 * @return DataResponse<Http::STATUS_OK, CospendMember, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, string, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateMember')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Members'])]
	public function publicCreateMember(
		string $token, string $name, float $weight = 1, int $active = 1,
		?string $color = null, ?string $userId = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		try {
			$member = $this->localProjectService->createMember(
				$publicShareInfo['projectid'], $name, $weight, $active !== 0, $color, $userId
			);
			return new DataResponse($member);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data['error'] ?? '', $e->getCode());
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreatePaymentMode')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Payment-modes'])]
	public function publicCreatePaymentMode(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->createPaymentMode(
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
	 * @return DataResponse<Http::STATUS_OK, CospendPaymentMode, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditPaymentMode')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Payment-modes'])]
	public function publicEditPaymentMode(
		string $token, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->editPaymentMode(
			$publicShareInfo['projectid'], $pmId, $name, $icon, $color
		);
		if (isset($result['name'])) {
			/** @var CospendPaymentMode $pm */
			$pm = $result;
			return new DataResponse($pm);
		} else {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $token
	 * @param array<array{order: int, id: int}> $order
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_FORBIDDEN, '', array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSavePMOrder')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Payment-modes'])]
	public function publicSavePaymentModeOrder(string $token, array $order): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		if ($this->localProjectService->savePaymentModeOrder($publicShareInfo['projectid'], $order)) {
			return new DataResponse('');
		} else {
			return new DataResponse('', Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $token
	 * @param int $pmId
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeletePM')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Payment-modes'])]
	public function publicDeletePaymentMode(string $token, int $pmId): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->deletePaymentMode($publicShareInfo['projectid'], $pmId);
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateCat')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Categories'])]
	public function publicCreateCategory(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->createCategory(
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
	 * @return DataResponse<Http::STATUS_OK, CospendCategory, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCat')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Categories'])]
	public function publicEditCategory(
		string  $token, int $categoryId,
		?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->editCategory(
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
	 * @param string $token Project share token
	 * @param array<array{order: int, id: int}> $order Array describing the categories ordering
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_FORBIDDEN, '', array{}>
	 * @throws Exception
	 *
	 * 200: Categories order is saved
	 * 403: Not saved
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSaveCatOrder')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Categories'])]
	public function publicSaveCategoryOrder(string $token, array $order): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		if ($this->localProjectService->saveCategoryOrder($publicShareInfo['projectid'], $order)) {
			return new DataResponse('');
		} else {
			return new DataResponse('', Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $token Project share token
	 * @param int $categoryId Category ID
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: Category is deleted
	 * 400: Category is not deleted
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCat')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Categories'])]
	public function publicDeleteCategory(string $token, int $categoryId): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->deleteCategory($publicShareInfo['projectid'], $categoryId);
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateCur')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Currencies'])]
	public function publicCreateCurrency(string $token, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->createCurrency($publicShareInfo['projectid'], $name, $rate);
		return new DataResponse($result);
	}

	/**
	 * Edit a currency
	 *
	 * @param string $token
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, CospendCurrency, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCur')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Currencies'])]
	public function publicEditCurrency(string $token, int $currencyId, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->editCurrency(
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
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCur')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Currencies'])]
	public function publicDeleteCurrency(string $token, int $currencyId): DataResponse {
		$publicShareInfo = $this->localProjectService->getShareInfoFromShareToken($token);
		$result = $this->localProjectService->deleteCurrency($publicShareInfo['projectid'], $currencyId);
		if (isset($result['success'])) {
			return new DataResponse('');
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}
}
