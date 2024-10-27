<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030003Date20241027164309 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_categories')) {
			$table = $schema->getTable('cospend_categories');
			if ($table->hasColumn('encoded_icon')) {
				$column = $table->getColumn('encoded_icon');
				$column->setLength(256);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_paymentmodes')) {
			$table = $schema->getTable('cospend_paymentmodes');
			if ($table->hasColumn('encoded_icon')) {
				$column = $table->getColumn('encoded_icon');
				$column->setLength(256);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
