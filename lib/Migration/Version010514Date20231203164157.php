<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010514Date20231203164157 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$qb = $this->connection->getQueryBuilder();

		$qb->update('cospend_projects')
			->set('lastchanged', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->lt('lastchanged', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $this->connection->getQueryBuilder();

		$qb->update('cospend_bills')
			->set('lastchanged', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->lt('lastchanged', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $this->connection->getQueryBuilder();

		$qb->update('cospend_bills')
			->set('timestamp', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $this->connection->getQueryBuilder();

		$qb->update('cospend_members')
			->set('lastchanged', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->lt('lastchanged', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
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
