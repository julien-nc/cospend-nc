<?php
/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\Cospend\Cron;

use OCA\Cospend\Service\ProjectService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class AutoExport extends TimedJob {
	/**
	 * @var ProjectService
	 */
	private $projectService;

	/**
	 * @param ITimeFactory $time
	 * @param ProjectService $projectService
	 */
	public function __construct(ITimeFactory $time, ProjectService $projectService) {
		parent::__construct($time);
		$this->projectService = $projectService;

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
