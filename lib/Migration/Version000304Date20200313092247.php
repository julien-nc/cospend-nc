<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000304Date20200313092247 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_bills')) {
			$table = $schema->getTable('cospend_bills');
			if (!$table->hasColumn('timestamp')) {
				$table->addColumn('timestamp', 'bigint', [
					'notnull' => true,
					'length' => 10,
					'default' => 0
				]);
				return $schema;
			}
		}

		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$qb = $this->connection->getQueryBuilder();
		$timestamps = [];
		$qb->select('id', 'date')
		->from('cospend_bills', 'b');
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$id = $row['id'];
			$date = $row['date'];

			$timestamp = strtotime($date.' 12:00:00');
			$timestamps[$id] = $timestamp;
		}
		$req->closeCursor();
		$qb = $this->connection->getQueryBuilder();

		foreach ($timestamps as $bid => $ts) {
			$qb->update('cospend_bills')
			->set('timestamp', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($bid, IQueryBuilder::PARAM_INT))
			);
			$qb->executeStatement();
			$qb = $this->connection->getQueryBuilder();
		}
	}
}
