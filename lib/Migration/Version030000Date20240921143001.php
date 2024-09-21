<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20240921143001 extends SimpleMigrationStep {

	public function __construct() {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$schemaChanged = false;

		if ($schema->hasTable('cospend_bill_owers')) {
			$table = $schema->getTable('cospend_bill_owers');
			// drop columns that have been renamed
			if ($table->hasColumn('bill_id') && $table->hasColumn('billid')) {
				$table->dropColumn('billid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('member_id') && $table->hasColumn('memberid')) {
				$table->dropColumn('memberid');
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_categories')) {
			$table = $schema->getTable('cospend_categories');
			// drop columns that have been renamed
			if ($table->hasColumn('project_id') && $table->hasColumn('projectid')) {
				$table->dropColumn('projectid');
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_currencies')) {
			$table = $schema->getTable('cospend_currencies');
			// drop columns that have been renamed
			if ($table->hasColumn('project_id') && $table->hasColumn('projectid')) {
				$table->dropColumn('projectid');
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_paymentmodes')) {
			$table = $schema->getTable('cospend_paymentmodes');
			// drop columns that have been renamed
			if ($table->hasColumn('project_id') && $table->hasColumn('projectid')) {
				$table->dropColumn('projectid');
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_shares')) {
			$table = $schema->getTable('cospend_shares');
			// drop columns that have been renamed
			if ($table->hasColumn('project_id') && $table->hasColumn('projectid')) {
				$table->dropColumn('projectid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('user_id') && $table->hasColumn('userid')) {
				$table->dropColumn('userid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('access_level') && $table->hasColumn('accesslevel')) {
				$table->dropColumn('accesslevel');
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_members')) {
			$table = $schema->getTable('cospend_members');
			// drop columns that have been renamed
			if ($table->hasColumn('project_id') && $table->hasColumn('projectid')) {
				$table->dropColumn('projectid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('user_id') && $table->hasColumn('userid')) {
				$table->dropColumn('userid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('last_changed') && $table->hasColumn('lastchanged')) {
				$table->dropColumn('lastchanged');
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
