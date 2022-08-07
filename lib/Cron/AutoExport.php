<?php
/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\Cospend\Cron;

use OCA\Cospend\Service\ExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class AutoExport extends TimedJob {
	/**
	 * @var ExportService
	 */
	private $exportService;

	/**
	 * @param ITimeFactory $time
	 * @param ExportService $exportService
	 */
	public function __construct(ITimeFactory $time, ExportService $exportService) {
		parent::__construct($time);

		$this->exportService = $exportService;
		$this->setInterval(24 * 60 * 60); // Run each day
	}

	/**
	 * @param $argument
	 * @return void
	 */
	protected function run($argument): void {
		$this->exportService->cronAutoExport();
	}
}
