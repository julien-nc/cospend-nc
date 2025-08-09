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
use OCA\Cospend\Service\LocalProjectService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepeatBills extends Base {

	public function __construct(
		private LocalProjectService $localProjectService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('cospend:repeat-bills')
			->setDescription('Repeat bills if necessary');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$repeated = $this->localProjectService->cronRepeatBills();
		foreach ($repeated as $r) {
			$output->writeln(
				'[Project "' . $r['project_name'] . '"] Bill "' . $r['what']
				. '" (' . $r['date_orig'] . ') repeated on (' . $r['date_repeat'] . ')'
			);
		}
		return 0;
	}
}
