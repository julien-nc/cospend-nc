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

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCP\IGroupManager;

use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IDBConnection;

use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\BillMapper;

class UserService {
	private $dbconnection;

	public function __construct (LoggerInterface $logger,
								IL10N $l10n,
								ProjectMapper $projectMapper,
								BillMapper $billMapper,
								IManager $shareManager,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IDBConnection $dbconnection) {
		$this->trans = $l10n;
		$this->logger = $logger;
		$this->dbconnection = $dbconnection;
		$this->qb = $dbconnection->getQueryBuilder();
		$this->projectMapper = $projectMapper;
		$this->billMapper = $billMapper;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
	}

	public function findUsers($projectid) {
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
		$qb = $qb->resetQueryParts();
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
