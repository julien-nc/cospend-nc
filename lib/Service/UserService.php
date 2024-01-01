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

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\ProjectMapper;

class UserService {

	public function __construct(
		private ProjectMapper $projectMapper,
		private IGroupManager $groupManager,
		private IDBConnection $dbconnection
	) {
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
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
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
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
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
