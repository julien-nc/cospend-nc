<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Cron;

use OCA\Cospend\Service\LocalProjectService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class RepeatBills extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private LocalProjectService $localProjectService,
	) {
		parent::__construct($time);
		// Run each day
		$this->setInterval(24 * 60 * 60);
	}

	/**
	 * @param $argument
	 * @return void
	 */
	protected function run($argument): void {
		$this->localProjectService->cronRepeatBills();
	}
}
