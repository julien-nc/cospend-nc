<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
	 *         multi_payer: bool,
	 *         amount_epsilon: float,
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
				// Lets a client know bills can carry several payers before it offers the
				// option. Without it the only signal is the version string, so the choice
				// would appear against servers that cannot store it and fail at save time.
				'multi_payer' => true,
				// The tolerance the server uses to decide whether a bill's payers account for
				// its amount. Published so a client can show the same verdict live while the
				// user types, instead of keeping a second constant that can drift from this one.
				'amount_epsilon' => Utils::AMOUNT_EPSILON,
			],
		];
	}
}
