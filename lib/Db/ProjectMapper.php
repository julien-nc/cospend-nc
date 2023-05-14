<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Db;

use Exception;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectMapper extends QBMapper {
	const TABLENAME = 'cospend_projects';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLENAME, Project::class);
	}

	public function find(string $id): Project {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLENAME)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new Exception('Project ' . $id . ' not found');
		}

		return $this->mapRowToEntity($row);
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getProjects(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}
}
