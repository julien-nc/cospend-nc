<?php
/**
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Cospend\Controller;

use \OCA\Cospend\AppInfo\Application;

class PageNUtilsControllerTest extends \PHPUnit\Framework\TestCase {

    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $config;
    private $app;

    private $pageController;
    private $pageController2;
    private $utilsController;

    public static function setUpBeforeClass(): void {
        $app = new Application();
        $c = $app->getContainer();

        // clear test users
        $user = $c->getServer()->getUserManager()->get('test');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test2');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test3');
        if ($user !== null) {
            $user->delete();
        }

        // CREATE DUMMY USERS
        $u1 = $c->getServer()->getUserManager()->createUser('test', 'T0T0T0');
        $u1->setEMailAddress('toto@toto.net');
        $u2 = $c->getServer()->getUserManager()->createUser('test2', 'T0T0T0');
        $u3 = $c->getServer()->getUserManager()->createUser('test3', 'T0T0T0');
        $c->getServer()->getGroupManager()->createGroup('group1test');
        $c->getServer()->getGroupManager()->get('group1test')->addUser($u1);
        $c->getServer()->getGroupManager()->createGroup('group2test');
        $c->getServer()->getGroupManager()->get('group2test')->addUser($u2);
    }

    protected function setUp(): void {
        $this->appName = 'cospend';
        $this->request = $this->getMockBuilder('\OCP\IRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->app = new Application();
        $this->container = $this->app->getContainer();
        $c = $this->container;
        $this->config = $c->query('ServerContainer')->getConfig();

        $this->activityManager = new \OCA\Cospend\Activity\ActivityManager(
            $c->query('ServerContainer')->getActivityManager(),
            new \OCA\Cospend\Service\UserService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                new \OCA\Cospend\Db\ProjectMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                new \OCA\Cospend\Db\BillMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                $c->getServer()->getShareManager(),
                $c->getServer()->getUserManager(),
                $c->getServer()->getGroupManager()
            ),
            new \OCA\Cospend\Db\ProjectMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            new \OCA\Cospend\Db\BillMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->getServer()->getUserManager(),
            'test'
        );

        $this->activityManager2 = new \OCA\Cospend\Activity\ActivityManager(
            $c->query('ServerContainer')->getActivityManager(),
            new \OCA\Cospend\Service\UserService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                new \OCA\Cospend\Db\ProjectMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                new \OCA\Cospend\Db\BillMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                $c->getServer()->getShareManager(),
                $c->getServer()->getUserManager(),
                $c->getServer()->getGroupManager()
            ),
            new \OCA\Cospend\Db\ProjectMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            new \OCA\Cospend\Db\BillMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->getServer()->getUserManager(),
            'test2'
        );

        $this->pageController = new PageController(
            $this->appName,
            $this->request,
            $c->query('ServerContainer'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->getServer()->getGroupManager(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->query('ServerContainer')->getLogger(),
            new \OCA\Cospend\Db\BillMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            new \OCA\Cospend\Db\ProjectMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            new \OCA\Cospend\Service\ProjectService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                $c->query('ServerContainer')->getConfig(),
                new \OCA\Cospend\Db\ProjectMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                new \OCA\Cospend\Db\BillMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                $this->activityManager,
                $c->query('ServerContainer')->getAvatarManager(),
                $c->getServer()->getShareManager(),
                $c->getServer()->getUserManager(),
                $c->getServer()->getGroupManager()
            ),
            $this->activityManager,
            'test'
        );

        $this->pageController2 = new PageController(
            $this->appName,
            $this->request,
            $c->query('ServerContainer'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->getServer()->getGroupManager(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->query('ServerContainer')->getLogger(),
            new \OCA\Cospend\Db\BillMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            new \OCA\Cospend\Db\ProjectMapper(
                $c->query('ServerContainer')->getDatabaseConnection()
            ),
            new \OCA\Cospend\Service\ProjectService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                $c->query('ServerContainer')->getConfig(),
                new \OCA\Cospend\Db\ProjectMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                new \OCA\Cospend\Db\BillMapper(
                    $c->query('ServerContainer')->getDatabaseConnection()
                ),
                $this->activityManager2,
                $c->query('ServerContainer')->getAvatarManager(),
                $c->getServer()->getShareManager(),
                $c->getServer()->getUserManager(),
                $c->getServer()->getGroupManager()
            ),
            $this->activityManager2,
            'test2'
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            $c->query('ServerContainer'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getAppManager(),
            $c->query('ServerContainer')->getAvatarManager(),
            'test'
        );
    }

    public static function tearDownAfterClass(): void {
        $app = new Application();
        $c = $app->getContainer();
        $user = $c->getServer()->getUserManager()->get('test');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test2');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test3');
        $user->delete();
        $c->getServer()->getGroupManager()->get('group1test')->delete();
        $c->getServer()->getGroupManager()->get('group2test')->delete();
    }

    protected function tearDown(): void {
        // in case there was a failure and something was not deleted
        $resp = $this->pageController->webDeleteProject('superproj');
        $resp = $this->pageController->webDeleteProject('projtodel');
    }

    public function testUtils() {
        // DELETE OPTIONS VALUES
        $resp = $this->utilsController->deleteOptionsValues();
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // SET OPTIONS
        $resp = $this->utilsController->saveOptionValue(['lala' => 'lolo']);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // GET OPTIONS
        $resp = $this->utilsController->getOptionsValues();
        $data = $resp->getData();
        $values = $data['values'];
        $this->assertEquals($values['lala'], 'lolo');
    }

    public function testPage() {
        // CLEAR OPTIONS
        $resp = $this->utilsController->deleteOptionsValues();
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // CREATE PROJECT
        $resp = $this->pageController->webCreateProject('superproj', 'SuperProj', 'toto');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('superproj', $data);

        $resp = $this->pageController->webCreateProject('superproj', 'SuperProj', 'toto');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webCreateProject('super/proj', 'SuperProj', 'toto');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // create members
        $resp = $this->pageController->webAddMember('superproj', 'bobby');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $idMember1 = intval($data);

        $resp = $this->pageController->webAddMember('superproj', 'robert');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $idMember2 = intval($data);

        // create member with unauthorized user
        $resp = $this->pageController2->webAddMember('superproj', 'bobby');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // get members
        $resp = $this->pageController->webGetProjects();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        //var_dump($data);
        $this->assertEquals(1, count($data));
        $this->assertEquals(2, count($data[0]['members']));

        // get project info
        $resp = $this->pageController->webGetProjectInfo('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('superproj', $data['id']);
        $this->assertEquals('SuperProj', $data['name']);
        $this->assertEquals('test', $data['userid']);
        foreach ($data['balance'] as $mid => $balance) {
            $this->assertEquals(0, $balance);
        }
        foreach ($data['members'] as $mid => $memberInfo) {
            $this->assertEquals(true, in_array($memberInfo['name'], ['robert', 'bobby']));
        }

        $resp = $this->pageController->webGetProjectInfo('superprojdoesnotexist');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // edit member
        $resp = $this->pageController->webEditMember('superproj', $idMember1, 'roberto', 1, true);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->webEditMember('superprojdoesnotexist', $idMember1, 'roberto', 1, true);
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webEditMember('superproj', -1, 'roberto', 1, true);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // create bills
        $resp = $this->pageController->webAddBill('superproj', '2019-01-22', 'boomerang', $idMember1, $idMember1.','.$idMember2, 22.5, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $idBill1 = intval($data);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-25', 'agua', $idMember2, $idMember1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $idBill2 = intval($data);

        $resp = $this->pageController->webAddBill('superprojdoesnotexist', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', -1, $idMember1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, -1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, '', 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, '');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '', 'lala', $idMember2, $idMember1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', '', $idMember2, $idMember1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1, 'amount', 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', 'memem', $idMember1, 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1.',aa', 12.3, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // edit bill
        $resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->webEditBill('superprojdoesnotexist', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webEditBill('superproj', -1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', '', $idMember1, $idMember1.','.$idMember2, 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, '');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', 0, $idMember1.','.$idMember2, 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, '0,'.$idMember2, 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, 'aa', 99, 'n');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // get project stats

        $resp = $this->pageController->webGetProjectStatistics('superprojdoesnotexist');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webGetProjectStatistics('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        // check member stats
        $id1Found = false;
        $id2Found = false;
        foreach ($data as $stat) {
            if ($stat['member']['id'] === $idMember1) {
                $this->assertEquals((99/2 - 12.3), $stat['balance']);
                $this->assertEquals(99, $stat['paid']);
                $this->assertEquals((99/2 + 12.3), $stat['spent']);
                $id1Found = true;
            }
            else if ($stat['member']['id'] === $idMember2) {
                $this->assertEquals((12.3 - 99/2), $stat['balance']);
                $this->assertEquals(12.3, $stat['paid']);
                $this->assertEquals(99/2, $stat['spent']);
                $id2Found = true;
            }
        }
        $this->assertEquals(true, $id1Found);
        $this->assertEquals(true, $id2Found);

        // get project settlement plan

        $resp = $this->pageController->webGetProjectSettlement('superprojdoesnotexist');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webGetProjectSettlement('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $id1Found = false;
        foreach ($data as $transaction) {
            if ($transaction['from'] === $idMember2 && $transaction['to'] === $idMember1) {
                $this->assertEquals((99/2 - 12.3), $transaction['amount']);
                $id1Found = true;
            }
        }
        $this->assertEquals(true, $id1Found);

        // auto settlement
        $resp = $this->pageController->webAutoSettlement('superprojdoesnotexist');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webAutoSettlement('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('OK', $data);

        // check balances are back to zero
        $resp = $this->pageController->webGetProjectStatistics('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        // check member stats
        $id1Found = false;
        $id2Found = false;
        foreach ($data as $stat) {
            if ($stat['member']['id'] === $idMember1) {
                $this->assertEquals(0, $stat['balance']);
                $this->assertEquals(99, $stat['paid']);
                $this->assertEquals((99/2 + 12.3) + (99/2 - 12.3), $stat['spent']);
                $id1Found = true;
            }
            else if ($stat['member']['id'] === $idMember2) {
                $this->assertEquals(0, $stat['balance']);
                $this->assertEquals(12.3 + (99/2 - 12.3), $stat['paid']);
                $this->assertEquals(99/2, $stat['spent']);
                $id2Found = true;
            }
        }
        $this->assertEquals(true, $id1Found);
        $this->assertEquals(true, $id2Found);

        // check number of bills
        $resp = $this->pageController->webGetBills('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $nbBills = count($data);
        $this->assertEquals(true, ($nbBills > 0));

        // DELETE BILL
        $resp = $this->pageController->webDeleteBill('superproj', $idBill1);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('OK', $data);

        // DELETE BILL of unexisting project
        $resp = $this->pageController->webDeleteBill('superprojLALA', $idBill1);
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // check number of bills again
        $resp = $this->pageController->webGetBills('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $nbBills2 = count($data);
        $this->assertEquals($nbBills2, ($nbBills - 1));

        $resp = $this->pageController->webGetBills('superprojLALA');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // EDIT PROJECT
        $resp = $this->pageController->webEditProject('superproj', 'newname', 'email@yep.yop', 'new password');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('UPDATED', $data);

        $resp = $this->pageController->webEditProject('superproj', 'newname', 'invalid email!', 'new password');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webEditProject('superprojLALA', 'newname', 'new email', 'new password');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        $resp = $this->pageController->webEditProject('superproj', '', 'new email', 'new password');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // DELETE PROJECT
        $resp = $this->pageController->webDeleteProject('superproj');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('DELETED', $data);

        // DELETE PROJECT which does not exist
        $resp = $this->pageController->webDeleteProject('superprojdontexist');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // CREATE PROJECT to delete
        $resp = $this->pageController->webCreateProject('projtodel', 'ProjToDel', 'weakpasswd');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('projtodel', $data);

        // attempt to delete : wrong user
        $resp = $this->pageController2->webDeleteProject('projtodel');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // share the project with second user
        $resp = $this->pageController->addUserShare('projtodel', 'test2');
        $status = $resp->getStatus();
        $shareId2 = $resp->getData();
        $this->assertEquals(200, $status);

        // get projects of second user
        $resp = $this->pageController2->webGetProjects();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
        $this->assertEquals('projtodel', $data[0]['id']);

        $resp = $this->pageController2->webGetProjectInfo('projtodel');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        // delete the user share
        $resp = $this->pageController->deleteUserShare('projtodel', $shareId2);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController2->webGetProjectInfo('projtodel');
        $status = $resp->getStatus();
        $this->assertEquals(403, $status);

        // get projects of second user to check if access to project was removed
        $resp = $this->pageController2->webGetProjects();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(0, count($data));

        // add a group share
        $resp = $this->pageController->addGroupShare('projtodel', 'group2test');
        $status = $resp->getStatus();
        $groupShareId = $resp->getData();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->addGroupShare('projtodel', 'group2test');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->addGroupShare('projtodel', 'group2testLALA');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // get projects of second user to see if access to shared project is possible
        $resp = $this->pageController2->webGetProjects();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
        $this->assertEquals('projtodel', $data[0]['id']);

        $resp = $this->pageController2->webGetProjectInfo('projtodel');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        // delete the group share
        $resp = $this->pageController->deleteGroupShare('projtodel', $groupShareId);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->deleteGroupShare('projtodel', -7777);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // then it should be ok to delete
        $resp = $this->pageController->webDeleteProject('projtodel');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('DELETED', $data);

        // EXTERNAL PROJECTS
        // ADD ONE
        $resp = $this->pageController->webAddExternalProject('idext', 'lastcloud.net', 'passodoble');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $idExt = $data;

        $resp = $this->pageController->webAddExternalProject('idext', 'lastcloud.net', 'passodoble');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->webAddExternalProject('id/ext', 'lastcloud.net', 'passodoble');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // EDIT EXT PROJECT
        $resp = $this->pageController->webEditExternalProject($idExt, 'lastcloud.net', 'passotriple');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->webEditExternalProject($idExt, 'lastcloud.org', 'passotriple');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // GET PROJECTS
        $resp = $this->pageController->webGetProjects();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
        $this->assertEquals('idext', $data[0]['id']);

        // DELETE EXT PROJECT
        $resp = $this->pageController->webDeleteExternalProject($idExt, 'lastcloud.net');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->webDeleteExternalProject($idExt, 'lastcloud.org');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        // GET USER LIST
        $resp = $this->pageController->getUserList();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $testFound = false;
        $groupFound = false;
        foreach ($data['users'] as $userid=>$username) {
            if ($userid === 'test2') {
                $testFound = true;
            }
        }
        $this->assertEquals(true, $testFound);
        foreach ($data['groups'] as $groupid=>$groupname) {
            if ($groupid === 'group1test') {
                $groupFound = true;
            }
        }
        $this->assertEquals(true, $groupFound);
    }

}
