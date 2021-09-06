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

use OCP\AppFramework\Services\IInitialState;
use OCP\IServerContainer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use OCP\Notification\IManager as INotificationManager;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\Share\IManager as IShareManager;
use OCP\App\IAppManager;
use OCP\IUserManager;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\Service\UserService;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Service\ProjectService;

class PageNUtilsControllerTest extends TestCase {

	private $appName;
	private $request;

	private $container;
	private $app;

	private $pageController;
	private $pageController2;
	private $utilsController;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		// clear test users
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('test');
		if ($user !== null) {
			$user->delete();
		}
		$user = $userManager->get('test2');
		if ($user !== null) {
			$user->delete();
		}
		$user = $userManager->get('test3');
		if ($user !== null) {
			$user->delete();
		}

		// CREATE DUMMY USERS
		$u1 = $userManager->createUser('test', 'T0T0T0');
		$u1->setEMailAddress('toto@toto.net');
		$u2 = $userManager->createUser('test2', 'T0T0T0');
		$u3 = $userManager->createUser('test3', 'T0T0T0');
		$groupManager = $c->get(IGroupManager::class);
		$groupManager->createGroup('group1test');
		$groupManager->get('group1test')->addUser($u1);
		$groupManager->createGroup('group2test');
		$groupManager->get('group2test')->addUser($u2);
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
		$sc = $c->get(IServerContainer::class);
		$this->config = $sc->getConfig();

		$this->activityManager = new ActivityManager(
			$sc->getActivityManager(),
			new UserService(
				new ProjectMapper(
					$sc->getDatabaseConnection()
				),
				$c->get(IGroupManager::class),
				$sc->getDatabaseConnection()
			),
			new ProjectMapper(
				$sc->getDatabaseConnection()
			),
			new BillMapper(
				$sc->getDatabaseConnection()
			),
			$sc->getL10N($c->get('AppName')),
			$c->get(LoggerInterface::class),
			'test'
		);

		$this->activityManager2 = new ActivityManager(
			$sc->getActivityManager(),
			new UserService(
				new ProjectMapper(
					$sc->getDatabaseConnection()
				),
				$c->get(IGroupManager::class),
				$sc->getDatabaseConnection()
			),
			new ProjectMapper(
				$sc->getDatabaseConnection()
			),
			new BillMapper(
				$sc->getDatabaseConnection()
			),
			$sc->getL10N($c->get('AppName')),
			$c->get(LoggerInterface::class),
			'test2'
		);

		$this->projectService = new ProjectService(
			$sc->getL10N($c->get('AppName')),
			$sc->getConfig(),
			new ProjectMapper(
				$sc->getDatabaseConnection()
			),
			new BillMapper(
				$sc->getDatabaseConnection()
			),
			$this->activityManager,
			$sc->getAvatarManager(),
			$c->get(IUserManager::class),
			$c->get(IAppManager::class),
			$c->get(IGroupManager::class),
			$sc->getDateTimeZone(),
			$c->get(IRootFolder::class),
			$c->get(INotificationManager::class),
			$sc->getDatabaseConnection()
		);

		$this->pageController = new PageController(
			$this->appName,
			$this->request,
			$sc->getConfig(),
			$c->get(IShareManager::class),
			$c->get(IUserManager::class),
			$sc->getL10N($c->get('AppName')),
			new BillMapper(
				$sc->getDatabaseConnection()
			),
			$this->projectService,
			$this->activityManager,
			$sc->getDatabaseConnection(),
			$c->get(IRootFolder::class),
			$c->get(IInitialState::class),
			$c->get(IAppManager::class),
			'test'
		);

		$this->pageController2 = new PageController(
			$this->appName,
			$this->request,
			$sc->getConfig(),
			$c->get(IShareManager::class),
			$c->get(IUserManager::class),
			$sc->getL10N($c->get('AppName')),
			new BillMapper(
				$sc->getDatabaseConnection()
			),
			$this->projectService,
			$this->activityManager,
			$sc->getDatabaseConnection(),
			$c->get(IRootFolder::class),
			$c->get(IInitialState::class),
			$c->get(IAppManager::class),
			'test2'
		);

		$this->utilsController = new UtilsController(
			$this->appName,
			$this->request,
			$sc->getConfig(),
			'test'
		);
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('test');
		$user->delete();
		$user = $userManager->get('test2');
		$user->delete();
		$user = $userManager->get('test3');
		$user->delete();
		$groupManager = $c->get(IGroupManager::class);
		$groupManager->get('group1test')->delete();
		$groupManager->get('group2test')->delete();
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
		$this->assertEquals('superproj', $data['id']);

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
		$idMember1 = (int) $data['id'];

		$resp = $this->pageController->webAddMember('superproj', 'robert');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember2 = (int) $data['id'];

		// create member with unauthorized user
		$resp = $this->pageController2->webAddMember('superproj', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// create project with no contact email
		$result = $this->projectService->createProject('dummy proj', 'dummyproj', 'pwd', null, 'test');
		$this->assertTrue(isset($result['id']));
		$this->assertEquals('dummyproj', $result['id']);
		// delete this project
		$result = $this->projectService->deleteProject('dummyproj');
		$this->assertTrue(isset($result['message']));
		$this->assertEquals('DELETED', $result['message']);
		// delete unexisting project
		$result = $this->projectService->deleteProject('dummyproj2');
		$this->assertTrue(isset($result['error']));

		// guest access level
		$level = $this->projectService->getGuestAccessLevel('superproj');
		$this->assertEquals(Application::ACCESS_PARTICIPANT, $level);
		$level = $this->projectService->getGuestAccessLevel('superproj_doesnotexist');
		$this->assertEquals(Application::ACCESS_PARTICIPANT, $level);

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
		$idBill1 = (int) $data;

		$resp = $this->pageController->webAddBill('superproj', '2019-01-25', 'agua', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill2 = (int) $data;

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

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1.',aa', 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// get all bill ids
		$ids = $this->projectService->getAllBillIds('superproj');
		$this->assertTrue(in_array($idBill1, $ids));

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
		$this->assertEquals(200, $status);

		$resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, '');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', 0, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, '0,'.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, 'aa', 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// currencies
		$result = $this->projectService->editProject('superproj', 'SuperProj', null, null, null, 'euro');
		$this->assertTrue(isset($result['success']));
		$currencyId = $this->projectService->addCurrency('superproj', 'dollar', 1.5);
		$this->assertTrue($currencyId > 0);

		// get project stats

		$resp = $this->pageController->webGetProjectStatistics('superprojdoesnotexist');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		$resp = $this->pageController->webGetProjectStatistics('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$stats = $data['stats'];
		// check member stats
		$id1Found = false;
		$id2Found = false;
		foreach ($stats as $stat) {
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

		// stats with currency
		$resp = $this->pageController->webGetProjectStatistics(
			'superproj', null, null, null, null,
			null, null, null, $currencyId
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		// get project settlement plan

		$resp = $this->pageController->webGetProjectSettlement('superprojdoesnotexist');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		$resp = $this->pageController->webGetProjectSettlement('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$respData = $resp->getData();
		$data = $respData['transactions'];
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
		$stats = $data['stats'];
		// check member stats
		$id1Found = false;
		$id2Found = false;
		foreach ($stats as $stat) {
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
		$nbBills = count($data['bills']);
		$this->assertTrue($nbBills > 0);

		// get bills with limit
		$resp = $this->pageController->webGetBills('superproj', null, null, $nbBills - 1);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$limitedNbBills = count($data['bills']);
		$this->assertTrue($limitedNbBills < $nbBills);
		$this->assertEquals($nbBills - 1, $limitedNbBills);

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
		$nbBills2 = count($data['bills']);
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
		$this->assertEquals('DELETED', $data['message'] ?? '');

		// DELETE PROJECT which does not exist
		$resp = $this->pageController->webDeleteProject('superprojdontexist');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// CREATE PROJECT to delete
		$resp = $this->pageController->webCreateProject('projtodel', 'ProjToDel', 'weakpasswd');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('projtodel', $data['id']);

		// attempt to delete : wrong user
		$resp = $this->pageController2->webDeleteProject('projtodel');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// share the project with second user
		$resp = $this->pageController->addUserShare('projtodel', 'test2');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$shareId2 = $resp->getData()['id'];

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
		$this->assertEquals(200, $status);
		$groupShareId = $resp->getData()['id'];

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
		$this->assertEquals('DELETED', $data['message'] ?? '');
	}

}
