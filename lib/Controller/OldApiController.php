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
use OCP\AppFramework\Http;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\ApiController;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;

class OldApiController extends ApiController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectService $projectService,
		private ActivityManager $activityManager,
		private IDBConnection $dbconnection,
		private ?string $userId
	) {
		parent::__construct(
			$appName, $request,
			'PUT, POST, GET, DELETE, PATCH, OPTIONS',
			'Authorization, Content-Type, Accept',
			1728000
		);
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
	 * @NoAdminRequired
	 *
	 */
	public function webCheckPassword(string $projectid, string $password): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			return new DataResponse($this->checkLogin($projectid, $password));
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to access this project')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivSetProjectInfo(string $projectid, ?string $name = null, ?string $contact_email = null, ?string $password = null,
										  ?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
										  ?string $categorysort = null, ?string $paymentmodesort = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_ADMIN) {
			$result = $this->projectService->editProject(
				$projectid, $name, $contact_email, $password, $autoexport,
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiCreateProject(string $name, string $id, ?string $password = null, ?string $contact_email = null): DataResponse {
		$allow = (int) $this->config->getAppValue('cospend', 'allowAnonymousCreation', '0');
		if ($allow) {
			$result = $this->projectService->createProject($name, $id, $password, $contact_email);
			if (isset($result['id'])) {
				return new DataResponse($result['id']);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Anonymous project creation is not allowed on this server')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivCreateProject(string $name, string $id, ?string $password = null, ?string $contact_email = null): DataResponse {
		$result = $this->projectService->createProject($name, $id, $password, $contact_email, $this->userId);
		if (isset($result['id'])) {
			return new DataResponse($result['id']);
		} else {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiGetProjectInfo(string $projectid, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$projectInfo = $this->projectService->getProjectInfo($publicShareInfo['projectid'] ?? $projectid);
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
					404
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivGetProjectInfo(string $projectid): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$projectInfo = $this->projectService->getProjectInfo($projectid);
			if ($projectInfo !== null) {
				unset($projectInfo['userid']);
				$projectInfo['myaccesslevel'] = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
				return new DataResponse($projectInfo);
			} else {
				return new DataResponse(
					['message' => $this->trans->t('Project not found')],
					404
				);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiSetProjectInfo(string $projectid, string $passwd, ?string $name = null, ?string $contact_email = null,
									?string $password = null, ?string $autoexport = null, ?string $currencyname = null,
									?bool $deletion_disabled = null, ?string $categorysort = null, ?string $paymentmodesort = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $passwd) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_ADMIN)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $passwd === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_ADMIN)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiGetMembers(string $projectid, string $password, ?int $lastchanged = null): DataResponse {
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivGetMembers(string $projectid, ?int $lastchanged = null): DataResponse {
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiGetBills(string $projectid, string $password, ?int $lastchanged = null,
								?int $offset = 0, ?int $limit = null, bool $reverse = false, ?int $deleted = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			if ($limit) {
				$bills = $this->billMapper->getBillsWithLimit(
					$publicShareInfo['projectid'] ?? $projectid, null, null,
					null, null, null, null, null,
					$lastchanged, $limit, $reverse, $offset, null, null, null, $deleted
				);
			} else {
				$bills = $this->billMapper->getBills(
					$publicShareInfo['projectid'] ?? $projectid, null, null,
					null, null, null, null, null,
					$lastchanged, null, $reverse, null, $deleted
				);
			}
			return new DataResponse($bills);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 *
	 * @param string $projectid
	 * @param string $password
	 * @param int|null $lastchanged
	 * @param int|null $offset
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $payerId
	 * @return DataResponse
	 */
	public function apiv3GetBills(
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
			$result = [
				'nb_bills' => $this->billMapper->countBills(
					$publicShareInfo['projectid'] ?? $projectid, $payerId, $categoryId, $paymentModeId, $deleted
				),
				'bills' => $bills,
			];
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivGetBills(string $projectid, ?int $lastchanged = null, ?int $deleted = 0): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$bills = $this->billMapper->getBills(
				$projectid, null, null, null, null, null,
				null, null, $lastchanged, null, false, null, $deleted
			);
			$billIds = $this->projectService->getAllBillIds($projectid, $deleted);
			$ts = (new DateTime())->getTimestamp();
			return new DataResponse([
				'bills' => $bills,
				'allBillIds' => $billIds,
				'timestamp' => $ts,
			]);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiv2GetBills(string $projectid, string $password, ?int $lastchanged = null, ?int $deleted = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$bills = $this->billMapper->getBills(
				$publicShareInfo['projectid'] ?? $projectid, null, null,
				null, null, null, null, null, $lastchanged,
				null, false, null, $deleted
			);
			$billIds = $this->projectService->getAllBillIds($publicShareInfo['projectid'] ?? $projectid, $deleted);
			$ts = (new DateTime())->getTimestamp();
			return new DataResponse([
				'bills' => $bills,
				'allBillIds' => $billIds,
				'timestamp' => $ts,
			]);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiAddMember(string $projectid, string $password, string $name,
								float $weight = 1, int $active = 1, ?string $color = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			$result = $this->projectService->createMember(
				$publicShareInfo['projectid'] ?? $projectid, $name, $weight, $active !== 0, $color, null
			);
			if (!isset($result['error'])) {
				return new DataResponse($result['id']);
			} else {
				return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add members')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiv2AddMember(string $projectid, string $password, string $name, float $weight = 1, int $active = 1,
									?string $color = null, ?string $userid = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			$result = $this->projectService->createMember(
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAddMember(string $projectid, string $name, float $weight = 1, int $active = 1,
									?string $color = null, ?string $userid = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->createMember($projectid, $name, $weight, $active !== 0, $color, $userid);
			if (!isset($result['error'])) {
				return new DataResponse($result['id']);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiAddBill(string $projectid, string $password, ?string $date = null, ?string $what = null, ?int $payer = null,
							?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
							?string $paymentmode = null, ?int $paymentmodeid = null,
							?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
							?string $comment = null, ?int $repeatfreq = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
		) {
			$result = $this->projectService->createBill(
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAddBill(string $projectid, ?string $date = null, ?string $what = null, ?int $payer = null,
								?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
								?string $paymentmode = null, ?int $paymentmodeid = null,
								?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
								?string $comment = null, ?int $repeatfreq = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_PARTICIPANT) {
			$result = $this->projectService->createBill($projectid, $date, $what, $payer, $payed_for, $amount,
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
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
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
	public function apiRepeatBill(string $projectid, string $password, int $billid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
		) {
			// TODO check if bill is in this project
			$result = $this->projectService->cronRepeatBills($billid);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiEditBill(
		string $projectid, string $password, int $billid, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiEditBills(
		string $projectid, string $password, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null, ?float $amount = null,
		?string $repeat = 'n', ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivEditBill(
		string $projectid, int $billid, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, ?string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment=null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_PARTICIPANT) {
			$result = $this->projectService->editBill(
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
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiClearTrashbin(string $projectid, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiDeleteBill(string $projectid, string $password, int $billid, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
		) {
			$billObj = null;
			if ($this->billMapper->getBill($publicShareInfo['projectid'] ?? $projectid, $billid) !== null) {
				$billObj = $this->billMapper->find($billid);
			}

			$result = $this->projectService->deleteBill($publicShareInfo['projectid'] ?? $projectid, $billid, false, $moveToTrash);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiDeleteBills(string $projectid, string $password, array $billIds, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
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
				if ($this->billMapper->getBill($publicShareInfo['projectid'] ?? $projectid, $billid) !== null) {
					$billObj = $this->billMapper->find($billid);
				}

				$result = $this->projectService->deleteBill($publicShareInfo['projectid'] ?? $projectid, $billid, false, $moveToTrash);
				if (!isset($result['success'])) {
					return new DataResponse($result, 404);
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivClearTrashbin(string $projectid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_PARTICIPANT) {
			try {
			$this->billMapper->deleteDeletedBills($projectid);
				return new DataResponse('');
			} catch (\Exception | \Throwable $e) {
				return new DataResponse('', Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeleteBill(string $projectid, int $billid, bool $moveToTrash = true): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_PARTICIPANT) {
			$billObj = null;
			if ($this->billMapper->getBill($projectid, $billid) !== null) {
				$billObj = $this->billMapper->find($billid);
			}

			$result = $this->projectService->deleteBill($projectid, $billid, false, $moveToTrash);
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
				return new DataResponse($result, 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiDeleteMember(string $projectid, string $password, int $memberid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			$result = $this->projectService->deleteMember($publicShareInfo['projectid'] ?? $projectid, $memberid);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeleteMember(string $projectid, int $memberid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->deleteMember($projectid, $memberid);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiDeleteProject(string $projectid, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_ADMIN)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_ADMIN)
		) {
			$result = $this->projectService->deleteProject($publicShareInfo['projectid'] ?? $projectid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeleteProject(string $projectid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_ADMIN) {
			$result = $this->projectService->deleteProject($projectid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiEditMember(string $projectid, string $password, int $memberid,
								?string $name = null, ?float $weight = null, $activated = null,
								?string $color = null, ?string $userid = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			if ($activated === 'true') {
				$activated = true;
			} elseif ($activated === 'false') {
				$activated = false;
			}
			$result = $this->projectService->editMember(
				$publicShareInfo['projectid'] ?? $projectid, $memberid, $name, $userid, $weight, $activated, $color
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivEditMember(string $projectid, int $memberid, ?string $name = null, ?float $weight = null,
									$activated = null, ?string $color = null, ?string $userid = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			if ($activated === 'true') {
				$activated = true;
			} elseif ($activated === 'false') {
				$activated = false;
			}
			$result = $this->projectService->editMember($projectid, $memberid, $name, $userid, $weight, $activated, $color);
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
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 *
	 * @param string $projectid
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
	public function apiGetProjectStatistics(string $projectid, string $password, ?int $tsMin = null, ?int $tsMax = null,
											?int   $paymentModeId = null, ?int $categoryId = null,
											?float $amountMin = null, ?float $amountMax=null,
											string $showDisabled = '1', ?int $currencyId = null,
											?int $payerId = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$result = $this->projectService->getProjectStatistics(
				$publicShareInfo['projectid'] ?? $projectid, 'lowername', $tsMin, $tsMax,
				$paymentModeId, $categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId,
				$payerId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 *
	 * @param string $projectid
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
	public function apiPrivGetProjectStatistics(string $projectid, ?int $tsMin = null, ?int $tsMax = null,
												?int   $paymentModeId = null,
												?int   $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
												string $showDisabled = '1', ?int $currencyId = null,
												?int $payerId = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->getProjectStatistics(
				$projectid, 'lowername', $tsMin, $tsMax, $paymentModeId,
				$categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId, $payerId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiGetProjectSettlement(string $projectid, string $password, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if ($this->checkLogin($projectid, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$result = $this->projectService->getProjectSettlement(
				$publicShareInfo['projectid'] ?? $projectid, $centeredOn, $maxTimestamp
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivGetProjectSettlement(string $projectid, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiAutoSettlement(string $projectid, string $password, ?int $centeredOn = null,
									int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_PARTICIPANT)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_PARTICIPANT)
		) {
			$result = $this->projectService->autoSettlement(
				$publicShareInfo['projectid'] ?? $projectid, $centeredOn, $precision, $maxTimestamp
			);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAutoSettlement(string $projectid, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_PARTICIPANT) {
			$result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
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
	public function apiEditGuestAccessLevel($projectid, $password, $accesslevel): DataResponse {
		return new DataResponse(
			['message' => $this->trans->t('You are not allowed to edit guest access level')],
			Http::STATUS_FORBIDDEN
		);
		//if ($this->checkLogin($projectid, $password)) {
		//    $guestAccessLevel = $this->projectService->getGuestAccessLevel($projectid);
		//    if ($guestAccessLevel >= Application::ACCESS_LEVEL_PARTICIPANT and $guestAccessLevel >= $accesslevel) {
		//        $result = $this->projectService->editGuestAccessLevel($projectid, $accesslevel);
		//        if ($result === 'OK') {
		//            return new DataResponse($result);
		//        }
		//        else {
		//            return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		//        }
		//    }
		//    else {
		//        return new DataResponse(
		//            ['message' => $this->trans->t('You are not allowed to give such access level')],
		//				Http::STATUS_FORBIDDEN
		//        );
		//    }
		//}
		//else {
		//    return new DataResponse(
		//        ['message' => $this->trans->t('You are not allowed to access this project')],
		//			Http::STATUS_FORBIDDEN
		//    );
		//}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiAddPaymentMode(string $projectid, string $password, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			$result = $this->projectService->createPaymentMode(
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAddPaymentMode(string $projectid, string $name, ?string $icon = null, ?string $color = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->createPaymentMode($projectid, $name, $icon, $color);
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiEditPaymentMode(string $projectid, string $password, int $pmid, ?string $name = null,
									?string $icon = null, ?string $color = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiSavePaymentModeOrder(string $projectid, string $password, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			if ($this->projectService->savePaymentModeOrder($publicShareInfo['projectid'] ?? $projectid, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivEditPaymentMode(string $projectid, int $pmid, ?string $name = null,
										?string $icon = null, ?string $color = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->editPaymentMode($projectid, $pmid, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
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
	public function apiDeletePaymentMode(string $projectid, string $password, int $pmid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeletePaymentMode(string $projectid, int $pmid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiAddCategory(string $projectid, string $password, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			$result = $this->projectService->createCategory(
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAddCategory(string $projectid, string $name, ?string $icon = null, ?string $color = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->createCategory($projectid, $name, $icon, $color);
			if (is_numeric($result)) {
				// inserted category id
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiEditCategory(string $projectid, string $password, int $categoryid, ?string $name = null,
									?string $icon = null, ?string $color = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiSaveCategoryOrder(string $projectid, string $password, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			if ($this->projectService->saveCategoryOrder($publicShareInfo['projectid'] ?? $projectid, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivEditCategory(string $projectid, int $categoryid, ?string $name = null,
										?string $icon = null, ?string $color = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
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
	public function apiDeleteCategory(string $projectid, string $password, int $categoryid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeleteCategory(string $projectid, int $categoryid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiAddCurrency(string $projectid, string $password, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
		) {
			$result = $this->projectService->createCurrency($publicShareInfo['projectid'] ?? $projectid, $name, $rate);
			if (is_numeric($result)) {
				// inserted currency id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivAddCurrency(string $projectid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
			$result = $this->projectService->createCurrency($projectid, $name, $rate);
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
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiEditCurrency(string $projectid, string $password, int $currencyid, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivEditCurrency(string $projectid, int $currencyid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
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
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiDeleteCurrency(string $projectid, string $password, int $currencyid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectid);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= Application::ACCESS_LEVEL_MAINTAINER)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVEL_MAINTAINER)
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
				401
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function apiPrivDeleteCurrency(string $projectid, int $currencyid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= Application::ACCESS_LEVEL_MAINTAINER) {
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
}
