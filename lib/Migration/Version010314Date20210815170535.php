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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010314Date20210815170535 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('cospend_paymentmodes')) {
			$table = $schema->createTable('cospend_paymentmodes');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('projectid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('color', Types::STRING, [
				'notnull' => false,
				'length' => 10,
				'default' => null
			]);
			$table->addColumn('encoded_icon', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null
			]);
			$table->addColumn('order', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
		}

		if ($schema->hasTable('cospend_bills')) {
			$table = $schema->getTable('cospend_bills');
			if (!$table->hasColumn('paymentmodeid')) {
				$table->addColumn('paymentmodeid', Types::INTEGER, [
					'notnull' => true,
					'length' => 4,
					'default' => 0,
				]);
			}
		}

		if ($schema->hasTable('cospend_projects')) {
			$table = $schema->getTable('cospend_projects');
			if (!$table->hasColumn('paymentmodesort')) {
				$table->addColumn('paymentmodesort', Types::STRING, [
					'notnull' => true,
					'length' => 1,
					'default' => 'a',
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

		$ts = (new \DateTime())->getTimestamp();

		// convert pm ids in existing bills
		$PAYMENT_MODE_ID_CONVERSION = [
			'n' => 0,
			'c' => -1,
			'b' => -2,
			'f' => -3,
			't' => -4,
			'o' => -5,
		];
		foreach ($PAYMENT_MODE_ID_CONVERSION as $old => $new) {
			$qb->update('cospend_bills')
				->set('paymentmodeid', $qb->createNamedParameter($new, IQueryBuilder::PARAM_INT))
				->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT))
				->where(
					$qb->expr()->eq('paymentmode', $qb->createNamedParameter($old, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
		}
	}
}
