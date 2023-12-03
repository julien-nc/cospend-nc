<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010512Date20231201151136 extends SimpleMigrationStep {

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
		if ($schema->hasTable('cospend_projects')) {
			$table = $schema->getTable('cospend_projects');
			if (!$table->hasColumn('archived_ts')) {
				$table->addColumn('archived_ts', Types::BIGINT, [
					'notnull' => false,
					'default' => null,
					'unsigned' => true,
				]);
				return $schema;
			}
		}
		return null;
	}
}
