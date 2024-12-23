<?php

namespace OCA\Cospend\Settings;

use OCA\Cospend\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private IInitialState $initialStateService,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$federationEnabled = $this->appConfig->getValueString('cospend', 'federation_enabled', '0') === '1';
		$balancePastBillsOnly = $this->appConfig->getValueString('cospend', 'balance_past_bills_only', '0') === '1';

		$values = [
			'federation_enabled' => $federationEnabled,
			'balance_past_bills_only' => $balancePastBillsOnly,
		];
		$this->initialStateService->provideInitialState('admin-settings', $values);

		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 10;
	}
}
