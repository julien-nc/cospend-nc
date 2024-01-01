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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;

use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\AppInfo\Application;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $trans,
		private ProjectService $projectService,
		private IInitialState $initialStateService,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
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
		$activityEnabled = $this->appManager->isEnabledForUser('activity');
		$this->initialStateService->provideInitialState('activity_enabled', $activityEnabled ? '1' : '0');
		$this->initialStateService->provideInitialState('pathProjectId', $projectId ?? '');
		$this->initialStateService->provideInitialState('pathBillId', $billId ?? 0);
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());
		return new TemplateResponse('cospend', 'main');
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

	// TODO add bruteforce protection
	/**
	 * @param string $token
	 * @return PublicTemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function publicShareLinkPage(string $token): PublicTemplateResponse {
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
			}
			$response->setHeaderTitle($this->trans->t('Cospend shared link access'));
			$response->setFooterVisible(false);
		} else {
			$templateParams = [
				'errors' => [
					['error' => $this->trans->t('Access denied')],
				],
			];
			$response = new PublicTemplateResponse('core', '403', $templateParams);
			$response->setHeaderTitle($this->trans->t('No such share link'));
		}
		$response->setFooterVisible(false);
		return $response;
	}

	// TODO improve token param name
	/**
	 * @param string $projecttoken
	 * @param string|null $password
	 * @return PublicTemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function pubProject(string $projecttoken, ?string $password = null): PublicTemplateResponse {
		if ($projecttoken && !is_null($password)) {
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
				return $response;
			}
		}
		// TODO return error page
		$response = new PublicTemplateResponse('cospend', 'error', []);
		$response->setHeaderTitle($this->trans->t('No such share link or public access'));
		$response->setHeaderDetails($this->trans->t('Access denied'));
		return $response;
	}

	// TODO remove this and cleanup UI from all guest access related stuff
	/**
	 * @param string $projectid
	 * @param int $accesslevel
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
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
	 * @param int|null $since
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getBillActivity(?int $since): DataResponse {
		$result = $this->projectService->getBillActivity($this->userId, $since);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
