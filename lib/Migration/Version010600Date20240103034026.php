<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010600Date20240103034026 extends SimpleMigrationStep {

	public function __construct(private IDBConnection $connection) {
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
			// delete password and guestaccesslevel columns
			if ($table->hasColumn('password')) {
				$table->dropColumn('password');
				$schemaChanged = true;
			}
			if ($table->hasColumn('guestaccesslevel')) {
				$table->dropColumn('guestaccesslevel');
				$schemaChanged = true;
			}
			/*
			// rename columns
			if (!$table->hasColumn('user_id')) {
				$table->addColumn('user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('currency_name')) {
				$table->addColumn('currency_name', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('deletion_disabled')) {
				$table->addColumn('deletion_disabled', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('category_sort')) {
				$table->addColumn('category_sort', Types::STRING, [
					'notnull' => true,
					'length' => 1,
					'default' => 'a',
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('payment_mode_sort')) {
				$table->addColumn('payment_mode_sort', Types::STRING, [
					'notnull' => true,
					'length' => 1,
					'default' => 'a',
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('auto_export')) {
				$table->addColumn('auto_export', Types::STRING, [
					'notnull' => true,
					'length' => 1,
					'default' => 'n',
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
			*/
		}

		return $schemaChanged ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		/*
		$qb = $this->connection->getQueryBuilder();
		$qb->update('cospend_projects');
		$qb->set('user_id', 'userid');
		$qb->set('category_sort', 'categorysort');
		$qb->set('payment_mode_sort', 'paymentmodesort');
		$qb->set('currency_name', 'currencyname');
		$qb->set('deletion_disabled', 'deletiondisabled');
		$qb->set('auto_export', 'autoexport');
		$qb->set('last_changed', 'lastchanged');
		$qb->executeStatement();
		*/
	}
}
