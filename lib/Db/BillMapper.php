<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net
 * @copyright Julien Veyssier 2019
 */

 namespace OCA\Cospend\Db;

use Exception;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class BillMapper extends QBMapper {
	const TABLENAME = 'cospend_bills';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLENAME, Bill::class);
	}

	/**
	 * @param int $id
	 * @return Bill
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): Bill {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new Exception('Bill ' . $id . ' not found');
		}

		return $this->mapRowToEntity($row);
	}

	public function findProjectId(int $id): string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new Exception('Bill ' . $id . ' not found');
		}

		return $row['projectid'];
	}

	/**
	 * @param string $projectId
	 * @param string|null $what
	 * @param int|null $minTimestamp
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteBills(string $projectId, ?string $what = null, ?int $minTimestamp = null): array {
		// first delete the bill owers
		$qb = $this->db->getQueryBuilder();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('id')
			->from('cospend_bills')
			->where(
				$qb2->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($what !== null) {
			$qb2->andWhere(
				$qb2->expr()->eq('what', $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR))
			);
		}
		if ($minTimestamp !== null) {
			$qb2->andWhere(
				$qb2->expr()->gt('timestamp', $qb->createNamedParameter($minTimestamp, IQueryBuilder::PARAM_INT))
			);
		}

		$qb->delete('cospend_bill_owers')
			->where(
				$qb2->expr()->in('billid', $qb->createFunction($qb2->getSQL()), IQueryBuilder::PARAM_STR_ARRAY)
			);
		$nbBillOwersDeleted = $qb->executeStatement();
		$qb->resetQueryParts();

		///////////////////
		// delete the bills
		$qb->delete('cospend_bills')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($what !== null) {
			$qb->andWhere(
				$qb->expr()->eq('what', $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR))
			);
		}
		if ($minTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->gt('timestamp', $qb->createNamedParameter($minTimestamp, IQueryBuilder::PARAM_INT))
			);
		}
		$nbBillsDeleted = $qb->executeStatement();
		return [
			'bills' => $nbBillsDeleted,
			'billOwers' => $nbBillOwersDeleted,
		];
	}

	/**
	 * @param string $projectId
	 * @param string|null $what
	 * @param int|null $minTimestamp
	 * @return Bill[]
	 * @throws \OCP\DB\Exception
	 */
	public function getBills(string $projectId, ?string $what = null, ?int $minTimestamp = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('cospend_bills')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($what !== null) {
			$qb->andWhere(
				$qb->expr()->eq('what', $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR))
			);
		}
		if ($minTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->gt('timestamp', $qb->createNamedParameter($minTimestamp, IQueryBuilder::PARAM_INT))
			);
		}
		return $this->findEntities($qb);
	}
}
