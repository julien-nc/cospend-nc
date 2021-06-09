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

use OCP\IConfig;
use \OCP\IL10N;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\ApiController;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Share\IShare;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;
use OCP\IDBConnection;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\Activity\ActivityManager;

require_once __DIR__ . '/../Service/const.php';

function endswith($string, $test) {
	$strlen = strlen($string);
	$testlen = strlen($test);
	if ($testlen > $strlen) return false;
	return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class PageController extends ApiController {

	private $userId;
	private $config;
	private $appVersion;
	private $shareManager;
	private $userManager;
	private $dbconnection;
	private $trans;
	protected $appName;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IManager $shareManager,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IL10N $trans,
								LoggerInterface $logger,
								BillMapper $billMapper,
								ProjectMapper $projectMapper,
								ProjectService $projectService,
								ActivityManager $activityManager,
								IDBConnection $dbconnection,
								IRootFolder $root,
								?string $userId){
		parent::__construct($AppName, $request,
							'PUT, POST, GET, DELETE, PATCH, OPTIONS',
							'Authorization, Content-Type, Accept',
							1728000);
		$this->logger = $logger;
		$this->appName = $AppName;
		$this->billMapper = $billMapper;
		$this->projectMapper = $projectMapper;
		$this->projectService = $projectService;
		$this->appVersion = $config->getAppValue('cospend', 'installed_version');
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->activityManager = $activityManager;
		$this->trans = $trans;
		$this->config = $config;
		$this->root = $root;
		$this->dbconnection = $dbconnection;
		$this->shareManager = $shareManager;
	}

	/**
	 * Welcome page
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		// PARAMS to view
		$params = [
			'projectid' => '',
			'password' => '',
			'username' => $this->userId,
			'cospend_version' => $this->appVersion,
		];
		$response = new TemplateResponse('cospend', 'main', $params);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			//->addAllowedChildSrcDomain('*')
			->addAllowedFrameDomain('*')
			->addAllowedWorkerSrcDomain('*')
			//->allowInlineScript(true)
			->allowEvalScript(true)
			->addAllowedObjectDomain('*')
			->addAllowedScriptDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function pubLoginProjectPassword(string $projectid, string $password = ''): PublicTemplateResponse {
		// PARAMS to view
		$params = [
			'projectid' => $projectid,
			'password' => $password,
			'wrong' => false,
			'cospend_version' => $this->appVersion
		];
		$response = new PublicTemplateResponse('cospend', 'login', $params);
		$response->setHeaderTitle($this->trans->t('Cospend public access'));
		$response->setHeaderDetails($this->trans->t('Enter password of project %s', [$projectid]));
		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			//->addAllowedChildSrcDomain('*')
			->addAllowedFrameDomain('*')
			->addAllowedWorkerSrcDomain('*')
			->addAllowedObjectDomain('*')
			->addAllowedScriptDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function pubLoginProject(string $projectid): PublicTemplateResponse {
		// PARAMS to view
		$params = [
			'projectid' => $projectid,
			'wrong' => false,
			'cospend_version' => $this->appVersion,
		];
		$response = new PublicTemplateResponse('cospend', 'login', $params);
		$response->setHeaderTitle($this->trans->t('Cospend public access'));
		$response->setHeaderDetails($this->trans->t('Enter password of project %s', [$projectid]));
		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			//->addAllowedChildSrcDomain('*')
			->addAllowedFrameDomain('*')
			->addAllowedWorkerSrcDomain('*')
			->addAllowedObjectDomain('*')
			->addAllowedScriptDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function pubLogin(): PublicTemplateResponse {
		// PARAMS to view
		$params = [
			'wrong' => false,
			'cospend_version' => $this->appVersion,
		];
		$response = new PublicTemplateResponse('cospend', 'login', $params);
		$response->setHeaderTitle($this->trans->t('Cospend public access'));
		$response->setHeaderDetails($this->trans->t('Enter project id and password'));
		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			//->addAllowedChildSrcDomain('*')
			->addAllowedFrameDomain('*')
			->addAllowedWorkerSrcDomain('*')
			->addAllowedObjectDomain('*')
			->addAllowedScriptDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function publicShareLinkPage(string $token): PublicTemplateResponse {
		$result = $this->projectService->getProjectInfoFromShareToken($token);
		if ($result['projectid'] !== null) {
			// PARAMS to view
			$params = [
				'projectid' => $result['projectid'],
				'password' => $token,
				'cospend_version' => $this->appVersion,
			];
			$response = new PublicTemplateResponse('cospend', 'main', $params);
			$response->setHeaderTitle($this->trans->t('Cospend public access'));
			$response->setHeaderDetails($this->trans->t('Project %s', [$result['projectid']]));
			$response->setFooterVisible(false);
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedImageDomain('*')
				->addAllowedMediaDomain('*')
				//->addAllowedChildSrcDomain('*')
				->addAllowedFrameDomain('*')
				->addAllowedWorkerSrcDomain('*')
				->allowEvalScript(true)
				->addAllowedObjectDomain('*')
				->addAllowedScriptDomain('*')
				->addAllowedConnectDomain('*');
			$response->setContentSecurityPolicy($csp);
			return $response;
		} else {
			$params = [
				'wrong' => true,
				'cospend_version' => $this->appVersion,
			];
			$response = new PublicTemplateResponse('cospend', 'login', $params);
			$response->setHeaderTitle($this->trans->t('Cospend public access'));
			$response->setHeaderDetails($this->trans->t('Access denied'));
			$response->setFooterVisible(false);
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedImageDomain('*')
				->addAllowedMediaDomain('*')
				//->addAllowedChildSrcDomain('*')
				->addAllowedFrameDomain('*')
				->addAllowedWorkerSrcDomain('*')
				->addAllowedObjectDomain('*')
				->addAllowedScriptDomain('*')
				->addAllowedConnectDomain('*');
			$response->setContentSecurityPolicy($csp);
			return $response;
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function pubProject(string $projectid, string $password): PublicTemplateResponse {
		if ($this->checkLogin($projectid, $password)) {
			// PARAMS to view
			$params = [
				'projectid' => $projectid,
				'password' => $password,
				'cospend_version' => $this->appVersion,
			];
			$response = new PublicTemplateResponse('cospend', 'main', $params);
			$response->setHeaderTitle($this->trans->t('Cospend public access'));
			$response->setHeaderDetails($this->trans->t('Project %s', [$projectid]));
			$response->setFooterVisible(false);
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedImageDomain('*')
				->addAllowedMediaDomain('*')
				//->addAllowedChildSrcDomain('*')
				->addAllowedFrameDomain('*')
				->addAllowedWorkerSrcDomain('*')
				->allowEvalScript(true)
				->addAllowedObjectDomain('*')
				->addAllowedScriptDomain('*')
				->addAllowedConnectDomain('*');
			$response->setContentSecurityPolicy($csp);
			return $response;
		} else {
			//$response = new DataResponse(null, 403);
			//return $response;
			$params = [
				'wrong' => true,
				'cospend_version' => $this->appVersion,
			];
			$response = new PublicTemplateResponse('cospend', 'login', $params);
			$response->setHeaderTitle($this->trans->t('Cospend public access'));
			$response->setHeaderDetails($this->trans->t('Access denied'));
			$response->setFooterVisible(false);
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedImageDomain('*')
				->addAllowedMediaDomain('*')
				//->addAllowedChildSrcDomain('*')
				->addAllowedFrameDomain('*')
				->addAllowedWorkerSrcDomain('*')
				->addAllowedObjectDomain('*')
				->addAllowedScriptDomain('*')
				->addAllowedConnectDomain('*');
			$response->setContentSecurityPolicy($csp);
			return $response;
		}
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
			while ($row = $req->fetch()){
				$dbPassword = $row['password'];
				break;
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();
			return (
				$password !== null &&
				$password !== '' &&
				$dbPassword !== null &&
				password_verify($password, $dbPassword)
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webCreateProject(string $id, string $name, ?string $password = null): DataResponse {
		$user = $this->userManager->get($this->userId);
		$userEmail = $user->getEMailAddress();
		$result = $this->projectService->createProject($name, $id, $password, $userEmail, $this->userId);
		if (isset($result['id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['id']);
			$projInfo['myaccesslevel'] = ACCESS_ADMIN;
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, 400);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webDeleteProject(string $projectid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_ADMIN) {
			$result = $this->projectService->deleteProject($projectid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webDeleteBill(string $projectid, int $billid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$billObj = null;
			if ($this->projectService->getBill($projectid, $billid) !== null) {
				$billObj = $this->billMapper->find($billid);
			}

			$result = $this->projectService->deleteBill($projectid, $billid);
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
			$response = new DataResponse(
				['message' => $this->trans->t('You are not allowed to delete this bill')],
				403
			);
			return $response;
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webDeleteBills(string $projectid, array $billIds): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			foreach ($billIds as $billid) {
				$billObj = null;
				if ($this->projectService->getBill($projectid, $billid) !== null) {
					$billObj = $this->billMapper->find($billid);
				}
				$result = $this->projectService->deleteBill($projectid, $billid);
				if (!isset($result['success'])) {
					return new DataResponse($result, 400);
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
			$response = new DataResponse(
				['message' => $this->trans->t('You are not allowed to delete this bill')],
				403
			);
			return $response;
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webGetProjectInfo(string $projectid): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$projectInfo = $this->projectService->getProjectInfo($projectid);
			return new DataResponse($projectInfo);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get this project\'s info')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webGetProjectStatistics(string $projectid, ?int $tsMin = null, ?int $tsMax = null, ?string $paymentMode = null,
											?int $category = null, ?float $amountMin = null, ?float $amountMax = null,
											string $showDisabled = '1', ?int $currencyId = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->getProjectStatistics(
				$projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
				$category, $amountMin, $amountMax, $showDisabled === '1', $currencyId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get this project\'s statistics')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webGetProjectSettlement(string $projectid, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
			return new DataResponse($result);
		}
		else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get this project\'s settlement')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webAutoSettlement(string $projectid, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 403);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to settle this project automatically')],
				403
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
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webEditMember(string $projectid, int $memberid, ?string $name = null,
								?float $weight = null, $activated = null, ?string $color = null,
								?string $userid = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
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
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this member')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webEditBill(string $projectid, int $billid, ?string $date = null, ?string $what = null, ?int $payer = null,
								?string $payed_for = null, ?float $amount = null, ?string $repeat = null, ?string $paymentmode = null,
								?int $categoryid = null, ?int $repeatallactive = null, ?string $repeatuntil = null,
								?int $timestamp = null, ?string $comment = null, ?int $repeatfreq = null): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= ACCESS_PARTICIPANT) {
			$result =  $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
													   $amount, $repeat, $paymentmode, $categoryid,
													   $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billid);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					[]
				);

				return new DataResponse($result['edited_bill_id']);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webRepeatBill(string $projectid, int $billid): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->cronRepeatBills($billid);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webEditBills(string $projectid, array $billIds, ?int $categoryid = null, ?string $date = null,
								?string $what = null, ?int $payer = null, ?string $payed_for = null,
								?float $amount = null, ?string $repeat = null, ?string $paymentmode = null,
								?int $repeatallactive = null, ?string $repeatuntil = null, ?int $timestamp = null,
								?string $comment = null, ?int $repeatfreq = null): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= ACCESS_PARTICIPANT) {
			foreach ($billIds as $billid) {
				$result =  $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
														$amount, $repeat, $paymentmode, $categoryid,
														$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
				if (isset($result['edited_bill_id'])) {
					$billObj = $this->billMapper->find($billid);
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_BILL, $billObj,
						ActivityManager::SUBJECT_BILL_UPDATE,
						[]
					);
				} else {
					return new DataResponse($result, 400);
				}
			}
			return new DataResponse($billIds);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webEditProject(string $projectid, string $name, ?string $contact_email = null, ?string $password = null,
									?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
									?string $categorysort = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_ADMIN) {
			$result = $this->projectService->editProject($projectid, $name, $contact_email, $password, $autoexport, $currencyname, $deletion_disabled, $categorysort);
			if (isset($result['success'])) {
				return new DataResponse('UPDATED');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webAddBill(string $projectid, ?string $date = null, ?string $what = null, ?int $payer = null, ?string $payed_for = null,
							?float $amount = null, ?string $repeat = null, ?string $paymentmode = null, ?int $categoryid = null,
							?int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
							?int $repeatfreq = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
													 $repeat, $paymentmode, $categoryid, $repeatallactive,
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
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webAddMember(string $projectid, string $name, ?string $userid = null,
								float $weight = 1, int $active = 1, ?string $color=null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->addMember($projectid, $name, $weight, $active !== 0, $color, $userid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result['error'], 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add members')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 */
	public function webGetBills(string $projectid, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null,
								bool $reverse = false): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			if ($limit) {
				$bills = $this->projectService->getBillsWithLimit(
					$projectid, null, null, null, null, null, null, $lastchanged, $limit, $reverse, $offset
				);
			} else {
				$bills = $this->projectService->getBills(
					$projectid, null, null, null, null, null, null, $lastchanged, null, $reverse
				);
			}
			$result = [
				'nb_bills' => $this->projectService->getNbBills($projectid),
				'bills' => $bills,
			];
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to get the bill list')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 */
	public function webGetProjects(): DataResponse {
		return new DataResponse(
			$this->projectService->getProjects($this->userId)
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 */
	public function webGetProjects2(): DataResponse {
		return new DataResponse(
			$this->projectService->getProjects($this->userId)
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiCreateProject(string $name, string $id, ?string $password = null, ?string $contact_email = null): DataResponse {
		$allow = intval($this->config->getAppValue('cospend', 'allowAnonymousCreation'));
		if ($allow) {
			$result = $this->projectService->createProject($name, $id, $password, $contact_email);
			if (isset($result['id'])) {
				return new DataResponse($result['id']);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Anonymous project creation is not allowed on this server')],
				403
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
			return new DataResponse($result, 400);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiGetProjectInfo(string $projectid, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			$projectInfo = $this->projectService->getProjectInfo($projectid);
			if ($projectInfo !== null) {
				unset($projectInfo['userid']);
				// for public link share: set the visible access level for frontend
				if ($publicShareInfo['accesslevel'] !== null) {
					$projectInfo['myaccesslevel'] = $publicShareInfo['accesslevel'];
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
				['message' => $this->trans->t('Bad password or public link')],
				400
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
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiSetProjectInfo(string $projectid, string $passwd, string $name, ?string $contact_email = null,
									?string $password = null, ?string $autoexport = null, ?string $currencyname = null,
									?bool $deletion_disabled = null, ?string $categorysort = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($passwd);
		if (
			($this->checkLogin($projectid, $passwd) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_ADMIN)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_ADMIN)
		) {
			$result = $this->projectService->editProject($projectid, $name, $contact_email, $password, $autoexport, $currencyname, $deletion_disabled, $categorysort);
			if (isset($result['success'])) {
				return new DataResponse('UPDATED');
			} else {
				return new DataResponse($result, 400);
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
	public function apiPrivSetProjectInfo(string $projectid, string $name, ?string $contact_email = null, ?string $password = null,
										?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
										?string $categorysort = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_ADMIN) {
			$result = $this->projectService->editProject($projectid, $name, $contact_email, $password, $autoexport, $currencyname, $deletion_disabled, $categorysort);
			if (isset($result['success'])) {
				return new DataResponse('UPDATED');
			} else {
				return new DataResponse($result, 400);
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			$members = $this->projectService->getMembers($projectid, null, $lastchanged);
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
				403
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
								?int $offset = 0, ?int $limit = null, bool $reverse = false): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			if ($limit) {
				$bills = $this->projectService->getBillsWithLimit(
					$projectid, null, null, null, null, null, null, $lastchanged, $limit, $reverse, $offset
				);
			} else {
				$bills = $this->projectService->getBills(
					$projectid, null, null, null, null, null, null, $lastchanged, null, $reverse
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
	 */
	public function apiv3GetBills(string $projectid, string $password, ?int $lastchanged = null,
								?int $offset = 0, ?int $limit = null, bool $reverse = false): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			if ($limit) {
				$bills = $this->projectService->getBillsWithLimit(
					$projectid, null, null, null, null, null, null, $lastchanged, $limit, $reverse, $offset
				);
			} else {
				$bills = $this->projectService->getBills(
					$projectid, null, null, null, null, null, null, $lastchanged, null, $reverse
				);
			}
			$result = [
				'nb_bills' => $this->projectService->getNbBills($projectid),
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
	public function apiPrivGetBills(string $projectid, ?int $lastchanged = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
			$billIds = $this->projectService->getAllBillIds($projectid);
			$ts = (new \DateTime())->getTimestamp();
			return new DataResponse([
				'bills' => $bills,
				'allBillIds' => $billIds,
				'timestamp' => $ts,
			]);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiv2GetBills(string $projectid, string $password, ?int $lastchanged = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			$bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
			$billIds = $this->projectService->getAllBillIds($projectid);
			$ts = (new \DateTime())->getTimestamp();
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->addMember($projectid, $name, $weight, $active !== 0, $color, null);
			if (!isset($result['error'])) {
				return new DataResponse($result['id']);
			} else {
				return new DataResponse($result['error'], 400);
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->addMember($projectid, $name, $weight, $active !== 0, $color, $userid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result['error'], 400);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->addMember($projectid, $name, $weight, $active !== 0, $color, $userid);
			if (!isset($result['error'])) {
				return new DataResponse($result['id']);
			} else {
				return new DataResponse($result['error'], 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add members')],
				403
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
							?string $payed_for = null, ?float $amount = null, string $repeat = 'n', ?string $paymentmode = null,
							?int $categoryid = null, ?int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
							?string $comment = null, ?int $repeatfreq = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
			$result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
													 $repeat, $paymentmode, $categoryid, $repeatallactive,
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
				return new DataResponse($result, 400);
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
								?string $payed_for = null, ?float $amount = null, string $repeat = 'n', ?string $paymentmode = null,
								?int $categoryid = null, ?int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
								?string $comment = null, ?int $repeatfreq = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
													 $repeat, $paymentmode, $categoryid, $repeatallactive,
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
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
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
	public function apiEditBill(string $projectid, string $password, int $billid, ?string $date = null, ?string $what = null,
								?int $payer = null, ?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
								?string $paymentmode = null, ?int $categoryid = null, ?int $repeatallactive = null,
								?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
								?int $repeatfreq = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
			$result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
													  $amount, $repeat, $paymentmode, $categoryid,
													  $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billid);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					[]
				);

				return new DataResponse($result['edited_bill_id']);
			} else {
				return new DataResponse($result, 400);
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
	public function apiEditBills(string $projectid, string $password, array $billIds, ?int $categoryid = null, ?string $date = null,
								?string $what = null, ?int $payer = null, ?string $payed_for = null, ?float $amount = null,
								?string $repeat = 'n', ?string $paymentmode = null, ?int $repeatallactive = null,
								?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
								?int $repeatfreq = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
			foreach ($billIds as $billid) {
				$result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
														$amount, $repeat, $paymentmode, $categoryid,
														$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
				if (isset($result['edited_bill_id'])) {
					$billObj = $this->billMapper->find($billid);
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_BILL, $billObj,
						ActivityManager::SUBJECT_BILL_UPDATE,
						[]
					);
				} else {
					return new DataResponse($result, 400);
				}
			}
			return new DataResponse($result);
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
	public function apiPrivEditBill(string $projectid, int $billid, ?string $date = null, ?string $what = null,
								?int $payer = null, ?string $payed_for = null, ?float $amount = null, ?string $repeat = 'n',
								?string $paymentmode = null, ?int $categoryid = null, ?int $repeatallactive = null,
								?string $repeatuntil = null, ?int $timestamp = null, ?string $comment=null,
								?int $repeatfreq = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
													  $amount, $repeat, $paymentmode, $categoryid,
													  $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billid);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					[]
				);

				return new DataResponse($result['edited_bill_id']);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiDeleteBill(string $projectid, string $password, int $billid): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
			$billObj = null;
			if ($this->projectService->getBill($projectid, $billid) !== null) {
				$billObj = $this->billMapper->find($billid);
			}

			$result = $this->projectService->deleteBill($projectid, $billid);
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
	public function apiDeleteBills(string $projectid, string $password, array $billIds): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
			foreach ($billIds as $billid) {
				$billObj = null;
				if ($this->projectService->getBill($projectid, $billid) !== null) {
					$billObj = $this->billMapper->find($billid);
				}

				$result = $this->projectService->deleteBill($projectid, $billid);
				if (!isset($result['success'])) {
					return new DataResponse($result, 404);
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
	public function apiPrivDeleteBill(string $projectid, int $billid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$billObj = null;
			if ($this->projectService->getBill($projectid, $billid) !== null) {
				$billObj = $this->billMapper->find($billid);
			}

			$result = $this->projectService->deleteBill($projectid, $billid);
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
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->deleteMember($projectid, $memberid);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->deleteMember($projectid, $memberid);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_ADMIN)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_ADMIN)
		) {
			$result = $this->projectService->deleteProject($projectid);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_ADMIN) {
			$result = $this->projectService->deleteProject($projectid);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], 404);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
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
								?string $color = null, ?string $userid = null) {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
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
				return new DataResponse($result, 403);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
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
				return new DataResponse($result, 403);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function apiGetProjectStatistics(string $projectid, string $password, ?int $tsMin = null, ?int $tsMax = null,
											?string $paymentMode = null, ?int $category = null,
											?float $amountMin = null, ?float $amountMax=null,
											string $showDisabled = '1', ?int $currencyId = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			$result = $this->projectService->getProjectStatistics(
				$projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
				$category, $amountMin, $amountMax, $showDisabled === '1', $currencyId
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
	public function apiPrivGetProjectStatistics(string $projectid, ?int $tsMin = null, ?int $tsMax = null, ?string $paymentMode = null,
											?int $category = null, ?float $amountMin = null, ?float $amountMax = null,
											string $showDisabled = '1', ?int $currencyId = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->getProjectStatistics(
				$projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
				$category, $amountMin, $amountMax, $showDisabled === '1', $currencyId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
			$result = $this->projectService->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
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
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
		) {
			$result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 403);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 403);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function editShareAccessLevel(string $projectid, int $shid, int $accesslevel): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		// allow edition if user is at least participant and has greater or equal access level than target
		// user can't give higher access level than his/her level (do not downgrade one)
		if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $accesslevel && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->editShareAccessLevel($projectid, $shid, $accesslevel);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to give such shared access level')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function editGuestAccessLevel(string $projectid, int $accesslevel): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= ACCESS_ADMIN) {
			$result = $this->projectService->editGuestAccessLevel($projectid, $accesslevel);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit guest access level')],
				403
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
		$response = new DataResponse(
			['message' => $this->trans->t('You are not allowed to edit guest access level')],
			403
		);
		return $response;
		//if ($this->checkLogin($projectid, $password)) {
		//    $guestAccessLevel = $this->projectService->getGuestAccessLevel($projectid);
		//    if ($guestAccessLevel >= ACCESS_PARTICIPANT and $guestAccessLevel >= $accesslevel) {
		//        $result = $this->projectService->editGuestAccessLevel($projectid, $accesslevel);
		//        if ($result === 'OK') {
		//            return new DataResponse($result);
		//        }
		//        else {
		//            return new DataResponse($result, 400);
		//        }
		//    }
		//    else {
		//        return new DataResponse(
		//            ['message' => $this->trans->t('You are not allowed to give such access level')],
		//				403
		//        );
		//    }
		//}
		//else {
		//    return new DataResponse(
		//        ['message' => $this->trans->t('You are not allowed to access this project')],
		//			403
		//    );
		//}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addCategory(string $projectid, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->addCategory($projectid, $name, $icon, $color, $order);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->addCategory($projectid, $name, $icon, $color, $order);
			if (is_numeric($result)) {
				// inserted category id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
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
	public function apiPrivAddCategory(string $projectid, string $name, ?string $icon = null, ?string $color = null) {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->addCategory($projectid, $name, $icon, $color);
			if (is_numeric($result)) {
				// inserted category id
				return new DataResponse($result);
			}
			else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function editCategory(string $projectid, int $categoryid, ?string $name = null,
								?string $icon = null, ?string $color = null): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function saveCategoryOrder(string $projectid, array $order): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			if ($this->projectService->saveCategoryOrder($projectid, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			}
			else {
				return new DataResponse($result, 403);
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			if ($this->projectService->saveCategoryOrder($projectid, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, 403);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 403);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteCategory(string $projectid, int $categoryid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->deleteCategory($projectid, $categoryid);
			if (isset($result['success'])) {
				return new DataResponse($categoryid);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->deleteCategory($projectid, $categoryid);
			if (isset($result['success'])) {
				return new DataResponse($categoryid);
			} else {
				return new DataResponse($result, 400);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->deleteCategory($projectid, $categoryid);
			if (isset($result['success'])) {
				return new DataResponse($categoryid);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addCurrency(string $projectid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->addCurrency($projectid, $name, $rate);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->addCurrency($projectid, $name, $rate);
			if (is_numeric($result)) {
				// inserted currency id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->addCurrency($projectid, $name, $rate);
			if (is_numeric($result)) {
				// inserted bill id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function editCurrency(string $projectid, int $currencyid, string $name, float $rate): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 403);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 403);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteCurrency(string $projectid, int $currencyid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->deleteCurrency($projectid, $currencyid);
			if (isset($result['success'])) {
				return new DataResponse($currencyid);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				403
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
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
		if (
			($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
			|| ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
		) {
			$result = $this->projectService->deleteCurrency($projectid, $currencyid);
			if (isset($result['success'])) {
				return new DataResponse($currencyid);
			} else {
				return new DataResponse($result, 400);
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
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
			$result = $this->projectService->deleteCurrency($projectid, $currencyid);
			if (isset($result['success'])) {
				return new DataResponse($currencyid);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addUserShare(string $projectid, string $userid, int $accesslevel = ACCESS_PARTICIPANT,
								bool $manually_added = true): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->addUserShare($projectid, $userid, $this->userId, $accesslevel, $manually_added);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				403
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
		if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteUserShare($projectid, $shid, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addPublicShare(string $projectid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->addPublicShare($projectid, $this->userId);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add public shared accesses')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deletePublicShare(string $projectid, int $shid): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		$shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
		if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deletePublicShare($projectid, $shid);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addGroupShare(string $projectid, string $groupid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->addGroupShare($projectid, $groupid, $this->userId);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				403
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
		if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteGroupShare($projectid, $shid, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addCircleShare(string $projectid, string $circleid): DataResponse {
		if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
			$result = $this->projectService->addCircleShare($projectid, $circleid, $this->userId);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this project')],
				403
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
		if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
			$result = $this->projectService->deleteCircleShare($projectid, $shid, $this->userId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to remove this shared access')],
				403
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
					if (count($shares) > 0){
						foreach($shares as $share){
							if ($share->getPassword() === null){
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
					$response = new DataResponse(['message' => $this->trans->t('Access denied')], 403);
				}
			} else {
				$response = new DataResponse(['message' => $this->trans->t('Access denied')], 403);
			}
		} else {
			$response = new DataResponse(['message' => $this->trans->t('Access denied')], 403);
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
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to export this project settlement')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function exportCsvStatistics(string $projectid, ?int $tsMin = null, ?int $tsMax = null, ?string $paymentMode = null,
										?int $category = null, ?float $amountMin = null, ?float $amountMax = null, int $showDisabled = 1,
										?int $currencyId = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
			$result = $this->projectService->exportCsvStatistics($projectid, $this->userId, $tsMin, $tsMax,
																 $paymentMode, $category, $amountMin, $amountMax,
																 $showDisabled !== 0, $currencyId);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to export this project statistics')],
				403
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
			$result = $this->projectService->exportCsvProject($projectid, $name, $userId);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to export this project')],
				403
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
			$projInfo['myaccesslevel'] = ACCESS_ADMIN;
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, 400);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function importSWProject(string $path): DataResponse {
		$result = $this->projectService->importSWProject($path, $this->userId);
		if (isset($result['project_id'])) {
			$projInfo = $this->projectService->getProjectInfo($result['project_id']);
			$projInfo['myaccesslevel'] = ACCESS_ADMIN;
			return new DataResponse($projInfo);
		} else {
			return new DataResponse($result, 400);
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
			return new DataResponse($result, 400);
		} else {
			return new DataResponse($result);
		}
	}
}
