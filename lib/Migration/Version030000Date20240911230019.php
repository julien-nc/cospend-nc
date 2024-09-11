<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20240911230019 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_bills')) {
			$table = $schema->getTable('cospend_bills');
			// rename columns
			if (!$table->hasColumn('project_id')) {
				$table->addColumn('project_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('payer_id')) {
				$table->addColumn('payer_id', Types::BIGINT, [
					'notnull' => true,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('category_id')) {
				$table->addColumn('category_id', Types::BIGINT, [
					'notnull' => false,
					'default' => null,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('payment_mode_id')) {
				$table->addColumn('payment_mode_id', Types::BIGINT, [
					'notnull' => false,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('payment_mode')) {
				$table->addColumn('payment_mode', Types::STRING, [
					'notnull' => false,
					'length' => 1,
					'default' => null,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('last_changed')) {
				$table->addColumn('last_changed', Types::BIGINT, [
					'notnull' => true,
					'unsigned' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('repeat_all_active')) {
				$table->addColumn('repeat_all_active', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('repeat_until')) {
				$table->addColumn('repeat_until', Types::INTEGER, [
					'notnull' => false,
					'default' => null,
					'length' => 20,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('repeat_frequency')) {
				$table->addColumn('repeat_frequency', Types::INTEGER, [
					'notnull' => true,
					'default' => 1,
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
		$qb->update('cospend_bills');
		$qb->set('project_id', 'projectid');
		$qb->set('payer_id', 'payerid');
		$qb->set('category_id', 'categoryid');
		$qb->set('payment_mode_id', 'paymentmodeid');
		$qb->set('payment_mode', 'paymentmode');
		$qb->set('last_changed', 'lastchanged');
		$qb->set('repeat_all_active', 'repeatallactive');
		$qb->set('repeat_until', 'repeatuntil');
		$qb->set('repeat_frequency', 'repeatfreq');
		$qb->executeStatement();
	}
}
