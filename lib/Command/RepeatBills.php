<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
