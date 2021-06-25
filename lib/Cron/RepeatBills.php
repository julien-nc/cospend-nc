<?php
/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\Cospend\Cron;

use OC\BackgroundJob\TimedJob;
use OCA\Cospend\Service\ProjectService;

class RepeatBills extends TimedJob {

	/**
	 * @var ProjectService
	 */
	private $projectService;

	public function __construct(ProjectService $projectService) {
		// Run each day
		$this->setInterval(24 * 60 * 60);
		$this->projectService = $projectService;
	}

	protected function run($argument) {
		$this->projectService->cronRepeatBills();
	}

}
