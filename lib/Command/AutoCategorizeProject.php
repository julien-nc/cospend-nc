<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Command;

use OC\Core\Command\Base;
use OCA\Cospend\Service\LocalProjectService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AutoCategorizeProject extends Base {

	public function __construct(
		private LocalProjectService $projectService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('cospend:auto-categorize:project')
			->setDescription('Auto-categorise uncategorised bills in a project')
			->addArgument(
				'project_id',
				InputArgument::REQUIRED,
				'The id of the project'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$projectId = $input->getArgument('project_id');
		try {
			$count = $this->projectService->autoCategorizeProjectBills($projectId);
			$output->writeln("$count bill(s) categorised in project $projectId");
		} catch (\Exception $e) {
			$output->writeln('<error>Failed to auto-categorise: ' . $e->getMessage() . '</error>');
			return 1;
		}
		return 0;
	}
}
