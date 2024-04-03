<?php

declare(strict_types=1);

namespace OCA\Cospend;

use OCA\Cospend\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;

class Capabilities implements IPublicCapability {

	public function __construct(
		private IAppManager $appManager
	) {
	}

	/**
	 * @return array{
	 *     cospend: array{
	 *         version: string,
	 *     }
	 * }
	 */
	public function getCapabilities(): array {
		$appVersion = $this->appManager->getAppVersion(Application::APP_ID);
		return [
			Application::APP_ID => [
				'version' => $appVersion,
			],
		];
	}
}
