<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2024
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Member>
 */
class MemberMapper extends QBMapper {
	public const TABLE_NAME = 'cospend_members';

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, self::TABLE_NAME, Member::class);
	}

	/**
	 * @param string $projectId
	 * @param string $name
	 * @return Member|null
	 */
	public function getMemberByName(string $projectId, string $name): ?Member {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
			);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $projectId
	 * @param string $userId
	 * @return Member|null
	 */
	public function getMemberByUserid(string $projectId, string $userId): ?Member {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $projectId
	 * @param int $memberId
	 * @return Member|null
	 */
	public function getMemberById(string $projectId, int $memberId): ?Member {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT))
			);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return null;
		}
	}

	/**
	 * @param string $projectId
	 * @param string|null $order
	 * @param int|null $lastchanged
	 * @return array
	 */
	public function getMembers(string $projectId, ?string $order = null, ?int $lastchanged = null): array {
		$qb = $this->db->getQueryBuilder();

		$sqlOrder = 'name';
		if ($order !== null) {
			if ($order === 'lowername') {
				$sqlOrder = $qb->func()->lower('name');
			} else {
				$sqlOrder = $order;
			}
		}

		$qb->select('*')
			->from(self::TABLE_NAME)
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		if ($lastchanged !== null) {
			$qb->andWhere(
				$qb->expr()->gt('lastchanged', $qb->createNamedParameter($lastchanged, IQueryBuilder::PARAM_INT))
			);
		}
		$qb->orderBy($sqlOrder, 'ASC');

		try {
			return $this->findEntities($qb);
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * Get bills involving a member (as a payer or an ower)
	 *
	 * @param int $memberId
	 * @param int|null $deleted
	 * @return array
	 * @throws Exception
	 */
	public function getBillIdsOfMember(int $memberId, ?int $deleted = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('bi.id')
			->from('cospend_bill_owers', 'bo')
			->innerJoin('bo', 'cospend_bills', 'bi', $qb->expr()->eq('bo.billid', 'bi.id'))
			->innerJoin('bo', self::TABLE_NAME, 'm', $qb->expr()->eq('bo.memberid', 'm.id'));
		$or = $qb->expr()->orx();
		$or->add($qb->expr()->eq('bi.payer_id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT)));
		$or->add($qb->expr()->eq('bo.memberid', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT)));
		$qb->where($or);
		if ($deleted !== null) {
			$qb->andWhere(
				$qb->expr()->eq('bi.deleted', $qb->createNamedParameter($deleted, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();

		$billIds = [];
		while ($row = $req->fetch()) {
			$billIds[] = $row['id'];
		}
		return $billIds;
	}
}
