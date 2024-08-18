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
use OC\User\NoUserException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Attribute\CospendUserPermissions;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Service\ProjectService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
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
use OCP\IConfig;
use OCP\IL10N;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\PreConditionNotMetException;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * @psalm-import-type CospendFullProjectInfo from ResponseDefinitions
 * @psalm-import-type CospendProjectSettlement from ResponseDefinitions
 * @psalm-import-type CospendProjectStatistics from ResponseDefinitions
 * @psalm-import-type CospendMember from ResponseDefinitions
 * @psalm-import-type CospendBill from ResponseDefinitions
 * @psalm-import-type CospendPaymentMode from ResponseDefinitions
 * @psalm-import-type CospendCategory from ResponseDefinitions
 * @psalm-import-type CospendCurrency from ResponseDefinitions
 * @psalm-import-type CospendUserShare from ResponseDefinitions
 * @psalm-import-type CospendPublicShare from ResponseDefinitions
 * @psalm-import-type CospendGroupShare from ResponseDefinitions
 * @psalm-import-type CospendCircleShare from ResponseDefinitions
 */
class ApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IManager $shareManager,
		private IUserManager $userManager,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectService $projectService,
		private ActivityManager $activityManager,
		private IRootFolder $root,
		private IConfig $config,
		public ?string $userId
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * Delete user settings
	 *
	 * @return DataResponse<Http::STATUS_OK, '', array{}>
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Settings'])]
	public function deleteOptionsValues(): DataResponse {
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $key);
		}

		return new DataResponse('');
	}

	/**
	 * Save setting values
	 *
	 * Save setting values to the database for the current user
	 *
	 * @param array<string> $options Array of setting key/values to save
	 * @return DataResponse<Http::STATUS_OK, '', array{}>
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Settings'])]
	public function saveOptionValues(array $options): DataResponse {
		foreach ($options as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}

		return new DataResponse('');
	}

	/**
	 * Get setting values
	 *
	 * Get setting values from the database for the current user
	 *
	 * @return DataResponse<Http::STATUS_OK, array{values: array<string, string>}, array{}>
	 *
	 * 200: Values are returned
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Settings'])]
	public function getOptionsValues(): DataResponse {
		$ov = [];
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			$ov[$key] = $value;
		}

		return new DataResponse(['values' => $ov]);
	}

	/**
	 * Create a project
	 *
	 * Change for clients: response now contains full project info
	 *
	 * @param string $id
	 * @param string $name
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: Project successfully created
	 * 400: Failed to create project
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function createProject(string $id, string $name): DataResponse {
		$result = $this->projectService->createProject($name, $id, null, $this->userId);
		if (isset($result['id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVEL_ADMIN;
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get project list
	 *
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo[], array{}>
	 *
	 * 200: Project list
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getProjects(): DataResponse {
		return new DataResponse($this->projectService->getProjects($this->userId));
	}

	/**
	 * Get project information
	 *
	 * @param string $projectId
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>
	 * @throws Exception
	 *
	 * 200: Project info
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getProjectInfo(string $projectId): DataResponse {
		$projectInfo = $this->projectService->getProjectInfo($projectId);
		$projectInfo['myaccesslevel'] = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		return new DataResponse($projectInfo);
	}

	/**
	 * Edit a project
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string|null $autoExport
	 * @param string|null $currencyName
	 * @param bool|null $deletionDisabled
	 * @param string|null $categorySort
	 * @param string|null $paymentModeSort
	 * @param int|null $archivedTs
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The project was successfully update
	 * 400: Failed to edit the project
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function editProject(
		string  $projectId, ?string $name = null,
		?string $autoExport = null, ?string $currencyName = null, ?bool $deletionDisabled = null,
		?string $categorySort = null, ?string $paymentModeSort = null, ?int $archivedTs = null
	): DataResponse {
		$result = $this->projectService->editProject(
			$projectId, $name, null, $autoExport,
			$currencyName, $deletionDisabled, $categorySort, $paymentModeSort, $archivedTs
		);
		if (isset($result['success'])) {
			return new DataResponse('');
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a project
	 *
	 * @param string $projectId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: The project was successfully deleted
	 * 404: The project was not found
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function deleteProject(string $projectId): DataResponse {
		$result = $this->projectService->deleteProject($projectId);
		if (!isset($result['error'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse(['message' => $result['error']], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get statistics data
	 *
	 * @param string $projectId
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
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getProjectStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null
	): DataResponse {
		$result = $this->projectService->getProjectStatistics(
			$projectId, 'lowername', $tsMin, $tsMax, $paymentModeId,
			$categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId, $payerId
		);
		return new DataResponse($result);
	}

	/**
	 * Get settlement data
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn Member ID to center the settlement on. All suggested transactions will involve this member.
	 * @param int|null $maxTimestamp Settle at a precise date. So the member balances are all back to zero at this date.
	 * @return DataResponse<Http::STATUS_OK, CospendProjectSettlement, array{}>
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$result = $this->projectService->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
		return new DataResponse($result);
	}

	/**
	 * Automatic settlement plan
	 *
	 * Create reimbursement bills to automatically settle a project
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		$result = $this->projectService->autoSettlement($projectId, $centeredOn, $precision, $maxTimestamp);
		if (isset($result['success'])) {
			return new DataResponse('');
		} else {
			return new DataResponse(['message' => $result['message']], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Get members
	 *
	 * @param string $projectId
	 * @param int|null $lastChanged
	 * @return DataResponse<Http::STATUS_OK, CospendMember[], array{}>
	 *
	 * 200: List of members
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	public function getMembers(string $projectId, ?int $lastChanged = null): DataResponse {
		$members = $this->projectService->getMembers($projectId, null, $lastChanged);
		return new DataResponse($members);
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Member was successfully disabled or deleted
	 * 404: Member does not exist
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	public function deleteMember(string $projectId, int $memberId): DataResponse {
		$result = $this->projectService->deleteMember($projectId, $memberId);
		if (isset($result['success'])) {
			return new DataResponse('');
		}
		return new DataResponse($result, Http::STATUS_NOT_FOUND);
	}

	/**
	 * Edit a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @param string|null $name
	 * @param float|null $weight
	 * @param null $activated
	 * @param string|null $color
	 * @param string|null $userId
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_OK, CospendMember, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 *
	 * 200: Member was successfully edited (and deleted if it was disabled and wasn't ower of any bill)
	 * 400: Failed to edit the member
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	public function editMember(
		string $projectId, int $memberId, ?string $name = null, ?float $weight = null, $activated = null,
		?string $color = null, ?string $userId = null
	): DataResponse {
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		$result = $this->projectService->editMember($projectId, $memberId, $name, $userId, $weight, $activated, $color);
		if (empty($result)) {
			return new DataResponse(null);
		} elseif (isset($result['activated'])) {
			return new DataResponse($result);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a member
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $userId
	 * @param float $weight
	 * @param int $active
	 * @param string|null $color
	 * @return DataResponse<Http::STATUS_OK, CospendMember, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The member was successfully created
	 * 400: Failed to create the member
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	public function createMember(
		string $projectId, string $name, ?string $userId = null, float $weight = 1,
		int $active = 1, ?string $color = null
	): DataResponse {
		$result = $this->projectService->createMember($projectId, $name, $weight, $active !== 0, $color, $userId);
		if (!isset($result['error'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Edit a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
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
	 *
	 * 200: The bill was successfully edited
	 * 400: Failed to edit the bill
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function editBill(
		string $projectId, int $billId, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payedFor = null, ?float $amount = null, ?string $repeat = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?int $repeatAllActive = null, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null
	): DataResponse {
		$result = $this->projectService->editBill(
			$projectId, $billId, $date, $what, $payer, $payedFor,
			$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
			$repeatAllActive, $repeatUntil, $timestamp, $comment, $repeatFreq, null, $deleted
		);
		if (isset($result['edited_bill_id'])) {
			$billObj = $this->billMapper->find($billId);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_UPDATE,
				[]
			);

			return new DataResponse($result['edited_bill_id']);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Edit multiple bills
	 *
	 * @param string $projectId
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
	 *
	 * 200: The bills were successfully edited
	 * 400: Failed to edit the bills
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function editBills(
		string $projectId, array $billIds, ?int $categoryId = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payedFor = null,
		?float $amount = null, ?string $repeat = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $repeatAllActive = null, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null
	): DataResponse {
		$paymentModes = $this->projectService->getCategoriesOrPaymentModes($projectId, false);
		foreach ($billIds as $billId) {
			$result = $this->projectService->editBill(
				$projectId, $billId, $date, $what, $payer, $payedFor,
				$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
				$repeatAllActive, $repeatUntil, $timestamp, $comment,
				$repeatFreq, $paymentModes, $deleted
			);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billId);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					[]
				);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		}
		return new DataResponse($billIds);
	}

	/**
	 * Move a bill
	 *
	 * Move a bill from one project to another
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param string $toProjectId
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The bill was moved successfully
	 * 401: Current user is not allowed to create a bill in the target project
	 * 400: Failed to move the bill
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function moveBill(string $projectId, int $billId, string $toProjectId): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $toProjectId);
		if ($userAccessLevel < Application::ACCESS_LEVEL_PARTICIPANT) {
			return new DataResponse(['message' => $this->trans->t('You are not allowed to access the destination project')], Http::STATUS_UNAUTHORIZED);
		}

		// get current bill from mapper for the activity manager
		$oldBillObj = $this->billMapper->find($billId);

		// update the bill information
		$result = $this->projectService->moveBill($projectId, $billId, $toProjectId);

		if (!isset($result['inserted_id'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}

		$newBillObj = $this->billMapper->find($result ['inserted_id']);

		// add delete activity record
		$this->activityManager->triggerEvent(
			ActivityManager::COSPEND_OBJECT_BILL, $oldBillObj,
			ActivityManager::SUBJECT_BILL_DELETE, []
		);

		// add create activity record
		$this->activityManager->triggerEvent(
			ActivityManager::COSPEND_OBJECT_BILL, $newBillObj,
			ActivityManager::SUBJECT_BILL_CREATE, []
		);

		return new DataResponse($result['inserted_id']);
	}

	/**
	 * Repeat a bill
	 *
	 * Trigger bill repetition for a specific bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return DataResponse<Http::STATUS_OK, array<array{new_bill_id: int, date_orig: string, date_repeat: string, what: string, project_name: string}>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function repeatBill(string $projectId, int $billId): DataResponse {
		$bill = $this->billMapper->getBill($projectId, $billId);
		if ($bill === null) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		$result = $this->projectService->cronRepeatBills($billId);
		return new DataResponse($result);
	}

	/**
	 * Create a bill
	 *
	 * @param string $projectId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
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
	 *
	 * 200: The bill was successfully created
	 * 400: Failed to create the bill
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function createBill(
		string $projectId, ?string $date = null, ?string $what = null, ?int $payer = null, ?string $payedFor = null,
		?float $amount = null, ?string $repeat = null, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null
	): DataResponse {
		$result = $this->projectService->createBill(
			$projectId, $date, $what, $payer, $payedFor, $amount,
			$repeat, $paymentMode, $paymentModeId, $categoryId, $repeatAllActive,
			$repeatUntil, $timestamp, $comment, $repeatFreq
		);
		if (isset($result['inserted_id'])) {
			$billObj = $this->billMapper->find($result['inserted_id']);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_BILL, $billObj,
				ActivityManager::SUBJECT_BILL_CREATE,
				[]
			);
			return new DataResponse($result['inserted_id']);
		}
		return new DataResponse(['error' => $result], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Clear the trash bin
	 *
	 * @param string $projectId
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, '', array{}>
	 *
	 * 200: The trash bin was successfully cleared
	 * 400: Failed to clear the trash bin
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function clearTrashBin(string $projectId): DataResponse {
		try {
			$this->billMapper->deleteDeletedBills($projectId);
			return new DataResponse('');
		} catch (\Exception | \Throwable $e) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, '', array{}>
	 * @throws Exception
	 *
	 * 200: Bill was successfully deleted
	 * 403: This action is forbidden
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function deleteBill(string $projectId, int $billId, bool $moveToTrash = true): DataResponse {
		$billObj = null;
		if ($this->billMapper->getBill($projectId, $billId) !== null) {
			$billObj = $this->billMapper->find($billId);
		}

		$result = $this->projectService->deleteBill($projectId, $billId, false, $moveToTrash);
		if (isset($result['success'])) {
			if (!is_null($billObj)) {
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_DELETE,
					[]
				);
			}
			return new DataResponse('');
		} elseif (isset($result['message'])) {
			if ($result['message'] === 'forbidden') {
				return new DataResponse('', Http::STATUS_FORBIDDEN);
			} elseif ($result['message'] === 'not found') {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse('', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $projectId
	 * @param array<int> $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, '', array{}>
	 * @throws Exception
	 *
	 * 200: Bills were successfully deleted
	 * 403: This action is forbidden
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function deleteBills(string $projectId, array $billIds, bool $moveToTrash = true): DataResponse {
		foreach ($billIds as $billId) {
			if ($this->billMapper->getBill($projectId, $billId) === null) {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		}

		foreach ($billIds as $billId) {
			$billObj = $this->billMapper->find($billId);
			$result = $this->projectService->deleteBill($projectId, $billId, false, $moveToTrash);
			if (!isset($result['success'])) {
				if (isset($result['message'])) {
					if ($result['message'] === 'forbidden') {
						return new DataResponse('', Http::STATUS_FORBIDDEN);
					} elseif ($result['message'] === 'not found') {
						return new DataResponse('', Http::STATUS_NOT_FOUND);
					}
				}
				return new DataResponse('', Http::STATUS_BAD_REQUEST);
			} else {
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_DELETE,
					[]
				);
			}
		}
		return new DataResponse('');
	}

	/**
	 * Get a project's bill list
	 *
	 * @param string $projectId
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
	 *
	 * 200: The bill list was successfully obtained
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function getBills(
		string $projectId, ?int $lastChanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		if ($limit) {
			$bills = $this->billMapper->getBillsWithLimit(
				$projectId, null, null, null, $paymentModeId, $categoryId, null, null,
				$lastChanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
			);
		} else {
			$bills = $this->billMapper->getBills(
				$projectId, null, null, null, $paymentModeId, $categoryId, null, null,
				$lastChanged, null, $reverse, $payerId, $deleted
			);
		}
		$billIds = $this->billMapper->getAllBillIds($projectId, $deleted);
		$ts = (new DateTime())->getTimestamp();
		$result = [
			'nb_bills' => $this->billMapper->countBills($projectId, $payerId, $categoryId, $paymentModeId, $deleted),
			'bills' => $bills,
			'allBillIds' => $billIds,
			'timestamp' => $ts,
		];
		return new DataResponse($result);
	}

	/**
	 * Get a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return DataResponse<Http::STATUS_OK, CospendBill, array{}>|DataResponse<Http::STATUS_NOT_FOUND, '', array{}>
	 *
	 * 200: The bill was successfully obtained
	 * 404: The bill was not found
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function getBill(string $projectId, int $billId): DataResponse {
		$dbBillArray = $this->billMapper->getBill($projectId, $billId);
		if ($dbBillArray === null) {
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($dbBillArray);
	}

	/**
	 * Edit a shared access level
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param int $accessLevel
	 * @return DataResponse<Http::STATUS_OK, 'OK', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The shared access level was successfully edited
	 * 401: The current user cannot set this access level
	 * 400: Failed to edit the access level
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function editSharedAccessLevel(string $projectId, int $shId, int $accessLevel): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than their level (do not downgrade one)
		if ($userAccessLevel >= $accessLevel && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccessLevel($projectId, $shId, $accessLevel);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to give such shared access level')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a shared access
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param string|null $label
	 * @param string|null $password
	 * @return DataResponse<Http::STATUS_OK, 'OK', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The shared access was successfully edited
	 * 401: The current user is not allowed to edit this shared access
	 * 400: Failed to edit the access level
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function editSharedAccess(string $projectId, int $shId, ?string $label = null, ?string $password = null): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		// allow edition if user is at least participant and has greater or equal access level than target
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccess($projectId, $shId, $label, $password);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			}
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this shared access')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a payment mode
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 *
	 * 200: Payment mode was successfully created
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$result = $this->projectService->createPaymentMode($projectId, $name, $icon, $color, $order);
		return new DataResponse($result);
	}

	/**
	 * Edit a payment mode
	 *
	 * @param string $projectId
	 * @param int $pmId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse<Http::STATUS_OK, CospendPaymentMode, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 *
	 * 200: The payment mode was successfully edited
	 * 400: Failed to edit the payment mode
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	public function editPaymentMode(
		string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$result = $this->projectService->editPaymentMode($projectId, $pmId, $name, $icon, $color);
		if (isset($result['name'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $projectId
	 * @param array<array{order: int, id: int}> $order Array of objects, each object contains the order number and the payment mode ID
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, '', array{}>
	 *
	 * 200: The payment mode order was successfully saved
	 * 400: Failed to save the payment mode order
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	public function savePaymentModeOrder(string $projectId, array $order): DataResponse {
		if ($this->projectService->savePaymentModeOrder($projectId, $order)) {
			return new DataResponse('');
		}
		return new DataResponse('', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $projectId
	 * @param int $pmId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The payment mode was successfully deleted
	 * 400: Failed to delete the payment mode
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	public function deletePaymentMode(string $projectId, int $pmId): DataResponse {
		$result = $this->projectService->deletePaymentMode($projectId, $pmId);
		if (isset($result['success'])) {
			return new DataResponse('');
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Create a category
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 *
	 * 200: The category was successfully created
	 * 400: Failed to create the category
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$result = $this->projectService->createCategory($projectId, $name, $icon, $color, $order);
		return new DataResponse($result);
	}

	/**
	 * Edit a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse<Http::STATUS_OK, CospendCategory, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The category was successfully edited
	 * 400: Failed to edit the category
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$result = $this->projectService->editCategory($projectId, $categoryId, $name, $icon, $color);
		if (isset($result['name'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}


	/**
	 * Save categories order
	 *
	 * @param string $projectId
	 * @param array<array{order: int, id: int}> $order
	 * @return DataResponse<Http::STATUS_OK, true, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, false, array{}>
	 * @throws Exception
	 *
	 * 200: The category order was successfully saved
	 * 400: Failed to save the category order
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	public function saveCategoryOrder(string $projectId, array $order): DataResponse {
		if ($this->projectService->saveCategoryOrder($projectId, $order)) {
			return new DataResponse(true);
		}
		return new DataResponse(false, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The category was successfully deleted
	 * 400: Failed to delete the category
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	public function deleteCategory(string $projectId, int $categoryId): DataResponse {
		$result = $this->projectService->deleteCategory($projectId, $categoryId);
		if (isset($result['success'])) {
			return new DataResponse('');
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Create a currency
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws Exception
	 *
	 * 200: The currency was successfully created
	 * 400: Failed to create the currency
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Currencies'])]
	public function createCurrency(string $projectId, string $name, float $rate): DataResponse {
		$result = $this->projectService->createCurrency($projectId, $name, $rate);
		return new DataResponse($result);
	}

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, CospendCurrency, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The currency was successfully edited
	 * 400: Failed to edit the currency
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Currencies'])]
	public function editCurrency(string $projectId, int $currencyId, string $name, float $rate): DataResponse {
		$result = $this->projectService->editCurrency($projectId, $currencyId, $name, $rate);
		if (!isset($result['message'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The currency was successfully deleted
	 * 400: Failed to delete the currency
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Currencies'])]
	public function deleteCurrency(string $projectId, int $currencyId): DataResponse {
		$result = $this->projectService->deleteCurrency($projectId, $currencyId);
		if (isset($result['success'])) {
			return new DataResponse('');
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Create a user share
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param int $accessLevel
	 * @param bool $manuallyAdded
	 * @return DataResponse<Http::STATUS_OK, CospendUserShare, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The user share was successfully created
	 * 400: Failed to create the user share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createUserShare(
		string $projectId, string $userId, int $accessLevel = Application::ACCESS_LEVEL_PARTICIPANT,
		bool $manuallyAdded = true
	): DataResponse {
		$result = $this->projectService->createUserShare($projectId, $userId, $this->userId, $accessLevel, $manuallyAdded);
		if (!isset($result['message'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete a user share
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The user share was successfully deleted
	 * 400: Failed to delete the user share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteUserShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteUserShare($projectId, $shId, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('');
			}
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a public share link
	 *
	 * @param string $projectId
	 * @param string|null $label
	 * @param string|null $password
	 * @param int $accessLevel
	 * @return DataResponse<Http::STATUS_OK, CospendPublicShare, array{}>
	 * @throws Exception
	 *
	 * 200: The public share was successfully created
	 * 400: Failed to create the public share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createPublicShare(
		string $projectId, ?string $label = null, ?string $password = null, int $accessLevel = Application::ACCESS_LEVEL_PARTICIPANT
	): DataResponse {
		$result = $this->projectService->createPublicShare($projectId, $label, $password, $accessLevel);
		return new DataResponse($result);
	}

	/**
	 * Delete a public share link
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The public share was successfully deleted
	 * 400: Failed to delete the public share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deletePublicShare(string $projectId, int $shId): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deletePublicShare($projectId, $shId);
			if (isset($result['success'])) {
				return new DataResponse('');
			}
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a group share
	 *
	 * @param string $projectId
	 * @param string $groupId
	 * @param int $accessLevel
	 * @return DataResponse<Http::STATUS_OK, CospendGroupShare, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The group share was successfully created
	 * 400: Failed to create the group share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createGroupShare(string $projectId, string $groupId, int $accessLevel = Application::ACCESS_LEVEL_PARTICIPANT): DataResponse {
		$result = $this->projectService->createGroupShare($projectId, $groupId, $this->userId, $accessLevel);
		if (!isset($result['message'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete a group share
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The group share was successfully deleted
	 * 400: Failed to delete the group share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteGroupShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteGroupShare($projectId, $shId, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('');
			}
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a circle share
	 *
	 * @param string $projectId
	 * @param string $circleId
	 * @param int $accessLevel
	 * @return DataResponse<Http::STATUS_OK, CospendCircleShare, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws Exception
	 * @throws InitiatorNotFoundException
	 * @throws RequestBuilderException
	 *
	 * 200: The circle share was successfully created
	 * 400: Failed to create the circle share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createCircleShare(string $projectId, string $circleId, int $accessLevel = Application::ACCESS_LEVEL_PARTICIPANT): DataResponse {
		$result = $this->projectService->createCircleShare($projectId, $circleId, $this->userId, $accessLevel);
		if (!isset($result['message'])) {
			return new DataResponse($result);
		}
		return new DataResponse($result, Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Delete a circle share
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws Exception
	 *
	 * 200: The circle share was successfully deleted
	 * 400: Failed to delete the circle share
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteCircleShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteCircleShare($projectId, $shId, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('');
			}
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get or create file share
	 *
	 * Get or create a public file share from a node path
	 *
	 * @param string $path
	 * @return DataResponse<Http::STATUS_OK, array{token: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function getPublicFileShare(string $path): DataResponse {
		$cleanPath = str_replace(['../', '..\\'], '', $path);
		$userFolder = $this->root->getUserFolder($this->userId);
		if (!$userFolder->nodeExists($cleanPath)) {
			return new DataResponse(['message' => $this->trans->t('Access denied')], Http::STATUS_UNAUTHORIZED);
		}
		$file = $userFolder->get($cleanPath);
		if (!($file instanceof File)) {
			return new DataResponse(['message' => $this->trans->t('Access denied')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$file->isShareable()) {
			return new DataResponse(['message' => $this->trans->t('Access denied')], Http::STATUS_UNAUTHORIZED);
		}
		$shares = $this->shareManager->getSharesBy($this->userId,
			IShare::TYPE_LINK, $file, false, 1, 0);
		if (count($shares) > 0) {
			foreach($shares as $share) {
				if ($share->getPassword() === null) {
					$token = $share->getToken();
					break;
				}
			}
		} else {
			$share = $this->shareManager->newShare();
			$share->setNode($file);
			$share->setPermissions(Constants::PERMISSION_READ);
			$share->setShareType(IShare::TYPE_LINK);
			$share->setSharedBy($this->userId);
			$share = $this->shareManager->createShare($share);
			$token = $share->getToken();
		}
		return new DataResponse(['token' => $token]);
	}

	/**
	 * Export settlement plan
	 *
	 * Export settlement plan as CSV
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return DataResponse<Http::STATUS_OK, array{path: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function exportCsvSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$result = $this->projectService->exportCsvSettlement($projectId, $this->userId, $centeredOn, $maxTimestamp);
		if (isset($result['path'])) {
			return new DataResponse(['path' => $result['path']]);
		}
		return new DataResponse(['message' => $result['message']], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Export statistics
	 *
	 * Export statistics to CSV
	 *
	 * @param string $projectId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param int $showDisabled
	 * @param int|null $currencyId
	 * @return DataResponse<Http::STATUS_OK, array{path: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function exportCsvStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $category = null,
		?float $amountMin = null, ?float $amountMax = null,
		int $showDisabled = 1, ?int $currencyId = null
	): DataResponse {
		$result = $this->projectService->exportCsvStatistics(
			$projectId, $this->userId, $tsMin, $tsMax,
			$paymentModeId, $category, $amountMin, $amountMax,
			$showDisabled !== 0, $currencyId
		);
		if (isset($result['path'])) {
			return new DataResponse(['path' => $result['path']]);
		}
		return new DataResponse(['message' => $result['message']], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Export project
	 *
	 * Export project to CSV
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @return DataResponse<Http::STATUS_OK, array{path: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function exportCsvProject(string $projectId, ?string $name = null): DataResponse {
		$result = $this->projectService->exportCsvProject($projectId, $this->userId, $name);
		if (isset($result['path'])) {
			return new DataResponse(['path' => $result['path']]);
		}
		return new DataResponse(['message' => $result['message']], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Import project
	 *
	 * Import a project from a Cospend CSV file
	 *
	 * @param string $path
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \Throwable
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function importCsvProject(string $path): DataResponse {
		$result = $this->projectService->importCsvProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['project_id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVEL_ADMIN;
			return new DataResponse($projInfo);
		}
		return new DataResponse(['message' => $result['message']], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Import a SplitWise project
	 *
	 * Import a project from a SplitWise CSV file
	 *
	 * @param string $path
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function importSWProject(string $path): DataResponse {
		$result = $this->projectService->importSWProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['project_id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVEL_ADMIN;
			return new DataResponse($projInfo);
		}
		return new DataResponse(['message' => $result['message']], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Ping
	 *
	 * Used by MoneyBuster to check if weblogin is valid
	 * @return DataResponse<Http::STATUS_OK, array<?string>, array{}>
	 */
	#[NoAdminRequired]
	#[CORS]
	public function ping(): DataResponse {
		return new DataResponse([$this->userId]);
	}
}
