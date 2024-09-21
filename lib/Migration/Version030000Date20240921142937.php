<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCA\Cospend\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20240921142937 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
	) {
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
			if (!$table->hasColumn('bill_id')) {
				$table->addColumn('bill_id', Types::BIGINT, [
					'notnull' => true,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('member_id')) {
				$table->addColumn('member_id', Types::BIGINT, [
					'notnull' => true,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_categories')) {
			$table = $schema->getTable('cospend_categories');
			if (!$table->hasColumn('project_id')) {
				$table->addColumn('project_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_currencies')) {
			$table = $schema->getTable('cospend_currencies');
			if (!$table->hasColumn('project_id')) {
				$table->addColumn('project_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_paymentmodes')) {
			$table = $schema->getTable('cospend_paymentmodes');
			if (!$table->hasColumn('project_id')) {
				$table->addColumn('project_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_shares')) {
			$table = $schema->getTable('cospend_shares');
			if (!$table->hasColumn('project_id')) {
				$table->addColumn('project_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('user_id')) {
				$table->addColumn('user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('access_level')) {
				$table->addColumn('access_level', Types::INTEGER, [
					'notnull' => true,
					'default' => Application::ACCESS_LEVEL_PARTICIPANT,
				]);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('cospend_members')) {
			$table = $schema->getTable('cospend_members');
			if (!$table->hasColumn('project_id')) {
				$table->addColumn('project_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('user_id')) {
				$table->addColumn('user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('last_changed')) {
				$table->addColumn('last_changed', Types::BIGINT, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
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
		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_bill_owers');
		$qb->set('bill_id', 'billid');
		$qb->set('member_id', 'memberid');
		$qb->executeStatement();

		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_categories');
		$qb->set('project_id', 'projectid');
		$qb->executeStatement();

		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_currencies');
		$qb->set('project_id', 'projectid');
		$qb->executeStatement();

		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_paymentmodes');
		$qb->set('project_id', 'projectid');
		$qb->executeStatement();

		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_shares');
		$qb->set('project_id', 'projectid');
		$qb->set('user_id', 'userid');
		$qb->set('access_level', 'accesslevel');
		$qb->executeStatement();

		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_members');
		$qb->set('project_id', 'projectid');
		$qb->set('user_id', 'userid');
		$qb->set('last_changed', 'lastchanged');
		$qb->executeStatement();
	}
}
