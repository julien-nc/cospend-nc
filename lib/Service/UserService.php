<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IGroupManager;
use OCP\IDBConnection;

use OCA\Cospend\Db\ProjectMapper;

class UserService {

	/**
	 * @var ProjectMapper
	 */
	private $projectMapper;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;
	/**
	 * @var IDBConnection
	 */
	private $dbconnection;

	public function __construct (ProjectMapper $projectMapper,
								IGroupManager $groupManager,
								IDBConnection $dbconnection) {
		$this->projectMapper = $projectMapper;
		$this->groupManager = $groupManager;
		$this->dbconnection = $dbconnection;
	}

	public function findUsers($projectid): array {
		$userIds = [];
		// get owner with mapper
		$proj = $this->projectMapper->find($projectid);
		array_push($userIds, $proj->getUserid());

		// get user shares from project id
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('userid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			if (!in_array($row['userid'], $userIds)) {
				array_push($userIds, $row['userid']);
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// get group shares from project id
		$qb->select('userid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		$groupIds = [];
		while ($row = $req->fetch()) {
			array_push($groupIds, $row['userid']);
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		// get users of groups
		foreach ($groupIds as $gid) {
			$group = $this->groupManager->get($gid);
			if ($group !== null) {
				$groupUsers = $group->getUsers();
				foreach ($groupUsers as $user) {
					$uid = $user->getUID();
					if (!in_array($uid, $userIds)) {
						array_push($userIds, $uid);
					}
				}
			}
		}

		return $userIds;
	}

}
