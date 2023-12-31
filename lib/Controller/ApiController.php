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
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Share\IShare;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;

use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;

class ApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IManager $shareManager,
		private IUserManager $userManager,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectService $projectService,
		private ActivityManager $activityManager,
		private IDBConnection $dbconnection,
		private IRootFolder $root,
		private IInitialState $initialStateService,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
		private ?string $userId
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * Create a project
	 * Change for clients: response contains full project info
	 *
	 * @param string $id
	 * @param string $name
	 * @param string|null $password
	 * @param string|null $contact_email
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createProject(string $id, string $name, ?string $password = null, ?string $contact_email = null): DataResponse {
		if ($contact_email !== null) {
			$email = $contact_email;
		} else {
			$user = $this->userManager->get($this->userId);
			$email = $user->getEMailAddress();
		}
		$result = $this->projectService->createProject($name, $id, $password, $email, $this->userId);
		if (isset($result['id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVELS['admin'];
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a project
	 * @param string $projectId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteProject(string $projectId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['admin']) {
			$result = $this->projectService->deleteProject($projectId);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Clear the trashbin
	 *
	 * @param string $projectId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function clearTrashbin(string $projectId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			try {
				$this->billMapper->deleteDeletedBills($projectId);
				return new DataResponse('');
			} catch (\Exception | \Throwable $e) {
				return new DataResponse('', Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to clear the trashbin')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteBill(string $projectId, int $billId, bool $moveToTrash = true): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
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
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to delete this bill')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $projectId
	 * @param array $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteBills(string $projectId, array $billIds, bool $moveToTrash = true): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			foreach ($billIds as $billid) {
				$billObj = null;
				if ($this->billMapper->getBill($projectId, $billid) !== null) {
					$billObj = $this->billMapper->find($billid);
				}
				$result = $this->projectService->deleteBill($projectId, $billid, false, $moveToTrash);
				if (!isset($result['success'])) {
					return new DataResponse($result, Http::STATUS_BAD_REQUEST);
				} else {
					if (!is_null($billObj)) {
						$this->activityManager->triggerEvent(
							ActivityManager::COSPEND_OBJECT_BILL, $billObj,
							ActivityManager::SUBJECT_BILL_DELETE,
							[]
						);
					}
				}
			}
			return new DataResponse('OK');
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to delete this bill')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get project information
	 *
	 * Change for clients: error code is now Http::STATUS_FORBIDDEN
	 *
	 * @param string $projectId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getProjectInfo(string $projectId): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$projectInfo = $this->projectService->getProjectInfo($projectId);
			$projectInfo['myaccesslevel'] = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
			return new DataResponse($projectInfo);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get this project\'s info')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get project statistics
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
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getProjectStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null, ?int $paymentModeId = null,
		?int   $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null
	): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->projectService->getProjectStatistics(
				$projectId, 'lowername', $tsMin, $tsMax, $paymentModeId,
				$categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId, $payerId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get this project\'s statistics')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get project settlement info
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->projectService->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
			return new DataResponse($result);
		}
		else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get this project\'s settlement')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get automatic settlement plan
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): DataResponse {
//		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['participant']) {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->projectService->autoSettlement($projectId, $centeredOn, $precision, $maxTimestamp);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to settle this project automatically')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Edit a project member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @param string|null $name
	 * @param float|null $weight
	 * @param null $activated
	 * @param string|null $color
	 * @param string|null $userid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editMember(
		string  $projectId, int $memberId, ?string $name = null, ?float $weight = null, $activated = null,
		?string $color = null, ?string $userid = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			if ($activated === 'true') {
				$activated = true;
			} elseif ($activated === 'false') {
				$activated = false;
			}
			$result = $this->projectService->editMember($projectId, $memberId, $name, $userid, $weight, $activated, $color);
			if (count($result) === 0) {
				return new DataResponse(null);
			} elseif (isset($result['activated'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this member')],
				Http::STATUS_FORBIDDEN
			);
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
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
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
	#[CORS]
	public function editBill(
		string  $projectId, int $billId, ?string $date = null, ?string $what = null,
		?int    $payer = null, ?string $payed_for = null, ?float $amount = null, ?string $repeat = null,
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int    $categoryid = null, ?int $repeatallactive = null, ?string $repeatuntil = null,
		?int    $timestamp = null, ?string $comment = null, ?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant']) {
			$result =  $this->projectService->editBill(
				$projectId, $billId, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, null, $deleted
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
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Edit multiple bills
	 *
	 * @param string $projectId
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
	#[CORS]
	public function editBills(
		string $projectId, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null,
		?float $amount = null, ?string $repeat = null,
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant']) {
			$paymentModes = $this->projectService->getCategoriesOrPaymentModes($projectId, false);
			foreach ($billIds as $billid) {
				$result =  $this->projectService->editBill(
					$projectId, $billid, $date, $what, $payer, $payed_for,
					$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
					$repeatallactive, $repeatuntil, $timestamp, $comment,
					$repeatfreq, $paymentModes, $deleted
				);
				if (isset($result['edited_bill_id'])) {
					$billObj = $this->billMapper->find($billid);
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
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Move a bill from one project to another
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param string $toProjectId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function moveBill(string $projectId, int $billId, string $toProjectId): DataResponse {
		// ensure the user has permission to access both projects
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);

		if ($userAccessLevel < Application::ACCESS_LEVELS['participant']) {
			return new DataResponse(['message' => $this->trans->t('You are not allowed to edit this bill')], Http::STATUS_FORBIDDEN);
		}

		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $toProjectId);

		if ($userAccessLevel < Application::ACCESS_LEVELS['participant']) {
			return new DataResponse(['message' => $this->trans->t ('You are not allowed to access the destination project')], Http::STATUS_FORBIDDEN);
		}

		// get current bill from mapper for the activity manager
		$oldBillObj = $this->billMapper->find ($billId);

		// update the bill information
		$result = $this->projectService->moveBill($projectId, $billId, $toProjectId);

		if (!isset($result['inserted_id'])) {
			return new DataResponse($result, Http::STATUS_FORBIDDEN);
		}

		$newBillObj = $this->billMapper->find ($result ['inserted_id']);

		// add delete activity record
		$this->activityManager->triggerEvent (
			ActivityManager::COSPEND_OBJECT_BILL, $oldBillObj,
			ActivityManager::SUBJECT_BILL_DELETE, []
		);

		// add create activity record
		$this->activityManager->triggerEvent (
			ActivityManager::COSPEND_OBJECT_BILL, $newBillObj,
			ActivityManager::SUBJECT_BILL_CREATE, []
		);

		// return a 200 response
		return new DataResponse($result['inserted_id']);
	}

	/**
	 * Trigger bill repetition for a specific bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function repeatBill(string $projectId, int $billId): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->cronRepeatBills($billId);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Edit a project
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $autoexport
	 * @param string|null $currencyname
	 * @param bool|null $deletion_disabled
	 * @param string|null $categorysort
	 * @param string|null $paymentmodesort
	 * @param int|null $archived_ts
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editProject(
		string $projectId, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
		?string $categorysort = null, ?string $paymentmodesort = null, ?int $archived_ts = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['admin']) {
			$result = $this->projectService->editProject(
				$projectId, $name, $contact_email, null, $autoexport,
				$currencyname, $deletion_disabled, $categorysort, $paymentmodesort, $archived_ts
			);
			if (isset($result['success'])) {
				return new DataResponse('UPDATED');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a bill
	 *
	 * @param string $projectId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
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
	#[CORS]
	public function createBill(
		string $projectId, ?string $date = null, ?string $what = null, ?int $payer = null, ?string $payed_for = null,
		?float $amount = null, ?string $repeat = null, ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addBill($projectId, $date, $what, $payer, $payed_for, $amount,
													 $repeat, $paymentmode, $paymentmodeid, $categoryid, $repeatallactive,
													 $repeatuntil, $timestamp, $comment, $repeatfreq);
			if (isset($result['inserted_id'])) {
				$billObj = $this->billMapper->find($result['inserted_id']);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_CREATE,
					[]
				);
				return new DataResponse($result['inserted_id']);
			} else {
				return new DataResponse(['error' => $result], Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Create a project member
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $userid
	 * @param float $weight
	 * @param int $active
	 * @param string|null $color
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createMember(
		string $projectId, string $name, ?string $userid = null, float $weight = 1,
		int $active = 1, ?string $color = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addMember($projectId, $name, $weight, $active !== 0, $color, $userid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add members')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get a project's bill list
	 *
	 * @param string $projectId
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
	#[CORS]
	public function getBills(
		string $projectId, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			if ($limit) {
				$bills = $this->billMapper->getBillsWithLimit(
					$projectId, null, null, null, $paymentModeId, $categoryId, null, null,
					$lastchanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
				);
			} else {
				$bills = $this->billMapper->getBills(
					$projectId, null, null, null, $paymentModeId, $categoryId, null, null,
					$lastchanged, null, $reverse, $payerId, $deleted
				);
			}
			$billIds = $this->projectService->getAllBillIds($projectId, $deleted);
			$ts = (new DateTime())->getTimestamp();
			$result = [
				'nb_bills' => $this->billMapper->countBills($projectId, $payerId, $categoryId, $paymentModeId, $deleted),
				'bills' => $bills,
				'allBillIds' => $billIds,
				'timestamp' => $ts,
			];
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get the bill list')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get project list
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getProjects(): DataResponse {
		return new DataResponse(
			$this->projectService->getProjects($this->userId)
		);
	}

	/**
	 * Get a project's member list
	 *
	 * @param string $projectId
	 * @param int|null $lastChanged
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getMembers(string $projectId, ?int $lastChanged = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$members = $this->projectService->getMembers($projectId, null, $lastChanged);
			return new DataResponse($members);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteMember(string $projectId, int $memberId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteMember($projectId, $memberId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Edit a shared access level
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param int $accessLevel
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editSharedAccessLevel(string $projectId, int $shId, int $accessLevel): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than their level (do not downgrade one)
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $accessLevel && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccessLevel($projectId, $shId, $accessLevel);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to give such shared access level')],
				Http::STATUS_FORBIDDEN
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
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editSharedAccess(string $projectId, int $shId, ?string $label = null, ?string $password = null): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than their level (do not downgrade one)
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccess($projectId, $shId, $label, $password);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this shared access')],
				Http::STATUS_FORBIDDEN
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
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addPaymentMode($projectId, $name, $icon, $color, $order);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_FORBIDDEN
			);
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
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editPaymentMode(
		string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editPaymentMode($projectId, $pmId, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $projectId
	 * @param array $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function savePaymentModeOrder(string $projectId, array $order): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			if ($this->projectService->savePaymentModeOrder($projectId, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $projectId
	 * @param int $pmId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deletePaymentMode(string $projectId, int $pmId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deletePaymentMode($projectId, $pmId);
			if (isset($result['success'])) {
				return new DataResponse($pmId);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_FORBIDDEN
			);
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
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addCategory($projectId, $name, $icon, $color, $order);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_FORBIDDEN
			);
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
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editCategory($projectId, $categoryId, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_FORBIDDEN
			);
		}
	}


	/**
	 * Save categories order
	 *
	 * @param string $projectId
	 * @param array $order
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function saveCategoryOrder(string $projectId, array $order): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			if ($this->projectService->saveCategoryOrder($projectId, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteCategory(string $projectId, int $categoryId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteCategory($projectId, $categoryId);
			if (isset($result['success'])) {
				return new DataResponse($categoryId);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Create a currency
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createCurrency(string $projectId, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addCurrency($projectId, $name, $rate);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function editCurrency(string $projectId, int $currencyId, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editCurrency($projectId, $currencyId, $name, $rate);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteCurrency(string $projectId, int $currencyId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteCurrency($projectId, $currencyId);
			if (isset($result['success'])) {
				return new DataResponse($currencyId);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Create a user share
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param int $accessLevel
	 * @param bool $manuallyAdded
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createUserShare(
		string $projectId, string $userId, int $accessLevel = Application::ACCESS_LEVELS['participant'],
		bool $manuallyAdded = true
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addUserShare($projectId, $userId, $this->userId, $accessLevel, $manuallyAdded);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a user share
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteUserShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteUserShare($projectId, $shId, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Create a public share link
	 *
	 * @param string $projectId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createPublicShare(string $projectId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addPublicShare($projectId);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add public shared accesses')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a public share link
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deletePublicShare(string $projectId, int $shId): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deletePublicShare($projectId, $shId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Create a group share
	 *
	 * @param string $projectId
	 * @param string $groupId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createGroupShare(string $projectId, string $groupId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addGroupShare($projectId, $groupId, $this->userId);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a group share
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteGroupShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteGroupShare($projectId, $shId, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Create a circle share
	 *
	 * @param string $projectId
	 * @param string $circleId
	 * @return DataResponse
	 * @throws Exception
	 * @throws InitiatorNotFoundException
	 * @throws RequestBuilderException
	 */
	#[NoAdminRequired]
	#[CORS]
	public function createCircleShare(string $projectId, string $circleId): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectId) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addCircleShare($projectId, $circleId, $this->userId);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a circle share
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
	public function deleteCircleShare(string $projectId, int $shId): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectId);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectId, $shId);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteCircleShare($projectId, $shId, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get a public file share from a node path
	 *
	 * @param string $path
	 * @return DataResponse
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getPublicFileShare(string $path): DataResponse {
		$cleanPath = str_replace(array('../', '..\\'), '',  $path);
		$userFolder = $this->root->getUserFolder($this->userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file->getType() === FileInfo::TYPE_FILE) {
				if ($file->isShareable()) {
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
					$response = new DataResponse(['token' => $token]);
				} else {
					$response = new DataResponse(['message' => $this->trans->t('Access denied')], Http::STATUS_FORBIDDEN);
				}
			} else {
				$response = new DataResponse(['message' => $this->trans->t('Access denied')], Http::STATUS_FORBIDDEN);
			}
		} else {
			$response = new DataResponse(['message' => $this->trans->t('Access denied')], Http::STATUS_FORBIDDEN);
		}
		return $response;
	}

	/**
	 * Get CSV settlement plan
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	public function exportCsvSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->projectService->exportCsvSettlement($projectId, $this->userId, $centeredOn, $maxTimestamp);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to export this project settlement')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get CSV statistics
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
	 * @return DataResponse
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	public function exportCsvStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $category = null,
		?float $amountMin = null, ?float $amountMax = null,
		int $showDisabled = 1, ?int $currencyId = null
	): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->projectService->exportCsvStatistics(
				$projectId, $this->userId, $tsMin, $tsMax,
				$paymentModeId, $category, $amountMin, $amountMax,
				$showDisabled !== 0, $currencyId
			);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to export this project statistics')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get CSV project export
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string|null $uid
	 * @return DataResponse
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	public function exportCsvProject(string $projectId, ?string $name = null, ?string $uid = null): DataResponse {
		$userId = $uid;
		if ($this->userId) {
			$userId = $this->userId;
		}

		if ($this->projectService->userCanAccessProject($userId, $projectId)) {
			$result = $this->projectService->exportCsvProject($projectId, $userId, $name);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to export this project')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Import a project from a CSV file
	 *
	 * @param string $path
	 * @return DataResponse
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \Throwable
	 */
	#[NoAdminRequired]
	#[CORS]
	public function importCsvProject(string $path): DataResponse {
		$result = $this->projectService->importCsvProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['project_id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVELS['admin'];
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Import a SplitWise project from a CSV file
	 *
	 * @param string $path
	 * @return DataResponse
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[NoAdminRequired]
	#[CORS]
	public function importSWProject(string $path): DataResponse {
		$result = $this->projectService->importSWProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['project_id']);
			$projInfo['myaccesslevel'] = Application::ACCESS_LEVELS['admin'];
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Used by MoneyBuster to check if weblogin is valid
	 */
	#[NoAdminRequired]
	#[CORS]
	public function apiPing(): DataResponse {
		$response = new DataResponse([$this->userId]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * Get list of bill activity items
	 *
	 * @param int|null $since
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
	public function getBillActivity(?int $since): DataResponse {
		$result = $this->projectService->getBillActivity($this->userId, $since);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
