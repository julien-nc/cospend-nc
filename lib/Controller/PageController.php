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

use OC\Files\Filesystem;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\AppInfo\Application;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private ProjectService $projectService,
		private IDBConnection $dbconnection,
		private IInitialState $initialStateService,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Main page
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(?string $projectId = null, ?int $billId = null): TemplateResponse {
		$activityEnabled = $this->appManager->isEnabledForUser('activity');
		$this->initialStateService->provideInitialState('activity_enabled', $activityEnabled ? '1' : '0');
		$this->initialStateService->provideInitialState('pathProjectId', $projectId ?? '');
		$this->initialStateService->provideInitialState('pathBillId', $billId ?? 0);
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());
		$response = new TemplateResponse('cospend', 'main', []);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
//			->addAllowedChildSrcDomain('*')
			->addAllowedFrameDomain('*')
			->addAllowedWorkerSrcDomain('*')
			//->allowInlineScript(true)
			// to make eval work in frontend
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
	 * @param string $fileName
	 * @param string $color
	 * @return NotFoundResponse|Response
	 */
	public function getSvgFromApp(string $fileName, string $color = 'ffffff') {
		try {
			$appPath = $this->appManager->getAppPath(Application::APP_ID);
		} catch (AppPathNotFoundException $e) {
			return new NotFoundResponse();
		}

		$path = $appPath . "/img/$fileName.svg";
		return $this->getSvg($path, $color, $fileName);
	}

	private function getSvg(string $path, string $color, string $fileName): Response {
		if (!Filesystem::isValidPath($path)) {
			return new NotFoundResponse();
		}

		if (!file_exists($path)) {
			return new NotFoundResponse();
		}

		$svg = file_get_contents($path);

		if ($svg === null) {
			return new NotFoundResponse();
		}

		$svg = $this->colorizeSvg($svg, $color);

		$response = new DataDisplayResponse($svg, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);

		// Set cache control
		$ttl = 31536000;
		$response->cacheFor($ttl);

		return $response;
	}

	public function colorizeSvg(string $svg, string $color): string {
		if (!preg_match('/^[0-9a-f]{3,6}$/i', $color)) {
			// Prevent not-sane colors from being written into the SVG
			$color = '000';
		}

		// add fill (fill is not present on black elements)
		$fillRe = '/<((circle|rect|path)((?!fill)[a-z0-9 =".\-#():;,])+)\/>/mi';
		$svg = preg_replace($fillRe, '<$1 fill="#' . $color . '"/>', $svg);

		// replace any fill or stroke colors
		$svg = preg_replace('/stroke="#([a-z0-9]{3,6})"/mi', 'stroke="#' . $color . '"', $svg);
		$svg = preg_replace('/fill="#([a-z0-9]{3,6})"/mi', 'fill="#' . $color . '"', $svg);
		return $svg;
	}

	/**
	 * Main page
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexProject(string $projectId): TemplateResponse {
		return $this->index($projectId);
	}

	/**
	 * Main page
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexBill(string $projectId, int $billId): TemplateResponse {
		return $this->index($projectId, $billId);
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
		$isMain = false;
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($token);
		if (!is_null($publicShareInfo)) {
			$isPasswordProtected = !is_null($publicShareInfo['password'] ?? null);
			if ($isPasswordProtected) {
				$params = [
					'projecttoken' => $token,
					'wrong' => false,
				];
				$response = new PublicTemplateResponse('cospend', 'sharepassword', $params);
				$response->setHeaderDetails($this->trans->t('Enter link password of project %s', [$publicShareInfo['projectid']]));
			} else {
				$this->initialStateService->provideInitialState('projectid', $token);
				$this->initialStateService->provideInitialState('password', 'nopass');

				$response = new PublicTemplateResponse('cospend', 'main', []);
				$response->setHeaderDetails($this->trans->t('Project %s', [$publicShareInfo['projectid']]));
				$isMain = true;
			}
			$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
			$response->setFooterVisible(false);
		} else {
			$response = new PublicTemplateResponse('cospend', 'error', []);
			$response->setHeaderTitle($this->trans->t('No such share link'));
			$response->setHeaderDetails($this->trans->t('Access denied'));
		}
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
		if ($isMain) {
			$csp->allowEvalScript(true);
		}
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function pubProject(?string $projectid = null, ?string $password = null, ?string $projecttoken = null): PublicTemplateResponse {
		if (!is_null($projectid) && !is_null($password)) {
			if ($this->checkLogin($projectid, $password)) {
				$this->initialStateService->provideInitialState('projectid', $projectid);
				$this->initialStateService->provideInitialState('password', $password);
				$response = new PublicTemplateResponse('cospend', 'main', []);
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
				//$response = new DataResponse(null, Http::STATUS_FORBIDDEN);
				//return $response;
				$params = [
					'wrong' => true,
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
		} elseif (!is_null($projecttoken) && !is_null($password)) {
			$info = $this->projectService->getProjectInfoFromShareToken($projecttoken);
			// if the token is good and no password (or it matches the share one)
			if (!is_null($info['projectid'] ?? null)
				&& (is_null($info['password'] ?? null) || $password === $info['password'])
			) {
				$this->initialStateService->provideInitialState('projectid', $projecttoken);
				$this->initialStateService->provideInitialState('password', $password);

				$response = new PublicTemplateResponse('cospend', 'main', []);
				$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
				$response->setHeaderDetails($this->trans->t('Project %s', [$info['projectid']]));
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
			} elseif (!is_null($info['projectid'] ?? null)) {
				$params = [
					'projecttoken' => $projecttoken,
					'wrong' => true,
				];
				$response = new PublicTemplateResponse('cospend', 'sharepassword', $params);
				$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
				$response->setHeaderDetails($this->trans->t('Enter link password of project %s', [$info['projectid']]));
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
		// TODO return error page
		$response = new PublicTemplateResponse('cospend', 'error', []);
		$response->setHeaderTitle($this->trans->t('No such share link or public access'));
		$response->setHeaderDetails($this->trans->t('Access denied'));
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function editGuestAccessLevel(string $projectid, int $accesslevel): DataResponse {
		$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $projectid);
		if ($userAccessLevel >= Application::ACCESS_LEVEL_ADMIN) {
			$result = $this->projectService->editGuestAccessLevel($projectid, $accesslevel);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit guest access level')],
				Http::STATUS_FORBIDDEN
			);
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
