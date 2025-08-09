<?php

/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Command;

use OC\Core\Command\Base;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\CospendService;
use OCA\Cospend\Service\LocalProjectService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
			->setDescription('Export a project to CSV')
			->addArgument(
				'project_id',
				InputArgument::REQUIRED,
				'The id of the project you want to export'
			)
			->addArgument(
				'filename',
				InputArgument::OPTIONAL,
				'The name of the exported file'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$projectId = $input->getArgument('project_id');
		$name = $input->getArgument('filename');
		$dbProject = $this->projectMapper->find($projectId);
		if ($dbProject !== null) {
			$projectInfo = $this->localProjectService->getProjectInfoWithAccessLevel($projectId, $dbProject->getUserId());
			$bills = $this->localProjectService->getBills($projectId);
			$result = $this->cospendService->exportCsvProject($projectId, $dbProject->getUserId(), $projectInfo, $bills['bills'] ?? [], $name);
			if (array_key_exists('path', $result)) {
				$output->writeln(
					'Project "' . $projectId . '" exported in "' . $result['path']
					. '" of user "' . $dbProject->getUserId() . '" storage'
				);
			} else {
				$output->writeln('Error: ' . $result['message']);
			}
		} else {
			$output->writeln('Project ' . $projectId . ' not found');
		}
		return 0;
	}
}
