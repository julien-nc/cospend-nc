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

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;
use \OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\ApiController;
use OCP\Constants;
use OCP\Share;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IServerContainer;
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
    private $userfolder;
    private $config;
    private $appVersion;
    private $shareManager;
    private $userManager;
    private $groupManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $defaultDeviceId;
    private $trans;
    private $logger;
    protected $appName;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IManager $shareManager,
                                IAppManager $appManager,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                IL10N $trans,
                                LoggerInterface $logger,
                                BillMapper $billMapper,
                                ProjectMapper $projectMapper,
                                ProjectService $projectService,
                                ActivityManager $activityManager,
                                IDBConnection $dbconnection,
                                $UserId){
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
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->activityManager = $activityManager;
        $this->trans = $trans;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;

        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '`';
        }
        $this->dbconnection = $dbconnection;
        if ($UserId !== null && $UserId !== '' && $serverContainer !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->shareManager = $shareManager;
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    /**
     * Welcome page
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        // PARAMS to view
        $params = [
            'projectid' => '',
            'password' => '',
            'username' => $this->userId,
            'cospend_version' => $this->appVersion
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
    public function pubLoginProjectPassword($projectid, $password='') {
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
    public function pubLoginProject($projectid) {
        // PARAMS to view
        $params = [
            'projectid' => $projectid,
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
    public function pubLogin() {
        // PARAMS to view
        $params = [
            'wrong' => false,
            'cospend_version' => $this->appVersion
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
    public function publicShareLinkPage($token) {
        $result = $this->projectService->getProjectInfoFromShareToken($token);
        if ($result['projectid'] !== null) {
            // PARAMS to view
            $params = [
                'projectid' => $result['projectid'],
                'password' => $token,
                'cospend_version' => $this->appVersion
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
        }
        else {
            $params = [
                'wrong' => true,
                'cospend_version' => $this->appVersion
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
    public function pubProject($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            // PARAMS to view
            $params = [
                'projectid' => $projectid,
                'password' => $password,
                'cospend_version' => $this->appVersion
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
        }
        else {
            //$response = new DataResponse(null, 403);
            //return $response;
            $params = [
                'wrong' => true,
                'cospend_version' => $this->appVersion
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

    private function checkLogin($projectId, $password) {
        if ($projectId === '' || $projectId === null ||
            $password === '' || $password === null
        ) {
            return false;
        }
        else {
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->select('id', 'password')
               ->from('cospend_projects', 'p')
               ->where(
                   $qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
               );
            $req = $qb->execute();
            $dbid = null;
            $dbPassword = null;
            while ($row = $req->fetch()){
                $dbid = $row['id'];
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
    public function webCreateProject($id, $name, $password) {
        $user = $this->userManager->get($this->userId);
        $userEmail = $user->getEMailAddress();
        $result = $this->projectService->createProject($name, $id, $password, $userEmail, $this->userId);
        if (is_string($result) && !is_array($result)) {
            $projInfo = $this->projectService->getProjectInfo($result);
            $projInfo['myaccesslevel'] = ACCESS_ADMIN;
            return new DataResponse($projInfo);
        }
        else {
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
            if ($result === 'OK') {
                if (!is_null($billObj)) {
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                        ActivityManager::SUBJECT_BILL_DELETE,
                        []
                    );
                }
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to delete this bill')]
                , 403
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
                if ($result !== 'OK') {
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
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to delete this bill')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetProjectInfo($projectid) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $projectInfo = $this->projectService->getProjectInfo($projectid);
            $response = new DataResponse($projectInfo);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to get this project\'s info')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetProjectStatistics($projectid, $tsMin=null, $tsMax=null, $paymentMode=null, $category=null,
                                            $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectStatistics(
                $projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
                $category, $amountMin, $amountMax, $showDisabled, $currencyId
            );
            return new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to get this project\'s statistics')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetProjectSettlement(string $projectid, ?int $centeredOn = null, ?int $maxTimestamp = null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
            return new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to get this project\'s settlement')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAutoSettlement(string $projectid, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to settle this project automatically')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webCheckPassword($projectid, $password) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            return new DataResponse($this->checkLogin($projectid, $password));
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to access this project')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webEditMember($projectid, $memberid, $name, $weight, $activated, $color=null, $userid=null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->editMember($projectid, $memberid, $name, $userid, $weight, $activated, $color);
            if (count($result) === 0) {
                return new DataResponse(null);
            } elseif (array_key_exists('activated', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this member')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webEditBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                $amount, $repeat, $paymentmode=null, $categoryid=null,
                                $repeatallactive=null, $repeatuntil=null, $timestamp=null,
                                $comment=null, ?int $repeatfreq = null): DataResponse {
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        if ($userAccessLevel >= ACCESS_PARTICIPANT) {
            $result =  $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                       $amount, $repeat, $paymentmode, $categoryid,
                                                       $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
            if (is_numeric($result)) {
                $billObj = $this->billMapper->find($billid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_UPDATE,
                    []
                );

                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this bill')]
                , 403
            );
            return $response;
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
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add bills')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webEditBills($projectid, $billIds, $categoryid, $date=null,
                                $what=null, $payer=null, $payed_for=null,
                                $amount=null, $repeat=null, $paymentmode=null,
                                $repeatallactive=null, $repeatuntil=null, $timestamp=null,
                                $comment=null, ?int $repeatfreq = null) {
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        if ($userAccessLevel >= ACCESS_PARTICIPANT) {
            foreach ($billIds as $billid) {
                $result =  $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                        $amount, $repeat, $paymentmode, $categoryid,
                                                        $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
                if (is_numeric($result)) {
                    $billObj = $this->billMapper->find($billid);
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                        ActivityManager::SUBJECT_BILL_UPDATE,
                        []
                    );
                }
                else {
                    return new DataResponse($result, 400);
                }
            }
            return new DataResponse($billIds);
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this bill')]
                , 403
            );
            return $response;
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
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            } else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this project')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAddBill($projectid, $date, $what, $payer, $payed_for, $amount,
                               $repeat, $paymentmode=null, $categoryid=null, $repeatallactive=0,
                               $repeatuntil=null, $timestamp=null, $comment=null, ?int $repeatfreq = null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
                                                     $repeat, $paymentmode, $categoryid, $repeatallactive,
                                                     $repeatuntil, $timestamp, $comment, $repeatfreq);
            if (is_numeric($result)) {
                $billObj = $this->billMapper->find($result);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_CREATE,
                    []
                );

                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add bills')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAddMember($projectid, $name, $userid=null, $weight=1, $active=1, $color=null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color, $userid);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add members')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetBills(string $projectid, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            if ($limit) {
                $bills = $this->projectService->getBillsRestricted(
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
            $response = new DataResponse($result);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to get the bill list')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     */
    public function webGetProjects() {
        $response = new DataResponse(
            $this->projectService->getProjects($this->userId)
        );
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     */
    public function webGetProjects2() {
        $response = new DataResponse(
            $this->projectService->getProjects($this->userId)
        );
        return $response;
    }

    /**
     * curl -X POST https://ihatemoney.org/api/projects \
     *   -d 'name=yay&id=yay&password=yay&contact_email=yay@notmyidea.org'
     *   "yay"
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiCreateProject($name, $id, $password, $contact_email) {
        $allow = intval($this->config->getAppValue('cospend', 'allowAnonymousCreation'));
        if ($allow) {
            $result = $this->projectService->createProject($name, $id, $password, $contact_email);
            if (is_string($result) && !is_array($result)) {
                // project id
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Anonymous project creation is not allowed on this server')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivCreateProject($name, $id, $password, $contact_email) {
        $result = $this->projectService->createProject($name, $id, $password, $contact_email, $this->userId);
        if (is_string($result) && !is_array($result)) {
            // project id
            return new DataResponse($result);
        }
        else {
            return new DataResponse($result, 400);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiGetProjectInfo($projectid, $password) {
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
            }
            else {
                $response = new DataResponse(
                    ['message' => $this->trans->t('Project not found')]
                    , 404
                );
                return $response;
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Bad password or public link')]
                , 400
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivGetProjectInfo($projectid) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $projectInfo = $this->projectService->getProjectInfo($projectid);
            if ($projectInfo !== null) {
                unset($projectInfo['userid']);
                return new DataResponse($projectInfo);
            }
            else {
                $response = new DataResponse(
                    ['message' => $this->trans->t('Project not found')]
                    , 404
                );
                return $response;
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
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
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            } else {
                return new DataResponse($result, 400);
            }
        } else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
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
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            } else {
                return new DataResponse($result, 400);
            }
        } else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiGetMembers($projectid, $password, $lastchanged=null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
            $members = $this->projectService->getMembers($projectid, null, $lastchanged);
            $response = new DataResponse($members);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivGetMembers($projectid, $lastchanged=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $members = $this->projectService->getMembers($projectid, null, $lastchanged);
            $response = new DataResponse($members);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiGetBills($projectid, $password, $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
            if ($limit) {
                $bills = $this->projectService->getBillsRestricted(
                    $projectid, null, null, null, null, null, null, $lastchanged, $limit, $reverse, $offset
                );
            } else {
                $bills = $this->projectService->getBills(
                    $projectid, null, null, null, null, null, null, $lastchanged, null, $reverse
                );
            }
            $response = new DataResponse($bills);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiv3GetBills($projectid, $password, $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
            if ($limit) {
                $bills = $this->projectService->getBillsRestricted(
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
            $response = new DataResponse($result);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivGetBills($projectid, $lastchanged=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
            $billIds = $this->projectService->getAllBillIds($projectid);
            $ts = (new \DateTime())->getTimestamp();
            $response = new DataResponse([
                'bills' => $bills,
                'allBillIds' => $billIds,
                'timestamp' => $ts
            ]);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiv2GetBills($projectid, $password, $lastchanged=null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
            $bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
            $billIds = $this->projectService->getAllBillIds($projectid);
            $ts = (new \DateTime())->getTimestamp();
            $response = new DataResponse([
                'bills' => $bills,
                'allBillIds' => $billIds,
                'timestamp' => $ts
            ]);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiAddMember($projectid, $password, $name, $weight, $active=1, $color=null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color, null);
            if (is_array($result)) {
                return new DataResponse($result['id']);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add members')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiv2AddMember($projectid, $password, $name, $weight, $active=1, $color=null, $userid=null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color, $userid);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add members')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivAddMember($projectid, $name, $weight, $active=1, $color=null, $userid=null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color, $userid);
            if (is_array($result)) {
                return new DataResponse($result['id']);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add members')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiAddBill($projectid, $password, $date, $what, $payer, $payed_for,
                               $amount, $repeat='n', $paymentmode=null, $categoryid=null,
                               $repeatallactive=0, $repeatuntil=null, $timestamp=null, $comment=null, ?int $repeatfreq = null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
        ) {
            $result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
                                                     $repeat, $paymentmode, $categoryid, $repeatallactive,
                                                     $repeatuntil, $timestamp, $comment, $repeatfreq);
            if (is_numeric($result)) {
                $billObj = $this->billMapper->find($result);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_CREATE,
                    []
                );
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add bills')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivAddBill($projectid, $date, $what, $payer, $payed_for,
                               $amount, $repeat='n', $paymentmode=null, $categoryid=null,
                               $repeatallactive=0, $repeatuntil=null, $timestamp=null, $comment=null, ?int $repeatfreq = null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
                                                     $repeat, $paymentmode, $categoryid, $repeatallactive,
                                                     $repeatuntil, $timestamp, $comment, $repeatfreq);
            if (is_numeric($result)) {
                $billObj = $this->billMapper->find($result);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_CREATE,
                    []
                );
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add bills')]
                , 403
            );
            return $response;
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
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add bills')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiEditBill($projectid, $password, $billid, $date, $what, $payer, $payed_for,
                                $amount, $repeat='n', $paymentmode=null, $categoryid=null,
                                $repeatallactive=null, $repeatuntil=null, $timestamp=null, $comment=null, ?int $repeatfreq = null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
        ) {
            $result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                      $amount, $repeat, $paymentmode, $categoryid,
                                                      $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
            if (is_numeric($result)) {
                $billObj = $this->billMapper->find($billid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_UPDATE,
                    []
                );

                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this bill')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiEditBills($projectid, $password, $billIds, $categoryid, $date=null,
                                $what=null, $payer=null, $payed_for=null,
                                $amount=null, $repeat='n', $paymentmode=null,
                                $repeatallactive=null, $repeatuntil=null, $timestamp=null, $comment=null, ?int $repeatfreq = null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
        ) {
            foreach ($billIds as $billid) {
                $result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                        $amount, $repeat, $paymentmode, $categoryid,
                                                        $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
                if (is_numeric($result)) {
                    $billObj = $this->billMapper->find($billid);
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                        ActivityManager::SUBJECT_BILL_UPDATE,
                        []
                    );
                }
                else {
                    return new DataResponse($result, 400);
                }
            }
            return new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this bill')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivEditBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                $amount, $repeat='n', $paymentmode=null, $categoryid=null,
                                $repeatallactive=null, $repeatuntil=null, $timestamp=null, $comment=null, ?int $repeatfreq = null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                      $amount, $repeat, $paymentmode, $categoryid,
                                                      $repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq);
            if (is_numeric($result)) {
                $billObj = $this->billMapper->find($billid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_UPDATE,
                    []
                );

                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
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
            if ($result === 'OK') {
                if (!is_null($billObj)) {
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                        ActivityManager::SUBJECT_BILL_DELETE,
                        []
                    );
                }
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
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
                if ($result !== 'OK') {
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
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
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
            if ($result === 'OK') {
                if (!is_null($billObj)) {
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                        ActivityManager::SUBJECT_BILL_DELETE,
                        []
                    );
                }
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            return new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiDeleteMember($projectid, $password, $memberid) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->deleteMember($projectid, $memberid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivDeleteMember($projectid, $memberid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->deleteMember($projectid, $memberid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
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
    public function apiEditMember($projectid, $password, $memberid, $name, $weight, $activated, $color=null, $userid=null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->editMember($projectid, $memberid, $name, $userid, $weight, $activated, $color);
            if (count($result) === 0) {
                return new DataResponse(null);
            } elseif (array_key_exists('activated', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivEditMember($projectid, $memberid, $name, $weight, $activated, $color=null, $userid=null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->editMember($projectid, $memberid, $name, $userid, $weight, $activated, $color);
            if (count($result) === 0) {
                return new DataResponse(null);
            } elseif (array_key_exists('activated', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiGetProjectStatistics($projectid, $password, $tsMin=null, $tsMax=null, $paymentMode=null,
                                            $category=null, $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
            $result = $this->projectService->getProjectStatistics(
                $projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
                $category, $amountMin, $amountMax, $showDisabled, $currencyId
            );
            $response = new DataResponse($result);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivGetProjectStatistics($projectid, $tsMin=null, $tsMax=null, $paymentMode=null,
                                            $category=null, $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectStatistics(
                $projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
                $category, $amountMin, $amountMax, $showDisabled, $currencyId
            );
            $response = new DataResponse($result);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiGetProjectSettlement(string $projectid, string $password, ?int $centeredOn = null, ?int $maxTimestamp = null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if ($this->checkLogin($projectid, $password) || $publicShareInfo['accesslevel'] !== null) {
            $result = $this->projectService->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
            $response = new DataResponse($result);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivGetProjectSettlement(string $projectid, ?int $centeredOn = null, ?int $maxTimestamp = null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
            $response = new DataResponse($result);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiAutoSettlement(string $projectid, string $password, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_PARTICIPANT)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_PARTICIPANT)
        ) {
            $result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivAutoSettlement(string $projectid, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->autoSettlement($projectid, $centeredOn, $precision, $maxTimestamp);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('Unauthorized action')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editShareAccessLevel($projectid, $shid, $accesslevel) {
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        $shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
        // allow edition if user is at least participant and has greater or equal access level than target
        // user can't give higher access level than his/her level (do not downgrade one)
        if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $accesslevel && $userAccessLevel >= $shareAccessLevel) {
            $result = $this->projectService->editShareAccessLevel($projectid, $shid, $accesslevel);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to give such shared access level')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editGuestAccessLevel($projectid, $accesslevel) {
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        if ($userAccessLevel >= ACCESS_ADMIN) {
            $result = $this->projectService->editGuestAccessLevel($projectid, $accesslevel);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit guest access level')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiEditGuestAccessLevel($projectid, $password, $accesslevel) {
        $response = new DataResponse(
            ['message' => $this->trans->t('You are not allowed to edit guest access level')]
            , 403
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
        //        $response = new DataResponse(
        //            ['message' => $this->trans->t('You are not allowed to give such access level')]
        //            , 403
        //        );
        //        return $response;
        //    }
        //}
        //else {
        //    $response = new DataResponse(
        //        ['message' => $this->trans->t('You are not allowed to access this project')]
        //        , 403
        //    );
        //    return $response;
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
    public function apiPrivAddCategory($projectid, $name, $icon, $color) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->addCategory($projectid, $name, $icon, $color);
            if (is_numeric($result)) {
                // inserted category id
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage categories')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editCategory(string $projectid, int $categoryid, $name, $icon, $color): DataResponse {
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
    public function apiEditCategory($projectid, $password, $categoryid, $name, $icon, $color) {
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
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage categories')]
                , 401
            );
            return $response;
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
    public function apiPrivEditCategory($projectid, $categoryid, $name, $icon, $color) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage categories')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteCategory($projectid, $categoryid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->deleteCategory($projectid, $categoryid);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage categories')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiDeleteCategory($projectid, $password, $categoryid) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->deleteCategory($projectid, $categoryid);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage categories')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivDeleteCategory($projectid, $categoryid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->deleteCategory($projectid, $categoryid);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage categories')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addCurrency($projectid, $name, $rate) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->addCurrency($projectid, $name, $rate);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiAddCurrency($projectid, $password, $name, $rate) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->addCurrency($projectid, $name, $rate);
            if (is_numeric($result)) {
                // inserted currency id
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivAddCurrency($projectid, $name, $rate) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->addCurrency($projectid, $name, $rate);
            if (is_numeric($result)) {
                // inserted bill id
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editCurrency($projectid, $currencyid, $name, $rate) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiEditCurrency($projectid, $password, $currencyid, $name, $rate) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivEditCurrency($projectid, $currencyid, $name, $rate) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->editCurrency($projectid, $currencyid, $name, $rate);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteCurrency($projectid, $currencyid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->deleteCurrency($projectid, $currencyid);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function apiDeleteCurrency($projectid, $password, $currencyid) {
        $publicShareInfo = $this->projectService->getProjectInfoFromShareToken($password);
        if (
            ($this->checkLogin($projectid, $password) && $this->projectService->getGuestAccessLevel($projectid) >= ACCESS_MAINTENER)
            || ($publicShareInfo['accesslevel'] !== null && $publicShareInfo['accesslevel'] >= ACCESS_MAINTENER)
        ) {
            $result = $this->projectService->deleteCurrency($projectid, $currencyid);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function apiPrivDeleteCurrency($projectid, $currencyid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_MAINTENER) {
            $result = $this->projectService->deleteCurrency($projectid, $currencyid);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to manage currencies')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addUserShare($projectid, $userid, $accesslevel = ACCESS_PARTICIPANT, $manually_added = true) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->addUserShare($projectid, $userid, $this->userId, $accesslevel, $manually_added);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this project')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteUserShare($projectid, $shid) {
        // allow to delete share if user perms are at least participant AND if this share perms are <= user perms
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        $shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
        if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
            $result = $this->projectService->deleteUserShare($projectid, $shid, $this->userId);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to remove this shared access')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addPublicShare($projectid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->addPublicShare($projectid, $this->userId);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to add public shared accesses')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deletePublicShare($projectid, $shid) {
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        $shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
        if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
            $result = $this->projectService->deletePublicShare($projectid, $shid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to remove this shared access')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addGroupShare($projectid, $groupid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->addGroupShare($projectid, $groupid, $this->userId);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this project')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteGroupShare($projectid, $shid) {
        // allow to delete share if user perms are at least participant AND if this share perms are <= user perms
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        $shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
        if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
            $result = $this->projectService->deleteGroupShare($projectid, $shid, $this->userId);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to remove this shared access')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addCircleShare($projectid, $circleid) {
        if ($this->projectService->getUserMaxAccessLevel($this->userId, $projectid) >= ACCESS_PARTICIPANT) {
            $result = $this->projectService->addCircleShare($projectid, $circleid, $this->userId);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to edit this project')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteCircleShare($projectid, $shid) {
        // allow to delete share if user perms are at least participant AND if this share perms are <= user perms
        $userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
        $shareAccessLevel = $this->projectService->getShareAccessLevel($projectid, $shid);
        if ($userAccessLevel >= ACCESS_PARTICIPANT && $userAccessLevel >= $shareAccessLevel) {
            $result = $this->projectService->deleteCircleShare($projectid, $shid, $this->userId);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to remove this shared access')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function getPublicFileShare($path) {
        $cleanPath = str_replace(array('../', '..\\'), '',  $path);
        $userFolder = \OC::$server->getUserFolder();
        if ($userFolder->nodeExists($cleanPath)) {
            $file = $userFolder->get($cleanPath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                if ($file->isShareable()) {
                    $shares = $this->shareManager->getSharesBy($this->userId,
                        \OCP\Share::SHARE_TYPE_LINK, $file, false, 1, 0);
                    if (count($shares) > 0){
                        foreach($shares as $share){
                            if ($share->getPassword() === null){
                                $token = $share->getToken();
                                break;
                            }
                        }
                    }
                    else {
                        $share = $this->shareManager->newShare();
                        $share->setNode($file);
                        $share->setPermissions(Constants::PERMISSION_READ);
                        $share->setShareType(Share::SHARE_TYPE_LINK);
                        $share->setSharedBy($this->userId);
                        $share = $this->shareManager->createShare($share);
                        $token = $share->getToken();
                    }
                    $response = new DataResponse(['token' => $token]);
                }
                else {
                    $response = new DataResponse(['message' => $this->trans->t('Access denied')], 403);
                }
            }
            else {
                $response = new DataResponse(['message' => $this->trans->t('Access denied')], 403);
            }
        }
        else {
            $response = new DataResponse(['message' => $this->trans->t('Access denied')], 403);
        }
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function exportCsvSettlement(string $projectid, ?int $centeredOn = null, ?int $maxTimestamp = null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->exportCsvSettlement($projectid, $this->userId, $centeredOn, $maxTimestamp);
            if (is_array($result) && array_key_exists('path', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to export this project settlement')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function exportCsvStatistics($projectid, $tsMin=null, $tsMax=null, $paymentMode=null, $category=null,
                                        $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->exportCsvStatistics($projectid, $this->userId, $tsMin, $tsMax,
                                                                 $paymentMode, $category, $amountMin, $amountMax,
                                                                 $showDisabled, $currencyId);
            if (is_array($result) && array_key_exists('path', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to export this project statistics')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function exportCsvProject($projectid, $name=null, $uid=null) {
        $userId = $uid;
        if ($this->userId) {
            $userId = $this->userId;
        }

        if ($this->projectService->userCanAccessProject($userId, $projectid)) {
            $result = $this->projectService->exportCsvProject($projectid, $name, $userId);
            if (is_array($result) && array_key_exists('path', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message' => $this->trans->t('You are not allowed to export this project')]
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function importCsvProject($path) {
        $result = $this->projectService->importCsvProject($path, $this->userId);
        if (!is_array($result) && is_string($result)) {
            $projInfo = $this->projectService->getProjectInfo($result);
            $projInfo['myaccesslevel'] = ACCESS_ADMIN;
            return new DataResponse($projInfo);
        }
        else {
            return new DataResponse($result, 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function importSWProject($path) {
        $result = $this->projectService->importSWProject($path, $this->userId);
        if (!is_array($result) && is_string($result)) {
            $projInfo = $this->projectService->getProjectInfo($result);
            $projInfo['myaccesslevel'] = ACCESS_ADMIN;
            return new DataResponse($projInfo);
        }
        else {
            return new DataResponse($result, 400);
        }
    }

    /**
     * Used by MoneyBuster to check if weblogin is valid
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function apiPing() {
        $response = new DataResponse(
            [$this->userId]
        );
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
    public function getBillActivity(?int $since) {
        $result = $this->projectService->getBillActivity($this->userId, $since);
        if (isset($result['error'])) {
            return new DataResponse($result, 400);
        }
        else {
            return new DataResponse($result);
        }
    }
}
