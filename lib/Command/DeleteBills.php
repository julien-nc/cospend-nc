<?php

/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Cospend\Command;

use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\ProjectMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\Cospend\Service\ProjectService;

class DeleteBills extends Command {

	public function __construct(private BillMapper $billMapper) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('cospend:delete-bills')
			->setDescription('Delete some bills of a project')
			->addArgument(
				'project_id',
				InputArgument::REQUIRED,
				'The id of the project'
			)
			->addOption(
				'what',
				'w',
				InputOption::VALUE_OPTIONAL,
				'Only delete the bills with this "what" value'
			)
			->addOption(
				'min_timestamp',
				't',
				InputOption::VALUE_OPTIONAL,
				'Only delete the bills after this timestamp'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$projectId = $input->getArgument('project_id');
		$what = $input->getOption('what');
		$minTs = $input->getOption('min_timestamp');
		$minTs = $minTs === null ? null : (int) $minTs;
		$nbDeleted = $this->billMapper->deleteBills($projectId, $what, $minTs);
		$output->writeln($nbDeleted['bills'].' bills deleted');
		$output->writeln($nbDeleted['billOwers'].' bill owers deleted');
		return 0;
	}
}
