<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20240911230034 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_bills')) {
			$table = $schema->getTable('cospend_bills');
			// drop columns that have been renamed
			if ($table->hasColumn('project_id') && $table->hasColumn('projectid')) {
				$table->dropColumn('projectid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('payer_id') && $table->hasColumn('payerid')) {
				$table->dropColumn('payerid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('category_id') && $table->hasColumn('categoryid')) {
				$table->dropColumn('categoryid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('payment_mode_id') && $table->hasColumn('paymentmodeid')) {
				$table->dropColumn('paymentmodeid');
				$schemaChanged = true;
			}
			if ($table->hasColumn('payment_mode') && $table->hasColumn('paymentmode')) {
				$table->dropColumn('paymentmode');
				$schemaChanged = true;
			}
			if ($table->hasColumn('repeat_all_active') && $table->hasColumn('repeatallactive')) {
				$table->dropColumn('repeatallactive');
				$schemaChanged = true;
			}
			if ($table->hasColumn('repeat_until') && $table->hasColumn('repeatuntil')) {
				$table->dropColumn('repeatuntil');
				$schemaChanged = true;
			}
			if ($table->hasColumn('repeat_frequency') && $table->hasColumn('repeatfreq')) {
				$table->dropColumn('repeatfreq');
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
