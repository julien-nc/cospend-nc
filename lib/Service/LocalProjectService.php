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

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Generator;
use OC\User\NoUserException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\Bill;
use OCA\Cospend\Db\BillMapper;

use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Db\Member;
use OCA\Cospend\Db\MemberMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Utils;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

use OCP\IConfig;
use OCP\IDateTimeZone;

use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use Throwable;
use function str_replace;

/**
 * @psalm-import-type CospendProjectInfoPlusExtra from ResponseDefinitions
 * @psalm-import-type CospendMember from ResponseDefinitions
 */
class LocalProjectService {

	public array $defaultCategories;
	public array $defaultPaymentModes;
	private array $hardCodedCategoryNames;

	public function __construct(
		private IL10N $l10n,
		private IConfig $config,
		private ProjectMapper $projectMapper,
		private BillMapper $billMapper,
		private MemberMapper $memberMapper,
		private InvitationMapper $invitationMapper,
		private ActivityManager $activityManager,
		private IUserManager $userManager,
		private IAppManager $appManager,
		private IGroupManager $groupManager,
		private IDateTimeZone $dateTimeZone,
		private IRootFolder $root,
		private INotificationManager $notificationManager,
		private IDBConnection $db,
	) {
		$this->defaultCategories = [
			[
				'name' => $this->l10n->t('Grocery'),
				'icon' => '🛒',
				'color' => '#ffaa00',
			],
			[
				'name' => $this->l10n->t('Bar/Party'),
				'icon' => '🎉',
				'color' => '#aa55ff',
			],
			[
				'name' => $this->l10n->t('Rent'),
				'icon' => '🏠',
				'color' => '#da8733',
			],
			[
				'name' => $this->l10n->t('Bill'),
				'icon' => '🌩',
				'color' => '#4aa6b0',
			],
			[
				'name' => $this->l10n->t('Excursion/Culture'),
				'icon' => '🚸',
				'color' => '#0055ff',
			],
			[
				'name' => $this->l10n->t('Health'),
				'icon' => '💚',
				'color' => '#bf090c',
			],
			[
				'name' => $this->l10n->t('Shopping'),
				'icon' => '🛍',
				'color' => '#e167d1',
			],
			[
				'name' => $this->l10n->t('Restaurant'),
				'icon' => '🍴',
				'color' => '#d0d5e1',
			],
			[
				'name' => $this->l10n->t('Accommodation'),
				'icon' => '🛌',
				'color' => '#5de1a3',
			],
			[
				'name' => $this->l10n->t('Transport'),
				'icon' => '🚌',
				'color' => '#6f2ee1',
			],
			[
				'name' => $this->l10n->t('Sport'),
				'icon' => '🎾',
				'color' => '#69e177',
			],
		];

		$this->defaultPaymentModes = [
			[
				'name' => $this->l10n->t('Credit card'),
				'icon' => '💳',
				'color' => '#FF7F50',
				'old_id' => 'c',
			],
			[
				'name' => $this->l10n->t('Cash'),
				'icon' => '💵',
				'color' => '#556B2F',
				'old_id' => 'b',
			],
			[
				'name' => $this->l10n->t('Check'),
				'icon' => '🎫',
				'color' => '#A9A9A9',
				'old_id' => 'f',
			],
			[
				'name' => $this->l10n->t('Transfer'),
				'icon' => '⇄',
				'color' => '#00CED1',
				'old_id' => 't',
			],
			[
				'name' => $this->l10n->t('Online service'),
				'icon' => '🌎',
				'color' => '#9932CC',
				'old_id' => 'o',
			],
		];

		$this->hardCodedCategoryNames = [
			'-11' => $this->l10n->t('Reimbursement'),
		];
	}

	/**
	 * Get max access level of a given user for a given project
	 *
	 * @param string $userId
	 * @param string $projectId
	 * @return int
	 */
	public function getUserMaxAccessLevel(string $userId, string $projectId): int {
		$result = Application::ACCESS_LEVEL_NONE;
		$dbProject = $this->projectMapper->find($projectId);
		if ($dbProject !== null) {
			// does the user own the project ?
			if ($dbProject->getUserid() === $userId) {
				return Application::ACCESS_LEVEL_ADMIN;
			} else {
				$qb = $this->db->getQueryBuilder();
				// is the project shared with the user ?
				$qb->select('userid', 'projectid', 'accesslevel')
					->from('cospend_shares')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbProjectId = null;
				$dbAccessLevel = null;
				while ($row = $req->fetch()) {
					$dbProjectId = $row['projectid'];
					$dbAccessLevel = (int) $row['accesslevel'];
					break;
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();

				if ($dbProjectId !== null && $dbAccessLevel > $result) {
					$result = $dbAccessLevel;
				}

				// is the project shared with a group containing the user?
				$userO = $this->userManager->get($userId);

				$qb->select('userid', 'accesslevel')
					->from('cospend_shares')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				while ($row = $req->fetch()) {
					$groupId = $row['userid'];
					$dbAccessLevel = (int) $row['accesslevel'];
					if ($this->groupManager->groupExists($groupId)
						&& $this->groupManager->get($groupId)->inGroup($userO)
						&& $dbAccessLevel > $result
					) {
						$result = $dbAccessLevel;
					}
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();

				// are circles enabled and is the project shared with a circle containing the user
				$circlesEnabled = $this->appManager->isEnabledForUser('circles');
				if ($circlesEnabled) {
					$qb->select('userid', 'accesslevel')
						->from('cospend_shares')
						->where(
							$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->executeQuery();
					while ($row = $req->fetch()) {
						$circleId = $row['userid'];
						$dbAccessLevel = (int) $row['accesslevel'];
						if ($this->isUserInCircle($userId, $circleId) && $dbAccessLevel > $result) {
							$result = $dbAccessLevel;
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Get access level of a shared access
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return int
	 */
	public function getShareAccessLevel(string $projectId, int $shId): int {
		$result = 0;
		$qb = $this->db->getQueryBuilder();
		$qb->select('accesslevel')
			->from('cospend_shares')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$result = (int) $row['accesslevel'];
			break;
		}
		$req->closeCursor();

		return $result;
	}

	/**
	 * Create a project
	 *
	 * @param string $name
	 * @param string $id
	 * @param string|null $contact_email
	 * @param string $userId
	 * @param bool $createDefaultCategories
	 * @param bool $createDefaultPaymentModes
	 * @return array
	 */
	public function createProject(
		string $name, string $id, ?string $contact_email, string $userId = '',
		bool $createDefaultCategories = true, bool $createDefaultPaymentModes = true
	): array {
		return $this->projectMapper->createProject(
			$name, $id, $contact_email, $this->defaultCategories, $this->defaultPaymentModes,
			$userId, $createDefaultCategories, $createDefaultPaymentModes
		);
	}

	/**
	 * Delete a project and all associated data
	 *
	 * @param string $projectId
	 * @return array
	 */
	public function deleteProject(string $projectId): array {
		$dbProjectToDelete = $this->projectMapper->find($projectId);
		if ($dbProjectToDelete === null) {
			return ['error' => $this->l10n->t('Not Found')];
		}
		$this->projectMapper->deleteBillOwersOfProject($projectId);

		$associatedTableNames = [
			'cospend_bills',
			'cospend_members',
			'cospend_shares',
			'cospend_currencies',
			'cospend_categories',
			'cospend_paymentmodes'
		];

		$qb = $this->db->getQueryBuilder();
		foreach ($associatedTableNames as $tableName) {
			$qb->delete($tableName)
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $this->db->getQueryBuilder();
		}

		$this->projectMapper->delete($dbProjectToDelete);
		return ['message' => 'DELETED'];
	}

	/**
	 * Get all project data
	 *
	 * @param string $projectId
	 * @return CospendProjectInfoPlusExtra|null
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectInfo(string $projectId): ?array {
		try {
			$dbProject = $this->projectMapper->find($projectId);
		} catch (Exception | Throwable $e) {
			return null;
		}
		$dbProjectId = $dbProject->getId();

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
		$categories = $this->getCategoriesOrPaymentModes($dbProjectId);
		$paymentModes = $this->getCategoriesOrPaymentModes($dbProjectId, false);
		// get all shares
		$userShares = $this->getUserShares($dbProjectId);
		$groupShares = $this->getGroupShares($dbProjectId);
		$circleShares = $this->getCircleShares($dbProjectId);
		$publicShares = $this->getPublicShares($dbProjectId);
		$shares = array_merge($userShares, $groupShares, $circleShares, $publicShares);

		$extraProjectInfo = [
			'active_members' => $activeMembers,
			'members' => $members,
			'balance' => $balance,
			'nb_bills' => $smallStats['nb_bills'],
			'total_spent' => $smallStats['total_spent'],
			'nb_trashbin_bills' => $smallStats['nb_trashbin_bills'],
			'shares' => $shares,
			'currencies' => $currencies,
			'categories' => $categories,
			'paymentmodes' => $paymentModes,
		];

		return array_merge($extraProjectInfo, $dbProject->jsonSerialize());
	}

	/**
	 * @param string $projectId
	 * @param string $userId
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectInfoWithAccessLevel(string $projectId, string $userId): ?array {
		$projectInfo = $this->getProjectInfo($projectId);
		$projectInfo['myaccesslevel'] = $this->getUserMaxAccessLevel($userId, $projectId);
		return $projectInfo;
	}

	/**
	 * Get number of bills and total spent amount for a given project
	 *
	 * @param string $projectId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	private function getSmallStats(string $projectId): array {
		$totalSpent = 0;
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('SUM(amount)'), 'sum_amount')
			->from('cospend_bills')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$totalSpent = (float) $row['sum_amount'];
		}

		return [
			'nb_bills' => $this->billMapper->countBills($projectId, null, null, null, 0),
			'total_spent' => $totalSpent,
			'nb_trashbin_bills' => $this->billMapper->countBills($projectId, null, null, null, 1),
		];
	}

	/**
	 * Get project statistics
	 *
	 * @param string $projectId
	 * @param string|null $memberOrder
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param bool $showDisabled
	 * @param int|null $currencyId
	 * @param int|null $payerId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectStatistics(
		string $projectId, ?string $memberOrder = null, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		bool $showDisabled = true, ?int $currencyId = null, ?int $payerId = null
	): array {
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

		$projectCategories = $this->getCategoriesOrPaymentModes($projectId);
		$projectPaymentModes = $this->getCategoriesOrPaymentModes($projectId, false);

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
			$mBalance = (float) $membersBalance[$memberId];
			if ($showDisabled || $member['activated'] || $mBalance >= 0.01 || $mBalance <= -0.01) {
				$membersToDisplay[$memberId] = $member;
			}
		}

		// compute stats
		$bills = $this->billMapper->getBills(
			$projectId, $tsMin, $tsMax, null, $paymentModeId, $categoryId,
			$amountMin, $amountMax, null, null, false, $payerId
		);

		/*
		$firstBillTs = $bills[0]['timestamp'];
		$firstBillDate = DateTime::createFromFormat('U', $firstBillTs);
		$firstBillDate->setTimezone($timeZone);
		$firstBillDate->modify('first day of');
		$firstBillDate->setTime(0, 0);
		$year1 = (int) $firstBillDate->format('Y');
		$month1 = (int) $firstBillDate->format('m');

		$lastBillTs = $bills[count($bills) - 1]['timestamp'];
		$lastBillDate = DateTime::createFromFormat('U', $lastBillTs);
		$lastBillDate->setTimezone($timeZone);
		$lastBillDate->modify('first day of');
		$lastBillDate->setTime(0, 0);
		$year2 = (int) $lastBillDate->format('Y');
		$month2 = (int) $lastBillDate->format('m');

		$fullMonthNumber = (($year2 - $year1) * 12) + ($month2 - $month1 + 1);
		*/

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
		} else {
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
		$averageKey = $this->l10n->t('Average per month');
		// number of months with actual bills
		$nbMonth = count(array_keys($memberMonthlyPaidStats));
		$realMonths = array_keys($memberMonthlyPaidStats);
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
			$billCategoryId = $bill['categoryid'];
			if (!array_key_exists(strval($billCategoryId), $this->hardCodedCategoryNames) &&
				!array_key_exists(strval($billCategoryId), $projectCategories)
			) {
				$billCategoryId = 0;
			}
			$amount = $bill['amount'];
			if (!array_key_exists($billCategoryId, $categoryStats)) {
				$categoryStats[$billCategoryId] = 0;
			}
			$categoryStats[$billCategoryId] += $amount;

			// payment mode
			$paymentModeId = $bill['paymentmodeid'];
			if (!array_key_exists(strval($paymentModeId), $projectPaymentModes)) {
				$paymentModeId = 0;
			}
			$amount = $bill['amount'];
			if (!array_key_exists($paymentModeId, $paymentModeStats)) {
				$paymentModeStats[$paymentModeId] = 0;
			}
			$paymentModeStats[$paymentModeId] += $amount;
		}
		// convert if necessary
		if ($currency !== null) {
			foreach ($categoryStats as $catId => $val) {
				$categoryStats[$catId] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
			}
			foreach ($paymentModeStats as $pmId => $val) {
				$paymentModeStats[$pmId] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
			}
		}
		// compute category per member stats
		$categoryMemberStats = [];
		foreach ($bills as $bill) {
			$payerId = $bill['payer_id'];
			$billCategoryId = $bill['categoryid'];
			if (!array_key_exists(strval($billCategoryId), $this->hardCodedCategoryNames) &&
				!array_key_exists(strval($billCategoryId), $projectCategories)
			) {
				$billCategoryId = 0;
			}
			$amount = $bill['amount'];
			if (!array_key_exists($billCategoryId, $categoryMemberStats)) {
				$categoryMemberStats[$billCategoryId] = [];
				foreach ($membersToDisplay as $memberId => $member) {
					$categoryMemberStats[$billCategoryId][$memberId] = 0;
				}
			}
			if (array_key_exists($payerId, $membersToDisplay)) {
				$categoryMemberStats[$billCategoryId][$payerId] += $amount;
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
			$billCategoryId = $bill['categoryid'];
			if (!array_key_exists($billCategoryId, $categoryMonthlyStats)) {
				$categoryMonthlyStats[$billCategoryId] = [];
			}
			if (!array_key_exists($month, $categoryMonthlyStats[$billCategoryId])) {
				$categoryMonthlyStats[$billCategoryId][$month] = 0;
			}
			$categoryMonthlyStats[$billCategoryId][$month] += $amount;

			// payment mode
			$paymentModeId = $bill['paymentmodeid'];
			if (!array_key_exists($paymentModeId, $paymentModeMonthlyStats)) {
				$paymentModeMonthlyStats[$paymentModeId] = [];
			}
			if (!array_key_exists($month, $paymentModeMonthlyStats[$paymentModeId])) {
				$paymentModeMonthlyStats[$paymentModeId][$month] = 0;
			}
			$paymentModeMonthlyStats[$paymentModeId][$month] += $amount;
		}
		// average per month
		foreach ($categoryMonthlyStats as $catId => $monthValues) {
			$sum = 0;
			foreach ($monthValues as $month => $value) {
				$sum += $value;
			}
			$avg = $sum / $nbMonth;
			$categoryMonthlyStats[$catId][$averageKey] = $avg;
		}
		foreach ($paymentModeMonthlyStats as $pmId => $monthValues) {
			$sum = 0;
			foreach ($monthValues as $month => $value) {
				$sum += $value;
			}
			$avg = $sum / $nbMonth;
			$paymentModeMonthlyStats[$pmId][$averageKey] = $avg;
		}
		// convert if necessary
		if ($currency !== null) {
			foreach ($categoryMonthlyStats as $catId => $cStat) {
				foreach ($cStat as $month => $val) {
					$categoryMonthlyStats[$catId][$month] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
				}
			}
			foreach ($paymentModeMonthlyStats as $pmId => $pmStat) {
				foreach ($pmStat as $month => $val) {
					$paymentModeMonthlyStats[$pmId][$month] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
				}
			}
		}

		return [
			'stats' => $statistics,
			'memberMonthlyPaidStats' => count($memberMonthlyPaidStats) > 0 ? $memberMonthlyPaidStats : null,
			'memberMonthlySpentStats' => count($memberMonthlySpentStats) > 0 ? $memberMonthlySpentStats : null,
			'categoryStats' => $categoryStats,
			'categoryMonthlyStats' => $categoryMonthlyStats,
			'paymentModeStats' => $paymentModeStats,
			'paymentModeMonthlyStats' => $paymentModeMonthlyStats,
			'categoryMemberStats' => $categoryMemberStats,
			'memberIds' => array_keys($membersToDisplay),
			'allMemberIds' => $allMembersIds,
			'membersPaidFor' => $membersPaidFor,
			'realMonths' => $realMonths,
		];
	}

	/**
	 * Add a bill in a given project
	 *
	 * @param string $projectId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param int $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @param array|null $paymentModes
	 * @param int $deleted
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function createBill(
		string $projectId, ?string $date, ?string $what, ?int $payer, ?string $payedFor,
		?float $amount, ?string $repeat, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null,
		?array $paymentModes = null, int $deleted = 0
	): array {
		// if we don't have the payment modes, get them now
		if ($paymentModes === null) {
			$paymentModes = $this->getCategoriesOrPaymentModes($projectId, false);
		}

		if ($repeat === null || $repeat === '' || strlen($repeat) !== 1) {
			return ['repeat' => 'Invalid value (' . $repeat . ')'];
		} elseif (!in_array($repeat, Application::FREQUENCIES)) {
			return ['repeat' => 'Invalid frequency (' . $repeat . ')'];
		}
		if ($repeatUntil !== null && $repeatUntil === '') {
			$repeatUntil = null;
		}
		// priority to timestamp (moneybuster might send both for a moment)
		if ($timestamp === null) {
			if ($date === null || $date === '') {
				return ['message' => 'Timestamp (or date) field is required'];
			} else {
				$datetime = DateTime::createFromFormat('Y-m-d', $date);
				if ($datetime === false) {
					return ['date' => 'Invalid date'];
				}
				$dateTs = $datetime->getTimestamp();
			}
		} else {
			$dateTs = $timestamp;
		}
		if ($what === null) {
			$what = '';
		}
		if ($amount === null) {
			return ['amount' => 'This field is required'];
		}
		if ($payer === null) {
			return ['payer' => 'This field is required'];
		}
		if ($this->getMemberById($projectId, $payer) === null) {
			return ['payer' => 'Not a valid choice'];
		}
		// check owers
		$owerIds = explode(',', $payedFor);
		if ($payedFor === null || $payedFor === '' || empty($owerIds)) {
			return ['payed_for' => 'Invalid value (' . $payedFor . ')'];
		}
		foreach ($owerIds as $owerId) {
			if (!is_numeric($owerId)) {
				return ['payed_for' => 'Invalid value'];
			}
			if ($this->getMemberById($projectId, (int) $owerId) === null) {
				return ['payed_for' => 'Not a valid choice'];
			}
		}
		// payment mode
		if (!is_null($paymentModeId)) {
			// is the old_id set for this payment mode? if yes, use it for old 'paymentmode' column
			$paymentMode = 'n';
			if (isset($paymentModes[$paymentModeId], $paymentModes[$paymentModeId]['old_id'])
				&& $paymentModes[$paymentModeId]['old_id'] !== null
				&& $paymentModes[$paymentModeId]['old_id'] !== ''
			) {
				$paymentMode = $paymentModes[$paymentModeId]['old_id'];
			}
		} elseif (!is_null($paymentMode)) {
			// is there a pm with this old id? if yes, use it for new id
			$paymentModeId = 0;
			foreach ($paymentModes as $id => $pm) {
				if ($pm['old_id'] === $paymentMode) {
					$paymentModeId = $id;
					break;
				}
			}
		}

		// last modification timestamp is now
		$ts = (new DateTime())->getTimestamp();

		$newBill = new Bill();
		$newBill->setProjectid($projectId);
		$newBill->setWhat($what);
		if ($comment !== null) {
			$newBill->setComment($comment);
		}
		$newBill->setTimestamp($dateTs);
		$newBill->setAmount($amount);
		$newBill->setPayerid($payer);
		$newBill->setRepeat($repeat);
		$newBill->setRepeatallactive($repeatAllActive);
		$newBill->setRepeatuntil($repeatUntil);
		$newBill->setRepeatfreq($repeatFreq ?? 1);
		$newBill->setCategoryid($categoryId ?? 0);
		$newBill->setPaymentmode($paymentMode ?? 'n');
		$newBill->setPaymentmodeid($paymentModeId ?? 0);
		$newBill->setLastchanged($ts);
		$newBill->setDeleted($deleted);

		$createdBill = $this->billMapper->insert($newBill);

		$insertedBillId = $createdBill->getId();

		// insert bill owers
		$qb = $this->db->getQueryBuilder();
		foreach ($owerIds as $owerId) {
			$qb->insert('cospend_bill_owers')
				->values([
					'billid' => $qb->createNamedParameter($insertedBillId, IQueryBuilder::PARAM_INT),
					'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
				]);
			$qb->executeStatement();
		}

		$this->projectMapper->updateProjectLastChanged($projectId, $ts);

		return ['inserted_id' => $insertedBillId];
	}

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param bool $force Ignores any deletion protection and forces the deletion of the bill
	 * @param bool $moveToTrash
	 * @return array
	 */
	public function deleteBill(string $projectId, int $billId, bool $force = false, bool $moveToTrash = true): array {
		if ($force === false) {
			$project = $this->getProjectInfo($projectId);
			if ($project['deletiondisabled']) {
				return ['message' => 'forbidden'];
			}
		}
		$billToDelete = $this->billMapper->getBillEntity($projectId, $billId);
		if ($billToDelete !== null) {
			// really delete bills that already are in the trashbin
			if ($moveToTrash && $billToDelete->getDeleted() === 0) {
				$billToDelete->setDeleted(1);
				$this->billMapper->update($billToDelete);
			} else {
				$this->billMapper->deleteBillOwersOfBill($billId);
				$this->billMapper->delete($billToDelete);
			}

			$ts = (new DateTime())->getTimestamp();
			$this->projectMapper->updateProjectLastChanged($projectId, $ts);

			return ['success' => true];
		} else {
			return ['message' => 'not found'];
		}
	}

	/**
	 * Get a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return array|null
	 */
	public function getMemberById(string $projectId, int $memberId): ?array {
		$member = $this->memberMapper->getMemberById($projectId, $memberId);
		return $member?->jsonSerialize();
	}

	/**
	 * Generate bills to automatically settle a project
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): array {
		$settlement = $this->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
		$transactions = $settlement['transactions'];
		if (!is_array($transactions)) {
			return ['message' => $this->l10n->t('Error when getting project settlement transactions')];
		}

		$members = $this->getMembers($projectId);
		$memberIdToName = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
		}

		if ($maxTimestamp) {
			$ts = $maxTimestamp - 1;
		} else {
			$ts = (new DateTime())->getTimestamp();
		}

		$paymentModes = [];
		foreach ($transactions as $transaction) {
			$fromId = $transaction['from'];
			$toId = $transaction['to'];
			$amount = round((float) $transaction['amount'], $precision);
			$billTitle = $memberIdToName[$fromId].' → '.$memberIdToName[$toId];
			$addBillResult = $this->createBill(
				$projectId, null, $billTitle, $fromId, $toId, $amount,
				Application::FREQUENCY_NO, 'n', 0, Application::CATEGORY_REIMBURSEMENT,
				0, null, $ts, null, null, $paymentModes
			);
			if (!isset($addBillResult['inserted_id'])) {
				return ['message' => $this->l10n->t('Error when adding a bill')];
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
	 * @param string $projectId
	 * @param int $memberId
	 * @param string|null $name
	 * @param string|null $userId
	 * @param float|null $weight
	 * @param bool $activated
	 * @param string|null $color
	 * @return array|null
	 */
	public function editMember(
		string $projectId, int $memberId, ?string $name = null, ?string $userId = null,
		?float $weight = null, ?bool $activated = null, ?string $color = null
	): array|null {
		$dbMember = $this->memberMapper->getMemberById($projectId, $memberId);
		if ($dbMember === null) {
			return ['name' => $this->l10n->t('This project have no such member')];
		}
		$member = $dbMember->jsonSerialize();
		// delete member if it has no bill and we are disabling it
		if ($member['activated']
			&& $activated === false
			&& count($this->memberMapper->getBillIdsOfMember($memberId)) === 0
		) {
			$this->memberMapper->delete($dbMember);
			return null;
		}

		if ($name !== null) {
			if (str_contains($name, '/')) {
				return ['name' => $this->l10n->t('Invalid member name')];
			} else {
				// get existing member with this name
				$memberWithSameName = $this->getMemberByName($projectId, $name);
				if ($memberWithSameName && $memberWithSameName['id'] !== $memberId) {
					return ['name' => $this->l10n->t('Name already exists')];
				}
			}
		}

		if ($color !== null) {
			$color = preg_replace('/^#/', '', $color);
			if ($color === ''
				|| ((strlen($color) === 3 || strlen($color) === 6)
					&& preg_match('/^[0-9A-Fa-f]+/', $color) !== false)
			) {
				// fine
			} else {
				return ['color' => $this->l10n->t('Invalid value')];
			}
		}

		if ($weight !== null && $weight <= 0.0) {
			return ['weight' => $this->l10n->t('Not a valid decimal value')];
		}

		// UPDATE

		$ts = (new DateTime())->getTimestamp();
		$dbMember->setLastchanged($ts);

		if ($weight !== null) {
			$dbMember->setWeight($weight);
		}
		if ($activated !== null) {
			$dbMember->setActivated($activated ? 1 : 0);
		}

		if ($name !== null) {
			$dbMember->setName($name);
		}

		if ($color !== null) {
			$dbMember->setColor($color === '' ? null : $color);
		}

		if ($userId !== null) {
			$dbMember->setUserid($userId === '' ? null : $userId);
		}

		$this->memberMapper->update($dbMember);
		return $dbMember->jsonSerialize();
	}

	/**
	 * Edit a project
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $autoexport
	 * @param string|null $currencyname
	 * @param bool|null $deletion_disabled
	 * @param string|null $categorysort
	 * @param string|null $paymentmodesort
	 * @param int|null $archivedTs
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editProject(
		string  $projectId, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
		?string $categorysort = null, ?string $paymentmodesort = null, ?int $archivedTs = null
	): array {
		$dbProject = $this->projectMapper->find($projectId);
		if ($dbProject === null) {
			return ['message' => $this->l10n->t('There is no such project')];
		}

		if ($name === '') {
			return ['name' => [$this->l10n->t('Name can\'t be empty')]];
		}
		if ($contact_email !== null && $contact_email !== '') {
			if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
			} else {
				return ['contact_email' => [$this->l10n->t('Invalid email address')]];
			}
		}
		if ($autoexport !== null && $autoexport !== '') {
			if (in_array($autoexport, Application::FREQUENCIES)) {
			} else {
				return ['autoexport' => [$this->l10n->t('Invalid frequency')]];
			}
		}
		if ($categorysort !== null && $categorysort !== '') {
			if (in_array($categorysort, Application::SORT_ORDERS)) {
			} else {
				return ['categorysort' => [$this->l10n->t('Invalid sort order')]];
			}
		}
		if ($paymentmodesort !== null && $paymentmodesort !== '') {
			if (in_array($paymentmodesort, Application::SORT_ORDERS)) {
			} else {
				return ['paymentmodesort' => [$this->l10n->t('Invalid sort order')]];
			}
		}

		if ($archivedTs !== null) {
			if ($archivedTs === ProjectMapper::ARCHIVED_TS_NOW) {
				$dbTs = (new DateTime())->getTimestamp();
			} elseif ($archivedTs === ProjectMapper::ARCHIVED_TS_UNSET) {
				$dbTs = null;
			} else {
				$dbTs = $archivedTs;
			}
			$dbProject->setArchivedTs($dbTs);
		}

		if ($name !== null) {
			$dbProject->setName($name);
		}

		if ($contact_email !== null && $contact_email !== '') {
			$dbProject->setEmail($contact_email);
		}

		if ($autoexport !== null && $autoexport !== '') {
			$dbProject->setAutoexport($autoexport);
		}
		if ($categorysort !== null && $categorysort !== '') {
			$dbProject->setCategorysort($categorysort);
		}
		if ($paymentmodesort !== null && $paymentmodesort !== '') {
			$dbProject->setPaymentmodesort($paymentmodesort);
		}
		if ($deletion_disabled !== null) {
			$dbProject->setDeletiondisabled($deletion_disabled ? 1 : 0);
		}
		if ($currencyname !== null) {
			$dbProject->setCurrencyname($currencyname === '' ? null : $currencyname);
		}
		$ts = (new DateTime())->getTimestamp();
		$dbProject->setLastchanged($ts);
		$this->projectMapper->update($dbProject);
		return ['success' => true];
	}

	/**
	 * Add a member to a project
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float|null $weight
	 * @param bool $active
	 * @param string|null $color
	 * @param string|null $userId
	 * @return array{error: string}|CospendMember
	 * @throws \OCP\DB\Exception
	 */
	public function createMember(
		string $projectId, string $name, ?float $weight = 1.0, bool $active = true,
		?string $color = null, ?string $userId = null
	): array {
		if ($name === '') {
			return ['error' => $this->l10n->t('Name field is required')];
		}
		if (str_contains($name, '/')) {
			return ['error' => $this->l10n->t('Invalid member name')];
		}
		if ($weight !== null && $weight <= 0.0) {
			return ['error' => $this->l10n->t('Weight is not a valid decimal value')];
		}
		if ($color !== null && $color !== '' && strlen($color) !== 4 && strlen($color) !== 7) {
			return ['error' => $this->l10n->t('Invalid color value')];
		}
		if ($this->memberMapper->getMemberByName($projectId, $name) !== null) {
			return ['error' => $this->l10n->t('This project already has this member')];
		}
		if ($userId !== null && $this->memberMapper->getMemberByUserid($projectId, $userId) !== null) {
			return ['error' => $this->l10n->t('This project already has this member (user)')];
		}

		$newMember = new Member();

		$weightToInsert = $weight === null ? 1.0 : $weight;
		$newMember->setWeight($weightToInsert);

		if ($color !== null
			&& (strlen($color) === 4 || strlen($color) === 7)
			&& preg_match('/^#[0-9A-Fa-f]+/', $color) !== false
		) {
			$newMember->setColor($color);
		}

		$ts = (new DateTime())->getTimestamp();
		$newMember->setLastchanged($ts);
		$newMember->setProjectid($projectId);
		if ($userId !== null) {
			$newMember->setUserid($userId);
		}
		$newMember->setActivated($active ? 1 : 0);
		$newMember->setName($name);

		$createdMember = $this->memberMapper->insert($newMember);
		return $createdMember->jsonSerialize();
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
		$members = $this->memberMapper->getMembers($projectId, $order, $lastchanged);
		return array_map(static function (Member $dbMember) {
			return $dbMember->jsonSerialize();
		}, $members);
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

		$bills = $this->billMapper->getBills($projectId, null, $maxTimestamp);
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
		try {
			$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
			$circlesManager->startSuperSession();
		} catch (Exception $e) {
			return false;
		}
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
	 * For all projects the user has access to, get id => name
	 *
	 * @param string|null $userId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectNames(?string $userId): array {
		if (is_null($userId)) {
			return [];
		}

		$projectNames = [];

		$qb = $this->db->getQueryBuilder();

		$qb->select('id', 'name')
			->from('cospend_projects', 'p')
			->where(
				$qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$projectNames[$row['id']] = $row['name'];
		}
		$req->closeCursor();

		$qb = $this->db->getQueryBuilder();

		// shared with user
		$qb->select('p.id', 'p.name')
			->from('cospend_projects', 'p')
			->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
			->where(
				$qb->expr()->eq('s.userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('s.type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			// avoid putting twice the same project
			// this can happen with a share loop
			if (!isset($projectNames[$row['id']])) {
				$projectNames[$row['id']] = $row['name'];
			}
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		// shared with one of the groups the user is member of
		$userO = $this->userManager->get($userId);

		// get group with which a project is shared
		$candidateGroupIds = [];
		$qb->select('userid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
			)
			->groupBy('userid');
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$groupId = $row['userid'];
			$candidateGroupIds[] = $groupId;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		// is the user member of these groups?
		foreach ($candidateGroupIds as $candidateGroupId) {
			$group = $this->groupManager->get($candidateGroupId);
			if ($group !== null && $group->inGroup($userO)) {
				// get projects shared with this group
				$qb->select('p.id', 'p.name')
					->from('cospend_projects', 'p')
					->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
					->where(
						$qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateGroupId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('s.type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();

				while ($row = $req->fetch()) {
					// avoid putting twice the same project
					// this can happen with a share loop
					if (!isset($projectNames[$row['id']])) {
						$projectNames[$row['id']] = $row['name'];
					}
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();
			}
		}

		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			// get circles with which a project is shared
			$candidateCircleIds = [];
			$qb->select('userid')
				->from('cospend_shares', 's')
				->where(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
				)
				->groupBy('userid');
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$circleId = $row['userid'];
				$candidateCircleIds[] = $circleId;
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();

			// is the user member of these circles?
			foreach ($candidateCircleIds as $candidateCircleId) {
				if ($this->isUserInCircle($userId, $candidateCircleId)) {
					// get projects shared with this circle
					$qb->select('p.id', 'p.name')
						->from('cospend_projects', 'p')
						->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
						->where(
							$qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateCircleId, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('s.type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->executeQuery();

					while ($row = $req->fetch()) {
						// avoid putting twice the same project
						// this can happen with a share loop or multiple shares
						if (!isset($projectNames[$row['id']])) {
							$projectNames[$row['id']] = $row['name'];
						}
					}
					$req->closeCursor();
					$qb = $this->db->getQueryBuilder();
				}
			}
		}
		return $projectNames;
	}

	/**
	 * Get detailed project list for a given NC user
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProjects(string $userId): array {
		$projectids = array_keys($this->getProjectNames($userId));

		// get the projects
		$projects = [];
		foreach ($projectids as $projectid) {
			$project = $this->getProjectInfo($projectid);
			$project['myaccesslevel'] = $this->getUserMaxAccessLevel($userId, $projectid);
			$projects[] = $project;
		}

		return $projects;
	}

	public function getFederatedProjects(string $userId): array {
		$invitations = $this->invitationMapper->getInvitationsForUser($userId, Invitation::STATE_ACCEPTED);
		return array_map(static function (Invitation $invitation) {
			return $invitation->getRemoteProjectId() . '@' . $invitation->getRemoteServerUrl();
		}, $invitations);
	}

	/**
	 * Get categories of a given project
	 *
	 * @param string $projectId
	 * @param bool $getCategories
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getCategoriesOrPaymentModes(string $projectId, bool $getCategories = true): array {
		$elements = [];

		$qb = $this->db->getQueryBuilder();

		if ($getCategories) {
			$sortOrderField = 'categorysort';
			$billTableField = 'categoryid';
			$dbTable = 'cospend_categories';
			$alias = 'cat';
		} else {
			$sortOrderField = 'paymentmodesort';
			$billTableField = 'paymentmodeid';
			$dbTable = 'cospend_paymentmodes';
			$alias = 'pm';
		}

		// get sort method
		$qb->select($sortOrderField)
			->from('cospend_projects', 'p')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		$sortMethod = Application::SORT_ORDER_ALPHA;
		while ($row = $req->fetch()) {
			$sortMethod = $row[$sortOrderField];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if ($sortMethod === Application::SORT_ORDER_MANUAL || $sortMethod === Application::SORT_ORDER_ALPHA) {
			if ($getCategories) {
				$qb = $qb->select('name', 'id', 'encoded_icon', 'color', 'order');
			} else {
				$qb = $qb->select('name', 'id', 'encoded_icon', 'color', 'order', 'old_id');
			}
			$qb->from($dbTable, 'c')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbName = $row['name'];
				$dbIcon = $row['encoded_icon'] === null ? null : urldecode($row['encoded_icon']);
				$dbColor = $row['color'];
				$dbId = (int) $row['id'];
				$dbOrder = (int) $row['order'];
				$elements[$dbId] = [
					'name' => $dbName,
					'icon' => $dbIcon,
					'color' => $dbColor,
					'id' => $dbId,
					'order' => $dbOrder,
				];
				if (!$getCategories) {
					$elements[$dbId]['old_id'] = $row['old_id'];
				}
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();
		} elseif ($sortMethod === Application::SORT_ORDER_MOST_USED || $sortMethod === Application::SORT_ORDER_RECENTLY_USED) {
			// get all categories/paymentmodes
			if ($getCategories) {
				$qb = $qb->select('name', 'id', 'encoded_icon', 'color');
			} else {
				$qb = $qb->select('name', 'id', 'encoded_icon', 'color', 'old_id');
			}
			$qb->from($dbTable, 'c')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbName = $row['name'];
				$dbIcon = $row['encoded_icon'] === null ? null : urldecode($row['encoded_icon']);
				$dbColor = $row['color'];
				$dbId = (int) $row['id'];
				$elements[$dbId] = [
					'name' => $dbName,
					'icon' => $dbIcon,
					'color' => $dbColor,
					'id' => $dbId,
					'order' => null,
				];
				if (!$getCategories) {
					$elements[$dbId]['old_id'] = $row['old_id'];
				}
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();
			// now we get the order
			if ($sortMethod === Application::SORT_ORDER_MOST_USED) {
				// sort by most used
				// first get list of most used
				$mostUsedOrder = [];
				$qb->select($alias . '.id')
					->from($dbTable, $alias)
					->innerJoin($alias, 'cospend_bills', 'bill', $qb->expr()->eq($alias . '.id', 'bill.' . $billTableField))
					->where(
						$qb->expr()->eq($alias . '.projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('bill.deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
					)
					->orderBy($qb->func()->count($alias . '.id'), 'DESC')
					->groupBy($alias . '.id');
				$req = $qb->executeQuery();
				$order = 0;
				while ($row = $req->fetch()) {
					$dbId = (int) $row['id'];
					$mostUsedOrder[$dbId] = $order++;
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();
				// affect order
				foreach ($elements as $cid => $cat) {
					// fallback order is more than max order
					$elements[$cid]['order'] = $mostUsedOrder[$cid] ?? $order;
				}
			} else {
				// sort by most recently used
				$mostUsedOrder = [];
				$qb->select($alias . '.id')
					->from($dbTable, $alias)
					->innerJoin($alias, 'cospend_bills', 'bill', $qb->expr()->eq($alias . '.id', 'bill.' . $billTableField))
					->where(
						$qb->expr()->eq($alias . '.projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('bill.deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
					)
					->orderBy($qb->func()->max('bill.timestamp'), 'DESC')
					->groupBy($alias . '.id');
				$req = $qb->executeQuery();
				$order = 0;
				while ($row = $req->fetch()) {
					$dbId = (int) $row['id'];
					$mostUsedOrder[$dbId] = $order++;
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();
				// affect order
				foreach ($elements as $elemId => $element) {
					// fallback order is more than max order
					$elements[$elemId]['order'] = $mostUsedOrder[$elemId] ?? $order;
				}
			}
		}

		return $elements;
	}

	/**
	 * Get currencies of a project
	 *
	 * @param string $projectId
	 * @return array
	 */
	private function getCurrencies(string $projectId): array {
		$currencies = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('name', 'id', 'exchange_rate')
			->from('cospend_currencies')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$dbName = $row['name'];
			$dbId = (int) $row['id'];
			$dbExchangeRate = (float) $row['exchange_rate'];
			$currencies[] = [
				'name' => $dbName,
				'exchange_rate' => $dbExchangeRate,
				'id' => $dbId,
			];
		}
		$req->closeCursor();

		return $currencies;
	}

	/**
	 * Get user shared access of a project
	 *
	 * @param string $projectId
	 * @return array
	 */
	private function getUserShares(string $projectId): array {
		$shares = [];
		$userIdToName = [];
		$sharesToDelete = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'userid', 'id', 'accesslevel', 'manually_added')
			->from('cospend_shares')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$dbuserId = $row['userid'];
			$dbId = (int) $row['id'];
			$dbAccessLevel = (int) $row['accesslevel'];
			$dbManuallyAdded = (int) $row['manually_added'];
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
				'type' => Application::SHARE_TYPE_USER,
				'manually_added' => $dbManuallyAdded === 1,
			];
		}
		$req->closeCursor();

		// delete shares pointing to unfound users
		foreach ($sharesToDelete as $shId) {
			$this->deleteUserShare($projectId, $shId);
		}

		return $shares;
	}

	/**
	 * Get public links of a project
	 *
	 * @param string $projectId
	 * @param int|null $maxAccessLevel
	 * @return array
	 */
	public function getPublicShares(string $projectId, ?int $maxAccessLevel = null): array {
		$shares = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'userid', 'id', 'accesslevel', 'label', 'password')
			->from('cospend_shares')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_PUBLIC_LINK, IQueryBuilder::PARAM_STR))
			);
		if (!is_null($maxAccessLevel)) {
			$qb->andWhere(
				$qb->expr()->lte('accesslevel', $qb->createNamedParameter($maxAccessLevel, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$dbToken = $row['userid'];
			$dbId = (int) $row['id'];
			$dbAccessLevel = (int) $row['accesslevel'];
			$dbLabel = $row['label'];
			$dbPassword = $row['password'];
			$shares[] = [
				'token' => $dbToken,
				'id' => $dbId,
				'accesslevel' => $dbAccessLevel,
				'label' => $dbLabel,
				'password' => $dbPassword,
				'type' => Application::SHARE_TYPE_PUBLIC_LINK,
			];
		}
		$req->closeCursor();

		return $shares;
	}

	/**
	 * Get project info for a given public share token
	 *
	 * @param string $token
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	public function getShareInfoFromShareToken(string $token): ?array {
		$projectInfo = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'accesslevel', 'label', 'password')
			->from('cospend_shares')
			->where(
				$qb->expr()->eq('userid', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_PUBLIC_LINK, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$projectId = $row['projectid'];
			$label = $row['label'];
			$password = $row['password'];
			$accessLevel = (int) $row['accesslevel'];
			$projectInfo = [
				'projectid' => $projectId,
				'accesslevel' => $accessLevel,
				'label' => $label,
				'password' => $password,
			];
			break;
		}
		$req->closeCursor();

		return $projectInfo;
	}

	/**
	 * Get group shared access list of a project
	 *
	 * @param string $projectId
	 * @return array
	 */
	private function getGroupShares(string $projectId): array {
		$shares = [];
		$groupIdToName = [];
		$sharesToDelete = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('projectid', 'userid', 'id', 'accesslevel')
			->from('cospend_shares')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$dbGroupId = $row['userid'];
			$dbId = (int) $row['id'];
			$dbAccessLevel = (int) $row['accesslevel'];
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
				'type' => Application::SHARE_TYPE_GROUP,
			];
		}
		$req->closeCursor();

		foreach ($sharesToDelete as $shId) {
			$this->deleteGroupShare($projectId, $shId);
		}

		return $shares;
	}

	/**
	 * Get circle shared access list of a project
	 *
	 * @param string $projectId
	 * @return array
	 */
	private function getCircleShares(string $projectId): array {
		$shares = [];

		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			try {
				$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
				$circlesManager->startSuperSession();
			} catch (Exception $e) {
				return [];
			}
			$qb = $this->db->getQueryBuilder();
			$qb->select('projectid', 'userid', 'id', 'accesslevel')
				->from('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$dbCircleId = $row['userid'];
				$dbId = (int) $row['id'];
				$dbAccessLevel = (int) $row['accesslevel'];
				try {
					$circle = $circlesManager->getCircle($dbCircleId);
					$shares[] = [
						'circleid' => $dbCircleId,
						'name' => $circle->getDisplayName(),
						'id' => $dbId,
						'accesslevel' => $dbAccessLevel,
						'type' => Application::SHARE_TYPE_CIRCLE,
					];
				} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				}
			}
			$req->closeCursor();
			$circlesManager->stopSession();
		}
		return $shares;
	}

	/**
	 * Delete a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return array
	 */
	public function deleteMember(string $projectId, int $memberId): array {
		$dbMemberToDelete = $this->memberMapper->getMemberById($projectId, $memberId);
		if ($dbMemberToDelete !== null) {
			$memberToDelete = $dbMemberToDelete->jsonSerialize();
			if (count($this->memberMapper->getBillIdsOfMember($memberId)) === 0) {
				$this->memberMapper->delete($dbMemberToDelete);
			} elseif ($memberToDelete['activated']) {
				$dbMemberToDelete->setActivated(0);
				$this->memberMapper->update($dbMemberToDelete);
			}
			return ['success' => true];
		} else {
			return ['error' => 'Not Found'];
		}
	}

	/**
	 * Get a member from its name
	 *
	 * @param string $projectId
	 * @param string $name
	 * @return array|null
	 */
	public function getMemberByName(string $projectId, string $name): ?array {
		$member = $this->memberMapper->getMemberByName($projectId, $name);
		return $member?->jsonSerialize();
	}

	/**
	 * Get a member from its user ID
	 *
	 * @param string $projectId
	 * @param string|null $userId
	 * @return array|null
	 */
	public function getMemberByUserid(string $projectId, ?string $userId): ?array {
		if ($userId === null) {
			return null;
		}
		$member = $this->memberMapper->getMemberByUserid($projectId, $userId);
		return $member?->jsonSerialize();
	}

	/**
	 * Edit a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $categoryid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @param array|null $paymentModes
	 * @param int|null $deleted
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editBill(
		string $projectId, int $billId, ?string $date, ?string $what, ?int $payer, ?string $payed_for,
		?float $amount, ?string $repeat, ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null, ?string $repeatuntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatfreq = null,
		?array $paymentModes = null, ?int $deleted = null
	): array {
		// if we don't have the payment modes, get them now
		if ($paymentModes === null) {
			$paymentModes = $this->getCategoriesOrPaymentModes($projectId, false);
		}

		$dbBill = $this->billMapper->getBillEntity($projectId, $billId);
		// first check the bill exists
		if ($dbBill === null) {
			return ['message' => $this->l10n->t('There is no such bill')];
		}

		// validate params

		if ($repeat !== null && $repeat !== '') {
			if (!in_array($repeat, Application::FREQUENCIES)) {
				return ['repeat' => $this->l10n->t('Invalid value')];
			}
		}

		if ($timestamp === null && $date !== null && $date !== '') {
			$datetime = DateTime::createFromFormat('Y-m-d', $date);
			if ($datetime === false) {
				return ['date' => $this->l10n->t('Invalid value')];
			}
		}

		if ($payer !== null) {
			$dbPayer = $this->memberMapper->getMemberById($projectId, $payer);
			if ($dbPayer === null) {
				return ['payer' => $this->l10n->t('Not a valid choice')];
			}
		}

		// validate owers
		$owerIds = null;
		// check owers
		if ($payed_for !== null && $payed_for !== '') {
			$owerIds = explode(',', $payed_for);
			if (empty($owerIds)) {
				return ['payed_for' => $this->l10n->t('Invalid value')];
			} else {
				foreach ($owerIds as $owerId) {
					if (!is_numeric($owerId)) {
						return ['payed_for' => $this->l10n->t('Invalid value')];
					}
					if ($this->getMemberById($projectId, (int) $owerId) === null) {
						return ['payed_for' => $this->l10n->t('Not a valid choice')];
					}
				}
			}
		}

		// UPDATE

		$qb = $this->db->getQueryBuilder();
		$qb->update('cospend_bills');

		// set last modification timestamp
		$ts = (new DateTime())->getTimestamp();
		$dbBill->setLastchanged($ts);

		if ($what !== null) {
			$dbBill->setWhat($what);
		}

		if ($comment !== null) {
			$dbBill->setComment($comment);
		}

		if ($deleted !== null) {
			$dbBill->setDeleted($deleted);
		}

		if ($repeat !== null && $repeat !== '') {
			if (in_array($repeat, Application::FREQUENCIES)) {
				$dbBill->setRepeat($repeat);
			}
		}

		if ($repeatfreq !== null) {
			$dbBill->setRepeatfreq($repeatfreq);
		}

		if ($repeatuntil !== null) {
			$dbBill->setRepeatuntil($repeatuntil === '' ? null : $repeatuntil);
		}
		if ($repeatallactive !== null) {
			$dbBill->setRepeatallactive($repeatallactive);
		}
		// payment mode
		if ($paymentmodeid !== null) {
			// is the old_id set for this payment mode? if yes, use it for old 'paymentmode' column
			$paymentmode = 'n';
			if (isset($paymentModes[$paymentmodeid], $paymentModes[$paymentmodeid]['old_id'])
				&& $paymentModes[$paymentmodeid]['old_id'] !== null
				&& $paymentModes[$paymentmodeid]['old_id'] !== ''
			) {
				$paymentmode = $paymentModes[$paymentmodeid]['old_id'];
			}
			$dbBill->setPaymentmodeid($paymentmodeid);
			$dbBill->setPaymentmode($paymentmode);
		} elseif ($paymentmode !== null) {
			// is there a pm with this old id? if yes, use it for new id
			$paymentmodeid = 0;
			foreach ($paymentModes as $id => $pm) {
				if ($pm['old_id'] === $paymentmode) {
					$paymentmodeid = $id;
					break;
				}
			}
			$dbBill->setPaymentmodeid($paymentmodeid);
			$dbBill->setPaymentmode($paymentmode);
		}
		if ($categoryid !== null) {
			$dbBill->setCategoryid($categoryid);
		}
		// priority to timestamp (moneybuster might send both for a moment)
		if ($timestamp !== null) {
			$dbBill->setTimestamp($timestamp);
		} elseif ($date !== null && $date !== '') {
			$datetime = DateTime::createFromFormat('Y-m-d', $date);
			if ($datetime !== false) {
				$dateTs = $datetime->getTimestamp();
				$dbBill->setTimestamp($dateTs);
			}
		}
		if ($amount !== null) {
			$dbBill->setAmount($amount);
		}
		if ($payer !== null) {
			$dbBill->setPayerid($payer);
		}

		$this->billMapper->update($dbBill);

		// edit the bill owers
		if ($owerIds !== null) {
			// delete old bill owers
			$this->billMapper->deleteBillOwersOfBill($billId);
			// insert bill owers
			foreach ($owerIds as $owerId) {
				$qb->insert('cospend_bill_owers')
					->values([
						'billid' => $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT),
						'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
					]);
				$qb->executeStatement();
				$qb = $this->db->getQueryBuilder();
			}
		}

		$this->projectMapper->updateProjectLastChanged($projectId, $ts);

		return ['edited_bill_id' => $billId];
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
		$now = new DateTimeImmutable();
		// in case cron job wasn't executed during several days,
		// continue trying to repeat bills as long as there was at least one repeated
		$continue = true;
		while ($continue) {
			$continue = false;
			// get bills with repetition flag
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'projectid', 'what', 'timestamp', 'amount', 'payerid', 'repeat', 'repeatallactive', 'repeatfreq')
				->from('cospend_bills', 'b')
				->where(
					$qb->expr()->neq('repeat', $qb->createNamedParameter(Application::FREQUENCY_NO, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('deleted', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				);
			// we only repeat one bill
			if (!is_null($billId)) {
				$qb->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
				);
			}
			$req = $qb->executeQuery();
			$bills = [];
			/** @var DateTimeZone[] $timezoneByProjectId */
			$timezoneByProjectId = [];
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
				if (!isset($timezoneByProjectId[$projectid])) {
					$timezoneByProjectId[$projectid] = $this->getProjectTimeZone($projectid);
				}
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();

			foreach ($bills as $bill) {
				$billProjectId = $bill['projectid'];
				$billDate = (new DateTimeImmutable())->setTimestamp($bill['timestamp'])->setTimezone($timezoneByProjectId[$billProjectId]);
				$nextDate = $this->getNextRepetitionDate($bill, $billDate);

				// Unknown repeat interval
				if ($nextDate === null) {
					continue;
				}

				// Repeat if $nextDate is in the past (or today)
				$nowTs = $now->getTimestamp();
				$nextDateTs = $nextDate->getTimestamp();
				if ($nowTs > $nextDateTs || $nextDate->format('Y-m-d') === $now->format('Y-m-d')) {
					$newBillId = $this->repeatBill($bill['projectid'], $bill['id'], $nextDate);
					// bill was not repeated (because of disabled owers or repeatuntil)
					if ($newBillId === null) {
						continue;
					}
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

	private function getProjectTimeZone(string $projectId): DateTimeZone {
		$projectInfo = $this->getProjectInfo($projectId);
		$userId = $projectInfo['userid'];
		$timeZone = $this->config->getUserValue($userId, 'core', 'timezone', null);
		$serverTimeZone = date_default_timezone_get() ?: 'UTC';

		if ($timeZone === null) {
			$timeZone = $serverTimeZone;
		}

		try {
			return new DateTimeZone($timeZone);
		} catch (Exception $e) {
			return new DateTimeZone($serverTimeZone);
		}
	}

	private function copyBillPaymentModeOver(string $projectId, array $bill, string $toProjectId): int {
		$originPayments = $this->getCategoriesOrPaymentModes($projectId, false);
		$destinationPayments = $this->getCategoriesOrPaymentModes($toProjectId, false);

		if ($bill['paymentmodeid'] !== 0) {
			$originPayment = array_filter($originPayments, static function ($val) use ($bill) {
				return $val['id'] === $bill['paymentmodeid'];
			});
			$originPayment = array_shift($originPayment);

			// find a payment mode with the same name
			$paymentNameMatches = array_filter($destinationPayments, static function ($val) use ($originPayment) {
				return $val['name'] === $originPayment['name'];
			});

			// no payment mode match, means new mode
			if (count($paymentNameMatches) === 0) {
				return $this->createPaymentMode($toProjectId, $originPayment['name'], $originPayment['icon'], $originPayment['color']);
			} else {
				return array_shift($paymentNameMatches)['id'];
			}
		}

		return $bill['paymentmodeid'];
	}

	private function copyBillCategoryOver(string $projectId, array $bill, string $toProjectId): int {
		$originCategories = $this->getCategoriesOrPaymentModes($projectId);
		$destinationCategories = $this->getCategoriesOrPaymentModes($toProjectId);

		if ($bill['categoryid'] !== 0 && $bill['categoryid'] !== Application::CATEGORY_REIMBURSEMENT) {
			$originCategory = array_filter($originCategories, static function ($val) use ($bill) {
				return $val['id'] === $bill['categoryid'];
			});
			$originCategory = array_shift($originCategory);

			// find a category with the same name
			$categoryNameMatches = array_filter($destinationCategories, static function ($val) use ($originCategory) {
				return $val['name'] === $originCategory['name'];
			});

			// no category match, means new category
			if (count($categoryNameMatches) === 0) {
				return $this->createCategory($toProjectId, $originCategory['name'], $originCategory['icon'], $originCategory['color']);
			} else {
				return array_shift($categoryNameMatches)['id'];
			}
		}

		return $bill['categoryid'];
	}

	/**
	 * @param string $projectId
	 * @param int $billId
	 * @param string $toProjectId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function moveBill(string $projectId, int $billId, string $toProjectId): array {
		$bill = $this->billMapper->getBill($projectId, $billId);

		// get all members in all the projects and try to match them
		$originMembers = $this->getMembers($projectId, 'lowername');
		$destinationMembers = $this->getMembers($toProjectId, 'lowername');

		// try to match them
		$originalPayer = $originMembers;
		$originalPayer = array_filter($originalPayer, static function ($val) use ($bill) {
			return $val['id'] === $bill['payer_id'];
		});
		$originalPayer = array_shift($originalPayer);

		$newPayer = $destinationMembers;
		$newPayer = array_filter($newPayer, static function ($val) use ($originalPayer) {
			return $val['name'] === $originalPayer['name'];
		});

		if (count($newPayer) < 1) {
			return ['message' => $this->l10n->t('Cannot match payer')];
		}

		$newPayer = array_shift($newPayer);

		// match owers too, these do not mind that much, the user will be able to modify the new invoice just after moving it
		$newOwers = array_filter($destinationMembers, static function ($member) use ($bill) {
			$matches = array_filter($bill['owers'], static function ($oldMember) use ($member) {
				return $oldMember['name'] === $member['name'];
			});

			if (count($matches) === 0) {
				return false;
			}

			return true;
		});

		$newCategoryId = $this->copyBillCategoryOver($projectId, $bill, $toProjectId);
		$newPaymentId = $this->copyBillPaymentModeOver($projectId, $bill, $toProjectId);

		$result = $this->createBill(
			$toProjectId, null, $bill['what'], $newPayer['id'],
			implode(',', array_column($newOwers, 'id')), $bill['amount'], $bill['repeat'],
			$bill['paymentmode'], $newPaymentId,
			$newCategoryId, $bill['repeatallactive'], $bill['repeatuntil'],
			$bill['timestamp'], $bill['comment'], $bill['repeatfreq'], null, $bill['deleted']
		);

		if (!isset($result['inserted_id'])) {
			return ['message' => $this->l10n->t('Cannot create new bill: %1$s', $result['message'])];
		}

		// remove the old bill
		$this->deleteBill($projectId, $billId, true);

		return $result;
	}

	/**
	 * duplicate the bill today and give it the repeat flag
	 * remove the repeat flag on original bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param DateTimeImmutable $targetDatetime
	 * @return int|null
	 * @throws \OCP\DB\Exception
	 */
	private function repeatBill(string $projectId, int $billId, DateTimeImmutable $targetDatetime): ?int {
		$bill = $this->billMapper->getBill($projectId, $billId);

		$owerIds = [];
		if (((int) $bill['repeatallactive']) === 1) {
			$pInfo = $this->getProjectInfo($projectId);
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
			$this->editBill(
				$projectId, $billId, null, null, null, null,
				null, Application::FREQUENCY_NO, null, null,
				null, null
			);
			return null;
		}

		// if bill should be repeated only until...
		if ($bill['repeatuntil'] !== null && $bill['repeatuntil'] !== '') {
			$untilDate = DateTimeImmutable::createFromFormat('Y-m-d', $bill['repeatuntil']);
			if ($targetDatetime > $untilDate) {
				$this->editBill(
					$projectId, $billId, null, null, null, null,
					null, Application::FREQUENCY_NO, null, null,
					null, null
				);
				return null;
			}
		}

		$addBillResult = $this->createBill(
			$projectId, null, $bill['what'], $bill['payer_id'],
			$owerIdsStr, $bill['amount'], $bill['repeat'],
			$bill['paymentmode'], $bill['paymentmodeid'],
			$bill['categoryid'], $bill['repeatallactive'], $bill['repeatuntil'],
			$targetDatetime->getTimestamp(), $bill['comment'], $bill['repeatfreq']
		);

		$newBillId = $addBillResult['inserted_id'] ?? 0;

		$billObj = $this->billMapper->find($newBillId);
		$this->activityManager->triggerEvent(
			ActivityManager::COSPEND_OBJECT_BILL, $billObj,
			ActivityManager::SUBJECT_BILL_CREATE,
			[]
		);

		// now we can remove repeat flag on original bill
		$this->editBill($projectId, $billId, null, $bill['what'], $bill['payer_id'], null,
			$bill['amount'], Application::FREQUENCY_NO, null, null, null, null);
		return $newBillId;
	}

	/**
	 * Get next repetition date of a bill
	 *
	 * @param array $bill
	 * @param DateTimeImmutable $billDate
	 * @return DateTimeImmutable|null
	 * @throws Exception
	 */
	private function getNextRepetitionDate(array $bill, DateTimeImmutable $billDate): ?DateTimeImmutable {
		switch ($bill['repeat']) {
			case Application::FREQUENCY_DAILY:
				if ($bill['repeatfreq'] < 2) {
					return $billDate->add(new DateInterval('P1D'));
				} else {
					return $billDate->add(new DateInterval('P' . $bill['repeatfreq'] . 'D'));
				}
				break;

			case Application::FREQUENCY_WEEKLY:
				if ($bill['repeatfreq'] < 2) {
					return $billDate->add(new DateInterval('P7D'));
				} else {
					$nbDays = 7 * $bill['repeatfreq'];
					return $billDate->add(new DateInterval('P' . $nbDays . 'D'));
				}
				break;

			case Application::FREQUENCY_BI_WEEKLY:
				return $billDate->add(new DateInterval('P14D'));
				break;

			case Application::FREQUENCY_SEMI_MONTHLY:
				$day = (int) $billDate->format('d');
				$month = (int) $billDate->format('m');
				$year = (int) $billDate->format('Y');

				// first of next month
				if ($day >= 15) {
					if ($month === 12) {
						$nextYear = $year + 1;
						$nextMonth = 1;
						return $billDate->setDate($nextYear, $nextMonth, 1);
					} else {
						$nextMonth = $month + 1;
						return $billDate->setDate($year, $nextMonth, 1);
					}
				} else {
					// 15 of same month
					return $billDate->setDate($year, $month, 15);
				}
				break;

			case Application::FREQUENCY_MONTHLY:
				$freq = ($bill['repeatfreq'] < 2) ? 1 : $bill['repeatfreq'];
				$billMonth = (int) $billDate->format('m');
				$yearDelta = intdiv($billMonth + $freq - 1, 12);
				$nextYear = ((int) $billDate->format('Y')) + $yearDelta;
				$nextMonth = (($billMonth + $freq - 1) % 12) + 1;

				// same day of month if possible, otherwise at end of month
				$firstOfNextMonth = $billDate->setDate($nextYear, $nextMonth, 1);
				$billDay = (int) $billDate->format('d');
				$nbDaysInTargetMonth = (int) $firstOfNextMonth->format('t');
				if ($billDay > $nbDaysInTargetMonth) {
					return $billDate->setDate($nextYear, $nextMonth, $nbDaysInTargetMonth);
				} else {
					return $billDate->setDate($nextYear, $nextMonth, $billDay);
				}
				break;

			case Application::FREQUENCY_YEARLY:
				$freq = ($bill['repeatfreq'] < 2) ? 1 : $bill['repeatfreq'];
				$billYear = (int) $billDate->format('Y');
				$billMonth = (int) $billDate->format('m');
				$billDay = (int) $billDate->format('d');
				$nextYear = $billYear + $freq;

				// same day of month if possible, otherwise at end of month + same month
				$firstDayOfTargetMonth = $billDate->setDate($nextYear, $billMonth, 1);
				$nbDaysInTargetMonth = (int) $firstDayOfTargetMonth->format('t');
				if ($billDay > $nbDaysInTargetMonth) {
					return $billDate->setDate($nextYear, $billMonth, $nbDaysInTargetMonth);
				} else {
					return $billDate->setDate($nextYear, $billMonth, $billDay);
				}
				break;
		}

		return null;
	}

	/**
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return int
	 */
	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int {
		$qb = $this->db->getQueryBuilder();

		$encIcon = $icon;
		if ($icon !== null && $icon !== '') {
			$encIcon = urlencode($icon);
		}
		$qb->insert('cospend_paymentmodes')
			->values([
				'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
				'encoded_icon' => $qb->createNamedParameter($encIcon, IQueryBuilder::PARAM_STR),
				'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
				'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
				'order' => $qb->createNamedParameter(is_null($order) ? 0 : $order, IQueryBuilder::PARAM_INT)
			]);
		$qb->executeStatement();

		return $qb->getLastInsertId();
	}

	/**
	 * @param string $projectId
	 * @param int $pmId
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	public function getPaymentMode(string $projectId, int $pmId): ?array {
		$pm = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'projectid', 'encoded_icon', 'color', 'old_id')
			->from('cospend_paymentmodes', 'pm')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($pmId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbPmId = (int) $row['id'];
			$dbName = $row['name'];
			$dbIcon = $row['encoded_icon'] === null ? null : urldecode($row['encoded_icon']);
			$dbColor = $row['color'];
			$dbOldId = $row['old_id'];
			$pm = [
				'name' => $dbName,
				'icon' => $dbIcon,
				'color' => $dbColor,
				'id' => $dbPmId,
				'projectid' => $projectId,
				'old_id' => $dbOldId,
			];
			break;
		}
		$req->closeCursor();
		return $pm;
	}

	/**
	 * @param string $projectId
	 * @param int $pmId
	 * @return array|true[]
	 * @throws \OCP\DB\Exception
	 */
	public function deletePaymentMode(string $projectId, int $pmId): array {
		$pmToDelete = $this->getPaymentMode($projectId, $pmId);
		if ($pmToDelete !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cospend_paymentmodes')
				->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($pmId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			// then get rid of this pm in bills
			$qb = $this->db->getQueryBuilder();
			$qb->update('cospend_bills');
			$qb->set('paymentmodeid', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				->where(
					$qb->expr()->eq('paymentmodeid', $qb->createNamedParameter($pmId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			return ['success' => true];
		} else {
			return ['message' => $this->l10n->t('Not found')];
		}
	}

	/**
	 * @param string $projectId
	 * @param array $order
	 * @return bool
	 */
	public function savePaymentModeOrder(string $projectId, array $order): bool {
		$qb = $this->db->getQueryBuilder();
		foreach ($order as $o) {
			$qb->update('cospend_paymentmodes');
			$qb->set('order', $qb->createNamedParameter($o['order'], IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($o['id'], IQueryBuilder::PARAM_INT))
			)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $this->db->getQueryBuilder();
		}
		return true;
	}

	/**
	 * @param string $projectId
	 * @param int $pmId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return array
	 */
	public function editPaymentMode(
		string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array {
		if ($name !== null && $name !== '') {
			$encIcon = $icon;
			if ($icon !== null && $icon !== '') {
				$encIcon = urlencode($icon);
			}
			if ($this->getPaymentMode($projectId, $pmId) !== null) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('cospend_paymentmodes');
				$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
				$qb->set('encoded_icon', $qb->createNamedParameter($encIcon, IQueryBuilder::PARAM_STR));
				$qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($pmId, IQueryBuilder::PARAM_INT))
				)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					);
				$qb->executeStatement();

				$pm = $this->getPaymentMode($projectId, $pmId);
				if ($pm === null) {
					return ['message' => $this->l10n->t('Impossible to get the edited payment mode')];
				}
				return $pm;
			} else {
				return ['message' => $this->l10n->t('This project has no such payment mode')];
			}
		} else {
			return ['message' => $this->l10n->t('Incorrect field values')];
		}
	}

	/**
	 * Add a new category
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return int
	 */
	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int {
		$qb = $this->db->getQueryBuilder();

		$encIcon = $icon;
		if ($icon !== null && $icon !== '') {
			$encIcon = urlencode($icon);
		}
		$qb->insert('cospend_categories')
			->values([
				'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
				'encoded_icon' => $qb->createNamedParameter($encIcon, IQueryBuilder::PARAM_STR),
				'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
				'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
				'order' => $qb->createNamedParameter(is_null($order) ? 0 : $order, IQueryBuilder::PARAM_INT)
			]);
		$qb->executeStatement();

		return $qb->getLastInsertId();
	}

	/**
	 * Get a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return array|null
	 */
	public function getCategory(string $projectId, int $categoryId): ?array {
		$category = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'projectid', 'encoded_icon', 'color')
			->from('cospend_categories')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbCategoryId = (int) $row['id'];
			$dbName = $row['name'];
			$dbIcon = $row['encoded_icon'] === null ? null : urldecode($row['encoded_icon']);
			$dbColor = $row['color'];
			$category = [
				'id' => $dbCategoryId,
				'projectid' => $projectId,
				'name' => $dbName,
				'icon' => $dbIcon,
				'color' => $dbColor,
			];
			break;
		}
		$req->closeCursor();
		return $category;
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteCategory(string $projectId, int $categoryId): array {
		$categoryToDelete = $this->getCategory($projectId, $categoryId);
		if ($categoryToDelete !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cospend_categories')
				->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $this->db->getQueryBuilder();

			// then get rid of this category in bills
			$qb = $this->db->getQueryBuilder();
			$qb->update('cospend_bills');
			$qb->set('categoryid', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				->where(
					$qb->expr()->eq('categoryid', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			return ['success' => true];
		} else {
			return ['message' => 'Not found'];
		}
	}

	/**
	 * Save the manual category order
	 *
	 * @param string $projectId
	 * @param array $order
	 * @return bool
	 * @throws \OCP\DB\Exception
	 */
	public function saveCategoryOrder(string $projectId, array $order): bool {
		$qb = $this->db->getQueryBuilder();
		foreach ($order as $o) {
			$qb->update('cospend_categories');
			$qb->set('order', $qb->createNamedParameter($o['order'], IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($o['id'], IQueryBuilder::PARAM_INT))
			)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();
			$qb = $this->db->getQueryBuilder();
		}
		return true;
	}

	/**
	 * Edit a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array {
		if ($name !== null && $name !== '') {
			$encIcon = $icon;
			if ($icon !== null && $icon !== '') {
				$encIcon = urlencode($icon);
			}
			if ($this->getCategory($projectId, $categoryId) !== null) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('cospend_categories');
				$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
				$qb->set('encoded_icon', $qb->createNamedParameter($encIcon, IQueryBuilder::PARAM_STR));
				$qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
				)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					);
				$qb->executeStatement();

				$category = $this->getCategory($projectId, $categoryId);
				if ($category === null) {
					return ['message' => $this->l10n->t('Impossible to get the edited category')];
				}
				return $category;
			} else {
				return ['message' => $this->l10n->t('This project has no such category')];
			}
		} else {
			return ['message' => $this->l10n->t('Incorrect field values')];
		}
	}

	/**
	 * Add a currency
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float $rate
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function createCurrency(string $projectId, string $name, float $rate): int {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('cospend_currencies')
			->values([
				'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
				'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
				'exchange_rate' => $qb->createNamedParameter($rate, IQueryBuilder::PARAM_STR)
			]);
		$qb->executeStatement();

		return $qb->getLastInsertId();
	}

	/**
	 * Get one currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	private function getCurrency(string $projectId, int $currencyId): ?array {
		$currency = null;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'exchange_rate', 'projectid')
			->from('cospend_currencies')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($currencyId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();

		while ($row = $req->fetch()) {
			$dbCurrencyId = (int) $row['id'];
			$dbRate = (float) $row['exchange_rate'];
			$dbName = $row['name'];
			$currency = [
				'name' => $dbName,
				'id' => $dbCurrencyId,
				'exchange_rate' => $dbRate,
				'projectid' => $projectId,
			];
			break;
		}
		$req->closeCursor();
		return $currency;
	}

	/**
	 * Delete one currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteCurrency(string $projectId, int $currencyId): array {
		$currencyToDelete = $this->getCurrency($projectId, $currencyId);
		if ($currencyToDelete !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cospend_currencies')
				->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($currencyId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			return ['success' => true];
		} else {
			return ['message' => $this->l10n->t('Not found')];
		}
	}

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @param string $name
	 * @param float $exchange_rate
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editCurrency(string $projectId, int $currencyId, string $name, float $exchange_rate): array {
		if ($name !== '' && $exchange_rate !== 0.0) {
			if ($this->getCurrency($projectId, $currencyId) !== null) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('cospend_currencies');
				$qb->set('exchange_rate', $qb->createNamedParameter($exchange_rate, IQueryBuilder::PARAM_STR));
				$qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
				$qb->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($currencyId, IQueryBuilder::PARAM_INT))
				)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					);
				$qb->executeStatement();

				$currency = $this->getCurrency($projectId, $currencyId);
				if ($currency === null) {
					return ['message' => $this->l10n->t('Impossible to get the edited currency')];
				}
				return $currency;
			} else {
				return ['message' => $this->l10n->t('This project have no such currency')];
			}
		} else {
			return ['message' => $this->l10n->t('Incorrect field values')];
		}
	}

	/**
	 * Add a user shared access to a project
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param string $fromUserId
	 * @param int $accesslevel
	 * @param bool $manually_added
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function createUserShare(
		string $projectId, string $userId, string $fromUserId, int $accesslevel = Application::ACCESS_LEVEL_PARTICIPANT,
		bool $manually_added = true
	): array {
		$user = $this->userManager->get($userId);
		if ($user !== null && $userId !== $fromUserId) {
			$userName = $user->getDisplayName();
			$qb = $this->db->getQueryBuilder();
			$projectInfo = $this->getProjectInfo($projectId);
			// check if someone tries to share the project with its owner
			if ($userId !== $projectInfo['userid']) {
				// check if user share exists
				$qb->select('userid', 'projectid')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbuserId = null;
				while ($row = $req->fetch()) {
					$dbuserId = $row['userid'];
					break;
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();

				if ($dbuserId === null) {
					if ($this->getUserMaxAccessLevel($fromUserId, $projectId) >= $accesslevel) {
						$qb->insert('cospend_shares')
							->values([
								'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
								'userid' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
								'type' => $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR),
								'accesslevel' => $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT),
								'manually_added' => $qb->createNamedParameter($manually_added ? 1 : 0, IQueryBuilder::PARAM_INT),
							]);
						$qb->executeStatement();

						$insertedShareId = $qb->getLastInsertId();
						$response = [
							'id' => $insertedShareId,
							'name' => $userName,
							'accesslevel' => $accesslevel,
							'manually_added' => $manually_added,
							'userid' => $userId,
							'type' => Application::SHARE_TYPE_USER,
						];

						// activity
						$projectObj = $this->projectMapper->find($projectId);
						$this->activityManager->triggerEvent(
							ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
							ActivityManager::SUBJECT_PROJECT_SHARE,
							['who' => $userId, 'type' => Application::SHARE_TYPE_USER]
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
							->setUser($userId)
							->setDateTime(new DateTime())
							->setObject('addusershare', $projectId)
							->setSubject('add_user_share', [$fromUserId, $projectInfo['name']])
							->addAction($acceptAction)
							->addAction($declineAction);

						$manager->notify($notification);

						return $response;
					} else {
						return ['message' => $this->l10n->t('You are not authorized to give such access level')];
					}
				} else {
					return ['message' => $this->l10n->t('Already shared with this user')];
				}
			} else {
				return ['message' => $this->l10n->t('Impossible to share the project with its owner')];
			}
		} else {
			return ['message' => $this->l10n->t('No such user')];
		}
	}

	/**
	 * Add public share access (public link with token)
	 *
	 * @param string $projectId
	 * @param string|null $label
	 * @param string|null $password
	 * @param int $accesslevel
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function createPublicShare(
		string $projectId, ?string $label = null, ?string $password = null, int $accesslevel = Application::ACCESS_LEVEL_PARTICIPANT
	): array {
		$qb = $this->db->getQueryBuilder();
		// generate token
		$token = md5($projectId.rand());

		$qb->insert('cospend_shares')
			->values([
				'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
				'userid' => $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR),
				'type' => $qb->createNamedParameter(Application::SHARE_TYPE_PUBLIC_LINK, IQueryBuilder::PARAM_STR),
				'accesslevel' => $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT),
				'label' => $qb->createNamedParameter($label, IQueryBuilder::PARAM_STR),
				'password' => $qb->createNamedParameter($password, IQueryBuilder::PARAM_STR),
			]);
		$qb->executeStatement();

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
			'id' => $insertedShareId,
			'accesslevel' => $accesslevel,
			'label' => $label,
			'password' => $password,
			'type' => Application::SHARE_TYPE_PUBLIC_LINK,
		];
	}

	/**
	 * Change shared access permissions
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param int $accessLevel
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editShareAccessLevel(string $projectId, int $shId, int $accessLevel): array {
		// check if user share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		while ($row = $req->fetch()) {
			$dbId = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if ($dbId !== null) {
			// set the accesslevel
			$qb->update('cospend_shares')
				->set('accesslevel', $qb->createNamedParameter($accessLevel, IQueryBuilder::PARAM_INT))
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
				);
			$qb->executeStatement();

			return ['success' => true];
		} else {
			return ['message' => $this->l10n->t('No such share')];
		}
	}

	/**
	 * Change shared access permissions
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param string|null $label
	 * @param string|null $password
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editShareAccess(string $projectId, int $shId, ?string $label = null, ?string $password = null): array {
		// check if user share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		while ($row = $req->fetch()) {
			$dbId = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if (!is_null($dbId) && (!is_null($label) || !is_null($password))) {
			$qb->update('cospend_shares');
			if (!is_null($label)) {
				if ($label === '') {
					$label = null;
				}
				$qb->set('label', $qb->createNamedParameter($label, IQueryBuilder::PARAM_STR));
			}
			if (!is_null($password)) {
				if ($password === '') {
					$password = null;
				}
				$qb->set('password', $qb->createNamedParameter($password, IQueryBuilder::PARAM_STR));
			}
			$qb->where(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
				);
			$qb->executeStatement();

			return ['success' => true];
		} else {
			return ['message' => $this->l10n->t('No such share')];
		}
	}

	/**
	 * Delete user shared access
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param string|null $fromUserId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteUserShare(string $projectId, int $shId, ?string $fromUserId = null): array {
		// check if user share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		$dbUserId = null;
		while ($row = $req->fetch()) {
			$dbId = $row['id'];
			$dbUserId = $row['userid'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if ($dbId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_USER, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			// activity
			$projectObj = $this->projectMapper->find($projectId);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
				ActivityManager::SUBJECT_PROJECT_UNSHARE,
				['who' => $dbUserId, 'type' => Application::SHARE_TYPE_USER]
			);

			// SEND NOTIFICATION
			if (!is_null($fromUserId)) {
				$projectInfo = $this->getProjectInfo($projectId);

				$manager = $this->notificationManager;
				$notification = $manager->createNotification();

				$acceptAction = $notification->createAction();
				$acceptAction->setLabel('accept')
					->setLink('/apps/cospend', 'GET');

				$declineAction = $notification->createAction();
				$declineAction->setLabel('decline')
					->setLink('/apps/cospend', 'GET');

				$notification->setApp('cospend')
					->setUser($dbUserId)
					->setDateTime(new DateTime())
					->setObject('deleteusershare', $projectId)
					->setSubject('delete_user_share', [$fromUserId, $projectInfo['name']])
					->addAction($acceptAction)
					->addAction($declineAction)
				;

				$manager->notify($notification);
			}

			return ['success' => true];
		} else {
			return ['message' => $this->l10n->t('No such share')];
		}
	}

	/**
	 * Delete public shared access
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deletePublicShare(string $projectId, int $shId): array {
		// check if public share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'userid', 'projectid')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_PUBLIC_LINK, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbId = null;
		while ($row = $req->fetch()) {
			$dbId = $row['id'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if ($dbId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_PUBLIC_LINK, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

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
			return ['message' => $this->l10n->t('No such shared access')];
		}
	}

	/**
	 * Add group shared access
	 *
	 * @param string $projectId
	 * @param string $groupId
	 * @param string|null $fromUserId
	 * @param int $accesslevel
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function createGroupShare(
		string $projectId, string $groupId, ?string $fromUserId = null, int $accesslevel = Application::ACCESS_LEVEL_PARTICIPANT
	): array {
		if ($this->groupManager->groupExists($groupId)) {
			$groupName = $this->groupManager->get($groupId)->getDisplayName();
			$qb = $this->db->getQueryBuilder();
			// check if user share exists
			$qb->select('userid', 'projectid')
				->from('cospend_shares', 's')
				->where(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('userid', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();
			$dbGroupId = null;
			while ($row = $req->fetch()) {
				$dbGroupId = $row['userid'];
				break;
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();

			if ($dbGroupId === null) {
				$qb->insert('cospend_shares')
					->values([
						'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
						'userid' => $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR),
						'type' => $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR),
						'accesslevel' => $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT),
					]);
				$qb->executeStatement();

				$insertedShareId = $qb->getLastInsertId();

				// activity
				$projectObj = $this->projectMapper->find($projectId);
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
					ActivityManager::SUBJECT_PROJECT_SHARE,
					['who' => $groupId, 'type' => Application::SHARE_TYPE_GROUP]
				);

				return [
					'id' => $insertedShareId,
					'name' => $groupName,
					'groupid' => $groupId,
					'accesslevel' => $accesslevel,
					'type' => Application::SHARE_TYPE_GROUP,
				];
			} else {
				return ['message' => $this->l10n->t('Already shared with this group')];
			}
		} else {
			return ['message' => $this->l10n->t('No such group')];
		}
	}

	/**
	 * Delete group shared access
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param string|null $fromUserId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteGroupShare(string $projectId, int $shId, ?string $fromUserId = null): array {
		// check if group share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'projectid', 'id')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbGroupId = null;
		while ($row = $req->fetch()) {
			$dbGroupId = $row['userid'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if ($dbGroupId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_GROUP, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			// activity
			$projectObj = $this->projectMapper->find($projectId);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
				ActivityManager::SUBJECT_PROJECT_UNSHARE,
				['who' => $dbGroupId, 'type' => Application::SHARE_TYPE_GROUP]
			);

			return ['success' => true];
		} else {
			return ['message' => $this->l10n->t('No such share')];
		}
	}

	/**
	 * Add circle shared access
	 *
	 * @param string $projectId
	 * @param string $circleId
	 * @param string|null $fromUserId
	 * @param int $accesslevel
	 * @return array
	 * @throws InitiatorNotFoundException
	 * @throws RequestBuilderException
	 * @throws \OCP\DB\Exception
	 */
	public function createCircleShare(string $projectId, string $circleId, ?string $fromUserId = null, int $accesslevel = Application::ACCESS_LEVEL_PARTICIPANT): array {
		// check if circleId exists
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			try {
				$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
				$circlesManager->startSuperSession();
			} catch (Exception $e) {
				return ['message' => $this->l10n->t('Impossible to get the circle manager')];
			}

			$exists = true;
			$circleName = '';
			try {
				$circle = $circlesManager->getCircle($circleId);
				$circleName = $circle->getDisplayName();
			} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				$exists = false;
			}

			if ($circleId !== '' && $exists) {
				$qb = $this->db->getQueryBuilder();
				// check if circle share exists
				$qb->select('userid', 'projectid')
					->from('cospend_shares', 's')
					->where(
						$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('userid', $qb->createNamedParameter($circleId, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->executeQuery();
				$dbCircleId = null;
				while ($row = $req->fetch()) {
					$dbCircleId = $row['userid'];
					break;
				}
				$req->closeCursor();
				$qb = $this->db->getQueryBuilder();

				if ($dbCircleId === null) {
					$qb->insert('cospend_shares')
						->values([
							'projectid' => $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR),
							'userid' => $qb->createNamedParameter($circleId, IQueryBuilder::PARAM_STR),
							'type' => $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR),
							'accesslevel' => $qb->createNamedParameter($accesslevel, IQueryBuilder::PARAM_INT),
						]);
					$qb->executeStatement();

					$insertedShareId = $qb->getLastInsertId();

					// activity
					$projectObj = $this->projectMapper->find($projectId);
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
						ActivityManager::SUBJECT_PROJECT_SHARE,
						['who' => $circleId, 'type' => Application::SHARE_TYPE_CIRCLE]
					);

					$circlesManager->stopSession();
					return [
						'id' => $insertedShareId,
						'name' => $circleName,
						'circleid' => $circleId,
						'accesslevel' => $accesslevel,
						'type' => Application::SHARE_TYPE_CIRCLE,
					];
				} else {
					$circlesManager->stopSession();
					return ['message' => $this->l10n->t('Already shared with this circle')];
				}
			} else {
				$circlesManager->stopSession();
				return ['message' => $this->l10n->t('No such circle')];
			}
		} else {
			return ['message' => $this->l10n->t('Circles app is not enabled')];
		}
	}

	/**
	 * Delete circle shared access
	 *
	 * @param string $projectId
	 * @param int $shId
	 * @param string|null $fromUserId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteCircleShare(string $projectId, int $shId, ?string $fromUserId = null): array {
		// check if circle share exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'projectid', 'id')
			->from('cospend_shares', 's')
			->where(
				$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		$dbCircleId = null;
		while ($row = $req->fetch()) {
			$dbCircleId = $row['userid'];
			break;
		}
		$req->closeCursor();
		$qb = $this->db->getQueryBuilder();

		if ($dbCircleId !== null) {
			// delete
			$qb->delete('cospend_shares')
				->where(
					$qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('id', $qb->createNamedParameter($shId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('type', $qb->createNamedParameter(Application::SHARE_TYPE_CIRCLE, IQueryBuilder::PARAM_STR))
				);
			$qb->executeStatement();

			// activity
			$projectObj = $this->projectMapper->find($projectId);
			$this->activityManager->triggerEvent(
				ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
				ActivityManager::SUBJECT_PROJECT_UNSHARE,
				['who' => $dbCircleId, 'type' => Application::SHARE_TYPE_CIRCLE]
			);

			$response = ['success' => true];
		} else {
			$response = ['message' => $this->l10n->t('No such share')];
		}
		return $response;
	}

	/**
	 * Export settlement plan in CSV
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return array
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function exportCsvSettlement(string $projectId, string $userId, ?int $centeredOn = null, ?int $maxTimestamp = null): array {
		// create export directory if needed
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);
		if (!$folder instanceof Folder) {
			return ['message' => $outPath . ' is not a directory'];
		}

		// create file
		if ($folder->nodeExists($projectId.'-settlement.csv')) {
			$folder->get($projectId.'-settlement.csv')->delete();
		}
		$file = $folder->newFile($projectId.'-settlement.csv');
		$handler = $file->fopen('w');
		fwrite(
			$handler,
			'"' . $this->l10n->t('Who pays?')
			. '","' . $this->l10n->t('To whom?')
			. '","' . $this->l10n->t('How much?')
			. '"' . "\n"
		);
		$settlement = $this->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
		$transactions = $settlement['transactions'];

		$members = $this->getMembers($projectId);
		$memberIdToName = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
		}

		foreach ($transactions as $transaction) {
			fwrite(
				$handler,
				'"' . $memberIdToName[$transaction['from']]
				. '","' . $memberIdToName[$transaction['to']]
				. '",' . (float) $transaction['amount']
				. "\n"
			);
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $projectId . '-settlement.csv'];
	}

	/**
	 * Create directory where things will be exported
	 *
	 * @param Folder $userFolder
	 * @param string $outPath
	 * @return string
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function createAndCheckExportDirectory(Folder $userFolder, string $outPath): string {
		if (!$userFolder->nodeExists($outPath)) {
			$userFolder->newFolder($outPath);
		}
		if ($userFolder->nodeExists($outPath)) {
			$folder = $userFolder->get($outPath);
			if (!$folder instanceof Folder) {
				return $this->l10n->t('%1$s is not a folder', [$outPath]);
			} elseif (!$folder->isCreatable()) {
				return $this->l10n->t('%1$s is not writeable', [$outPath]);
			} else {
				return '';
			}
		} else {
			return $this->l10n->t('Impossible to create %1$s', [$outPath]);
		}
	}

	/**
	 * @param string $projectId
	 * @param string $userId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param bool $showDisabled
	 * @param int|null $currencyId
	 * @return array
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function exportCsvStatistics(
		string $projectId, string $userId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $category = null,
		?float $amountMin = null, ?float $amountMax = null,
		bool $showDisabled = true, ?int $currencyId = null
	): array {
		// create export directory if needed
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);
		if (!$folder instanceof Folder) {
			return ['message' => $outPath . ' is not a directory'];
		}

		// create file
		if ($folder->nodeExists($projectId.'-stats.csv')) {
			$folder->get($projectId.'-stats.csv')->delete();
		}
		$file = $folder->newFile($projectId.'-stats.csv');
		$handler = $file->fopen('w');
		fwrite(
			$handler,
			$this->l10n->t('Member name')
			. ',' . $this->l10n->t('Paid')
			. ',' . $this->l10n->t('Spent')
			. ',' . $this->l10n->t('Balance')
			. "\n"
		);
		$allStats = $this->getProjectStatistics(
			$projectId, 'lowername', $tsMin, $tsMax, $paymentModeId,
			$category, $amountMin, $amountMax, $showDisabled, $currencyId
		);
		$stats = $allStats['stats'];

		foreach ($stats as $stat) {
			fwrite(
				$handler,
				'"' . $stat['member']['name']
				. '",' . (float) $stat['paid']
				. ',' . (float) $stat['spent']
				. ',' . (float) $stat['balance']
				. "\n"
			);
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $projectId . '-stats.csv'];
	}

	/**
	 * Export project in CSV
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string $userId
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function exportCsvProject(string $projectId, string $userId, ?string $name = null): array {
		// create export directory if needed
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);
		if (!$folder instanceof Folder) {
			return ['message' => $outPath . ' is not a directory'];
		}

		// create file
		$filename = $projectId.'.csv';
		if ($name !== null) {
			$filename = $name;
			if (!str_ends_with($filename, '.csv')) {
				$filename .= '.csv';
			}
		}
		if ($folder->nodeExists($filename)) {
			$folder->get($filename)->delete();
		}
		$file = $folder->newFile($filename);
		$handler = $file->fopen('w');
		foreach ($this->getJsonProject($projectId) as $chunk) {
			fwrite($handler, $chunk);
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $filename];
	}

	/**
	 * @param string $projectId
	 * @return Generator
	 * @throws \OCP\DB\Exception
	 */
	public function getJsonProject(string $projectId): Generator {
		// members
		yield "name,weight,active,color\n";
		$projectInfo = $this->getProjectInfo($projectId);
		$members = $projectInfo['members'];
		$memberIdToName = [];
		$memberIdToWeight = [];
		$memberIdToActive = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
			$memberIdToWeight[$member['id']] = $member['weight'];
			$memberIdToActive[$member['id']] = (int) $member['activated'];
			$c = $member['color'];
			yield '"' . $member['name'] . '",'
				. (float) $member['weight'] . ','
				. (int) $member['activated'] . ',"'
				. sprintf("#%02x%02x%02x", $c['r'] ?? 0, $c['g'] ?? 0, $c['b'] ?? 0) . '"'
				. "\n";
		}
		// bills
		yield "\nwhat,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,paymentmodeid,comment,deleted\n";
		$bills = $this->billMapper->getBills(
			$projectId, null, null, null, null, null,
			null, null, null, null, false, null, null
		);
		foreach ($bills as $bill) {
			$owerNames = [];
			foreach ($bill['owers'] as $ower) {
				$owerNames[] = $ower['name'];
			}
			$owersTxt = implode(',', $owerNames);

			$payer_id = $bill['payer_id'];
			$payer_name = $memberIdToName[$payer_id];
			$payer_weight = $memberIdToWeight[$payer_id];
			$payer_active = $memberIdToActive[$payer_id];
			$dateTime = DateTime::createFromFormat('U', $bill['timestamp']);
			$oldDateStr = $dateTime->format('Y-m-d');
			yield '"' . $bill['what'] . '",'
				. (float) $bill['amount'] . ','
				. $oldDateStr . ','
				. $bill['timestamp'] . ',"'
				. $payer_name . '",'
				. (float) $payer_weight . ','
				. $payer_active . ',"'
				. $owersTxt . '",'
				. $bill['repeat'] . ','
				. $bill['repeatfreq'] . ','
				. $bill['repeatallactive'] .','
				. $bill['repeatuntil'] . ','
				. $bill['categoryid'] . ','
				. $bill['paymentmode'] . ','
				. $bill['paymentmodeid'] . ',"'
				. urlencode($bill['comment']) . '",'
				. $bill['deleted']
				. "\n";
		}

		// write categories
		$categories = $projectInfo['categories'];
		if (count($categories) > 0) {
			yield "\ncategoryname,categoryid,icon,color\n";
			foreach ($categories as $id => $cat) {
				yield '"' . $cat['name'] . '",' .
					(int) $id . ',"' .
					$cat['icon'] . '","' .
					$cat['color'] . '"' .
					"\n";
			}
		}

		// write payment modes
		$paymentModes = $projectInfo['paymentmodes'];
		if (count($paymentModes) > 0) {
			yield "\npaymentmodename,paymentmodeid,icon,color\n";
			foreach ($paymentModes as $id => $pm) {
				yield '"' . $pm['name'] . '",' .
					(int) $id . ',"' .
					$pm['icon'] . '","' .
					$pm['color'] . '"' .
					"\n";
			}
		}

		// write currencies
		$currencies = $projectInfo['currencies'];
		if (count($currencies) > 0) {
			yield "\ncurrencyname,exchange_rate\n";
			// main currency
			yield '"' . $projectInfo['currencyname'] . '",1' . "\n";
			foreach ($currencies as $cur) {
				yield '"' . $cur['name']
					. '",' . (float) $cur['exchange_rate']
					. "\n";
			}
		}

		return [];
	}

	/**
	 * Wrap the import process in an atomic DB transaction
	 * This increases insert performance a lot
	 *
	 * importCsvProject() still takes care of cleaning up created entities in case of error
	 * but this could be done by rollBack
	 *
	 * This could be done with TTransactional::atomic() when we drop support for NC < 24
	 *
	 * @param $handle
	 * @param string $userId
	 * @param string $projectName
	 * @return array
	 * @throws Throwable
	 * @throws \OCP\DB\Exception
	 */
	public function importCsvProjectAtomicWrapper($handle, string $userId, string $projectName): array {
		$this->db->beginTransaction();
		try {
			$result = $this->importCsvProjectStream($handle, $userId, $projectName);
			$this->db->commit();
			return $result;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Import CSV project file
	 *
	 * @param string $path
	 * @param string $userId
	 * @return array
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws Throwable
	 * @throws \OCP\DB\Exception
	 */
	public function importCsvProject(string $path, string $userId): array {
		$cleanPath = str_replace(['../', '..\\'], '', $path);
		$userFolder = $this->root->getUserFolder($userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if (($handle = $file->fopen('r')) !== false) {
					$projectName = preg_replace('/\.csv$/', '', $file->getName());
					return $this->importCsvProjectAtomicWrapper($handle, $userId, $projectName);
				} else {
					return ['message' => $this->l10n->t('Access denied')];
				}
			} else {
				return ['message' => $this->l10n->t('Access denied')];
			}
		} else {
			return ['message' => $this->l10n->t('Access denied')];
		}
	}

	/**
	 * @param $handle
	 * @param string $userId
	 * @param string $projectName
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function importCsvProjectStream($handle, string $userId, string $projectName): array {
		$columns = [];
		$membersByName = [];
		$bills = [];
		$currencies = [];
		$mainCurrencyName = null;
		$categories = [];
		$categoryIdConv = [];
		$paymentModes = [];
		$paymentModeIdConv = [];
		$previousLineEmpty = false;
		$currentSection = null;
		$row = 0;
		while (($data = fgetcsv($handle, 0, ',')) !== false) {
			$uni = array_unique($data);
			if ($data === [null] || (count($uni) === 1 && $uni[0] === '')) {
				$previousLineEmpty = true;
			} elseif ($row === 0 || $previousLineEmpty) {
				// determine which section we're entering
				$previousLineEmpty = false;
				$nbCol = count($data);
				$columns = [];
				for ($c = 0; $c < $nbCol; $c++) {
					if ($data[$c] !== '') {
						$columns[$data[$c]] = $c;
					}
				}
				if (array_key_exists('what', $columns)
					&& array_key_exists('amount', $columns)
					&& (array_key_exists('date', $columns) || array_key_exists('timestamp', $columns))
					&& array_key_exists('payer_name', $columns)
					&& array_key_exists('payer_weight', $columns)
					&& array_key_exists('owers', $columns)
				) {
					$currentSection = 'bills';
				} elseif (array_key_exists('name', $columns)
					&& array_key_exists('weight', $columns)
					&& array_key_exists('active', $columns)
					&& array_key_exists('color', $columns)
				) {
					$currentSection = 'members';
				} elseif (array_key_exists('icon', $columns)
					&& array_key_exists('color', $columns)
					&& array_key_exists('paymentmodeid', $columns)
					&& array_key_exists('paymentmodename', $columns)
				) {
					$currentSection = 'paymentmodes';
				} elseif (array_key_exists('icon', $columns)
					&& array_key_exists('color', $columns)
					&& array_key_exists('categoryid', $columns)
					&& array_key_exists('categoryname', $columns)
				) {
					$currentSection = 'categories';
				} elseif (array_key_exists('exchange_rate', $columns)
					&& array_key_exists('currencyname', $columns)
				) {
					$currentSection = 'currencies';
				} else {
					fclose($handle);
					return ['message' => $this->l10n->t('Malformed CSV, bad column names at line %1$s', [$row + 1])];
				}
			} else {
				// normal line: bill/category/payment mode/currency
				$previousLineEmpty = false;
				if ($currentSection === 'categories') {
					if (mb_strlen($data[$columns['icon']], 'UTF-8') && preg_match('!\S!u', $data[$columns['icon']])) {
						$icon = $data[$columns['icon']];
					} else {
						$icon = null;
					}
					$color = $data[$columns['color']];
					$categoryname = $data[$columns['categoryname']];
					if (!is_numeric($data[$columns['categoryid']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding category %1$s', [$categoryname])];
					}
					$categoryid = (int) $data[$columns['categoryid']];
					$categories[] = [
						'icon' => $icon,
						'color' => $color,
						'id' => $categoryid,
						'name' => $categoryname,
					];
				} elseif ($currentSection === 'paymentmodes') {
					if (mb_strlen($data[$columns['icon']], 'UTF-8') && preg_match('!\S!u', $data[$columns['icon']])) {
						$icon = $data[$columns['icon']];
					} else {
						$icon = null;
					}
					$paymentmodename = $data[$columns['paymentmodename']];
					if (!is_numeric($data[$columns['paymentmodeid']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding payment mode %1$s', [$paymentmodename])];
					}
					$color = $data[$columns['color']];
					$paymentmodeid = (int) $data[$columns['paymentmodeid']];
					$paymentModes[] = [
						'icon' => $icon,
						'color' => $color,
						'id' => $paymentmodeid,
						'name' => $paymentmodename,
					];
				} elseif ($currentSection === 'currencies') {
					$name = $data[$columns['currencyname']];
					if (!is_numeric($data[$columns['exchange_rate']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding currency %1$s', [$name])];
					}
					$exchange_rate = (float) $data[$columns['exchange_rate']];
					if (($exchange_rate) === 1.0) {
						$mainCurrencyName = $name;
					} else {
						$currencies[] = [
							'name' => $name,
							'exchange_rate' => $exchange_rate,
						];
					}
				} elseif ($currentSection === 'members') {
					$name = trim($data[$columns['name']]);
					if (!is_numeric($data[$columns['weight']]) || !is_numeric($data[$columns['active']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding member %1$s', [$name])];
					}
					$weight = (float) $data[$columns['weight']];
					$active = (int) $data[$columns['active']];
					$color = $data[$columns['color']];
					if (strlen($name) > 0
						&& preg_match('/^#[0-9A-Fa-f]+$/', $color) !== false
					) {
						$membersByName[$name] = [
							'weight' => $weight,
							'active' => $active !== 0,
							'color' => $color,
						];
					} else {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, invalid member on line %1$s', [$row + 1])];
					}
				} elseif ($currentSection === 'bills') {
					$what = $data[$columns['what']];
					if (!is_numeric($data[$columns['amount']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, invalid amount on line %1$s', [$row + 1])];
					}
					$amount = (float) $data[$columns['amount']];
					$timestamp = null;
					// priority to timestamp
					if (array_key_exists('timestamp', $columns)) {
						$timestamp = (int) $data[$columns['timestamp']];
					} elseif (array_key_exists('date', $columns)) {
						$date = $data[$columns['date']];
						$datetime = DateTime::createFromFormat('Y-m-d', $date);
						if ($datetime !== false) {
							$timestamp = $datetime->getTimestamp();
						}
					}
					if ($timestamp === null) {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, missing or invalid date/timestamp on line %1$s', [$row + 1])];
					}
					$payer_name = $data[$columns['payer_name']];
					$payer_weight = $data[$columns['payer_weight']];
					$owers = $data[$columns['owers']];
					$payer_active = array_key_exists('payer_active', $columns) ? $data[$columns['payer_active']] : 1;
					$repeat = array_key_exists('repeat', $columns) ? $data[$columns['repeat']] : Application::FREQUENCY_NO;
					$categoryid = array_key_exists('categoryid', $columns) ? (int) $data[$columns['categoryid']] : null;
					$paymentmode = array_key_exists('paymentmode', $columns) ? $data[$columns['paymentmode']] : null;
					$paymentmodeid = array_key_exists('paymentmodeid', $columns) ? (int) $data[$columns['paymentmodeid']] : null;
					$repeatallactive = array_key_exists('repeatallactive', $columns) ? (int) $data[$columns['repeatallactive']] : 0;
					$repeatuntil = array_key_exists('repeatuntil', $columns) ? $data[$columns['repeatuntil']] : null;
					$repeatfreq = array_key_exists('repeatfreq', $columns) ? (int) $data[$columns['repeatfreq']] : 1;
					$comment = array_key_exists('comment', $columns) ? urldecode($data[$columns['comment']] ?? '') : null;
					$deleted = array_key_exists('deleted', $columns) ? (int) $data[$columns['deleted']] : 0;

					// manage members
					if (!isset($membersByName[$payer_name])) {
						$membersByName[$payer_name] = [
							'active' => ((int) $payer_active) !== 0,
							'weight' => 1.0,
							'color' => null,
						];
						if (is_numeric($payer_weight)) {
							$membersByName[$payer_name]['weight'] = (float) $payer_weight;
						} else {
							fclose($handle);
							return ['message' => $this->l10n->t('Malformed CSV, invalid payer weight on line %1$s', [$row + 1])];
						}
					}
					if (strlen($owers) === 0) {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, invalid owers on line %1$s', [$row + 1])];
					}
					if ($what !== 'deleteMeIfYouWant') {
						$owersArray = explode(',', $owers);
						foreach ($owersArray as $ower) {
							$strippedOwer = trim($ower);
							if (strlen($strippedOwer) === 0) {
								fclose($handle);
								return ['message' => $this->l10n->t('Malformed CSV, invalid owers on line %1$s', [$row + 1])];
							}
							if (!isset($membersByName[$strippedOwer])) {
								$membersByName[$strippedOwer]['weight'] = 1.0;
								$membersByName[$strippedOwer]['active'] = true;
								$membersByName[$strippedOwer]['color'] = null;
							}
						}
						$bills[] = [
							'what' => $what,
							'comment' => $comment,
							'timestamp' => $timestamp,
							'amount' => $amount,
							'payer_name' => $payer_name,
							'owers' => $owersArray,
							'paymentmode' => $paymentmode,
							'paymentmodeid' => $paymentmodeid,
							'categoryid' => $categoryid,
							'repeat' => $repeat,
							'repeatuntil' => $repeatuntil,
							'repeatallactive' => $repeatallactive,
							'repeatfreq' => $repeatfreq,
							'deleted' => $deleted,
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
		$projectid = Utils::slugify($projectName);
		$createDefaultCategories = (count($categories) === 0);
		$createDefaultPaymentModes = (count($paymentModes) === 0);
		$projResult = $this->createProject(
			$projectName, $projectid, $userEmail, $userId,
			$createDefaultCategories, $createDefaultPaymentModes
		);
		if (!isset($projResult['id'])) {
			return ['message' => $this->l10n->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
		}
		// set project main currency
		if ($mainCurrencyName !== null) {
			$this->editProject($projectid, $projectName, null, null, $mainCurrencyName);
		}
		// add payment modes
		foreach ($paymentModes as $pm) {
			$insertedPmId = $this->createPaymentMode($projectid, $pm['name'], $pm['icon'], $pm['color']);
			$paymentModeIdConv[$pm['id']] = $insertedPmId;
		}
		// add categories
		foreach ($categories as $cat) {
			$insertedCatId = $this->createCategory($projectid, $cat['name'], $cat['icon'], $cat['color']);
			$categoryIdConv[$cat['id']] = $insertedCatId;
		}
		// add currencies
		foreach ($currencies as $cur) {
			$insertedCurId = $this->createCurrency($projectid, $cur['name'], $cur['exchange_rate']);
		}
		// add members
		foreach ($membersByName as $memberName => $member) {
			$insertedMember = $this->createMember(
				$projectid, $memberName, $member['weight'], $member['active'], $member['color'] ?? null
			);
			$memberNameToId[$memberName] = $insertedMember['id'];
		}
		$dbPaymentModes = $this->getCategoriesOrPaymentModes($projectid, false);
		// add bills
		foreach ($bills as $bill) {
			// manage category id if this is a custom category
			$catId = $bill['categoryid'];
			if ($catId !== null && $catId > 0) {
				$catId = $categoryIdConv[$catId];
			}
			// manage payment mode id if this is a custom payment mode
			$pmId = $bill['paymentmodeid'];
			if ($pmId !== null && $pmId > 0) {
				$pmId = $paymentModeIdConv[$pmId];
			}
			$payerId = $memberNameToId[$bill['payer_name']];
			$owerIds = [];
			foreach ($bill['owers'] as $owerName) {
				$strippedOwer = trim($owerName);
				$owerIds[] = $memberNameToId[$strippedOwer];
			}
			$owerIdsStr = implode(',', $owerIds);
			$addBillResult = $this->createBill(
				$projectid, null, $bill['what'], $payerId,
				$owerIdsStr, $bill['amount'], $bill['repeat'],
				$bill['paymentmode'], $pmId,
				$catId, $bill['repeatallactive'],
				$bill['repeatuntil'], $bill['timestamp'], $bill['comment'], $bill['repeatfreq'],
				$dbPaymentModes, $bill['deleted'] ?? 0
			);
			if (!isset($addBillResult['inserted_id'])) {
				$this->deleteProject($projectid);
				return ['message' => $this->l10n->t('Error when adding bill %1$s', [$bill['what']])];
			}
		}
		return ['project_id' => $projectid];
	}

	/**
	 * Import SplitWise project file
	 *
	 * @param string $path
	 * @param string $userId
	 * @return array
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function importSWProject(string $path, string $userId): array {
		$cleanPath = str_replace(['../', '..\\'], '', $path);
		$userFolder = $this->root->getUserFolder($userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if (($handle = $file->fopen('r')) !== false) {
					$columns = [];
					$membersWeight = [];
					$bills = [];
					$owersArray = [];
					$categoryNames = [];
					$row = 0;
					$nbCol = 0;

					$columnNamesLineFound = false;
					while (($data = fgetcsv($handle, 1000, ',')) !== false) {
						// look for column order line
						if (!$columnNamesLineFound) {
							$nbCol = count($data);
							for ($c = 0; $c < $nbCol; $c++) {
								$columns[$data[$c]] = $c;
							}
							if (!array_key_exists('Date', $columns)
								|| !array_key_exists('Description', $columns)
								|| !array_key_exists('Category', $columns)
								|| !array_key_exists('Cost', $columns)
								|| !array_key_exists('Currency', $columns)
							) {
								$columns = [];
								$row++;
								continue;
							}
							$columnNamesLineFound = true;
							// manage members
							$m = 0;
							for ($c = 5; $c < $nbCol; $c++) {
								$owersArray[$m] = $data[$c];
								$m++;
							}
							foreach ($owersArray as $ower) {
								if (strlen($ower) === 0) {
									fclose($handle);
									return ['message' => $this->l10n->t('Malformed CSV, cannot have an empty ower')];
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
							$cost = trim($data[$columns['Cost']]);
							if (empty($cost)) {
								// skip lines with no cost, it might be the balances line
								$row++;
								continue;
							}
							$date = $data[$columns['Date']];
							$datetime = DateTime::createFromFormat('Y-m-d', $date);
							if ($datetime === false) {
								fclose($handle);
								return ['message' => $this->l10n->t('Malformed CSV, missing or invalid date/timestamp on line %1$s', [$row])];
							}
							$timestamp = $datetime->getTimestamp();

							$categoryName = null;
							// manage categories
							if (array_key_exists('Category', $columns)
								&& $data[$columns['Category']] !== null
								&& $data[$columns['Category']] !== '') {
								$categoryName = $data[$columns['Category']];
								if (!in_array($categoryName, $categoryNames)) {
									$categoryNames[] = $categoryName;
								}
							}

							// new algorithm
							// get those with a negative value, they will be the owers in generated bills
							$negativeCols = [];
							for ($c = 5; $c < $nbCol; $c++) {
								if (!is_numeric($data[$c])) {
									fclose($handle);
									return ['message' => $this->l10n->t('Malformed CSV, bad amount on line %1$s', [$row])];
								}
								$amount = (float) $data[$c];
								if ($amount < 0) {
									$negativeCols[] = $c;
								}
							}
							$owersList = array_map(static function ($c) use ($owersArray) {
								return $owersArray[$c - 5];
							}, $negativeCols);
							// each positive one: bill with member-specific amount (not the full amount), owers are the negative ones
							for ($c = 5; $c < $nbCol; $c++) {
								$amount = (float) $data[$c];
								if ($amount > 0) {
									$payer_name = $owersArray[$c - 5];
									if (empty($payer_name)) {
										fclose($handle);
										return ['message' => $this->l10n->t('Malformed CSV, no payer on line %1$s', [$row])];
									}
									$bill = [
										'what' => $what,
										'timestamp' => $timestamp,
										'amount' => $amount,
										'payer_name' => $payer_name,
										'owers' => $owersList
									];
									if ($categoryName !== null) {
										$bill['category_name'] = $categoryName;
									}
									$bills[] = $bill;
								}
							}
						}
						$row++;
					}
					fclose($handle);

					if (!$columnNamesLineFound) {
						return ['message' => $this->l10n->t('Malformed CSV, impossible to find the column names. Make sure your Splitwise account language is set to English first, then export the project again.')];
					}

					$memberNameToId = [];

					// add project
					$user = $this->userManager->get($userId);
					$userEmail = $user->getEMailAddress();
					$projectName = preg_replace('/\.csv$/', '', $file->getName());
					$projectid = Utils::slugify($projectName);
					// create default categories only if none are found in the CSV
					$createDefaultCategories = (count($categoryNames) === 0);
					$projResult = $this->createProject(
						$projectName, $projectid, $userEmail,
						$userId, $createDefaultCategories
					);
					if (!isset($projResult['id'])) {
						return ['message' => $this->l10n->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
					}
					// add categories
					$catNameToId = [];
					foreach ($categoryNames as $categoryName) {
						$insertedCatId = $this->createCategory($projectid, $categoryName, null, '#000000');
						/*
						if (!is_numeric($insertedCatId)) {
							$this->deleteProject($projectid);
							return ['message' => $this->l10n->t('Error when adding category %1$s', [$categoryName])];
						}
						*/
						$catNameToId[$categoryName] = $insertedCatId;
					}
					// add members
					foreach ($membersWeight as $memberName => $weight) {
						$insertedMember = $this->createMember($projectid, $memberName, $weight);
						/*
						if (!is_array($insertedMember)) {
							$this->deleteProject($projectid);
							return ['message' => $this->l10n->t('Error when adding member %1$s', [$memberName])];
						}
						*/
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
						if (array_key_exists('category_name', $bill)
							&& array_key_exists($bill['category_name'], $catNameToId)) {
							$catId = $catNameToId[$bill['category_name']];
						}
						$addBillResult = $this->createBill(
							$projectid, null, $bill['what'], $payerId, $owerIdsStr,
							$bill['amount'], Application::FREQUENCY_NO, null, 0, $catId,
							0, null, $bill['timestamp'], null, null, []
						);
						if (!isset($addBillResult['inserted_id'])) {
							$this->deleteProject($projectid);
							return ['message' => $this->l10n->t('Error when adding bill %1$s', [$bill['what']])];
						}
					}
					return ['project_id' => $projectid];
				} else {
					return ['message' => $this->l10n->t('Access denied')];
				}
			} else {
				return ['message' => $this->l10n->t('Access denied')];
			}
		} else {
			return ['message' => $this->l10n->t('Access denied')];
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
		$dateMaxDay = new DateTime($y . '-' . $m . '-' . $d);
		$maxDayTimestamp = $dateMaxDay->getTimestamp();
		$minDayTimestamp = $maxDayTimestamp - (24 * 60 * 60);

		$dateMaxDay->modify('-1 day');
		$dailySuffix = '_'.$this->l10n->t('daily').'_'.$dateMaxDay->format('Y-m-d');

		// last week
		$now = new DateTime();
		while (((int) $now->format('N')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateWeekMax = new DateTime($y.'-'.$m.'-'.$d);
		$maxWeekTimestamp = $dateWeekMax->getTimestamp();
		$minWeekTimestamp = $maxWeekTimestamp - (7 * 24 * 60 * 60);
		$dateWeekMin = new DateTime($y.'-'.$m.'-'.$d);
		$dateWeekMin->modify('-7 day');
		$weeklySuffix = '_'.$this->l10n->t('weekly').'_'.$dateWeekMin->format('Y-m-d');

		// last month
		$now = new DateTime();
		while (((int) $now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateMonthMax = new DateTime($y.'-'.$m.'-'.$d);
		$maxMonthTimestamp = $dateMonthMax->getTimestamp();
		$now->modify('-1 day');
		while (((int) $now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = (int) $now->format('Y');
		$m = (int) $now->format('m');
		$d = (int) $now->format('d');
		$dateMonthMin = new DateTime($y.'-'.$m.'-'.$d);
		$minMonthTimestamp = $dateMonthMin->getTimestamp();
		$monthlySuffix = '_'.$this->l10n->t('monthly').'_'.$dateMonthMin->format('Y-m');

		// $weekFilterArray = [];
		// $weekFilterArray['tsmin'] = $minWeekTimestamp;
		// $weekFilterArray['tsmax'] = $maxWeekTimestamp;
		// $dayFilterArray = [];
		// $dayFilterArray['tsmin'] = $minDayTimestamp;
		// $dayFilterArray['tsmax'] = $maxDayTimestamp;
		// $monthFilterArray = [];
		// $monthFilterArray['tsmin'] = $minMonthTimestamp;
		// $monthFilterArray['tsmax'] = $maxMonthTimestamp;

		$qb = $this->db->getQueryBuilder();

		foreach ($this->userManager->search('') as $u) {
			$uid = $u->getUID();
			$outPath = $this->config->getUserValue($uid, 'cospend', 'outputDirectory', '/Cospend');

			$qb->select('id', 'name', 'autoexport')
				->from('cospend_projects')
				->where(
					$qb->expr()->eq('userid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->neq('autoexport', $qb->createNamedParameter(Application::FREQUENCY_NO, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();

			$dbProjectId = null;
			while ($row = $req->fetch()) {
				$dbProjectId = $row['id'];
				$autoexport = $row['autoexport'];

				$suffix = $dailySuffix;
				// TODO add suffix for all frequencies
				if ($autoexport === Application::FREQUENCY_WEEKLY) {
					$suffix = $weeklySuffix;
				} elseif ($autoexport === Application::FREQUENCY_MONTHLY) {
					$suffix = $monthlySuffix;
				}
				// check if file already exists
				$exportName = $dbProjectId . $suffix . '.csv';

				$userFolder = $this->root->getUserFolder($uid);
				if (!$userFolder->nodeExists($outPath . '/' . $exportName)) {
					$this->exportCsvProject($dbProjectId, $uid, $exportName);
				}
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();
		}
	}
}
