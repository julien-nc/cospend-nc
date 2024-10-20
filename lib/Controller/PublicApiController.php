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
use OCA\Cospend\Db\ShareMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Service\LocalProjectService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
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

	public string $projectId;

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ShareMapper $shareMapper,
		private LocalProjectService $localProjectService,
		private ActivityManager $activityManager,
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * Delete a project
	 *
	 * @param string $token
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[BruteForceProtection(action: 'CospendPublicDeleteProject')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicDeleteProject(string $token): DataResponse {
		try {
			$this->localProjectService->deleteProject($this->projectId);
			return new DataResponse(['message' => 'DELETED']);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Clear the trash bin
	 *
	 * @param string $token
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, '', array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicClearTrashBin')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicClearTrashBin(string $token): DataResponse {
		try {
			$this->billMapper->deleteDeletedBills($this->projectId);
			return new DataResponse('');
		} catch (\Exception|\Throwable $e) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a bill
	 *
	 * @param string $token
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicDeleteBill(string $token, int $billId, bool $moveToTrash = true): DataResponse {
		$share = $this->shareMapper->getLinkOrFederatedShareByToken($token);
		$billObj = null;
		if ($this->billMapper->getBill($this->projectId, $billId) !== null) {
			$billObj = $this->billMapper->find($billId);
		}

		try {
			$this->localProjectService->deleteBill($this->projectId, $billId, false, $moveToTrash);
			if (!is_null($billObj)) {
				if ($share->getLabel()) {
					$authorName = $share->getLabel();
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
		} catch (CospendBasicException $e) {
			if ($e->getCode() === Http::STATUS_NOT_FOUND) {
				return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
			}
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $token
	 * @param array<int> $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicDeleteBills')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicDeleteBills(string $token, array $billIds, bool $moveToTrash = true): DataResponse {
		$share = $this->shareMapper->getLinkOrFederatedShareByToken($token);
		if ($share->getLabel()) {
			$authorName = $share->getLabel();
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billId) {
			if ($this->billMapper->getBill($this->projectId, $billId) === null) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}

		foreach ($billIds as $billId) {
			$billObj = $this->billMapper->find($billId);
			try {
				$this->localProjectService->deleteBill($this->projectId, $billId, false, $moveToTrash);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_DELETE,
					['author' => $authorFullText]
				);
			} catch (CospendBasicException $e) {
				if ($e->getCode() === Http::STATUS_NOT_FOUND) {
					return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
				}
				return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
			}
		}
		return new DataResponse('');
	}

	/**
	 * Get project information
	 *
	 * @param string $token
	 * @return DataResponse<Http::STATUS_OK, CospendFullPublicProjectInfo, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 * @throws CospendBasicException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicProjectInfo')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicGetProjectInfo(string $token): DataResponse {
		$share = $this->shareMapper->getLinkOrFederatedShareByToken($token);
		$projectInfo = $this->localProjectService->getProjectInfo($share->getProjectId());
		if ($projectInfo !== null) {
			unset($projectInfo['userid']);
			// set the visible access level for frontend
			$projectInfo['myaccesslevel'] = $share->getAccessLevel() ;
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
		?int $paymentModeId = null, ?int $categoryId = null,
		?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null,
	): DataResponse {
		$result = $this->localProjectService->getStatistics(
			$this->projectId, $tsMin, $tsMax,
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
		$result = $this->localProjectService->getProjectSettlement(
			$this->projectId, $centeredOn, $maxTimestamp
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
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicAutoSettlement')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Projects'])]
	public function publicAutoSettlement(
		string $token, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null,
	): DataResponse {
		try {
			$this->localProjectService->autoSettlement(
				$this->projectId, $centeredOn, $precision, $maxTimestamp
			);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
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
		?int $repeatFreq = null, ?int $deleted = null,
	): DataResponse {
		$share = $this->shareMapper->getLinkOrFederatedShareByToken($token);
		try {
			$this->localProjectService->editBill(
				$this->projectId, $billId, $date, $what, $payer, $payedFor,
				$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
				$repeatAllActive, $repeatUntil, $timestamp, $comment, $repeatFreq, $deleted
			);
			$billObj = $this->billMapper->find($billId);
			if ($share->getLabel()) {
				$authorName = $share->getLabel();
				$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
			} else {
				$authorFullText = $this->trans->t('Share link');
			}
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_UPDATE,
				['author' => $authorFullText]
			);

			return new DataResponse($billId);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
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
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
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
		?int $repeatAllActive = null, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null,
	): DataResponse {
		$share = $this->shareMapper->getLinkOrFederatedShareByToken($token);
		if ($share->getLabel()) {
			$authorName = $share->getLabel();
			$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
		} else {
			$authorFullText = $this->trans->t('Share link');
		}
		foreach ($billIds as $billId) {
			try {
				$this->localProjectService->editBill(
					$this->projectId, $billId, $date, $what, $payer, $payedFor,
					$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
					$repeatAllActive, $repeatUntil, $timestamp, $comment, $repeatFreq, $deleted
				);
				$billObj = $this->billMapper->find($billId);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					['author' => $authorFullText]
				);
			} catch (CospendBasicException $e) {
				return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
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
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[BruteForceProtection(action: 'CospendPublicRepeatBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicRepeatBill(string $token, int $billId): DataResponse {
		$bill = $this->billMapper->getBill($this->projectId, $billId);
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
		?string $categorySort = null, ?string $paymentModeSort = null, ?int $archivedTs = null,
	): DataResponse {
		try {
			$this->localProjectService->editProject(
				$this->projectId, $name, null, $autoExport,
				$currencyName, $deletionDisabled, $categorySort, $paymentModeSort, $archivedTs
			);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
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
		?string $comment = null, ?int $repeatFreq = null,
	): DataResponse {
		$share = $this->shareMapper->getLinkOrFederatedShareByToken($token);
		try {
			$insertedId = $this->localProjectService->createBill(
				$this->projectId, $date, $what, $payer, $payedFor, $amount,
				$repeat, $paymentMode, $paymentModeId, $categoryId, $repeatAllActive,
				$repeatUntil, $timestamp, $comment, $repeatFreq
			);
			$billObj = $this->billMapper->find($insertedId);
			if ($share->getLabel()) {
				$authorName = $share->getLabel();
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
		?string $searchTerm = null, ?int $deleted = 0,
	): DataResponse {
		if ($limit) {
			$bills = $this->billMapper->getBillsWithLimit(
				$this->projectId, null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastChanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
			);
		} else {
			$bills = $this->billMapper->getBillsClassic(
				$this->projectId, null, null,
				null, $paymentModeId, $categoryId, null, null,
				$lastChanged, null, $reverse, $payerId, $deleted
			);
		}
		$billIds = $this->billMapper->getAllBillIds($this->projectId, $deleted);
		$ts = (new DateTime())->getTimestamp();
		$result = [
			'nb_bills' => $this->billMapper->countBills(
				$this->projectId, $payerId, $categoryId, $paymentModeId, $deleted
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
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[BruteForceProtection(action: 'CospendPublicGetBill')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Bills'])]
	public function publicGetBill(string $token, int $billId): DataResponse {
		$dbBillArray = $this->billMapper->getBill($this->projectId, $billId);
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
		$members = $this->localProjectService->getMembers($this->projectId, null, $lastChanged);
		return new DataResponse($members);
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $token
	 * @param int $memberId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteMember')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Members'])]
	public function publicDeleteMember(string $token, int $memberId): DataResponse {
		try {
			$this->localProjectService->deleteMember($this->projectId, $memberId);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
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
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditMember')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Members'])]
	public function publicEditMember(
		string $token, int $memberId, ?string $name = null, ?float $weight = null,
		$activated = null, ?string $color = null, ?string $userId = null,
	): DataResponse {
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		try {
			$member = $this->localProjectService->editMember(
				$this->projectId, $memberId, $name, $userId, $weight, $activated, $color
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
		?string $color = null, ?string $userId = null,
	): DataResponse {
		try {
			$member = $this->localProjectService->createMember(
				$this->projectId, $name, $weight, $active !== 0, $color, $userId
			);
			return new DataResponse($member);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data['error'] ?? '', Http::STATUS_BAD_REQUEST);
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
		$insertedId = $this->localProjectService->createPaymentMode(
			$this->projectId, $name, $icon, $color, $order
		);
		return new DataResponse($insertedId);
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
		string $token, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null,
	): DataResponse {
		try {
			$pm = $this->localProjectService->editPaymentMode(
				$this->projectId, $pmId, $name, $icon, $color
			);
			return new DataResponse($pm);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $token
	 * @param array<array{order: int, id: int}> $order
	 * @return DataResponse<Http::STATUS_OK, '', array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicSavePMOrder')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Payment-modes'])]
	public function publicSavePaymentModeOrder(string $token, array $order): DataResponse {
		$this->localProjectService->savePaymentModeOrder($this->projectId, $order);
		return new DataResponse('');
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $token
	 * @param int $pmId
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeletePM')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Payment-modes'])]
	public function publicDeletePaymentMode(string $token, int $pmId): DataResponse {
		try {
			$this->localProjectService->deletePaymentMode($this->projectId, $pmId);
			return new DataResponse($pmId);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
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
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateCat')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Categories'])]
	public function publicCreateCategory(string $token, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$insertedId = $this->localProjectService->createCategory(
			$this->projectId, $name, $icon, $color, $order
		);
		return new DataResponse($insertedId);
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
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCat')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Categories'])]
	public function publicEditCategory(
		string $token, int $categoryId,
		?string $name = null, ?string $icon = null, ?string $color = null,
	): DataResponse {
		try {
			$category = $this->localProjectService->editCategory(
				$this->projectId, $categoryId, $name, $icon, $color
			);
			return new DataResponse($category);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Save categories order
	 *
	 * @param string $token Project share token
	 * @param array<array{order: int, id: int}> $order Array describing the categories ordering
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
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
		try {
			$this->localProjectService->saveCategoryOrder($this->projectId, $order);
			return new DataResponse('');
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $token Project share token
	 * @param int $categoryId Category ID
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
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
		try {
			$this->localProjectService->deleteCategory($this->projectId, $categoryId);
			return new DataResponse($categoryId);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a currency
	 *
	 * @param string $token
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicCreateCur')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Currencies'])]
	public function publicCreateCurrency(string $token, string $name, float $rate): DataResponse {
		try {
			$result = $this->localProjectService->createCurrency($this->projectId, $name, $rate);
			return new DataResponse($result);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Edit a currency
	 *
	 * @param string $token
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, CospendCurrency, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicEditCur')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Currencies'])]
	public function publicEditCurrency(string $token, int $currencyId, string $name, float $rate): DataResponse {
		try {
			$currency = $this->localProjectService->editCurrency(
				$this->projectId, $currencyId, $name, $rate
			);
			return new DataResponse($currency);
		} catch (CospendBasicException $e) {
			if ($e->getCode() == Http::STATUS_FORBIDDEN) {
				return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
			} elseif ($e->getCode() == Http::STATUS_NOT_FOUND) {
				return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
			}
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a currency
	 *
	 * @param string $token
	 * @param int $currencyId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	#[CospendPublicAuth(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[BruteForceProtection(action: 'CospendPublicDeleteCur')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Public-API_Currencies'])]
	public function publicDeleteCurrency(string $token, int $currencyId): DataResponse {
		try {
			$this->localProjectService->deleteCurrency($this->projectId, $currencyId);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
