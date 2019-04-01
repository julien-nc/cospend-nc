<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000007Date20190401053312 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('cospend_projects')) {
			$table = $schema->createTable('cospend_projects');
			$table->addColumn('id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('userid', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('email', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('password', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
		}

		if (!$schema->hasTable('cospend_ext_projects')) {
			$table = $schema->createTable('cospend_ext_projects');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('projectid', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('ncurl', 'string', [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('userid', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('password', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('cospend_shares')) {
			$table = $schema->createTable('cospend_shares');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('projectid', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('userid', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('isgroupshare', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('cospend_members')) {
			$table = $schema->createTable('cospend_members');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('projectid', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('weight', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('activated', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 1,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('cospend_bills')) {
			$table = $schema->createTable('cospend_bills');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('projectid', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('what', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('date', 'string', [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('amount', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('payerid', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('repeat', 'string', [
				'notnull' => true,
				'length' => 1,
				'default' => 'n',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('cospend_bill_owers')) {
			$table = $schema->createTable('cospend_bill_owers');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('billid', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('memberid', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->setPrimaryKey(['id']);
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
