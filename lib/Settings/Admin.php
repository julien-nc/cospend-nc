<?php

namespace OCA\Cospend\Settings;

use OCA\Cospend\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private IInitialState $initialStateService,
		private IConfig $config,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$federationEnabled = $this->config->getAppValue('cospend', 'federation_enabled', '0') === '1';
		$this->initialStateService->provideInitialState('federation_enabled', $federationEnabled);

		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 10;
	}
}
