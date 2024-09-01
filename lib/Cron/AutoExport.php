<?php
/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\Cospend\Cron;

use OCA\Cospend\Service\LocalProjectService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class AutoExport extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private LocalProjectService $projectService
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
		$this->projectService->cronAutoExport();
	}
}
