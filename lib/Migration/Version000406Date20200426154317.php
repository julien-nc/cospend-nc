<?php

declare(strict_types=1);

namespace OCA\Cospend\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000406Date20200426154317 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $connection;
	private $trans;

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

		if ($schema->hasTable('cospend_categories')) {
			$table = $schema->getTable('cospend_categories');
			if (!$table->hasColumn('encoded_icon')) {
				$table->addColumn('encoded_icon', 'string', [
					'notnull' => false,
					'length' => 64,
					'default' => null
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

		// first, copy icon -> encoded_icon
		$categoryIconDict = [];
		$qb->select('c.id', 'c.icon')
			->from('cospend_categories', 'c');
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$categoryIconDict[$row['id']] = $row['icon'];
		}
		$req->closeCursor();
		$qb = $this->connection->getQueryBuilder();

		foreach ($categoryIconDict as $id => $icon) {
			$qb->update('cospend_categories');
			$qb->set('encoded_icon', $qb->createNamedParameter(urlencode($icon), IQueryBuilder::PARAM_STR));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
			$req = $qb->executeStatement();
			$qb = $this->connection->getQueryBuilder();
		}

		// then add default categories only if none of them is there already
		// if there was an encoding problem, they are not there,
		// if everything went fine when upgrading to v0.4.5, they are there and maybe some of them were deleted
		$categoryNames = [
			'-1' => $this->trans->t('Grocery'),
			'-2' => $this->trans->t('Bar/Party'),
			'-3' => $this->trans->t('Rent'),
			'-4' => $this->trans->t('Bill'),
			'-5' => $this->trans->t('Excursion/Culture'),
			'-6' => $this->trans->t('Health'),
			'-10' => $this->trans->t('Shopping'),
			//'-11' => $this->trans->t('Reimbursement'),
			'-12' => $this->trans->t('Restaurant'),
			'-13' => $this->trans->t('Accommodation'),
			'-14' => $this->trans->t('Transport'),
			'-15' => $this->trans->t('Sport')
		];
		$categoryNameList = array_values($categoryNames);
		$categoryEncodedIcons = [
			'-1' => urlencode('🛒'),
			'-2' => urlencode('🎉'),
			'-3' => urlencode('🏠'),
			'-4' => urlencode('🌩'),
			'-5' => urlencode('🚸'),
			'-6' => urlencode('💚'),
			'-10' => urlencode('🛍'),
			//'-11' => '💰',
			'-12' => urlencode('🍴'),
			'-13' => urlencode('🛌'),
			'-14' => urlencode('🚌'),
			'-15' => urlencode('🎾')
		];
		$categoryColors = [
			'-1' => '#ffaa00',
			'-2' => '#aa55ff',
			'-3' => '#da8733',
			'-4' => '#4aa6b0',
			'-5' => '#0055ff',
			'-6' => '#bf090c',
			'-10' => '#e167d1',
			//'-11' => '#e1d85a',
			'-12' => '#d0d5e1',
			'-13' => '#5de1a3',
			'-14' => '#6f2ee1',
			'-15' => '#69e177'
		];
		$ts = (new \DateTime())->getTimestamp();

		// get project ids
		$projectIdList = [];
		$qb->select('p.id')
			->from('cospend_projects', 'p');
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			array_push($projectIdList, $row['id']);
		}
		$req->closeCursor();
		$qb = $this->connection->getQueryBuilder();

		foreach ($projectIdList as $projectId) {
			// is there at least one default category already?
			$oneDefaultFound = false;
			$qb->select('c.name')
				->from('cospend_categories', 'c')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();

			while ($row = $req->fetch()) {
				if (in_array($row['name'], $categoryNameList)) {
					$oneDefaultFound = true;
					break;
				}
			}
			$req->closeCursor();
			$qb = $this->connection->getQueryBuilder();

			// if there is at least one default category found, do not add default categories
			if (!$oneDefaultFound) {
				foreach ($categoryNames as $strId => $name) {
					$icon = $categoryEncodedIcons[$strId];
					$color = $categoryColors[$strId];
					// insert new default category
					$qb->insert('cospend_categories')
						->values([
							'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
							'encoded_icon' => $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR),
							'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
							'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)
						]);
					$req = $qb->executeStatement();
					$insertedCategoryId = $qb->getLastInsertId();
					$qb = $this->connection->getQueryBuilder();

					// convert category ids in existing bills
					$qb->update('cospend_bills')
						->set('categoryid', $qb->createNamedParameter($insertedCategoryId, IQueryBuilder::PARAM_INT))
						->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT))
						->where(
							$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('categoryid', $qb->createNamedParameter((int)$strId, IQueryBuilder::PARAM_INT))
						);
					$qb->executeStatement();
					$qb = $this->connection->getQueryBuilder();
				}
			}
		}
	}
}
