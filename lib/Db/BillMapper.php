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

use DateTime;
use Exception;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @psalm-import-type CospendBill from ResponseDefinitions
 * @extends QBMapper<Bill>
 */
class BillMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_bills', Bill::class);
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

	public function deleteDeletedBills(string $projectId): void {
		// first delete the bill owers
		$qb = $this->db->getQueryBuilder();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('id')
			->from($this->getTableName())
			->where(
				$qb2->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			);

		$qb->delete('cospend_bill_owers')
			->where(
				$qb2->expr()->in('bill_id', $qb->createFunction($qb2->getSQL()), IQueryBuilder::PARAM_STR_ARRAY)
			);
		$qb->executeStatement();

		// delete the bills
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
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
			->from($this->getTableName())
			->where(
				$qb2->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
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
				$qb2->expr()->in('bill_id', $qb->createFunction($qb2->getSQL()), IQueryBuilder::PARAM_STR_ARRAY)
			);
		$nbBillOwersDeleted = $qb->executeStatement();
		$qb = $this->db->getQueryBuilder();

		///////////////////
		// delete the bills
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
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
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function moveBillsToTrash(string $projectId, ?string $what = null, ?int $minTimestamp = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('deleted', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
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
		];
	}

	/**
	 * @param string $projectId
	 * @param string|null $what
	 * @param int|null $minTimestamp
	 * @return Bill[]
	 * @throws \OCP\DB\Exception
	 */
	public function getBillsToDelete(string $projectId, ?string $what = null, ?int $minTimestamp = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
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

	/**
	 * @param string $projectId
	 * @param int $billId
	 * @return Bill|null
	 */
	public function getBillEntity(string $projectId, int $billId): ?Bill {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			return null;
		}
	}

	/**
	 * Get bill info
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return array|null
	 */
	public function getBill(string $projectId, int $billId): ?array {
		$dbBbill = $this->getBillEntity($projectId, $billId);
		if ($dbBbill === null) {
			return null;
		}
		// get bill owers
		$billOwers = [];
		$billOwerIds = [];

		$qb = $this->db->getQueryBuilder();

		$qb->select('bo.member_id', 'm.name', 'm.weight', 'm.activated')
			->from('cospend_bill_owers', 'bo')
			->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.member_id', 'm.id'))
			->where(
				$qb->expr()->eq('bo.bill_id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbWeight = (float)$row['weight'];
			$dbName = $row['name'];
			$dbActivated = (((int)$row['activated']) === 1);
			$dbOwerId = (int)$row['member_id'];
			$billOwers[] = [
				'id' => $dbOwerId,
				'weight' => $dbWeight,
				'name' => $dbName,
				'activated' => $dbActivated,
			];
			$billOwerIds[] = $dbOwerId;
		}
		$req->closeCursor();

		// get the bill
		$bill = $dbBbill->jsonSerialize();
		$bill['owers'] = $billOwers;
		$bill['owerIds'] = $billOwerIds;
		return $bill;
	}

	/**
	 * @param int|null $billId
	 * @return Bill[]
	 * @throws \OCP\DB\Exception
	 */
	public function getBillsToRepeat(?int $billId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->neq('repeat', $qb->createNamedParameter(Application::FREQUENCY_NO, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		// we only repeat one bill
		if (!is_null($billId)) {
			$qb->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Get filtered list of bills for a project
	 *
	 * @param string $projectId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param int|null $lastchanged
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $payerId
	 * @param int|null $deleted
	 * @return list<CospendBill>
	 * @throws \OCP\DB\Exception
	 */
	public function getBillsClassic(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $category = null, ?float $amountMin = null, ?float $amountMax = null,
		?int $lastchanged = null, ?int $limit = null,
		bool $reverse = false, ?int $payerId = null, ?int $deleted = 0,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('bi.id', 'what', 'comment', 'timestamp', 'amount', 'payer_id', 'repeat',
			'payment_mode', 'payment_mode_id', 'category_id', 'bi.last_changed', 'repeat_all_active', 'repeat_until', 'repeat_frequency',
			'deleted', 'bo.member_id', 'm.name', 'm.weight', 'm.activated')
			->from('cospend_bill_owers', 'bo')
			->innerJoin('bo', 'cospend_bills', 'bi', $qb->expr()->eq('bo.bill_id', 'bi.id'))
			->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.member_id', 'm.id'))
			->where(
				$qb->expr()->eq('bi.project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		// take bills that have changed after $lastchanged
		if ($lastchanged !== null) {
			$qb->andWhere(
				$qb->expr()->gt('bi.last_changed', $qb->createNamedParameter($lastchanged, IQueryBuilder::PARAM_INT))
			);
		}
		if ($payerId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('bi.payer_id', $qb->createNamedParameter($payerId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($tsMin !== null) {
			$qb->andWhere(
				$qb->expr()->gte('timestamp', $qb->createNamedParameter($tsMin, IQueryBuilder::PARAM_INT))
			);
		}
		if ($tsMax !== null) {
			$qb->andWhere(
				$qb->expr()->lte('timestamp', $qb->createNamedParameter($tsMax, IQueryBuilder::PARAM_INT))
			);
		}
		if ($deleted !== null) {
			$qb->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter($deleted, IQueryBuilder::PARAM_INT))
			);
		}
		if ($paymentMode !== null && $paymentMode !== '' && $paymentMode !== 'n') {
			$qb->andWhere(
				$qb->expr()->eq('payment_mode', $qb->createNamedParameter($paymentMode, IQueryBuilder::PARAM_STR))
			);
		} elseif (!is_null($paymentModeId)) {
			$qb->andWhere(
				$qb->expr()->eq('payment_mode_id', $qb->createNamedParameter($paymentModeId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($category !== null) {
			if ($category === -100) {
				$or = $qb->expr()->orx();
				$or->add($qb->expr()->isNull('category_id'));
				$or->add($qb->expr()->neq('category_id', $qb->createNamedParameter(Application::CATEGORY_REIMBURSEMENT, IQueryBuilder::PARAM_INT)));
				$qb->andWhere($or);
			} else {
				$qb->andWhere(
					$qb->expr()->eq('category_id', $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT))
				);
			}
		}
		if ($amountMin !== null) {
			$qb->andWhere(
				$qb->expr()->gte('amount', $qb->createNamedParameter($amountMin, IQueryBuilder::PARAM_STR))
			);
		}
		if ($amountMax !== null) {
			$qb->andWhere(
				$qb->expr()->lte('amount', $qb->createNamedParameter($amountMax, IQueryBuilder::PARAM_STR))
			);
		}
		if ($reverse) {
			$qb->orderBy('timestamp', 'DESC');
		} else {
			$qb->orderBy('timestamp', 'ASC');
		}
		if ($limit) {
			$qb->setMaxResults($limit);
		}
		$req = $qb->executeQuery();

		// bills by id
		/** @var array<string, CospendBill> $billDict */
		$billDict = [];
		// ordered list of bill ids
		$orderedBillIds = [];
		while ($row = $req->fetch()) {
			$dbBillId = (int)$row['id'];
			// if first time we see the bill : add it to bill list
			if (!isset($billDict[$dbBillId])) {
				$billDict[$dbBillId] = $this->getBillFromRow($row);
				// keep order of bills
				$orderedBillIds[] = $dbBillId;
			}
			// anyway add an ower
			$dbWeight = (float)$row['weight'];
			$dbName = $row['name'];
			$dbActivated = ((int)$row['activated']) === 1;
			$dbOwerId = (int)$row['member_id'];
			$billDict[$dbBillId]['owers'][] = [
				'id' => $dbOwerId,
				'weight' => $dbWeight,
				'name' => $dbName,
				'activated' => $dbActivated,
			];
			$billDict[$dbBillId]['owerIds'][] = $dbOwerId;
		}
		$req->closeCursor();

		/** @var list<CospendBill> $resultBills */
		$resultBills = [];
		foreach ($orderedBillIds as $bid) {
			$resultBills[] = $billDict[$bid];
		}

		return $resultBills;
	}

	/**
	 * Get filtered list of bills for a project
	 *
	 * @param string $projectId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param int|null $lastchanged
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $offset
	 * @param int|null $payerId
	 * @param int|null $includeBillId
	 * @param string|null $searchTerm
	 * @param int|null $deleted
	 * @return list<CospendBill>
	 * @throws \OCP\DB\Exception
	 */
	public function getBillsWithLimit(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $category = null, ?float $amountMin = null, ?float $amountMax = null,
		?int $lastchanged = null, ?int $limit = null,
		bool $reverse = false, ?int $offset = 0, ?int $payerId = null,
		?int $includeBillId = null, ?string $searchTerm = null, ?int $deleted = 0,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName(), 'bi')
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		// take bills that have changed after $lastchanged
		if ($lastchanged !== null) {
			$qb->andWhere(
				$qb->expr()->gt('last_changed', $qb->createNamedParameter($lastchanged, IQueryBuilder::PARAM_INT))
			);
		}
		if ($payerId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('payer_id', $qb->createNamedParameter($payerId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($tsMin !== null) {
			$qb->andWhere(
				$qb->expr()->gte('timestamp', $qb->createNamedParameter($tsMin, IQueryBuilder::PARAM_INT))
			);
		}
		if ($tsMax !== null) {
			$qb->andWhere(
				$qb->expr()->lte('timestamp', $qb->createNamedParameter($tsMax, IQueryBuilder::PARAM_INT))
			);
		}
		if ($deleted !== null) {
			$qb->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter($deleted, IQueryBuilder::PARAM_INT))
			);
		}
		if ($paymentMode !== null && $paymentMode !== '' && $paymentMode !== 'n') {
			$qb->andWhere(
				$qb->expr()->eq('payment_mode', $qb->createNamedParameter($paymentMode, IQueryBuilder::PARAM_STR))
			);
		} elseif (!is_null($paymentModeId)) {
			$qb->andWhere(
				$qb->expr()->eq('payment_mode_id', $qb->createNamedParameter($paymentModeId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($category !== null) {
			if ($category === -100) {
				$or = $qb->expr()->orx();
				$or->add($qb->expr()->isNull('category_id'));
				$or->add($qb->expr()->neq('category_id', $qb->createNamedParameter(Application::CATEGORY_REIMBURSEMENT, IQueryBuilder::PARAM_INT)));
				$qb->andWhere($or);
			} else {
				$qb->andWhere(
					$qb->expr()->eq('category_id', $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT))
				);
			}
		}
		if ($amountMin !== null) {
			$qb->andWhere(
				$qb->expr()->gte('amount', $qb->createNamedParameter($amountMin, IQueryBuilder::PARAM_STR))
			);
		}
		if ($amountMax !== null) {
			$qb->andWhere(
				$qb->expr()->lte('amount', $qb->createNamedParameter($amountMax, IQueryBuilder::PARAM_STR))
			);
		}
		// handle the search term (what, comment, amount+-1)
		if ($searchTerm !== null && $searchTerm !== '') {
			$qb = $this->applyBillSearchTermCondition($qb, $searchTerm, 'bi');
		}
		if ($reverse) {
			$qb->orderBy('timestamp', 'DESC');
		} else {
			$qb->orderBy('timestamp', 'ASC');
		}
		if ($limit) {
			$qb->setMaxResults($limit);
		}
		if ($offset) {
			$qb->setFirstResult($offset);
		}

		$billEntities = $this->findEntities($qb);
		$includeBillFound = false;
		foreach ($billEntities as $bill) {
			if ($bill->getId() === $includeBillId) {
				$includeBillFound = true;
				break;
			}
		}
		$bills = array_map(static function (Bill $bill) {
			return $bill->jsonSerialize();
		}, $billEntities);

		// look further if we want to include a specific bill
		if ($includeBillId !== null && $includeBillFound === false && $limit && $offset === 0) {
			$lastResultCount = count($bills);
			while ($lastResultCount > 0 && $includeBillFound === false) {
				$offset = $offset + $limit;
				$qb->setFirstResult($offset);
				$billEntities = $this->findEntities($qb);
				$lastResultCount = count($billEntities);

				foreach ($billEntities as $bill) {
					if ($bill->getId() === $includeBillId) {
						$includeBillFound = true;
						break;
					}
				}
				$moreBills = array_map(static function (Bill $bill) {
					return $bill->jsonSerialize();
				}, $billEntities);
				$bills = array_merge($bills, $moreBills);
			}
		}

		$qb = $this->db->getQueryBuilder();

		// get owers
		foreach ($bills as $i => $bill) {
			$billId = $bill['id'];
			$billOwers = [];
			$billOwerIds = [];

			$qb->select('bo.member_id', 'm.name', 'm.weight', 'm.activated')
				->from('cospend_bill_owers', 'bo')
				->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.member_id', 'm.id'))
				->where(
					$qb->expr()->eq('bo.bill_id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
				);
			$qb->setFirstResult(0);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbWeight = (float)$row['weight'];
				$dbName = $row['name'];
				$dbActivated = ((int)$row['activated']) === 1;
				$dbOwerId = (int)$row['member_id'];
				$billOwers[] = [
					'id' => $dbOwerId,
					'weight' => $dbWeight,
					'name' => $dbName,
					'activated' => $dbActivated,
				];
				$billOwerIds[] = $dbOwerId;
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();
			$bills[$i]['owers'] = $billOwers;
			$bills[$i]['owerIds'] = $billOwerIds;
		}

		/** @var list<CospendBill> $bills */
		return $bills;
	}

	private function getBillFromRow(array $row): array {
		$dbBillId = (int)$row['id'];
		$dbAmount = (float)$row['amount'];
		$dbWhat = $row['what'];
		$dbComment = $row['comment'];
		$dbTimestamp = (int)$row['timestamp'];
		$dbDate = DateTime::createFromFormat('U', $row['timestamp']);
		$dbRepeat = $row['repeat'];
		$dbPayerId = (int)$row['payer_id'];
		$dbPaymentMode = $row['payment_mode'];
		$dbPaymentModeId = (int)$row['payment_mode_id'];
		$dbCategoryId = (int)$row['category_id'];
		$dbLastchanged = (int)$row['last_changed'];
		$dbRepeatAllActive = (int)$row['repeat_all_active'];
		$dbRepeatUntil = $row['repeat_until'];
		$dbRepeatFreq = (int)$row['repeat_frequency'];
		$dbDeleted = (int)$row['deleted'];
		return [
			'id' => $dbBillId,
			'amount' => $dbAmount,
			'what' => $dbWhat,
			'comment' => $dbComment ?? '',
			'timestamp' => $dbTimestamp,
			'date' => $dbDate->format('Y-m-d'),
			'payer_id' => $dbPayerId,
			'owers' => [],
			'owerIds' => [],
			'repeat' => $dbRepeat,
			'paymentmode' => $dbPaymentMode,
			'paymentmodeid' => $dbPaymentModeId,
			'categoryid' => $dbCategoryId,
			'lastchanged' => $dbLastchanged,
			'repeatallactive' => $dbRepeatAllActive,
			'repeatuntil' => $dbRepeatUntil,
			'repeatfreq' => $dbRepeatFreq,
			'deleted' => $dbDeleted,
		];
	}

	private function applyBillSearchTermCondition(IQueryBuilder $qb, string $term, string $billTableAlias): IQueryBuilder {
		$term = strtolower($term);
		$or = $qb->expr()->orx();
		$or->add(
			$qb->expr()->iLike($billTableAlias . '.what', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%', IQueryBuilder::PARAM_STR))
		);
		$or->add(
			$qb->expr()->iLike($billTableAlias . '.comment', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%', IQueryBuilder::PARAM_STR))
		);
		// search amount
		$noCommaTerm = str_replace(',', '.', $term);
		if (is_numeric($noCommaTerm)) {
			$amount = (float)$noCommaTerm;
			$amountMin = $amount - 1.0;
			$amountMax = $amount + 1.0;
			$andExpr = $qb->expr()->andX();
			$andExpr->add(
				$qb->expr()->gte($billTableAlias . '.amount', $qb->createNamedParameter($amountMin, IQueryBuilder::PARAM_STR))
			);
			$andExpr->add(
				$qb->expr()->lte($billTableAlias . '.amount', $qb->createNamedParameter($amountMax, IQueryBuilder::PARAM_STR))
			);
			$or->add($andExpr);
		}
		$qb->andWhere($or);
		return $qb;
	}

	/**
	 * Search bills with query string
	 *
	 * @param string $projectId
	 * @param string $term
	 * @return array
	 */
	public function searchBills(string $projectId, string $term, ?int $deleted = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'b.id', 'what', 'comment', 'amount', 'timestamp',
			'payment_mode', 'payment_mode_id', 'category_id',
			'pr.currency_name', 'me.name', 'me.user_id'
		)
			->from($this->getTableName(), 'b')
			->innerJoin('b', 'cospend_projects', 'pr', $qb->expr()->eq('b.project_id', 'pr.id'))
			->innerJoin('b', 'cospend_members', 'me', $qb->expr()->eq('b.payer_id', 'me.id'))
			->where(
				$qb->expr()->eq('b.project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($deleted !== null) {
			$qb->andWhere(
				$qb->expr()->eq('b.deleted', $qb->createNamedParameter($deleted, IQueryBuilder::PARAM_INT))
			);
		}
		$qb = $this->applyBillSearchTermCondition($qb, $term, 'b');
		$qb->orderBy('timestamp', 'ASC');
		$req = $qb->executeQuery();

		// bills by id
		$bills = [];
		while ($row = $req->fetch()) {
			$dbBillId = (int)$row['id'];
			$dbAmount = (float)$row['amount'];
			$dbWhat = $row['what'];
			$dbTimestamp = (int)$row['timestamp'];
			$dbComment = $row['comment'];
			$dbPaymentMode = $row['payment_mode'];
			$dbPaymentModeId = (int)$row['payment_mode_id'];
			$dbCategoryId = (int)$row['category_id'];
			$dbProjectCurrencyName = $row['currency_name'];
			$dbPayerName = $row['name'];
			$dbPayerUserId = $row['user_id'];
			$bills[] = [
				'id' => $dbBillId,
				'projectId' => $projectId,
				'amount' => $dbAmount,
				'what' => $dbWhat,
				'timestamp' => $dbTimestamp,
				'comment' => $dbComment,
				'paymentmode' => $dbPaymentMode,
				'paymentmodeid' => $dbPaymentModeId,
				'categoryid' => $dbCategoryId,
				'currencyname' => $dbProjectCurrencyName,
				'payer_name' => $dbPayerName,
				'payer_user_id' => $dbPayerUserId,
			];
		}
		$req->closeCursor();

		return $bills;
	}

	/**
	 * Get number of bills in a project
	 *
	 * @param string $projectId
	 * @param int|null $payerId
	 * @param int|null $categoryId
	 * @param int|null $paymentModeId
	 * @param int|null $deleted
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function countBills(string $projectId, ?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $deleted = 0): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_bills')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($deleted !== null) {
			$qb->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter($deleted, IQueryBuilder::PARAM_INT))
			);
		}
		if ($payerId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('payer_id', $qb->createNamedParameter($payerId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($categoryId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($paymentModeId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('payment_mode_id', $qb->createNamedParameter($paymentModeId, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			return (int)$row['count_bills'];
		}
		return 0;
	}

	/**
	 * Get all bill IDs of a project
	 *
	 * @param string $projectId
	 * @param int|null $deleted
	 * @return list<int>
	 * @throws \OCP\DB\Exception
	 */
	public function getAllBillIds(string $projectId, ?int $deleted = 0): array {
		$billIds = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName(), 'b')
			->where(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($deleted !== null) {
			$qb->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter($deleted, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$billIds[] = (int)$row['id'];
		}
		$req->closeCursor();

		return $billIds;
	}

	/**
	 * @param string $projectId
	 * @param int $pmId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function removePaymentModeInProject(string $projectId, int $pmId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName());
		$qb->set('payment_mode_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('payment_mode_id', $qb->createNamedParameter($pmId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		return $qb->executeStatement();
	}

	/**
	 * @param string $projectId
	 * @param int $categoryId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function removeCategoryInProject(string $projectId, int $categoryId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName());
		$qb->set('category_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		return $qb->executeStatement();
	}
}
