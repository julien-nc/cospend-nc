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

use OCP\Files\FileInfo;
use OCP\IL10N;
use OCP\IConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IAvatarManager;
use OCP\Notification\IManager as INotificationManager;

use OCP\IUserManager;
use OCP\IDBConnection;
use OCP\IDateTimeZone;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

use DateTimeImmutable;
use DateInterval;
use DateTime;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\BillMapper;
use function str_replace;

require_once __DIR__ . '/../utils.php';

class ProjectService {

	/**
	 * @var IL10N
	 */
	private $trans;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var ProjectMapper
	 */
	private $projectMapper;
	/**
	 * @var BillMapper
	 */
	private $billMapper;
	/**
	 * @var ActivityManager
	 */
	private $activityManager;
	/**
	 * @var IAvatarManager
	 */
	private $avatarManager;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;
	/**
	 * @var IDateTimeZone
	 */
	private $dateTimeZone;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var INotificationManager
	 */
	private $notificationManager;
	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var array
	 */
	private $defaultCategoryNames;
	/**
	 * @var string[]
	 */
	private $defaultCategoryIcons;
	/**
	 * @var string[]
	 */
	private $defaultCategoryColors;
	/**
	 * @var array
	 */
	private $hardCodedCategoryNames;

	public function __construct (IL10N $trans,
								IConfig $config,
								ProjectMapper $projectMapper,
								BillMapper $billMapper,
								ActivityManager $activityManager,
								IAvatarManager $avatarManager,
								IUserManager $userManager,
								IAppManager $appManager,
								IGroupManager $groupManager,
								IDateTimeZone $dateTimeZone,
								IRootFolder $root,
								INotificationManager $notificationManager,
								IDBConnection $db) {
		$this->trans = $trans;
		$this->config = $config;
		$this->projectMapper = $projectMapper;
		$this->billMapper = $billMapper;
		$this->activityManager = $activityManager;
		$this->avatarManager = $avatarManager;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
		$this->groupManager = $groupManager;
		$this->dateTimeZone = $dateTimeZone;
		$this->root = $root;
		$this->notificationManager = $notificationManager;
		$this->db = $db;

		$this->defaultCategoryNames = [
			'-1' => $this->trans->t('Grocery'),
			'-2' => $this->trans->t('Bar/Party'),
			'-3' => $this->trans->t('Rent'),
			'-4' => $this->trans->t('Bill'),
			'-5' => $this->trans->t('Excursion/Culture'),
			'-6' => $this->trans->t('Health'),
			'-10' => $this->trans->t('Shopping'),
			'-12' => $this->trans->t('Restaurant'),
			'-13' => $this->trans->t('Accommodation'),
			'-14' => $this->trans->t('Transport'),
			'-15' => $this->trans->t('Sport')
		];
		$this->defaultCategoryIcons = [
			'-1'  => '🛒',
			'-2'  => '🎉',
			'-3'  => '🏠',
			'-4'  => '🌩',
			'-5'  => '🚸',
			'-6'  => '💚',
			'-10' => '🛍',
			'-12' => '🍴',
			'-13' => '🛌',
			'-14' => '🚌',
			'-15' => '🎾'
		];
		$this->defaultCategoryColors = [
			'-1'  => '#ffaa00',
			'-2'  => '#aa55ff',
			'-3'  => '#da8733',
			'-4'  => '#4aa6b0',
			'-5'  => '#0055ff',
			'-6'  => '#bf090c',
			'-10' => '#e167d1',
			'-12' => '#d0d5e1',
			'-13' => '#5de1a3',
			'-14' => '#6f2ee1',
			'-15' => '#69e177'
		];

		$this->hardCodedCategoryNames = [
			'-11' => $this->trans->t('Reimbursement'),
		];
	}

	/**
	 * check if user owns the project
	 * or if the project is shared with the user
	 *
	 * @param string $userid
	 * @param string $projectid
	 * @return bool
	 */
	public function userCanAccessProject(string $userid, string $projectid): bool {
		$projectInfo = $this->getProjectInfo($projectid);
		if ($projectInfo !== null) {
			// does the user own the project ?
			if ($projectInfo['userid'] === $userid) {
				return true;
			} else {
				$qb = $this->db->getQueryBuilder();
				// is the project shared with the user ?
				$qb->select('userid', 'projectid')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbProjectId = null;
				while ($row = $req->fetch()) {
					$dbProjectId = $row['projectid'];
					break;
				}
				$req->closeCursor();
				$qb = $qb->resetQueryParts();

				if ($dbProjectId !== null) {
					return true;
				} else {
					// if not, is the project shared with a group containing the user?
					$userO = $this->userManager->get($userid);
					$accessWithGroup = null;

					$qb->select('userid')
						->from('cospend_shares', 's')
						->where(
							$qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->executeQuery();
					while ($row = $req->fetch()) {
						$groupId = $row['userid'];
						if ($this->groupManager->groupExists($groupId) && $this->groupManager->get($groupId)->inGroup($userO)) {
							$accessWithGroup = $groupId;
							break;
						}
					}
					$req->closeCursor();
					$qb = $qb->resetQueryParts();

					if ($accessWithGroup !== null) {
						return true;
					} else {
						// if not, are circles enabled and is the project shared with a circle containing the user?
						$circlesEnabled = $this->appManager->isEnabledForUser('circles');
						if ($circlesEnabled) {
							$qb->select('userid')
								->from('cospend_shares', 's')
								->where(
									$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
								)
								->andWhere(
									$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
								);
							$req = $qb->executeQuery();
							while ($row = $req->fetch()) {
								$circleId = $row['userid'];
								if ($this->isUserInCircle($userid, $circleId)) {
									return true;
								}
							}
						}
						return false;
					}

				}
			}
		} else {
			return false;
		}
	}

	/**
	 * Get max access level of a given user for a given project
	 *
	 * @param string $userid
	 * @param string $projectid
	 * @return int
	 */
	public function getUserMaxAccessLevel(string $userid, string $projectid): int {
		$result = 0;
		$projectInfo = $this->getProjectInfo($projectid);
		if ($projectInfo !== null) {
			// does the user own the project ?
			if ($projectInfo['userid'] === $userid) {
				return Application::ACCESS_ADMIN;
			} else {
				$qb = $this->db->getQueryBuilder();
				// is the project shared with the user ?
				$qb->select('userid', 'projectid', 'accesslevel')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbProjectId = null;
				$dbAccessLevel = null;
				while ($row = $req->fetch()) {
					$dbProjectId = $row['projectid'];
					$dbAccessLevel = intval($row['accesslevel']);
					break;
				}
				$req->closeCursor();
				$qb = $qb->resetQueryParts();

				if ($dbProjectId !== null && $dbAccessLevel > $result) {
					$result = $dbAccessLevel;
				}

				// is the project shared with a group containing the user?
				$userO = $this->userManager->get($userid);

				$qb->select('userid', 'accesslevel')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				while ($row = $req->fetch()){
					$groupId = $row['userid'];
					$dbAccessLevel = intval($row['accesslevel']);
					if ($this->groupManager->groupExists($groupId)
						&& $this->groupManager->get($groupId)->inGroup($userO)
						&& $dbAccessLevel > $result
					) {
						$result = $dbAccessLevel;
					}
				}
				$req->closeCursor();
				$qb = $qb->resetQueryParts();

				// are circles enabled and is the project shared with a circle containing the user
				$circlesEnabled = $this->appManager->isEnabledForUser('circles');
				if ($circlesEnabled) {
					$qb->select('userid', 'accesslevel')
						->from('cospend_shares', 's')
						->where(
							$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->executeQuery();
					while ($row = $req->fetch()) {
						$circleId = $row['userid'];
						$dbAccessLevel = intval($row['accesslevel']);
						if ($this->isUserInCircle($userid, $circleId) && $dbAccessLevel > $result) {
							$result = $dbAccessLevel;
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Get guest access level of a given project
	 *
	 * @param string $projectid
	 * @return int
	 */
	public function getGuestAccessLevel(string $projectid): int {
		$projectInfo = $this->getProjectInfo($projectid);
		if ($projectInfo !== null) {
			return intval($projectInfo['guestaccesslevel']);
		} else {
			return Application::NO_ACCESS;
		}
	}

	/**
	 * Get access level of a shared access
	 *
	 * @param string $projectid
	 * @param int $shid
	 * @return int
	 */
	public function getShareAccessLevel(string $projectid, int $shid): int {
		$result = 0;
		$qb = $this->db->getQueryBuilder();
		$qb->select('accesslevel')
		   ->from('cospend_shares', 's')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$result = intval($row['accesslevel']);
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return $result;
	}

	/**
	 * Create a project
	 *
	 * @param string $name
	 * @param string $id
	 * @param string|null $password
	 * @param string|null $contact_email
	 * @param string $userid
	 * @param bool $createDefaultCategories
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function createProject(string $name, string $id, ?string $password, ?string $contact_email, string $userid = '',
								  bool $createDefaultCategories = true): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
		   ->from('cospend_projects', 'p')
		   ->where(
			   $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		$dbid = null;
		while ($row = $req->fetch()){
			$dbid = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();
		if ($dbid === null) {
			// check if id is valid
			if (strpos($id, '/') !== false) {
				return ['message' => $this->trans->t('Invalid project id')];
			}
			$dbPassword = '';
			if ($password !== null && $password !== '') {
				$dbPassword = password_hash($password, PASSWORD_DEFAULT);
			}
			if ($contact_email === null) {
				$contact_email = '';
			}
			$ts = (new DateTime())->getTimestamp();
			$qb->insert('cospend_projects')
				->values([
					'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
					'id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
					'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
					'password' => $qb->createNamedParameter($dbPassword, IQueryBuilder::PARAM_STR),
					'email' => $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR),
					'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
				]);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			// create default categories
			if ($createDefaultCategories) {
				foreach ($this->defaultCategoryNames as $strId => $catName) {
					$icon = urlencode($this->defaultCategoryIcons[$strId]);
					$color = $this->defaultCategoryColors[$strId];
					$qb->insert('cospend_project_categories')
						->values([
							'projectid' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
							'encoded_icon' => $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR),
							'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
							'name' => $qb->createNamedParameter($catName, IQueryBuilder::PARAM_STR)
						]);
					$qb->executeStatement();
					$qb = $qb->resetQueryParts();
				}
			}

			return ['id' => $id];
		} else {
			return ['message' => $this->trans->t('A project with id "%1$s" already exists', [$id])];
		}
	}

	/**
	 * Delete a project and all associated data
	 *
	 * @param string $projectid
	 * @return array
	 */
	public function deleteProject(string $projectid): array {
		$projectToDelete = $this->getProjectById($projectid);
		if ($projectToDelete !== null) {
			$qb = $this->db->getQueryBuilder();

			// delete project bills
			$bills = $this->getBills($projectid);
			foreach ($bills as $bill) {
				$this->deleteBillOwersOfBill($bill['id']);
			}

			$qb->delete('cospend_bills')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			// delete project members
			$qb->delete('cospend_members')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			// delete shares
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			// delete currencies
			$qb->delete('cospend_currencies')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			// delete categories
			$qb->delete('cospend_project_categories')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			// delete project
			$qb->delete('cospend_projects')
				->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			return ['message' => 'DELETED'];
		} else {
			return ['error' => $this->trans->t('Not Found')];
		}
	}

	/**
	 * Get all project data
	 *
	 * @param string $projectid
	 * @return array
	 */
	public function getProjectInfo(string $projectid): ?array {
		$projectInfo = null;

		$qb = $this->db->getQueryBuilder();

		$qb->select('id', 'password', 'name', 'email', 'userid', 'lastchanged', 'guestaccesslevel',
					'autoexport', 'currencyname', 'deletiondisabled', 'categorysort')
		   ->from('cospend_projects', 'p')
		   ->where(
			   $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		$dbProjectId = null;
		while ($row = $req->fetch()){
			$dbProjectId = $row['id'];
			$dbName = $row['name'];
			$dbEmail= $row['email'];
			$dbUserId = $row['userid'];
			$dbGuestAccessLevel = intval($row['guestaccesslevel']);
			$dbLastchanged = intval($row['lastchanged']);
			$dbAutoexport= $row['autoexport'];
			$dbCurrencyName = $row['currencyname'];
			$dbDeletionDisabled = intval($row['deletiondisabled']) === 1;
			$dbCategorySort = $row['categorysort'];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		if ($dbProjectId !== null) {
			$smallStats = $this->getSmallStats($dbProjectId);
			$members = $this->getMembers($dbProjectId, 'lowername');
			$activeMembers = [];
			foreach ($members as $member) {
				if ($member['activated']) {
					$activeMembers[] = $member;
				}
			}
			$balance = $this->getBalance($dbProjectId);
			$currencies = $this->getCurrencies($dbProjectId);
			$categories = $this->getCategories($dbProjectId);
			// get all shares
			$userShares = $this->getUserShares($dbProjectId);
			$groupShares = $this->getGroupShares($dbProjectId);
			$circleShares = $this->getCircleShares($dbProjectId);
			$publicShares = $this->getPublicShares($dbProjectId);
			$shares = array_merge($userShares, $groupShares, $circleShares, $publicShares);

			$projectInfo = [
				'userid' => $dbUserId,
				'name' => $dbName,
				'contact_email' => $dbEmail,
				'id' => $dbProjectId,
				'guestaccesslevel' => $dbGuestAccessLevel,
				'autoexport' => $dbAutoexport,
				'currencyname' => $dbCurrencyName,
				'lastchanged' => $dbLastchanged,
				'active_members' => $activeMembers,
				'members' => $members,
				'balance' => $balance,
				'nb_bills' => $smallStats['nb_bills'],
				'total_spent' => $smallStats['total_spent'],
				'shares' => $shares,
				'currencies' => $currencies,
				'categories' => $categories,
				'deletion_disabled' => $dbDeletionDisabled,
				'categorysort' => $dbCategorySort,
			];
		}

		return $projectInfo;
	}

	/**
	 * Get number of bills and total spent amount for a givne project
	 *
	 * @param string $projectId
	 * @return array
	 */
	private function getSmallStats(string $projectId): array {
		$nbBills = 0;
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_bills')
		   ->from('cospend_bills')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$nbBills = (int) $row['count_bills'];
		}
		$qb = $qb->resetQueryParts();

		$totalSpent = 0;
		$qb->selectAlias($qb->createFunction('SUM(amount)'), 'sum_amount')
		   ->from('cospend_bills')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$totalSpent = (int) $row['sum_amount'];
		}
		$qb->resetQueryParts();
		return [
			'nb_bills' => $nbBills,
			'total_spent' => $totalSpent,
		];
	}

	/**
	 * Get project statistics
	 *
	 * @param string $projectId
	 * @param string|null $memberOrder
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param string|null $paymentMode
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param bool|null $showDisabled
	 * @param int|null $currencyId
	 * @return array
	 */
	public function getProjectStatistics(string $projectId, ?string $memberOrder = null, ?int $tsMin = null, ?int $tsMax = null,
										  ?string $paymentMode = null, ?int $category = null, ?float $amountMin = null, ?float $amountMax = null,
										  bool $showDisabled = true, ?int $currencyId = null): array {
		$timeZone = $this->dateTimeZone->getTimeZone();
		$membersWeight = [];
		$membersNbBills = [];
		$membersBalance = [];
		$membersFilteredBalance = [];
		$membersPaid = [
			'total' => [],
		];
		$membersSpent = [];
		$membersPaidFor = [];

		$currency = null;
		if ($currencyId !== null && $currencyId !== 0) {
			$currency = $this->getCurrency($projectId, $currencyId);
		}

		$projectCategories = $this->getCategories($projectId);

		// get the real global balances with no filters
		$balances = $this->getBalance($projectId);

		$members = $this->getMembers($projectId, $memberOrder);
		foreach ($members as $member) {
			$memberId = $member['id'];
			$memberWeight = $member['weight'];
			$membersWeight[$memberId] = $memberWeight;
			$membersNbBills[$memberId] = 0;
			$membersBalance[$memberId] = $balances[$memberId];
			$membersFilteredBalance[$memberId] = 0.0;
			$membersPaid[$memberId] = 0.0;
			$membersSpent[$memberId] = 0.0;
			$membersPaidFor[$memberId] = [];
			foreach ($members as $m) {
				$membersPaidFor[$memberId][$m['id']] = 0.0;
			}
			$membersPaidFor['total'][$memberId] = 0.0;
		}

		// build list of members to display
		$membersToDisplay = [];
		$allMembersIds = [];
		foreach ($members as $member) {
			$memberId = $member['id'];
			$allMembersIds[] = $memberId;
			// only take enabled members or those with non-zero balance
			$mBalance = floatval($membersBalance[$memberId]);
			if ($showDisabled || $member['activated'] || $mBalance >= 0.01 || $mBalance <= -0.01) {
				$membersToDisplay[$memberId] = $member;
			}
		}

		// compute stats
		$bills = $this->getBills($projectId, $tsMin, $tsMax, $paymentMode, $category, $amountMin, $amountMax);
		// compute classic stats
		foreach ($bills as $bill) {
			$payerId = $bill['payer_id'];
			$amount = $bill['amount'];
			$owers = $bill['owers'];

			$membersNbBills[$payerId]++;
			$membersFilteredBalance[$payerId] += $amount;
			$membersPaid[$payerId] += $amount;

			$nbOwerShares = 0.0;
			foreach ($owers as $ower) {
				$owerWeight = $ower['weight'];
				if ($owerWeight === 0.0) {
					$owerWeight = 1.0;
				}
				$nbOwerShares += $owerWeight;
			}
			foreach ($owers as $ower) {
				$owerWeight = $ower['weight'];
				if ($owerWeight === 0.0) {
					$owerWeight = 1.0;
				}
				$owerId = $ower['id'];
				$spent = $amount / $nbOwerShares * $owerWeight;
				$membersFilteredBalance[$owerId] -= $spent;
				$membersSpent[$owerId] += $spent;
				// membersPaidFor
				$membersPaidFor[$payerId][$owerId] += $spent;
				$membersPaidFor['total'][$owerId] += $spent;
			}
		}
		foreach ($members as $member) {
			$memberId = $member['id'];
			$membersPaidFor[$memberId]['total'] = $membersPaid[$memberId];
		}

		// build global stats data
		$statistics = [];
		if ($currency === null) {
			foreach ($membersToDisplay as $memberId => $member) {
				$statistic = [
					'balance' => $membersBalance[$memberId],
					'filtered_balance' => $membersFilteredBalance[$memberId],
					'paid' => $membersPaid[$memberId],
					'spent' => $membersSpent[$memberId],
					'member' => $member
				];
				$statistics[] = $statistic;
			}
		}
		else {
			foreach ($membersToDisplay as $memberId => $member) {
				$statistic = [
					'balance' => ($membersBalance[$memberId] === 0.0) ? 0 : $membersBalance[$memberId] / $currency['exchange_rate'],
					'filtered_balance' => ($membersFilteredBalance[$memberId] === 0.0) ? 0 : $membersFilteredBalance[$memberId] / $currency['exchange_rate'],
					'paid' => ($membersPaid[$memberId] === 0.0) ? 0 : $membersPaid[$memberId] / $currency['exchange_rate'],
					'spent' => ($membersSpent[$memberId] === 0.0) ? 0 : $membersSpent[$memberId] / $currency['exchange_rate'],
					'member' => $member
				];
				$statistics[] = $statistic;
			}
		}

		// compute monthly member stats
		$memberMonthlyPaidStats = [];
		$memberMonthlySpentStats = [];
		$allMembersKey = 0;
		foreach ($bills as $bill) {
			$payerId = $bill['payer_id'];
			$amount = $bill['amount'];
			$owers = $bill['owers'];
			$date = DateTime::createFromFormat('U', $bill['timestamp']);
			$date->setTimezone($timeZone);
			$month = $date->format('Y-m');
			//////////////// PAID
			// initialize this month
			if (!array_key_exists($month, $memberMonthlyPaidStats)) {
				$memberMonthlyPaidStats[$month] = [];
				foreach ($membersToDisplay as $memberId => $member) {
					$memberMonthlyPaidStats[$month][$memberId] = 0;
				}
				$memberMonthlyPaidStats[$month][$allMembersKey] = 0;
			}

			// add paid amount
			if (array_key_exists($payerId, $membersToDisplay)) {
				$memberMonthlyPaidStats[$month][$payerId] += $amount;
				$memberMonthlyPaidStats[$month][$allMembersKey] += $amount;
			}
			//////////////// SPENT
			// initialize this month
			if (!array_key_exists($month, $memberMonthlySpentStats)) {
				$memberMonthlySpentStats[$month] = [];
				foreach ($membersToDisplay as $memberId => $member) {
					$memberMonthlySpentStats[$month][$memberId] = 0;
				}
				$memberMonthlySpentStats[$month][$allMembersKey] = 0;
			}
			// spent value for all members is the bill amount (like the paid value)
			$memberMonthlySpentStats[$month][$allMembersKey] += $amount;
			// compute number of shares
			$nbOwerShares = 0.0;
			foreach ($owers as $ower) {
				$owerWeight = $ower['weight'];
				if ($owerWeight === 0.0) {
					$owerWeight = 1.0;
				}
				$nbOwerShares += $owerWeight;
			}
			// compute how much each ower has spent
			foreach ($owers as $ower) {
				$owerWeight = $ower['weight'];
				if ($owerWeight === 0.0) {
					$owerWeight = 1.0;
				}
				$owerId = $ower['id'];
				$spent = $amount / $nbOwerShares * $owerWeight;
				if (array_key_exists($owerId, $membersToDisplay)) {
					$memberMonthlySpentStats[$month][$owerId] += $spent;
				}
			}
		}
		// monthly paid and spent average
		$averageKey = $this->trans->t('Average per month');
		$nbMonth = count(array_keys($memberMonthlyPaidStats));
		if ($nbMonth > 0) {
			////////////////////// PAID
			$averagePaidStats = [];
			foreach ($membersToDisplay as $memberId => $member) {
				$sum = 0;
				foreach ($memberMonthlyPaidStats as $month => $mStat) {
					$sum += $memberMonthlyPaidStats[$month][$memberId];
				}
				$averagePaidStats[$memberId] = $sum / $nbMonth;
			}
			// average for all members
			$sum = 0;
			foreach ($memberMonthlyPaidStats as $month => $mStat) {
				$sum += $memberMonthlyPaidStats[$month][$allMembersKey];
			}
			$averagePaidStats[$allMembersKey] = $sum / $nbMonth;

			$memberMonthlyPaidStats[$averageKey] = $averagePaidStats;
			////////////////////// SPENT
			$averageSpentStats = [];
			foreach ($membersToDisplay as $memberId => $member) {
				$sum = 0;
				foreach ($memberMonthlySpentStats as $month => $mStat) {
					$sum += $memberMonthlySpentStats[$month][$memberId];
				}
				$averageSpentStats[$memberId] = $sum / $nbMonth;
			}
			// average for all members
			$sum = 0;
			foreach ($memberMonthlySpentStats as $month => $mStat) {
				$sum += $memberMonthlySpentStats[$month][$allMembersKey];
			}
			$averageSpentStats[$allMembersKey] = $sum / $nbMonth;

			$memberMonthlySpentStats[$averageKey] = $averageSpentStats;
		}
		// convert if necessary
		if ($currency !== null) {
			foreach ($memberMonthlyPaidStats as $month => $mStat) {
				foreach ($mStat as $mid => $val) {
					$memberMonthlyPaidStats[$month][$mid] = ($memberMonthlyPaidStats[$month][$mid] === 0.0)
						? 0
						: $memberMonthlyPaidStats[$month][$mid] / $currency['exchange_rate'];
				}
			}
			foreach ($memberMonthlySpentStats as $month => $mStat) {
				foreach ($mStat as $mid => $val) {
					$memberMonthlySpentStats[$month][$mid] = ($memberMonthlySpentStats[$month][$mid] === 0.0)
						? 0
						: $memberMonthlySpentStats[$month][$mid] / $currency['exchange_rate'];
				}
			}
		}
		// compute category and payment mode stats
		$categoryStats = [];
		$paymentModeStats = [];
		foreach ($bills as $bill) {
			// category
			$categoryId = $bill['categoryid'];
			if (!array_key_exists(strval($categoryId), $this->hardCodedCategoryNames) &&
				!array_key_exists(strval($categoryId), $projectCategories)
			) {
				$categoryId = 0;
			}
			$amount = $bill['amount'];
			if (!array_key_exists($categoryId, $categoryStats)) {
				$categoryStats[$categoryId] = 0;
			}
			$categoryStats[$categoryId] += $amount;

			// payment mode
			$paymentMode = $bill['paymentmode'] ?: 'n';
			if (!array_key_exists($paymentMode, $paymentModeStats)) {
				$paymentModeStats[$paymentMode] = 0;
			}
			$paymentModeStats[$paymentMode] += $amount;
		}
		// convert if necessary
		if ($currency !== null) {
			foreach ($categoryStats as $catId => $val) {
				$categoryStats[$catId] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
			}
		}
		// compute category per member stats
		$categoryMemberStats = [];
		foreach ($bills as $bill) {
			$payerId = $bill['payer_id'];
			$categoryId = $bill['categoryid'];
			if (!array_key_exists(strval($categoryId), $this->hardCodedCategoryNames) &&
				!array_key_exists(strval($categoryId), $projectCategories)
			) {
				$categoryId = 0;
			}
			$amount = $bill['amount'];
			if (!array_key_exists($categoryId, $categoryMemberStats)) {
				$categoryMemberStats[$categoryId] = [];
				foreach ($membersToDisplay as $memberId => $member) {
					$categoryMemberStats[$categoryId][$memberId] = 0;
				}
			}
			if (array_key_exists($payerId, $membersToDisplay)) {
				$categoryMemberStats[$categoryId][$payerId] += $amount;
			}
		}
		// convert if necessary
		if ($currency !== null) {
			foreach ($categoryMemberStats as $catId => $mStat) {
				foreach ($mStat as $mid => $val) {
					$categoryMemberStats[$catId][$mid] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
				}
			}
		}
		// compute category/payment mode per month stats
		$categoryMonthlyStats = [];
		$paymentModeMonthlyStats = [];
		foreach ($bills as $bill) {
			$amount = $bill['amount'];
			$date = DateTime::createFromFormat('U', $bill['timestamp']);
			$date->setTimezone($timeZone);
			$month = $date->format('Y-m');

			// category
			$categoryId = $bill['categoryid'];
			if (!array_key_exists($categoryId, $categoryMonthlyStats)) {
				$categoryMonthlyStats[$categoryId] = [];
			}
			if (!array_key_exists($month, $categoryMonthlyStats[$categoryId])) {
				$categoryMonthlyStats[$categoryId][$month] = 0;
			}
			$categoryMonthlyStats[$categoryId][$month] += $amount;

			// payment mode
			$paymentMode = $bill['paymentmode'] ?: 'n';
			if (!array_key_exists($paymentMode, $paymentModeMonthlyStats)) {
				$paymentModeMonthlyStats[$paymentMode] = [];
			}
			if (!array_key_exists($month, $paymentModeMonthlyStats[$paymentMode])) {
				$paymentModeMonthlyStats[$paymentMode][$month] = 0;
			}
			$paymentModeMonthlyStats[$paymentMode][$month] += $amount;
		}
		// convert if necessary
		if ($currency !== null) {
			foreach ($categoryMonthlyStats as $catId => $cStat) {
				foreach ($cStat as $month => $val) {
					$categoryMonthlyStats[$catId][$month] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
				}
			}
			foreach ($paymentModeMonthlyStats as $pm => $pmStat) {
				foreach ($pmStat as $month => $val) {
					$paymentModeMonthlyStats[$pm][$month] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
				}
			}
		}

		return [
			'stats' => $statistics,
			'memberMonthlyPaidStats' => $memberMonthlyPaidStats,
			'memberMonthlySpentStats' => $memberMonthlySpentStats,
			'categoryStats' => $categoryStats,
			'categoryMonthlyStats' => $categoryMonthlyStats,
			'paymentModeStats' => $paymentModeStats,
			'paymentModeMonthlyStats' => $paymentModeMonthlyStats,
			'categoryMemberStats' => $categoryMemberStats,
			'memberIds' => array_keys($membersToDisplay),
			'allMemberIds' => $allMembersIds,
			'membersPaidFor' => $membersPaidFor,
		];
	}

	/**
	 * Add a bill in a given project
	 *
	 * @param string $projectid
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentmode
	 * @param int|null $categoryid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @return array
	 */
	public function addBill(string $projectid, ?string $date, ?string $what, ?int $payer, ?string $payed_for,
							?float $amount, ?string $repeat, ?string $paymentmode = null, ?int $categoryid = null,
							?int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
							?string $comment = null, ?int $repeatfreq = null): array {
		if ($repeat === null || $repeat === '' || strlen($repeat) !== 1) {
			return ['repeat' => $this->trans->t('Invalid value')];
		}
		if ($repeatallactive === null) {
			return ['repeatallactive' => $this->trans->t('Invalid value')];
		}
		if ($repeatuntil !== null && $repeatuntil === '') {
			$repeatuntil = null;
		}
		// priority to timestamp (moneybuster might send both for a moment)
		if ($timestamp === null || !is_numeric($timestamp)) {
			if ($date === null || $date === '') {
				return ['message' => $this->trans->t('Timestamp (or date) field is required')];
			} else {
				$dateTs = strtotime($date);
				if ($dateTs === false) {
					return ['date' => $this->trans->t('Invalid date')];
				}
			}
		} else {
			$dateTs = intval($timestamp);
		}
		if ($what === null || $what === '') {
			return ['what' => $this->trans->t('This field is invalid')];
		}
		if ($amount === null) {
			return ['amount' => $this->trans->t('This field is required')];
		}
		if ($payer === null) {
			return ['payer' => $this->trans->t('This field is required')];
		}
		if ($this->getMemberById($projectid, $payer) === null) {
			return ['payer' => $this->trans->t('Not a valid choice')];
		}
		// check owers
		$owerIds = explode(',', $payed_for);
		if ($payed_for === null || $payed_for === '' || count($owerIds) === 0) {
			return ['payed_for' => $this->trans->t('Invalid value')];
		}
		foreach ($owerIds as $owerId) {
			if (!is_numeric($owerId)) {
				return ['payed_for' => $this->trans->t('Invalid value')];
			}
			if ($this->getMemberById($projectid, $owerId) === null) {
				return ['payed_for' => $this->trans->t('Not a valid choice')];
			}
		}

		// last modification timestamp is now
		$ts = (new DateTime())->getTimestamp();

		// do it already !
		$qb = $this->db->getQueryBuilder();
		$qb->insert('cospend_bills')
			->values([
				'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
				'what' => $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR),
				'comment' => $qb->createNamedParameter($comment, IQueryBuilder::PARAM_STR),
				'timestamp' => $qb->createNamedParameter($dateTs, IQueryBuilder::PARAM_INT),
				'amount' => $qb->createNamedParameter($amount, IQueryBuilder::PARAM_STR),
				'payerid' => $qb->createNamedParameter($payer, IQueryBuilder::PARAM_INT),
				'repeat' => $qb->createNamedParameter($repeat, IQueryBuilder::PARAM_STR),
				'repeatallactive' => $qb->createNamedParameter($repeatallactive, IQueryBuilder::PARAM_INT),
				'repeatuntil' => $qb->createNamedParameter($repeatuntil, IQueryBuilder::PARAM_STR),
				'repeatfreq' => $qb->createNamedParameter($repeatfreq ?? 1, IQueryBuilder::PARAM_INT),
				'categoryid' => $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT),
				'paymentmode' => $qb->createNamedParameter($paymentmode, IQueryBuilder::PARAM_STR),
				'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
			]);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$insertedBillId = $qb->getLastInsertId();

		// insert bill owers
		foreach ($owerIds as $owerId) {
			$qb->insert('cospend_bill_owers')
				->values([
					'billid' => $qb->createNamedParameter($insertedBillId, IQueryBuilder::PARAM_INT),
					'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
				]);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();
		}

		$this->updateProjectLastChanged($projectid, $ts);

		return ['inserted_id' => $insertedBillId];
	}

	/**
	 * Delete a bill
	 *
	 * @param string $projectid
	 * @param int $billid
	 * @return array
	 */
	public function deleteBill(string $projectid, int $billid): array {
		$project = $this->getProjectInfo($projectid);
		$deletionDisabled = $project['deletion_disabled'];
		if ($deletionDisabled) {
			return ['message' => 'Forbidden'];
		}
		$billToDelete = $this->getBill($projectid, $billid);
		if ($billToDelete !== null) {
			$this->deleteBillOwersOfBill($billid);

			$qb = $this->db->getQueryBuilder();
			$qb->delete('cospend_bills')
			   ->where(
				   $qb->expr()->eq('id', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
			   )
			   ->andWhere(
				   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			   );
			$qb->executeStatement();
			$qb->resetQueryParts();

			$ts = (new DateTime())->getTimestamp();
			$this->updateProjectLastChanged($projectid, $ts);

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('Not Found')];
		}
	}

	/**
	 * Get a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return array|null
	 */
	private function getMemberById(string $projectId, int $memberId): ?array {
		$member = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'name', 'weight', 'color', 'activated')
		   ->from('cospend_members', 'm')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbMemberId = intval($row['id']);
			$dbWeight = floatval($row['weight']);
			$dbUserid = $row['userid'];
			$dbName = $row['name'];
			$dbActivated = intval($row['activated']);
			$dbColor = $row['color'];
			if ($dbColor === null) {
				$av = $this->avatarManager->getGuestAvatar($dbName);
				$dbColor = $av->avatarBackgroundColor($dbName);
			} else {
				$dbColor = $this->hexToRgb($dbColor);
			}

			$member = [
					'activated' => ($dbActivated === 1),
					'userid' => $dbUserid,
					'name' => $dbName,
					'id' => $dbMemberId,
					'weight' => $dbWeight,
					'color' => $dbColor
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $member;
	}

	/**
	 * Get project info
	 *
	 * @param string $projectId
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectById(string $projectId): ?array {
		$project = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'name', 'email', 'password', 'currencyname', 'autoexport', 'guestaccesslevel', 'lastchanged')
		   ->from('cospend_projects', 'p')
		   ->where(
			   $qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$dbId = $row['id'];
			$dbPassword = $row['password'];
			$dbName = $row['name'];
			$dbUserId = $row['userid'];
			$dbEmail = $row['email'];
			$dbCurrencyName = $row['currencyname'];
			$dbAutoexport = $row['autoexport'];
			$dbLastchanged = intval($row['lastchanged']);
			$dbGuestAccessLevel = intval($row['guestaccesslevel']);
			$project = [
				'id' => $dbId,
				'name' => $dbName,
				'userid' => $dbUserId,
				'password' => $dbPassword,
				'email' => $dbEmail,
				'lastchanged' => $dbLastchanged,
				'currencyname' => $dbCurrencyName,
				'autoexport' => $dbAutoexport,
				'guestaccesslevel' => $dbGuestAccessLevel
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $project;
	}

	/**
	 * Get bill info
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @return array|null
	 */
	public function getBill(string $projectId, int $billId): ?array {
		$bill = null;
		// get bill owers
		$billOwers = [];
		$billOwerIds = [];

		$qb = $this->db->getQueryBuilder();

		$qb->select('memberid', 'm.name', 'm.weight', 'm.activated')
		   ->from('cospend_bill_owers', 'bo')
		   ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
		   ->where(
			   $qb->expr()->eq('bo.billid', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$dbWeight = floatval($row['weight']);
			$dbName = $row['name'];
			$dbActivated = (intval($row['activated']) === 1);
			$dbOwerId= intval($row['memberid']);
			$billOwers[] = [
				'id' => $dbOwerId,
				'weight' => $dbWeight,
				'name' => $dbName,
				'activated' => $dbActivated
			];
			$billOwerIds[] = $dbOwerId;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// get the bill
		$qb->select('id', 'what', 'comment', 'timestamp', 'amount', 'payerid', 'repeat',
					'repeatallactive', 'paymentmode', 'categoryid', 'repeatuntil', 'repeatfreq')
		   ->from('cospend_bills', 'b')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$dbBillId = intval($row['id']);
			$dbAmount = floatval($row['amount']);
			$dbWhat = $row['what'];
			$dbComment = $row['comment'];
			$dbTimestamp = $row['timestamp'];
			$dbDate = DateTime::createFromFormat('U', $dbTimestamp);
			$dbRepeat = $row['repeat'];
			$dbRepeatAllActive = $row['repeatallactive'];
			$dbRepeatUntil = $row['repeatuntil'];
			$dbRepeatFreq = (int) $row['repeatfreq'];
			$dbPayerId = intval($row['payerid']);
			$dbPaymentMode = $row['paymentmode'];
			$dbCategoryId = intval($row['categoryid']);
			$bill = [
				'id' => $dbBillId,
				'amount' => $dbAmount,
				'what' => $dbWhat,
				'comment' => $dbComment,
				'date' => $dbDate->format('Y-m-d'),
				'timestamp' => $dbTimestamp,
				'payer_id' => $dbPayerId,
				'owers' => $billOwers,
				'owerIds' => $billOwerIds,
				'repeat' => $dbRepeat,
				'repeatallactive' => $dbRepeatAllActive,
				'repeatuntil' => $dbRepeatUntil,
				'repeatfreq' => $dbRepeatFreq,
				'paymentmode' => $dbPaymentMode,
				'categoryid' => $dbCategoryId
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return $bill;
	}

	/**
	 * Delete bill owers of given bill
	 *
	 * @param int $billid
	 * @return void
	 */
	private function deleteBillOwersOfBill(int $billid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cospend_bill_owers')
		   ->where(
			   $qb->expr()->eq('billid', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
		   );
		$qb->executeStatement();
		$qb->resetQueryParts();
	}

	/**
	 * Generate bills to automatically settle a project
	 *
	 * @param string $projectid
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function autoSettlement(string $projectid, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): array {
		$settlement = $this->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
		$transactions = $settlement['transactions'];
		if (!is_array($transactions)) {
			return ['message' => $this->trans->t('Error when getting project settlement transactions')];
		}

		$members = $this->getMembers($projectid);
		$memberIdToName = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
		}

		if ($maxTimestamp) {
			$ts = $maxTimestamp - 1;
		} else {
			$ts = (new DateTime())->getTimestamp();
		}

		foreach ($transactions as $transaction) {
			$fromId = $transaction['from'];
			$toId = $transaction['to'];
			$amount = round(floatval($transaction['amount']), $precision);
			$billTitle = $memberIdToName[$fromId].' → '.$memberIdToName[$toId];
			$addBillResult = $this->addBill(
				$projectid, null, $billTitle, $fromId, $toId, $amount,
				'n', 'n', Application::CAT_REIMBURSEMENT,
				0, null, $ts
			);
			if (!isset($addBillResult['inserted_id'])) {
				return ['message' => $this->trans->t('Error when adding a bill')];
			}
		}
		return ['success' => true];
	}

	/**
	 * Get project settlement plan
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function getProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): array {
		$balances = $this->getBalance($projectId, $maxTimestamp);
		if ($centeredOn === null) {
			$transactions = $this->settle($balances);
		} else {
			$transactions = $this->centeredSettle($balances, $centeredOn);
		}
		return [
			'transactions' => $transactions,
			'balances' => $balances,
		];
	}

	/**
	 * Get a settlement plan centered on a member
	 *
	 * @param array $balances
	 * @param int $centeredOn
	 * @return array
	 */
	private function centeredSettle(array $balances, int $centeredOn): array {
		$transactions = [];
		foreach ($balances as $memberId => $balance) {
			if ($memberId !== $centeredOn) {
				if ($balance > 0.0) {
					$transactions[] = [
						'from' => $centeredOn,
						'to' => $memberId,
						'amount' => $balance
					];
				} elseif ($balance < 0.0) {
					$transactions[] = [
						'from' => $memberId,
						'to' => $centeredOn,
						'amount' => -$balance
					];
				}
			}
		}
		return $transactions;
	}

	/**
	 * Get optimal settlement of a balance list
	 *
	 * @param array $balances
	 * @return array
	 */
	private function settle(array $balances): ?array {
		$debitersCrediters = $this->orderBalance($balances);
		$debiters = $debitersCrediters[0];
		$crediters = $debitersCrediters[1];
		return $this->reduceBalance($crediters, $debiters);
	}

	/**
	 * Separate crediter and debiter balances
	 *
	 * @param array $balances
	 * @return array
	 */
	private function orderBalance(array $balances): array {
		$crediters = [];
		$debiters = [];
		foreach ($balances as $id => $balance) {
			if ($balance > 0.0) {
				$crediters[] = [$id, $balance];
			} elseif ($balance < 0.0) {
				$debiters[] = [$id, $balance];
			}
		}

		return [$debiters, $crediters];
	}

	/**
	 * Recursively produce transaction list of the settlement plan
	 *
	 * @param array $crediters
	 * @param array $debiters
	 * @param array|null $results
	 * @return array
	 */
	private function reduceBalance(array $crediters, array $debiters, ?array $results = null): ?array {
		if (count($crediters) === 0 || count($debiters) === 0) {
			return $results;
		}

		if ($results === null) {
			$results = [];
		}

		$crediters = $this->sortCreditersDebiters($crediters);
		$debiters = $this->sortCreditersDebiters($debiters, true);

		$deb = array_pop($debiters);
		$debiter = $deb[0];
		$debiterBalance = $deb[1];

		$cred = array_pop($crediters);
		$crediter = $cred[0];
		$crediterBalance = $cred[1];

		if (abs($debiterBalance) > abs($crediterBalance)) {
			$amount = abs($crediterBalance);
		} else {
			$amount = abs($debiterBalance);
		}

		$newResults = $results;
		$newResults[] = ['to' => $crediter, 'amount' => $amount, 'from' => $debiter];

		$newDebiterBalance = $debiterBalance + $amount;
		if ($newDebiterBalance < 0.0) {
			$debiters[] = [$debiter, $newDebiterBalance];
			$debiters = $this->sortCreditersDebiters($debiters, true);
		}

		$newCrediterBalance = $crediterBalance - $amount;
		if ($newCrediterBalance > 0.0) {
			$crediters[] = [$crediter, $newCrediterBalance];
			$crediters = $this->sortCreditersDebiters($crediters);
		}

		return $this->reduceBalance($crediters, $debiters, $newResults);
	}

	/**
	 * Sort crediters or debiters array by balance value
	 *
	 * @param array $arr
	 * @param bool $reverse
	 * @return array
	 */
	private function sortCreditersDebiters(array $arr, bool $reverse = false): array {
		$res = [];
		if ($reverse) {
			foreach ($arr as $elem) {
				$i = 0;
				while ($i < count($res) && $elem[1] < $res[$i][1]) {
					$i++;
				}
				array_splice($res, $i, 0, [$elem]);
			}
		} else {
			foreach ($arr as $elem) {
				$i = 0;
				while ($i < count($res) && $elem[1] >= $res[$i][1]) {
					$i++;
				}
				array_splice($res, $i, 0, [$elem]);
			}
		}
		return $res;
	}

	/**
	 * Edit a member
	 *
	 * @param string $projectid
	 * @param int $memberid
	 * @param string|null $name
	 * @param string|null $userid
	 * @param float|null $weight
	 * @param bool $activated
	 * @param string|null $color
	 * @return array
	 */
	public function editMember(string $projectid, int $memberid, ?string $name = null, ?string $userid = null,
								?float $weight = null, ?bool $activated = null, ?string $color = null): array {
		if (!is_null($name) && $name !== '') {
			$member = $this->getMemberById($projectid, $memberid);
			if (!is_null($member)) {
				$qb = $this->db->getQueryBuilder();
				// delete member if it has no bill and we are disabling it
				if ($member['activated']
					&& (!is_null($activated) && $activated === false)
					&& count($this->getBillsOfMember($projectid, $memberid)) === 0
				) {
					$qb->delete('cospend_members')
						->where(
							$qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
						);
					$qb->executeStatement();
					$qb->resetQueryParts();
					return [];
				}
				// get existing member with this name
				$memberWithSameName = $this->getMemberByName($projectid, $name);
				if (strpos($name, '/') !== false) {
					return ['name' => $this->trans->t('Invalid member name')];
				} elseif ($memberWithSameName && $memberWithSameName['id'] !== $memberid) {
					return ['name' => $this->trans->t('Name already exists')];
				}
				$qb->update('cospend_members');
				if ($weight !== null) {
					if ($weight > 0.0) {
						$qb->set('weight', $qb->createNamedParameter($weight, IQueryBuilder::PARAM_STR));
					} else {
						return ['weight' => $this->trans->t('Not a valid decimal value')];
					}
				}
				if (!is_null($activated)) {
					$qb->set('activated', $qb->createNamedParameter(($activated ? 1 : 0), IQueryBuilder::PARAM_INT));
				}

				$ts = (new DateTime())->getTimestamp();
				$qb->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT));

				$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
				if ($color !== null) {
					if ($color === '') {
						$qb->set('color', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
					} else {
						$qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
					}
				}
				if ($userid !== null) {
					if ($userid === '') {
						$qb->set('userid', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
					} else {
						$qb->set('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR));
					}
				}
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
				)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					);
				$qb->executeStatement();
				$qb->resetQueryParts();

				return $this->getMemberById($projectid, $memberid);
			} else {
				return ['name' => $this->trans->t('This project have no such member')];
			}
		} else {
			return ['name' => $this->trans->t('This field is required')];
		}
	}

	/**
	 * Edit a project
	 *
	 * @param string $projectid
	 * @param string $name
	 * @param string|null $contact_email
	 * @param string|null $password
	 * @param string|null $autoexport
	 * @param string|null $currencyname
	 * @param bool|null $deletion_disabled
	 * @param string|null $categorysort
	 * @return array
	 */
	public function editProject(string $projectid, string $name, ?string $contact_email = null, ?string $password = null,
								?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
								?string $categorysort = null): array {
		if ($name === null || $name === '') {
			return ['name' => [$this->trans->t('Name field is required')]];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('cospend_projects');

		if ($contact_email !== null && $contact_email !== '') {
			if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
				$qb->set('email', $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR));
			} else {
				return ['contact_email' => [$this->trans->t('Invalid email address')]];
			}
		}
		if ($password !== null && $password !== '') {
			$dbPassword = password_hash($password, PASSWORD_DEFAULT);
			$qb->set('password', $qb->createNamedParameter($dbPassword, IQueryBuilder::PARAM_STR));
		}
		if ($autoexport !== null && $autoexport !== '') {
			$qb->set('autoexport', $qb->createNamedParameter($autoexport, IQueryBuilder::PARAM_STR));
		}
		if ($categorysort !== null && $categorysort !== '') {
			$qb->set('categorysort', $qb->createNamedParameter($categorysort, IQueryBuilder::PARAM_STR));
		}
		if ($deletion_disabled !== null) {
			$qb->set('deletiondisabled', $qb->createNamedParameter($deletion_disabled ? 1 : 0, IQueryBuilder::PARAM_INT));
		}
		if ($currencyname !== null) {
			if ($currencyname === '') {
				$qb->set('currencyname', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
			} else {
				$qb->set('currencyname', $qb->createNamedParameter($currencyname, IQueryBuilder::PARAM_STR));
			}
		}
		if ($this->getProjectById($projectid) !== null) {
			$ts = (new DateTime())->getTimestamp();
			$qb->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT));
			$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			);
			$qb->executeStatement();
			$qb->resetQueryParts();

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('There is no such project')];
		}
	}

	/**
	 * Add a member to a project
	 *
	 * @param string $projectid
	 * @param string $name
	 * @param float|null $weight
	 * @param bool $active
	 * @param string|null $color
	 * @param string|null $userid
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function addMember(string $projectid, string $name, ?float $weight = 1.0, bool $active = true,
								?string $color = null, ?string $userid = null): array {
		if ($name !== null && $name !== '') {
			if ($this->getMemberByName($projectid, $name) === null && $this->getMemberByUserid($projectid, $userid) === null) {
				if (strpos($name, '/') !== false) {
					return ['error' => $this->trans->t('Invalid member name')];
				}
				$weightToInsert = 1.0;
				if ($weight !== null) {
					if ($weight > 0.0) {
						$weightToInsert = $weight;
					} else {
						return ['error' => $this->trans->t('Weight is not a valid decimal value')];
					}
				}

				$ts = (new DateTime())->getTimestamp();

				$qb = $this->db->getQueryBuilder();
				$qb->insert('cospend_members')
					->values([
						'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
						'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
						'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
						'weight' => $qb->createNamedParameter($weightToInsert, IQueryBuilder::PARAM_STR),
						'activated' => $qb->createNamedParameter($active ? 1 : 0, IQueryBuilder::PARAM_INT),
						'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
						'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
					]);
				$qb->executeStatement();
				$qb->resetQueryParts();

				return $this->getMemberByName($projectid, $name);
			} else {
				return ['error' => $this->trans->t('This project already has this member')];
			}
		} else {
			return ['error' => $this->trans->t('Name field is required')];
		}
	}

	/**
	 * Get number of bills in a project
	 *
	 * @param string $projectId
	 * @return int
	 */
	public function getNbBills(string $projectId): int {
		$nb = 0;
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_bills')
		   ->from('cospend_bills', 'bi')
		   ->where(
			   $qb->expr()->eq('bi.projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$nb = (int) $row['count_bills'];
		}
		return $nb;
	}

	/**
	 * Get filtered list of bills for a project
	 *
	 * @param string $projectId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param string|null $paymentMode
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param int|null $lastchanged
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int $offset
	 * @return array
	 */
	public function getBillsWithLimit(string $projectId, ?int $tsMin = null, ?int $tsMax = null, ?string $paymentMode = null, ?int $category = null,
							  ?float $amountMin = null, ?float $amountMax = null, ?int $lastchanged = null, ?int $limit = null,
							  bool $reverse = false, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'what', 'comment', 'timestamp', 'amount', 'payerid', 'repeat',
					'paymentmode', 'categoryid', 'lastchanged', 'repeatallactive',
					'repeatuntil', 'repeatfreq')
		   ->from('cospend_bills', 'bi')
		   ->where(
			   $qb->expr()->eq('bi.projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		// take bills that have changed after $lastchanged
		if ($lastchanged !== null && is_numeric($lastchanged)) {
			$qb->andWhere(
				$qb->expr()->gt('bi.lastchanged', $qb->createNamedParameter(intval($lastchanged), IQueryBuilder::PARAM_INT))
			);
		}
		if (is_numeric($tsMin)) {
			$qb->andWhere(
				$qb->expr()->gte('timestamp', $qb->createNamedParameter($tsMin, IQueryBuilder::PARAM_INT))
			);
		}
		if (is_numeric($tsMax)) {
			$qb->andWhere(
				$qb->expr()->lte('timestamp', $qb->createNamedParameter($tsMax, IQueryBuilder::PARAM_INT))
			);
		}
		if ($paymentMode !== null && $paymentMode !== '' && $paymentMode !== 'n') {
			$qb->andWhere(
				$qb->expr()->eq('paymentmode', $qb->createNamedParameter($paymentMode, IQueryBuilder::PARAM_STR))
			);
		}
		if ($category !== null && $category !== 0) {
			if ($category === -100) {
				$or = $qb->expr()->orx();
				$or->add($qb->expr()->isNull('categoryid'));
				$or->add($qb->expr()->neq('categoryid', $qb->createNamedParameter(Application::CAT_REIMBURSEMENT, IQueryBuilder::PARAM_INT)));
				$qb->andWhere($or);
			} else {
				$qb->andWhere(
					$qb->expr()->eq('categoryid', $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT))
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
		if ($offset) {
			$qb->setFirstResult($offset);
		}
		$req = $qb->executeQuery();

		$bills = [];
		while ($row = $req->fetch()){
			$dbBillId = intval($row['id']);
			$dbAmount = floatval($row['amount']);
			$dbWhat = $row['what'];
			$dbComment = $row['comment'];
			$dbTimestamp = $row['timestamp'];
			$dbDate = DateTime::createFromFormat('U', $dbTimestamp);
			$dbRepeat = $row['repeat'];
			$dbPayerId = intval($row['payerid']);
			$dbPaymentMode = $row['paymentmode'];
			$dbCategoryId = intval($row['categoryid']);
			$dbLastchanged = intval($row['lastchanged']);
			$dbRepeatAllActive = intval($row['repeatallactive']);
			$dbRepeatUntil = $row['repeatuntil'];
			$dbRepeatFreq = (int) $row['repeatfreq'];
			$bills[] = [
				'id' => $dbBillId,
				'amount' => $dbAmount,
				'what' => $dbWhat,
				'comment' => $dbComment,
				'timestamp' => $dbTimestamp,
				'date' => $dbDate->format('Y-m-d'),
				'payer_id' => $dbPayerId,
				'owers' => [],
				'owerIds' => [],
				'repeat' => $dbRepeat,
				'paymentmode' => $dbPaymentMode,
				'categoryid' => $dbCategoryId,
				'lastchanged' => $dbLastchanged,
				'repeatallactive' => $dbRepeatAllActive,
				'repeatuntil' => $dbRepeatUntil,
				'repeatfreq' => $dbRepeatFreq,
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// get owers
		foreach ($bills as $i => $bill) {
			$billId = $bill['id'];
			$billOwers = [];
			$billOwerIds = [];

			$qb->select('memberid', 'm.name', 'm.weight', 'm.activated')
			   ->from('cospend_bill_owers', 'bo')
			   ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
			   ->where(
				   $qb->expr()->eq('bo.billid', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
			   );
			$qb->setFirstResult(0);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()){
				$dbWeight = floatval($row['weight']);
				$dbName = $row['name'];
				$dbActivated = (intval($row['activated']) === 1);
				$dbOwerId= intval($row['memberid']);
				$billOwers[] = [
					'id' => $dbOwerId,
					'weight' => $dbWeight,
					'name' => $dbName,
					'activated' => $dbActivated
				];
				$billOwerIds[] = $dbOwerId;
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();
			$bills[$i]['owers'] = $billOwers;
			$bills[$i]['owerIds'] = $billOwerIds;
		}

		return $bills;
	}

	/**
	 * Get filtered list of bills for a project
	 *
	 * @param string $projectId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param string|null $paymentMode
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param int|null $lastchanged
	 * @param int|null $limit
	 * @param bool $reverse
	 * @return array
	 */
	public function getBills(string $projectId, ?int $tsMin = null, ?int $tsMax = null, ?string $paymentMode = null, ?int $category = null,
							  ?float $amountMin = null, ?float $amountMax = null, ?int $lastchanged = null, ?int $limit = null,
							  bool $reverse = false): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('bi.id', 'what', 'comment', 'timestamp', 'amount', 'payerid', 'repeat',
					'paymentmode', 'categoryid', 'bi.lastchanged', 'repeatallactive', 'repeatuntil', 'repeatfreq',
					'memberid', 'm.name', 'm.weight', 'm.activated')
		   ->from('cospend_bill_owers', 'bo')
		   ->innerJoin('bo', 'cospend_bills', 'bi', $qb->expr()->eq('bo.billid', 'bi.id'))
		   ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
		   ->where(
			   $qb->expr()->eq('bi.projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		// take bills that have changed after $lastchanged
		if ($lastchanged !== null && is_numeric($lastchanged)) {
			$qb->andWhere(
				$qb->expr()->gt('bi.lastchanged', $qb->createNamedParameter(intval($lastchanged), IQueryBuilder::PARAM_INT))
			);
		}
		if (is_numeric($tsMin)) {
			$qb->andWhere(
				$qb->expr()->gte('timestamp', $qb->createNamedParameter($tsMin, IQueryBuilder::PARAM_INT))
			);
		}
		if (is_numeric($tsMax)) {
			$qb->andWhere(
				$qb->expr()->lte('timestamp', $qb->createNamedParameter($tsMax, IQueryBuilder::PARAM_INT))
			);
		}
		if ($paymentMode !== null && $paymentMode !== '' && $paymentMode !== 'n') {
			$qb->andWhere(
				$qb->expr()->eq('paymentmode', $qb->createNamedParameter($paymentMode, IQueryBuilder::PARAM_STR))
			);
		}
		if ($category !== null && $category !== 0) {
			if ($category === -100) {
				$or = $qb->expr()->orx();
				$or->add($qb->expr()->isNull('categoryid'));
				$or->add($qb->expr()->neq('categoryid', $qb->createNamedParameter(Application::CAT_REIMBURSEMENT, IQueryBuilder::PARAM_INT)));
				$qb->andWhere($or);
			} else {
				$qb->andWhere(
					$qb->expr()->eq('categoryid', $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT))
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
		$billDict = [];
		// ordered list of bill ids
		$orderedBillIds = [];
		while ($row = $req->fetch()){
			$dbBillId = intval($row['id']);
			// if first time we see the bill : add it to bill list
			if (!array_key_exists($dbBillId, $billDict)) {
				$dbAmount = floatval($row['amount']);
				$dbWhat = $row['what'];
				$dbComment = $row['comment'];
				$dbTimestamp = $row['timestamp'];
				$dbDate = DateTime::createFromFormat('U', $dbTimestamp);
				$dbRepeat = $row['repeat'];
				$dbPayerId = intval($row['payerid']);
				$dbPaymentMode = $row['paymentmode'];
				$dbCategoryId = intval($row['categoryid']);
				$dbLastchanged = intval($row['lastchanged']);
				$dbRepeatAllActive = intval($row['repeatallactive']);
				$dbRepeatUntil = $row['repeatuntil'];
				$dbRepeatFreq = (int) $row['repeatfreq'];
				$billDict[$dbBillId] = [
					'id' => $dbBillId,
					'amount' => $dbAmount,
					'what' => $dbWhat,
					'comment' => $dbComment,
					'timestamp' => $dbTimestamp,
					'date' => $dbDate->format('Y-m-d'),
					'payer_id' => $dbPayerId,
					'owers' => [],
					'owerIds' => [],
					'repeat' => $dbRepeat,
					'paymentmode' => $dbPaymentMode,
					'categoryid' => $dbCategoryId,
					'lastchanged' => $dbLastchanged,
					'repeatallactive' => $dbRepeatAllActive,
					'repeatuntil' => $dbRepeatUntil,
					'repeatfreq' => $dbRepeatFreq,
				];
				// keep order of bills
				$orderedBillIds[] = $dbBillId;
			}
			// anyway add an ower
			$dbWeight = floatval($row['weight']);
			$dbName = $row['name'];
			$dbActivated = (intval($row['activated']) === 1);
			$dbOwerId= intval($row['memberid']);
			$billDict[$dbBillId]['owers'][] = [
				'id' => $dbOwerId,
				'weight' => $dbWeight,
				'name' => $dbName,
				'activated' => $dbActivated,
			];
			$billDict[$dbBillId]['owerIds'][] = $dbOwerId;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		$resultBills = [];
		foreach ($orderedBillIds as $bid) {
			$resultBills[] = $billDict[$bid];
		}

		return $resultBills;
	}

	/**
	 * Get all bill IDs of a project
	 *
	 * @param string $projectId
	 * @return array
	 */
	public function getAllBillIds(string $projectId): array {
		$billIds = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cospend_bills', 'b')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$billIds[] = $row['id'];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return $billIds;
	}

	/**
	 * Get members of a project
	 *
	 * @param string $projectId
	 * @param string|null $order
	 * @param int|null $lastchanged
	 * @return array
	 */
	public function getMembers(string $projectId, ?string $order = null, ?int $lastchanged = null): array {
		$members = [];
		$qb = $this->db->getQueryBuilder();

		$sqlOrder = 'name';
		if ($order !== null) {
			if ($order === 'lowername') {
				$sqlOrder = $qb->func()->lower('name');
			} else {
				$sqlOrder = $order;
			}
		}

		$qb->select('id', 'userid', 'name', 'weight', 'color', 'activated', 'lastchanged')
		   ->from('cospend_members', 'm')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		if ($lastchanged !== null) {
		   $qb->andWhere(
			   $qb->expr()->gt('lastchanged', $qb->createNamedParameter($lastchanged, IQueryBuilder::PARAM_INT))
		   );
		}
		$qb->orderBy($sqlOrder, 'ASC');
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$dbMemberId = intval($row['id']);
			$dbWeight = floatval($row['weight']);
			$dbUserid = $row['userid'];
			$dbName = $row['name'];
			$dbActivated = intval($row['activated']);
			$dbLastchanged = intval($row['lastchanged']);
			$dbColor = $row['color'];
			if ($dbColor === null) {
				$av = $this->avatarManager->getGuestAvatar($dbName);
				$dbColor = $av->avatarBackgroundColor($dbName);
			} else {
				$dbColor = $this->hexToRgb($dbColor);
			}

			$members[] = [
				'activated' => ($dbActivated === 1),
				'userid' => $dbUserid,
				'name' => $dbName,
				'id' => $dbMemberId,
				'weight' => $dbWeight,
				'color' => $dbColor,
				'lastchanged' => $dbLastchanged
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $members;
	}

	/**
	 * Get members balances for a project
	 *
	 * @param string $projectId
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	private function getBalance(string $projectId, ?int $maxTimestamp = null): array {
		$membersWeight = [];
		$membersBalance = [];

		$members = $this->getMembers($projectId);
		foreach ($members as $member) {
			$memberId = $member['id'];
			$memberWeight = $member['weight'];
			$membersWeight[$memberId] = $memberWeight;
			$membersBalance[$memberId] = 0.0;
		}

		$bills = $this->getBills($projectId, null, $maxTimestamp);
		foreach ($bills as $bill) {
			$payerId = $bill['payer_id'];
			$amount = $bill['amount'];
			$owers = $bill['owers'];

			$membersBalance[$payerId] += $amount;

			$nbOwerShares = 0.0;
			foreach ($owers as $ower) {
				$owerWeight = $ower['weight'];
				if ($owerWeight === 0.0) {
					$owerWeight = 1.0;
				}
				$nbOwerShares += $owerWeight;
			}
			foreach ($owers as $ower) {
				$owerWeight = $ower['weight'];
				if ($owerWeight === 0.0) {
					$owerWeight = 1.0;
				}
				$owerId = $ower['id'];
				$spent = $amount / $nbOwerShares * $owerWeight;
				$membersBalance[$owerId] -= $spent;
			}
		}

		return $membersBalance;
	}

	/**
	 * Check if a user is member of a given circle
	 *
	 * @param string $userId
	 * @param string $circleId
	 * @return bool
	 */
	private function isUserInCircle(string $userId, string $circleId): bool {
		$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
		$circlesManager->startSuperSession();
		try {
			$circle = $circlesManager->getCircle($circleId);
		} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
			$circlesManager->stopSession();
			return false;
		}
		// is the circle owner
		$owner = $circle->getOwner();
		// the owner is also a member so this might be useless...
		if ($owner->getUserType() === 1 && $owner->getUserId() === $userId) {
			$circlesManager->stopSession();
			return true;
		} else {
			$members = $circle->getMembers();
			foreach ($members as $m) {
				// is member of this circle
				if ($m->getUserType() === 1 && $m->getUserId() === $userId) {
					$circlesManager->stopSession();
					return true;
				}
			}
		}
		$circlesManager->stopSession();
		return false;
	}

	/**
	 * Get project list for a given NC user
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProjects(string $userId): array {
		$projectids = [];

		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
		   ->from('cospend_projects', 'p')
		   ->where(
			   $qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$dbProjectId = $row['id'];
			$projectids[] = $dbProjectId;
		}
		$req->closeCursor();

		$qb = $qb->resetQueryParts();

		// shared with user
		$qb->select('p.id')
		   ->from('cospend_projects', 'p')
		   ->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
		   ->where(
			   $qb->expr()->eq('s.userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('s.type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$dbProjectId = $row['id'];
			// avoid putting twice the same project
			// this can happen with a share loop
			if (!in_array($dbProjectId, $projectids)) {
				$projectids[] = $dbProjectId;
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// shared with one of the groups the user is member of
		$userO = $this->userManager->get($userId);

		// get group with which a project is shared
		$candidateGroupIds = [];
		$qb->select('userid')
		   ->from('cospend_shares', 's')
		   ->where(
			   $qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
		   )
		   ->groupBy('userid');
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$groupId = $row['userid'];
			$candidateGroupIds[] = $groupId;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// is the user member of these groups?
		foreach ($candidateGroupIds as $candidateGroupId) {
			$group = $this->groupManager->get($candidateGroupId);
			if ($group !== null && $group->inGroup($userO)) {
				// get projects shared with this group
				$qb->select('p.id')
					->from('cospend_projects', 'p')
					->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
					->where(
						$qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateGroupId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('s.type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();

				while ($row = $req->fetch()){
					$dbProjectId = $row['id'];
					// avoid putting twice the same project
					// this can happen with a share loop
					if (!in_array($dbProjectId, $projectids)) {
						$projectids[] = $dbProjectId;
					}
				}
				$req->closeCursor();
				$qb = $qb->resetQueryParts();
			}
		}

		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			// get circles with which a project is shared
			$candidateCircleIds = [];
			$qb->select('userid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
			)
			->groupBy('userid');
			$req = $qb->executeQuery();
			while ($row = $req->fetch()){
				$circleId = $row['userid'];
				$candidateCircleIds[] = $circleId;
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();

			// is the user member of these circles?
			foreach ($candidateCircleIds as $candidateCircleId) {
				if ($this->isUserInCircle($userId, $candidateCircleId)) {
					// get projects shared with this circle
					$qb->select('p.id')
						->from('cospend_projects', 'p')
						->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
						->where(
							$qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateCircleId, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('s.type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
						);
					$req = $qb->executeQuery();

					while ($row = $req->fetch()){
						$dbProjectId = $row['id'];
						// avoid putting twice the same project
						// this can happen with a share loop or multiple shares
						if (!in_array($dbProjectId, $projectids)) {
							$projectids[] = $dbProjectId;
						}
					}
					$req->closeCursor();
					$qb = $qb->resetQueryParts();
				}
			}
		}

		// get the projects
		$projects = [];
		foreach ($projectids as $projectid) {
			$project = $this->getProjectInfo($projectid);
			$project['myaccesslevel'] = $this->getUserMaxAccessLevel($userId, $projectid);
			$projects[] = $project;
		}

		return $projects;
	}

	/**
	 * Get categories of a given project
	 *
	 * @param string $projectid
	 * @return array
	 */
	private function getCategories(string $projectid): array {
		$categories = [];

		$qb = $this->db->getQueryBuilder();

		// get sort method
		$qb->select('categorysort')
			->from('cospend_projects', 'p')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		$sortMethod = 'a';
		while ($row = $req->fetch()) {
			$sortMethod = $row['categorysort'];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		if ($sortMethod === 'm' || $sortMethod === 'a') {
			$qb->select('name', 'id', 'encoded_icon', 'color', 'order')
				->from('cospend_project_categories', 'c')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbName = $row['name'];
				$dbIcon = urldecode($row['encoded_icon']);
				$dbColor = $row['color'];
				$dbId = intval($row['id']);
				$dbOrder = intval($row['order']);
				$categories[$dbId] = [
					'name' => $dbName,
					'icon' => $dbIcon,
					'color' => $dbColor,
					'id' => $dbId,
					'order' => $dbOrder,
				];
			}
			$req->closeCursor();
			$qb->resetQueryParts();
		} elseif ($sortMethod === 'u') {
			// get all categories
			$qb->select('name', 'id', 'encoded_icon', 'color')
				->from('cospend_project_categories', 'c')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbName = $row['name'];
				$dbIcon = urldecode($row['encoded_icon']);
				$dbColor = $row['color'];
				$dbId = intval($row['id']);
				$categories[$dbId] = [
					'name' => $dbName,
					'icon' => $dbIcon,
					'color' => $dbColor,
					'id' => $dbId,
					'order' => null,
				];
			}
			$req->closeCursor();
			$qb->resetQueryParts();
			// sort by most used
			// first get list of most used
			$mostUsedOrder = [];
			$qb->select('name', 'cat.id', 'encoded_icon', 'color')
				->from('cospend_project_categories', 'cat')
				->innerJoin('cat', 'cospend_bills', 'bill', $qb->expr()->eq('cat.id', 'bill.categoryid'))
				->where(
					$qb->expr()->eq('cat.projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->orderBy($qb->func()->count('cat.id'), 'DESC')
				->groupBy('cat.id');
			$req = $qb->executeQuery();
			$order = 0;
			while ($row = $req->fetch()) {
				$dbId = (int) $row['id'];
				$mostUsedOrder[$dbId] = $order++;
			}
			$req->closeCursor();
			$qb->resetQueryParts();
			// affect order
			foreach ($categories as $cid => $cat) {
				// fallback order is more than max order
				$categories[$cid]['order'] = $mostUsedOrder[$cid] ?? $order;
			}
		} elseif ($sortMethod === 'r') {
			// sort by most recently used
		}

		return $categories;
	}

	/**
	 * Get currencies of a project
	 *
	 * @param string $projectid
	 * @return array
	 */
	private function getCurrencies(string $projectid): array {
		$currencies = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('name', 'id', 'exchange_rate')
		   ->from('cospend_currencies', 'c')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$dbName = $row['name'];
			$dbId = intval($row['id']);
			$dbExchangeRate = floatval($row['exchange_rate']);
			$currencies[] = [
				'name' => $dbName,
				'exchange_rate' => $dbExchangeRate,
				'id' => $dbId,
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return $currencies;
	}

	/**
	 * Get user shared access of a project
	 *
	 * @param string $projectid
	 * @return array
	 */
	private function getUserShares(string $projectid): array {
		$shares = [];
		$userIdToName = [];
		$sharesToDelete = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'userid', 'id', 'accesslevel', 'manually_added')
		   ->from('cospend_shares', 'sh')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$dbuserId = $row['userid'];
			$dbId = $row['id'];
			$dbAccessLevel = intval($row['accesslevel']);
			$dbManuallyAdded = intval($row['manually_added']);
			if (array_key_exists($dbuserId, $userIdToName)) {
				$name = $userIdToName[$dbuserId];
			} else {
				$user = $this->userManager->get($dbuserId);
				if ($user !== null) {
					$userIdToName[$user->getUID()] = $user->getDisplayName();
					$name = $user->getDisplayName();
				} else {
					$sharesToDelete[] = $dbId;
					continue;
				}
			}
			$shares[] = [
				'userid' => $dbuserId,
				'name' => $name,
				'id' => $dbId,
				'accesslevel' => $dbAccessLevel,
				'type' => 'u',
				'manually_added' => $dbManuallyAdded === 1,
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		// delete shares pointing to unfound users
		foreach ($sharesToDelete as $shId) {
			$this->deleteUserShare($projectid, $shId);
		}

		return $shares;
	}

	/**
	 * Get public links of a project
	 *
	 * @param string $projectid
	 * @param int|null $maxAccessLevel
	 * @return array
	 */
	private function getPublicShares(string $projectid, ?int $maxAccessLevel = null): array {
		$shares = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'userid', 'id', 'accesslevel')
		   ->from('cospend_shares', 'sh')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('type', $qb->createNamedParameter('l', IQueryBuilder::PARAM_STR))
		   );
		if (!is_null($maxAccessLevel)) {
		   $qb->andWhere(
			   $qb->expr()->lte('accesslevel', $qb->createNamedParameter($maxAccessLevel, IQueryBuilder::PARAM_INT))
		   );
		}
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$dbToken = $row['userid'];
			$dbId = $row['id'];
			$dbAccessLevel = intval($row['accesslevel']);
			$shares[] = [
				'token' => $dbToken,
				'id' => $dbId,
				'accesslevel' => $dbAccessLevel,
				'type' => 'l',
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return $shares;
	}

	/**
	 * Get project info for a given public share token
	 *
	 * @param string $token
	 * @return array
	 */
	public function getProjectInfoFromShareToken(string $token): array {
		$projectId = null;
		$accessLevel = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'accesslevel')
		   ->from('cospend_shares', 'sh')
		   ->where(
			   $qb->expr()->eq('userid', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('type', $qb->createNamedParameter('l', IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$projectId = $row['projectid'];
			$accessLevel = intval($row['accesslevel']);
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return [
			'projectid' => $projectId,
			'accesslevel' => $accessLevel
		];
	}

	/**
	 * Get group shared access list of a project
	 *
	 * @param string $projectid
	 * @return array
	 */
	private function getGroupShares(string $projectid): array {
		$shares = [];
		$groupIdToName = [];
		$sharesToDelete = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'userid', 'id', 'accesslevel')
		   ->from('cospend_shares', 'sh')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();
		while ($row = $req->fetch()){
			$dbGroupId = $row['userid'];
			$dbId = $row['id'];
			$dbAccessLevel = intval($row['accesslevel']);
			if (array_key_exists($dbGroupId, $groupIdToName)) {
				$name = $groupIdToName[$dbGroupId];
			} else {
				if ($this->groupManager->groupExists($dbGroupId)) {
					$name = $this->groupManager->get($dbGroupId)->getDisplayName();
					$groupIdToName[$dbGroupId] = $name;
				} else {
					$sharesToDelete[] = $dbId;
					continue;
				}
			}
			$shares[] = [
				'groupid' => $dbGroupId,
				'name' => $name,
				'id' => $dbId,
				'accesslevel' => $dbAccessLevel,
				'type' => 'g',
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		foreach ($sharesToDelete as $shId) {
			$this->deleteGroupShare($projectid, $shId);
		}

		return $shares;
	}

	/**
	 * Get circle shared access list of a project
	 *
	 * @param string $projectid
	 * @return array
	 */
	private function getCircleShares(string $projectid): array {
		$shares = [];

		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
			$circlesManager->startSuperSession();
			$qb = $this->db->getQueryBuilder();
			$qb->select('projectid', 'userid', 'id', 'accesslevel')
				->from('cospend_shares', 'sh')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbCircleId = $row['userid'];
				$dbId = $row['id'];
				$dbAccessLevel = intval($row['accesslevel']);
				try {
					$circle = $circlesManager->getCircle($dbCircleId);
					$shares[] = [
						'circleid' => $dbCircleId,
						'name' => $circle->getDisplayName(),
						'id' => $dbId,
						'accesslevel' => $dbAccessLevel,
						'type' => 'c',
					];
				} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				}
			}
			$req->closeCursor();
			$qb->resetQueryParts();
			$circlesManager->stopSession();
		}
		return $shares;
	}

	/**
	 * Delete a member
	 *
	 * @param string $projectid
	 * @param int $memberid
	 * @return array
	 */
	public function deleteMember(string $projectid, int $memberid): array {
		$memberToDelete = $this->getMemberById($projectid, $memberid);
		if ($memberToDelete !== null) {
			$qb = $this->db->getQueryBuilder();
			if (count($this->getBillsOfMember($projectid, $memberid)) === 0) {
				$qb->delete('cospend_members')
					->where(
						$qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
					);
				$qb->executeStatement();
				$qb->resetQueryParts();
			} elseif ($memberToDelete['activated']) {
				$qb->update('cospend_members');
				$qb->set('activated', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
				$qb->executeStatement();
				$qb->resetQueryParts();
			}
			return ['success' => true];
		} else {
			return ['Not Found'];
		}
	}

	/**
	 * Get bills involving a member (as a payer or an ower)
	 *
	 * @param string $projectid
	 * @param int $memberid
	 * @return array
	 */
	private function getBillsOfMember(string $projectid, int $memberid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('bi.id')
			->from('cospend_bill_owers', 'bo')
			->innerJoin('bo', 'cospend_bills', 'bi', $qb->expr()->eq('bo.billid', 'bi.id'))
			->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
			->where(
				$qb->expr()->eq('bi.payerid', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
			)
			->orWhere(
				$qb->expr()->eq('bo.memberid', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();

		$billIds = [];
		while ($row = $req->fetch()) {
			$billIds[] = $row['id'];
		}
		return $billIds;
	}

	/**
	 * Get a member from its name
	 *
	 * @param string $projectId
	 * @param string $name
	 * @return array|null
	 */
	public function getMemberByName(string $projectId, string $name): ?array {
		$member = null;
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'name', 'weight', 'color', 'activated')
		   ->from('cospend_members', 'm')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()){
			$dbMemberId = intval($row['id']);
			$dbWeight = floatval($row['weight']);
			$dbUserid = $row['userid'];
			$dbName = $row['name'];
			$dbActivated= intval($row['activated']);
			$dbColor = $row['color'];
			if ($dbColor === null) {
				$av = $this->avatarManager->getGuestAvatar($dbName);
				$dbColor = $av->avatarBackgroundColor($dbName);
			} else {
				$dbColor = $this->hexToRgb($dbColor);
			}
			$member = [
					'activated' => ($dbActivated === 1),
					'userid' => $dbUserid,
					'name' => $dbName,
					'id' => $dbMemberId,
					'weight' => $dbWeight,
					'color' => $dbColor
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $member;
	}

	/**
	 * Get a member from its user ID
	 *
	 * @param string $projectId
	 * @param string|null $userid
	 * @return array|null
	 */
	public function getMemberByUserid(string $projectId, ?string $userid): ?array {
		$member = null;
		if ($userid !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'userid', 'name', 'weight', 'color', 'activated')
			   ->from('cospend_members', 'm')
			   ->where(
				   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			   )
			   ->andWhere(
				   $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
			   );
			$req = $qb->executeQuery();

			while ($row = $req->fetch()){
				$dbMemberId = intval($row['id']);
				$dbWeight = floatval($row['weight']);
				$dbUserid = $row['userid'];
				$dbName = $row['name'];
				$dbActivated= intval($row['activated']);
				$dbColor = $row['color'];
				if ($dbColor === null) {
					$av = $this->avatarManager->getGuestAvatar($dbName);
					$dbColor = $av->avatarBackgroundColor($dbName);
				} else {
					$dbColor = $this->hexToRgb($dbColor);
				}
				$member = [
						'activated' => ($dbActivated === 1),
						'userid' => $dbUserid,
						'name' => $dbName,
						'id' => $dbMemberId,
						'weight' => $dbWeight,
						'color' => $dbColor
				];
				break;
			}
			$req->closeCursor();
			$qb->resetQueryParts();
		}
		return $member;
	}

	/**
	 * Edit a bill
	 *
	 * @param string $projectid
	 * @param int $billid
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentmode
	 * @param int|null $categoryid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @return array
	 */
	public function editBill(string $projectid, int $billid, ?string $date, ?string $what, ?int $payer, ?string $payed_for,
							  ?float $amount, ?string $repeat, ?string $paymentmode = null, ?int $categoryid = null,
							  ?int $repeatallactive = null, ?string $repeatuntil = null, ?int $timestamp = null,
							  ?string $comment = null, ?int $repeatfreq = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cospend_bills');

		// set last modification timestamp
		$ts = (new DateTime())->getTimestamp();
		$qb->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT));

		// first check the bill exists
		if ($this->getBill($projectid, $billid) === null) {
			return ['message' => $this->trans->t('There is no such bill')];
		}
		// then edit the hell of it
		if ($what !== null && is_string($what) && $what !== '') {
			$qb->set('what', $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR));
		}

		if ($comment !== null && is_string($comment)) {
			$qb->set('comment', $qb->createNamedParameter($comment, IQueryBuilder::PARAM_STR));
		}

		if ($repeat !== null && $repeat !== '') {
			if (in_array($repeat, ['n', 'd', 'w', 'b', 's', 'm', 'y'])) {
				$qb->set('repeat', $qb->createNamedParameter($repeat, IQueryBuilder::PARAM_STR));
			} else {
				return ['repeat' => $this->trans->t('Invalid value')];
			}
		}

		if ($repeatfreq !== null) {
			$qb->set('repeatfreq', $qb->createNamedParameter($repeatfreq, IQueryBuilder::PARAM_INT));
		}

		if ($repeatuntil !== null) {
			if ($repeatuntil === '') {
				$qb->set('repeatuntil', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
			} else {
				$qb->set('repeatuntil', $qb->createNamedParameter($repeatuntil, IQueryBuilder::PARAM_STR));
			}
		}
		if ($repeatallactive !== null) {
			$qb->set('repeatallactive', $qb->createNamedParameter($repeatallactive, IQueryBuilder::PARAM_INT));
		}
		if ($paymentmode !== null) {
			$qb->set('paymentmode', $qb->createNamedParameter($paymentmode, IQueryBuilder::PARAM_STR));
		}
		if ($categoryid !== null) {
			$qb->set('categoryid', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT));
		}
		// priority to timestamp (moneybuster might send both for a moment)
		if ($timestamp !== null) {
			$qb->set('timestamp', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT));
		} elseif ($date !== null && $date !== '') {
			$dateTs = strtotime($date);
			if ($dateTs !== false) {
				$qb->set('timestamp', $qb->createNamedParameter($dateTs, IQueryBuilder::PARAM_INT));
			} else {
				return ['date' => $this->trans->t('Invalid value')];
			}
		}
		if ($amount !== null) {
			$qb->set('amount', $qb->createNamedParameter($amount, IQueryBuilder::PARAM_STR));
		}
		if ($payer !== null) {
			$member = $this->getMemberById($projectid, $payer);
			if ($member === null) {
				return ['payer' => $this->trans->t('Not a valid choice')];
			} else {
				$qb->set('payerid', $qb->createNamedParameter($payer, IQueryBuilder::PARAM_INT));
			}
		}

		$owerIds = null;
		// check owers
		if ($payed_for !== null && $payed_for !== '') {
			$owerIds = explode(',', $payed_for);
			if (count($owerIds) === 0) {
				return ['payed_for' => $this->trans->t('Invalid value')];
			} else {
				foreach ($owerIds as $owerId) {
					if (!is_numeric($owerId)) {
						return ['payed_for' => $this->trans->t('Invalid value')];
					}
					if ($this->getMemberById($projectid, $owerId) === null) {
						return ['payed_for' => $this->trans->t('Not a valid choice')];
					}
				}
			}
		}

		// do it already!
		$qb->where(
			   $qb->expr()->eq('id', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
		   )
		   ->andWhere(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
		   );
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		// edit the bill owers
		if ($owerIds !== null) {
			// delete old bill owers
			$this->deleteBillOwersOfBill($billid);
			// insert bill owers
			foreach ($owerIds as $owerId) {
				$qb->insert('cospend_bill_owers')
					->values([
						'billid' => $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT),
						'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
					]);
				$qb->executeStatement();
				$qb = $qb->resetQueryParts();
			}
		}

		$this->updateProjectLastChanged($projectid, $ts);

		return ['edited_bill_id' => $billid];
	}

	/**
	 * daily check of repeated bills
	 *
	 * @param int|null $billId
	 * @return array
	 */
	public function cronRepeatBills(?int $billId = null): array {
		$result = [];
		$projects = [];
		$now = new DateTime();
		// in case cron job wasn't executed during several days,
		// continue trying to repeat bills as long as there was at least one repeated
		$continue = true;
		while ($continue) {
			$continue = false;
			// get bills whith repetition flag
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'projectid', 'what', 'timestamp', 'amount', 'payerid', 'repeat', 'repeatallactive', 'repeatfreq')
				->from('cospend_bills', 'b')
				->where(
					$qb->expr()->neq('repeat', $qb->createNamedParameter('n', IQueryBuilder::PARAM_STR))
				);
			// we only repeat one bill
			if (!is_null($billId)) {
				$qb->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
				);
			}
			$req = $qb->executeQuery();
			$bills = [];
			while ($row = $req->fetch()) {
				$id = $row['id'];
				$what = $row['what'];
				$repeat = $row['repeat'];
				$repeatallactive = $row['repeatallactive'];
				$repeatfreq = (int) $row['repeatfreq'];
				$timestamp = $row['timestamp'];
				$projectid = $row['projectid'];
				$bills[] = [
					'id' => $id,
					'what' => $what,
					'repeat' => $repeat,
					'repeatallactive' => $repeatallactive,
					'repeatfreq' => $repeatfreq,
					'projectid' => $projectid,
					'timestamp' => $timestamp
				];
			}
			$req->closeCursor();
			$qb->resetQueryParts();

			foreach ($bills as $bill) {
				// Use DateTimeImmutable instead of DateTime so that $billDate->add() returns a
				// new instance instead of modifying $billDate
				$billDate = DateTimeImmutable::createFromFormat('U', $bill['timestamp']);
				$nextDate = $this->getNextRepetitionDate($bill, $billDate);

				// Unknown repeat interval
				if ($nextDate === null) {
					continue;
				}

				// Repeat if $nextDate is in the past (or today)
				$diff = $now->diff($nextDate);
				if ($nextDate->format('Y-m-d') === $now->format('Y-m-d') || $diff->invert) {
					$newBillId = $this->repeatBill($bill['projectid'], $bill['id'], $nextDate);
					if (!array_key_exists($bill['projectid'], $projects)) {
						$projects[$bill['projectid']] = $this->getProjectInfo($bill['projectid']);
					}
					$result[] = [
						'new_bill_id' => $newBillId,
						'date_orig' => $billDate->format('Y-m-d'),
						'date_repeat' => $nextDate->format('Y-m-d'),
						'what' => $bill['what'],
						'project_name' => $projects[$bill['projectid']]['name'],
					];
					$continue = true;
					// when only repeating one bill, this newly created bill is the one we want to potentially repeat
					$billId = $newBillId;
				}
			}
		}
		return $result;
	}

	/**
	 * duplicate the bill today and give it the repeat flag
	 * remove the repeat flag on original bill
	 *
	 * @param string $projectid
	 * @param int $billid
	 * @param $datetime
	 * @return int|null
	 */
	private function repeatBill(string $projectid, int $billid, $datetime): ?int {
		$bill = $this->getBill($projectid, $billid);

		$owerIds = [];
		if (intval($bill['repeatallactive']) === 1) {
			$pInfo = $this->getProjectInfo($projectid);
			foreach ($pInfo['active_members'] as $am) {
				$owerIds[] = $am['id'];
			}
		} else {
			foreach ($bill['owers'] as $ower) {
				if ($ower['activated']) {
					$owerIds[] = $ower['id'];
				}
			}
		}
		$owerIdsStr = implode(',', $owerIds);
		// if all owers are disabled, don't try to repeat the bill and remove repeat flag
		if (count($owerIds) === 0) {
			$this->editBill($projectid, $billid, null, $bill['what'], $bill['payer_id'], null,
							$bill['amount'], 'n', null, null, 0, null);
			return null;
		}

		// if bill should be repeated until...
		if ($bill['repeatuntil'] !== null && $bill['repeatuntil'] !== '') {
			$untilDate = new DateTime($bill['repeatuntil']);
			// TODO improve this, maybe don't produce bill after repeatuntil...
			if ($datetime >= $untilDate) {
				$bill['repeat'] = 'n';
			}
		}

		$addBillResult = $this->addBill($projectid, null, $bill['what'], $bill['payer_id'],
									$owerIdsStr, $bill['amount'], $bill['repeat'], $bill['paymentmode'],
									$bill['categoryid'], $bill['repeatallactive'], $bill['repeatuntil'],
									$datetime->getTimestamp(), $bill['comment'], $bill['repeatfreq']);

		$newBillId = $addBillResult['inserted_id'] ?? 0;

		$billObj = $this->billMapper->find($newBillId);
		$this->activityManager->triggerEvent(
			ActivityManager::COSPEND_OBJECT_BILL, $billObj,
			ActivityManager::SUBJECT_BILL_CREATE,
			[]
		);

		// now we can remove repeat flag on original bill
		$this->editBill($projectid, $billid, null, $bill['what'], $bill['payer_id'], null,
						$bill['amount'], 'n', null, null, 0, null);
		return $newBillId;
	}

	/**
	 * Get next repetition date of a bill
	 *
	 * @param array $bill
	 * @param DateTimeImmutable $billDate
	 * @return DateTime|null
	 */
	private function getNextRepetitionDate(array $bill, DateTimeImmutable $billDate): ?DateTime {
		$nextDate = null;
		switch ($bill['repeat']) {
			case 'd':
				if ($bill['repeatfreq'] < 2) {
					$tmpImmuDate = $billDate->add(new DateInterval('P1D'));
				} else {
					$tmpImmuDate = $billDate->add(new DateInterval('P' . $bill['repeatfreq'] . 'D'));
				}
				$nextDate = new DateTime();
				$nextDate->setTimestamp($tmpImmuDate->getTimestamp());
				break;

			case 'w':
				if ($bill['repeatfreq'] < 2) {
					$tmpImmuDate = $billDate->add(new DateInterval('P7D'));
				} else {
					$nbDays = 7 * $bill['repeatfreq'];
					$tmpImmuDate = $billDate->add(new DateInterval('P' . $nbDays . 'D'));
				}
				$nextDate = new DateTime();
				$nextDate->setTimestamp($tmpImmuDate->getTimestamp());
				break;

			// bi weekly
			case 'b':
				$tmpImmuDate = $billDate->add(new DateInterval('P14D'));
				$nextDate = new DateTime();
				$nextDate->setTimestamp($tmpImmuDate->getTimestamp());
				break;

			// semi monthly
			case 's':
				$day = intval($billDate->format('d'));
				$month = intval($billDate->format('m'));
				$year = intval($billDate->format('Y'));

				$nextDate = new DateTime();
				// first of next month
				if ($day >= 15) {
					if ($month === 12) {
						$nextYear = $year + 1;
						$nextMonth = 1;
						$nextDate->setDate($nextYear, $nextMonth, 1);
					} else {
						$nextMonth = $month + 1;
						$nextDate->setDate($year, $nextMonth, 1);
					}
				} else {
					// 15 of same month
					$nextDate->setDate($year, $month, 15);
				}
				break;

			case 'm':
				$freq = ($bill['repeatfreq'] < 2) ? 1 : $bill['repeatfreq'];
				$billMonth = intval($billDate->format('m'));
				$yearDelta = intdiv($billMonth + $freq - 1, 12);
				$nextYear = intval($billDate->format('Y')) + $yearDelta;
				$nextMonth = (($billMonth + $freq - 1) % 12) + 1;

				// same day of month if possible, otherwise at end of month
				$nextDate = new DateTime();
				// to get the time
				$nextDate->setTimestamp($billDate->getTimestamp());
				$nextDate->setDate($nextYear, $nextMonth, 1);
				$billDay = intval($billDate->format('d'));
				$nbDaysInNextMonth = intval($nextDate->format('t'));
				if ($billDay > $nbDaysInNextMonth) {
					$nextDate->setDate($nextYear, $nextMonth, $nbDaysInNextMonth);
				} else {
					$nextDate->setDate($nextYear, $nextMonth, $billDay);
				}
				break;

			case 'y':
				$freq = ($bill['repeatfreq'] < 2) ? 1 : $bill['repeatfreq'];
				$billYear = intval($billDate->format('Y'));
				$billMonth = intval($billDate->format('m'));
				$billDay = intval($billDate->format('d'));
				$nextYear = $billYear + $freq;

				// same day of month if possible, otherwise at end of month + same month
				$nextDate = new DateTime();
				// to get the time
				$nextDate->setTimestamp($billDate->getTimestamp());
				$nextDate->setDate($nextYear, $billMonth, 1);
				$nbDaysInNextMonth = intval($nextDate->format('t'));
				if ($billDay > $nbDaysInNextMonth) {
					$nextDate->setDate($nextYear, $billMonth, $nbDaysInNextMonth);
				} else {
					$nextDate->setDate($nextYear, $billMonth, $billDay);
				}
				break;
		}

		return $nextDate;
	}

	/**
	 * Add a new category
	 *
	 * @param string $projectid
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return int
	 */
	public function addCategory(string $projectid, string $name, ?string $icon, string $color, ?int $order = 0): int {
		$qb = $this->db->getQueryBuilder();

		$encIcon = $icon;
		if ($icon !== null && $icon !== '') {
			$encIcon = urlencode($icon);
		}
		$qb->insert('cospend_project_categories')
			->values([
				'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
				'encoded_icon' => $qb->createNamedParameter($encIcon, IQueryBuilder::PARAM_STR),
				'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
				'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
				'order' => $qb->createNamedParameter(is_null($order) ? 0 : $order, IQueryBuilder::PARAM_INT)
			]);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		return $qb->getLastInsertId();
	}

	/**
	 * Get a category
	 *
	 * @param string $projectId
	 * @param int $categoryid
	 * @return array|null
	 */
	private function getCategory(string $projectId, int $categoryid): ?array {
		$category = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'projectid', 'encoded_icon', 'color')
		   ->from('cospend_project_categories', 'c')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('id', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbCategoryId = intval($row['id']);
			$dbName = $row['name'];
			$dbIcon = urldecode($row['encoded_icon']);
			$dbColor = $row['color'];
			$category = [
					'name' => $dbName,
					'icon' => $dbIcon,
					'color' => $dbColor,
					'id' => $dbCategoryId,
					'projectid' => $projectId
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $category;
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectid
	 * @param int $categoryid
	 * @return array
	 */
	public function deleteCategory(string $projectid, int $categoryid): array {
		$categoryToDelete = $this->getCategory($projectid, $categoryid);
		if ($categoryToDelete !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cospend_project_categories')
			   ->where(
				   $qb->expr()->eq('id', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
			   )
			   ->andWhere(
				   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			   );
			$qb->executeStatement();
			$qb->resetQueryParts();

			// then get rid of this category in bills
			$qb = $this->db->getQueryBuilder();
			$qb->update('cospend_bills');
			$qb->set('categoryid', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('categoryid', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
			)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('Not found')];
		}
	}

	/**
	 * Save the manual category order
	 *
	 * @param string $projectid
	 * @param array $order
	 * @return bool
	 */
	public function saveCategoryOrder(string $projectid, array $order): bool {
		$qb = $this->db->getQueryBuilder();
		foreach ($order as $o) {
			$qb->update('cospend_project_categories');
			$qb->set('order', $qb->createNamedParameter($o['order'], IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($o['id'], IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();
		}
		return true;
	}

	/**
	 * Edit a category
	 *
	 * @param string $projectid
	 * @param int $categoryid
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return array
	 */
	public function editCategory(string $projectid, int $categoryid, ?string $name = null,
								?string $icon = null, ?string $color = null): array {
		if ($name !== null && $name !== '') {
			$encIcon = $icon;
			if ($icon !== null && $icon !== '') {
				$encIcon = urlencode($icon);
			}
			if ($this->getCategory($projectid, $categoryid) !== null) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('cospend_project_categories');
				$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
				$qb->set('encoded_icon', $qb->createNamedParameter($encIcon, IQueryBuilder::PARAM_STR));
				$qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				);
				$qb->executeStatement();
				$qb->resetQueryParts();

				return $this->getCategory($projectid, $categoryid);
			} else {
				return ['message' => $this->trans->t('This project have no such category')];
			}
		} else {
			return ['message' => $this->trans->t('Incorrect field values')];
		}
	}

	/**
	 * Add a currency
	 *
	 * @param string $projectid
	 * @param string $name
	 * @param float $rate
	 * @return int
	 */
	public function addCurrency(string $projectid, string $name, float $rate): int {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('cospend_currencies')
			->values([
				'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
				'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
				'exchange_rate' => $qb->createNamedParameter($rate, IQueryBuilder::PARAM_STR)
			]);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		return $qb->getLastInsertId();
	}

	/**
	 * Get one currency
	 *
	 * @param string $projectId
	 * @param int $currencyid
	 * @return array|null
	 */
	private function getCurrency(string $projectId, int $currencyid): ?array {
		$currency = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'exchange_rate', 'projectid')
		   ->from('cospend_currencies', 'c')
		   ->where(
			   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   )
		   ->andWhere(
			   $qb->expr()->eq('id', $qb->createNamedParameter($currencyid, IQueryBuilder::PARAM_INT))
		   );
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbCurrencyId = intval($row['id']);
			$dbRate = floatval($row['exchange_rate']);
			$dbName = $row['name'];
			$currency = [
					'name' => $dbName,
					'id' => $dbCurrencyId,
					'exchange_rate' => $dbRate,
					'projectid' => $projectId
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $currency;
	}

	/**
	 * Delete one currency
	 *
	 * @param string $projectid
	 * @param int $currencyid
	 * @return array
	 */
	public function deleteCurrency(string $projectid, int $currencyid): array {
		$currencyToDelete = $this->getCurrency($projectid, $currencyid);
		if ($currencyToDelete !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cospend_currencies')
			   ->where(
				   $qb->expr()->eq('id', $qb->createNamedParameter($currencyid, IQueryBuilder::PARAM_INT))
			   )
			   ->andWhere(
				   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			   );
			$qb->executeStatement();
			$qb->resetQueryParts();

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('Not found')];
		}
	}

	/**
	 * Edit a currency
	 *
	 * @param string $projectid
	 * @param int $currencyid
	 * @param string $name
	 * @param float $exchange_rate
	 * @return array
	 */
	public function editCurrency(string $projectid, int $currencyid, string $name, float $exchange_rate): array {
		if ($name !== '' && $exchange_rate !== 0.0) {
			if ($this->getCurrency($projectid, $currencyid) !== null) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('cospend_currencies');
				$qb->set('exchange_rate', $qb->createNamedParameter($exchange_rate, IQueryBuilder::PARAM_STR));
				$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($currencyid, IQueryBuilder::PARAM_INT))
				)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					);
				$qb->executeStatement();
				$qb->resetQueryParts();

				return $this->getCurrency($projectid, $currencyid);
			} else {
				return ['message' => $this->trans->t('This project have no such currency')];
			}
		} else {
			return ['message' => $this->trans->t('Incorrect field values')];
		}
	}

	/**
	 * Add a user shared access to a project
	 *
	 * @param string $projectid
	 * @param string $userid
	 * @param string $fromUserId
	 * @param int $accesslevel
	 * @param bool $manually_added
	 * @return array
	 */
	public function addUserShare(string $projectid, string $userid, string $fromUserId,
								int $accesslevel = Application::ACCESS_PARTICIPANT, bool $manually_added = true): array {
		$user = $this->userManager->get($userid);
		if ($user !== null && $userid !== $fromUserId) {
			$userName = $user->getDisplayName();
			$qb = $this->db->getQueryBuilder();
			$projectInfo = $this->getProjectInfo($projectid);
			// check if someone tries to share the project with its owner
			if ($userid !== $projectInfo['userid']) {
				// check if user share exists
				$qb->select('userid', 'projectid')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbuserId = null;
				while ($row = $req->fetch()){
					$dbuserId = $row['userid'];
					break;
				}
				$req->closeCursor();
				$qb = $qb->resetQueryParts();

				if ($dbuserId === null) {
					if ($this->getUserMaxAccessLevel($fromUserId, $projectid) >= $accesslevel) {
						$qb->insert('cospend_shares')
							->values([
								'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
								'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
								'type' => $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR),
								'accesslevel' => $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT),
								'manually_added' => $qb->createNamedParameter($manually_added ? 1 : 0, IQueryBuilder::PARAM_INT),
							]);
						$qb->executeStatement();
						$qb = $qb->resetQueryParts();

						$insertedShareId = $qb->getLastInsertId();
						$response = [
							'id' => $insertedShareId,
							'name' => $userName,
						];

						// activity
						$projectObj = $this->projectMapper->find($projectid);
						$this->activityManager->triggerEvent(
							ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
							ActivityManager::SUBJECT_PROJECT_SHARE,
							['who' => $userid, 'type' => 'u']
						);

						// SEND NOTIFICATION
						$manager = $this->notificationManager;
						$notification = $manager->createNotification();

						$acceptAction = $notification->createAction();
						$acceptAction->setLabel('accept')
							->setLink('/apps/cospend', 'GET');

						$declineAction = $notification->createAction();
						$declineAction->setLabel('decline')
							->setLink('/apps/cospend', 'GET');

						$notification->setApp('cospend')
							->setUser($userid)
							->setDateTime(new DateTime())
							->setObject('addusershare', $projectid)
							->setSubject('add_user_share', [$fromUserId, $projectInfo['name']])
							->addAction($acceptAction)
							->addAction($declineAction);

						$manager->notify($notification);

						return $response;
					} else {
						return ['message' => $this->trans->t('You are not authorized to give such access level')];
					}
				} else {
					return ['message' => $this->trans->t('Already shared with this user')];
				}
			} else {
				return ['message' => $this->trans->t('Impossible to share the project with its owner')];
			}
		} else {
			return ['message' => $this->trans->t('No such user')];
		}
	}

	/**
	 * Add public share access (public link with token)
	 *
	 * @param string $projectid
	 * @return array
	 */
	public function addPublicShare(string $projectid): array {
		$qb = $this->db->getQueryBuilder();
		// generate token
		$token = md5($projectid.rand());

		$qb->insert('cospend_shares')
			->values([
				'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
				'userid' => $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR),
				'type' => $qb->createNamedParameter('l', IQueryBuilder::PARAM_STR)
			]);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$insertedShareId = $qb->getLastInsertId();

		//// activity
		//$projectObj = $this->projectMapper->find($projectid);
		//$this->activityManager->triggerEvent(
		//    ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
		//    ActivityManager::SUBJECT_PROJECT_SHARE,
		//    ['who' => $userid, 'type' => 'u']
		//);

		//// SEND NOTIFICATION
		//$projectInfo = $this->getProjectInfo($projectid);
		//$manager = $this->notificationManager;
		//$notification = $manager->createNotification();

		//$acceptAction = $notification->createAction();
		//$acceptAction->setLabel('accept')
		//    ->setLink('/apps/cospend', 'GET');

		//$declineAction = $notification->createAction();
		//$declineAction->setLabel('decline')
		//    ->setLink('/apps/cospend', 'GET');

		//$notification->setApp('cospend')
		//    ->setUser($userid)
		//    ->setDateTime(new DateTime())
		//    ->setObject('addusershare', $projectid)
		//    ->setSubject('add_user_share', [$fromUserId, $projectInfo['name']])
		//    ->addAction($acceptAction)
		//    ->addAction($declineAction)
		//    ;

		//$manager->notify($notification);

		return [
			'token' => $token,
			'id' => $insertedShareId
		];
	}

	/**
	 * Change shared access permissions
	 *
	 * @param string $projectid
	 * @param int $shid
	 * @param int $accesslevel
	 * @return array
	 */
	public function editShareAccessLevel(string $projectid, int $shid, int $accesslevel): array {
		// check if user share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		while ($row = $req->fetch()){
			$dbId = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if ($dbId !== null) {
			// set the accesslevel
			$qb->update('cospend_shares')
				->set('accesslevel', $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT))
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('No such share')];
		}
	}

	/**
	 * Change guest access permissions
	 *
	 * @param string $projectid
	 * @param int $accesslevel
	 * @return array
	 */
	public function editGuestAccessLevel(string $projectid, int $accesslevel): array {
		// check if project exists
		$qb = $this->db->getQueryBuilder();

		// set the access level
		$qb->update('cospend_projects')
			->set('guestaccesslevel', $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();

		return ['success' => true];
	}

	/**
	 * Delete user shared access
	 *
	 * @param string $projectid
	 * @param int $shid
	 * @param string|null $fromUserId
	 * @return array
	 */
	public function deleteUserShare(string $projectid, int $shid, ?string $fromUserId = null): array {
		// check if user share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		$dbuserId = null;
		while ($row = $req->fetch()){
			$dbId = $row['id'];
			$dbuserId = $row['userid'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if ($dbId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			// activity
			$projectObj = $this->projectMapper->find($projectid);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
				ActivityManager::SUBJECT_PROJECT_UNSHARE,
				['who' => $dbuserId, 'type' => 'u']
			);

			// SEND NOTIFICATION
			if (!is_null($fromUserId)) {
				$projectInfo = $this->getProjectInfo($projectid);

				$manager = $this->notificationManager;
				$notification = $manager->createNotification();

				$acceptAction = $notification->createAction();
				$acceptAction->setLabel('accept')
					->setLink('/apps/cospend', 'GET');

				$declineAction = $notification->createAction();
				$declineAction->setLabel('decline')
					->setLink('/apps/cospend', 'GET');

				$notification->setApp('cospend')
					->setUser($dbuserId)
					->setDateTime(new DateTime())
					->setObject('deleteusershare', $projectid)
					->setSubject('delete_user_share', [$fromUserId, $projectInfo['name']])
					->addAction($acceptAction)
					->addAction($declineAction)
					;

				$manager->notify($notification);
			}

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('No such share')];
		}
	}

	/**
	 * Delete public shared access
	 *
	 * @param string $projectid
	 * @param int $shid
	 * @return array
	 */
	public function deletePublicShare(string $projectid, int $shid): array {
		// check if public share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('l', IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		while ($row = $req->fetch()){
			$dbId = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if ($dbId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter('l', IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			//// activity
			//$projectObj = $this->projectMapper->find($projectid);
			//$this->activityManager->triggerEvent(
			//    ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
			//    ActivityManager::SUBJECT_PROJECT_UNSHARE,
			//    ['who' => $dbuserId, 'type' => 'u']
			//);

			//// SEND NOTIFICATION
			//$projectInfo = $this->getProjectInfo($projectid);

			//$manager = $this->notificationManager;
			//$notification = $manager->createNotification();

			//$acceptAction = $notification->createAction();
			//$acceptAction->setLabel('accept')
			//    ->setLink('/apps/cospend', 'GET');

			//$declineAction = $notification->createAction();
			//$declineAction->setLabel('decline')
			//    ->setLink('/apps/cospend', 'GET');

			//$notification->setApp('cospend')
			//    ->setUser($dbuserId)
			//    ->setDateTime(new DateTime())
			//    ->setObject('deleteusershare', $projectid)
			//    ->setSubject('delete_user_share', [$fromUserId, $projectInfo['name']])
			//    ->addAction($acceptAction)
			//    ->addAction($declineAction)
			//    ;

			//$manager->notify($notification);

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('No such shared access')];
		}
	}

	/**
	 * Add group shared access
	 *
	 * @param string $projectid
	 * @param string $groupid
	 * @param string|null $fromUserId
	 * @return array
	 */
	public function addGroupShare(string $projectid, string $groupid, ?string $fromUserId = null): array {
		if ($this->groupManager->groupExists($groupid)) {
			$groupName = $this->groupManager->get($groupid)->getDisplayName();
			$qb = $this->db->getQueryBuilder();
			// check if user share exists
			$qb->select('userid', 'projectid')
				->from('cospend_shares', 's')
				->where(
					$qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('userid', $qb->createNamedParameter($groupid, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			$dbGroupId = null;
			while ($row = $req->fetch()){
				$dbGroupId = $row['userid'];
				break;
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();

			if ($dbGroupId === null) {
				$qb->insert('cospend_shares')
					->values([
						'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
						'userid' => $qb->createNamedParameter($groupid, IQueryBuilder::PARAM_STR),
						'type' => $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR)
					]);
				$qb->executeStatement();
				$qb = $qb->resetQueryParts();

				$insertedShareId = $qb->getLastInsertId();

				// activity
				$projectObj = $this->projectMapper->find($projectid);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
					ActivityManager::SUBJECT_PROJECT_SHARE,
					['who' => $groupid, 'type' => 'g']
				);

				return [
					'id' => $insertedShareId,
					'name' => $groupName,
				];
			} else {
				return ['message' => $this->trans->t('Already shared with this group')];
			}
		} else {
			return ['message' => $this->trans->t('No such group')];
		}
	}

	/**
	 * Delete group shared access
	 *
	 * @param string $projectid
	 * @param int shid
	 * @param string|null $fromUserId
	 * @return array
	 */
	public function deleteGroupShare(string $projectid, int $shid, ?string $fromUserId = null): array {
		// check if group share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'projectid', 'id')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbGroupId = null;
		while ($row = $req->fetch()){
			$dbGroupId = $row['userid'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if ($dbGroupId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			// activity
			$projectObj = $this->projectMapper->find($projectid);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
				ActivityManager::SUBJECT_PROJECT_UNSHARE,
				['who' => $dbGroupId, 'type' => 'g']
			);

			return ['success' => true];
		} else {
			return ['message' => $this->trans->t('No such share')];
		}
	}

	/**
	 * Add circle shaed access
	 *
	 * @param string $projectid
	 * @param string $circleid
	 * @param string|null $fromUserId
	 * @return array
	 */
	public function addCircleShare(string $projectid, string $circleid, ?string $fromUserId = null): array {
		// check if circleId exists
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
			$circlesManager->startSuperSession();

			$exists = true;
			$circleName = '';
			try {
				$circle = $circlesManager->getCircle($circleid);
				$circleName = $circle->getDisplayName();
			} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				$exists = false;
			}

			if ($circleid !== '' && $exists) {
				$qb = $this->db->getQueryBuilder();
				// check if circle share exists
				$qb->select('userid', 'projectid')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($circleid, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbCircleId = null;
				while ($row = $req->fetch()){
					$dbCircleId = $row['userid'];
					break;
				}
				$req->closeCursor();
				$qb = $qb->resetQueryParts();

				if ($dbCircleId === null) {
					$qb->insert('cospend_shares')
						->values([
							'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
							'userid' => $qb->createNamedParameter($circleid, IQueryBuilder::PARAM_STR),
							'type' => $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR)
						]);
					$qb->executeStatement();
					$qb = $qb->resetQueryParts();

					$insertedShareId = $qb->getLastInsertId();

					// activity
					$projectObj = $this->projectMapper->find($projectid);
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
						ActivityManager::SUBJECT_PROJECT_SHARE,
						['who' => $circleid, 'type' => 'c']
					);

					$circlesManager->stopSession();
					return [
						'id' => $insertedShareId,
						'name' => $circleName,
					];
				} else {
					$circlesManager->stopSession();
					return ['message' => $this->trans->t('Already shared with this circle')];
				}
			} else {
				$circlesManager->stopSession();
				return ['message' => $this->trans->t('No such circle')];
			}
		} else {
			return ['message' => $this->trans->t('Circles app is not enabled')];
		}
	}

	/**
	 * Delete circle shared access
	 *
	 * @param string $projectid
	 * @param int $shid
	 * @param string|null $fromUserId
	 * @return array
	 */
	public function deleteCircleShare(string $projectid, int $shid, ?string $fromUserId = null): array {
		// check if circle share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'projectid', 'id')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbCircleId = null;
		while ($row = $req->fetch()){
			$dbCircleId = $row['userid'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if ($dbCircleId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb->resetQueryParts();

			// activity
			$projectObj = $this->projectMapper->find($projectid);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
				ActivityManager::SUBJECT_PROJECT_UNSHARE,
				['who' => $dbCircleId, 'type' => 'c']
			);

			$response = ['success' => true];
		} else {
			$response = ['message' => $this->trans->t('No such share')];
		}
		return $response;
	}

	/**
	 * Export settlement plan in CSV
	 *
	 * @param string $projectid
	 * @param string $userId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function exportCsvSettlement(string $projectid, string $userId, ?int $centeredOn = null, ?int $maxTimestamp = null): array {
		// create export directory if needed
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);

		// create file
		if ($folder->nodeExists($projectid.'-settlement.csv')) {
			$folder->get($projectid.'-settlement.csv')->delete();
		}
		$file = $folder->newFile($projectid.'-settlement.csv');
		$handler = $file->fopen('w');
		fwrite(
			$handler,
			'"' . $this->trans->t('Who pays?')
				. '","' . $this->trans->t('To whom?')
				. '","' . $this->trans->t('How much?')
				. '"' . "\n"
		);
		$settlement = $this->getProjectSettlement($projectid, $centeredOn, $maxTimestamp);
		$transactions = $settlement['transactions'];

		$members = $this->getMembers($projectid);
		$memberIdToName = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
		}

		foreach ($transactions as $transaction) {
			fwrite($handler, '"'.$memberIdToName[$transaction['from']].'","'.$memberIdToName[$transaction['to']].'",'.floatval($transaction['amount'])."\n");
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $projectid . '-settlement.csv'];
	}

	/**
	 * Create directory where things will be exported
	 *
	 * @param Folder $userFolder
	 * @param string $outPath
	 * @return string
	 */
	private function createAndCheckExportDirectory(Folder $userFolder, string $outPath): string {
		if (!$userFolder->nodeExists($outPath)) {
			$userFolder->newFolder($outPath);
		}
		if ($userFolder->nodeExists($outPath)) {
			$folder = $userFolder->get($outPath);
			if ($folder->getType() !== FileInfo::TYPE_FOLDER) {
				return $this->trans->t('%1$s is not a folder', [$outPath]);
			} elseif (!$folder->isCreatable()) {
				return $this->trans->t('%1$s is not writeable', [$outPath]);
			} else {
				return '';
			}
		} else {
			return $this->trans->t('Impossible to create %1$s', [$outPath]);
		}
	}

	/**
	 * @param string $projectid
	 * @param string $userId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param string|null $paymentMode
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param bool $showDisabled
	 * @param int|null $currencyId
	 * @return array
	 */
	public function exportCsvStatistics(string $projectid, string $userId, ?int $tsMin = null, ?int $tsMax = null,
										?string $paymentMode = null, ?int $category = null,
										?float $amountMin = null, ?float $amountMax = null,
										bool $showDisabled = true, ?int $currencyId = null): array {
		// create export directory if needed
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);

		// create file
		if ($folder->nodeExists($projectid.'-stats.csv')) {
			$folder->get($projectid.'-stats.csv')->delete();
		}
		$file = $folder->newFile($projectid.'-stats.csv');
		$handler = $file->fopen('w');
		fwrite($handler, $this->trans->t('Member name').','. $this->trans->t('Paid').','. $this->trans->t('Spent').','. $this->trans->t('Balance')."\n");
		$allStats = $this->getProjectStatistics($projectid, 'lowername', $tsMin, $tsMax, $paymentMode,
												$category, $amountMin, $amountMax, $showDisabled, $currencyId);
		$stats = $allStats['stats'];

		foreach ($stats as $stat) {
			fwrite($handler, '"'.$stat['member']['name'].'",'.floatval($stat['paid']).','.floatval($stat['spent']).','.floatval($stat['balance'])."\n");
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $projectid . '-stats.csv'];
	}

	/**
	 * Export project in CSV
	 *
	 * @param string $projectid
	 * @param string|null $name
	 * @param string $userId
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function exportCsvProject(string $projectid, string $userId, ?string $name = null): array {
		// create export directory if needed
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);

		$projectInfo = $this->getProjectInfo($projectid);

		// create file
		$filename = $projectid.'.csv';
		if ($name !== null) {
			$filename = $name;
			if (!endswith($filename, '.csv')) {
				$filename .= '.csv';
			}
		}
		if ($folder->nodeExists($filename)) {
			$folder->get($filename)->delete();
		}
		$file = $folder->newFile($filename);
		$handler = $file->fopen('w');
		fwrite($handler, "what,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,comment\n");
		$members = $projectInfo['members'];
		$memberIdToName = [];
		$memberIdToWeight = [];
		$memberIdToActive = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
			$memberIdToWeight[$member['id']] = $member['weight'];
			$memberIdToActive[$member['id']] = intval($member['activated']);
			fwrite($handler, 'deleteMeIfYouWant,1,1970-01-01,0,"'.$member['name'].'",'.floatval($member['weight']).','.
							  intval($member['activated']).',"'.$member['name'].'",n,,,,,,'."\n");
		}
		$bills = $this->getBills($projectid);
		foreach ($bills as $bill) {
			$owerNames = [];
			foreach ($bill['owers'] as $ower) {
				$owerNames[] = $ower['name'];
			}
			$owersTxt = implode(', ', $owerNames);

			$payer_id = $bill['payer_id'];
			$payer_name = $memberIdToName[$payer_id];
			$payer_weight = $memberIdToWeight[$payer_id];
			$payer_active = $memberIdToActive[$payer_id];
			$dateTime = DateTime::createFromFormat('U', $bill['timestamp']);
			$oldDateStr = $dateTime->format('Y-m-d');
			fwrite($handler, '"'.$bill['what'].'",'.floatval($bill['amount']).','.$oldDateStr.','.$bill['timestamp'].
							 ',"'.$payer_name.'",'.
							 floatval($payer_weight).','.$payer_active.',"'.$owersTxt.'",'.$bill['repeat'].
							 ','.$bill['repeatfreq'].','.$bill['repeatallactive'].','.
							 $bill['repeatuntil'].','.$bill['categoryid'].','.$bill['paymentmode'].
							 ',"'.urlencode($bill['comment']).'"'."\n");
		}

		// write categories
		$categories = $projectInfo['categories'];
		if (count($categories) > 0) {
			fwrite($handler, "\n");
			fwrite($handler, "categoryname,categoryid,icon,color\n");
			foreach ($categories as $id => $cat) {
				fwrite($handler, '"'.$cat['name'].'",'.intval($id).',"'.$cat['icon'].'","'.$cat['color'].'"'."\n");
			}
		}

		// write currencies
		$currencies = $projectInfo['currencies'];
		if (count($currencies) > 0) {
			fwrite($handler, "\n");
			fwrite($handler, "currencyname,exchange_rate\n");
			// main currency
			fwrite($handler, '"'.$projectInfo['currencyname'].'",1'."\n");
			foreach ($currencies as $cur) {
				fwrite($handler, '"'.$cur['name'].'",'.floatval($cur['exchange_rate'])."\n");
			}
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $filename];
	}

	/**
	 * Import CSV project file
	 *
	 * @param string $path
	 * @param string $userId
	 * @return array
	 */
	public function importCsvProject(string $path, string $userId): array {
		$cleanPath = str_replace(array('../', '..\\'), '',  $path);
		$userFolder = $this->root->getUserFolder($userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file->getType() === FileInfo::TYPE_FILE) {
				if (($handle = $file->fopen('r')) !== false) {
					$columns = [];
					$membersWeight = [];
					$membersActive = [];
					$bills = [];
					$currencies = [];
					$mainCurrencyName = null;
					$categories = [];
					$categoryIdConv = [];
					$previousLineEmpty = false;
					$currentSection = null;
					$row = 0;
					while (($data = fgetcsv($handle, 1000, ',')) !== false) {
						if ($data === [null]) {
							$previousLineEmpty = true;
						} elseif ($row === 0 || $previousLineEmpty) {
							// determine which section we're entering
							$previousLineEmpty = false;
							$nbCol = count($data);
							$columns = [];
							for ($c = 0; $c < $nbCol; $c++) {
								$columns[$data[$c]] = $c;
							}
							if (array_key_exists('what', $columns) and
								array_key_exists('amount', $columns) and
								(array_key_exists('date', $columns) || array_key_exists('timestamp', $columns)) and
								array_key_exists('payer_name', $columns) and
								array_key_exists('payer_weight', $columns) and
								array_key_exists('owers', $columns)
							) {
								$currentSection = 'bills';
							} elseif (array_key_exists('icon', $columns) and
									 array_key_exists('color', $columns) and
									 array_key_exists('categoryid', $columns) and
									 array_key_exists('categoryname', $columns)
							) {
								$currentSection = 'categories';
							} elseif (array_key_exists('exchange_rate', $columns) and
									 array_key_exists('currencyname', $columns)
							) {
								$currentSection = 'currencies';
							} else {
								fclose($handle);
								return ['message' => $this->trans->t('Malformed CSV, bad column names at line %1$s', [$row + 1])];
							}
						} else {
							// normal line : bill or category
							$previousLineEmpty = false;
							if ($currentSection === 'categories') {
								$icon = $data[$columns['icon']];
								$color = $data[$columns['color']];
								$categoryid = $data[$columns['categoryid']];
								$categoryname = $data[$columns['categoryname']];
								$categories[] = [
									'icon' => $icon,
									'color' => $color,
									'id' => $categoryid,
									'name' => $categoryname,
								];
							} elseif ($currentSection === 'currencies') {
								$name = $data[$columns['currencyname']];
								$exchange_rate = $data[$columns['exchange_rate']];
								if (floatval($exchange_rate) === 1.0) {
									$mainCurrencyName = $name;
								} else {
									$currencies[] = [
										'name' => $name,
										'exchange_rate' => $exchange_rate,
									];
								}
							} elseif ($currentSection === 'bills') {
								$what = $data[$columns['what']];
								$amount = $data[$columns['amount']];
								// priority to timestamp
								if (array_key_exists('timestamp', $columns)) {
									$timestamp = $data[$columns['timestamp']];
								} elseif (array_key_exists('date', $columns)) {
									$date = $data[$columns['date']];
									$timestamp = strtotime($date);
								}
								$payer_name = $data[$columns['payer_name']];
								$payer_weight = $data[$columns['payer_weight']];
								$owers = $data[$columns['owers']];
								$payer_active = array_key_exists('payer_active', $columns) ? $data[$columns['payer_active']] : 1;
								$repeat = array_key_exists('repeat', $columns) ? $data[$columns['repeat']] : 'n';
								$categoryid = array_key_exists('categoryid', $columns) ? intval($data[$columns['categoryid']]) : null;
								$paymentmode = array_key_exists('paymentmode', $columns) ? $data[$columns['paymentmode']] : null;
								$repeatallactive = array_key_exists('repeatallactive', $columns) ? $data[$columns['repeatallactive']] : 0;
								$repeatuntil = array_key_exists('repeatuntil', $columns) ? $data[$columns['repeatuntil']] : null;
								$repeatfreq = array_key_exists('repeatfreq', $columns) ? $data[$columns['repeatfreq']] : 1;
								$comment = array_key_exists('comment', $columns) ? urldecode($data[$columns['comment']] ?? '') : null;

								// manage members
								$membersActive[$payer_name] = intval($payer_active);
								if (is_numeric($payer_weight)) {
									$membersWeight[$payer_name] = floatval($payer_weight);
								} else {
									fclose($handle);
									return ['message' => $this->trans->t('Malformed CSV, bad payer weight on line %1$s', [$row + 1])];
								}
								if (strlen($owers) === 0) {
									fclose($handle);
									return ['message' => $this->trans->t('Malformed CSV, bad owers on line %1$s', [$row + 1])];
								}
								if ($what !== 'deleteMeIfYouWant') {
									$owersArray = explode(', ', $owers);
									foreach ($owersArray as $ower) {
										if (strlen($ower) === 0) {
											fclose($handle);
											return ['message' => $this->trans->t('Malformed CSV, bad owers on line %1$s', [$row + 1])];
										}
										if (!array_key_exists($ower, $membersWeight)) {
											$membersWeight[$ower] = 1.0;
										}
									}
									if (!is_numeric($amount)) {
										fclose($handle);
										return ['message' => $this->trans->t('Malformed CSV, bad amount on line %1$s', [$row + 1])];
									}
									$bills[] = [
										'what' => $what,
										'comment' => $comment,
										'timestamp' => $timestamp,
										'amount' => $amount,
										'payer_name' => $payer_name,
										'owers' => $owersArray,
										'paymentmode' => $paymentmode,
										'categoryid' => $categoryid,
										'repeat' => $repeat,
										'repeatuntil' => $repeatuntil,
										'repeatallactive' => $repeatallactive,
										'repeatfreq' => $repeatfreq,
									];
								}
							}
						}
						$row++;
					}
					fclose($handle);

					$memberNameToId = [];

					// add project
					$user = $this->userManager->get($userId);
					$userEmail = $user->getEMailAddress();
					$projectName = str_replace('.csv', '', $file->getName());
					$projectid = slugify($projectName);
					$createDefaultCategories = (count($categories) === 0);
					$projResult = $this->createProject($projectName, $projectid, '', $userEmail, $userId,
													   $createDefaultCategories);
					if (!isset($projResult['id'])) {
						return ['message' => $this->trans->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
					}
					// set project main currency
					if ($mainCurrencyName !== null) {
						$this->editProject($projectid, $projectName, null, null, null, $mainCurrencyName);
					}
					// add categories
					foreach ($categories as $cat) {
						$insertedCatId = $this->addCategory($projectid, $cat['name'], $cat['icon'], $cat['color']);
						if (!is_numeric($insertedCatId)) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding category %1$s', [$cat['name']])];
						}
						$categoryIdConv[$cat['id']] = $insertedCatId;
					}
					// add currencies
					foreach ($currencies as $cur) {
						$insertedCurId = $this->addCurrency($projectid, $cur['name'], $cur['exchange_rate']);
						if (!is_numeric($insertedCurId)) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding currency %1$s', [$cur['name']])];
						}
					}
					// add members
					foreach ($membersWeight as $memberName => $weight) {
						$insertedMember = $this->addMember($projectid, $memberName, $weight, $membersActive[$memberName]);
						if (!is_array($insertedMember)) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding member %1$s', [$memberName])];
						}
						$memberNameToId[$memberName] = $insertedMember['id'];
					}
					// add bills
					foreach ($bills as $bill) {
						// manage category id if this is a custom category
						$catId = $bill['categoryid'];
						if (is_numeric($catId) && intval($catId) > 0) {
							$catId = $categoryIdConv[$catId];
						}
						$payerId = $memberNameToId[$bill['payer_name']];
						$owerIds = [];
						foreach ($bill['owers'] as $owerName) {
							$owerIds[] = $memberNameToId[$owerName];
						}
						$owerIdsStr = implode(',', $owerIds);
						$addBillResult = $this->addBill($projectid, null, $bill['what'], $payerId,
														$owerIdsStr, $bill['amount'], $bill['repeat'],
														$bill['paymentmode'], $catId, $bill['repeatallactive'],
														$bill['repeatuntil'], $bill['timestamp'], $bill['comment'], $bill['repeatfreq']);
						if (!isset($addBillResult['inserted_id'])) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding bill %1$s', [$bill['what']])];
						}
					}

					return ['project_id' => $projectid];
				} else {
					return ['message' => $this->trans->t('Access denied')];
				}
			} else {
				return ['message' => $this->trans->t('Access denied')];
			}
		} else {
			return ['message' => $this->trans->t('Access denied')];
		}
	}

	/**
	 * Import SplitWise project file
	 *
	 * @param string $path
	 * @param string $userId
	 * @return array
	 */
	public function importSWProject(string $path, string $userId): array {
		$cleanPath = str_replace(array('../', '..\\'), '',  $path);
		$userFolder = $this->root->getUserFolder($userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file->getType() === FileInfo::TYPE_FILE) {
				if (($handle = $file->fopen('r')) !== false) {
					$columns = [];
					$membersWeight = [];
					$bills = [];
					$owersArray = [];
					$categoryNames = [];
					$row = 0;
					$nbCol = 0;
					while (($data = fgetcsv($handle, 1000, ',')) !== false) {
						$owersList = [];
						$payer_name = '';
						// first line : get column order
						if ($row === 0) {
							$nbCol = count($data);
							for ($c=0; $c < $nbCol; $c++) {
								$columns[$data[$c]] = $c;
							}
							if (!array_key_exists('Date', $columns) or
								!array_key_exists('Description', $columns) or
								!array_key_exists('Category', $columns) or
								!array_key_exists('Cost', $columns) or
								!array_key_exists('Currency', $columns)
							) {
								fclose($handle);
								return ['message' => $this->trans->t('Malformed CSV, bad column names')];
							}
							// manage members
							$m=0;
							for ($c=5; $c < $nbCol; $c++){
								$owersArray[$m] = $data[$c];
								$m++;
							}
							foreach ($owersArray as $ower) {
								if (strlen($ower) === 0) {
									fclose($handle);
									return ['message' => $this->trans->t('Malformed CSV, cannot have an empty ower')];
								}
								if (!array_key_exists($ower, $membersWeight)) {
									$membersWeight[$ower] = 1.0;
								}
							}
						} elseif (!isset($data[$columns['Date']]) || empty($data[$columns['Date']])) {
							// skip empty lines
						} elseif (isset($data[$columns['Description']]) && $data[$columns['Description']] === 'Total balance') {
							// skip the total lines
						} else {
							// normal line : bill
							$what = $data[$columns['Description']];
							$amount = $data[$columns['Cost']];
							$date = $data[$columns['Date']];
							$timestamp = strtotime($date);
							$l = 0;
							for ($c=5; $c < $nbCol; $c++){
								if (max($data[$c], 0) !== 0){
									$payer_name = $owersArray[$c-5];
								}
								if ($data[$c] === $amount){
									continue;
								} elseif ($data[$c] === -$amount){
									$owersList = [];
									$owersList[$l++] = $owersArray[$c-5];
									break;
								} else {
									$owersList[$l++] = $owersArray[$c-5];
								}
							}
							if (!isset($payer_name) || empty($payer_name)) {
								return ['message' => $this->trans->t('Malformed CSV, no payer on line %1$s', [$row])];
							}
//							$payer_weight = 1;

							if (!is_numeric($amount)) {
								fclose($handle);
								return ['message' => $this->trans->t('Malformed CSV, bad amount on line %1$s', [$row])];
							}
							$bill = [
								'what' => $what,
								'timestamp' => $timestamp,
								'amount' => $amount,
								'payer_name' => $payer_name,
								'owers' => $owersList
							];
							// manage categories
							if (array_key_exists('Category', $columns) and
								$data[$columns['Category']] !== null and
								$data[$columns['Category']] !== '') {
								$catName = $data[$columns['Category']];
								if (!in_array($catName, $categoryNames)) {
									$categoryNames[] = $catName;
								}
								$bill['category_name'] = $catName;
							}
							$bills[] = $bill;
						}
						$row++;
					}
					fclose($handle);

					$memberNameToId = [];

					// add project
					$user = $this->userManager->get($userId);
					$userEmail = $user->getEMailAddress();
					$projectName = str_replace('.csv', '', $file->getName());
					$projectid = slugify($projectName);
					// create default categories only if none are found in the CSV
					$createDefaultCategories = (count($categoryNames) === 0);
					$projResult = $this->createProject($projectName, $projectid, '', $userEmail,
													   $userId, $createDefaultCategories);
					if (!isset($projResult['id'])) {
						return ['message' => $this->trans->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
					}
					// add categories
					$catNameToId = [];
					foreach ($categoryNames as $catName) {
						$insertedCatId = $this->addCategory($projectid, $catName, null, '#000000');
						if (!is_numeric($insertedCatId)) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding category %1$s', [$catName])];
						}
						$catNameToId[$catName] = $insertedCatId;
					}
					// add members
					foreach ($membersWeight as $memberName => $weight) {
						$insertedMember = $this->addMember($projectid, $memberName, $weight);
						if (!is_array($insertedMember)) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding member %1$s', [$memberName])];
						}
						$memberNameToId[$memberName] = $insertedMember['id'];
					}
					// add bills
					foreach ($bills as $bill) {
						$payerId = $memberNameToId[$bill['payer_name']];
						$owerIds = [];
						foreach ($bill['owers'] as $owerName) {
							$owerIds[] = $memberNameToId[$owerName];
						}
						$owerIdsStr = implode(',', $owerIds);
						// category
						$catId = null;
						if (array_key_exists('category_name', $bill) and
							array_key_exists($bill['category_name'], $catNameToId)) {
							$catId = $catNameToId[$bill['category_name']];
						}
						$addBillResult = $this->addBill($projectid, null, $bill['what'], $payerId, $owerIdsStr, $bill['amount'], 'n',
														null, $catId, 0, null, $bill['timestamp']);
						if (!isset($addBillResult['inserted_id'])) {
							$this->deleteProject($projectid);
							return ['message' => $this->trans->t('Error when adding bill %1$s', [$bill['what']])];
						}
					}
					return ['project_id' => $projectid];
				} else {
					return ['message' => $this->trans->t('Access denied')];
				}
			} else {
				return ['message' => $this->trans->t('Access denied')];
			}
		} else {
			return ['message' => $this->trans->t('Access denied')];
		}
	}

	/**
	 * auto export
	 * triggered by NC cron job
	 *
	 * export projects
	 */
	public function cronAutoExport(): void {
		date_default_timezone_set('UTC');
		// last day
		$now = new DateTime();
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');

		// get begining of today
		$dateMaxDay = new DateTime($y.'-'.$m.'-'.$d);
		$maxDayTimestamp = $dateMaxDay->getTimestamp();
		$minDayTimestamp = $maxDayTimestamp - 24*60*60;

		$dateMaxDay->modify('-1 day');
		$dailySuffix = '_'.$this->trans->t('daily').'_'.$dateMaxDay->format('Y-m-d');

		// last week
		$now = new DateTime();
		while (intval($now->format('N')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateWeekMax = new DateTime($y.'-'.$m.'-'.$d);
		$maxWeekTimestamp = $dateWeekMax->getTimestamp();
		$minWeekTimestamp = $maxWeekTimestamp - 7*24*60*60;
		$dateWeekMin = new DateTime($y.'-'.$m.'-'.$d);
		$dateWeekMin->modify('-7 day');
		$weeklySuffix = '_'.$this->trans->t('weekly').'_'.$dateWeekMin->format('Y-m-d');

		// last month
		$now = new DateTime();
		while (intval($now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateMonthMax = new DateTime($y.'-'.$m.'-'.$d);
		$maxMonthTimestamp = $dateMonthMax->getTimestamp();
		$now->modify('-1 day');
		while (intval($now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = intval($now->format('Y'));
		$m = intval($now->format('m'));
		$d = intval($now->format('d'));
		$dateMonthMin = new DateTime($y.'-'.$m.'-'.$d);
		$minMonthTimestamp = $dateMonthMin->getTimestamp();
		$monthlySuffix = '_'.$this->trans->t('monthly').'_'.$dateMonthMin->format('Y-m');

//		$weekFilterArray = [];
//		$weekFilterArray['tsmin'] = $minWeekTimestamp;
//		$weekFilterArray['tsmax'] = $maxWeekTimestamp;
//		$dayFilterArray = [];
//		$dayFilterArray['tsmin'] = $minDayTimestamp;
//		$dayFilterArray['tsmax'] = $maxDayTimestamp;
//		$monthFilterArray = [];
//		$monthFilterArray['tsmin'] = $minMonthTimestamp;
//		$monthFilterArray['tsmax'] = $maxMonthTimestamp;

		$qb = $this->db->getQueryBuilder();

		foreach ($this->userManager->search('') as $u) {
			$uid = $u->getUID();
			$outPath = $this->config->getUserValue($uid, 'cospend', 'outputDirectory', '/Cospend');

			$qb->select('p.id', 'p.name', 'p.autoexport')
			->from('cospend_projects', 'p')
			->where(
				$qb->expr()->eq('userid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->neq('p.autoexport', $qb->createNamedParameter('n', IQueryBuilder::PARAM_STR))
			);
			$req = $qb->executeQuery();

			$dbProjectId = null;
			while ($row = $req->fetch()) {
				$dbProjectId = $row['id'];
				$autoexport = $row['autoexport'];

				$suffix = $dailySuffix;
				if ($autoexport === 'w') {
					$suffix = $weeklySuffix;
				} elseif ($autoexport === 'm') {
					$suffix = $monthlySuffix;
				}
				// check if file already exists
				$exportName = $dbProjectId.$suffix.'.csv';

				$userFolder = $this->root->getUserFolder($uid);
				if (! $userFolder->nodeExists($outPath.'/'.$exportName)) {
					$this->exportCsvProject($dbProjectId, $uid, $exportName);
				}
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();
		}
	}

	/**
	 * Convert hexadecimal color into RGB array
	 *
	 * @param string $color
	 * @return array
	 */
	private function hexToRgb(string $color): array {
		$color = str_replace('#', '', $color);
		$split_hex_color = str_split($color, 2);
		$r = hexdec($split_hex_color[0]);
		$g = hexdec($split_hex_color[1]);
		$b = hexdec($split_hex_color[2]);
		return [
			'r' => $r,
			'g' => $g,
			'b' => $b,
		];
	}

	/**
	 * Search bills with query string
	 *
	 * @param string $projectId
	 * @param string $term
	 * @return array
	 */
	public function searchBills(string $projectId, string $term): array {
		$term = strtolower($term);
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'what', 'comment', 'amount', 'timestamp',
					'paymentmode', 'categoryid', 'pr.currencyname')
		   ->from('cospend_bills', 'b')
		   ->innerJoin('b', 'cospend_projects', 'pr', $qb->expr()->eq('b.projectid', 'pr.id'))
		   ->where(
			   $qb->expr()->eq('b.projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		   );
		$or = $qb->expr()->orx();
		$or->add(
			$qb->expr()->iLike('b.what', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%', IQueryBuilder::PARAM_STR))
		);
		$or->add(
			$qb->expr()->iLike('b.comment', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%', IQueryBuilder::PARAM_STR))
		);
		$qb->andWhere($or);
		$qb->orderBy('timestamp', 'ASC');
		$req = $qb->executeQuery();

		// bills by id
		$bills = [];
		while ($row = $req->fetch()){
			$dbBillId = intval($row['id']);
			$dbAmount = floatval($row['amount']);
			$dbWhat = $row['what'];
			$dbTimestamp = intval($row['timestamp']);
			$dbComment = $row['comment'];
			$dbPaymentMode = $row['paymentmode'];
			$dbCategoryId = intval($row['categoryid']);
			$dbProjectCurrencyName = $row['currencyname'];
			$bills[] = [
				'id' => $dbBillId,
				'projectId' => $projectId,
				'amount' => $dbAmount,
				'what' => $dbWhat,
				'timestamp' => $dbTimestamp,
				'comment' => $dbComment,
				'paymentmode' => $dbPaymentMode,
				'categoryid' => $dbCategoryId,
				'currencyname' => $dbProjectCurrencyName,
			];
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return $bills;
	}

	/**
	 * Get Cospend bill activity
	 *
	 * @param string $userId
	 * @param int|null $since
	 * @return array
	 */
	public function getBillActivity(string $userId, ?int $since): array {
		// get projects
		$projects = $this->getProjects($userId);

		// get bills (7 max)
		$bills = [];
		foreach ($projects as $project) {
			$pid = $project['id'];
			$bl = $this->getBills($pid, null, null, null, null, null, null, $since, 20, true);

			// get members by id
			$membersById = [];
			foreach ($project['members'] as $m) {
				$membersById[$m['id']] = $m;
			}
			// add information
			foreach ($bl as $i => $bill) {
				$payerId = $bill['payer_id'];
				$bl[$i]['payer'] = $membersById[$payerId];
				$bl[$i]['project_id'] = $pid;
				$bl[$i]['project_name'] = $project['name'];
			}

			$bills = array_merge($bills, $bl);
		}

		// sort bills by date
		usort($bills, function($a, $b) {
			$ta = $a['timestamp'];
			$tb = $b['timestamp'];
			return ($ta > $tb) ? -1 : 1;
		});

		// take 7 firsts
		return array_slice($bills, 0, 7);
	}

	/**
	 * Touch a project
	 *
	 * @param string $projectId
	 * @param int $timestamp
	 * @return void
	 */
	private function updateProjectLastChanged(string $projectId, int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cospend_projects');
		$qb->set('lastchanged', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT));
		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
		);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}
}
