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
            return $this->getProjectInfo($projectid);
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

    private function createProject($name, $id, $password, $contact_email) {
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
                    $this->db_quote_escape_string('').','.
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
        $sql = '
            SELECT id, password, name, email
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
            $response = new DataResponse(
                [
                    'name'=>$dbName,
                    'contact_email'=>$dbEmail,
                    'id'=>$dbProjectId,
                    'active_members'=>$activeMembers,
                    'members'=>$members,
                    'balance'=>$balance
                ]
            );
            return $response;
        }
        else {
            $response = new DataResponse(
                ['message'=>'Project not found in the database']
                , 404
            );
            return $response;
        }
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
            $dbMemberId = $row['id'];
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
        // TODO
        return [];
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
            $dbMemberId = $row['id'];
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
            $dbMemberId = $row['id'];
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
        if ($contact_email === null || $name === '') {
            $response = new DataResponse(
                ["contact_email"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        if ($password === null || $password === '') {
            $response = new DataResponse(
                ["password"=> ["This field is required."]]
                , 400
            );
            return $response;
        }
        if ($this->getProjectById($projectid) !== null) {
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
            $nameSql = 'name='.$this->db_quote_escape_string($name).',';
            $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            $passwordSql = 'password='.$this->db_quote_escape_string($dbPassword);
            $sqlupd = '
                    UPDATE *PREFIX*spend_projects
                    SET
                         '.$nameSql.'
                         '.$emailSql.'
                         '.$passwordSql.'
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
