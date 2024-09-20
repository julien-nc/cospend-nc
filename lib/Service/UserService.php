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

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\ProjectMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCP\IDBConnection;
use OCP\IGroupManager;

class UserService {

	public function __construct(
		private ProjectMapper $projectMapper,
		private IGroupManager $groupManager,
		private IDBConnection $dbconnection,
	) {
	}

	public function findUsers($projectid): array {
		$userIds = [];
		// get owner with mapper
		$proj = $this->projectMapper->find($projectid);
		array_push($userIds, $proj->getUserId());

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
		$qb = $this->dbconnection->getQueryBuilder();

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
		/** @var string[] $groupIds */
		$groupIds = [];
		while ($row = $req->fetch()) {
			$groupIds[] = (string)$row['userid'];
		}
		$req->closeCursor();
		// get users of groups
		foreach ($groupIds as $gid) {
			$group = $this->groupManager->get($gid);
			if ($group !== null) {
				$groupUsers = $group->getUsers();
				foreach ($groupUsers as $user) {
					$uid = $user->getUID();
					if (!in_array($uid, $userIds)) {
						$userIds[] = $uid;
					}
				}
			}
		}

		return $userIds;
	}

}
