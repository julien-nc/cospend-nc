<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Command;

use OC\Core\Command\Base;
use OCA\Cospend\Service\LocalProjectService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AutoCategorizeAll extends Base {

	public function __construct(
		private LocalProjectService $projectService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('cospend:auto-categorize:all')
			->setDescription('Auto-categorise uncategorised bills in all non-archived projects with auto-categorisation enabled');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		try {
			$totalCount = $this->projectService->autoCategorizeAllBills();
			$output->writeln("$totalCount bill(s) categorised across all projects");
		} catch (\Exception $e) {
			$output->writeln('<error>Failed to auto-categorise: ' . $e->getMessage() . '</error>');
			return 1;
		}
		return 0;
	}
}
