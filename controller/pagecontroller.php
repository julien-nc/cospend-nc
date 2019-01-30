<?php
/**
 * Nextcloud - spend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Spend\Controller;

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class PageController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $shareManager;
    private $userManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $defaultDeviceId;
    private $logger;
    protected $appName;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $logger){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('spend', 'installed_version');
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;

        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
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
            'username'=>$this->userId,
            'spend_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('spend', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
          //->addAllowedChildSrcDomain("'self'")
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
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
    public function webDeleteProject($projectid) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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
     * @NoAdminRequired
     *
     */
    public function webGetProjectInfo($projectid) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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
    public function webEditMember($projectid, $memberid, $name, $weight, $activated) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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
    public function webEditBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
            return $this->editBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount);
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
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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
    public function webAddBill($projectid, $date, $what, $payer, $payed_for, $amount) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
            return $this->addBill($projectid, $date, $what, $payer, $payed_for, $amount);
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
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null && $projectInfo['userid'] === $this->userId) {
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

        $sql = '
            SELECT id, password, name, email
            FROM *PREFIX*spend_projects
            WHERE userid='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            $dbPassword = $row['password'];
            $dbName = $row['name'];
            $dbEmail= $row['email'];
            array_push($projects, [
                'name'=>$dbName,
                'contact_email'=>$dbEmail,
                'id'=>$dbProjectId,
                'active_members'=>null,
                'members'=>null,
                'balance'=>null
            ]);
        }
        $req->closeCursor();
        for ($i = 0; $i < count($projects); $i++) {
            $dbProjectId = $projects[$i]['id'];
            $members = $this->getMembers($dbProjectId);
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
        }

        $response = new DataResponse($projects);
        return $response;
    }

    private function checkLogin($projectId, $password) {
        if ($projectId === '' || $projectId === null ||
            $password === '' || $password === null
        ) {
            return false;
        }
        else {
            $sql = '
                SELECT id, password
                FROM *PREFIX*spend_projects
                WHERE id='.$this->db_quote_escape_string($projectId).' ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $dbid = null;
            $dbPassword = null;
            while ($row = $req->fetch()){
                $dbid = $row['id'];
                $dbPassword = $row['password'];
                break;
            }
            $req->closeCursor();
            return ($dbPassword !== null && password_verify($password, $dbPassword));
        }
    }

    /**
     * curl -X POST https://ihatemoney.org/api/projects \
     *   -d 'name=yay&id=yay&password=yay&contact_email=yay@notmyidea.org'
     *   "yay"
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function apiCreateProject($name, $id, $password, $contact_email) {
        $allow = intval($this->config->getAppValue('spend', 'allowAnonymousCreation'));
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
                , 401
            );
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
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
     */
    public function apiAddBill($projectid, $password, $date, $what, $payer, $payed_for, $amount) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->addBill($projectid, $date, $what, $payer, $payed_for, $amount);
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
     */
    public function apiEditBill($projectid, $password, $billid, $date, $what, $payer, $payed_for, $amount) {
        if ($this->checkLogin($projectid, $password)) {
            return $this->editBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount);
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
     */
    public function apiGetProjectStatistics($projectid, $password, $memberid) {
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

    private function getProjectStatistics($projectId) {
        $membersWeight = [];
        $membersNbBills = [];
        $membersBalance = [];
        $membersPaid = [];
        $membersSpent = [];

        $members = $this->getMembers($projectId);
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
                $nbOwerShares += $ower['weight'];
            }
            foreach ($owers as $ower) {
                $owerWeight = $ower['weight'];
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

    private function createProject($name, $id, $password, $contact_email, $userid='') {
        $sql = '
            SELECT id
            FROM *PREFIX*spend_projects
            WHERE id='.$this->db_quote_escape_string($id).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        $dbid = null;
        while ($row = $req->fetch()){
            $dbid = $row['id'];
            break;
        }
        $req->closeCursor();
        if ($dbid === null) {
            $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = '
                INSERT INTO *PREFIX*spend_projects
                (userid, id, name, password, email)
                VALUES ('.
                    $this->db_quote_escape_string($userid).','.
                    $this->db_quote_escape_string($id).','.
                    $this->db_quote_escape_string($name).','.
                    $this->db_quote_escape_string($dbPassword).','.
                    $this->db_quote_escape_string($contact_email).
                ') ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();

            $response = new DataResponse($id);
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'A project with id "'.$id.'" already exists']
                , 403
            );
            return $response;
        }
    }

    private function getProjectInfo($projectid) {
        $projectInfo = null;

        $sql = '
            SELECT id, password, name, email, userid
            FROM *PREFIX*spend_projects
            WHERE id='.$this->db_quote_escape_string($projectid).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
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

        $sql = '
            SELECT memberid,
                *PREFIX*spend_members.name as name,
                *PREFIX*spend_members.weight as weight,
                *PREFIX*spend_members.activated as activated
            FROM *PREFIX*spend_bill_owers
            INNER JOIN *PREFIX*spend_members ON memberid=*PREFIX*spend_members.id
            WHERE *PREFIX*spend_bill_owers.billid='.$this->db_quote_escape_string($billId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
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

        // get the bill
        $sql = '
            SELECT id, what, date, amount, payerid
            FROM *PREFIX*spend_bills
            WHERE projectid='.$this->db_quote_escape_string($projectId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        while ($row = $req->fetch()){
            $dbBillId = intval($row['id']);
            $dbAmount = floatval($row['amount']);
            $dbWhat = $row['what'];
            $dbDate = $row['date'];
            $dbPayerId= intval($row['payerid']);
            $bill = [
                'id' => $dbBillId,
                'amount' => $dbAmount,
                'what' => $dbWhat,
                'date' => $dbDate,
                'payer_id' => $dbPayerId,
                'owers' => $billOwers
            ];
        }
        $req->closeCursor();

        return $bill;
    }

    private function getBills($projectId) {
        $bills = [];

        // first get all bill ids
        $billIds = [];
        $sql = '
            SELECT id
            FROM *PREFIX*spend_bills
            WHERE projectid='.$this->db_quote_escape_string($projectId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        while ($row = $req->fetch()){
            array_push($billIds, $row['id']);
        }
        $req->closeCursor();

        // get bill owers
        $billOwersByBill = [];
        foreach ($billIds as $billId) {
            $billOwers = [];

            $sql = '
                SELECT memberid,
                    *PREFIX*spend_members.name as name,
                    *PREFIX*spend_members.weight as weight,
                    *PREFIX*spend_members.activated as activated
                FROM *PREFIX*spend_bill_owers
                INNER JOIN *PREFIX*spend_members ON memberid=*PREFIX*spend_members.id
                WHERE *PREFIX*spend_bill_owers.billid='.$this->db_quote_escape_string($billId).' ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
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
            $billOwersByBill[$billId] = $billOwers;
        }

        $sql = '
            SELECT id, what, date, amount, payerid
            FROM *PREFIX*spend_bills
            WHERE projectid='.$this->db_quote_escape_string($projectId).' ORDER BY date ASC;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        while ($row = $req->fetch()){
            $dbBillId = intval($row['id']);
            $dbAmount = floatval($row['amount']);
            $dbWhat = $row['what'];
            $dbDate = $row['date'];
            $dbPayerId= intval($row['payerid']);
            array_push(
                $bills,
                [
                    'id' => $dbBillId,
                    'amount' => $dbAmount,
                    'what' => $dbWhat,
                    'date' => $dbDate,
                    'payer_id' => $dbPayerId,
                    'owers' => $billOwersByBill[$row['id']]
                ]
            );
        }
        $req->closeCursor();

        return $bills;
    }

    private function getMembers($projectId) {
        $members = [];
        $sql = '
            SELECT id, name, weight, activated
            FROM *PREFIX*spend_members
            WHERE projectid='.$this->db_quote_escape_string($projectId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
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
        $req->closeCursor();
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
                $nbOwerShares += $ower['weight'];
            }
            foreach ($owers as $ower) {
                $owerWeight = $ower['weight'];
                $owerId = $ower['id'];
                $spent = $amount / $nbOwerShares * $owerWeight;
                $membersBalance[$owerId] -= $spent;
            }
        }

        return $membersBalance;
    }

    private function getMemberByName($projectId, $name) {
        $member = null;
        $sql = '
            SELECT id, name, weight, activated
            FROM *PREFIX*spend_members
            WHERE projectid='.$this->db_quote_escape_string($projectId).'
                AND name='.$this->db_quote_escape_string($name).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
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
        return $member;
    }

    private function getMemberById($projectId, $memberId) {
        $member = null;
        $sql = '
            SELECT id, name, weight, activated
            FROM *PREFIX*spend_members
            WHERE projectid='.$this->db_quote_escape_string($projectId).'
                AND id='.$this->db_quote_escape_string($memberId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
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
        return $member;
    }

    private function getProjectById($projectId) {
        $project = null;
        $sql = '
            SELECT id, userid, name, email, password
            FROM *PREFIX*spend_projects
            WHERE id='.$this->db_quote_escape_string($projectId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
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
        return $project;
    }

    private function editBill($projectid, $billid, $date, $what, $payer, $payed_for, $amount) {
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
        $whatSql = 'what='.$this->db_quote_escape_string($what);

        $dateSql = '';
        if ($date !== null && $date !== '') {
            $dateSql = 'date='.$this->db_quote_escape_string($date).',';
        }
        $amountSql = '';
        if ($amount !== null && $amount !== '' && is_numeric($amount)) {
            $amountSql = 'amount='.$this->db_quote_escape_string($amount).',';
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
                $payerSql = 'payerid='.$this->db_quote_escape_string($payer).',';
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
        $sqlupd = '
                UPDATE *PREFIX*spend_bills
                SET
                     '.$dateSql.'
                     '.$amountSql.'
                     '.$payerSql.'
                     '.$whatSql.'
                WHERE id='.$this->db_quote_escape_string($billid).'
                      AND projectid='.$this->db_quote_escape_string($projectid).' ;';
        $req = $this->dbconnection->prepare($sqlupd);
        $req->execute();
        $req->closeCursor();

        // edit the bill owers
        if ($owerIds !== null) {
            // delete old bill owers
            $this->deleteBillOwersOfBill($billid);
            // insert bill owers
            foreach ($owerIds as $owerId) {
                $sql = '
                    INSERT INTO *PREFIX*spend_bill_owers
                    (billid, memberid)
                    VALUES ('.
                        $this->db_quote_escape_string($billid).','.
                        $this->db_quote_escape_string($owerId).
                    ') ;';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();
            }
        }

        $response = new DataResponse(intval($billid));
        return $response;
    }

    private function addBill($projectid, $date, $what, $payer, $payed_for, $amount) {
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
        $sql = '
            INSERT INTO *PREFIX*spend_bills
            (projectid, what, date, amount, payerid)
            VALUES ('.
                $this->db_quote_escape_string($projectid).','.
                $this->db_quote_escape_string($what).','.
                $this->db_quote_escape_string($date).','.
                $this->db_quote_escape_string($amount).','.
                $this->db_quote_escape_string($payer).
            ') ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        $req->closeCursor();

        // get inserted bill id
        $sql = '
            SELECT id
            FROM *PREFIX*spend_bills
            WHERE projectid='.$this->db_quote_escape_string($projectid).'
            ORDER BY id DESC LIMIT 1 ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        while ($row = $req->fetch()){
            $insertedBillId = intval($row['id']);
            break;
        }
        $req->closeCursor();

        // insert bill owers
        foreach ($owerIds as $owerId) {
            $sql = '
                INSERT INTO *PREFIX*spend_bill_owers
                (billid, memberid)
                VALUES ('.
                    $this->db_quote_escape_string($insertedBillId).','.
                    $this->db_quote_escape_string($owerId).
                ') ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();
        }

        $response = new DataResponse($insertedBillId);
        return $response;
    }

    private function addMember($projectid, $name, $weight) {
        if ($name !== null && $name !== '') {
            if ($this->getMemberByName($projectid, $name) === null) {
                $weightToInsert = 1;
                if ($weight !== null && $weight !== '') {
                    if (is_numeric($weight)) {
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
                $sql = '
                    INSERT INTO *PREFIX*spend_members
                    (projectid, name, weight, activated)
                    VALUES ('.
                        $this->db_quote_escape_string($projectid).','.
                        $this->db_quote_escape_string($name).','.
                        $this->db_quote_escape_string($weightToInsert).','.
                        $this->db_quote_escape_string('1').
                    ') ;';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                $insertedMember = $this->getMemberByName($projectid, $name);

                $response = new DataResponse($insertedMember['id']);
                return $response;
            }
            else {
                $response = new DataResponse(
                    ['name'=>["This project already have this member"]]
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

            $sqldel = '
                    DELETE FROM *PREFIX*spend_bills
                    WHERE id='.$this->db_quote_escape_string($billid).'
                        AND projectid='.$this->db_quote_escape_string($projectid).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

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
                $sqlupd = '
                        UPDATE *PREFIX*spend_members
                        SET
                             activated='.$this->db_quote_escape_string('0').'
                        WHERE id='.$this->db_quote_escape_string($memberid).'
                              AND projectid='.$this->db_quote_escape_string($projectid).' ;';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();
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
        $sqldel = '
                DELETE FROM *PREFIX*spend_bill_owers
                WHERE billid='.$this->db_quote_escape_string($billid).' ;';
        $req = $this->dbconnection->prepare($sqldel);
        $req->execute();
        $req->closeCursor();
    }

    private function deleteProject($projectid) {
        $projectToDelete = $this->getProjectById($projectid);
        if ($projectToDelete !== null) {
            // delete project bills
            $bills = $this->getBills($projectid);
            foreach ($bills as $bill) {
                $this->deleteBillOwersOfBill($bill['id']);
            }
            $sqldel = '
                    DELETE FROM *PREFIX*spend_bills
                    WHERE projectid='.$this->db_quote_escape_string($projectid).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
            // delete project members
            $sqldel = '
                    DELETE FROM *PREFIX*spend_members
                    WHERE projectid='.$this->db_quote_escape_string($projectid).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
            // delete project
            $sqldel = '
                    DELETE FROM *PREFIX*spend_projects
                    WHERE id='.$this->db_quote_escape_string($projectid).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
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
                $weightSql = '';
                $activatedSql = '';
                if ($weight !== null && $weight !== '') {
                    if (is_numeric($weight)) {
                        $newWeight = floatval($weight);
                        $weightSql = 'weight='.$this->db_quote_escape_string($newWeight).',';
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
                    $activatedSql = 'activated='.$this->db_quote_escape_string($activated === 'true' ? '1' : '0').',';
                }
                $sqlupd = '
                        UPDATE *PREFIX*spend_members
                        SET
                             '.$weightSql.'
                             '.$activatedSql.'
                             name='.$this->db_quote_escape_string($name).'
                        WHERE id='.$this->db_quote_escape_string($memberid).'
                              AND projectid='.$this->db_quote_escape_string($projectid).' ;';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();

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

    private function editProject($projectid, $name, $contact_email, $password) {
        if ($name === null || $name === '') {
            $response = new DataResponse(
                ["name"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        $emailSql = '';
        if ($contact_email !== null && $contact_email !== '') {
            if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                $emailSql = 'email='.$this->db_quote_escape_string($contact_email).',';
            }
            else {
                $response = new DataResponse(
                    ["contact_email"=> ["Invalid email address"]]
                    , 400
                );
                return $response;
            }
        }
        $passwordSql = '';
        if ($password !== null && $password !== '') {
            $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            $passwordSql = 'password='.$this->db_quote_escape_string($dbPassword).',';
        }
        if ($this->getProjectById($projectid) !== null) {
            $nameSql = 'name='.$this->db_quote_escape_string($name);
            $sqlupd = '
                    UPDATE *PREFIX*spend_projects
                    SET
                         '.$emailSql.'
                         '.$passwordSql.'
                         '.$nameSql.'
                    WHERE id='.$this->db_quote_escape_string($projectid).' ;';
            $req = $this->dbconnection->prepare($sqlupd);
            $req->execute();
            $req->closeCursor();

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

}
