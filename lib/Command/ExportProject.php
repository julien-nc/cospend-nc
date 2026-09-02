<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Command;

use OC\Core\Command\Base;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\CospendService;
use OCA\Cospend\Service\LocalProjectService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportProject extends Base {

	public function __construct(
		private LocalProjectService $localProjectService,
		private CospendService $cospendService,
		private ProjectMapper $projectMapper,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('cospend:export-project')
			->setDescription(
				'Export a project to CSV as a file in Nextcloud and/or on the file system.'
			)
			->addArgument(
				'project_id',
				InputArgument::REQUIRED,
				'The id of the project you want to export'
			)
			->addOption(
				'internal-path',
				's',
				InputOption::VALUE_REQUIRED,
				'Optional path to export the file in the Nextcloud storage. This path must be relative to the user\'s default export directory'
			)
			->addOption(
				'filesystem-path',
				'f',
				InputOption::VALUE_REQUIRED,
				'Optional file system path to export the file in the file system, outside Nextcloud'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$projectId = $input->getArgument('project_id');
		$internalPath = $input->getOption('internal-path');
		$fsPath = $input->getOption('filesystem-path');

		if ($internalPath === null && $fsPath === null) {
			$output->writeln(
				'<error>You must specify either --internal-path or --filesystem-path</error>'
			);
			return 1;
		}

		$dbProject = $this->projectMapper->find($projectId);
		if ($dbProject === null) {
			$output->writeln('Project ' . $projectId . ' not found');
			return 1;
		}

		$projectInfo = $this->localProjectService->getProjectInfoWithAccessLevel($projectId, $dbProject->getUserId());
		$bills = $this->localProjectService->getBills($projectId);

		if ($fsPath !== null) {
			$result = $this->cospendService->exportCsvProject($projectId, $dbProject->getUserId(), $projectInfo, $bills['bills'] ?? [], $fsPath, true);
			if (!array_key_exists('path', $result)) {
				$output->writeln('<error>Error: ' . $result['message'] . '</error>');
				return 1;
			}
			$output->writeln(
				'<info>Project "' . $projectId . '" was exported in "' . $result['path'] . '" on the filesystem.</info>'
			);
		}

		if ($internalPath !== null) {
			$result = $this->cospendService->exportCsvProject($projectId, $dbProject->getUserId(), $projectInfo, $bills['bills'] ?? [], $internalPath, false);
			if (!array_key_exists('path', $result)) {
				$output->writeln('<error>Error: ' . $result['message'] . '</error>');
				return 1;
			}
			$output->writeln(
				'<info>Project "' . $projectId . '" was exported in "' . $result['path']
				. '" in the storage of user "' . $dbProject->getUserId() . '".</info>'
			);
		}

		return 0;
	}
}
