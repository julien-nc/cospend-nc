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
use OCP\IConfig;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OC\Archive\ZIP;
use OCP\IGroupManager;
use OCP\IAvatarManager;

use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IServerContainer;

use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\BillMapper;

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class ProjectService {

    private $l10n;
    private $logger;
    private $config;
    private $qb;
    private $dbconnection;

    public function __construct (ILogger $logger,
                                IL10N $l10n,
                                IConfig $config,
                                ProjectMapper $projectMapper,
                                BillMapper $billMapper,
                                ActivityManager $activityManager,
                                IAvatarManager $avatarManager,
                                IManager $shareManager,
                                IUserManager $userManager,
                                IGroupManager $groupManager) {
        $this->trans = $l10n;
        $this->config = $config;
        $this->logger = $logger;
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        $this->projectMapper = $projectMapper;
        $this->billMapper = $billMapper;
        $this->activityManager = $activityManager;
        $this->avatarManager = $avatarManager;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;

        $this->categoryNames = [
        '-1'=>$this->trans->t('Grocery'),
        '-2'=>$this->trans->t('Bar/Party'),
        '-3'=>$this->trans->t('Rent'),
        '-4'=>$this->trans->t('Bill'),
        '-5'=>$this->trans->t('Excursion/Culture'),
        '-6'=>$this->trans->t('Health'),
        '-10'=>$this->trans->t('Shopping'),
        '-11'=>$this->trans->t('Reimbursement'),
        '-12'=>$this->trans->t('Restaurant'),
        '-13'=>$this->trans->t('Accommodation'),
        '-14'=>$this->trans->t('Transport'),
        '-15'=>$this->trans->t('Sport')
        ];

    }

    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    /**
     * check if user owns the project
     * or if the project is shared with the user
     */
    public function userCanAccessProject($userid, $projectid) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null) {
            // does the user own the project ?
            if ($projectInfo['userid'] === $userid) {
                return true;
            }
            else {
                $qb = $this->dbconnection->getQueryBuilder();
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
                $req = $qb->execute();
                $dbProjectId = null;
                while ($row = $req->fetch()) {
                    $dbProjectId = $row['projectid'];
                    break;
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();

                if ($dbProjectId !== null) {
                    return true;
                }
                else {
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
                    $req = $qb->execute();
                    while ($row = $req->fetch()){
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
                    }
                    else {
                        // if not, are circles enabled and is the project shared with a circle containing the user?
                        $circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
                        if ($circlesEnabled) {
                            $dbCircleId = null;

                            $qb->select('userid')
                                ->from('cospend_shares', 's')
                                ->where(
                                    $qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
                                )
                                ->andWhere(
                                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                                );
                            $req = $qb->execute();
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
        }
        else {
            return false;
        }
    }

    /**
     * check if user owns the project
     * or if the project is shared with the user with corresponding permission
     */
    public function userHasPermission($userid, $projectid, $permission) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null) {
            // does the user own the project ?
            if ($projectInfo['userid'] === $userid) {
                return true;
            }
            else {
                $qb = $this->dbconnection->getQueryBuilder();
                // is the project shared with the user ?
                $qb->select('userid', 'projectid', 'permissions')
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
                $req = $qb->execute();
                $dbProjectId = null;
                $dbPermissions = null;
                while ($row = $req->fetch()) {
                    $dbProjectId = $row['projectid'];
                    $dbPermissions = $row['permissions'];
                    break;
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();

                if ($dbProjectId !== null and strrpos($dbPermissions, $permission) !== false) {
                    return true;
                }
                else {
                    // if not, is the project shared with a group containing the user?
                    $userO = $this->userManager->get($userid);
                    $accessWithGroup = null;

                    $qb->select('userid', 'permissions')
                        ->from('cospend_shares', 's')
                        ->where(
                            $qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
                        )
                        ->andWhere(
                            $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                        );
                    $req = $qb->execute();
                    while ($row = $req->fetch()){
                        $groupId = $row['userid'];
                        $dbPermissions = $row['permissions'];
                        if ($this->groupManager->groupExists($groupId)
                            and $this->groupManager->get($groupId)->inGroup($userO)
                            and strrpos($dbPermissions, $permission) !== false
                        ) {
                            $accessWithGroup = $groupId;
                            break;
                        }
                    }
                    $req->closeCursor();
                    $qb = $qb->resetQueryParts();

                    if ($accessWithGroup !== null) {
                        return true;
                    }
                    else {
                        // if not, are circles enabled and is the project shared with a circle containing the user
                        // with asked permission?
                        $circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
                        if ($circlesEnabled) {
                            $dbCircleId = null;

                            $qb->select('userid', 'permissions')
                                ->from('cospend_shares', 's')
                                ->where(
                                    $qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
                                )
                                ->andWhere(
                                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                                );
                            $req = $qb->execute();
                            while ($row = $req->fetch()) {
                                $circleId = $row['userid'];
                                $dbPermissions = $row['permissions'];
                                // got permission
                                if (strrpos($dbPermissions, $permission) !== false) {
                                    if ($this->isUserInCircle($userid, $circleId)) {
                                        return true;
                                    }
                                }
                            }
                        }
                        return false;
                    }
                }
            }
        }
        else {
            return false;
        }
    }

    public function guestHasPermission($projectid, $permission) {
        $projectInfo = $this->getProjectInfo($projectid);
        if ($projectInfo !== null) {
            return (strrpos($projectInfo['guestpermissions'], $permission) !== false);
        }
        else {
            return false;
        }
    }

    /**
     * check if user owns the external project
     */
    public function userCanAccessExternalProject($userid, $projectid, $ncurl) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid')
           ->from('cospend_ext_projects', 'ep')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('userid', $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        $dbProjectId = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['projectid'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return ($dbProjectId !== null);
    }

    public function createProject($name, $id, $password, $contact_email, $userid='') {
        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('id')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

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
                return ['message'=>$this->trans->t('Invalid project id')];
            }
            $dbPassword = '';
            if ($password !== null && $password !== '') {
                $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            }
            if ($contact_email === null) {
                $contact_email = '';
            }
            $ts = (new \DateTime())->getTimestamp();
            $qb->insert('cospend_projects')
                ->values([
                    'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
                    'id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
                    'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                    'password' => $qb->createNamedParameter($dbPassword, IQueryBuilder::PARAM_STR),
                    'email' => $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR),
                    'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
                ]);
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return $id;
        }
        else {
            return ['message'=>$this->trans->t('A project with id "%1$s" already exists', [$id])];
        }
    }

    public function addExternalProject($ncurl, $id, $password, $userid) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid')
           ->from('cospend_ext_projects', 'ep')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        $dbprojectid = null;
        while ($row = $req->fetch()){
            $dbprojectid = $row['projectid'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($dbprojectid === null) {
            // check if id is valid
            if (strpos($id, '/') !== false) {
                return ['message'=>$this->trans->t('Invalid project id')];
            }
            $qb->insert('cospend_ext_projects')
                ->values([
                    'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
                    'projectid' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR),
                    'ncurl' => $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR),
                    'password' => $qb->createNamedParameter($password, IQueryBuilder::PARAM_STR)
                ]);
            $req = $qb->execute();

            return $id;
        }
        else {
            return ['message'=>$this->trans->t('A project with id "%1$s" and url "%2$s" already exists', [$id, $ncurl])];
        }
    }

    public function deleteProject($projectid) {
        $projectToDelete = $this->getProjectById($projectid);
        if ($projectToDelete !== null) {
            $qb = $this->dbconnection->getQueryBuilder();

            // delete project bills
            $bills = $this->getBills($projectid);
            foreach ($bills as $bill) {
                $this->deleteBillOwersOfBill($bill['id']);
            }

            $qb->delete('cospend_bills')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete project members
            $qb->delete('cospend_members')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete shares
            $qb->delete('cospend_shares')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete currencies
            $qb->delete('cospend_currencies')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete categories
            $qb->delete('cospend_project_categories')
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // delete project
            $qb->delete('cospend_projects')
                ->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return 'DELETED';
        }
        else {
            return [$this->trans->t('Not Found')];
        }
    }

    public function getProjectInfo($projectid) {
        $projectInfo = null;

        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('id', 'password', 'name', 'email', 'userid', 'lastchanged', 'guestpermissions', 'autoexport', 'currencyname')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            $dbPassword = $row['password'];
            $dbName = $row['name'];
            $dbEmail= $row['email'];
            $dbUserId = $row['userid'];
            $dbGuestPermissions = $row['guestpermissions'];
            $dbLastchanged = intval($row['lastchanged']);
            $dbAutoexport= $row['autoexport'];
            $dbCurrencyName= $row['currencyname'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($dbProjectId !== null) {
            $members = $this->getMembers($dbProjectId);
            $activeMembers = [];
            foreach ($members as $member) {
                if ($member['activated']) {
                    array_push($activeMembers, $member);
                }
            }
            $balance = $this->getBalance($dbProjectId);
            $currencies = $this->getCurrencies($dbProjectId);
            $categories = $this->getCategories($dbProjectId);
            $projectInfo = [
                'userid'=>$dbUserId,
                'name'=>$dbName,
                'contact_email'=>$dbEmail,
                'id'=>$dbProjectId,
                'guestpermissions'=>$dbGuestPermissions,
                'autoexport'=>$dbAutoexport,
                'currencyname'=>$dbCurrencyName,
                'currencies'=>$currencies,
                'categories'=>$categories,
                'active_members'=>$activeMembers,
                'members'=>$members,
                'balance'=>$balance,
                'lastchanged'=>$dbLastchanged
            ];
        }

        return $projectInfo;
    }

    public function getProjectStatistics($projectId, $memberOrder=null, $dateMin=null, $dateMax=null,
                                          $paymentMode=null, $category=null, $amountMin=null, $amountMax=null,
                                          $showDisabled='1', $currencyId=null) {
        $membersWeight = [];
        $membersNbBills = [];
        $membersBalance = [];
        $membersFilteredBalance = [];
        $membersPaid = [];
        $membersSpent = [];

        $showDisabled = ($showDisabled === '1');

        $currency = null;
        if ($currencyId !== null and intval($currencyId) !== 0) {
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
        }

        // build list of members to display
        $membersToDisplay = [];
        foreach ($members as $member) {
            $memberId = $member['id'];
            // only take enabled members or those with non-zero balance
            $mBalance = floatval($membersBalance[$memberId]);
            if ($showDisabled or $member['activated'] or $mBalance >= 0.01 or $mBalance <= -0.01) {
                $membersToDisplay[$memberId] = $member;
            }
        }

        // compute stats
        $bills = $this->getBills($projectId, $dateMin, $dateMax, $paymentMode, $category, $amountMin, $amountMax);
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
            }
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
                array_push($statistics, $statistic);
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
                array_push($statistics, $statistic);
            }
        }

        // compute monthly stats
        $monthlyStats = [];
        $allMembersKey = 0;
        foreach ($bills as $bill) {
            $payerId = $bill['payer_id'];
            $amount = $bill['amount'];
            $date = $bill['date'];
            $month = substr($date, 0, 7);
            if (!array_key_exists($month, $monthlyStats)) {
                $monthlyStats[$month] = [];
                foreach ($membersToDisplay as $memberId => $member) {
                    $monthlyStats[$month][$memberId] = 0;
                }
                $monthlyStats[$month][$allMembersKey] = 0;
            }

            if (array_key_exists($payerId, $membersToDisplay)) {
                $monthlyStats[$month][$payerId] += $amount;
                $monthlyStats[$month][$allMembersKey] += $amount;
            }
        }
        // monthly average
        $nbMonth = count(array_keys($monthlyStats));
        if ($nbMonth > 0) {
            $averageStats = [];
            foreach ($membersToDisplay as $memberId => $member) {
                $sum = 0;
                foreach ($monthlyStats as $month=>$mStat) {
                    $sum += $monthlyStats[$month][$memberId];
                }
                $averageStats[$memberId] = $sum / $nbMonth;
            }
            // average for all members
            $sum = 0;
            foreach ($monthlyStats as $month=>$mStat) {
                $sum += $monthlyStats[$month][$allMembersKey];
            }
            $averageStats[$allMembersKey] = $sum / $nbMonth;

            $averageKey = $this->trans->t('Average per month');
            $monthlyStats[$averageKey] = $averageStats;
        }
        // convert if necessary
        if ($currency !== null) {
            foreach ($monthlyStats as $month=>$mStat) {
                foreach ($mStat as $mid=>$val) {
                    $monthlyStats[$month][$mid] = ($monthlyStats[$month][$mid] === 0.0) ? 0 : $monthlyStats[$month][$mid] / $currency['exchange_rate'];
                }
            }
        }
        // compute category stats
        $categoryStats = [];
        foreach ($bills as $bill) {
            $categoryId = $bill['categoryid'];
            if (!array_key_exists(strval($categoryId), $this->categoryNames) and
                !array_key_exists(strval($categoryId), $projectCategories)
            ) {
                $categoryId = 0;
            }
            $amount = $bill['amount'];
            if (!array_key_exists($categoryId, $categoryStats)) {
                $categoryStats[$categoryId] = 0;
            }
            $categoryStats[$categoryId] += $amount;
        }
        // convert if necessary
        if ($currency !== null) {
            foreach ($categoryStats as $catId=>$val) {
                $categoryStats[$catId] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
            }
        }
        // compute category per member stats
        $categoryMemberStats = [];
        foreach ($bills as $bill) {
            $payerId = $bill['payer_id'];
            $categoryId = $bill['categoryid'];
            if (!array_key_exists(strval($categoryId), $this->categoryNames) and
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
            foreach ($categoryMemberStats as $catId=>$mStat) {
                foreach ($mStat as $mid=>$val) {
                    $categoryMemberStats[$catId][$mid] = ($val === 0.0) ? 0 : $val / $currency['exchange_rate'];
                }
            }
        }

        return [
            'stats'=>$statistics,
            'monthlyStats'=>$monthlyStats,
            'categoryStats'=>$categoryStats,
            'categoryMemberStats'=>$categoryMemberStats,
            'memberIds'=>array_keys($membersToDisplay)
        ];
    }

    public function addBill($projectid, $date, $what, $payer, $payed_for,
                            $amount, $repeat, $paymentmode=null, $categoryid=null,
                            $repeatallactive=0, $repeatuntil=null) {
        if ($repeat === null || $repeat === '' || strlen($repeat) !== 1) {
            return ['repeat'=>$this->trans->t('Invalid value')];
        }
        if ($repeatallactive === null || ($repeatallactive !== '' && !is_numeric($repeatallactive))) {
            return ['repeatallactive'=>$this->trans->t('Invalid value')];
        }
        if ($repeatallactive !== null && $repeatallactive === '') {
            $repeatallactive = 0;
        }
        if ($repeatuntil !== null && $repeatuntil === '') {
            $repeatuntil = null;
        }
        if ($date === null || $date === '') {
            return ['date'=>$this->trans->t('This field is required')];
        }
        if ($what === null || $what === '') {
            return ['what'=>$this->trans->t('This field is required')];
        }
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            return ['amount'=>$this->trans->t('This field is required')];
        }
        if ($payer === null || $payer === '' || !is_numeric($payer)) {
            return ['payer'=>$this->trans->t('This field is required')];
        }
        if ($this->getMemberById($projectid, $payer) === null) {
            return ['payer'=>$this->trans->t('Not a valid choice')];
        }
        // check owers
        $owerIds = explode(',', $payed_for);
        if ($payed_for === null || $payed_for === '' || count($owerIds) === 0) {
            return ['payed_for'=>$this->trans->t('Invalid value')];
        }
        foreach ($owerIds as $owerId) {
            if (!is_numeric($owerId)) {
                return ['payed_for'=>$this->trans->t('Invalid value')];
            }
            if ($this->getMemberById($projectid, $owerId) === null) {
                return ['payed_for'=>$this->trans->t('Not a valid choice')];
            }
        }

        // last modification timestamp is now
        $ts = (new \DateTime())->getTimestamp();

        // do it already !
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->insert('cospend_bills')
            ->values([
                'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                'what' => $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR),
                'date' => $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR),
                'amount' => $qb->createNamedParameter($amount, IQueryBuilder::PARAM_STR),
                'payerid' => $qb->createNamedParameter($payer, IQueryBuilder::PARAM_INT),
                'repeat' => $qb->createNamedParameter($repeat, IQueryBuilder::PARAM_STR),
                'repeatallactive' => $qb->createNamedParameter($repeatallactive, IQueryBuilder::PARAM_INT),
                'repeatuntil' => $qb->createNamedParameter($repeatuntil, IQueryBuilder::PARAM_STR),
                'categoryid' => $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT),
                'paymentmode' => $qb->createNamedParameter($paymentmode, IQueryBuilder::PARAM_STR),
                'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
            ]);
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $insertedBillId = $qb->getLastInsertId();

        // insert bill owers
        foreach ($owerIds as $owerId) {
            $qb->insert('cospend_bill_owers')
                ->values([
                    'billid' => $qb->createNamedParameter($insertedBillId, IQueryBuilder::PARAM_INT),
                    'memberid' => $qb->createNamedParameter($owerId, IQueryBuilder::PARAM_INT)
                ]);
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();
        }

        return $insertedBillId;
    }

    public function deleteBill($projectid, $billid) {
        $billToDelete = $this->getBill($projectid, $billid);
        if ($billToDelete !== null) {
            $this->deleteBillOwersOfBill($billid);

            $qb = $this->dbconnection->getQueryBuilder();
            $qb->delete('cospend_bills')
               ->where(
                   $qb->expr()->eq('id', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
               )
               ->andWhere(
                   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
               );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return 'OK';
        }
        else {
            return ['message'=>$this->trans->t('Not Found')];
        }
    }

    private function getMemberById($projectId, $memberId) {
        $member = null;

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'weight', 'color', 'activated')
           ->from('cospend_members', 'm')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('id', $qb->createNamedParameter($memberId, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            $dbMemberId = intval($row['id']);
            $dbWeight = floatval($row['weight']);
            $dbName = $row['name'];
            $dbActivated = intval($row['activated']);
            $dbColor = $row['color'];
            if ($dbColor === null) {
                $av = $this->avatarManager->getGuestAvatar($dbName);
                $dbColor = $av->avatarBackgroundColor($dbName);
            }
            else {
                $dbColor = $this->hexToRgb($dbColor);
            }

            $member = [
                    'activated' => ($dbActivated === 1),
                    'name' => $dbName,
                    'id' => $dbMemberId,
                    'weight' => $dbWeight,
                    'color' => $dbColor
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $member;
    }

    public function getProjectById($projectId) {
        $project = null;

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'userid', 'name', 'email', 'password', 'currencyname', 'autoexport', 'guestpermissions', 'lastchanged')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbId = $row['id'];
            $dbPassword = $row['password'];
            $dbName = $row['name'];
            $dbUserId = $row['userid'];
            $dbEmail = $row['email'];
            $dbCurrencyName = $row['currencyname'];
            $dbAutoexport = $row['autoexport'];
            $dbLastchanged = intval($row['lastchanged']);
            $dbGuestPermissions = $row['guestpermissions'];
            $project = [
                    'id' => $dbId,
                    'name' => $dbName,
                    'userid' => $dbUserId,
                    'password' => $dbPassword,
                    'email' => $dbEmail,
                    'lastchanged' => $dbLastchanged,
                    'currencyname' => $dbCurrencyName,
                    'autoexport' => $dbAutoexport,
                    'guestpermissions' => $dbGuestPermissions
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $project;
    }

    public function getBill($projectId, $billId) {
        $bill = null;
        // get bill owers
        $billOwers = [];

        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('memberid', 'm.name', 'm.weight', 'm.activated')
           ->from('cospend_bill_owers', 'bo')
           ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
           ->where(
               $qb->expr()->eq('bo.billid', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbWeight = floatval($row['weight']);
            $dbName = $row['name'];
            $dbActivated = (intval($row['activated']) === 1);
            $dbOwerId= intval($row['memberid']);
            array_push(
                $billOwers,
                [
                    'id' => $dbOwerId,
                    'weight' => $dbWeight,
                    'name' => $dbName,
                    'activated' => $dbActivated
                ]
            );
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // get the bill
        $qb->select('id', 'what', 'date', 'amount', 'payerid', 'repeat',
                    'repeatallactive', 'paymentmode', 'categoryid', 'repeatuntil')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('id', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbBillId = intval($row['id']);
            $dbAmount = floatval($row['amount']);
            $dbWhat = $row['what'];
            $dbDate = $row['date'];
            $dbRepeat = $row['repeat'];
            $dbRepeatAllActive = $row['repeatallactive'];
            $dbRepeatUntil = $row['repeatuntil'];
            $dbPayerId = intval($row['payerid']);
            $dbPaymentMode = $row['paymentmode'];
            $dbCategoryId = intval($row['categoryid']);
            $bill = [
                'id' => $dbBillId,
                'amount' => $dbAmount,
                'what' => $dbWhat,
                'date' => $dbDate,
                'payer_id' => $dbPayerId,
                'owers' => $billOwers,
                'repeat' => $dbRepeat,
                'repeatallactive' => $dbRepeatAllActive,
                'repeatuntil' => $dbRepeatUntil,
                'paymentmode' => $dbPaymentMode,
                'categoryid' => $dbCategoryId
            ];
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $bill;
    }

    private function deleteBillOwersOfBill($billid) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->delete('cospend_bill_owers')
           ->where(
               $qb->expr()->eq('billid', $qb->createNamedParameter($billid, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function autoSettlement($projectid) {
        $transactions = $this->getProjectSettlement($projectid);
        if (!is_array($transactions)) {
            return ['message'=>$this->trans->t('Error when getting project settlement transactions')];
        }

        $members = $this->getMembers($projectid);
        $memberIdToName = [];
        foreach ($members as $member) {
            $memberIdToName[$member['id']] = $member['name'];
        }

        $now = new \DateTime();
        $date = $now->format('Y-m-d');

        foreach ($transactions as $transaction) {
            $fromId = $transaction['from'];
            $toId = $transaction['to'];
            $amount = floatval($transaction['amount']);
            $billTitle = $memberIdToName[$fromId].' → '.$memberIdToName[$toId];
            $addBillResult = $this->addBill($projectid, $date, $billTitle, $fromId, $toId, $amount, 'n', null, -11);
            if (!is_numeric($addBillResult)) {
                return ['message'=>$this->trans->t('Error when addind a bill')];
            }
        }
        return 'OK';
    }

    public function getProjectSettlement($projectId) {
        $balances = $this->getBalance($projectId);
        $transactions = $this->settle($balances);
        return $transactions;
    }

    private function settle($balances) {
        $debitersCrediters = $this->orderBalance($balances);
        $debiters = $debitersCrediters[0];
        $crediters = $debitersCrediters[1];
        return $this->reduceBalance($crediters, $debiters);
    }

    private function orderBalance($balances) {
        $crediters = [];
        $debiters = [];
        foreach ($balances as $id => $balance) {
            if ($balance > 0.0) {
                array_push($crediters, [$id, $balance]);
            }
            else if ($balance < 0.0) {
                array_push($debiters, [$id, $balance]);
            }
        }

        return [$debiters, $crediters];
    }

    private function reduceBalance($crediters, $debiters, $results=null) {
        if (count($crediters) === 0 or count($debiters) === 0) {
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
        }
        else {
            $amount = abs($debiterBalance);
        }

        $newResults = $results;
        array_push($newResults, ['to'=>$crediter, 'amount'=>$amount, 'from'=>$debiter]);

        $newDebiterBalance = $debiterBalance + $amount;
        if ($newDebiterBalance < 0.0) {
            array_push($debiters, [$debiter, $newDebiterBalance]);
            $debiters = $this->sortCreditersDebiters($debiters, true);
        }

        $newCrediterBalance = $crediterBalance - $amount;
        if ($newCrediterBalance > 0.0) {
            array_push($crediters, [$crediter, $newCrediterBalance]);
            $crediters = $this->sortCreditersDebiters($crediters);
        }

        return $this->reduceBalance($crediters, $debiters, $newResults);
    }

    private function sortCreditersDebiters($arr, $reverse=false) {
        $res = [];
        if ($reverse) {
            foreach ($arr as $elem) {
                $i = 0;
                while ($i < count($res) and $elem[1] < $res[$i][1]) {
                    $i++;
                }
                array_splice($res, $i, 0, [$elem]);
            }
        }
        else {
            foreach ($arr as $elem) {
                $i = 0;
                while ($i < count($res) and $elem[1] >= $res[$i][1]) {
                    $i++;
                }
                array_splice($res, $i, 0, [$elem]);
            }
        }
        return $res;
    }

    public function editMember($projectid, $memberid, $name, $weight, $activated, $color=null) {
        if ($name !== null && $name !== '') {
            if ($this->getMemberById($projectid, $memberid) !== null) {
                $qb = $this->dbconnection->getQueryBuilder();
                $qb->update('cospend_members');
                if ($weight !== null && $weight !== '') {
                    if (is_numeric($weight) and floatval($weight) > 0.0) {
                        $newWeight = floatval($weight);
                        $qb->set('weight', $qb->createNamedParameter($newWeight, IQueryBuilder::PARAM_STR));
                    }
                    else {
                        return ['weight'=>$this->trans->t('Not a valid decimal value')];
                    }
                }
                if ($activated !== null && $activated !== '' && ($activated === 'true' || $activated === 'false')) {
                    $qb->set('activated', $qb->createNamedParameter(($activated === 'true' ? 1 : 0), IQueryBuilder::PARAM_INT));
                }

                $ts = (new \DateTime())->getTimestamp();
                $qb->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT));

                $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
                if ($color !== null) {
                    if ($color === '') {
                        $qb->set('color', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
                    }
                    else {
                        $qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
                    }
                }
                $qb->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $editedMember = $this->getMemberById($projectid, $memberid);

                return $editedMember;
            }
            else {
                return ['name'=>$this->trans->t('This project have no such member')];
            }
        }
        else {
            return ['name'=>$this->trans->t('This field is required')];
        }
    }

    public function editProject($projectid, $name, $contact_email, $password, $autoexport=null, $currencyname=null) {
        if ($name === null || $name === '') {
            return ['name'=> [$this->trans->t('Name field is required')]];
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('cospend_projects');

        $emailSql = '';
        if ($contact_email !== null && $contact_email !== '') {
            if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                $qb->set('email', $qb->createNamedParameter($contact_email, IQueryBuilder::PARAM_STR));
            }
            else {
                return ['contact_email'=> [$this->trans->t('Invalid email address')]];
            }
        }
        if ($password !== null && $password !== '') {
            $dbPassword = password_hash($password, PASSWORD_DEFAULT);
            $qb->set('password', $qb->createNamedParameter($dbPassword, IQueryBuilder::PARAM_STR));
        }
        if ($autoexport !== null && $autoexport !== '') {
            $qb->set('autoexport', $qb->createNamedParameter($autoexport, IQueryBuilder::PARAM_STR));
        }
        if ($currencyname !== null) {
            if ($currencyname === '') {
                $qb->set('currencyname', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
            }
            else {
                $qb->set('currencyname', $qb->createNamedParameter($currencyname, IQueryBuilder::PARAM_STR));
            }
        }
        if ($this->getProjectById($projectid) !== null) {
            $ts = (new \DateTime())->getTimestamp();
            $qb->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT));
            $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
            $qb->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return 'UPDATED';
        }
        else {
            return ['message'=>$this->trans->t('There is no such project')];
        }
    }

    public function editExternalProject($projectid, $ncurl, $password) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('cospend_ext_projects');
        $qb->set('password', $qb->createNamedParameter($password, IQueryBuilder::PARAM_STR));
        $qb->where(
            $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
        )
        ->andWhere(
            $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
        );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        return 'UPDATED';
    }

    public function deleteExternalProject($projectid, $ncurl) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->delete('cospend_ext_projects')
            ->where(
                $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $qb->expr()->eq('ncurl', $qb->createNamedParameter($ncurl, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        return 'DELETED';
    }

    public function addMember($projectid, $name, $weight, $active=1, $color=null) {
        if ($name !== null && $name !== '') {
            if ($this->getMemberByName($projectid, $name) === null) {
                $weightToInsert = 1;
                if ($weight !== null && $weight !== '') {
                    if (is_numeric($weight) and floatval($weight) > 0.0) {
                        $weightToInsert = floatval($weight);
                    }
                    else {
                        return $this->trans->t('Weight is not a valid decimal value');
                    }
                }
                if ($active === null || !is_numeric($active)) {
                    return $this->trans->t('Active is not a valid integer value');
                }

                $ts = (new \DateTime())->getTimestamp();

                $qb = $this->dbconnection->getQueryBuilder();
                $qb->insert('cospend_members')
                    ->values([
                        'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                        'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                        'weight' => $qb->createNamedParameter($weightToInsert, IQueryBuilder::PARAM_STR),
                        'activated' => $qb->createNamedParameter($active, IQueryBuilder::PARAM_INT),
                        'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
                        'lastchanged' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)
                    ]);
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $insertedMember = $this->getMemberByName($projectid, $name);

                return $insertedMember;
            }
            else {
                return $this->trans->t('This project already has this member');
            }
        }
        else {
            return $this->trans->t('Name field is required');
        }
    }

    public function getBills($projectId, $dateMin=null, $dateMax=null, $paymentMode=null, $category=null,
                              $amountMin=null, $amountMax=null, $lastchanged=null) {
        $bills = [];

        // first get all bill ids
        $billIds = [];
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        // take bills that have changed after $lastchanged
        if ($lastchanged !== null and is_numeric($lastchanged)) {
           $qb->andWhere(
               $qb->expr()->gt('lastchanged', $qb->createNamedParameter(intval($lastchanged), IQueryBuilder::PARAM_INT))
           );
        }
        if ($dateMin !== null and $dateMin !== '') {
           $qb->andWhere(
               $qb->expr()->gte('date', $qb->createNamedParameter($dateMin, IQueryBuilder::PARAM_STR))
           );
        }
        if ($dateMax !== null and $dateMax !== '') {
           $qb->andWhere(
               $qb->expr()->lte('date', $qb->createNamedParameter($dateMax, IQueryBuilder::PARAM_STR))
           );
        }
        if ($paymentMode !== null and $paymentMode !== '' and $paymentMode !== 'n') {
           $qb->andWhere(
               $qb->expr()->eq('paymentmode', $qb->createNamedParameter($paymentMode, IQueryBuilder::PARAM_STR))
           );
        }
        if ($category !== null and $category !== '' and intval($category) !== 0) {
            if (intval($category) === -100) {
                $or = $qb->expr()->orx();
                $or->add($qb->expr()->isNull('categoryid'));
                $or->add($qb->expr()->neq('categoryid', $qb->createNamedParameter(-8, IQueryBuilder::PARAM_INT)));
                $qb->andWhere($or);
            }
            else {
                $qb->andWhere(
                    $qb->expr()->eq('categoryid', $qb->createNamedParameter(intval($category), IQueryBuilder::PARAM_INT))
                );
            }
        }
        if ($amountMin !== null and is_numeric($amountMin)) {
           $qb->andWhere(
               $qb->expr()->gte('amount', $qb->createNamedParameter(floatval($amountMin), IQueryBuilder::PARAM_STR))
           );
        }
        if ($amountMax !== null and is_numeric($amountMax)) {
           $qb->andWhere(
               $qb->expr()->lte('amount', $qb->createNamedParameter(floatval($amountMax), IQueryBuilder::PARAM_STR))
           );
        }
        $req = $qb->execute();

        while ($row = $req->fetch()){
            array_push($billIds, $row['id']);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // get bill owers
        $billOwersByBill = [];
        foreach ($billIds as $billId) {
            $billOwers = [];

            $qb->select('memberid', 'm.name', 'm.weight', 'm.activated')
               ->from('cospend_bill_owers', 'bo')
               ->innerJoin('bo', 'cospend_members', 'm', $qb->expr()->eq('bo.memberid', 'm.id'))
               ->where(
                   $qb->expr()->eq('bo.billid', $qb->createNamedParameter($billId, IQueryBuilder::PARAM_INT))
               );
            $req = $qb->execute();
            while ($row = $req->fetch()){
                $dbWeight = floatval($row['weight']);
                $dbName = $row['name'];
                $dbActivated = (intval($row['activated']) === 1);
                $dbOwerId= intval($row['memberid']);
                array_push(
                    $billOwers,
                    [
                        'id' => $dbOwerId,
                        'weight' => $dbWeight,
                        'name' => $dbName,
                        'activated' => $dbActivated
                    ]
                );
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();
            $billOwersByBill[$billId] = $billOwers;
        }

        $qb->select('id', 'what', 'date', 'amount', 'payerid', 'repeat',
                    'paymentmode', 'categoryid', 'lastchanged', 'repeatallactive', 'repeatuntil')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        // take bills that have changed after $lastchanged
        if ($lastchanged !== null and is_numeric($lastchanged)) {
           $qb->andWhere(
               $qb->expr()->gt('lastchanged', $qb->createNamedParameter(intval($lastchanged), IQueryBuilder::PARAM_INT))
           );
        }
        if ($dateMin !== null and $dateMin !== '') {
           $qb->andWhere(
               $qb->expr()->gte('date', $qb->createNamedParameter($dateMin, IQueryBuilder::PARAM_STR))
           );
        }
        if ($dateMax !== null and $dateMax !== '') {
           $qb->andWhere(
               $qb->expr()->lte('date', $qb->createNamedParameter($dateMax, IQueryBuilder::PARAM_STR))
           );
        }
        if ($paymentMode !== null and $paymentMode !== '' and $paymentMode !== 'n') {
           $qb->andWhere(
               $qb->expr()->eq('paymentmode', $qb->createNamedParameter($paymentMode, IQueryBuilder::PARAM_STR))
           );
        }
        if ($category !== null and $category !== '' and intval($category) !== 0) {
            if (intval($category) === -100) {
                $or = $qb->expr()->orx();
                $or->add($qb->expr()->isNull('categoryid'));
                $or->add($qb->expr()->neq('categoryid', $qb->createNamedParameter(-8, IQueryBuilder::PARAM_INT)));
                $qb->andWhere($or);
            }
            else {
                $qb->andWhere(
                    $qb->expr()->eq('categoryid', $qb->createNamedParameter(intval($category), IQueryBuilder::PARAM_INT))
                );
            }
        }
        if ($amountMin !== null and is_numeric($amountMin)) {
           $qb->andWhere(
               $qb->expr()->gte('amount', $qb->createNamedParameter(floatval($amountMin), IQueryBuilder::PARAM_STR))
           );
        }
        if ($amountMax !== null and is_numeric($amountMax)) {
           $qb->andWhere(
               $qb->expr()->lte('amount', $qb->createNamedParameter(floatval($amountMax), IQueryBuilder::PARAM_STR))
           );
        }
        $qb->orderBy('date', 'ASC');
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbBillId = intval($row['id']);
            $dbAmount = floatval($row['amount']);
            $dbWhat = $row['what'];
            $dbDate = $row['date'];
            $dbRepeat = $row['repeat'];
            $dbPayerId = intval($row['payerid']);
            $dbPaymentMode = $row['paymentmode'];
            $dbCategoryId = intval($row['categoryid']);
            $dbLastchanged = intval($row['lastchanged']);
            $dbRepeatAllActive = intval($row['repeatallactive']);
            $dbRepeatUntil = $row['repeatuntil'];
            array_push(
                $bills,
                [
                    'id' => $dbBillId,
                    'amount' => $dbAmount,
                    'what' => $dbWhat,
                    'date' => $dbDate,
                    'payer_id' => $dbPayerId,
                    'owers' => $billOwersByBill[$row['id']],
                    'repeat' => $dbRepeat,
                    'paymentmode' => $dbPaymentMode,
                    'categoryid' => $dbCategoryId,
                    'lastchanged' => $dbLastchanged,
                    'repeatallactive' => $dbRepeatAllActive,
                    'repeatuntil' => $dbRepeatUntil
                ]
            );
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $bills;
    }

    public function getAllBillIds($projectId) {
        $billIds = [];
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id')
           ->from('cospend_bills', 'b')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            array_push($billIds, $row['id']);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $billIds;
    }

    public function getMembers($projectId, $order=null, $lastchanged=null) {
        $members = [];

        $sqlOrder = 'name';
        if ($order !== null) {
            if ($order === 'lowername') {
                $sqlOrder = 'name';
            }
            else {
                $sqlOrder = $order;
            }
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'weight', 'color', 'activated', 'lastchanged')
           ->from('cospend_members', 'm')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           );
        if ($lastchanged !== null and is_numeric($lastchanged)) {
           $qb->andWhere(
               $qb->expr()->gt('lastchanged', $qb->createNamedParameter($lastchanged, IQueryBuilder::PARAM_INT))
           );
        }
        $qb->orderBy($sqlOrder, 'ASC');
        $req = $qb->execute();

        if ($order === 'lowername') {
            while ($row = $req->fetch()){
                $dbMemberId = intval($row['id']);
                $dbWeight = floatval($row['weight']);
                $dbName = $row['name'];
                $dbActivated = intval($row['activated']);
                $dbLastchanged = intval($row['lastchanged']);
                $dbColor = $row['color'];
                if ($dbColor === null) {
                    $av = $this->avatarManager->getGuestAvatar($dbName);
                    $dbColor = $av->avatarBackgroundColor($dbName);
                }
                else {
                    $dbColor = $this->hexToRgb($dbColor);
                }

                // find index to make sorted insert
                $ii = 0;
                while ($ii < count($members) && strcmp(strtolower($dbName), strtolower($members[$ii]['name'])) > 0) {
                    $ii++;
                }

                array_splice(
                    $members,
                    $ii,
                    0,
                    [[
                        'activated' => ($dbActivated === 1),
                        'name' => $dbName,
                        'id' => $dbMemberId,
                        'weight' => $dbWeight,
                        'color'=>$dbColor,
                        'lastchanged'=>$dbLastchanged
                    ]]
                );
            }
        }
        else {
            while ($row = $req->fetch()){
                $dbMemberId = intval($row['id']);
                $dbWeight = floatval($row['weight']);
                $dbName = $row['name'];
                $dbActivated = intval($row['activated']);
                $dbLastchanged = intval($row['lastchanged']);
                $dbColor = $row['color'];
                if ($dbColor === null) {
                    $av = $this->avatarManager->getGuestAvatar($dbName);
                    $dbColor = $av->avatarBackgroundColor($dbName);
                }
                else {
                    $dbColor = $this->hexToRgb($dbColor);
                }

                array_push(
                    $members,
                    [
                        'activated' => ($dbActivated === 1),
                        'name' => $dbName,
                        'id' => $dbMemberId,
                        'weight' => $dbWeight,
                        'color'=>$dbColor,
                        'lastchanged'=>$dbLastchanged
                    ]
                );
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $members;
    }

    private function getBalance($projectId) {
        $membersWeight = [];
        $membersBalance = [];

        $members = $this->getMembers($projectId);
        foreach ($members as $member) {
            $memberId = $member['id'];
            $memberWeight = $member['weight'];
            $membersWeight[$memberId] = $memberWeight;
            $membersBalance[$memberId] = 0.0;
        }

        $bills = $this->getBills($projectId);
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

    private function isUserInCircle($userId, $circleId) {
        $circleDetails = null;
        try {
            $circleDetails = \OCA\Circles\Api\v1\Circles::detailsCircle($circleId);
        }
        catch (\OCA\Circles\Exceptions\CircleDoesNotExistException $e) {
        }
        if ($circleDetails) {
            // is the circle owner
            if ($circleDetails->getOwner()->getUserId() === $userId) {
                return true;
            }
            else {
                foreach ($circleDetails->getMembers() as $m) {
                    // is member of this circle
                    if ($m->getUserId() === $userId) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function getProjects($userId) {
        $projects = [];
        $projectids = [];

        $qb = $this->dbconnection->getQueryBuilder();

        $qb->select('p.id', 'p.password', 'p.name', 'p.email', 'p.autoexport', 'p.guestpermissions', 'p.currencyname', 'p.lastchanged')
           ->from('cospend_projects', 'p')
           ->where(
               $qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            array_push($projectids, $dbProjectId);
            $dbPassword = $row['password'];
            $dbName  = $row['name'];
            $dbEmail = $row['email'];
            $autoexport = $row['autoexport'];
            $guestpermissions = $row['guestpermissions'];
            $dbCurrencyName = $row['currencyname'];
            $dbLastchanged = intval($row['lastchanged']);
            array_push($projects, [
                'name'=>$dbName,
                'contact_email'=>$dbEmail,
                'id'=>$dbProjectId,
                'autoexport'=>$autoexport,
                'lastchanged'=>$dbLastchanged,
                'active_members'=>null,
                'members'=>null,
                'balance'=>null,
                'shares'=>[],
                'guestpermissions'=>$guestpermissions,
                'currencyname'=>$dbCurrencyName
            ]);
        }
        $req->closeCursor();

        $qb = $qb->resetQueryParts();

        // shared with user
        $qb->select('p.id', 'p.password', 'p.name', 'p.email', 'p.autoexport', 'p.guestpermissions', 'p.currencyname', 'p.lastchanged')
           ->from('cospend_projects', 'p')
           ->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
           ->where(
               $qb->expr()->eq('s.userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('s.type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        $dbProjectId = null;
        $dbPassword = null;
        while ($row = $req->fetch()){
            $dbProjectId = $row['id'];
            // avoid putting twice the same project
            // this can happen with a share loop
            if (!in_array($dbProjectId, $projectids)) {
                $dbPassword = $row['password'];
                $dbName = $row['name'];
                $dbEmail= $row['email'];
                $autoexport = $row['autoexport'];
                $guestpermissions = $row['guestpermissions'];
                $dbCurrencyName = $row['currencyname'];
                $dbLastchanged = intval($row['lastchanged']);
                array_push($projects, [
                    'name'=>$dbName,
                    'contact_email'=>$dbEmail,
                    'id'=>$dbProjectId,
                    'autoexport'=>$autoexport,
                    'lastchanged'=>$dbLastchanged,
                    'active_members'=>null,
                    'members'=>null,
                    'balance'=>null,
                    'shares'=>[],
                    'guestpermissions'=>$guestpermissions,
                    'currencyname'=>$dbCurrencyName
                ]);
                array_push($projectids, $dbProjectId);
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
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $groupId = $row['userid'];
            array_push($candidateGroupIds, $groupId);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        // is the user member of these groups?
        foreach ($candidateGroupIds as $candidateGroupId) {
            $group = $this->groupManager->get($candidateGroupId);
            if ($group !== null && $group->inGroup($userO)) {
                // get projects shared with this group
                $qb->select('p.id', 'p.password', 'p.name', 'p.email', 'p.autoexport', 'p.guestpermissions', 'p.currencyname', 'p.lastchanged')
                    ->from('cospend_projects', 'p')
                    ->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
                    ->where(
                        $qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateGroupId, IQueryBuilder::PARAM_STR))
                    )
                    ->andWhere(
                        $qb->expr()->eq('s.type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
                    );
                $req = $qb->execute();

                $dbProjectId = null;
                $dbPassword = null;
                while ($row = $req->fetch()){
                    $dbProjectId = $row['id'];
                    // avoid putting twice the same project
                    // this can happen with a share loop
                    if (!in_array($dbProjectId, $projectids)) {
                        $dbPassword = $row['password'];
                        $dbName = $row['name'];
                        $dbEmail= $row['email'];
                        $autoexport = $row['autoexport'];
                        $guestpermissions = $row['guestpermissions'];
                        $dbCurrencyName = $row['currencyname'];
                        $dbLastchanged = intval($row['lastchanged']);
                        array_push($projects, [
                            'name'=>$dbName,
                            'contact_email'=>$dbEmail,
                            'id'=>$dbProjectId,
                            'autoexport'=>$autoexport,
                            'lastchanged'=>$dbLastchanged,
                            'active_members'=>null,
                            'members'=>null,
                            'balance'=>null,
                            'shares'=>[],
                            'guestpermissions'=>$guestpermissions,
                            'currencyname'=>$dbCurrencyName
                        ]);
                        array_push($projectids, $dbProjectId);
                    }
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();
            }
        }

        $circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
        if ($circlesEnabled) {
            // get circles with which a project is shared
            $candidateCircleIds = [];
            $qb->select('userid')
            ->from('cospend_shares', 's')
            ->where(
                $qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
            )
            ->groupBy('userid');
            $req = $qb->execute();
            while ($row = $req->fetch()){
                $circleId = $row['userid'];
                array_push($candidateCircleIds, $circleId);
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();

            // is the user member of these circles?
            foreach ($candidateCircleIds as $candidateCircleId) {
                if ($this->isUserInCircle($userId, $candidateCircleId)) {
                    // get projects shared with this circle
                    $qb->select('p.id', 'p.password', 'p.name', 'p.email', 'p.autoexport', 'p.guestpermissions', 'p.currencyname', 'p.lastchanged')
                        ->from('cospend_projects', 'p')
                        ->innerJoin('p', 'cospend_shares', 's', $qb->expr()->eq('p.id', 's.projectid'))
                        ->where(
                            $qb->expr()->eq('s.userid', $qb->createNamedParameter($candidateCircleId, IQueryBuilder::PARAM_STR))
                        )
                        ->andWhere(
                            $qb->expr()->eq('s.type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
                        );
                    $req = $qb->execute();

                    $dbProjectId = null;
                    $dbPassword = null;
                    while ($row = $req->fetch()){
                        $dbProjectId = $row['id'];
                        // avoid putting twice the same project
                        // this can happen with a share loop or multiple shares
                        if (!in_array($dbProjectId, $projectids)) {
                            $dbPassword = $row['password'];
                            $dbName = $row['name'];
                            $dbEmail= $row['email'];
                            $autoexport = $row['autoexport'];
                            $guestpermissions = $row['guestpermissions'];
                            $dbCurrencyName = $row['currencyname'];
                            $dbLastchanged = intval($row['lastchanged']);
                            array_push($projects, [
                                'name'=>$dbName,
                                'contact_email'=>$dbEmail,
                                'id'=>$dbProjectId,
                                'autoexport'=>$autoexport,
                                'lastchanged'=>$dbLastchanged,
                                'active_members'=>null,
                                'members'=>null,
                                'balance'=>null,
                                'shares'=>[],
                                'guestpermissions'=>$guestpermissions,
                                'currencyname'=>$dbCurrencyName
                            ]);
                            array_push($projectids, $dbProjectId);
                        }
                    }
                    $req->closeCursor();
                    $qb = $qb->resetQueryParts();
                }
            }
        }

        // get values for projects we're gonna return
        for ($i = 0; $i < count($projects); $i++) {
            $dbProjectId = $projects[$i]['id'];
            $members = $this->getMembers($dbProjectId, 'lowername');
            $shares = $this->getUserShares($dbProjectId);
            $groupShares = $this->getGroupShares($dbProjectId);
            $circleShares = $this->getCircleShares($dbProjectId);
            $currencies = $this->getCurrencies($dbProjectId);
            $categories = $this->getCategories($dbProjectId);
            $activeMembers = [];
            foreach ($members as $member) {
                if ($member['activated']) {
                    array_push($activeMembers, $member);
                }
            }
            $balance = $this->getBalance($dbProjectId);
            $projects[$i]['active_members'] = $activeMembers;
            $projects[$i]['members'] = $members;
            $projects[$i]['balance'] = $balance;
            $projects[$i]['shares'] = $shares;
            $projects[$i]['group_shares'] = $groupShares;
            $projects[$i]['circle_shares'] = $circleShares;
            $projects[$i]['currencies'] = $currencies;
            $projects[$i]['categories'] = $categories;
        }

        // get external projects
        $qb->select('ep.projectid', 'ep.password', 'ep.ncurl')
           ->from('cospend_ext_projects', 'ep')
           ->where(
               $qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbProjectId = $row['projectid'];
            $dbPassword = $row['password'];
            $dbNcUrl = $row['ncurl'];
            array_push($projects, [
                'name'=>$dbProjectId.'@'.$dbNcUrl,
                'ncurl'=>$dbNcUrl,
                'id'=>$dbProjectId,
                'password'=>$dbPassword,
                'active_members'=>null,
                'members'=>null,
                'balance'=>null,
                'shares'=>[],
                'external'=>true
            ]);
        }
        $req->closeCursor();

        return $projects;
    }

    private function getCategories($projectid) {
        $categories = [];

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('name', 'id', 'icon', 'color')
           ->from('cospend_project_categories', 'c')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbName = $row['name'];
            $dbIcon = $row['icon'];
            $dbColor = $row['color'];
            $dbId = intval($row['id']);
            $categories[$dbId] = [
                'name'=>$dbName,
                'icon'=>$dbIcon,
                'color'=>$dbColor
            ];
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $categories;
    }

    private function getCurrencies($projectid) {
        $currencies = [];

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('name', 'id', 'exchange_rate')
           ->from('cospend_currencies', 'c')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbName = $row['name'];
            $dbId = intval($row['id']);
            $dbExchangeRate = floatval($row['exchange_rate']);
            array_push($currencies, [
                'name'=>$dbName,
                'exchange_rate'=>$dbExchangeRate,
                'id'=>$dbId
            ]);
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $currencies;
    }

    private function getUserShares($projectid) {
        $shares = [];

        $userIdToName = [];
        foreach($this->userManager->search('') as $u) {
            $userIdToName[$u->getUID()] = $u->getDisplayName();
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid', 'userid', 'id', 'permissions')
           ->from('cospend_shares', 'sh')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('type', $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbuserId = $row['userid'];
            $dbprojectId = $row['projectid'];
            $dbId = $row['id'];
            $dbPermissions = $row['permissions'];
            if (array_key_exists($dbuserId, $userIdToName)) {
                array_push($shares, [
                    'userid'=>$dbuserId,
                    'name'=>$userIdToName[$dbuserId],
                    'id'=>$dbId,
                    'permissions'=>$dbPermissions
                ]);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $shares;
    }

    private function getGroupShares($projectid) {
        $shares = [];

        $groupIdToName = [];
        foreach($this->groupManager->search('') as $g) {
            $groupIdToName[$g->getGID()] = $g->getDisplayName();
        }

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('projectid', 'userid', 'id', 'permissions')
           ->from('cospend_shares', 'sh')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('type', $qb->createNamedParameter('g', IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();
        while ($row = $req->fetch()){
            $dbGroupId = $row['userid'];
            $dbprojectId = $row['projectid'];
            $dbId = $row['id'];
            $dbPermissions = $row['permissions'];
            if (array_key_exists($dbGroupId, $groupIdToName)) {
                array_push($shares, [
                    'groupid'=>$dbGroupId,
                    'name'=>$groupIdToName[$dbGroupId],
                    'id'=>$dbId,
                    'permissions'=>$dbPermissions
                ]);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $shares;
    }

    private function getCircleShares($projectid) {
        $shares = [];

        $circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
        if ($circlesEnabled) {
            $circleIdToName = [];
            $cs = \OCA\Circles\Api\v1\Circles::listCircles(\OCA\Circles\Model\Circle::CIRCLES_ALL, '', 0);
            foreach ($cs as $c) {
                $circleUniqueId = $c->getUniqueId();
                $circleName = $c->getName();
                $circleIdToName[$circleUniqueId] = $circleName;
            }

            $qb = $this->dbconnection->getQueryBuilder();
            $qb->select('projectid', 'userid', 'id', 'permissions')
            ->from('cospend_shares', 'sh')
            ->where(
                $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $qb->expr()->eq('type', $qb->createNamedParameter('c', IQueryBuilder::PARAM_STR))
            );
            $req = $qb->execute();
            while ($row = $req->fetch()){
                $dbCircleId = $row['userid'];
                $dbprojectId = $row['projectid'];
                $dbId = $row['id'];
                $dbPermissions = $row['permissions'];
                if (array_key_exists($dbCircleId, $circleIdToName)) {
                    array_push($shares, [
                        'circleid'=>$dbCircleId,
                        'name'=>$circleIdToName[$dbCircleId],
                        'id'=>$dbId,
                        'permissions'=>$dbPermissions
                    ]);
                }
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();
        }
        return $shares;
    }

    public function deleteMember($projectid, $memberid) {
        $memberToDelete = $this->getMemberById($projectid, $memberid);
        if ($memberToDelete !== null) {
            if ($memberToDelete['activated']) {
                $qb = $this->dbconnection->getQueryBuilder();
                $qb->update('cospend_members');
                $qb->set('activated', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
                $qb->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($memberid, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();
            }
            return 'OK';
        }
        else {
            return ['Not Found'];
        }
    }

    public function getMemberByName($projectId, $name) {
        $member = null;
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'weight', 'color', 'activated')
           ->from('cospend_members', 'm')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()){
            $dbMemberId = intval($row['id']);
            $dbWeight = floatval($row['weight']);
            $dbName = $row['name'];
            $dbActivated= intval($row['activated']);
            $dbColor = $row['color'];
            if ($dbColor === null) {
                $av = $this->avatarManager->getGuestAvatar($dbName);
                $dbColor = $av->avatarBackgroundColor($dbName);
            }
            else {
                $dbColor = $this->hexToRgb($dbColor);
            }
            $member = [
                    'activated' => ($dbActivated === 1),
                    'name' => $dbName,
                    'id' => $dbMemberId,
                    'weight' => $dbWeight,
                    'color' => $dbColor
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $member;
    }

    public function editBill($projectid, $billid, $date, $what, $payer, $payed_for,
                              $amount, $repeat, $paymentmode=null, $categoryid=null,
                              $repeatallactive=null, $repeatuntil=null) {
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->update('cospend_bills');

        // set last modification timestamp
        $ts = (new \DateTime())->getTimestamp();
        $qb->set('lastchanged', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT));

        // first check the bill exists
        if ($this->getBill($projectid, $billid) === null) {
            return ['message'=>$this->trans->t('There is no such bill')];
        }
        // then edit the hell of it
        if ($what === null || $what === '') {
            return ['what'=>$this->trans->t('"What" field is required')];
        }
        $qb->set('what', $qb->createNamedParameter($what, IQueryBuilder::PARAM_STR));

        if ($repeat === null || $repeat === '' || strlen($repeat) !== 1) {
            return ['repeat'=>$this->trans->t('Invalid value')];
        }
        $qb->set('repeat', $qb->createNamedParameter($repeat, IQueryBuilder::PARAM_STR));

        if ($repeatuntil !== null) {
            if ($repeatuntil === '') {
                $qb->set('repeatuntil', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR));
            }
            else {
                $qb->set('repeatuntil', $qb->createNamedParameter($repeatuntil, IQueryBuilder::PARAM_STR));
            }
        }
        if ($repeatallactive !== null && is_numeric($repeatallactive)) {
            $qb->set('repeatallactive', $qb->createNamedParameter($repeatallactive, IQueryBuilder::PARAM_INT));
        }
        if ($paymentmode !== null && is_string($paymentmode)) {
            $qb->set('paymentmode', $qb->createNamedParameter($paymentmode, IQueryBuilder::PARAM_STR));
        }
        if ($categoryid !== null && is_numeric($categoryid)) {
            $qb->set('categoryid', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT));
        }
        if ($date !== null && $date !== '') {
            $qb->set('date', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR));
        }
        if ($amount !== null && $amount !== '' && is_numeric($amount)) {
            $qb->set('amount', $qb->createNamedParameter($amount, IQueryBuilder::PARAM_STR));
        }
        if ($payer !== null && $payer !== '' && is_numeric($payer)) {
            $member = $this->getMemberById($projectid, $payer);
            if ($member === null) {
                return ['payer'=>$this->trans->t('Not a valid choice')];
            }
            else {
                $qb->set('payerid', $qb->createNamedParameter($payer, IQueryBuilder::PARAM_INT));
            }
        }

        $owerIds = null;
        // check owers
        if ($payed_for !== null && $payed_for !== '') {
            $owerIds = explode(',', $payed_for);
            if (count($owerIds) === 0) {
                return ['payed_for'=>$this->trans->t('Invalid value')];
            }
            else {
                foreach ($owerIds as $owerId) {
                    if (!is_numeric($owerId)) {
                        return ['payed_for'=>$this->trans->t('Invalid value')];
                    }
                    if ($this->getMemberById($projectid, $owerId) === null) {
                        return ['payed_for'=>$this->trans->t('Not a valid choice')];
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
        $req = $qb->execute();
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
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();
            }
        }

        return intval($billid);
    }

    /**
     * daily check of repeated bills
     */
    public function cronRepeatBills() {
        $result = [];
        $projects = [];
        $now = new \DateTime();
        // in case cron job wasn't executed during several days,
        // continue trying to repeat bills as long as there was at least one repeated
        $continue = true;
        while ($continue) {
            $continue = false;
            // get bills whith repetition flag
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->select('id', 'projectid', 'what', 'date', 'amount', 'payerid', 'repeat', 'repeatallactive')
            ->from('cospend_bills', 'b')
            ->where(
                $qb->expr()->neq('repeat', $qb->createNamedParameter('n', IQueryBuilder::PARAM_STR))
            );
            $req = $qb->execute();
            $bills = [];
            while ($row = $req->fetch()){
                $id = $row['id'];
                $what = $row['what'];
                $repeat = $row['repeat'];
                $repeatallactive = $row['repeatallactive'];
                $date = $row['date'];
                $projectid = $row['projectid'];
                array_push($bills, [
                    'id' => $id,
                    'what' => $what,
                    'repeat' => $repeat,
                    'repeatallactive' => $repeatallactive,
                    'date' => $date,
                    'projectid' => $projectid
                ]);
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();

            foreach ($bills as $bill) {
                // Use DateTimeImmutable instead of DateTime so that $billDate->add() returns a
                // new instance instead of modifying $billDate
                $billDate = new \DateTimeImmutable($bill['date']);

                $nextDate = null;
                switch($bill['repeat']) {
                case 'd':
                    $nextDate = $billDate->add(new \DateInterval('P1D'));
                    break;

                case 'w';
                    $nextDate = $billDate->add(new \DateInterval('P7D'));
                    break;

                case 'm':
                    if (intval($billDate->format('m')) === 12) {
                        $nextYear = $billDate->format('Y') + 1;
                        $nextMonth = 1;
                    }
                    else {
                        $nextYear = $billDate->format('Y');
                        $nextMonth = $billDate->format('m') + 1;
                    }

                    // same day of month if possible, otherwise at end of month
                    $nextDate = new \DateTime();
                    $nextDate->setDate($nextYear, $nextMonth, 1);
                    if ($billDate->format('d') > $nextDate->format('t')) {
                        $nextDate->setDate($nextYear, $nextMonth, $nextDate->format('t'));
                    }
                    else {
                        $nextDate->setDate($nextYear, $nextMonth, $billDate->format('d'));
                    }
                    break;

                case 'y':
                    $nextYear = $billDate->format('Y') + 1;
                    $nextMonth = $billDate->format('m');

                    // same day of month if possible, otherwise at end of month + same month
                    $nextDate = new \DateTime();
                    $nextDate->setDate($billDate->format('Y') + 1, $billDate->format('m'), 1);
                    if ($billDate->format('d') > $nextDate->format('t')) {
                        $nextDate->setDate($nextYear, $nextMonth, $nextDate->format('t'));
                    }
                    else {
                        $nextDate->setDate($nextYear, $nextMonth, $billDate->format('d'));
                    }
                    break;
                }

                // Unknown repeat interval
                if ($nextDate === null) {
                    continue;
                }

                // Repeat if $nextDate is in the past (or today)
                $diff = $now->diff($nextDate);
                if ($nextDate->format('Y-m-d') === $now->format('Y-m-d') || $diff->invert) {
                    $this->repeatBill($bill['projectid'], $bill['id'], $nextDate);
                    if (!array_key_exists($bill['projectid'], $projects)) {
                        $projects[$bill['projectid']] = $this->getProjectInfo($bill['projectid']);
                    }
                    array_push($result, [
                        'date_orig' => $bill['date'],
                        'date_repeat' => $nextDate->format('Y-m-d'),
                        'what' => $bill['what'],
                        'project_name' => $projects[$bill['projectid']]['name']
                    ]);
                    $continue = true;
                }
            }
        }
        return $result;
    }

    /**
     * duplicate the bill today and give it the repeat flag
     * remove the repeat flag on original bill
     */
    private function repeatBill($projectid, $billid, $datetime) {
        $bill = $this->getBill($projectid, $billid);

        $owerIds = [];
        if (intval($bill['repeatallactive']) === 1) {
            $pInfo = $this->getProjectInfo($projectid);
            foreach ($pInfo['active_members'] as $am) {
                array_push($owerIds, $am['id']);
            }
        }
        else {
            foreach ($bill['owers'] as $ower) {
                if ($ower['activated']) {
                    array_push($owerIds, $ower['id']);
                }
            }
        }
        $owerIdsStr = implode(',', $owerIds);

        // if bill should be repeated until...
        if ($bill['repeatuntil'] !== null && $bill['repeatuntil'] !== '') {
            $untilDate = new \DateTime($bill['repeatuntil']);
            $billDate = new \DateTimeImmutable($bill['date']);
            $nextDate = new \DateTime('now');
            if ($bill['repeat'] === 'd') {
                $nextDate = $billDate->add(new \DateInterval('P1D'));
            }
            else if ($bill['repeat'] === 'w') {
                $nextDate = $billDate->add(new \DateInterval('P7D'));
            }
            else if ($bill['repeat'] === 'm') {
                if (intval($billDate->format('m')) === 12) {
                    $nextYear = $billDate->format('Y') + 1;
                    $nextMonth = 1;
                }
                else {
                    $nextYear = $billDate->format('Y');
                    $nextMonth = $billDate->format('m') + 1;
                }

                // same day of month if possible, otherwise at end of month
                $nextDate = new \DateTime();
                $nextDate->setDate($nextYear, $nextMonth, 1);
                if ($billDate->format('d') > $nextDate->format('t')) {
                    $nextDate->setDate($nextYear, $nextMonth, $nextDate->format('t'));
                }
                else {
                    $nextDate->setDate($nextYear, $nextMonth, $billDate->format('d'));
                }
            }
            else if ($bill['repeat'] === 'y') {
                $nextYear = $billDate->format('Y') + 1;
                $nextMonth = $billDate->format('m');

                // same day of month if possible, otherwise at end of month + same month
                $nextDate = new \DateTime();
                $nextDate->setDate($billDate->format('Y') + 1, $billDate->format('m'), 1);
                if ($billDate->format('d') > $nextDate->format('t')) {
                    $nextDate->setDate($nextYear, $nextMonth, $nextDate->format('t'));
                }
                else {
                    $nextDate->setDate($nextYear, $nextMonth, $billDate->format('d'));
                }
            }
            if ($nextDate >= $untilDate) {
                $bill['repeat'] = 'n';
            }
        }

        $newBillId = $this->addBill($projectid, $datetime->format('Y-m-d'), $bill['what'], $bill['payer_id'],
                                    $owerIdsStr, $bill['amount'], $bill['repeat'], $bill['paymentmode'],
                                    $bill['categoryid'], $bill['repeatallactive'], $bill['repeatuntil']);

        $billObj = $this->billMapper->find($newBillId);
        $this->activityManager->triggerEvent(
            ActivityManager::COSPEND_OBJECT_BILL, $billObj,
            ActivityManager::SUBJECT_BILL_CREATE,
            []
        );

        // now we can remove repeat flag on original bill
        $this->editBill($projectid, $billid, $bill['date'], $bill['what'], $bill['payer_id'], null,
                        $bill['amount'], 'n', null, null, 0, null);
    }

    public function addCategory($projectid, $name, $icon, $color) {
        $qb = $this->dbconnection->getQueryBuilder();
        $projectInfo = $this->getProjectInfo($projectid);

        $qb->insert('cospend_project_categories')
            ->values([
                'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                'icon' => $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR),
                'color' => $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR),
                'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)
            ]);
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $insertedCategoryId = intval($qb->getLastInsertId());
        $response = $insertedCategoryId;

        return $response;
    }

    private function getCategory($projectId, $categoryid) {
        $category = null;

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'projectid', 'icon', 'color')
           ->from('cospend_project_categories', 'c')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('id', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            $dbCategoryId = intval($row['id']);
            $dbName = $row['name'];
            $dbIcon = $row['icon'];
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
        $qb = $qb->resetQueryParts();
        return $category;
    }

    public function deleteCategory($projectid, $categoryid) {
        $categoryToDelete = $this->getCategory($projectid, $categoryid);
        if ($categoryToDelete !== null) {
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->delete('cospend_project_categories')
               ->where(
                   $qb->expr()->eq('id', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
               )
               ->andWhere(
                   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
               );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            // then get rid of this category in bills
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->update('cospend_bills');
            $qb->set('categoryid', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
            $qb->where(
                $qb->expr()->eq('categoryid', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
            )
            ->andWhere(
                $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return $categoryid;
        }
        else {
            return ['message'=>$this->trans->t('Not found')];
        }
    }

    public function editCategory($projectid, $categoryid, $name, $icon, $color) {
        if ($name !== null && $name !== '') {
            if ($this->getCategory($projectid, $categoryid) !== null) {
                $qb = $this->dbconnection->getQueryBuilder();
                $qb->update('cospend_project_categories');
                $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
                $qb->set('icon', $qb->createNamedParameter($icon, IQueryBuilder::PARAM_STR));
                $qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
                $qb->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($categoryid, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $editedCategory = $this->getCategory($projectid, $categoryid);

                return $editedCategory;
            }
            else {
                return ['message'=>$this->trans->t('This project have no such category')];
            }
        }
        else {
            return ['message'=>$this->trans->t('Incorrect field values')];
        }
    }

    public function addCurrency($projectid, $name, $rate) {
        $qb = $this->dbconnection->getQueryBuilder();
        $projectInfo = $this->getProjectInfo($projectid);

        $qb->insert('cospend_currencies')
            ->values([
                'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                'exchange_rate' => $qb->createNamedParameter($rate, IQueryBuilder::PARAM_STR)
            ]);
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $insertedCurrencyId = intval($qb->getLastInsertId());
        $response = $insertedCurrencyId;

        return $response;
    }

    private function getCurrency($projectId, $currencyid) {
        $currency = null;

        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'name', 'exchange_rate', 'projectid')
           ->from('cospend_currencies', 'c')
           ->where(
               $qb->expr()->eq('projectid', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
           )
           ->andWhere(
               $qb->expr()->eq('id', $qb->createNamedParameter($currencyid, IQueryBuilder::PARAM_INT))
           );
        $req = $qb->execute();

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
        $qb = $qb->resetQueryParts();
        return $currency;
    }

    public function deleteCurrency($projectid, $currencyid) {
        $currencyToDelete = $this->getCurrency($projectid, $currencyid);
        if ($currencyToDelete !== null) {
            $qb = $this->dbconnection->getQueryBuilder();
            $qb->delete('cospend_currencies')
               ->where(
                   $qb->expr()->eq('id', $qb->createNamedParameter($currencyid, IQueryBuilder::PARAM_INT))
               )
               ->andWhere(
                   $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
               );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return $currencyid;
        }
        else {
            return ['message'=>$this->trans->t('Not found')];
        }
    }

    public function editCurrency($projectid, $currencyid, $name, $exchange_rate) {
        if ($name !== null && $name !== '' && is_numeric($exchange_rate)) {
            if ($this->getCurrency($projectid, $currencyid) !== null) {
                $qb = $this->dbconnection->getQueryBuilder();
                $qb->update('cospend_currencies');
                $qb->set('exchange_rate', $qb->createNamedParameter($exchange_rate, IQueryBuilder::PARAM_STR));
                $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
                $qb->where(
                    $qb->expr()->eq('id', $qb->createNamedParameter($currencyid, IQueryBuilder::PARAM_INT))
                )
                ->andWhere(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $editedCurrency = $this->getCurrency($projectid, $currencyid);

                return $editedCurrency;
            }
            else {
                return ['message'=>$this->trans->t('This project have no such currency')];
            }
        }
        else {
            return ['message'=>$this->trans->t('Incorrect field values')];
        }
    }

    public function addUserShare($projectid, $userid, $fromUserId) {
        // check if userId exists
        $userIds = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $fromUserId) {
                array_push($userIds, $u->getUID());
            }
        }
        if ($userid !== '' and in_array($userid, $userIds)) {
            $qb = $this->dbconnection->getQueryBuilder();
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
                $req = $qb->execute();
                $dbuserId = null;
                while ($row = $req->fetch()){
                    $dbuserId = $row['userid'];
                    break;
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();

                if ($dbuserId === null) {
                    $qb->insert('cospend_shares')
                        ->values([
                            'projectid' => $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR),
                            'userid' => $qb->createNamedParameter($userid, IQueryBuilder::PARAM_STR),
                            'type' => $qb->createNamedParameter('u', IQueryBuilder::PARAM_STR)
                        ]);
                    $req = $qb->execute();
                    $qb = $qb->resetQueryParts();

                    $insertedShareId = intval($qb->getLastInsertId());
                    $response = $insertedShareId;

                    // activity
                    $projectObj = $this->projectMapper->find($projectid);
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
                        ActivityManager::SUBJECT_PROJECT_SHARE,
                        ['who'=>$userid, 'type'=>'u']
                    );

                    // SEND NOTIFICATION
                    $manager = \OC::$server->getNotificationManager();
                    $notification = $manager->createNotification();

                    $acceptAction = $notification->createAction();
                    $acceptAction->setLabel('accept')
                        ->setLink('/apps/cospend', 'GET');

                    $declineAction = $notification->createAction();
                    $declineAction->setLabel('decline')
                        ->setLink('/apps/cospend', 'GET');

                    $notification->setApp('cospend')
                        ->setUser($userid)
                        ->setDateTime(new \DateTime())
                        ->setObject('addusershare', $projectid)
                        ->setSubject('add_user_share', [$fromUserId, $projectInfo['name']])
                        ->addAction($acceptAction)
                        ->addAction($declineAction)
                        ;

                    $manager->notify($notification);

                    return $response;
                }
                else {
                    return ['message'=>$this->trans->t('Already shared with this user')];
                }
            }
            else {
                return ['message'=>$this->trans->t('Impossible to share the project with its owner')];
            }
        }
        else {
            return ['message'=>$this->trans->t('No such user')];
        }
    }

    public function editSharePermissions($projectid, $shid, $permissions) {
        // check if user share exists
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('id', 'projectid')
            ->from('cospend_shares', 's')
            ->where(
                $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
            );
        $req = $qb->execute();
        $dbId = null;
        while ($row = $req->fetch()){
            $dbId = $row['id'];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        if ($dbId !== null) {
            // set the permissions
            $qb->update('cospend_shares')
                ->set('permissions', $qb->createNamedParameter($permissions, IQueryBuilder::PARAM_STR))
                ->where(
                    $qb->expr()->eq('projectid', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
                )
                ->andWhere(
                    $qb->expr()->eq('id', $qb->createNamedParameter($shid, IQueryBuilder::PARAM_INT))
                );
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            return 'OK';
        }
        else {
            return ['message'=>$this->trans->t('No such share')];
        }
    }

    public function editGuestPermissions($projectid, $permissions) {
        // check if project exists
        $qb = $this->dbconnection->getQueryBuilder();

        // set the permissions
        $qb->update('cospend_projects')
            ->set('guestpermissions', $qb->createNamedParameter($permissions, IQueryBuilder::PARAM_STR))
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($projectid, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();

        $response = 'OK';

        return $response;
    }

    public function deleteUserShare($projectid, $shid, $fromUserId) {
        // check if user share exists
        $qb = $this->dbconnection->getQueryBuilder();
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
        $req = $qb->execute();
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
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            $response = 'OK';

            // activity
            $projectObj = $this->projectMapper->find($projectid);
            $this->activityManager->triggerEvent(
                ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
                ActivityManager::SUBJECT_PROJECT_UNSHARE,
                ['who'=>$dbuserId, 'type'=>'u']
            );

            // SEND NOTIFICATION
            $projectInfo = $this->getProjectInfo($projectid);

            $manager = \OC::$server->getNotificationManager();
            $notification = $manager->createNotification();

            $acceptAction = $notification->createAction();
            $acceptAction->setLabel('accept')
                ->setLink('/apps/cospend', 'GET');

            $declineAction = $notification->createAction();
            $declineAction->setLabel('decline')
                ->setLink('/apps/cospend', 'GET');

            $notification->setApp('cospend')
                ->setUser($dbuserId)
                ->setDateTime(new \DateTime())
                ->setObject('deleteusershare', $projectid)
                ->setSubject('delete_user_share', [$fromUserId, $projectInfo['name']])
                ->addAction($acceptAction)
                ->addAction($declineAction)
                ;

            $manager->notify($notification);

            return $response;
        }
        else {
            return ['message'=>$this->trans->t('No such share')];
        }
    }

    public function addGroupShare($projectid, $groupid, $fromUserId) {
        // check if groupId exists
        $groupIds = [];
        foreach($this->groupManager->search('') as $g) {
            array_push($groupIds, $g->getGID());
        }
        if ($groupid !== '' and in_array($groupid, $groupIds)) {
            $qb = $this->dbconnection->getQueryBuilder();
            $projectInfo = $this->getProjectInfo($projectid);
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
            $req = $qb->execute();
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
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $insertedShareId = intval($qb->getLastInsertId());
                $response = $insertedShareId;

                // activity
                $projectObj = $this->projectMapper->find($projectid);
                $this->activityManager->triggerEvent(
                    ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
                    ActivityManager::SUBJECT_PROJECT_SHARE,
                    ['who'=>$groupid, 'type'=>'g']
                );

                return $response;
            }
            else {
                return ['message'=>$this->trans->t('Already shared with this group')];
            }
        }
        else {
            return ['message'=>$this->trans->t('No such group')];
        }
    }

    public function deleteGroupShare($projectid, $shid, $fromUserId) {
        // check if group share exists
        $qb = $this->dbconnection->getQueryBuilder();
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
        $req = $qb->execute();
        $dbGroupId = null;
        $dbId = null;
        while ($row = $req->fetch()){
            $dbGroupId = $row['userid'];
            $dbId = $row['id'];
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
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            $response = 'OK';

            // activity
            $projectObj = $this->projectMapper->find($projectid);
            $this->activityManager->triggerEvent(
                ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
                ActivityManager::SUBJECT_PROJECT_UNSHARE,
                ['who'=>$dbGroupId, 'type'=>'g']
            );

            return $response;
        }
        else {
            return ['message'=>$this->trans->t('No such share')];
        }
    }

    public function addCircleShare($projectid, $circleid, $fromUserId) {
        // check if circleId exists
        $circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
        if ($circlesEnabled) {
            $cs = \OCA\Circles\Api\v1\Circles::listCircles(\OCA\Circles\Model\Circle::CIRCLES_ALL, '', 0);
            $exists = false;
            foreach ($cs as $c) {
                if ($c->getUniqueId() === $circleid) {
                    if ($c->getType() === \OCA\Circles\Model\Circle::CIRCLES_PERSONAL) {
                        return ['message'=>$this->trans->t('Sharing with personal circles is not supported')];
                    }
                    else {
                        $exists = true;
                    }
                }
            }
            if ($circleid !== '' and $exists) {
                $qb = $this->dbconnection->getQueryBuilder();
                $projectInfo = $this->getProjectInfo($projectid);
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
                $req = $qb->execute();
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
                    $req = $qb->execute();
                    $qb = $qb->resetQueryParts();

                    $insertedShareId = intval($qb->getLastInsertId());
                    $response = $insertedShareId;

                    // activity
                    $projectObj = $this->projectMapper->find($projectid);
                    $this->activityManager->triggerEvent(
                        ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
                        ActivityManager::SUBJECT_PROJECT_SHARE,
                        ['who'=>$circleid, 'type'=>'c']
                    );

                    return $response;
                }
                else {
                    return ['message'=>$this->trans->t('Already shared with this circle')];
                }
            }
            else {
                return ['message'=>$this->trans->t('No such circle')];
            }
        }
        else {
            return ['message'=>$this->trans->t('Circles app is not enabled')];
        }
    }

    public function deleteCircleShare($projectid, $shid, $fromUserId) {
        // check if circle share exists
        $qb = $this->dbconnection->getQueryBuilder();
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
        $req = $qb->execute();
        $dbCircleId = null;
        $dbId = null;
        while ($row = $req->fetch()){
            $dbCircleId = $row['userid'];
            $dbId = $row['id'];
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
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();

            $response = 'OK';

            // activity
            $projectObj = $this->projectMapper->find($projectid);
            $this->activityManager->triggerEvent(
                ActivityManager::COSPEND_OBJECT_PROJECT, $projectObj,
                ActivityManager::SUBJECT_PROJECT_UNSHARE,
                ['who'=>$dbCircleId, 'type'=>'c']
            );
        }
        else {
            $response = ['message'=>$this->trans->t('No such share')];
        }

        return $response;
    }

    public function exportCsvSettlement($projectid, $userId) {
        // create export directory if needed
        $outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
        $userFolder = \OC::$server->getUserFolder($userId);
        $msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
        if ($msg !== '') {
            $response = ['message'=>$msg];
            return $response;
        }
        $folder = $userFolder->get($outPath);

        // create file
        if ($folder->nodeExists($projectid.'-settlement.csv')) {
            $folder->get($projectid.'-settlement.csv')->delete();
        }
        $file = $folder->newFile($projectid.'-settlement.csv');
        $handler = $file->fopen('w');
        fwrite($handler, $this->trans->t('Who pays?').','. $this->trans->t('To whom?').','. $this->trans->t('How much?')."\n");
        $transactions = $this->getProjectSettlement($projectid);

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
        $response = ['path'=>$outPath.'/'.$projectid.'-settlement.csv'];
        return $response;
    }

    private function createAndCheckExportDirectory($userFolder, $outPath) {
        if (!$userFolder->nodeExists($outPath)) {
            $userFolder->newFolder($outPath);
        }
        if ($userFolder->nodeExists($outPath)) {
            $folder = $userFolder->get($outPath);
            if ($folder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                return $this->trans->t('%1$s is not a folder', [$outPath]);
            }
            else if (!$folder->isCreatable()) {
                return $this->trans->t('%1$s is not writeable', [$outPath]);
            }
            else {
                return '';
            }
        }
        else {
            return $this->trans->t('Impossible to create %1$s', [$outPath]);
        }
    }

    public function exportCsvStatistics($projectid, $userId, $dateMin=null, $dateMax=null, $paymentMode=null, $category=null,
                                        $amountMin=null, $amountMax=null, $showDisabled='1', $currencyId=null) {
        // create export directory if needed
        $outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
        $userFolder = \OC::$server->getUserFolder($userId);
        $msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
        if ($msg !== '') {
            $response = ['message'=>$msg];
            return $response;
        }
        $folder = $userFolder->get($outPath);

        // create file
        if ($folder->nodeExists($projectid.'-stats.csv')) {
            $folder->get($projectid.'-stats.csv')->delete();
        }
        $file = $folder->newFile($projectid.'-stats.csv');
        $handler = $file->fopen('w');
        fwrite($handler, $this->trans->t('Member name').','. $this->trans->t('Paid').','. $this->trans->t('Spent').','. $this->trans->t('Balance')."\n");
        $allStats = $this->getProjectStatistics($projectid, 'lowername', $dateMin, $dateMax, $paymentMode,
                                                $category, $amountMin, $amountMax, $showDisabled, $currencyId);
        $stats = $allStats['stats'];
        if (!is_array($stats)) {
        }

        foreach ($stats as $stat) {
            fwrite($handler, '"'.$stat['member']['name'].'",'.floatval($stat['paid']).','.floatval($stat['spent']).','.floatval($stat['balance'])."\n");
        }

        fclose($handler);
        $file->touch();
        $response = ['path'=>$outPath.'/'.$projectid.'-stats.csv'];
        return $response;
    }

    public function exportCsvProject($projectid, $name, $userId) {
        // create export directory if needed
        $outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
        $userFolder = \OC::$server->getUserFolder($userId);
        $msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
        if ($msg !== '') {
            $response = ['message'=>$msg];
            return $response;
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
        fwrite($handler, "what,amount,date,payer_name,payer_weight,payer_active,owers,repeat,repeatallactive,repeatuntil,categoryid,paymentmode\n");
        $members = $projectInfo['members'];
        $memberIdToName = [];
        $memberIdToWeight = [];
        $memberIdToActive = [];
        foreach ($members as $member) {
            $memberIdToName[$member['id']] = $member['name'];
            $memberIdToWeight[$member['id']] = $member['weight'];
            $memberIdToActive[$member['id']] = intval($member['activated']);
            fwrite($handler, 'deleteMeIfYouWant,1,1970-01-01,"'.$member['name'].'",'.floatval($member['weight']).','.
                              intval($member['activated']).',"'.$member['name'].'",n,,,,'."\n");;
        }
        $bills = $this->getBills($projectid);
        foreach ($bills as $bill) {
            $owerNames = [];
            foreach ($bill['owers'] as $ower) {
                array_push($owerNames, $ower['name']);
            }
            $owersTxt = implode(', ', $owerNames);

            $payer_id = $bill['payer_id'];
            $payer_name = $memberIdToName[$payer_id];
            $payer_weight = $memberIdToWeight[$payer_id];
            $payer_active = $memberIdToActive[$payer_id];
            fwrite($handler, '"'.$bill['what'].'",'.floatval($bill['amount']).','.$bill['date'].',"'.$payer_name.'",'.
                             floatval($payer_weight).','.$payer_active.',"'.$owersTxt.'",'.$bill['repeat'].
                             ','.$bill['repeatallactive'].','.
                             $bill['repeatuntil'].','.$bill['categoryid'].','.$bill['paymentmode']."\n");
        }

        // write categories
        $categories = $projectInfo['categories'];
        if (count($categories) > 0) {
            fwrite($handler, "\n");
            fwrite($handler, "categoryname,categoryid,icon,color\n");
            foreach ($categories as $id=>$cat) {
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
        $response = ['path'=>$outPath.'/'.$filename];
        return $response;
    }

    public function importCsvProject($path, $userId) {
        $cleanPath = str_replace(array('../', '..\\'), '',  $path);
        $userFolder = \OC::$server->getUserFolder($userId);
        if ($userFolder->nodeExists($cleanPath)) {
            $file = $userFolder->get($cleanPath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
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
                        }
                        // determine which section we're entering
                        else if ($row === 0 or $previousLineEmpty) {
                            $previousLineEmpty = false;
                            $nbCol = count($data);
                            $columns = [];
                            for ($c=0; $c < $nbCol; $c++) {
                                $columns[$data[$c]] = $c;
                            }
                            if (array_key_exists('what', $columns) and
                                array_key_exists('amount', $columns) and
                                array_key_exists('date', $columns) and
                                array_key_exists('payer_name', $columns) and
                                array_key_exists('payer_weight', $columns) and
                                array_key_exists('owers', $columns)
                            ) {
                                $currentSection = 'bills';
                            }
                            else if (array_key_exists('icon', $columns) and
                                     array_key_exists('color', $columns) and
                                     array_key_exists('categoryid', $columns) and
                                     array_key_exists('categoryname', $columns)
                            ) {
                                $currentSection = 'categories';
                            }
                            else if (array_key_exists('exchange_rate', $columns) and
                                     array_key_exists('currencyname', $columns)
                            ) {
                                $currentSection = 'currencies';
                            }
                            else {
                                fclose($handle);
                                return ['message'=>$this->trans->t('Malformed CSV, bad column names')];
                            }
                        }
                        // normal line : bill or category
                        else {
                            $previousLineEmpty = false;
                            if ($currentSection === 'categories') {
                                $icon = $data[$columns['icon']];
                                $color = $data[$columns['color']];
                                $categoryid = $data[$columns['categoryid']];
                                $categoryname = $data[$columns['categoryname']];
                                array_push($categories, [
                                    'icon'=>$icon,
                                    'color'=>$color,
                                    'id'=>$categoryid,
                                    'name'=>$categoryname
                                ]);
                            }
                            else if ($currentSection === 'currencies') {
                                $name = $data[$columns['currencyname']];
                                $exchange_rate = $data[$columns['exchange_rate']];
                                if (floatval($exchange_rate) === 1.0) {
                                    $mainCurrencyName = $name;
                                }
                                else {
                                    array_push($currencies, [
                                        'name'=>$name,
                                        'exchange_rate'=>$exchange_rate
                                    ]);
                                }
                            }
                            else if ($currentSection === 'bills') {
                                $what = $data[$columns['what']];
                                $amount = $data[$columns['amount']];
                                $date = $data[$columns['date']];
                                $payer_name = $data[$columns['payer_name']];
                                $payer_weight = $data[$columns['payer_weight']];
                                $owers = $data[$columns['owers']];
                                $payer_active = array_key_exists('payer_active', $columns) ? $data[$columns['payer_active']] : 1;
                                $repeat = array_key_exists('repeat', $columns) ? $data[$columns['repeat']] : 'n';
                                $categoryid = array_key_exists('categoryid', $columns) ? intval($data[$columns['categoryid']]) : null;
                                $paymentmode = array_key_exists('paymentmode', $columns) ? $data[$columns['paymentmode']] : null;
                                $repeatallactive = array_key_exists('repeatallactive', $columns) ? $data[$columns['repeatallactive']] : 0;
                                $repeatuntil = array_key_exists('repeatuntil', $columns) ? $data[$columns['repeatuntil']] : null;

                                // manage members
                                $membersActive[$payer_name] = intval($payer_active);
                                if (is_numeric($payer_weight)) {
                                    $membersWeight[$payer_name] = floatval($payer_weight);
                                }
                                else {
                                    fclose($handle);
                                    return ['message'=>$this->trans->t('Malformed CSV, bad payer weight on line %1$s', [$row])];
                                }
                                if (strlen($owers) === 0) {
                                    fclose($handle);
                                    return ['message'=>$this->trans->t('Malformed CSV, bad owers on line %1$s', [$row])];
                                }
                                $owersArray = explode(', ', $owers);
                                foreach ($owersArray as $ower) {
                                    if (strlen($ower) === 0) {
                                        fclose($handle);
                                        return ['message'=>$this->trans->t('Malformed CSV, bad owers on line %1$s', [$row])];
                                    }
                                    if (!array_key_exists($ower, $membersWeight)) {
                                        $membersWeight[$ower] = 1.0;
                                    }
                                }
                                if (!is_numeric($amount)) {
                                    fclose($handle);
                                    return ['message'=>$this->trans->t('Malformed CSV, bad amount on line %1$s', [$row])];
                                }
                                array_push($bills, [
                                    'what'=>$what,
                                    'date'=>$date,
                                    'amount'=>$amount,
                                    'payer_name'=>$payer_name,
                                    'owers'=>$owersArray,
                                    'paymentmode'=>$paymentmode,
                                    'categoryid'=>$categoryid,
                                    'repeat'=>$repeat,
                                    'repeatuntil'=>$repeatuntil,
                                    'repeatallactive'=>$repeatallactive
                                ]);
                            }
                        }
                        $row++;
                    }
                    fclose($handle);

                    $memberNameToId = [];

                    // add project
                    $user = $this->userManager->get($userId);
                    $userEmail = $user->getEMailAddress();
                    $projectid = str_replace('.csv', '', $file->getName());
                    $projectName = $projectid;
                    $projResult = $this->createProject($projectName, $projectid, '', $userEmail, $userId);
                    if (!is_string($projResult)) {
                        return ['message'=>$this->trans->t('Error in project creation, %1$s', [$projResult['message']])];
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
                            return ['message'=>$this->trans->t('Error when adding category %1$s', [$cat['name']])];
                        }
                        $categoryIdConv[$cat['id']] = $insertedCatId;
                    }
                    // add currencies
                    foreach ($currencies as $cur) {
                        $insertedCurId = $this->addCurrency($projectid, $cur['name'], $cur['exchange_rate']);
                        if (!is_numeric($insertedCurId)) {
                            $this->deleteProject($projectid);
                            return ['message'=>$this->trans->t('Error when adding currency %1$s', [$cur['name']])];
                        }
                    }
                    // add members
                    foreach ($membersWeight as $memberName => $weight) {
                        $insertedMember = $this->addMember($projectid, $memberName, $weight, $membersActive[$memberName]);
                        if (!is_array($insertedMember)) {
                            $this->deleteProject($projectid);
                            return ['message'=>$this->trans->t('Error when adding member %1$s', [$memberName])];
                        }
                        $memberNameToId[$memberName] = $insertedMember['id'];
                    }
                    // add bills
                    foreach ($bills as $bill) {
                        // manage category id if this is a custom category
                        $catId = $bill['categoryid'];
                        if (is_numeric($catId) and intval($catId) > 0) {
                            $catId = $categoryIdConv[$catId];
                        }
                        $payerId = $memberNameToId[$bill['payer_name']];
                        $owerIds = [];
                        foreach ($bill['owers'] as $owerName) {
                            array_push($owerIds, $memberNameToId[$owerName]);
                        }
                        $owerIdsStr = implode(',', $owerIds);
                        $addBillResult = $this->addBill($projectid, $bill['date'], $bill['what'], $payerId,
                                                        $owerIdsStr, $bill['amount'], $bill['repeat'],
                                                        $bill['paymentmode'],
                                                        $catId, $bill['repeatallactive'],
                                                        $bill['repeatuntil']);
                        if (!is_numeric($addBillResult)) {
                            $this->deleteProject($projectid);
                            return ['message'=>$this->trans->t('Error when adding bill %1$s', [$bill['what']])];
                        }
                    }

                    return $projectid;
                }
                else {
                    return ['message'=>$this->trans->t('Access denied')];
                }
            }
            else {
                return ['message'=>$this->trans->t('Access denied')];
            }
        }
        else {
            return ['message'=>$this->trans->t('Access denied')];
        }
    }

       /**
     * @NoAdminRequired
     */
    public function importSWProject($path, $userId) {
        $cleanPath = str_replace(array('../', '..\\'), '',  $path);
        $userFolder = \OC::$server->getUserFolder();
        if ($userFolder->nodeExists($cleanPath)) {
            $file = $userFolder->get($cleanPath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                if (($handle = $file->fopen('r')) !== false) {
                    $columns = [];
                    $membersWeight = [];
                    $bills = [];
                    $owersArray = [];
                    $row = 0;
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
                                return ['message'=>$this->trans->t('Malformed CSV, bad column names')];
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
                                    return ['message'=>$this->trans->t('Malformed CSV, cannot have an empty ower')];
                                }
                                if (!array_key_exists($ower, $membersWeight)) {
                                    $membersWeight[$ower] = 1.0;
                                }
                            }
                        } elseif (!isset($data[$columns['Date']]) || empty($data[$columns['Date']])) {
                            // skip empty lines
                        } elseif (isset($data[$columns['Description']]) && $data[$columns['Description']] === 'Total balance') {
                            // skip the total lines
                        }
                        // normal line : bill
                        else {
                            $what = $data[$columns['Description']];
                            $amount = $data[$columns['Cost']];
                            $date = $data[$columns['Date']];
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
                                };
                            }
                            if (!isset($payer_name) || empty($payer_name)) {
                                return ['message'=>$this->trans->t('Malformed CSV, no payer on line %1$s', [$row])];
                            }
                            $payer_weight = 1;

                            if (!is_numeric($amount)) {
                                fclose($handle);
                                return ['message'=>$this->trans->t('Malformed CSV, bad amount on line %1$s', [$row])];
                            }
                            array_push($bills,
                                [
                                    'what'=>$what,
                                    'date'=>$date,
                                    'amount'=>$amount,
                                    'payer_name'=>$payer_name,
                                    'owers'=>$owersList,
                                ]
                            );
                        }
                        $row++;
                    }
                    fclose($handle);

                    $memberNameToId = [];

                    // add project
                    $user = $this->userManager->get($userId);
                    $userEmail = $user->getEMailAddress();
                    $projectid = str_replace('.csv', '', $file->getName());
                    $projectName = $projectid;
                    $projResult = $this->createProject($projectName, $projectid, '', $userEmail, $userId);
                    if (!is_string($projResult)) {
                        return ['message'=>$this->trans->t('Error in project creation, %1$s', [$projResult['message']])];
                    }
                    // add members
                    foreach ($membersWeight as $memberName => $weight) {
                        $insertedMember = $this->addMember($projectid, $memberName, $weight);
                        if (!is_array($insertedMember)) {
                            $this->deleteProject($projectid);
                            return ['message'=>$this->trans->t('Error when adding member %1$s', [$memberName])];
                        }
                        $memberNameToId[$memberName] = $insertedMember['id'];
                    }
                    // add bills
                    foreach ($bills as $bill) {
                        $payerId = $memberNameToId[$bill['payer_name']];
                        $owerIds = [];
                        foreach ($bill['owers'] as $owerName) {
                            array_push($owerIds, $memberNameToId[$owerName]);
                        }
                        $owerIdsStr = implode(',', $owerIds);
                        $addBillResult = $this->addBill($projectid, $bill['date'], $bill['what'], $payerId, $owerIdsStr, $bill['amount'], 'n');
                        if (!is_numeric($addBillResult)) {
                            $this->deleteProject($projectid);
                            return ['message'=>$this->trans->t('Error when adding bill %1$s', [$bill['what']])];
                        }
                    }
                    return $projectid;
                }
                else {
                    return ['message'=>$this->trans->t('Access denied')];
                }
            }
            else {
                return ['message'=>$this->trans->t('Access denied')];
            }
        }
        else {
            return ['message'=>$this->trans->t('Access denied')];
        }
    }

    /**
     * auto export
     * triggered by NC cron job
     *
     * export projects
     */
    public function cronAutoExport() {
        date_default_timezone_set('UTC');
        // last day
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $timestamp = $now->getTimestamp();

        // get begining of today
        $dateMaxDay = new \DateTime($y.'-'.$m.'-'.$d);
        $maxDayTimestamp = $dateMaxDay->getTimestamp();
        $minDayTimestamp = $maxDayTimestamp - 24*60*60;

        $dateMaxDay->modify('-1 day');
        $dailySuffix = '_'.$this->trans->t('daily').'_'.$dateMaxDay->format('Y-m-d');

        // last week
        $now = new \DateTime();
        while (intval($now->format('N')) !== 1) {
            $now->modify('-1 day');
        }
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateWeekMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxWeekTimestamp = $dateWeekMax->getTimestamp();
        $minWeekTimestamp = $maxWeekTimestamp - 7*24*60*60;
        $dateWeekMin = new \DateTime($y.'-'.$m.'-'.$d);
        $dateWeekMin->modify('-7 day');
        $weeklySuffix = '_'.$this->trans->t('weekly').'_'.$dateWeekMin->format('Y-m-d');

        // last month
        $now = new \DateTime();
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateMonthMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxMonthTimestamp = $dateMonthMax->getTimestamp();
        $now->modify('-1 day');
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = intval($now->format('Y'));
        $m = intval($now->format('m'));
        $d = intval($now->format('d'));
        $dateMonthMin = new \DateTime($y.'-'.$m.'-'.$d);
        $minMonthTimestamp = $dateMonthMin->getTimestamp();
        $monthlySuffix = '_'.$this->trans->t('monthly').'_'.$dateMonthMin->format('Y-m');

        $weekFilterArray = array();
        $weekFilterArray['tsmin'] = $minWeekTimestamp;
        $weekFilterArray['tsmax'] = $maxWeekTimestamp;
        $dayFilterArray = array();
        $dayFilterArray['tsmin'] = $minDayTimestamp;
        $dayFilterArray['tsmax'] = $maxDayTimestamp;
        $monthFilterArray = array();
        $monthFilterArray['tsmin'] = $minMonthTimestamp;
        $monthFilterArray['tsmax'] = $maxMonthTimestamp;

        $qb = $this->dbconnection->getQueryBuilder();

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
            $req = $qb->execute();

            $dbProjectId = null;
            $dbPassword = null;
            while ($row = $req->fetch()) {
                $dbProjectId = $row['id'];
                $dbName  = $row['name'];
                $autoexport = $row['autoexport'];

                $suffix = $dailySuffix;
                if ($autoexport === 'w') {
                    $suffix = $weeklySuffix;
                }
                else if ($autoexport === 'm') {
                    $suffix = $monthlySuffix;
                }
                // check if file already exists
                $exportName = $dbProjectId.$suffix.'.csv';

                $userFolder = \OC::$server->getUserFolder($uid);
                if (! $userFolder->nodeExists($outPath.'/'.$exportName)) {
                    $this->exportCsvProject($dbProjectId, $exportName, $uid);
                }
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();
        }
    }

    private function hexToRgb($color) {
        $color = \str_replace('#', '', $color);
        $split_hex_color = str_split($color, 2);
        $r = hexdec($split_hex_color[0]);
        $g = hexdec($split_hex_color[1]);
        $b = hexdec($split_hex_color[2]);
        return ['r'=>$r, 'g'=>$g, 'b'=>$b];
    }

}
