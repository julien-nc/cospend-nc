<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<AutoCategoryMapping>
 */
class AutoCategoryMappingMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cospend_auto_category_mappings', AutoCategoryMapping::class);
	}

	/**
	 * Get a mapping by ID
	 *
	 * @param int $id
	 * @return AutoCategoryMapping|null
	 * @throws Exception
	 */
	public function getById(int $id): ?AutoCategoryMapping {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * Get all mappings for a project
	 *
	 * @param string $projectId
	 * @return AutoCategoryMapping[]
	 * @throws Exception
	 */
	public function getMappings(string $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'project_id', 'bill_title', 'category_id', 'last_changed', 'created_at')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
	}

	/**
	 * Find a mapping by project and bill title (case-insensitive)
	 *
	 * @param string $projectId
	 * @param string $billTitle
	 * @return AutoCategoryMapping|null
	 * @throws Exception
	 */
	public function findMapping(string $projectId, string $billTitle): ?AutoCategoryMapping {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'project_id', 'bill_title', 'category_id', 'last_changed', 'created_at')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq(
				$qb->createFunction('LOWER(' . $qb->getColumnName('bill_title') . ')'),
				$qb->createNamedParameter(strtolower($billTitle), IQueryBuilder::PARAM_STR)
			))
			// case-variant duplicates are rejected on creation but might predate that check,
			// prefer the most recently changed one
			->orderBy('last_changed', 'DESC')
			->setMaxResults(1);

		$mappings = $this->findEntities($qb);
		return $mappings[0] ?? null;
	}

	/**
	 * Nullify category_id on mappings that reference a deleted category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return void
	 * @throws Exception
	 */
	public function nullifyCategoryId(string $projectId, int $categoryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('category_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_INT))
			->set('last_changed', $qb->createNamedParameter((new DateTime())->getTimestamp(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
