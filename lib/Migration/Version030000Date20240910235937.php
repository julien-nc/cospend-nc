<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20240910235937 extends SimpleMigrationStep {

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
			// drop columns that have been renamed
			if ($table->hasColumn('user_id') && $table->hasColumn('userid')) {
				$table->dropColumn('userid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('currency_name') && $table->hasColumn('currencyname')) {
				$table->dropColumn('currencyname');
				$schemaChanged = true;
			}
			if ($table->hasColumn('deletion_disabled') && $table->hasColumn('deletiondisabled')) {
				$table->dropColumn('deletiondisabled');
				$schemaChanged = true;
			}
			if ($table->hasColumn('category_sort') && $table->hasColumn('categorysort')) {
				$table->dropColumn('categorysort');
				$schemaChanged = true;
			}
			if ($table->hasColumn('payment_mode_sort') && $table->hasColumn('paymentmodesort')) {
				$table->dropColumn('paymentmodesort');
				$schemaChanged = true;
			}
			if ($table->hasColumn('auto_export') && $table->hasColumn('autoexport')) {
				$table->dropColumn('autoexport');
				$schemaChanged = true;
			}
			if ($table->hasColumn('last_changed') && $table->hasColumn('lastchanged')) {
				$table->dropColumn('lastchanged');
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
