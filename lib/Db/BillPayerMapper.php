<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BillPayer>
 */
class BillPayerMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_bill_payers', BillPayer::class);
	}

	/**
	 * Get the payers of a single bill
	 *
	 * @param int $billId
	 * @return BillPayer[]
	 * @throws \OCP\DB\Exception
	 */
	public function getPayersOfBill(int $billId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('bill_id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			)
			// rows are stored in a canonical order, so read them back in it
			->orderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Get the payers of many bills at once, indexed by bill id.
	 *
	 * Bill reads are already N+1 on the owers side; this exists so the payers do
	 * not add a second per-bill query. It must NOT be folded into the bill/ower
	 * JOIN in BillMapper::getBillsClassic(): that query appends an ower row
	 * unconditionally per result row, so a third join would multiply every ower
	 * and the duplicated `owerIds` array would be persisted back on the next edit.
	 *
	 * @param list<int> $billIds
	 * @return array<int, list<BillPayer>> bill id => payers
	 * @throws \OCP\DB\Exception
	 */
	public function getPayersOfBills(array $billIds): array {
		if ($billIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('bill_id', $qb->createNamedParameter($billIds, IQueryBuilder::PARAM_INT_ARRAY))
			)
			->orderBy('id', 'ASC');

		$byBillId = [];
		foreach ($this->findEntities($qb) as $billPayer) {
			$byBillId[$billPayer->getBillId()][] = $billPayer;
		}
		return $byBillId;
	}

	/**
	 * Delete every payer row of a given bill
	 *
	 * Called on hard bill deletion and before re-inserting on edit. Soft deletion
	 * (the trash bin) must NOT call this: it only flips `cospend_bills.deleted`, so
	 * payer rows survive and a restore needs no work.
	 *
	 * @param int $billId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function deleteBillPayersOfBill(int $billId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('bill_id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			);

		return $qb->executeStatement();
	}
}
