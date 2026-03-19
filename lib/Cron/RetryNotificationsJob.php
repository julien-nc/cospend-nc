<?php

declare(strict_types=1);

namespace OCA\Cospend\Cron;

use OCA\Cospend\Federation\BackendNotifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Retry sending failed OCM notifications for federated Cospend projects
 */
class RetryNotificationsJob extends TimedJob {
	public function __construct(
		private BackendNotifier $backendNotifier,
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);

		// Run on every cron execution
		$this->setInterval(1);
	}

	#[\Override]
	protected function run($argument): void {
		$this->backendNotifier->retrySendingFailedNotifications($this->time->getDateTime());
	}
}
