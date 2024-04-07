<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010314Date20210828143421 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $connection;
	private IL10N $trans;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection, IL10N $l10n) {
		$this->connection = $connection;
		$this->trans = $l10n;
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

		if ($schema->hasTable('cospend_paymentmodes')) {
			$table = $schema->getTable('cospend_paymentmodes');
			if (!$table->hasColumn('old_id')) {
				$table->addColumn('old_id', Types::STRING, [
					'notnull' => false,
					'length' => 1,
					'default' => null,
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
		$pmNames = [
			$this->trans->t('Credit card'),
			$this->trans->t('Cash'),
			$this->trans->t('Check'),
			$this->trans->t('Transfer'),
			$this->trans->t('Online service'),
		];
		$defaultPaymentModes = [
			[
				'name' => $this->trans->t('Credit card'),
				'icon' => '💳',
				'color' => '#FF7F50',
				'old_id' => 'c',
				'hardcoded_id' => -1,
			],
			[
				'name' => $this->trans->t('Cash'),
				'icon' => '💵',
				'color' => '#556B2F',
				'old_id' => 'b',
				'hardcoded_id' => -2,
			],
			[
				'name' => $this->trans->t('Check'),
				'icon' => '🎫',
				'color' => '#A9A9A9',
				'old_id' => 'f',
				'hardcoded_id' => -3,
			],
			[
				'name' => $this->trans->t('Transfer'),
				'icon' => '⇄',
				'color' => '#00CED1',
				'old_id' => 't',
				'hardcoded_id' => -4,
			],
			[
				'name' => $this->trans->t('Online service'),
				'icon' => '🌎',
				'color' => '#9932CC',
				'old_id' => 'o',
				'hardcoded_id' => -5,
			],
		];

		$ts = (new \DateTime())->getTimestamp();
		$qb = $this->connection->getQueryBuilder();
		// get project ids
		$projectIdList = [];
		$qb->select('id')
			->from('cospend_projects');
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$projectIdList[] = $row['id'];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		foreach ($projectIdList as $projectId) {
			// is there at least one default payment mode already?
			$oneDefaultFound = false;
			$qb->select('name')
				->from('cospend_paymentmodes')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();

			while ($row = $req->fetch()) {
				if (in_array($row['name'], $pmNames)) {
					$oneDefaultFound = true;
					break;
				}
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();

			// if there is at least one default pm found, do not add default pms
			if (!$oneDefaultFound) {
				foreach ($defaultPaymentModes as $pm) {
					// insert new default pm
					$qb->insert('cospend_paymentmodes')
						->values([
							'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
							'encoded_icon' => $qb->createNamedParameter(urlencode($pm['icon']), IQueryBuilder::PARAM_STR),
							'color' => $qb->createNamedParameter($pm['color'], IQueryBuilder::PARAM_STR),
							'name' => $qb->createNamedParameter($pm['name'], IQueryBuilder::PARAM_STR),
							'old_id' => $qb->createNamedParameter($pm['old_id'], IQueryBuilder::PARAM_STR),
						]);
					$req = $qb->executeStatement();
					$qb = $qb->resetQueryParts();
					$insertedPmId = $qb->getLastInsertId();

					// convert pm ids in existing bills
					$qb->update('cospend_bills')
						->set('paymentmodeid', $qb->createNamedParameter($insertedPmId, IQueryBuilder::PARAM_INT))
						->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT))
						->where(
							$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('paymentmodeid', $qb->createNamedParameter($pm['hardcoded_id'], IQueryBuilder::PARAM_INT))
						);
					$qb->executeStatement();
					$qb = $qb->resetQueryParts();
				}
			}
		}
	}
}
