<?php
/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\Cospend\Cron;

use OCA\Cospend\Service\CospendService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class AutoExport extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private CospendService $cospendService
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
		$this->cospendService->cronAutoExport();
	}
}
