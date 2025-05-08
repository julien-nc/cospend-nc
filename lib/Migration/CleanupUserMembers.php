<?php

namespace OCA\Cospend\Migration;

use OCA\Cospend\Db\MemberMapper;
use OCP\DB\Exception;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class CleanupUserMembers implements IRepairStep {

	public function __construct(
		private MemberMapper $memberMapper,
	) {
	}

	public function getName() {
		return 'Fix Cospend project members whose related NC user does not exist';
	}

	/**
	 * @param IOutput $output
	 * @throws Exception
	 */
	public function run(IOutput $output) {
		$fixedUserIds = $this->memberMapper->cleanupUserMembers();
		if (empty($fixedUserIds)) {
			$output->info('No members to fix');
		} else {
			$output->info('Fixed ' . count($fixedUserIds) . ' Cospend project members whose related NC user does not exist:');
			$output->info(implode(', ', $fixedUserIds));
		}
	}
}
