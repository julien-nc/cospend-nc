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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BillOwer>
 */
class BillOwerMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_bill_owers', BillOwer::class);
	}

	/**
	 * @param int $id
	 * @return BillOwer
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function getById(int $id): BillOwer {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * Delete bill owers of given bill
	 *
	 * @param int $billId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function deleteBillOwersOfBill(int $billId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('bill_id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			);
		$nbDeleted = $qb->executeStatement();
		return $nbDeleted;
	}

	/**
	 * @param int $billId
	 * @return BillOwer[]
	 * @throws \OCP\DB\Exception
	 */
	public function getOwersOfBill(int $billId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('bill_id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
	}
}
