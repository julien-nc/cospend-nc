<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class PaymentModeMapper
 *
 * @package OCA\Cospend\Db
 *
 * @method PaymentMode mapRowToEntity(array $row)
 * @method PaymentMode findEntity(IQueryBuilder $query)
 * @method PaymentMode[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<PaymentMode>
 */
class PaymentModeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_paymentmodes', PaymentMode::class);
	}

	/**
	 * @param int $id
	 * @return PaymentMode
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getById(int $id): PaymentMode {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $projectId
	 * @param int $id
	 * @return PaymentMode
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getPaymentModeOfProject(string $projectId, int $id): PaymentMode {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $projectId
	 * @return PaymentMode[]
	 * @throws Exception
	 */
	public function getPaymentModesOfProject(string $projectId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
	}
}
