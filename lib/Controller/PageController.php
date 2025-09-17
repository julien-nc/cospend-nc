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
use OCA\Cospend\Service\LocalProjectService;
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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;

use OCP\IL10N;
use OCP\IRequest;
use OCP\PreConditionNotMetException;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private LocalProjectService $projectService,
		private IInitialState $initialStateService,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
		private IConfig $config,
		private ?string $userId,
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
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $projectId = null, ?int $billId = null): TemplateResponse {
		$state = $this->getOptionsValues();
		$activityEnabled = $this->appManager->isEnabledForUser('activity');
		$state['activity_enabled'] = $activityEnabled;
		if ($state['selectedProject']) {
			$state['restoredCurrentProjectId'] = $state['selectedProject'];
		}
		if ($projectId !== null) {
			$state['restoredCurrentProjectId'] = $projectId;
		}
		if ($billId !== null) {
			$state['restoredCurrentBillId'] = $billId;
		}
		$state['useTime'] = $state['useTime'] !== '0';
		$state['showMyBalance'] = $state['showMyBalance'] !== '0';

		$this->initialStateService->provideInitialState('cospend-state', $state);
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());
		$response = new TemplateResponse('cospend', 'main');
		return $response;
	}

	/**
	 * @param string $fileName
	 * @param string $color
	 * @return NotFoundResponse|Response
	 */
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
		try {
			$colorizedSvg = preg_replace($fillRe, '<$1 fill="#' . $color . '"/>', $svg);

			// replace any fill or stroke colors
			$colorizedSvg = preg_replace('/stroke="#([a-z0-9]{3,6})"/mi', 'stroke="#' . $color . '"', $colorizedSvg);
			$colorizedSvg = preg_replace('/fill="#([a-z0-9]{3,6})"/mi', 'fill="#' . $color . '"', $colorizedSvg);
			return $colorizedSvg ?? $svg;
		} catch (\Exception|\Throwable $e) {
			return $svg;
		}
	}

	/**
	 * Main page
	 *
	 * @param string $projectId
	 * @return TemplateResponse
	 */
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
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexBill(string $projectId, int $billId): TemplateResponse {
		return $this->index($projectId, $billId);
	}

	/**
	 * @param string $token
	 * @return TemplateResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'CospendPublicShareLinkPage')]
	public function publicShareLinkPage(string $token): TemplateResponse {
		$publicShareInfo = $this->projectService->getLinkShareInfoFromShareToken($token);
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
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[BruteForceProtection(action: 'CospendPublicProjectPage')]
	public function pubProject(string $token, ?string $password = null): TemplateResponse {
		if ($token && !is_null($password)) {
			$info = $this->projectService->getLinkShareInfoFromShareToken($token);
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

	/**
	 * Delete user settings
	 *
	 * @return DataResponse<Http::STATUS_OK, '', array{}>
	 */
	#[NoAdminRequired]
	public function deleteOptionsValues(): DataResponse {
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $key);
		}

		return new DataResponse('');
	}

	/**
	 * Save setting values
	 *
	 * Save setting values to the database for the current user
	 *
	 * @param array<string> $options Array of setting key/values to save
	 * @return DataResponse<Http::STATUS_OK, '', array{}>
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	public function saveOptionValues(array $options): DataResponse {
		foreach ($options as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}

		return new DataResponse('');
	}

	private function getOptionsValues(): array {
		$settings = [];
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			$settings[$key] = $value;
		}
		return $settings;
	}
}
