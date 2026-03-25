<?php

declare(strict_types=1);

namespace OCA\Cospend;

use OCA\Cospend\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use OCP\IAppConfig;

class Capabilities implements IPublicCapability {

	public function __construct(
		private IAppManager $appManager,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @return array{
	 *     cospend: array{
	 *         version: string,
	 *         federation: array{
	 *             enabled: bool,
	 *         },
	 *     }
	 * }
	 */
	public function getCapabilities(): array {
		$appVersion = $this->appManager->getAppVersion(Application::APP_ID);
		$federationEnabled = $this->appConfig->getValueString(Application::APP_ID, 'federation_enabled', '0', lazy: true) === '1';
		return [
			Application::APP_ID => [
				'version' => $appVersion,
				'federation' => [
					'enabled' => $federationEnabled,
				],
			],
		];
	}
}
