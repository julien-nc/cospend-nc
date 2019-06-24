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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\ApiController;
use OCP\Constants;
use OCP\Share;
use OCP\DB\QueryBuilder\IQueryBuilder;

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

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $groupManager, IL10N $trans, $logger){
        parent::__construct($AppName, $request,
                            'PUT, POST, GET, DELETE, PATCH, OPTIONS',
                            'Authorization, Content-Type, Accept',
                            1728000);
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('cospend', 'installed_version');
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
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
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
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
    public function pubLoginProject($projectid) {
        // PARAMS to view
        $params = [
            'projectid'=>$projectid,
            'wrong'=>false,
            'cospend_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('cospend', 'login', $params);
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
        $response = new TemplateResponse('cospend', 'login', $params);
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
            $response = new TemplateResponse('cospend', 'main', $params);
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
            $response = new TemplateResponse('cospend', 'login', $params);
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
     *
     */
    public function webCreateProject($id, $name, $password) {
        $user = $this->userManager->get($this->userId);
        $userEmail = $user->getEMailAddress();
        return $this->createProject($name, $id, $password, $userEmail, $this->userId);
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webAddExternalProject($id, $url, $password) {
        return $this->addExternalProject($url, $id, $password, $this->userId);
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webDeleteProject($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->deleteProject($projectid);
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
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->deleteBill($projectid, $billid);
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
     * check if user owns the project
     * or if the project is shared with the user
     */
    private function userCanAccessProject($userid, $projectid) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null) {
            // does the user own the project ?
            if ($projectInfo['userid'] === $userid) {
                return true;
            }
            else {
                $qb = $this->dbconnection->getQueryBuilder();
                // is the project shared with the user ?
                $qb->select('userid', 'projectid')
                    ->from('cospend_shares', 's')
                    ->where(
                        $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
                    )
                    ->andWhere(
                        $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
                    );
                $req = $qb->execute();
                $dbProjectId = null;
                while ($row = $req->fetch()) {
                    $dbProjectId = $row['projectid'];
                    break;
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();

                if ($dbProjectId !== null) {
                    return true;
                }
                else {
                    // if not, is the project shared with a group containing the user?
                    $userO = $this->userManager->get($userid);

                    $qb->select('userid')
                        ->from('cospend_shares', 's')
                        ->where(
                            $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                        )
                        ->andWhere(
                            $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                        );
                    $req = $qb->execute();
                    while ($row = $req->fetch()){
                        $groupId = $row['userid'];
                        if ($this->groupManager->groupExists($groupId) && $this->groupManager->get($groupId)->inGroup($userO)) {
                            return true;
                        }
                    }
                    $req->closeCursor();
                    $qb = $qb->resetQueryParts();

                    return false;
                }
            }
        }
        else {
            return false;
        }
    }

    /**
     * check if user owns the external project
     */
    private function userCanAccessExternalProject($userid, $projectid, $ncurl) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid')
           ->from('cospend_ext_projects', 'ep')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        $dbProjectId = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['projectid'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return ($dbProjectId !== null);
    }

    /**
     * @NoAdminRequired
     *
     */
    public function webGetProjectInfo($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            $projectInfo = $this->getProjectInfo($projectid);
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
    public function webGetProjectStatistics($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->getProjectStatistics($projectid);
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
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->getProjectSettlement($projectid);
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
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->autoSettlement($projectid);
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
    public function webEditMember($projectid, $memberid, $name, $weight, $activated) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->editMember($projectid, $memberid, $name, $weight, $activated);
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
    public function webEditBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount, $repeat) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->editBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount, $repeat);
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
    public function webEditProject($projectid, $name, $contact_email, $password) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->editProject($projectid, $name, $contact_email, $password);
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
        if ($this->userCanAccessExternalProject($this->userId, $projectid, $ncurl)) {
            return $this->editExternalProject($projectid, $ncurl, $password);
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
        if ($this->userCanAccessExternalProject($this->userId, $projectid, $ncurl)) {
            return $this->deleteExternalProject($projectid, $ncurl);
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
    public function webAddBill($projectid, $date, $what, $payer, $payed_for, $amount, $repeat) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->addBill($projectid, $date, $what, $payer, $payed_for, $amount, $repeat);
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
    public function webAddMember($projectid, $name) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            return $this->addMember($projectid, $name, 1);
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
    public function webGetBills($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            $bills = $this->getBills($projectid);
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
     *
     */
    public function webGetProjects() {
        $projects = [];
        $projectids = [];

        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('p.id', 'p.password', 'p.name', 'p.email')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('userid', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            array_push($projectids, $dbProjectId);
            $dbPassword = $row['password'];
            $dbName = $row['name'];
            $dbEmail= $row['email'];
            array_push($projects, [
                'name'=>$dbName,
                'contact_email'=>$dbEmail,
                'id'=>$dbProjectId,
                'active_members'=>null,
                'members'=>null,
                'balance'=>null,
                'shares'=>[]
            ]);
        }
        $req->closeCursor();

        $qb = $qb->resetQueryParts();

        // shared with user
        $qb->select('p.id', 'p.password', 'p.name', 'p.email')
           ->from('cospend_projects', 'p')
           ->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
           ->where(
               $qb->expr()->eq('s.userid', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('s.isgroupshare', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            // avoid putting twice the same project
            // this can happen with a share loop
            if (!in_array($dbProjectId, $projectids)) {
                $dbPassword = $row['password'];
                $dbName = $row['name'];
                $dbEmail= $row['email'];
                array_push($projects, [
                    'name'=>$dbName,
                    'contact_email'=>$dbEmail,
                    'id'=>$dbProjectId,
                    'active_members'=>null,
                    'members'=>null,
                    'balance'=>null,
                    'shares'=>[]
                ]);
                array_push($projectids, $dbProjectId);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // shared with one of the groups the user is member of
        $userO = $this->userManager->get($this->userId);

        // get group with which a project is shared
        $candidateGroupIds = [];
        $qb->select('userid')
           ->from('cospend_shares', 's')
           ->where(
               $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
           )
           ->groupBy('userid');
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $groupId = $row['userid'];
            array_push($candidateGroupIds, $groupId);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // is the user member of these groups?
        foreach ($candidateGroupIds as $candidateGroupId) {
            $group = $this->groupManager->get($candidateGroupId);
            if ($group !== null && $group->inGroup($userO)) {
                // get projects shared with this group
                $qb->select('p.id', 'p.password', 'p.name', 'p.email')
                    ->from('cospend_projects', 'p')
                    ->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
                    ->where(
                        $qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateGroupId, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('s.isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                    );
                $req = $qb->execute();

                $dbProjectId = null;
                $dbPassword = null;
                while ($row = $req->fetch()){
                    $dbProjectId = $row['id'];
                    // avoid putting twice the same project
                    // this can happen with a share loop
                    if (!in_array($dbProjectId, $projectids)) {
                        $dbPassword = $row['password'];
                        $dbName = $row['name'];
                        $dbEmail= $row['email'];
                        array_push($projects, [
                            'name'=>$dbName,
                            'contact_email'=>$dbEmail,
                            'id'=>$dbProjectId,
                            'active_members'=>null,
                            'members'=>null,
                            'balance'=>null,
                            'shares'=>[]
                        ]);
                        array_push($projectids, $dbProjectId);
                    }
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();
            }
        }

        // get values for projects we're gonna return
        for ($i = 0; $i < count($projects); $i++) {
            $dbProjectId = $projects[$i]['id'];
            $members = $this->getMembers($dbProjectId);
            $shares = $this->getUserShares($dbProjectId);
            $groupShares = $this->getGroupShares($dbProjectId);
            $activeMembers = [];
            foreach ($members as $member) {
                if ($member['activated']) {
                    array_push($activeMembers, $member);
                }
            }
            $balance = $this->getBalance($dbProjectId);
            $projects[$i]['active_members'] = $activeMembers;
            $projects[$i]['members'] = $members;
            $projects[$i]['balance'] = $balance;
            $projects[$i]['shares'] = $shares;
            $projects[$i]['group_shares'] = $groupShares;
        }

        // get external projects
        $qb->select('ep.projectid', 'ep.password', 'ep.ncurl')
           ->from('cospend_ext_projects', 'ep')
           ->where(
               $qb->expr()->eq('userid', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbProjectId = $row['projectid'];
            $dbPassword = $row['password'];
            $dbNcUrl = $row['ncurl'];
            array_push($projects, [
                'name'=>$dbProjectId.'@'.$dbNcUrl,
                'ncurl'=>$dbNcUrl,
                'id'=>$dbProjectId,
                'password'=>$dbPassword,
                'active_members'=>null,
                'members'=>null,
                'balance'=>null,
                'shares'=>[],
                'external'=>true
            ]);
        }
        $req->closeCursor();

        $response = new DataResponse($projects);
        return $response;
    }

    private function getUserShares($projectid) {
        $shares = [];

        $userIdToName = [];
        foreach($this->userManager->search('') as $u) {
            $userIdToName[$u->getUID()] = $u->getDisplayName();
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid', 'userid')
           ->from('cospend_shares', 'sh')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbuserId = $row['userid'];
            $dbprojectId = $row['projectid'];
            if (array_key_exists($dbuserId, $userIdToName)) {
                array_push($shares, ['userid'=>$dbuserId, 'name'=>$userIdToName[$dbuserId]]);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $shares;
    }

    private function getGroupShares($projectid) {
        $shares = [];

        $groupIdToName = [];
        foreach($this->groupManager->search('') as $g) {
            $groupIdToName[$g->getGID()] = $g->getDisplayName();
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid', 'userid')
           ->from('cospend_shares', 'sh')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbGroupId = $row['userid'];
            $dbprojectId = $row['projectid'];
            if (array_key_exists($dbGroupId, $groupIdToName)) {
                array_push($shares, ['groupid'=>$dbGroupId, 'name'=>$groupIdToName[$dbGroupId]]);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $shares;
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
            return $this->createProject($name, $id, $password, $contact_email);
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
            $projectInfo = $this->getProjectInfo($projectid);
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
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
                , 400
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
    public function apiSetProjectInfo($projectid, $passwd, $name, $contact_email, $password) {
        if ($this->checkLogin($projectid, $passwd)) {
            return $this->editProject($projectid, $name, $contact_email, $password);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiGetMembers($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            $members = $this->getMembers($projectid);
            $response = new DataResponse($members);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiGetBills($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            $bills = $this->getBills($projectid);
            $response = new DataResponse($bills);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiAddMember($projectid, $password, $name, $weight) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->addMember($projectid, $name, $weight);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiAddBill($projectid, $password, $date, $what, $payer, $payed_for, $amount, $repeat='n') {
        if ($this->checkLogin($projectid, $password)) {
            return $this->addBill($projectid, $date, $what, $payer, $payed_for, $amount, $repeat);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiEditBill($projectid, $password, $billid, $date, $what, $payer, $payed_for, $amount, $repeat='n') {
        if ($this->checkLogin($projectid, $password)) {
            return $this->editBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount, $repeat);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiDeleteBill($projectid, $password, $billid) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->deleteBill($projectid, $billid);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiDeleteMember($projectid, $password, $memberid) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->deleteMember($projectid, $memberid);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiDeleteProject($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->deleteProject($projectid);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiEditMember($projectid, $password, $memberid, $name, $weight, $activated) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->editMember($projectid, $memberid, $name, $weight, $activated);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiGetProjectStatistics($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->getProjectStatistics($projectid);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiGetProjectSettlement($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->getProjectSettlement($projectid);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
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
    public function apiAutoSettlement($projectid, $password) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->autoSettlement($projectid);
        }
        else {
            $response = new DataResponse(
                ['message'=>'The server could not verify that you are authorized to access the URL requested.  You either supplied the wrong credentials (e.g. a bad password), or your browser doesn\'t understand how to supply the credentials required.']
                , 401
            );
            return $response;
        }
    }

    private function getProjectStatistics($projectId, $memberOrder=null) {
        $membersWeight = [];
        $membersNbBills = [];
        $membersBalance = [];
        $membersPaid = [];
        $membersSpent = [];

        $members = $this->getMembers($projectId, $memberOrder);
        foreach ($members as $member) {
            $memberId = $member['id'];
            $memberWeight = $member['weight'];
            $membersWeight[$memberId] = $memberWeight;
            $membersNbBills[$memberId] = 0;
            $membersBalance[$memberId] = 0.0;
            $membersPaid[$memberId] = 0.0;
            $membersSpent[$memberId] = 0.0;
        }

        $bills = $this->getBills($projectId);
        foreach ($bills as $bill) {
            $payerId = $bill['payer_id'];
            $amount = $bill['amount'];
            $owers = $bill['owers'];

            $membersNbBills[$payerId]++;
            $membersBalance[$payerId] += $amount;
            $membersPaid[$payerId] += $amount;

            $nbOwerShares = 0.0;
            foreach ($owers as $ower) {
                $owerWeight = $ower['weight'];
                if ($owerWeight === 0.0) {
                    $owerWeight = 1.0;
                }
                $nbOwerShares += $owerWeight;
            }
            foreach ($owers as $ower) {
                $owerWeight = $ower['weight'];
                if ($owerWeight === 0.0) {
                    $owerWeight = 1.0;
                }
                $owerId = $ower['id'];
                $spent = $amount / $nbOwerShares * $owerWeight;
                $membersBalance[$owerId] -= $spent;
                $membersSpent[$owerId] += $spent;
            }
        }

        $statistics = [];
        foreach ($members as $member) {
            $memberId = $member['id'];
            $statistic = [
                'balance' => $membersBalance[$memberId],
                'paid' => $membersPaid[$memberId],
                'spent' => $membersSpent[$memberId],
                'member' => $member
            ];
            array_push($statistics, $statistic);
        }

        $response = new DataResponse($statistics);
        return $response;
    }

    private function addExternalProject($ncurl, $id, $password, $userid) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid')
           ->from('cospend_ext_projects', 'ep')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        $dbprojectid = null;
        while ($row = $req->fetch()){
            $dbprojectid = $row['projectid'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($dbprojectid === null) {
            // check if id is valid
            if (strpos($id, '/') !== false) {
                $response = new DataResponse(['message'=>'Invalid project id'], 400);
                return $response;
            }
            $qb->insert('cospend_ext_projects')
                ->values([
                    'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
                    'projectid' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
                    'ncurl' => $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR),
                    'password' => $qb->createNamedParameter($password, IQueryBuilder::PARAM_STR)
                ]);
            $req = $qb->execute();

            $response = new DataResponse($id);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'A project with id "'.$id.'" and url "'.$ncurl.'" already exists']
                , 400
            );
            return $response;
        }
    }

    private function createProject($name, $id, $password, $contact_email, $userid='') {
        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('id')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        $dbid = null;
        while ($row = $req->fetch()){
            $dbid = $row['id'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($dbid === null) {
            // check if id is valid
            if (strpos($id, '/') !== false) {
                $response = new DataResponse(['message'=>'Invalid project id'], 400);
                return $response;
            }
            $dbPassword = '';
            if ($password !== null && $password !== '') {
                $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            }
            if ($contact_email === null) {
                $contact_email = '';
            }
            $qb->insert('cospend_projects')
                ->values([
                    'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
                    'id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
                    'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                    'password' => $qb->createNamedParameter($dbPassword, IQueryBuilder::PARAM_STR),
                    'email' => $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR)
                ]);
            $req = $qb->execute();

            $response = new DataResponse($id);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'A project with id "'.$id.'" already exists']
                , 400
            );
            return $response;
        }
    }

    private function getProjectInfo($projectid) {
        $projectInfo = null;

        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('id', 'password', 'name', 'email', 'userid')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            $dbPassword = $row['password'];
            $dbName = $row['name'];
            $dbEmail= $row['email'];
            $dbUserId = $row['userid'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($dbProjectId !== null) {
            $members = $this->getMembers($dbProjectId);
            $activeMembers = [];
            foreach ($members as $member) {
                if ($member['activated']) {
                    array_push($activeMembers, $member);
                }
            }
            $balance = $this->getBalance($dbProjectId);
            $projectInfo = [
                'userid'=>$dbUserId,
                'name'=>$dbName,
                'contact_email'=>$dbEmail,
                'id'=>$dbProjectId,
                'active_members'=>$activeMembers,
                'members'=>$members,
                'balance'=>$balance
            ];
        }

        return $projectInfo;
    }

    private function getBill($projectId, $billId) {
        $bill = null;
        // get bill owers
        $billOwers = [];

        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('memberid', 'm.name', 'm.weight', 'm.activated')
           ->from('cospend_bill_owers', 'bo')
           ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
           ->where(
               $qb->expr()->eq('bo.billid', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbWeight = floatval($row['weight']);
            $dbName = $row['name'];
            $dbActivated = (intval($row['activated']) === 1);
            $dbOwerId= intval($row['memberid']);
            array_push(
                $billOwers,
                [
                    'id' => $dbOwerId,
                    'weight' => $dbWeight,
                    'name' => $dbName,
                    'activated' => $dbActivated
                ]
            );
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // get the bill
        $qb->select('id', 'what', 'date', 'amount', 'payerid', 'repeat')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbBillId = intval($row['id']);
            $dbAmount = floatval($row['amount']);
            $dbWhat = $row['what'];
            $dbDate = $row['date'];
            $dbRepeat = $row['repeat'];
            $dbPayerId= intval($row['payerid']);
            $bill = [
                'id' => $dbBillId,
                'amount' => $dbAmount,
                'what' => $dbWhat,
                'date' => $dbDate,
                'payer_id' => $dbPayerId,
                'owers' => $billOwers,
                'repeat' => $dbRepeat
            ];
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $bill;
    }

    private function getBills($projectId) {
        $bills = [];

        // first get all bill ids
        $billIds = [];
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            array_push($billIds, $row['id']);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // get bill owers
        $billOwersByBill = [];
        foreach ($billIds as $billId) {
            $billOwers = [];

            $qb->select('memberid', 'm.name', 'm.weight', 'm.activated')
               ->from('cospend_bill_owers', 'bo')
               ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
               ->where(
                   $qb->expr()->eq('bo.billid', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
               );
            $req = $qb->execute();
            while ($row = $req->fetch()){
                $dbWeight = floatval($row['weight']);
                $dbName = $row['name'];
                $dbActivated = (intval($row['activated']) === 1);
                $dbOwerId= intval($row['memberid']);
                array_push(
                    $billOwers,
                    [
                        'id' => $dbOwerId,
                        'weight' => $dbWeight,
                        'name' => $dbName,
                        'activated' => $dbActivated
                    ]
                );
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();
            $billOwersByBill[$billId] = $billOwers;
        }

        $qb->select('id', 'what', 'date', 'amount', 'payerid', 'repeat')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->orderBy('date', 'ASC');
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbBillId = intval($row['id']);
            $dbAmount = floatval($row['amount']);
            $dbWhat = $row['what'];
            $dbDate = $row['date'];
            $dbRepeat = $row['repeat'];
            $dbPayerId= intval($row['payerid']);
            array_push(
                $bills,
                [
                    'id' => $dbBillId,
                    'amount' => $dbAmount,
                    'what' => $dbWhat,
                    'date' => $dbDate,
                    'payer_id' => $dbPayerId,
                    'owers' => $billOwersByBill[$row['id']],
                    'repeat' => $dbRepeat
                ]
            );
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $bills;
    }

    private function getMembers($projectId, $order=null) {
        $members = [];

        $sqlOrder = 'name';
        if ($order !== null) {
            if ($order === 'lowername') {
                $sqlOrder = 'name';
            }
            else {
                $sqlOrder = $order;
            }
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'weight', 'activated')
           ->from('cospend_members', 'm')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->orderBy($sqlOrder, 'ASC');
        $req = $qb->execute();

        if ($order === 'lowername') {
            while ($row = $req->fetch()){
                $dbMemberId = intval($row['id']);
                $dbWeight = floatval($row['weight']);
                $dbName = $row['name'];
                $dbActivated= intval($row['activated']);

                // find index to make sorted insert
                $ii = 0;
                while ($ii < count($members) && strcmp(strtolower($dbName), strtolower($members[$ii]['name'])) > 0) {
                    $ii++;
                }

                array_splice(
                    $members,
                    $ii,
                    0,
                    [[
                        'activated' => ($dbActivated === 1),
                        'name' => $dbName,
                        'id' => $dbMemberId,
                        'weight' => $dbWeight
                    ]]
                );
            }
        }
        else {
            while ($row = $req->fetch()){
                $dbMemberId = intval($row['id']);
                $dbWeight = floatval($row['weight']);
                $dbName = $row['name'];
                $dbActivated= intval($row['activated']);

                array_push(
                    $members,
                    [
                        'activated' => ($dbActivated === 1),
                        'name' => $dbName,
                        'id' => $dbMemberId,
                        'weight' => $dbWeight
                    ]
                );
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $members;
    }

    private function getBalance($projectId) {
        $membersWeight = [];
        $membersBalance = [];

        $members = $this->getMembers($projectId);
        foreach ($members as $member) {
            $memberId = $member['id'];
            $memberWeight = $member['weight'];
            $membersWeight[$memberId] = $memberWeight;
            $membersBalance[$memberId] = 0.0;
        }

        $bills = $this->getBills($projectId);
        foreach ($bills as $bill) {
            $payerId = $bill['payer_id'];
            $amount = $bill['amount'];
            $owers = $bill['owers'];

            $membersBalance[$payerId] += $amount;

            $nbOwerShares = 0.0;
            foreach ($owers as $ower) {
                $owerWeight = $ower['weight'];
                if ($owerWeight === 0.0) {
                    $owerWeight = 1.0;
                }
                $nbOwerShares += $owerWeight;
            }
            foreach ($owers as $ower) {
                $owerWeight = $ower['weight'];
                if ($owerWeight === 0.0) {
                    $owerWeight = 1.0;
                }
                $owerId = $ower['id'];
                $spent = $amount / $nbOwerShares * $owerWeight;
                $membersBalance[$owerId] -= $spent;
            }
        }

        return $membersBalance;
    }

    private function getMemberByName($projectId, $name) {
        $member = null;
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'weight', 'activated')
           ->from('cospend_members', 'm')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbMemberId = intval($row['id']);
            $dbWeight = floatval($row['weight']);
            $dbName = $row['name'];
            $dbActivated= intval($row['activated']);
            $member = [
                    'activated' => ($dbActivated === 1),
                    'name' => $dbName,
                    'id' => $dbMemberId,
                    'weight' => $dbWeight
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $member;
    }

    private function getMemberById($projectId, $memberId) {
        $member = null;

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'weight', 'activated')
           ->from('cospend_members', 'm')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbMemberId = intval($row['id']);
            $dbWeight = floatval($row['weight']);
            $dbName = $row['name'];
            $dbActivated= intval($row['activated']);
            $member = [
                    'activated' => ($dbActivated === 1),
                    'name' => $dbName,
                    'id' => $dbMemberId,
                    'weight' => $dbWeight
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $member;
    }

    private function getProjectById($projectId) {
        $project = null;

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'userid', 'name', 'email', 'password')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbId = $row['id'];
            $dbPassword = $row['password'];
            $dbName = $row['name'];
            $dbUserId = $row['userid'];
            $dbEmail = $row['email'];
            $project = [
                    'id' => $dbId,
                    'name' => $dbName,
                    'userid' => $dbUserId,
                    'password' => $dbPassword,
                    'email' => $dbEmail
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $project;
    }

    private function editBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount, $repeat) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('cospend_bills');

        // first check the bill exists
        if ($this->getBill($projectid, $billid) === null) {
            $response = new DataResponse(
                ["message"=> ["There is no such bill"]]
                , 404
            );
            return $response;
        }
        // then edit the hell of it
        if ($what === null || $what === '') {
            $response = new DataResponse(
                ["what"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        //$whatSql = 'what='.$this->db_quote_escape_string($what);
        $qb->set('what', $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR));

        if ($repeat === null || $repeat === '' || strlen($repeat) !== 1) {
            $response = new DataResponse(
                ["repeat"=> ["Invalid value."]]
                , 400
            );
            return $response;
        }
        //$repeatSql = $this->dbdblquotes.'repeat'.$this->dbdblquotes.'='.$this->db_quote_escape_string($repeat).',';
        $qb->set('repeat', $qb->createNamedParameter($repeat, IQueryBuilder::PARAM_STR));

        $dateSql = '';
        if ($date !== null && $date !== '') {
            //$dateSql = 'date='.$this->db_quote_escape_string($date).',';
            $qb->set('date', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR));
        }
        $amountSql = '';
        if ($amount !== null && $amount !== '' && is_numeric($amount)) {
            //$amountSql = 'amount='.$this->db_quote_escape_string($amount).',';
            $qb->set('amount', $qb->createNamedParameter($amount, IQueryBuilder::PARAM_STR));
        }
        $payerSql = '';
        if ($payer !== null && $payer !== '' && is_numeric($payer)) {
            if ($this->getMemberById($projectid, $payer) === null) {
                $response = new DataResponse(
                    ['payer'=>["Not a valid choice"]]
                    , 400
                );
                return $response;
            }
            else {
                //$payerSql = 'payerid='.$this->db_quote_escape_string($payer).',';
                $qb->set('payerid', $qb->createNamedParameter($payer, IQueryBuilder::PARAM_INT));
            }
        }

        $owerIds = null;
        // check owers
        if ($payed_for !== null && $payed_for !== '') {
            $owerIds = explode(',', $payed_for);
            if (count($owerIds) === 0) {
                $response = new DataResponse(
                    ['payed_for'=>["Invalid value"]]
                    , 400
                );
                return $response;
            }
            else {
                foreach ($owerIds as $owerId) {
                    if (!is_numeric($owerId)) {
                        $response = new DataResponse(
                            ['payed_for'=>["Invalid value"]]
                            , 400
                        );
                        return $response;
                    }
                    if ($this->getMemberById($projectid, $owerId) === null) {
                        $response = new DataResponse(
                            ['payed_for'=>["Not a valid choice"]]
                            , 400
                        );
                        return $response;
                    }
                }
            }
        }

        // do it already !
        $qb->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
           )
           ->andWhere(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        // edit the bill owers
        if ($owerIds !== null) {
            // delete old bill owers
            $this->deleteBillOwersOfBill($billid);
            // insert bill owers
            foreach ($owerIds as $owerId) {
                $qb->insert('cospend_bill_owers')
                    ->values([
                        'billid' => $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT),
                        'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
                    ]);
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();
            }
        }

        $response = new DataResponse(intval($billid));
        return $response;
    }

    private function addBill($projectid, $date, $what, $payer, $payed_for, $amount, $repeat) {
        if ($repeat === null || $repeat === '' || strlen($repeat) !== 1) {
            $response = new DataResponse(
                ["repeat"=> ["Invalid value."]]
                , 400
            );
            return $response;
        }
        if ($date === null || $date === '') {
            $response = new DataResponse(
                ["date"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        if ($what === null || $what === '') {
            $response = new DataResponse(
                ["what"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            $response = new DataResponse(
                ["amount"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        if ($payer === null || $payer === '' || !is_numeric($payer)) {
            $response = new DataResponse(
                ["payer"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        if ($this->getMemberById($projectid, $payer) === null) {
            $response = new DataResponse(
                ['payer'=>["Not a valid choice"]]
                , 400
            );
            return $response;
        }
        // check owers
        $owerIds = explode(',', $payed_for);
        if ($payed_for === null || $payed_for === '' || count($owerIds) === 0) {
            $response = new DataResponse(
                ['payed_for'=>["Invalid value"]]
                , 400
            );
            return $response;
        }
        foreach ($owerIds as $owerId) {
            if (!is_numeric($owerId)) {
                $response = new DataResponse(
                    ['payed_for'=>["Invalid value"]]
                    , 400
                );
                return $response;
            }
            if ($this->getMemberById($projectid, $owerId) === null) {
                $response = new DataResponse(
                    ['payed_for'=>["Not a valid choice"]]
                    , 400
                );
                return $response;
            }
        }

        // do it already !
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->insert('cospend_bills')
            ->values([
                'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                'what' => $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR),
                'date' => $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR),
                'amount' => $qb->createNamedParameter($amount, IQueryBuilder::PARAM_STR),
                'payerid' => $qb->createNamedParameter($payer, IQueryBuilder::PARAM_INT),
                'repeat' => $qb->createNamedParameter($repeat, IQueryBuilder::PARAM_STR)
            ]);
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $insertedBillId = $qb->getLastInsertId();

        // insert bill owers
        foreach ($owerIds as $owerId) {
            $qb->insert('cospend_bill_owers')
                ->values([
                    'billid' => $qb->createNamedParameter($insertedBillId, IQueryBuilder::PARAM_INT),
                    'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
                ]);
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();
        }

        $response = new DataResponse($insertedBillId);
        return $response;
    }

    private function addMember($projectid, $name, $weight) {
        if ($name !== null && $name !== '') {
            if ($this->getMemberByName($projectid, $name) === null) {
                $weightToInsert = 1;
                if ($weight !== null && $weight !== '') {
                    if (is_numeric($weight) and floatval($weight) > 0.0) {
                        $weightToInsert = floatval($weight);
                    }
                    else {
                        $response = new DataResponse(
                            ["weight"=> ["Not a valid decimal value"]]
                            , 400
                        );
                        return $response;
                    }
                }

                $qb = $this->dbconnection->getQueryBuilder();
                $qb->insert('cospend_members')
                    ->values([
                        'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                        'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                        'weight' => $qb->createNamedParameter($weightToInsert, IQueryBuilder::PARAM_STR),
                        'activated' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
                    ]);
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $insertedMember = $this->getMemberByName($projectid, $name);

                $response = new DataResponse($insertedMember['id']);
                return $response;
            }
            else {
                $response = new DataResponse(
                    ['message'=>["This project already has this member"]]
                    , 400
                );
                return $response;
            }
        }
        else {
            $response = new DataResponse(
                ["name"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
    }

    private function deleteBill($projectid, $billid) {
        $billToDelete = $this->getBill($projectid, $billid);
        if ($billToDelete !== null) {
            $this->deleteBillOwersOfBill($billid);

            $qb = $this->dbconnection->getQueryBuilder();
            $qb->delete('cospend_bills')
               ->where(
                   $qb->expr()->eq('id', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
               )
               ->andWhere(
                   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
               );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            $response = new DataResponse("OK");
            return $response;
        }
        else {
            $response = new DataResponse(
                ["message" => "Not Found"]
                , 404
            );
            return $response;
        }
    }

    private function deleteMember($projectid, $memberid) {
        $memberToDelete = $this->getMemberById($projectid, $memberid);
        if ($memberToDelete !== null) {
            if ($memberToDelete['activated']) {
                $qb = $this->dbconnection->getQueryBuilder();
                $qb->update('cospend_members');
                $qb->set('activated', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
                $qb->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();
            }
            $response = new DataResponse("OK");
            return $response;
        }
        else {
            $response = new DataResponse(
                ["Not Found"]
                , 404
            );
            return $response;
        }
    }

    private function deleteBillOwersOfBill($billid) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->delete('cospend_bill_owers')
           ->where(
               $qb->expr()->eq('billid', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    private function deleteProject($projectid) {
        $projectToDelete = $this->getProjectById($projectid);
        if ($projectToDelete !== null) {
            $qb = $this->dbconnection->getQueryBuilder();

            // delete project bills
            $bills = $this->getBills($projectid);
            foreach ($bills as $bill) {
                $this->deleteBillOwersOfBill($bill['id']);
            }

            $qb->delete('cospend_bills')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete project members
            $qb->delete('cospend_members')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete shares
            $qb->delete('cospend_shares')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete project
            $qb->delete('cospend_projects')
                ->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            $response = new DataResponse("DELETED");
            return $response;
        }
        else {
            $response = new DataResponse(
                ["Not Found"]
                , 404
            );
            return $response;
        }
    }

    private function editMember($projectid, $memberid, $name, $weight, $activated) {
        if ($name !== null && $name !== '') {
            if ($this->getMemberById($projectid, $memberid) !== null) {
                $qb = $this->dbconnection->getQueryBuilder();
                $qb->update('cospend_members');
                if ($weight !== null && $weight !== '') {
                    if (is_numeric($weight) and floatval($weight) > 0.0) {
                        $newWeight = floatval($weight);
                        $qb->set('weight', $qb->createNamedParameter($newWeight, IQueryBuilder::PARAM_STR));
                    }
                    else {
                        $response = new DataResponse(
                            ["weight"=> ["Not a valid decimal value"]]
                            , 400
                        );
                        return $response;
                    }
                }
                if ($activated !== null && $activated !== '' && ($activated === 'true' || $activated === 'false')) {
                    $qb->set('activated', $qb->createNamedParameter(($activated === 'true' ? 1 : 0), IQueryBuilder::PARAM_INT));
                }

                $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
                $qb->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $editedMember = $this->getMemberById($projectid, $memberid);

                $response = new DataResponse($editedMember);
                return $response;
            }
            else {
                $response = new DataResponse(
                    ['name'=>["This project have no such member"]]
                    , 404
                );
                return $response;
            }
        }
        else {
            $response = new DataResponse(
                ["name"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
    }

    private function editExternalProject($projectid, $ncurl, $password) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('cospend_ext_projects');
        $qb->set('password', $qb->createNamedParameter($password, IQueryBuilder::PARAM_STR));
        $qb->where(
            $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
        )
        ->andWhere(
            $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
        );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $response = new DataResponse("UPDATED");
        return $response;
    }

    private function deleteExternalProject($projectid, $ncurl) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->delete('cospend_ext_projects')
            ->where(
                $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $response = new DataResponse("DELETED");
        return $response;
    }

    private function editProject($projectid, $name, $contact_email, $password) {
        if ($name === null || $name === '') {
            $response = new DataResponse(
                ["name"=> ["This field is required."]]
                , 400
            );
            return $response;
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('cospend_projects');

        $emailSql = '';
        if ($contact_email !== null && $contact_email !== '') {
            if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                $qb->set('email', $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR));
            }
            else {
                $response = new DataResponse(
                    ["contact_email"=> ["Invalid email address"]]
                    , 400
                );
                return $response;
            }
        }
        if ($password !== null && $password !== '') {
            $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            $qb->set('password', $qb->createNamedParameter($dbPassword, IQueryBuilder::PARAM_STR));
        }
        if ($this->getProjectById($projectid) !== null) {
            $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
            $qb->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            $response = new DataResponse("UPDATED");
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>["There is no such project"]]
                , 404
            );
            return $response;
        }
    }

    private function getProjectSettlement($projectId) {

        $statResp = $this->getProjectStatistics($projectId);
        $stats = $statResp->getData();

        $credits = [];
        $debts = [];
        $transactions = [];

        // Create lists of credits and debts
        $k = 0;
        foreach ($stats as $stat) {
            $memberid = $stat['member']['id'];
            $rBalance = round($stat['balance'] * 100.0) / 100.0;
            if ($rBalance > 0.0) {
                $credits[$k] = ['key'=>$k, 'memberid'=>$memberid, 'amount'=>$stat['balance']];
            }
            else if ($rBalance < 0.0) {
                $debts[$k] = ['key'=>$k, 'memberid'=>$memberid, 'amount'=>(-$stat['balance'])];
            }
            $k++;
        }

        // Try and find exact matches
        foreach ($credits as $credKey=>$credit) {
            $match = $this->exactMatch($credit['amount'], $debts);
            if ($match !== null && count($match) > 0) {
                foreach ($match as $m) {
                    array_push($transactions, ['from'=>$m['memberid'], 'to'=>$credit['memberid'], 'amount'=>$m['amount']]);
                    $debtKey = $m['key'];
                    unset($debts[$debtKey]);
                }
                unset($credits[$credKey]);
            }
        }

        // Split any remaining debts & credits
        while (count($credits) > 0 && count($debts) > 0) {
            $credKey = array_keys($credits)[0];
            $credit = array_values($credits)[0];
            $debtKey = array_keys($debts)[0];
            $debt = array_values($debts)[0];
            if ($credit['amount'] > $debt['amount']) {
                array_push($transactions,
                    [
                        'from'=>$debt['memberid'],
                        'to'=>$credit['memberid'],
                        'amount'=>$debt['amount']
                    ]
                );
                $credit['amount'] = $credit['amount'] - $debt['amount'];
                $credits[$credKey] = $credit;
                unset($debts[$debtKey]);
            }
            else {
                array_push($transactions,
                    [
                        'from'=>$debt['memberid'],
                        'to'=>$credit['memberid'],
                        'amount'=>$credit['amount']
                    ]
                );
                $debt['amount'] = $debt['amount'] - $credit['amount'];
                $debts[$debtKey] = $debt;
                unset($credits[$credKey]);
            }
        }

        $response = new DataResponse($transactions);
        return $response;
    }

    private function exactMatch($creditAmount, $debts) {
        if (count($debts) === 0) {
            return null;
        }
        $debtKey = array_keys($debts)[0];
        $debt = array_values($debts)[0];
        if ($debt['amount'] > $creditAmount) {
            return $this->exactMatch($creditAmount, array_slice($debts, 1));
        }
        else if ($debt['amount'] === $creditAmount) {
            $res = [$debt];
            return $res;
        }
        else {
            $match = $this->exactMatch($creditAmount - $debt['amount'], array_slice($debts, 1));
            if ($match !== null && count($match) > 0) {
                array_push($match, $debt);
            }
            else {
                $match = $this->exactMatch($creditAmount, array_slice($debts, 1));
            }
            return $match;
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
        $response = new DataResponse(
            [
                'users'=>$userNames,
                'groups'=>$groupNames
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
    public function addUserShare($projectid, $userid) {
        // check if userId exists
        $userIds = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $this->userId) {
                array_push($userIds, $u->getUID());
            }
        }
        if ($userid !== '' and in_array($userid, $userIds)) {
            if ($this->userCanAccessProject($this->userId, $projectid)) {
                $qb = $this->dbconnection->getQueryBuilder();
                $projectInfo = $this->getProjectInfo($projectid);
                // check if someone tries to share the project with its owner
                if ($userid !== $projectInfo['userid']) {
                    // check if user share exists
                    $qb->select('userid', 'projectid')
                        ->from('cospend_shares', 's')
                        ->where(
                            $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
                        )
                        ->andWhere(
                            $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                        )
                        ->andWhere(
                            $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
                        );
                    $req = $qb->execute();
                    $dbuserId = null;
                    while ($row = $req->fetch()){
                        $dbuserId = $row['userid'];
                        break;
                    }
                    $req->closeCursor();
                    $qb = $qb->resetQueryParts();

                    if ($dbuserId === null) {
                        $qb->insert('cospend_shares')
                            ->values([
                                'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                                'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
                                'isgroupshare' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)
                            ]);
                        $req = $qb->execute();
                        $qb = $qb->resetQueryParts();

                        $response = new DataResponse('OK');

                        // SEND NOTIFICATION
                        $manager = \OC::$server->getNotificationManager();
                        $notification = $manager->createNotification();

                        $acceptAction = $notification->createAction();
                        $acceptAction->setLabel('accept')
                            ->setLink('/apps/cospend', 'GET');

                        $declineAction = $notification->createAction();
                        $declineAction->setLabel('decline')
                            ->setLink('/apps/cospend', 'GET');

                        $notification->setApp('cospend')
                            ->setUser($userid)
                            ->setDateTime(new \DateTime())
                            ->setObject('addusershare', $projectid)
                            ->setSubject('add_user_share', [$this->userId, $projectInfo['name']])
                            ->addAction($acceptAction)
                            ->addAction($declineAction)
                            ;

                        $manager->notify($notification);
                    }
                    else {
                        $response = new DataResponse(['message'=>'Already shared with this user'], 400);
                    }
                }
                else {
                    $response = new DataResponse(['message'=>'Impossible to share the project with its owner'], 400);
                }
            }
            else {
                $response = new DataResponse(['message'=>'Access denied'], 400);
            }
        }
        else {
            $response = new DataResponse(['message'=>'No such user'], 400);
        }

        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteUserShare($projectid, $userid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            // check if user share exists
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->select('userid', 'projectid')
                ->from('cospend_shares', 's')
                ->where(
                    $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                )
                ->andWhere(
                    $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $dbuserId = null;
            while ($row = $req->fetch()){
                $dbuserId = $row['userid'];
                break;
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();

            if ($dbuserId !== null) {
                // delete
                $qb->delete('cospend_shares')
                    ->where(
                        $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
                    );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $response = new DataResponse('OK');

                // SEND NOTIFICATION
                $projectInfo = $this->getProjectInfo($projectid);

                $manager = \OC::$server->getNotificationManager();
                $notification = $manager->createNotification();

                $acceptAction = $notification->createAction();
                $acceptAction->setLabel('accept')
                    ->setLink('/apps/cospend', 'GET');

                $declineAction = $notification->createAction();
                $declineAction->setLabel('decline')
                    ->setLink('/apps/cospend', 'GET');

                $notification->setApp('cospend')
                    ->setUser($userid)
                    ->setDateTime(new \DateTime())
                    ->setObject('deleteusershare', $projectid)
                    ->setSubject('delete_user_share', [$this->userId, $projectInfo['name']])
                    ->addAction($acceptAction)
                    ->addAction($declineAction)
                    ;

                $manager->notify($notification);
            }
            else {
                $response = new DataResponse(['message'=>'No such share'], 401);
            }
        }
        else {
            $response = new DataResponse(['message'=>'Access denied'], 403);
        }

        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function addGroupShare($projectid, $groupid) {
        // check if groupId exists
        $groupIds = [];
        foreach($this->groupManager->search('') as $g) {
            array_push($groupIds, $g->getGID());
        }
        if ($groupid !== '' and in_array($groupid, $groupIds)) {
            if ($this->userCanAccessProject($this->userId, $projectid)) {
                $qb = $this->dbconnection->getQueryBuilder();
                $projectInfo = $this->getProjectInfo($projectid);
                // check if user share exists
                $qb->select('userid', 'projectid')
                    ->from('cospend_shares', 's')
                    ->where(
                        $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                    )
                    ->andWhere(
                        $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('userid', $qb->createNamedParameter($groupid, IQueryBuilder::PARAM_STR))
                    );
                $req = $qb->execute();
                $dbGroupId = null;
                while ($row = $req->fetch()){
                    $dbGroupId = $row['userid'];
                    break;
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();

                if ($dbGroupId === null) {
                    $qb->insert('cospend_shares')
                        ->values([
                            'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                            'userid' => $qb->createNamedParameter($groupid, IQueryBuilder::PARAM_STR),
                            'isgroupshare' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
                        ]);
                    $req = $qb->execute();
                    $qb = $qb->resetQueryParts();

                    $response = new DataResponse('OK');

                    //// SEND NOTIFICATION
                    //$manager = \OC::$server->getNotificationManager();
                    //$notification = $manager->createNotification();

                    //$acceptAction = $notification->createAction();
                    //$acceptAction->setLabel('accept')
                    //    ->setLink('/apps/cospend', 'GET');

                    //$declineAction = $notification->createAction();
                    //$declineAction->setLabel('decline')
                    //    ->setLink('/apps/cospend', 'GET');

                    //$notification->setApp('cospend')
                    //    ->setUser($userid)
                    //    ->setDateTime(new \DateTime())
                    //    ->setObject('addusershare', $projectid)
                    //    ->setSubject('add_user_share', [$this->userId, $projectInfo['name']])
                    //    ->addAction($acceptAction)
                    //    ->addAction($declineAction)
                    //    ;

                    //$manager->notify($notification);
                }
                else {
                    $response = new DataResponse(['message'=>'Already shared with this group'], 400);
                }
            }
            else {
                $response = new DataResponse(['message'=>'Access denied'], 400);
            }
        }
        else {
            $response = new DataResponse(['message'=>'No such group'], 400);
        }

        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteGroupShare($projectid, $groupid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            // check if group share exists
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->select('userid', 'projectid')
                ->from('cospend_shares', 's')
                ->where(
                    $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                )
                ->andWhere(
                    $qb->expr()->eq('userid', $qb->createNamedParameter($groupid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $dbGroupId = null;
            while ($row = $req->fetch()){
                $dbGroupId = $row['userid'];
                break;
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();

            if ($dbGroupId !== null) {
                // delete
                $qb->delete('cospend_shares')
                    ->where(
                        $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('userid', $qb->createNamedParameter($groupid, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('isgroupshare', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                    );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $response = new DataResponse('OK');

                //// SEND NOTIFICATION
                //$projectInfo = $this->getProjectInfo($projectid);

                //$manager = \OC::$server->getNotificationManager();
                //$notification = $manager->createNotification();

                //$acceptAction = $notification->createAction();
                //$acceptAction->setLabel('accept')
                //    ->setLink('/apps/cospend', 'GET');

                //$declineAction = $notification->createAction();
                //$declineAction->setLabel('decline')
                //    ->setLink('/apps/cospend', 'GET');

                //$notification->setApp('cospend')
                //    ->setUser($userid)
                //    ->setDateTime(new \DateTime())
                //    ->setObject('deleteusershare', $projectid)
                //    ->setSubject('delete_user_share', [$this->userId, $projectInfo['name']])
                //    ->addAction($acceptAction)
                //    ->addAction($declineAction)
                //    ;

                //$manager->notify($notification);
            }
            else {
                $response = new DataResponse(['message'=>'No such share'], 401);
            }
        }
        else {
            $response = new DataResponse(['message'=>'Access denied'], 403);
        }

        return $response;
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
                    $response = new DataResponse(['message'=>'Access denied'], 403);
                }
            }
            else {
                $response = new DataResponse(['message'=>'Access denied'], 403);
            }
        }
        else {
            $response = new DataResponse(['message'=>'Access denied'], 403);
        }
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function exportCsvSettlement($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            // create Cospend directory if needed
            $userFolder = \OC::$server->getUserFolder();
            if (!$userFolder->nodeExists('/Cospend')) {
                $userFolder->newFolder('Cospend');
            }
            if ($userFolder->nodeExists('/Cospend')) {
                $folder = $userFolder->get('/Cospend');
                if ($folder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                    $response = new DataResponse(['message'=>'/Cospend is not a folder'], 400);
                    return $response;
                }
                else if (!$folder->isCreatable()) {
                    $response = new DataResponse(['message'=>'/Cospend is not writeable'], 400);
                    return $response;
                }
            }
            else {
                $response = new DataResponse(['message'=>'Impossible to create /Cospend'], 400);
                return $response;
            }

            // create file
            if ($folder->nodeExists($projectid.'-settlement.csv')) {
                $folder->get($projectid.'-settlement.csv')->delete();
            }
            $file = $folder->newFile($projectid.'-settlement.csv');
            $handler = $file->fopen('w');
            fwrite($handler, $this->trans->t('Who pays?').','. $this->trans->t('To whom?').','. $this->trans->t('How much?')."\n");
            $settleResp = $this->getProjectSettlement($projectid);
            if ($settleResp->getStatus() !== 200) {
            }
            $transactions = $settleResp->getData();

            $members = $this->getMembers($projectid);
            $memberIdToName = [];
            foreach ($members as $member) {
                $memberIdToName[$member['id']] = $member['name'];
            }

            foreach ($transactions as $transaction) {
                fwrite($handler, '"'.$memberIdToName[$transaction['from']].'","'.$memberIdToName[$transaction['to']].'",'.floatval($transaction['amount'])."\n");
            }

            fclose($handler);
            $file->touch();
            $response = new DataResponse(['path'=>'/Cospend/'.$projectid.'-settlement.csv']);
            return $response;
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
    public function exportCsvStatistics($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            // create Cospend directory if needed
            $userFolder = \OC::$server->getUserFolder();
            if (!$userFolder->nodeExists('/Cospend')) {
                $userFolder->newFolder('Cospend');
            }
            if ($userFolder->nodeExists('/Cospend')) {
                $folder = $userFolder->get('/Cospend');
                if ($folder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                    $response = new DataResponse(['message'=>'/Cospend is not a folder'], 400);
                    return $response;
                }
                else if (!$folder->isCreatable()) {
                    $response = new DataResponse(['message'=>'/Cospend is not writeable'], 400);
                    return $response;
                }
            }
            else {
                $response = new DataResponse(['message'=>'Impossible to create /Cospend'], 400);
                return $response;
            }

            // create file
            if ($folder->nodeExists($projectid.'-stats.csv')) {
                $folder->get($projectid.'-stats.csv')->delete();
            }
            $file = $folder->newFile($projectid.'-stats.csv');
            $handler = $file->fopen('w');
            fwrite($handler, $this->trans->t('Member name').','. $this->trans->t('Paid').','. $this->trans->t('Spent').','. $this->trans->t('Balance')."\n");
            $statsResp = $this->getProjectStatistics($projectid);
            if ($statsResp->getStatus() !== 200) {
            }
            $stats = $statsResp->getData();

            foreach ($stats as $stat) {
                fwrite($handler, '"'.$stat['member']['name'].'",'.floatval($stat['paid']).','.floatval($stat['spent']).','.floatval($stat['balance'])."\n");
            }

            fclose($handler);
            $file->touch();
            $response = new DataResponse(['path'=>'/Cospend/'.$projectid.'-stats.csv']);
            return $response;
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
    public function exportCsvProject($projectid) {
        if ($this->userCanAccessProject($this->userId, $projectid)) {
            // create Cospend directory if needed
            $userFolder = \OC::$server->getUserFolder();
            if (!$userFolder->nodeExists('/Cospend')) {
                $userFolder->newFolder('Cospend');
            }
            if ($userFolder->nodeExists('/Cospend')) {
                $folder = $userFolder->get('/Cospend');
                if ($folder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                    $response = new DataResponse(['message'=>'/Cospend is not a folder'], 400);
                    return $response;
                }
                else if (!$folder->isCreatable()) {
                    $response = new DataResponse(['message'=>'/Cospend is not writeable'], 400);
                    return $response;
                }
            }
            else {
                $response = new DataResponse(['message'=>'Impossible to create /Cospend'], 400);
                return $response;
            }

            // create file
            if ($folder->nodeExists($projectid.'.csv')) {
                $folder->get($projectid.'.csv')->delete();
            }
            $file = $folder->newFile($projectid.'.csv');
            $handler = $file->fopen('w');
            fwrite($handler, "what,amount,date,payer_name,payer_weight,owers\n");
            $members = $this->getMembers($projectid);
            $memberIdToName = [];
            $memberIdToWeight = [];
            foreach ($members as $member) {
                $memberIdToName[$member['id']] = $member['name'];
                $memberIdToWeight[$member['id']] = $member['weight'];
                fwrite($handler, 'deleteMeIfYouWant,1,1970-01-01,"'.$member['name'].'",'.floatval($member['weight']).',"'.$member['name'].'"'."\n");;
            }
            $bills = $this->getBills($projectid);
            foreach ($bills as $bill) {
                $owerNames = [];
                foreach ($bill['owers'] as $ower) {
                    array_push($owerNames, $ower['name']);
                }
                $owersTxt = implode(', ', $owerNames);

                $payer_id = $bill['payer_id'];
                $payer_name = $memberIdToName[$payer_id];
                $payer_weight = $memberIdToWeight[$payer_id];
                fwrite($handler, '"'.$bill['what'].'",'.floatval($bill['amount']).','.$bill['date'].',"'.$payer_name.'",'.floatval($payer_weight).',"'.$owersTxt.'"'."\n");
            }

            fclose($handler);
            $file->touch();
            $response = new DataResponse(['path'=>'/Cospend/'.$projectid.'.csv']);
            return $response;
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
        $cleanPath = str_replace(array('../', '..\\'), '',  $path);
        $userFolder = \OC::$server->getUserFolder();
        if ($userFolder->nodeExists($cleanPath)) {
            $file = $userFolder->get($cleanPath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                if (($handle = $file->fopen('r')) !== false) {
                    $columns = [];
                    $membersWeight = [];
                    $bills = [];
                    $row = 0;
                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        // first line : get column order
                        if ($row === 0) {
                            $nbCol = count($data);
                            if ($nbCol !== 6) {
                                fclose($handle);
                                $response = new DataResponse(['message'=>'Malformed CSV, bad column number'], 400);
                                return $response;
                            }
                            else {
                                for ($c=0; $c < $nbCol; $c++) {
                                    $columns[$data[$c]] = $c;
                                }
                            }
                            if (!array_key_exists('what', $columns) or
                                !array_key_exists('amount', $columns) or
                                !array_key_exists('date', $columns) or
                                !array_key_exists('payer_name', $columns) or
                                !array_key_exists('payer_weight', $columns) or
                                !array_key_exists('owers', $columns)
                            ) {
                                fclose($handle);
                                $response = new DataResponse(['message'=>'Malformed CSV, bad column names'], 400);
                                return $response;
                            }
                        }
                        // normal line : bill
                        else {
                            $what = $data[$columns['what']];
                            $amount = $data[$columns['amount']];
                            $date = $data[$columns['date']];
                            $payer_name = $data[$columns['payer_name']];
                            $payer_weight = $data[$columns['payer_weight']];
                            $owers = $data[$columns['owers']];

                            // manage members
                            if (is_numeric($payer_weight)) {
                                $membersWeight[$payer_name] = floatval($payer_weight);
                            }
                            else {
                                fclose($handle);
                                $response = new DataResponse(['message'=>'Malformed CSV, bad payer weight on line '.$row], 400);
                                return $response;
                            }
                            if (strlen($owers) === 0) {
                                fclose($handle);
                                $response = new DataResponse(['message'=>'Malformed CSV, bad owers on line '.$row], 400);
                                return $response;
                            }
                            $owersArray = explode(', ', $owers);
                            foreach ($owersArray as $ower) {
                                if (strlen($ower) === 0) {
                                    fclose($handle);
                                    $response = new DataResponse(['message'=>'Malformed CSV, bad owers on line '.$row], 400);
                                    return $response;
                                }
                                if (!array_key_exists($ower, $membersWeight)) {
                                    $membersWeight[$ower] = 1.0;
                                }
                            }
                            if (!is_numeric($amount)) {
                                fclose($handle);
                                $response = new DataResponse(['message'=>'Malformed CSV, bad amount on line '.$row], 400);
                                return $response;
                            }
                            array_push($bills,
                                [
                                    'what'=>$what,
                                    'date'=>$date,
                                    'amount'=>$amount,
                                    'payer_name'=>$payer_name,
                                    'owers'=>$owersArray,
                                ]
                            );
                        }
                        $row++;
                    }
                    fclose($handle);

                    $memberNameToId = [];

                    // add project
                    $user = $this->userManager->get($this->userId);
                    $userEmail = $user->getEMailAddress();
                    $projectid = str_replace('.csv', '', $file->getName());
                    $projectName = $projectid;
                    $projResult = $this->createProject($projectName, $projectid, '', $userEmail, $this->userId);
                    if ($projResult->getStatus() !== 200) {
                        $response = new DataResponse('Error in project creation '.$projResult->getData()['message'], 400);
                        return $response;
                    }
                    // add members
                    foreach ($membersWeight as $memberName => $weight) {
                        $addMemberResult =  $this->addMember($projectid, $memberName, $weight);
                        if ($addMemberResult->getStatus() !== 200) {
                            $this->deleteProject($projectid);
                            $response = new DataResponse(['message'=>'Error when adding member '.$memberName], 400);
                            return $response;
                        }
                        $data = $addMemberResult->getData();
                        $memberId = $data;

                        $memberNameToId[$memberName] = $memberId;
                    }
                    // add bills
                    foreach ($bills as $bill) {
                        $payerId = $memberNameToId[$bill['payer_name']];
                        $owerIds = [];
                        foreach ($bill['owers'] as $owerName) {
                            array_push($owerIds, $memberNameToId[$owerName]);
                        }
                        $owerIdsStr = implode(',', $owerIds);
                        $addBillResult = $this->addBill($projectid, $bill['date'], $bill['what'], $payerId, $owerIdsStr, $bill['amount'], 'n');
                        if ($addBillResult->getStatus() !== 200) {
                            $this->deleteProject($projectid);
                            $response = new DataResponse(['message'=>'Error when adding bill '.$bill['what']], 400);
                            return $response;
                        }
                    }
                    $response = new DataResponse($projectid);
                }
                else {
                    $response = new DataResponse(['message'=>'Access denied'], 403);
                }
            }
            else {
                $response = new DataResponse(['message'=>'Access denied'], 403);
            }
        }
        else {
            $response = new DataResponse(['message'=>'Access denied'], 403);
        }

        return $response;
    }

    private function autoSettlement($projectid) {
        $settleResp = $this->getProjectSettlement($projectid);
        if ($settleResp->getStatus() !== 200) {
            $response = new DataResponse(['message'=>'Error when getting project settlement transactions'], 403);
            return $response;
        }
        $transactions = $settleResp->getData();

        $members = $this->getMembers($projectid);
        $memberIdToName = [];
        foreach ($members as $member) {
            $memberIdToName[$member['id']] = $member['name'];
        }

        $now = new \DateTime();
        $date = $now->format('Y-m-d');

        foreach ($transactions as $transaction) {
            $fromId = $transaction['from'];
            $toId = $transaction['to'];
            $amount = floatval($transaction['amount']);
            $billTitle = $memberIdToName[$fromId].' → '.$memberIdToName[$toId];
            $addBillResult = $this->addBill($projectid, $date, $billTitle, $fromId, $toId, $amount, 'n');
            if ($addBillResult->getStatus() !== 200) {
                $response = new DataResponse(['message'=>'Error when addind a bill'], 400);
                return $response;
            }
        }
        $response = new DataResponse('OK');
        return $response;
    }

    /**
     * daily check of repeated bills
     */
    public function cronRepeatBills() {
        $now = new \DateTime();
        // get bills whith repetition flag
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'projectid', 'what', 'date', 'amount', 'payerid', 'repeat')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->neq('repeat', $qb->createNamedParameter('n', IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        $bills = [];
        while ($row = $req->fetch()){
            $id = $row['id'];
            $what = $row['what'];
            $repeat = $row['repeat'];
            $date = $row['date'];
            $projectid = $row['projectid'];
            array_push($bills, [
                'id' => $id,
                'what' => $what,
                'repeat' => $repeat,
                'date' => $date,
                'projectid' => $projectid
            ]);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        foreach ($bills as $bill) {
            $billDate = new \Datetime($bill['date']);
            // does the bill need to be repeated now ?

            // daily repeat : at least one day of difference
            if ($bill['repeat'] === 'd' &&
                $now->diff($billDate)->days >= 1
            ) {
                $this->repeatBill($bill['projectid'], $bill['id'], $now);
            }
            // weekly repeat : exactly 7 days of difference
            else if ($bill['repeat'] === 'w' &&
                $now->diff($billDate)->days === 7
            ) {
                $this->repeatBill($bill['projectid'], $bill['id'], $now);
            }
            // monthly repeat : more then 27 days of difference, same day of month
            else if ($bill['repeat'] === 'm' &&
                $now->diff($billDate)->days > 27 &&
                $now->format('d') === $billDate->format('d')
            ) {
                $this->repeatBill($bill['projectid'], $bill['id'], $now);
            }
            // yearly repeat : more than 350 days of difference, same month, same day of month
            else if ($bill['repeat'] === 'y' &&
                $now->diff($billDate)->days > 350 &&
                $now->format('d') === $billDate->format('d') &&
                $now->format('m') === $billDate->format('m')
            ) {
                $this->repeatBill($bill['projectid'], $bill['id'], $now);
            }
        }
    }

    /**
     * duplicate the bill today and give it the repeat flag
     * remove the repeat flag on original bill
     */
    private function repeatBill($projectid, $billid, $datetime) {
        $bill = $this->getBill($projectid, $billid);

        $owerIds = [];
        foreach ($bill['owers'] as $ower) {
            array_push($owerIds, $ower['id']);
        }
        $owerIdsStr = implode(',', $owerIds);

        $this->addBill($projectid, $datetime->format('Y-m-d'), $bill['what'], $bill['payer_id'], $owerIdsStr, $bill['amount'], $bill['repeat']);

        // now we can remove repeat flag on original bill
        $this->editBill($projectid, $billid, $bill['date'], $bill['what'], $bill['payer_id'], null, $bill['amount'], 'n');
    }

}
