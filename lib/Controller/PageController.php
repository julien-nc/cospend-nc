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
use OCP\ILogger;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\Activity\ActivityManager;

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
                                ILogger $logger,
                                BillMapper $billMapper,
                                ProjectMapper $projectMapper,
                                ProjectService $projectService,
                                ActivityManager $activityManager,
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
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== null and $UserId !== '' and $serverContainer !== null){
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
            'projectid'=>'',
            'password'=>'',
            'username'=>$this->userId,
            'cospend_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('cospend', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            //->addAllowedChildSrcDomain('*')
            ->addAllowedFrameDomain('*')
            ->addAllowedWorkerSrcDomain('*')
            //->allowInlineScript(true)
            //->allowEvalScript(true)
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
            'projectid'=>$projectid,
            'password'=>$password,
            'wrong'=>false,
            'cospend_version'=>$this->appVersion
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
            'projectid'=>$projectid,
            'wrong'=>false,
            'cospend_version'=>$this->appVersion
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
            'wrong'=>false,
            'cospend_version'=>$this->appVersion
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
    public function pubProject($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            // PARAMS to view
            $params = [
                'projectid'=>$projectid,
                'password'=>$password,
                'cospend_version'=>$this->appVersion
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
                'wrong'=>true,
                'cospend_version'=>$this->appVersion
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
        if (is_string($result) and !is_array($result)) {
            // project id
            return new DataResponse($result);
        }
        else {
            return new DataResponse($result, 400);
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAddExternalProject($id, $url, $password) {
        $result = $this->projectService->addExternalProject($url, $id, $password, $this->userId);
        if (!is_array($result) and is_string($result)) {
            // project id
            return new DataResponse($result);
        }
        else {
            return new DataResponse($result, 400);
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webDeleteProject($projectid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'd')) {
            $result = $this->projectService->deleteProject($projectid);
            if ($result === 'DELETED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to delete this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webDeleteBill($projectid, $billid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'd')) {
            if ($this->projectService->getBill($projectid, $billid) !== null) {
                $billObj = $this->billMapper->find($billid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_DELETE,
                    []
                );
            }

            $result = $this->projectService->deleteBill($projectid, $billid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to delete this bill']
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
                ['message'=>'You are not allowed to get this project\'s info']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetProjectStatistics($projectid, $dateMin=null, $dateMax=null, $paymentMode=null, $category=null,
                                            $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectStatistics(
                $projectid, 'lowername', $dateMin, $dateMax, $paymentMode,
                $category, $amountMin, $amountMax, $showDisabled, $currencyId
            );
            return new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to get this project\'s statistics']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetProjectSettlement($projectid) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectSettlement($projectid);
            return new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to get this project\'s settlement']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAutoSettlement($projectid) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->autoSettlement($projectid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to settle this project automatically']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webEditMember($projectid, $memberid, $name, $weight, $activated, $color=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editMember($projectid, $memberid, $name, $weight, $activated, $color);
            if (is_array($result) and array_key_exists('activated', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this member']
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
                                $repeatallactive=null, $repeatuntil=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result =  $this->projectService->editBill(
                $projectid, $billid, $date, $what, $payer, $payed_for,
                $amount, $repeat, $paymentmode, $categoryid,
                $repeatallactive, $repeatuntil
            );
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
                ['message'=>'You are not allowed to edit this bill']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webEditProject($projectid, $name, $contact_email, $password, $autoexport=null, $currencyname=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editProject($projectid, $name, $contact_email, $password, $autoexport, $currencyname);
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webEditExternalProject($projectid, $ncurl, $password) {
        if ($this->projectService->userCanAccessExternalProject($this->userId, $projectid, $ncurl)) {
            $result = $this->projectService->editExternalProject($projectid, $ncurl, $password);
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this external project']
                , 400
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webDeleteExternalProject($projectid, $ncurl) {
        if ($this->projectService->userCanAccessExternalProject($this->userId, $projectid, $ncurl)) {
            $result = $this->projectService->deleteExternalProject($projectid, $ncurl);
            if ($result === 'DELETED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to delete this external project']
                , 400
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
                               $repeatuntil=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'c')) {
            $result = $this->projectService->addBill(
                $projectid, $date, $what, $payer, $payed_for, $amount,
                $repeat, $paymentmode, $categoryid, $repeatallactive, $repeatuntil
            );
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
                ['message'=>'You are not allowed to add a bill to this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAddMember($projectid, $name, $weight=1, $active=1, $color=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'c')) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to add member to this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetBills($projectid, $lastchanged=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
            $response = new DataResponse($bills);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to get bills of this project']
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
            if (is_string($result) and !is_array($result)) {
                // project id
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Anonymous project creation is not allowed on this server']
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
    public function apiGetProjectInfo($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            $projectInfo = $this->projectService->getProjectInfo($projectid);
            if ($projectInfo !== null) {
                unset($projectInfo['userid']);
                return new DataResponse($projectInfo);
            }
            else {
                $response = new DataResponse(
                    ['message'=>'Project not found in the database']
                    , 404
                );
                return $response;
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
                    ['message'=>'Project not found in the database']
                    , 404
                );
                return $response;
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action.']
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
    public function apiSetProjectInfo($projectid, $passwd, $name, $contact_email, $password, $autoexport=null, $currencyname=null) {
        if ($this->checkLogin($projectid, $passwd) and $this->projectService->guestHasPermission($projectid, 'e')) {
            $result = $this->projectService->editProject($projectid, $name, $contact_email, $password, $autoexport, $currencyname);
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivSetProjectInfo($projectid, $name, $contact_email, $password, $autoexport=null, $currencyname=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editProject($projectid, $name, $contact_email, $password, $autoexport, $currencyname);
            if ($result === 'UPDATED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action.']
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
        if ($this->checkLogin($projectid, $password)) {
            $members = $this->projectService->getMembers($projectid, null, $lastchanged);
            $response = new DataResponse($members);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
                ['message'=>'Unauthorized action']
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
    public function apiGetBills($projectid, $password, $lastchanged=null) {
        if ($this->checkLogin($projectid, $password)) {
            $bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
            $response = new DataResponse($bills);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
                'bills'=>$bills,
                'allBillIds'=>$billIds,
                'timestamp'=>$ts
            ]);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
        if ($this->checkLogin($projectid, $password)) {
            $bills = $this->projectService->getBills($projectid, null, null, null, null, null, null, $lastchanged);
            $billIds = $this->projectService->getAllBillIds($projectid);
            $ts = (new \DateTime())->getTimestamp();
            $response = new DataResponse([
                'bills'=>$bills,
                'allBillIds'=>$billIds,
                'timestamp'=>$ts
            ]);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'c')) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color);
            if (is_array($result)) {
                return new DataResponse($result['id']);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivAddMember($projectid, $name, $weight, $active=1, $color=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'c')) {
            $result = $this->projectService->addMember($projectid, $name, $weight, $active, $color);
            if (is_array($result)) {
                return new DataResponse($result['id']);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
                               $repeatallactive=0, $repeatuntil=null) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'c')) {
            $result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
                                                     $repeat, $paymentmode, $categoryid, $repeatallactive,
                                                     $repeatuntil);
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
                ['message'=>'Unauthorized action']
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
                               $repeatallactive=0, $repeatuntil=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'c')) {
            $result = $this->projectService->addBill($projectid, $date, $what, $payer, $payed_for, $amount,
                                                     $repeat, $paymentmode, $categoryid, $repeatallactive,
                                                     $repeatuntil);
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
                ['message'=>'Unauthorized action']
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
    public function apiEditBill($projectid, $password, $billid, $date, $what, $payer, $payed_for,
                                $amount, $repeat='n', $paymentmode=null, $categoryid=null,
                                $repeatallactive=null, $repeatuntil=null) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
            $result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                      $amount, $repeat, $paymentmode, $categoryid,
                                                      $repeatallactive, $repeatuntil);
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
                ['message'=>'Unauthorized action']
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
                                $repeatallactive=null, $repeatuntil=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                                                      $amount, $repeat, $paymentmode, $categoryid,
                                                      $repeatallactive, $repeatuntil);
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
                ['message'=>'Unauthorized action']
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
    public function apiDeleteBill($projectid, $password, $billid) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'd')) {
            if ($this->projectService->getBill($projectid, $billid) !== null) {
                $billObj = $this->billMapper->find($billid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_DELETE,
                    []
                );
            }

            $result = $this->projectService->deleteBill($projectid, $billid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivDeleteBill($projectid, $billid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'd')) {
            if ($this->projectService->getBill($projectid, $billid) !== null) {
                $billObj = $this->billMapper->find($billid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_BILL, $billObj,
                    ActivityManager::SUBJECT_BILL_DELETE,
                    []
                );
            }

            $result = $this->projectService->deleteBill($projectid, $billid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiDeleteMember($projectid, $password, $memberid) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'd')) {
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
                ['message'=>'Unauthorized action']
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
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'd')) {
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
                ['message'=>'Unauthorized action']
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
    public function apiDeleteProject($projectid, $password) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'd')) {
            $result = $this->projectService->deleteProject($projectid);
            if ($result === 'DELETED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivDeleteProject($projectid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'd')) {
            $result = $this->projectService->deleteProject($projectid);
            if ($result === 'DELETED') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 404);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiEditMember($projectid, $password, $memberid, $name, $weight, $activated, $color=null) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
            $result = $this->projectService->editMember($projectid, $memberid, $name, $weight, $activated, $color);
            if (is_array($result) and array_key_exists('activated', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivEditMember($projectid, $memberid, $name, $weight, $activated, $color=null) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editMember($projectid, $memberid, $name, $weight, $activated, $color);
            if (is_array($result) and array_key_exists('activated', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiGetProjectStatistics($projectid, $password, $dateMin=null, $dateMax=null, $paymentMode=null,
                                            $category=null, $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->checkLogin($projectid, $password)) {
            $result = $this->projectService->getProjectStatistics(
                $projectid, 'lowername', $dateMin, $dateMax, $paymentMode,
                $category, $amountMin, $amountMax, $showDisabled, $currencyId
            );
            $response = new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivGetProjectStatistics($projectid, $dateMin=null, $dateMax=null, $paymentMode=null,
                                            $category=null, $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectStatistics(
                $projectid, 'lowername', $dateMin, $dateMax, $paymentMode,
                $category, $amountMin, $amountMax, $showDisabled, $currencyId
            );
            $response = new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiGetProjectSettlement($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            $result = $this->projectService->getProjectSettlement($projectid);
            $response = new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivGetProjectSettlement($projectid) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->getProjectSettlement($projectid);
            $response = new DataResponse($result);
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiAutoSettlement($projectid, $password) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'c')) {
            $result = $this->projectService->autoSettlement($projectid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
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
    public function apiPrivAutoSettlement($projectid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'c')) {
            $result = $this->projectService->autoSettlement($projectid);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 403);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function getUserList() {
        $userNames = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $this->userId && $u->isEnabled()) {
                $userNames[$u->getUID()] = $u->getDisplayName();
            }
        }
        $groupNames = [];
        foreach($this->groupManager->search('') as $g) {
            $groupNames[$g->getGID()] = $g->getDisplayName();
        }
        // circles
        $circleNames = [];
        $circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
        if ($circlesEnabled) {
            $cs = \OCA\Circles\Api\v1\Circles::listCircles(\OCA\Circles\Model\Circle::CIRCLES_ALL, '', 0);
            foreach ($cs as $c) {
                $circleUniqueId = $c->getUniqueId();
                $circleName = $c->getName();
                if ($c->getOwner()->getUserId() === $this->userId) {
                    $circleNames[$circleUniqueId] = $circleName;
                    continue;
                }
                $circleDetails = \OCA\Circles\Api\v1\Circles::detailsCircle($c->getUniqueId());
                foreach ($circleDetails->getMembers() as $m) {
                    if ($m->getUserId() === $this->userId) {
                        $circleNames[$circleUniqueId] = $circleName;
                        break;
                    }
                }
            }
        }
        $response = new DataResponse(
            [
                'users'=>$userNames,
                'groups'=>$groupNames,
                'circles'=>$circleNames
            ]
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
    public function editSharePermissions($projectid, $shid, $permissions) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editSharePermissions($projectid, $shid, $permissions);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editGuestPermissions($projectid, $permissions) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editGuestPermissions($projectid, $permissions);
            if ($result === 'OK') {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addCategory($projectid, $name, $icon, $color) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->addCategory($projectid, $name, $icon, $color);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
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
    public function apiAddCategory($projectid, $password, $name, $icon, $color) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
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
    public function apiPrivAddCategory($projectid, $name, $icon, $color) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editCategory($projectid, $categoryid, $name, $icon, $color) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->editCategory($projectid, $categoryid, $name, $icon, $color);
            if (is_array($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
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
    public function apiEditCategory($projectid, $password, $categoryid, $name, $icon, $color) {
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
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
    public function apiPrivEditCategory($projectid, $categoryid, $name, $icon, $color) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteCategory($projectid, $categoryid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
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
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
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
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addCurrency($projectid, $name, $rate) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
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
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
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
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function editCurrency($projectid, $currencyid, $name, $rate) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
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
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
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
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteCurrency($projectid, $currencyid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
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
        if ($this->checkLogin($projectid, $password) and $this->projectService->guestHasPermission($projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
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
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'Unauthorized action']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addUserShare($projectid, $userid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->addUserShare($projectid, $userid, $this->userId);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteUserShare($projectid, $shid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addGroupShare($projectid, $groupid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->addGroupShare($projectid, $groupid, $this->userId);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteGroupShare($projectid, $shid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addCircleShare($projectid, $circleid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
            $result = $this->projectService->addCircleShare($projectid, $circleid, $this->userId);
            if (is_numeric($result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to edit this project']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteCircleShare($projectid, $shid) {
        if ($this->projectService->userHasPermission($this->userId, $projectid, 'e')) {
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
                ['message'=>'You are not allowed to edit this project']
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
                    $response = new DataResponse(['token'=>$token]);
                }
                else {
                    $response = new DataResponse(['message'=>$this->trans->t('Access denied')], 403);
                }
            }
            else {
                $response = new DataResponse(['message'=>$this->trans->t('Access denied')], 403);
            }
        }
        else {
            $response = new DataResponse(['message'=>$this->trans->t('Access denied')], 403);
        }
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function exportCsvSettlement($projectid) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->exportCsvSettlement($projectid, $this->userId);
            if (is_array($result) and array_key_exists('path', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to export this project settlement']
                , 403
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function exportCsvStatistics($projectid, $dateMin=null, $dateMax=null, $paymentMode=null, $category=null,
                                        $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        if ($this->projectService->userCanAccessProject($this->userId, $projectid)) {
            $result = $this->projectService->exportCsvStatistics($projectid, $this->userId, $dateMin, $dateMax,
                                                                 $paymentMode, $category, $amountMin, $amountMax,
                                                                 $showDisabled, $currencyId);
            if (is_array($result) and array_key_exists('path', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to export this project statistics']
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
            if (is_array($result) and array_key_exists('path', $result)) {
                return new DataResponse($result);
            }
            else {
                return new DataResponse($result, 400);
            }
        }
        else {
            $response = new DataResponse(
                ['message'=>'You are not allowed to export this project']
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
        if (!is_array($result) and is_string($result)) {
            return new DataResponse($result);
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
        if (!is_array($result) and is_string($result)) {
            return new DataResponse($result);
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

}
