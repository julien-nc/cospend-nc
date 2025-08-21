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
use OCA\Cospend\Attribute\CospendUserPermissions;
use OCA\Cospend\Attribute\SupportFederatedProject;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Service\CospendService;
use OCA\Cospend\Service\IProjectService;
use OCA\Cospend\Service\LocalProjectService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
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

	public IProjectService $projectService;

	public function __construct(
		string $appName,
		IRequest $request,
		private IManager $shareManager,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectMapper $projectMapper,
		private LocalProjectService $localProjectService,
		private CospendService $cospendService,
		private ActivityManager $activityManager,
		private IRootFolder $root,
		public ?string $userId,
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
		// this can be set to a FederatedProjectService instance by the FederationMiddleware
		$this->projectService = $localProjectService;
	}

	/**
	 * @param ClientException $e
	 * @return DataResponse<Http::STATUS_FAILED_DEPENDENCY, array, array{}>
	 */
	private static function getResponseFromClientException(ClientException $e): DataResponse {
		$response = $e->getResponse();
		$statusCode = $response->getStatusCode();
		$body = $response->getBody();
		$parsedBody = json_decode($body, true);
		if (!isset($parsedBody['ocs']['data'])) {
			$data = ['error' => 'unknown error'];
		} elseif (is_array($parsedBody['ocs']['data'])) {
			$data = $parsedBody['ocs']['data'];
		} elseif (is_string($parsedBody['ocs']['data'])) {
			$data = ['error' => $parsedBody['ocs']['data']];
		} else {
			$data = ['raw_body' => $body];
		}
		$data['status_code'] = $statusCode;
		return new DataResponse($data, Http::STATUS_FAILED_DEPENDENCY);
	}

	/**
	 * Create a project
	 *
	 * Change for clients: response now contains full project info
	 *
	 * @param string $id
	 * @param string $name
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: Project successfully created
	 * 400: Failed to create project
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function createProject(string $id, string $name): DataResponse {
		try {
			$this->projectMapper->getById($id);
			return new DataResponse(['error' => 'project already exists'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
		}
		try {
			$jsonProject = $this->localProjectService->createProject($name, $id, null, $this->userId);
			$projInfo = $this->localProjectService->getProjectInfo($jsonProject['id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVEL_ADMIN;
			return new DataResponse($projInfo);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get local project list
	 *
	 * @return DataResponse<Http::STATUS_OK, list<CospendFullProjectInfo>, array{}>
	 * @throws Exception
	 *
	 * 200: Project list
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getLocalProjects(): DataResponse {
		return new DataResponse($this->localProjectService->getLocalProjects($this->userId));
	}

	/**
	 * Get federated project list
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: int, remoteProjectId: string, remoteProjectName: string, remoteServerUrl: string, state: int, userId: string, inviterCloudId: string, inviterDisplayName: string}>, array{}>
	 * @throws Exception
	 *
	 * 200: Project list
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getFederatedProjects(): DataResponse {
		return new DataResponse($this->cospendService->getFederatedProjects($this->userId));
	}

	/**
	 * Get project information
	 *
	 * @param string $projectId
	 * @return DataResponse<Http::STATUS_OK, CospendFullProjectInfo, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: Project info
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function getProjectInfo(string $projectId): DataResponse {
		try {
			return new DataResponse($this->projectService->getProjectInfoWithAccessLevel($projectId, $this->userId));
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		}
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
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: The project was successfully update
	 * 400: Failed to edit the project
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function editProject(
		string $projectId, ?string $name = null,
		?string $autoExport = null, ?string $currencyName = null, ?bool $deletionDisabled = null,
		?string $categorySort = null, ?string $paymentModeSort = null, ?int $archivedTs = null,
	): DataResponse {
		try {
			$this->projectService->editProject(
				$projectId, $name, null, $autoExport,
				$currencyName, $deletionDisabled, $categorySort, $paymentModeSort, $archivedTs
			);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a project
	 *
	 * @param string $projectId
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: The project was successfully deleted
	 * 404: The project was not found
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_ADMIN)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function deleteProject(string $projectId): DataResponse {
		try {
			$this->projectService->deleteProject($projectId);
			return new DataResponse(['message' => 'DELETED']);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
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
	 * @return DataResponse<Http::STATUS_OK, CospendProjectStatistics, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function getProjectStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null,
	): DataResponse {
		try {
			$result = $this->projectService->getStatistics(
				$projectId, $tsMin, $tsMax, $paymentModeId,
				$categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId, $payerId
			);
			return new DataResponse($result);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Get settlement data
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn Member ID to center the settlement on. All suggested transactions will involve this member.
	 * @param int|null $maxTimestamp Settle at a precise date. So the member balances are all back to zero at this date.
	 * @return DataResponse<Http::STATUS_OK, CospendProjectSettlement, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function getProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		try {
			$result = $this->projectService->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
			return new DataResponse($result);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
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
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		try {
			$this->projectService->autoSettlement($projectId, $centeredOn, $precision, $maxTimestamp);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get members
	 *
	 * @param string $projectId
	 * @param int|null $lastChanged
	 * @return DataResponse<Http::STATUS_OK, list<CospendMember>, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: List of members
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	#[SupportFederatedProject]
	public function getMembers(string $projectId, ?int $lastChanged = null): DataResponse {
		try {
			$members = $this->projectService->getMembers($projectId, null, $lastChanged);
			return new DataResponse($members);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: Member was successfully disabled or deleted
	 * 404: Member does not exist
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	#[SupportFederatedProject]
	public function deleteMember(string $projectId, int $memberId): DataResponse {
		try {
			$this->projectService->deleteMember($projectId, $memberId);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		}
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
	 * @return DataResponse<Http::STATUS_OK, ?CospendMember, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: Member was successfully edited (and deleted if it was disabled and wasn't ower of any bill)
	 * 400: Failed to edit the member
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	#[SupportFederatedProject]
	public function editMember(
		string $projectId, int $memberId, ?string $name = null, ?float $weight = null, $activated = null,
		?string $color = null, ?string $userId = null,
	): DataResponse {
		if ($activated === 'true') {
			$activated = true;
		} elseif ($activated === 'false') {
			$activated = false;
		}
		try {
			$member = $this->projectService->editMember($projectId, $memberId, $name, $userId, $weight, $activated, $color);
			return new DataResponse($member);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
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
	 * @return DataResponse<Http::STATUS_OK, CospendMember, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array{error: string}, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Members'])]
	#[SupportFederatedProject]
	public function createMember(
		string $projectId, string $name, ?string $userId = null, float $weight = 1,
		int $active = 1, ?string $color = null,
	): DataResponse {
		try {
			$member = $this->projectService->createMember($projectId, $name, $weight, $active !== 0, $color, $userId);
			return new DataResponse($member);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function editBill(
		string $projectId, int $billId, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payedFor = null, ?float $amount = null, ?string $repeat = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?int $repeatAllActive = null, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null,
	): DataResponse {
		try {
			$this->projectService->editBill(
				$projectId, $billId, $date, $what, $payer, $payedFor,
				$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
				$repeatAllActive, $repeatUntil, $timestamp, $comment, $repeatFreq, $deleted, true
			);
			return new DataResponse($billId);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Edit multiple bills
	 *
	 * @param string $projectId
	 * @param list<int> $billIds
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
	 * @return DataResponse<Http::STATUS_OK, list<int>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function editBills(
		string $projectId, array $billIds, ?int $categoryId = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payedFor = null,
		?float $amount = null, ?string $repeat = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $repeatAllActive = null, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null,
	): DataResponse {
		try {
			$this->projectService->editBills(
				$projectId, $billIds, $date, $what, $payer, $payedFor,
				$amount, $repeat, $paymentMode, $paymentModeId, $categoryId,
				$repeatAllActive, $repeatUntil, $timestamp, $comment,
				$repeatFreq, $deleted, true
			);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
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
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	public function moveBill(string $projectId, int $billId, string $toProjectId): DataResponse {
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $toProjectId);
		if ($userAccessLevel < Application::ACCESS_LEVEL_PARTICIPANT) {
			return new DataResponse(['message' => $this->trans->t('You are not allowed to access the target project')], Http::STATUS_UNAUTHORIZED);
		}

		// get current bill from mapper for the activity manager
		$oldBillObj = $this->billMapper->find($billId);

		// update the bill information
		$result = $this->localProjectService->moveBill($projectId, $billId, $toProjectId);

		if (!isset($result['inserted_id'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}

		$newBillObj = $this->billMapper->find($result['inserted_id']);

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
	 * @return DataResponse<Http::STATUS_OK, list<array{new_bill_id: int, date_orig: string, date_repeat: string, what: string, project_name: string}>, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function repeatBill(string $projectId, int $billId): DataResponse {
		try {
			$result = $this->projectService->repeatBill($projectId, $billId);
			return new DataResponse($result);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		}
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function createBill(
		string $projectId, ?string $date = null, ?string $what = null, ?int $payer = null, ?string $payedFor = null,
		?float $amount = null, ?string $repeat = null, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null,
	): DataResponse {
		try {
			$newBillId = $this->projectService->createBill(
				$projectId, $date, $what, $payer, $payedFor, $amount,
				$repeat, $paymentMode, $paymentModeId, $categoryId, $repeatAllActive,
				$repeatUntil, $timestamp, $comment, $repeatFreq, 0, true
			);
			return new DataResponse($newBillId);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Clear the trash bin
	 *
	 * @param string $projectId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: The trash bin was successfully cleared
	 * 424: Failed to clear the trash bin
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function clearTrashBin(string $projectId): DataResponse {
		try {
			$this->projectService->clearTrashBin($projectId);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FORBIDDEN|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function deleteBill(string $projectId, int $billId, bool $moveToTrash = true): DataResponse {
		try {
			$this->projectService->deleteBill($projectId, $billId, false, $moveToTrash, true);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			if ($e->getCode() === Http::STATUS_FORBIDDEN) {
				return new DataResponse($e->data, Http::STATUS_FORBIDDEN);
			}
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $projectId
	 * @param list<int> $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: Bills were successfully deleted
	 * 404: The bill was not found
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function deleteBills(string $projectId, array $billIds, bool $moveToTrash = true): DataResponse {
		try {
			$this->projectService->deleteBills($projectId, $billIds, $moveToTrash);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
		}
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
	 * @return DataResponse<Http::STATUS_OK, array{nb_bills: int, allBillIds: list<int>, timestamp: int, bills: list<CospendBill>}, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function getBills(
		string $projectId, ?int $lastChanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0,
	): DataResponse {
		try {
			return new DataResponse(
				$this->projectService->getBills(
					$projectId, $lastChanged, $offset, $limit, $reverse, $payerId, $categoryId,
					$paymentModeId, $includeBillId, $searchTerm, $deleted
				)
			);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Get a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return DataResponse<Http::STATUS_OK, CospendBill, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: The bill was successfully obtained
	 * 404: The bill was not found
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Bills'])]
	#[SupportFederatedProject]
	public function getBill(string $projectId, int $billId): DataResponse {
		try {
			return new DataResponse($this->projectService->getBill($projectId, $billId));
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse(['error' => 'bill not found'], Http::STATUS_NOT_FOUND);
		}
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
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function editSharedAccessLevel(string $projectId, int $shId, int $accessLevel): DataResponse {
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than their level (do not downgrade one)
		if ($userAccessLevel >= $accessLevel && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->localProjectService->editShareAccessLevel($projectId, $shId, $accessLevel);
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
	 * @return DataResponse<Http::STATUS_OK, 'OK', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_FAILED_DEPENDENCY, array{message: string}, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The shared access was successfully edited
	 * 401: The current user is not allowed to edit this shared access
	 * 400: Failed to edit the access level
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function editSharedAccess(string $projectId, int $shId, ?string $label = null, ?string $password = null): DataResponse {
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		// allow edition if user is at least participant and has greater or equal access level than target
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->localProjectService->editShareAccess($projectId, $shId, $label, $password);
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
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: Payment mode was successfully created
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	#[SupportFederatedProject]
	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		try {
			$insertedId = $this->projectService->createPaymentMode($projectId, $name, $icon, $color, $order);
			return new DataResponse($insertedId);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Edit a payment mode
	 *
	 * @param string $projectId
	 * @param int $pmId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse<Http::STATUS_OK, CospendPaymentMode, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The payment mode was successfully edited
	 * 400: Failed to edit the payment mode
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	#[SupportFederatedProject]
	public function editPaymentMode(
		string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null,
	): DataResponse {
		try {
			$pm = $this->projectService->editPaymentMode($projectId, $pmId, $name, $icon, $color);
			return new DataResponse($pm);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $projectId
	 * @param list<array{order: int, id: int}> $order Array of objects, each object contains the order number and the payment mode ID
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The payment mode order was successfully saved
	 * 424: Failed to save the payment mode order
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	#[SupportFederatedProject]
	public function savePaymentModeOrder(string $projectId, array $order): DataResponse {
		try {
			$this->projectService->savePaymentModeOrder($projectId, $order);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $projectId
	 * @param int $pmId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The payment mode was successfully deleted
	 * 424: Failed to delete the payment mode
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Payment-modes'])]
	#[SupportFederatedProject]
	public function deletePaymentMode(string $projectId, int $pmId): DataResponse {
		try {
			$this->projectService->deletePaymentMode($projectId, $pmId);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Create a category
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The category was successfully created
	 * 424: Failed to create the category
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	#[SupportFederatedProject]
	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		try {
			$insertedId = $this->projectService->createCategory($projectId, $name, $icon, $color, $order);
			return new DataResponse($insertedId);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Edit a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse<Http::STATUS_OK, CospendCategory, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The category was successfully edited
	 * 400: Failed to edit the category
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	#[SupportFederatedProject]
	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null,
	): DataResponse {
		try {
			$category = $this->projectService->editCategory($projectId, $categoryId, $name, $icon, $color);
			return new DataResponse($category);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * Save categories order
	 *
	 * @param string $projectId
	 * @param list<array{order: int, id: int}> $order
	 * @return DataResponse<Http::STATUS_OK, true, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The category order was successfully saved
	 * 424: Failed to save the category order
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	#[SupportFederatedProject]
	public function saveCategoryOrder(string $projectId, array $order): DataResponse {
		try {
			$this->projectService->saveCategoryOrder($projectId, $order);
			return new DataResponse(true);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The category was successfully deleted
	 * 424: Failed to delete the category
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Categories'])]
	#[SupportFederatedProject]
	public function deleteCategory(string $projectId, int $categoryId): DataResponse {
		try {
			$this->projectService->deleteCategory($projectId, $categoryId);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Create a currency
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, int, array{}>|DataResponse<Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 *
	 * 200: The currency was successfully created
	 * 424: Failed to create the currency
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Currencies'])]
	#[SupportFederatedProject]
	public function createCurrency(string $projectId, string $name, float $rate): DataResponse {
		try {
			$insertedId = $this->projectService->createCurrency($projectId, $name, $rate);
			return new DataResponse($insertedId);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		}
	}

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse<Http::STATUS_OK, CospendCurrency, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 *
	 * 200: The currency was successfully edited
	 * 400: Failed to edit the currency
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Currencies'])]
	#[SupportFederatedProject]
	public function editCurrency(string $projectId, int $currencyId, string $name, float $rate): DataResponse {
		try {
			$currency = $this->projectService->editCurrency($projectId, $currencyId, $name, $rate);
			return new DataResponse($currency);
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			if ($e->getCode() == Http::STATUS_NOT_FOUND) {
				return new DataResponse($e->data, Http::STATUS_NOT_FOUND);
			}
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FAILED_DEPENDENCY, array<string, string>, array{}>
	 * @throws Exception
	 *                   * @throws MultipleObjectsReturnedException
	 *
	 * 200: The currency was successfully deleted
	 * 400: Failed to delete the currency
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_MAINTAINER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Currencies'])]
	#[SupportFederatedProject]
	public function deleteCurrency(string $projectId, int $currencyId): DataResponse {
		try {
			$this->projectService->deleteCurrency($projectId, $currencyId);
			return new DataResponse('');
		} catch (ClientException $e) {
			return $this->getResponseFromClientException($e);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a federated user share
	 *
	 * @param string $projectId
	 * @param string $userCloudId
	 * @param int $accessLevel
	 * @param bool $manuallyAdded
	 * @return DataResponse<Http::STATUS_OK, CospendUserShare, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The federated share was successfully created
	 * 400: Failed to create the federated share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createFederatedShare(
		string $projectId, string $userCloudId, int $accessLevel = 2,
		bool $manuallyAdded = true,
	): DataResponse {
		try {
			$share = $this->localProjectService->createFederatedShare($projectId, $userCloudId, $this->userId, $accessLevel, $manuallyAdded);
			return new DataResponse($share->jsonSerialize());
		} catch (CospendBasicException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a federated share (unshare)
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The federated share was successfully deleted
	 * 400: Failed to delete the user share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteFederatedShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel < $shareAccessLevel) {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_UNAUTHORIZED
			);
		}
		try {
			$this->localProjectService->deleteFederatedShare($projectId, $shId);
			return new DataResponse('');
		} catch (CospendBasicException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create a user share
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param int $accessLevel
	 * @param bool $manuallyAdded
	 * @return DataResponse<Http::STATUS_OK, CospendUserShare, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 * @throws CospendBasicException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The user share was successfully created
	 * 400: Failed to create the user share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createUserShare(
		string $projectId, string $userId, int $accessLevel = 2,
		bool $manuallyAdded = true,
	): DataResponse {
		$result = $this->localProjectService->createUserShare($projectId, $userId, $this->userId, $accessLevel, $manuallyAdded);
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
	 * @throws CospendBasicException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The user share was successfully deleted
	 * 400: Failed to delete the user share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteUserShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->localProjectService->deleteUserShare($projectId, $shId, $this->userId);
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
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createPublicShare(
		string $projectId, ?string $label = null, ?string $password = null, int $accessLevel = 2,
	): DataResponse {
		$result = $this->localProjectService->createPublicShare($projectId, $label, $password, $accessLevel);
		return new DataResponse($result);
	}

	/**
	 * Delete a public share link
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse<Http::STATUS_OK, '', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The public share was successfully deleted
	 * 400: Failed to delete the public share
	 * 401: The user is not authorized to delete this public share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deletePublicShare(string $projectId, int $shId): DataResponse {
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->localProjectService->deletePublicShare($projectId, $shId);
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
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The group share was successfully created
	 * 400: Failed to create the group share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createGroupShare(string $projectId, string $groupId, int $accessLevel = 2): DataResponse {
		$result = $this->localProjectService->createGroupShare($projectId, $groupId, $this->userId, $accessLevel);
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
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The group share was successfully deleted
	 * 400: Failed to delete the group share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteGroupShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->localProjectService->deleteGroupShare($projectId, $shId, $this->userId);
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
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The circle share was successfully created
	 * 400: Failed to create the circle share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function createCircleShare(string $projectId, string $circleId, int $accessLevel = 2): DataResponse {
		$result = $this->localProjectService->createCircleShare($projectId, $circleId, $this->userId, $accessLevel);
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
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 *
	 * 200: The circle share was successfully deleted
	 * 400: Failed to delete the circle share
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_PARTICIPANT)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Sharing'])]
	public function deleteCircleShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->localProjectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->localProjectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= $shareAccessLevel) {
			$result = $this->localProjectService->deleteCircleShare($projectId, $shId, $this->userId);
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
			foreach ($shares as $share) {
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
	 * @throws InvalidPathException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws LockedException
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function exportCsvSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$settlement = $this->projectService->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
		$members = $this->projectService->getMembers($projectId);
		$result = $this->cospendService->exportCsvSettlement($projectId, $this->userId, $settlement, $members);
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
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function exportCsvStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $category = null,
		?float $amountMin = null, ?float $amountMax = null,
		int $showDisabled = 1, ?int $currencyId = null,
	): DataResponse {
		$statistics = $this->projectService->getStatistics($projectId, $tsMin, $tsMax, $paymentModeId, $category, $amountMin, $amountMax, $showDisabled !== 0, $currencyId);
		$result = $this->cospendService->exportCsvStatistics($projectId, $this->userId, $statistics);
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
	 * @return DataResponse<Http::STATUS_OK, array{path: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, string>, array{}>
	 *
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CospendUserPermissions(minimumLevel: Application::ACCESS_LEVEL_VIEWER)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	#[SupportFederatedProject]
	public function exportCsvProject(string $projectId, ?string $name = null): DataResponse {
		try {
			$projectInfo = $this->projectService->getProjectInfoWithAccessLevel($projectId, $this->userId);
			$bills = $this->projectService->getBills($projectId);
			$result = $this->cospendService->exportCsvProject($projectId, $this->userId, $projectInfo, $bills['bills'], $name);
		} catch (CospendBasicException $e) {
			return new DataResponse($e->data, Http::STATUS_BAD_REQUEST);
		}
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
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function importCsvProject(string $path): DataResponse {
		$result = $this->cospendService->importCsvProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->localProjectService->getProjectInfo($result['project_id']);
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
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function importSWProject(string $path): DataResponse {
		$result = $this->cospendService->importSWProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->localProjectService->getProjectInfo($result['project_id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVEL_ADMIN;
			return new DataResponse($projInfo);
		}
		return new DataResponse(['message' => $result['message']], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Ping
	 *
	 * Used by MoneyBuster to check if weblogin is valid
	 *
	 * @return DataResponse<Http::STATUS_OK, list<?string>, array{}>
	 */
	#[NoAdminRequired]
	public function ping(): DataResponse {
		return new DataResponse([$this->userId]);
	}

	/**
	 * Get cross-project balances for the current user
	 *
	 * @return DataResponse<Http::STATUS_OK, array, array{}>
	 * @throws Exception
	 *
	 * 200: Cross-project balance
	 * 
	 * This endpoint implements Cross-project balances from GitHub issue #281.
	 * It provides aggregated balance information showing what the current user owes
	 * to and is owed by other users across all projects they participate in.
	 * 
	 * Returns:
	 * - Summary totals (total owed, total owed to user, net balance)
	 * - Per-person breakdowns with project-level details
	 * - Human-readable summary for quick display
	 * 
	 * The calculation maintains consistency with individual project settlement views
	 * by using the same underlying balance calculation logic.
	 * 
	 * @return DataResponse Array containing cross-project balance data
	 * 
	 * @since 1.6.0 Added for cross-project balance aggregation feature
	 */
	#[NoAdminRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT, tags: ['Projects'])]
	public function getCrossGroupBalances(): DataResponse {
		$balances = $this->cospendService->getCrossGroupBalances($this->userId);
		return new DataResponse($balances);
	}
}
