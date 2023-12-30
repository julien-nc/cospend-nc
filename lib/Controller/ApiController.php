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
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Share\IShare;
use OCP\DB\QueryBuilder\IQueryBuilder;
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
	 * Check if project password is valid
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return bool
	 */
	private function checkLogin(string $projectId, string $password): bool {
		if ($projectId === '' || $projectId === null
			|| $password === '' || $password === null
		) {
			return false;
		} else {
			$qb = $this->dbconnection->getQueryBuilder();
			$qb->select('id', 'password')
			   ->from('cospend_projects', 'p')
			   ->where(
				   $qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			   );
			$req = $qb->executeQuery();
			$dbPassword = null;
			$row = $req->fetch();
			if ($row !== false) {
				$dbPassword = $row['password'];
			}
			$req->closeCursor();
			$qb->resetQueryParts();
			return (
				$dbPassword !== null &&
				password_verify($password, $dbPassword)
			);
		}
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
//	#[NoCSRFRequired]
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
//	#[NoCSRFRequired]
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
	 * Delete a project
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicDeleteProject(string $projectId, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['admin'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['admin'])
		) {
			$result = $this->projectService->deleteProject($publicShareInfo['projectid'] ?? $projectId);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
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
	 * Clear the trashbin
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicClearTrashbin(string $projectId, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			try {
				$this->billMapper->deleteDeletedBills($publicShareInfo['projectid']);
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
//	#[NoCSRFRequired]
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
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicDeleteBill(string $projectId, string $password, int $billId, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$billObj = null;
			if ($this->billMapper->getBill($publicShareInfo['projectid'] ?? $projectId, $billId) !== null) {
				$billObj = $this->billMapper->find($billId);
			}

			$result = $this->projectService->deleteBill($publicShareInfo['projectid'] ?? $projectId, $billId, false, $moveToTrash);
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
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
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
	 * Delete multiple bills
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param array $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicDeleteBills(string $projectId, string $password, array $billIds, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
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
				if ($this->billMapper->getBill($publicShareInfo['projectid'] ?? $projectId, $billid) !== null) {
					$billObj = $this->billMapper->find($billid);
				}

				$result = $this->projectService->deleteBill($publicShareInfo['projectid'] ?? $projectId, $billid, false, $moveToTrash);
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
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
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
	 * Get project information
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicGetProjectInfo(string $projectId, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$projectInfo = $this->projectService->getProjectInfo($publicShareInfo['projectid'] ?? $projectId);
			if ($projectInfo !== null) {
				unset($projectInfo['userid']);
				// for public link share: set the visible access level for frontend
				if ($publicShareInfo !== null) {
					$projectInfo['myaccesslevel'] = $publicShareInfo['accesslevel'];
				} else {
					// my access level is the guest one
					$projectInfo['myaccesslevel'] = $projectInfo['guestaccesslevel'];
				}
				return new DataResponse($projectInfo);
			} else {
				return new DataResponse(
					['message' => $this->trans->t('Project not found')],
					Http::STATUS_NOT_FOUND
				);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Bad password or share link')],
				Http::STATUS_BAD_REQUEST
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
//	#[NoCSRFRequired]
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
	 * @param string $projectId
	 * @param string $password
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
//	#[NoCSRFRequired]
	public function publicGetProjectStatistics(
		string $projectId, string $password, ?int $tsMin = null, ?int $tsMax = null,
		?int   $paymentModeId = null, ?int $categoryId = null,
		?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$result = $this->projectService->getProjectStatistics(
				$publicShareInfo['projectid'] ?? $projectId, 'lowername', $tsMin, $tsMax,
				$paymentModeId, $categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId,
				$payerId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
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
	 * Get project settlement info
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicGetProjectSettlement(string $projectId, string $password, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$result = $this->projectService->getProjectSettlement(
				$publicShareInfo['projectid'] ?? $projectId, $centeredOn, $maxTimestamp
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
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
	 * Get automatic settlement plan
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicAutoSettlement(
		string $projectId, string $password, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$result = $this->projectService->autoSettlement(
				$publicShareInfo['projectid'] ?? $projectId, $centeredOn, $precision, $maxTimestamp
			);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
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
	 * Edit a project member
	 *
	 * @param string $projectId
	 * @param string $password
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
//	#[NoCSRFRequired]
	public function publicEditMember(
		string $projectId, string $password, int $memberId, ?string $name = null, ?float $weight = null,
			   $activated = null, ?string $color = null, ?string $userid = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			if ($activated === 'true') {
				$activated = true;
			} elseif ($activated === 'false') {
				$activated = false;
			}
			$result = $this->projectService->editMember(
				$publicShareInfo['projectid'] ?? $projectId, $memberId, $name, $userid, $weight, $activated, $color
			);
			if (count($result) === 0) {
				return new DataResponse(null);
			} elseif (array_key_exists('activated', $result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a bill
	 *
	 * @param string $projectid
	 * @param int $billid
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
//	#[NoCSRFRequired]
	public function editBill(
		string $projectid, int $billid, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, ?string $repeat = null,
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null, ?string $repeatuntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant']) {
			$result =  $this->projectService->editBill(
				$projectid, $billid, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, null, $deleted
			);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billid);
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
	 * Edit a bill
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $billid
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
//	#[NoCSRFRequired]
	public function publicEditBill(
		string $projectid, string $password, int $billid, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$result = $this->projectService->editBill(
				$publicShareInfo['projectid'] ?? $projectid, $billid, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, null, $deleted
			);
			if (isset($result['edited_bill_id'])) {
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

				return new DataResponse($result['edited_bill_id']);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Move a bill from one project to another
	 *
	 * @param string $projectid
	 * @param int $billid
	 * @param string $toProjectId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function moveBill(string $projectid, int $billid, string $toProjectId): DataResponse {
		// ensure the user has permission to access both projects
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);

		if ($userAccessLevel < Application::ACCESS_LEVELS['participant']) {
			return new DataResponse(['message' => $this->trans->t('You are not allowed to edit this bill')], Http::STATUS_FORBIDDEN);
		}

		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $toProjectId);

		if ($userAccessLevel < Application::ACCESS_LEVELS['participant']) {
			return new DataResponse(['message' => $this->trans->t ('You are not allowed to access the destination project')], Http::STATUS_FORBIDDEN);
		}

		// get current bill from mapper for the activity manager
		$oldBillObj = $this->billMapper->find ($billid);

		// update the bill information
		$result = $this->projectService->moveBill($projectid, $billid, $toProjectId);

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
	 * @param string $projectid
	 * @param int $billid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function repeatBill(string $projectid, int $billid): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->cronRepeatBills($billid);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Trigger bill repetition for a specific bill
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $billid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicRepeatBill(string $projectid, string $password, int $billid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			// TODO check if bill is in this project
			$result = $this->projectService->cronRepeatBills($billid);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit multiple bills
	 *
	 * @param string $projectid
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
//	#[NoCSRFRequired]
	public function webEditBills(
		string $projectid, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null,
		?float $amount = null, ?string $repeat = null,
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant']) {
			$paymentModes = $this->projectService->getCategoriesOrPaymentModes($projectid, false);
			foreach ($billIds as $billid) {
				$result =  $this->projectService->editBill(
					$projectid, $billid, $date, $what, $payer, $payed_for,
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
	 * Edit multiple bills
	 *
	 * @param string $projectid
	 * @param string $password
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
//	#[NoCSRFRequired]
	public function publicEditBills(
		string $projectid, string $password, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null, ?float $amount = null,
		?string $repeat = 'n', ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			if (is_null($publicShareInfo)) {
				$authorFullText = $this->trans->t('Guest access');
			} elseif ($publicShareInfo['label']) {
				$authorName = $publicShareInfo['label'];
				$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
			} else {
				$authorFullText = $this->trans->t('Share link');
			}
			$paymentModes = $this->projectService->getCategoriesOrPaymentModes($publicShareInfo['projectid'] ?? $projectid, false);
			foreach ($billIds as $billid) {
				$result = $this->projectService->editBill(
					$publicShareInfo['projectid'] ?? $projectid, $billid, $date, $what, $payer, $payed_for,
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
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a project
	 *
	 * @param string $projectid
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $password
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
//	#[NoCSRFRequired]
	public function editProject(
		string $projectid, ?string $name = null, ?string $contact_email = null, ?string $password = null,
		?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
		?string $categorysort = null, ?string $paymentmodesort = null, ?int $archived_ts = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['admin']) {
			$result = $this->projectService->editProject(
				$projectid, $name, $contact_email, $password, $autoexport,
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
	 * Edit a project
	 *
	 * @param string $projectid
	 * @param string $passwd
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $password
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
//	#[NoCSRFRequired]
	public function publicEditProject(
		string $projectid, string $passwd, ?string $name = null, ?string $contact_email = null,
		?string $password = null, ?string $autoexport = null, ?string $currencyname = null,
		?bool $deletion_disabled = null, ?string $categorysort = null, ?string $paymentmodesort = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $passwd) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['admin'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $passwd === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['admin'])
		) {
			$result = $this->projectService->editProject(
				$publicShareInfo['projectid'] ?? $projectid, $name, $contact_email, $password, $autoexport,
				$currencyname, $deletion_disabled, $categorysort, $paymentmodesort
			);
			if (isset($result['success'])) {
				return new DataResponse('UPDATED');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a bill
	 *
	 * @param string $projectid
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
//	#[NoCSRFRequired]
	public function createBill(
		string $projectid, ?string $date = null, ?string $what = null, ?int $payer = null, ?string $payed_for = null,
		?float $amount = null, ?string $repeat = null, ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
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
	 * Create a bill
	 *
	 * @param string $projectid
	 * @param string $password
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
//	#[NoCSRFRequired]
	public function publicCreateBill(
		string $projectid, string $password, ?string $date = null, ?string $what = null, ?int $payer = null,
		?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$result = $this->projectService->addBill(
				$publicShareInfo['projectid'] ?? $projectid, $date, $what, $payer, $payed_for, $amount,
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
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a project member
	 *
	 * @param string $projectid
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
//	#[NoCSRFRequired]
	public function createMember(
		string $projectid, string $name, ?string $userid = null, float $weight = 1,
		int $active = 1, ?string $color = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addMember($projectid, $name, $weight, $active !== 0, $color, $userid);
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
	 * Create a project member
	 *
	 * @param string $projectid
	 * @param string $password
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
//	#[NoCSRFRequired]
	public function publicCreateMember(
		string $projectid, string $password, string $name, float $weight = 1, int $active = 1,
		?string $color = null, ?string $userid = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addMember(
				$publicShareInfo['projectid'] ?? $projectid, $name, $weight, $active !== 0, $color, $userid
			);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add members')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get a project's bill list
	 *
	 * @param string $projectid
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
//	#[NoCSRFRequired]
	public function getBills(
		string $projectid, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			if ($limit) {
				$bills = $this->billMapper->getBillsWithLimit(
					$projectid, null, null, null, $paymentModeId, $categoryId, null, null,
					$lastchanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
				);
			} else {
				$bills = $this->billMapper->getBills(
					$projectid, null, null, null, $paymentModeId, $categoryId, null, null,
					$lastchanged, null, $reverse, $payerId, $deleted
				);
			}
			$billIds = $this->projectService->getAllBillIds($projectid, $deleted);
			$ts = (new DateTime())->getTimestamp();
			$result = [
				'nb_bills' => $this->billMapper->countBills($projectid, $payerId, $categoryId, $paymentModeId, $deleted),
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
	 * Get a project's bill list
	 *
	 * @param string $projectid
	 * @param string $password
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
//	#[NoCSRFRequired]
	public function publicGetBills(
		string $projectid, string $password, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			if ($limit) {
				$bills = $this->billMapper->getBillsWithLimit(
					$publicShareInfo['projectid'] ?? $projectid, null, null,
					null, $paymentModeId, $categoryId, null, null,
					$lastchanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
				);
			} else {
				$bills = $this->billMapper->getBills(
					$publicShareInfo['projectid'] ?? $projectid, null, null,
					null, $paymentModeId, $categoryId, null, null,
					$lastchanged, null, $reverse, $payerId, $deleted
				);
			}
			$billIds = $this->projectService->getAllBillIds($publicShareInfo['projectid'] ?? $projectid, $deleted);
			$ts = (new DateTime())->getTimestamp();
			$result = [
				'nb_bills' => $this->billMapper->countBills(
					$publicShareInfo['projectid'] ?? $projectid, $payerId, $categoryId, $paymentModeId, $deleted
				),
				'bills' => $bills,
				'allBillIds' => $billIds,
				'timestamp' => $ts,
			];
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
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
//	#[NoCSRFRequired]
	public function getProjects(): DataResponse {
		return new DataResponse(
			$this->projectService->getProjects($this->userId)
		);
	}

	/**
	 * Get a project's member list
	 *
	 * @param string $projectid
	 * @param int|null $lastchanged
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function getMembers(string $projectid, ?int $lastchanged = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$members = $this->projectService->getMembers($projectid, null, $lastchanged);
			return new DataResponse($members);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Get a project's member list
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int|null $lastchanged
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicGetMembers(string $projectid, string $password, ?int $lastchanged = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$members = $this->projectService->getMembers($publicShareInfo['projectid'] ?? $projectid, null, $lastchanged);
			return new DataResponse($members);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $projectid
	 * @param int $memberid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function deleteMember(string $projectid, int $memberid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteMember($projectid, $memberid);
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
	 * Delete or disable a member
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $memberid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicDeleteMember(string $projectid, string $password, int $memberid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deleteMember($publicShareInfo['projectid'] ?? $projectid, $memberid);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a shared access level
	 *
	 * @param string $projectid
	 * @param int $shid
	 * @param int $accesslevel
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function editShareAccessLevel(string $projectid, int $shid, int $accesslevel): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than their level (do not downgrade one)
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $accesslevel && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccessLevel($projectid, $shid, $accesslevel);
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
	 * @param string $projectid
	 * @param int $shid
	 * @param string|null $label
	 * @param string|null $password
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function editShareAccess(string $projectid, int $shid, ?string $label = null, ?string $password = null): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than their level (do not downgrade one)
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccess($projectid, $shid, $label, $password);
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
	 * @param string $projectid
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function createPaymentMode(string $projectid, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addPaymentMode($projectid, $name, $icon, $color, $order);
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
	 * Create a payment mode
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicCreatePaymentMode(string $projectid, string $password, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addPaymentMode(
				$publicShareInfo['projectid'] ?? $projectid, $name, $icon, $color, $order
			);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a payment mode
	 *
	 * @param string $projectid
	 * @param int $pmid
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function editPaymentMode(
		string $projectid, int $pmid, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editPaymentMode($projectid, $pmid, $name, $icon, $color);
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
	 * Edit a payment mode
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $pmid
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicEditPaymentMode(
		string $projectid, string $password, int $pmid, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->editPaymentMode(
				$publicShareInfo['projectid'] ?? $projectid, $pmid, $name, $icon, $color
			);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $projectid
	 * @param array $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function savePaymentModeOrder(string $projectid, array $order): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			if ($this->projectService->savePaymentModeOrder($projectid, $order)) {
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
	 * Save payment modes order
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param array $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicSavePaymentModeOrder(string $projectid, string $password, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			if ($this->projectService->savePaymentModeOrder($publicShareInfo['projectid'] ?? $projectid, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $projectid
	 * @param int $pmid
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function deletePaymentMode(string $projectid, int $pmid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deletePaymentMode($projectid, $pmid);
			if (isset($result['success'])) {
				return new DataResponse($pmid);
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
	 * Delete a payment mode
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $pmid
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicDeletePaymentMode(string $projectid, string $password, int $pmid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deletePaymentMode($publicShareInfo['projectid'] ?? $projectid, $pmid);
			if (isset($result['success'])) {
				return new DataResponse($pmid);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a category
	 *
	 * @param string $projectid
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function createCategory(string $projectid, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addCategory($projectid, $name, $icon, $color, $order);
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
	 * Create a category
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicCreateCategory(string $projectid, string $password, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addCategory(
				$publicShareInfo['projectid'] ?? $projectid, $name, $icon, $color, $order
			);
			if (is_numeric($result)) {
				// inserted category id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a category
	 *
	 * @param string $projectid
	 * @param int $categoryid
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function editCategory(
		string $projectid, int $categoryid, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
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
	 * Edit a category
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $categoryid
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicEditCategory(
		string $projectid, string $password, int $categoryid,
		?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->editCategory(
				$publicShareInfo['projectid'] ?? $projectid, $categoryid, $name, $icon, $color
			);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Save categories order
	 *
	 * @param string $projectid
	 * @param array $order
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function saveCategoryOrder(string $projectid, array $order): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			if ($this->projectService->saveCategoryOrder($projectid, $order)) {
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
	 * Save categories order
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param array $order
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicSaveCategoryOrder(string $projectid, string $password, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			if ($this->projectService->saveCategoryOrder($publicShareInfo['projectid'] ?? $projectid, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectid
	 * @param int $categoryid
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[CORS]
//	#[NoCSRFRequired]
	public function deleteCategory(string $projectid, int $categoryid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteCategory($projectid, $categoryid);
			if (isset($result['success'])) {
				return new DataResponse($categoryid);
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
	 * Delete a category
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int $categoryid
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
//	#[NoCSRFRequired]
	public function publicDeleteCategory(string $projectid, string $password, int $categoryid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deleteCategory($publicShareInfo['projectid'] ?? $projectid, $categoryid);
			if (isset($result['success'])) {
				return new DataResponse($categoryid);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	// TODO continue from there

	/**
	 * @NoAdminRequired
	 */
	public function addCurrency(string $projectid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addCurrency($projectid, $name, $rate);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiAddCurrency(string $projectid, string $password, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addCurrency($publicShareInfo['projectid'] ?? $projectid, $name, $rate);
			if (is_numeric($result)) {
				// inserted currency id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAddCurrency(string $projectid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->addCurrency($projectid, $name, $rate);
			if (is_numeric($result)) {
				// inserted bill id
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
	 * @NoAdminRequired
	 */
	public function editCurrency(string $projectid, int $currencyid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiEditCurrency(string $projectid, string $password, int $currencyid, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->editCurrency(
				$publicShareInfo['projectid'] ?? $projectid, $currencyid, $name, $rate
			);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivEditCurrency(string $projectid, int $currencyid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteCurrency(string $projectid, int $currencyid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteCurrency($projectid, $currencyid);
			if (isset($result['success'])) {
				return new DataResponse($currencyid);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiDeleteCurrency(string $projectid, string $password, int $currencyid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deleteCurrency($publicShareInfo['projectid'] ?? $projectid, $currencyid);
			if (isset($result['success'])) {
				return new DataResponse($currencyid);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeleteCurrency(string $projectid, int $currencyid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['maintainer']) {
			$result = $this->projectService->deleteCurrency($projectid, $currencyid);
			if (isset($result['success'])) {
				return new DataResponse($currencyid);
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
	 * @NoAdminRequired
	 */
	public function addUserShare(string $projectid, string $userid, int $accesslevel = Application::ACCESS_LEVELS['participant'],
								bool $manually_added = true): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addUserShare($projectid, $userid, $this->userId, $accesslevel, $manually_added);
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
	 * @NoAdminRequired
	 */
	public function deleteUserShare(string $projectid, int $shid): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteUserShare($projectid, $shid, $this->userId);
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
	 * @NoAdminRequired
	 */
	public function addPublicShare(string $projectid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addPublicShare($projectid);
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
	 * @NoAdminRequired
	 */
	public function deletePublicShare(string $projectid, int $shid): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deletePublicShare($projectid, $shid);
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
	 * @NoAdminRequired
	 */
	public function addGroupShare(string $projectid, string $groupid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addGroupShare($projectid, $groupid, $this->userId);
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
	 * @NoAdminRequired
	 */
	public function deleteGroupShare(string $projectid, int $shid): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteGroupShare($projectid, $shid, $this->userId);
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
	 * @NoAdminRequired
	 */
	public function addCircleShare(string $projectid, string $circleid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVELS['participant']) {
			$result = $this->projectService->addCircleShare($projectid, $circleid, $this->userId);
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
	 * @NoAdminRequired
	 */
	public function deleteCircleShare(string $projectid, int $shid): DataResponse {
		// allow to delete share if user perms are at least participant AND if this share perms are <= user perms
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		if ($userAccessLevel >= Application::ACCESS_LEVELS['participant'] && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteCircleShare($projectid, $shid, $this->userId);
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
	 * @NoAdminRequired
	 */
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
	 * @NoAdminRequired
	 */
	public function exportCsvSettlement(string $projectid, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->exportCsvSettlement($projectid, $this->userId, $centeredOn, $maxTimestamp);
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
	 * @NoAdminRequired
	 */
	public function exportCsvStatistics(string $projectid, ?int $tsMin = null, ?int $tsMax = null,
										?int $paymentModeId = null, ?int $category = null,
										?float $amountMin = null, ?float $amountMax = null, int $showDisabled = 1,
										?int $currencyId = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->exportCsvStatistics(
				$projectid, $this->userId, $tsMin, $tsMax,
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
	 * @NoAdminRequired
	 */
	public function exportCsvProject(string $projectid, ?string $name = null, ?string $uid = null): DataResponse {
		$userId = $uid;
		if ($this->userId) {
			$userId = $this->userId;
		}

		if ($this->projectService->userCanAccessProject($userId, $projectid)) {
			$result = $this->projectService->exportCsvProject($projectid, $userId, $name);
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
	 * @NoAdminRequired
	 */
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
	 * @NoAdminRequired
	 */
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
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
	 * @NoAdminRequired
	 */
	public function getBillActivity(?int $since): DataResponse {
		$result = $this->projectService->getBillActivity($this->userId, $since);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
