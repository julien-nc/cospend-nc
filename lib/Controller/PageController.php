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
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Service\ProjectService;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;

use OCP\IL10N;
use OCP\IRequest;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private ProjectService $projectService,
		private IInitialState $initialStateService,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
		private IConfig $config,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	protected function isDebugModeEnabled(): bool {
		return $this->config->getSystemValueBool('debug', false);
	}

	/**
	 * Main page
	 *
	 * @param string|null $projectId
	 * @param int|null $billId
	 * @return TemplateResponse
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $projectId = null, ?int $billId = null): TemplateResponse {
		$activityEnabled = $this->appManager->isEnabledForUser('activity');
		$this->initialStateService->provideInitialState('activity_enabled', $activityEnabled ? '1' : '0');
		$this->initialStateService->provideInitialState('pathProjectId', $projectId ?? '');
		$this->initialStateService->provideInitialState('pathBillId', $billId ?? 0);
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());
		$response = new TemplateResponse('cospend', 'main');
		$csp = new ContentSecurityPolicy();
		$csp->allowEvalScript();
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $fileName
	 * @param string $color
	 * @return NotFoundResponse|Response
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
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

		if ($svg === false) {
			return new NotFoundResponse();
		}

		$svg = $this->colorizeSvg($svg, $color);

		$response = new DataDisplayResponse($svg, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);

		// Set cache control
		$ttl = 31536000;
		$response->cacheFor($ttl);

		return $response;
	}

	private function colorizeSvg(string $svg, string $color): string {
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
	 *
	 * @param string $projectId
	 * @return TemplateResponse
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexProject(string $projectId): TemplateResponse {
		return $this->index($projectId);
	}

	/**
	 * Main page
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return TemplateResponse
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexBill(string $projectId, int $billId): TemplateResponse {
		return $this->index($projectId, $billId);
	}

	/**
	 * @param string $token
	 * @return TemplateResponse
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'CospendPublicShareLinkPage')]
	public function publicShareLinkPage(string $token): TemplateResponse {
		$publicShareInfo = $this->projectService->getShareInfoFromShareToken($token);
		if (!is_null($publicShareInfo)) {
			$isPasswordProtected = !is_null($publicShareInfo['password'] ?? null);
			if ($isPasswordProtected) {
				$params = [
					'token' => $token,
					'wrong' => false,
				];
				$response = new PublicTemplateResponse('cospend', 'sharepassword', $params);
				$response->setHeaderDetails($this->trans->t('Enter link password of project %s', [$publicShareInfo['projectid']]));
				$response->setFooterVisible(false);
			} else {
				$this->initialStateService->provideInitialState('projectid', $token);
				$this->initialStateService->provideInitialState('password', 'nopass');

				$response = new PublicTemplateResponse('cospend', 'main', []);
				$csp = new ContentSecurityPolicy();
				$csp->allowEvalScript();
				$response->setContentSecurityPolicy($csp);
				$response->setHeaderDetails($this->trans->t('Project %s', [$publicShareInfo['projectid']]));
			}
			$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
			$response->setFooterVisible(false);
		} else {
			$templateParams = ['message' => $this->trans->t('No such Cospend share link')];
			$response = new TemplateResponse('core', '403', $templateParams, TemplateResponse::RENDER_AS_ERROR);
			if (!$this->isDebugModeEnabled()) {
				$throttleMetadata = [
					'reason' => 'wrong token',
				];
				$response->throttle($throttleMetadata);
			}
		}
		return $response;
	}

	/**
	 * @param string $token
	 * @param string|null $password
	 * @return TemplateResponse
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'CospendPublicProjectPage')]
	public function pubProject(string $token, ?string $password = null): TemplateResponse {
		if ($token && !is_null($password)) {
			$info = $this->projectService->getShareInfoFromShareToken($token);
			// if the token is good and no password (or it matches the share one)
			if (!is_null($info['projectid'] ?? null)
				&& (is_null($info['password'] ?? null) || $password === $info['password'])
			) {
				$this->initialStateService->provideInitialState('projectid', $token);
				$this->initialStateService->provideInitialState('password', $password);

				$response = new PublicTemplateResponse('cospend', 'main', []);
				$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
				$response->setHeaderDetails($this->trans->t('Project %s', [$info['projectid']]));
				$response->setFooterVisible(false);
				$csp = new ContentSecurityPolicy();
				$csp->allowEvalScript();
				$response->setContentSecurityPolicy($csp);
				return $response;
			} elseif (!is_null($info['projectid'] ?? null)) {
				// good token, incorrect password
				$params = [
					'token' => $token,
					'wrong' => true,
				];
				$response = new PublicTemplateResponse('cospend', 'sharepassword', $params);
				$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
				$response->setHeaderDetails($this->trans->t('Enter link password of project %s', [$info['projectid']]));
				$response->setFooterVisible(false);
				if (!$this->isDebugModeEnabled()) {
					$throttleMetadata = [
						'reason' => 'wrong password',
					];
					$response->throttle($throttleMetadata);
				}
				return $response;
			}
		}
		$templateParams = ['message' => $this->trans->t('No such Cospend share link')];
		$response = new TemplateResponse('core', '403', $templateParams, TemplateResponse::RENDER_AS_ERROR);
		if (!$this->isDebugModeEnabled()) {
			$throttleMetadata = [
				'reason' => 'wrong token',
			];
			$response->throttle($throttleMetadata);
		}
		return $response;
	}
}
