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
            $dbPassword = password_hash($password, PASSWORD_BCRYPT);
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

}
