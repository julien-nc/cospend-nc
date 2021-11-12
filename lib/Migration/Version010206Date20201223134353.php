<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010206Date20201223134353 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('cospend_projects');
		if (!$table->hasColumn('deletiondisabled')) {
			$table->addColumn('deletiondisabled', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
		}
		if (!$table->hasColumn('categorysort')) {
			$table->addColumn('categorysort', 'string', [
				'notnull' => true,
				'length' => 1,
				'default' => 'a',
			]);
		}

		$table = $schema->getTable('cospend_categories');
		if (!$table->hasColumn('order')) {
			$table->addColumn('order', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
