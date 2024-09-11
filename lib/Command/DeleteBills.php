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

use DateTime;
use OC\Core\Command\Base;
use OCA\Cospend\Db\BillMapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteBills extends Base {

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
				'simulate',
				's',
				InputOption::VALUE_NONE,
				'Only simulate bill deletion and print bills that would be deleted'
			)
			->addOption(
				'what',
				'w',
				InputOption::VALUE_REQUIRED,
				'Only delete the bills with this "what" value'
			)
			->addOption(
				'min_timestamp',
				't',
				InputOption::VALUE_REQUIRED,
				'Only delete the bills after this timestamp'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$projectId = $input->getArgument('project_id');
		$what = $input->getOption('what');
		$minTs = $input->getOption('min_timestamp');
		$minTs = $minTs === null ? null : (int) $minTs;

		if ($input->getOption('simulate')) {
			$output->writeln('This is just a simulation');
		}

		$billsToDelete = $this->billMapper->getBillsToDelete($projectId, $what, $minTs);
		foreach ($billsToDelete as $bill) {
			$ts = $bill->getTimestamp();
			$date = (new DateTime())->setTimestamp($ts)->format('c');
			$output->writeln('DELETE [' . $bill->getId() . '] ' . $bill->getWhat() . ' ; ' . $bill->getAmount() . ' ; ' . $date . ' ; ' . $bill->getPayerId());
		}

		if ($input->getOption('simulate')) {
			$output->writeln('0 bill deleted');
			$output->writeln('0 bill ower deleted');
		} else {
			$nbDeleted = $this->billMapper->deleteBills($projectId, $what, $minTs);
			$output->writeln($nbDeleted['bills'] . ' bills deleted');
			$output->writeln($nbDeleted['billOwers'] . ' bill owers deleted');
		}
		return 0;
	}
}
