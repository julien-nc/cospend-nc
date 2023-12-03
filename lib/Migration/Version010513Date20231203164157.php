<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010513Date20231203164157 extends SimpleMigrationStep {

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
			if ($table->hasColumn('lastchanged')) {
				$column = $table->getColumn('lastchanged');
				$column->setType(Type::getType(Types::BIGINT));
				$column->setDefault(0);
				$column->setUnsigned(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_bills')) {
			$table = $schema->getTable('cospend_bills');
			if ($table->hasColumn('lastchanged')) {
				$column = $table->getColumn('lastchanged');
				$column->setType(Type::getType(Types::BIGINT));
				$column->setDefault(0);
				$column->setUnsigned(true);
				$schemaChanged = true;
			}
			if ($table->hasColumn('timestamp')) {
				$column = $table->getColumn('timestamp');
				$column->setType(Type::getType(Types::BIGINT));
				$column->setDefault(0);
				$column->setUnsigned(true);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_members')) {
			$table = $schema->getTable('cospend_members');
			if ($table->hasColumn('lastchanged')) {
				$column = $table->getColumn('lastchanged');
				$column->setType(Type::getType(Types::BIGINT));
				$column->setDefault(0);
				$column->setUnsigned(true);
				$schemaChanged = true;
			}
		}

		if ($schemaChanged) {
			return $schema;
		}
		return null;
	}
}
