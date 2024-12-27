<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030007Date20241225163007 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_projects')) {
			$table = $schema->getTable('cospend_projects');
			if ($table->hasColumn('user_id')) {
				$column = $table->getColumn('user_id');
				$column->setNotnull(false);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_bills')) {
			$table = $schema->getTable('cospend_bills');
			if ($table->hasColumn('project_id')) {
				$column = $table->getColumn('project_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
			if ($table->hasColumn('payer_id')) {
				$column = $table->getColumn('payer_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_bill_owers')) {
			$table = $schema->getTable('cospend_bill_owers');
			if ($table->hasColumn('bill_id')) {
				$column = $table->getColumn('bill_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
			if ($table->hasColumn('member_id')) {
				$column = $table->getColumn('member_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_members')) {
			$table = $schema->getTable('cospend_members');
			if ($table->hasColumn('project_id')) {
				$column = $table->getColumn('project_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_shares')) {
			$table = $schema->getTable('cospend_shares');
			if ($table->hasColumn('project_id')) {
				$column = $table->getColumn('project_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_categories')) {
			$table = $schema->getTable('cospend_categories');
			if ($table->hasColumn('project_id')) {
				$column = $table->getColumn('project_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_paymentmodes')) {
			$table = $schema->getTable('cospend_paymentmodes');
			if ($table->hasColumn('project_id')) {
				$column = $table->getColumn('project_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_currencies')) {
			$table = $schema->getTable('cospend_currencies');
			if ($table->hasColumn('project_id')) {
				$column = $table->getColumn('project_id');
				$column->setNotnull(true);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
