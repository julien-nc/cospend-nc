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
class Version000403Date20200231152118 extends SimpleMigrationStep {

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

		if ($schema->hasTable('cospend_projects')) {
			$table = $schema->getTable('cospend_projects');
			if (!$table->hasColumn('guestaccesslevel')) {
				$table->addColumn('guestaccesslevel', 'integer', [
					'notnull' => true,
					'length' => 4,
					'default' => 2
				]);
			}
		}

		if ($schema->hasTable('cospend_shares')) {
			$table = $schema->getTable('cospend_shares');
			if (!$table->hasColumn('accesslevel')) {
				$table->addColumn('accesslevel', 'integer', [
					'notnull' => true,
					'length' => 4,
					'default' => 2
				]);
			}
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

		// permissions were c e d => v p m a for viewer participant maintener admin
		// user share permissions
		$qb->update('cospend_shares')
			->set('accesslevel', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->neq('permissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb = $this->connection->getQueryBuilder();

		$qb->update('cospend_shares')
			->set('accesslevel', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('permissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb = $this->connection->getQueryBuilder();

		// guest permissions
		$qb->update('cospend_projects')
			->set('guestaccesslevel', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->neq('guestpermissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb = $this->connection->getQueryBuilder();

		$qb->update('cospend_projects')
			->set('guestaccesslevel', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('guestpermissions', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}
}
