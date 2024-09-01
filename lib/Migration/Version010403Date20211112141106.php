<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010403Date20211112141106 extends SimpleMigrationStep {

	/**
	 * @var boolean
	 */
	private $shouldCopyCategoryData;
	/**
	 * @var IDBConnection
	 */
	private $connection;
	/**
	 * @var boolean
	 */
	private $shouldCopyPaymentmodesData;

	public function __construct(IDBConnection $connection) {
		$this->shouldCopyCategoryData = false;
		$this->shouldCopyPaymentmodesData = false;
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
		// this happens if we upgrade from 1.4.2 or lower
		if (!$schema->hasTable('cospend_categories')) {
			if ($schema->hasTable('cospend_project_categories')) {
				$this->shouldCopyCategoryData = true;
			}
			$table = $schema->createTable('cospend_categories');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('projectid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 300,
				'default' => null,
			]);
			$table->addColumn('color', Types::STRING, [
				'notnull' => false,
				'length' => 10,
				'default' => null,
			]);
			$table->addColumn('encoded_icon', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null,
			]);
			$table->addColumn('order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('cospend_paymentmodes')) {
			if ($schema->hasTable('cospend_project_paymentmodes')) {
				$this->shouldCopyPaymentmodesData = true;
			}
			$table = $schema->createTable('cospend_paymentmodes');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('old_id', Types::STRING, [
				'notnull' => false,
				'length' => 1,
				'default' => null,
			]);
			$table->addColumn('projectid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 300,
				'default' => null,
			]);
			$table->addColumn('color', Types::STRING, [
				'notnull' => false,
				'length' => 10,
				'default' => null,
			]);
			$table->addColumn('encoded_icon', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null,
			]);
			$table->addColumn('order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
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
		$qb = $this->connection->getQueryBuilder();

		if ($this->shouldCopyCategoryData) {
			$categories = [];
			$qb->select('id', 'name', 'projectid', 'encoded_icon', 'color', 'order')
				->from('cospend_project_categories');
			$req = $qb->executeQuery();

			while ($row = $req->fetch()) {
				$categories[] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'projectid' => $row['projectid'],
					'encoded_icon' => $row['encoded_icon'],
					'color' => $row['color'],
					'order' => $row['order'],
				];
			}
			$req->closeCursor();
			$qb = $this->connection->getQueryBuilder();

			foreach ($categories as $cat) {
				$qb->insert('cospend_categories')
					->values([
						'id' => $qb->createNamedParameter($cat['id'], IQueryBuilder::PARAM_INT),
						'projectid' => $qb->createNamedParameter($cat['projectid'], IQueryBuilder::PARAM_STR),
						'encoded_icon' => $qb->createNamedParameter($cat['encoded_icon'], IQueryBuilder::PARAM_STR),
						'color' => $qb->createNamedParameter($cat['color'], IQueryBuilder::PARAM_STR),
						'name' => $qb->createNamedParameter($cat['name'], IQueryBuilder::PARAM_STR),
						'order' => $qb->createNamedParameter($cat['order'], IQueryBuilder::PARAM_INT),
					]);
				$qb->executeStatement();
				$qb = $this->connection->getQueryBuilder();
			}
		}

		if ($this->shouldCopyPaymentmodesData) {
			$pms = [];
			$qb->select('id', 'old_id', 'name', 'projectid', 'encoded_icon', 'color', 'order')
				->from('cospend_project_paymentmodes');
			$req = $qb->executeQuery();

			while ($row = $req->fetch()) {
				$pms[] = [
					'id' => $row['id'],
					'old_id' => $row['old_id'],
					'name' => $row['name'],
					'projectid' => $row['projectid'],
					'encoded_icon' => $row['encoded_icon'],
					'color' => $row['color'],
					'order' => $row['order'],
				];
			}
			$req->closeCursor();
			$qb = $this->connection->getQueryBuilder();

			foreach ($pms as $pm) {
				$qb->insert('cospend_paymentmodes')
					->values([
						'id' => $qb->createNamedParameter($pm['id'], IQueryBuilder::PARAM_INT),
						'old_id' => $qb->createNamedParameter($pm['old_id'], IQueryBuilder::PARAM_STR),
						'projectid' => $qb->createNamedParameter($pm['projectid'], IQueryBuilder::PARAM_STR),
						'encoded_icon' => $qb->createNamedParameter($pm['encoded_icon'], IQueryBuilder::PARAM_STR),
						'color' => $qb->createNamedParameter($pm['color'], IQueryBuilder::PARAM_STR),
						'name' => $qb->createNamedParameter($pm['name'], IQueryBuilder::PARAM_STR),
						'order' => $qb->createNamedParameter($pm['order'], IQueryBuilder::PARAM_INT),
					]);
				$qb->executeStatement();
				$qb = $this->connection->getQueryBuilder();
			}
		}
	}
}
