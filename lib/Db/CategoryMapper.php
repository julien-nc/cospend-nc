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
 * Class CategoryMapper
 *
 * @package OCA\Cospend\Db
 *
 * @method Category mapRowToEntity(array $row)
 * @method Category findEntity(IQueryBuilder $query)
 * @method Category[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Share>
 */
class CategoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_categories', Category::class);
	}

	/**
	 * @param int $id
	 * @return Category
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getById(int $id): Category {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $projectId
	 * @param int $id
	 * @return Category
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getCategoryOfProject(string $projectId, int $id): Category {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $projectId
	 * @return Category[]
	 * @throws Exception
	 */
	public function getCategoriesOfProject(string $projectId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
	}
}
