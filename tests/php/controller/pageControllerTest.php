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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
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
		$this->config = $c->get(IConfig::class);
		$this->l10n = $c->get(IL10N::class);
		$this->billMapper = new BillMapper($sc->getDatabaseConnection());
		$this->projectMapper = new ProjectMapper($sc->getDatabaseConnection(), $this->l10n);

		$this->activityManager = new ActivityManager(
			$sc->getActivityManager(),
			new UserService(
				$this->projectMapper,
				$c->get(IGroupManager::class),
				$sc->getDatabaseConnection()
			),
			$this->projectMapper,
			$this->billMapper,
			$sc->getL10N($c->get('AppName')),
			$c->get(LoggerInterface::class),
			'test'
		);

		$this->activityManager2 = new ActivityManager(
			$sc->getActivityManager(),
			new UserService(
				$this->projectMapper,
				$c->get(IGroupManager::class),
				$sc->getDatabaseConnection()
			),
			$this->projectMapper,
			$this->billMapper,
			$sc->getL10N($c->get('AppName')),
			$c->get(LoggerInterface::class),
			'test2'
		);

		$this->projectService = new ProjectService(
			$sc->getL10N($c->get('AppName')),
			$sc->getConfig(),
			$this->projectMapper,
			$this->billMapper,
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
			$this->billMapper,
			$this->projectService,
			$this->activityManager,
			$sc->getDatabaseConnection(),
			$c->get(IRootFolder::class),
			$c->get(IInitialState::class),
			$c->get(IAppManager::class),
			$c->get(IEventDispatcher::class),
			'test'
		);

		$this->pageController2 = new PageController(
			$this->appName,
			$this->request,
			$sc->getConfig(),
			$c->get(IShareManager::class),
			$c->get(IUserManager::class),
			$sc->getL10N($c->get('AppName')),
			$this->billMapper,
			$this->projectService,
			$this->activityManager,
			$sc->getDatabaseConnection(),
			$c->get(IRootFolder::class),
			$c->get(IInitialState::class),
			$c->get(IAppManager::class),
			$c->get(IEventDispatcher::class),
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
		$resp = $this->pageController->webDeleteProject('original');
		$resp = $this->pageController->webDeleteProject('newproject');
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

		// get project names
		$res = $this->projectService->getProjectNames(null);
		$this->assertEquals(0, count($res));

		// create members
		$resp = $this->pageController->webAddMember('superproj', 'bobby', null, 1, 1, '');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];

		$resp = $this->pageController->webAddMember('superproj', 'robert');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];

		$resp = $this->pageController->webAddMember('superproj', 'robert3');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember3 = $data['id'];

		// get members
		$members = $this->projectService->getMembers('superproj', 'name', 0);
		$this->assertEquals(3, count($members));
		$members = $this->projectService->getMembers('superproj', 'name', 2147483646);
		$this->assertEquals(0, count($members));

		// already exists
		$res = $this->projectService->addMember('superproj', 'robert3');
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		// invalid name
		$res = $this->projectService->addMember('superproj', 'robert/4');
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		$res = $this->projectService->addMember('superproj', '');
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		// invalid weight
		$res = $this->projectService->addMember('superproj', 'robert4', 0.0);
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		// delete the member
		$resp = $this->pageController->webEditMember('superproj', $idMember3, null, null, false);
		$this->assertNull($resp->getData());
		$this->assertNull($this->projectService->getMemberById('superproj', $idMember3));

		$resp = $this->pageController->webAddMember('superproj', 'robert4', 'test', 1.2, 0, '#123456');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember4 = $data['id'];

		$member = $this->projectService->getMemberByUserid('superproj', 'test');
		$this->assertNotNull($member);
		$this->assertTrue(isset($member['name']));
		$this->assertEquals('robert4', $member['name']);

		$this->projectService->editMember('superproj', $idMember4, null, null, null, null, '');
		$member = $this->projectService->getMemberByUserid('superproj', 'test');
		$this->assertNotNull($member['color']);

		// delete the member
		$result = $this->projectService->deleteMember('superproj', $idMember4);
		$this->assertTrue(isset($result['success']));
		$this->assertNull($this->projectService->getMemberById('superproj', $idMember4));

		$result = $this->projectService->deleteMember('superproj', -1);
		$this->assertFalse(isset($result['success']));

		// create member with unauthorized user
		$resp = $this->pageController2->webAddMember('superproj', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// create member with invalid color
		$resp = $this->pageController->webAddMember('superproj', 'jojo', null, 1.2, 1, '#zz');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// add categories and payment modes
		$resp = $this->pageController->addCategory('superproj', 'cat1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idCat1 = $resp->getData();
		$resp = $this->pageController->addCategory('superproj', 'cat2', 'a', '#456789', 3);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idCat2 = $resp->getData();
		$resp = $this->pageController->addCategory('superproj', 'cat3', 'a', '#456789', 4);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idCat3 = $resp->getData();

		// delete category
		$res = $this->projectService->deleteCategory('superproj', $idCat3);
		$this->assertTrue(isset($res['success']));
		$cat3 = $this->projectService->getCategory('superproj', $idCat3);
		$this->assertNull($cat3);

		$res = $this->projectService->deleteCategory('superproj', -1);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));

		// check cat values
		$cat2 = $this->projectService->getCategory('superproj', $idCat2);
		$this->assertNotNull($cat2);
		$this->assertEquals('cat2', $cat2['name']);
		$this->assertEquals('a', $cat2['icon']);
		$this->assertEquals('#456789', $cat2['color']);

		$res = $this->projectService->editCategory('superproj', $idCat2, 'cat2_renamed', 'b', '#987654');
		$this->assertFalse(isset($res['message']));
		$res = $this->projectService->editCategory('superproj', $idCat2, '', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$res = $this->projectService->editCategory('superproj', -1, 'cat2_renamed', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$cat2 = $this->projectService->getCategory('superproj', $idCat2);
		$this->assertNotNull($cat2);
		$this->assertEquals('cat2_renamed', $cat2['name']);
		$this->assertEquals('b', $cat2['icon']);
		$this->assertEquals('#987654', $cat2['color']);

		$resp = $this->pageController->addPaymentMode('superproj', 'pm1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idPm1 = $resp->getData();
		$resp = $this->pageController->addPaymentMode('superproj', 'pm2', 'a', '#456789', 3);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idPm2 = $resp->getData();
		$resp = $this->pageController->addPaymentMode('superproj', 'pm3', 'a', '#456789', 4);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idPm3 = $resp->getData();

		// delete pm
		$res = $this->projectService->deletePaymentMode('superproj', $idPm3);
		$this->assertTrue(isset($res['success']));
		$pm3 = $this->projectService->getPaymentMode('superproj', $idPm3);
		$this->assertNull($pm3);

		$res = $this->projectService->deletePaymentMode('superproj', -1);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));

		// check pm values
		$pm2 = $this->projectService->getPaymentMode('superproj', $idPm2);
		$this->assertNotNull($pm2);
		$this->assertEquals('pm2', $pm2['name']);
		$this->assertEquals('a', $pm2['icon']);
		$this->assertEquals('#456789', $pm2['color']);

		$res = $this->projectService->editPaymentMode('superproj', $idPm2, 'pm2_renamed', 'b', '#987654');
		$this->assertFalse(isset($res['message']));
		$res = $this->projectService->editPaymentMode('superproj', $idPm2, '', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$res = $this->projectService->editPaymentMode('superproj', -1, 'pm2_renamed', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$pm2 = $this->projectService->getPaymentMode('superproj', $idPm2);
		$this->assertNotNull($pm2);
		$this->assertEquals('pm2_renamed', $pm2['name']);
		$this->assertEquals('b', $pm2['icon']);
		$this->assertEquals('#987654', $pm2['color']);

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
		$this->assertEquals(Application::ACCESS_LEVELS['participant'], $level);
		$level = $this->projectService->getGuestAccessLevel('superproj_doesnotexist');
		$this->assertEquals(Application::ACCESS_LEVELS['none'], $level);

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
		$resp = $this->pageController->webEditMember('superproj', $idMember1, 'roberto', 1.2, true, '#112233', 'test');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		$this->assertEquals('test', $data['userid']);
		$this->assertEquals('roberto', $data['name']);
		$this->assertEquals(1.2, $data['weight']);
		$this->assertTrue($data['activated']);
		$resp = $this->pageController->webEditMember('superproj', $idMember1, 'roberto', 1, true, '', '');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals(null, $data['userid']);

		$resp = $this->pageController->webEditMember('superprojdoesnotexist', $idMember1, 'roberto', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));

		// member does not exist
		$resp = $this->pageController->webEditMember('superproj', -1, 'roberto', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['name']));
		$this->assertFalse(isset($data['id']));

		// name the user like an existing user
		$resp = $this->pageController->webEditMember('superproj', $idMember1, 'robert', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['name']));
		$this->assertFalse(isset($data['id']));

		// invalid name
		$resp = $this->pageController->webEditMember('superproj', $idMember1, 'robert/invalid', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['name']));
		$this->assertFalse(isset($data['id']));

		// invalid color
		$resp = $this->pageController->webEditMember('superproj', $idMember1, 'robertvalid', 1, true, '#zz');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['color']));
		$this->assertFalse(isset($data['id']));

		// invalid weight
		$resp = $this->pageController->webEditMember('superproj', $idMember1, 'robert3', 0, true);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['weight']));
		$this->assertFalse(isset($data['id']));

		// create bills
		$resp = $this->pageController->webAddBill(
			'superproj', '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, $idPm1, $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill1 = $data;

		// check bill values
		$bill = $this->billMapper->getBill('superproj', $idBill1);
		$this->assertNotNull($bill);
		$this->assertEquals('boomerang', $bill['what']);
		$this->assertEquals('2019-01-22', $bill['date']);
		$this->assertEquals($idMember1, $bill['payer_id']);
		$this->assertEquals(22.5, $bill['amount']);
		$this->assertEquals(Application::FREQUENCIES['no'], $bill['repeat']);
		$this->assertEquals('n', $bill['paymentmode']);
		$this->assertEquals($idPm1, $bill['paymentmodeid']);
		$this->assertEquals($idCat1, $bill['categoryid']);
		$this->assertEquals(0, $bill['repeatallactive']);
		$this->assertEquals('2049-01-01', $bill['repeatuntil']);
		$this->assertEquals(1, $bill['repeatfreq']);
		$this->assertEquals(null, $bill['comment']);
		$this->assertTrue(count($bill['owers']) === 2);
		$this->assertTrue($bill['owers'][0]['id'] === $idMember1 || $bill['owers'][0]['id'] === $idMember2);
		$this->assertTrue($bill['owers'][1]['id'] === $idMember1 || $bill['owers'][1]['id'] === $idMember2);
		$this->assertTrue(count($bill['owerIds']) === 2);
		$this->assertTrue(in_array($idMember1, $bill['owerIds']));
		$this->assertTrue(in_array($idMember2, $bill['owerIds']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-25', 'agua', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill2 = $data;

		// with null data
		$resp = $this->pageController->webAddBill(
			'superproj', '2019-01-25', null, $idMember2, $idMember1, 12.3, 'n',
			null, null, null, 0, ''
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill3 = $data;

		$bills = $this->projectService->getBillsOfMember($idMember2);
		$this->assertTrue(in_array($idBill3, $bills));

		$this->projectService->deleteBill('superproj', $idBill3);

		// check payment mode old id is set when using one default payment mode
		// get a default payment mode
		$pms = $this->projectService->getCategoriesOrPaymentModes('superproj', false);
		$oneDefPm = null;
		foreach ($pms as $pm) {
			if (isset($pm['old_id']) && $pm['old_id'] !== null && $pm['old_id'] !== '') {
				$oneDefPm = $pm;
				break;
			}
		}
		$this->assertNotNull($oneDefPm);
		// add a bill with this payment mode
		$resp = $this->pageController->webAddBill(
			'superproj', '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, $oneDefPm['id'], $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBillPm = $data;

		$bill = $this->billMapper->getBill('superproj', $idBillPm);
		$this->assertNotNull($bill);
		$this->assertEquals($oneDefPm['old_id'], $bill['paymentmode']);
		$this->assertEquals($oneDefPm['id'], $bill['paymentmodeid']);

		// check the same with bill edition
		// get another default payment mode
		$otherDefPm = null;
		foreach ($pms as $pm) {
			if (isset($pm['old_id']) && $pm['old_id'] !== null && $pm['old_id'] !== '' && $pm['old_id'] !== $oneDefPm['old_id']) {
				$otherDefPm = $pm;
				break;
			}
		}
		$this->assertNotNull($otherDefPm);
		// edit a bill with this payment mode
		$resp = $this->pageController->webEditBill(
			'superproj', $idBillPm, '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, $otherDefPm['id'], $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals($idBillPm, $data);

		$bill = $this->billMapper->getBill('superproj', $idBillPm);
		$this->assertNotNull($bill);
		$this->assertEquals($otherDefPm['old_id'], $bill['paymentmode']);
		$this->assertEquals($otherDefPm['id'], $bill['paymentmodeid']);

		$resp = $this->pageController->webEditBill(
			'superproj', $idBillPm, '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], $oneDefPm['old_id'], null, $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals($idBillPm, $data);
		$bill = $this->billMapper->getBill('superproj', $idBillPm);
		$this->assertNotNull($bill);
		$this->assertEquals($oneDefPm['old_id'], $bill['paymentmode']);
		$this->assertEquals($oneDefPm['id'], $bill['paymentmodeid']);
		$this->projectService->deleteBill('superproj', $idBillPm);

		// add bill with old pm id, it should affect the matching default pm
		$resp = $this->pageController->webAddBill(
			'superproj', '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'],
			'c', null, $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBillOldPmId = $data;
		$bill = $this->billMapper->getBill('superproj', $idBillOldPmId);
		$this->assertNotNull($bill);
		$this->assertEquals('c', $bill['paymentmode']);
		$this->assertTrue(isset($bill['paymentmodeid']));
		$pmId = $bill['paymentmodeid'];
		$pm = $this->projectService->getPaymentMode('superproj', $pmId);
		$this->assertNotNull($pm);
		$this->assertEquals('c', $pm['old_id']);
		$this->projectService->deleteBill('superproj', $idBillOldPmId);

		// more invalid data
		$resp = $this->pageController->webAddBill(
			'superproj', '2019-01-25', null, null, $idMember1, 12.3, 'n',
			null, null, null, 0, '', null,
		);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->pageController->webAddBill(
			'superproj', '2019-01-25', null, $idMember2, $idMember1, null, 'n',
			null, null, null, 0, '', null,
		);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->pageController->webAddBill('superprojdoesnotexist', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		$resp = $this->pageController->webAddBill('superproj', 'aa-aa', 'lala', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['date']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', -1, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['payer']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, -1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['payed_for']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, '', 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['payed_for']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, '');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['repeat']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, 'zzz');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['repeat']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '', 'lala', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->pageController->webAddBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1.',aa', 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['payed_for']));
		$this->assertFalse(isset($data['inserted_id']));

		// get all bill ids
		$ids = $this->projectService->getAllBillIds('superproj');
		$this->assertTrue(in_array($idBill1, $ids));

		// edit bill
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill1, '2039-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['monthly'], null,
			$idPm2, $idCat2, 1, '2021-09-10',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		// check bill values
		$bill = $this->billMapper->getBill('superproj', $idBill1);
		$this->assertNotNull($bill);
		$this->assertEquals('kangaroo', $bill['what']);
		$this->assertEquals('2039-02-02', $bill['date']);
		$this->assertEquals($idMember2, $bill['payer_id']);
		$this->assertEquals(99, $bill['amount']);
		$this->assertEquals(Application::FREQUENCIES['monthly'], $bill['repeat']);
		$this->assertEquals('n', $bill['paymentmode']);
		$this->assertEquals($idPm2, $bill['paymentmodeid']);
		$this->assertEquals($idCat2, $bill['categoryid']);
		$this->assertEquals(1, $bill['repeatallactive']);
		$this->assertEquals('2021-09-10', $bill['repeatuntil']);
		$this->assertEquals(2, $bill['repeatfreq']);
		$this->assertEquals('newcom', $bill['comment']);
		$this->assertTrue(count($bill['owers']) === 2);
		$this->assertTrue($bill['owers'][0]['id'] === $idMember1 || $bill['owers'][0]['id'] === $idMember2);
		$this->assertTrue($bill['owers'][1]['id'] === $idMember1 || $bill['owers'][1]['id'] === $idMember2);
		$this->assertTrue(count($bill['owerIds']) === 2);
		$this->assertTrue(in_array($idMember1, $bill['owerIds']));
		$this->assertTrue(in_array($idMember2, $bill['owerIds']));

		// set cat/pm order
		$this->projectService->editProject(
			'superproj', 'proj', null, null,
			null, null, null,
			Application::SORT_ORDERS['most_used'], Application::SORT_ORDERS['most_used']
		);
		// check categories/pm
		$cats = $this->projectService->getCategoriesOrPaymentModes('superproj', true);
		$this->assertTrue(count($cats) === count($this->projectService->defaultCategories) + 2);
		$this->assertEquals(0, $cats[$idCat2]['order'], 'order of cat2 should be 0 but is ' . $cats[$idCat2]['order']);
		$pms = $this->projectService->getCategoriesOrPaymentModes('superproj', false);
		$this->assertTrue(count($pms) === count($this->projectService->defaultPaymentModes) + 2);
		$this->assertEquals(0, $pms[$idPm2]['order'], 'order of pm2 should be 0 but is ' . $pms[$idPm2]['order']);

		// set cat/pm order
		$this->projectService->editProject(
			'superproj', 'proj', null, null,
			null, null, null,
			Application::SORT_ORDERS['most_recently_used'], Application::SORT_ORDERS['most_recently_used']
		);
		// check categories/pm
		$cats = $this->projectService->getCategoriesOrPaymentModes('superproj', true);
		$this->assertEquals(count($this->projectService->defaultCategories) + 2, count($cats));
		$this->assertEquals(0, $cats[$idCat2]['order']);
		$pms = $this->projectService->getCategoriesOrPaymentModes('superproj', false);
		$this->assertEquals(count($this->projectService->defaultPaymentModes) + 2, count($pms));
		$this->assertEquals(0, $pms[$idPm2]['order']);

		$resp = $this->pageController->webEditBill(
			'superproj', $idBill1, null, 'boomerang', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['monthly'], null,
			null, null, 1, '',
			123456789, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		// check bill values
		$bill = $this->billMapper->getBill('superproj', $idBill1);
		$this->assertNotNull($bill);
		$this->assertEquals(123456789, $bill['timestamp']);

		$resp = $this->pageController->webEditBill('superprojdoesnotexist', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		$resp = $this->pageController->webEditBill('superproj', -1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->pageController->webEditBill(
			'superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['monthly'] . 'wrong_value', null,
			null, null, null, null,
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', '', $idMember1, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$resp = $this->pageController->webEditBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, '');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		// invalid date
		$resp = $this->pageController->webEditBill('superproj', $idBill1, 'aaa', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, '');
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

		// currencies
		$result = $this->projectService->editProject('superproj', 'SuperProj', null, null, null, 'euro');
		$this->assertTrue(isset($result['success']));
		$currencyId = $this->projectService->addCurrency('superproj', 'dollar', 1.5);
		$this->assertTrue($currencyId > 0);

		$currencyId2 = $this->projectService->addCurrency('superproj', 'dollar2', 1.5);
		$this->assertTrue($currencyId2 > 0);
		$res = $this->projectService->editCurrency('superproj', $currencyId2, 'dolrenamed', 2);
		$this->assertFalse(isset($res['message']));
		$this->assertEquals('dolrenamed', $res['name']);
		$this->assertEquals(2, $res['exchange_rate']);
		$this->assertEquals($currencyId2, $res['id']);
		$res = $this->projectService->editCurrency('superproj', $currencyId2, '', 0);
		$this->assertTrue(isset($res['message']));
		$res = $this->projectService->editCurrency('superproj', -1, 'dolrenamed', 2);
		$this->assertTrue(isset($res['message']));
		$res = $this->projectService->deleteCurrency('superproj', $currencyId2);
		$this->assertTrue(isset($res['success']));
		$res = $this->projectService->deleteCurrency('superproj', -1);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));

		// share link
		$res = $this->projectService->addPublicShare('superproj');
		$this->assertTrue(isset($res['token'], $res['id']));
		$this->assertTrue($res['id'] > 0);
		$shareLinkId = $res['id'];
		$shareLinkToken = $res['token'];
		$res = $this->projectService->addPublicShare('superproj');
		$this->assertTrue(isset($res['id'], $res['token']));
		$this->assertTrue($res['id'] > 0);
		$shareLinkId2 = $res['id'];
		$res = $this->projectService->deletePublicShare('superproj', $shareLinkId2);
		$this->assertTrue(isset($res['success']));
		$res = $this->projectService->deletePublicShare('superproj', -1);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));

		$res = $this->projectService->editShareAccess('superproj', $shareLinkId, 'lala', 'passpass');
		$this->assertTrue(isset($res['success']));
		$this->assertFalse(isset($res['message']));
		$res = $this->projectService->editShareAccessLevel('superproj', $shareLinkId, Application::ACCESS_LEVELS['admin']);
		$this->assertTrue(isset($res['success']));
		$this->assertFalse(isset($res['message']));
		$res = $this->projectService->editShareAccess('superproj', -1, 'lala', 'passpass');
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));
		$res = $this->projectService->editShareAccessLevel('superproj', -1, Application::ACCESS_LEVELS['admin']);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));
		$res = $this->projectService->getPublicShares('superproj');
		$this->assertEquals(1, count($res));
		$this->assertEquals($shareLinkToken, $res[0]['token']);
		$this->assertEquals('lala', $res[0]['label']);
		$this->assertEquals('passpass', $res[0]['password']);
		$this->assertEquals(Application::ACCESS_LEVELS['admin'], $res[0]['accesslevel']);
		$this->assertEquals($shareLinkId, $res[0]['id']);

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
			} elseif ($stat['member']['id'] === $idMember2) {
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
			null, null, '1', $currencyId
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
			} elseif ($stat['member']['id'] === $idMember2) {
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

		// delete bill that does not exist
		$resp = $this->pageController->webDeleteBill('superproj', -1);
		$status = $resp->getStatus();
		$this->assertEquals(404, $status);

		// DELETE BILL of unexisting project
		$resp = $this->pageController->webDeleteBill('superprojLALA', $idBill1);
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// delete bill when deletion is disabled
		$resp = $this->pageController->webEditProject(
			'superproj', null, null, null,
			null, null, true,
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		// try to delete a bill
		$resp = $this->pageController->webDeleteBill('superproj', $idBill1);
		$status = $resp->getStatus();
		$this->assertEquals(404, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		// reset bill deletion in project
		$resp = $this->pageController->webEditProject(
			'superproj', null, null, null,
			null, null, false,
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

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
		$resp = $this->pageController->webEditProject(
			'superproj', 'newname', 'email@yep.yop', 'new password',
			Application::FREQUENCIES['monthly'], '', false,
			Application::SORT_ORDERS['manual'], Application::SORT_ORDERS['manual']
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('UPDATED', $data);

		// invalid email
		$resp = $this->pageController->webEditProject('superproj', 'newname', 'invalid email!', 'new password');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// user can't edit this project (test is not the owner of superprojLALA)
		$resp = $this->pageController->webEditProject('superprojLALA', 'newname', 'new email', 'new password');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// project does not exist
		$resp = $this->pageController->webEditProject('doesnotexit', 'newname', 'new email', 'new password');
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		$res = $this->projectService->editProject('blabla', 'plop');
		$this->assertTrue(isset($res['message']));
		$this->assertFalse(isset($res['success']));

		// invalid name
		$resp = $this->pageController->webEditProject('superproj', '', 'new email', 'new password');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// invalid category sort
		$resp = $this->pageController->webEditProject(
			'superproj', 'newname', 'email@yep.yop', 'new password',
			Application::FREQUENCIES['monthly'], 'euro', null,
			'zzz', Application::SORT_ORDERS['manual']
		);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// invalid payment mode sort
		$resp = $this->pageController->webEditProject(
			'superproj', 'newname', 'email@yep.yop', 'new password',
			Application::FREQUENCIES['monthly'], 'euro', null,
			Application::SORT_ORDERS['manual'], 'zzz'
		);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// invalid auto export frequency
		$resp = $this->pageController->webEditProject(
			'superproj', 'newname', 'email@yep.yop', 'new password',
			'zzz', 'euro', null,
			Application::SORT_ORDERS['manual'], Application::SORT_ORDERS['manual']
		);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// repeat bills
		// bill with no enabled owers
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['yearly'], null,
			$idPm2, $idCat2, 0, '2021-03-10',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		// disable users
		$resp = $this->pageController->webEditMember('superproj', $idMember1, null, null, false);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		$resp = $this->pageController->webEditMember('superproj', $idMember2, null, null, false);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		// repeat
		$repeated = $this->projectService->cronRepeatBills($idBill2);
		$this->assertEquals(0, count($repeated));
		// enable users
		$resp = $this->pageController->webEditMember('superproj', $idMember1, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		$resp = $this->pageController->webEditMember('superproj', $idMember2, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));

		// yearly
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['yearly'], null,
			$idPm2, $idCat2, 0, '2021-03-10',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill, 'repeated bill should not be null');
		$this->assertEquals(
			Application::FREQUENCIES['no'],
			$repeatedBill['repeat'],
			'repeat should be "n" for the repeated bill, it is "' . $repeatedBill['repeat'] . '"'
		);

		$this->assertEquals(2, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// yearly freq 2
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['yearly'], null,
			$idPm2, $idCat2, 1, '2021-03-10',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(1, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// monthly
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['monthly'], null,
			$idPm2, $idCat2, 1, '2019-05-10',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(3, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// monthly freq 2
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['monthly'], null,
			$idPm2, $idCat2, 1, '2019-06-10',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(2, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// daily
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['daily'], null,
			$idPm2, $idCat2, 1, '2019-02-12',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(10, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// daily freq 2
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['daily'], null,
			$idPm2, $idCat2, 1, '2019-02-12',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(5, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// weekly
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['weekly'], null,
			$idPm2, $idCat2, 1, '2019-03-18',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(2, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// weekly freq 2
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['weekly'], null,
			$idPm2, $idCat2, 1, '2019-03-18',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(1, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// bi weekly
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['bi_weekly'], null,
			$idPm2, $idCat2, 1, '2019-04-03',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(2, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// semi monthly
		$resp = $this->pageController->webEditBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCIES['semi_monthly'], null,
			$idPm2, $idCat2, 1, '2019-04-14',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$repeated = $this->projectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCIES['no'], $repeatedBill['repeat']);

		$this->assertEquals(2, count($repeated));
		foreach ($repeated as $r) {
			$bill = $this->billMapper->getBill('superproj', $r['new_bill_id']);
			$this->assertNotNull($bill);
			$this->assertEquals('kangaroo', $bill['what']);
			$this->assertEquals($idMember2, $bill['payer_id']);
			$this->assertEquals($idCat2, $bill['categoryid']);
			$this->assertEquals($idPm2, $bill['paymentmodeid']);
			$this->assertEquals('newcom', $bill['comment']);
			$this->assertEquals(99, $bill['amount']);
			$this->projectService->deleteBill('superproj', $r['new_bill_id']);
		}

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
		$resp = $this->pageController->addUserShare('projtodel', 'test2', Application::ACCESS_LEVELS['maintainer']);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$shareId2 = $resp->getData()['id'];
		// already shared
		$resp = $this->pageController->addUserShare('projtodel', 'test2', Application::ACCESS_LEVELS['maintainer']);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));
		// non-existing user
		$resp = $this->pageController->addUserShare('projtodel', 'test2_doesnotexist', Application::ACCESS_LEVELS['maintainer']);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));

		// share the project with owner
		$resp = $this->pageController->addUserShare('projtodel', 'test');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));

		$resp = $this->projectService->addUserShare('projtodel', 'test', 'test2');
		$this->assertTrue(isset($resp['message']));
		$this->assertFalse(isset($resp['id']));

		// make someone having shared access share to someone else with higher access level
		// in this case, test2 shares to test3 with admin access
		$res = $this->projectService->addUserShare('projtodel', 'test3', 'test2', Application::ACCESS_LEVELS['admin']);
		$this->assertTrue(isset($res['message']));
		$this->assertFalse(isset($res['id']));
		// but with equal access level, it's fine
		$res = $this->projectService->addUserShare('projtodel', 'test3', 'test2', Application::ACCESS_LEVELS['maintainer']);
		$this->assertFalse(isset($res['message']));
		$this->assertTrue(isset($res['id']));

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

	public function testSearchBills() {
		$resp = $this->pageController->webCreateProject('superprojS', 'SuperProj', 'toto');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('superprojS', $data['id']);
		$resp = $this->pageController->webAddMember('superprojS', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];
		$resp = $this->pageController->webAddMember('superprojS', 'robert');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];

		// search bills
		$resp = $this->pageController->webAddBill(
			'superprojS', '2019-01-22', 'one', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, null, null,
			0, '2049-01-01', null, 'super comment 1'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBillSearch1 = $data;
		$resp = $this->pageController->webAddBill(
			'superprojS', '2019-01-22', 'two', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, null, null,
			0, '2049-01-01', null, 'ultra comment 2'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBillSearch2 = $data;
		$resp = $this->pageController->webAddBill(
			'superprojS', '2019-01-22', 'three', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, null, null,
			0, '2049-01-01', null, 'mega comment 3'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBillSearch3 = $data;

		$bills = $this->billMapper->searchBills('superprojS', 'mega');
		$this->assertEquals(1, count($bills));
		$this->assertEquals($idBillSearch3, $bills[0]['id']);
		$bills = $this->billMapper->searchBills('superprojS', 'two');
		$this->assertEquals(1, count($bills));
		$this->assertEquals($idBillSearch2, $bills[0]['id']);

		$resp = $this->pageController->webDeleteProject('superprojS');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
	}

	public function testgetNbBills() {
		$resp = $this->pageController->webCreateProject('superprojS', 'SuperProj', 'toto');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('superprojS', $data['id']);
		$resp = $this->pageController->webAddMember('superprojS', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];
		$resp = $this->pageController->webAddMember('superprojS', 'robert');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];
		$resp = $this->pageController->addCategory('superprojS', 'cat1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idCat1 = $resp->getData();
		$resp = $this->pageController->addPaymentMode('superprojS', 'pm1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idPm1 = $resp->getData();

		// search bills
		$resp = $this->pageController->webAddBill(
			'superprojS', '2019-01-22', 'one', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, $idPm1, $idCat1,
			0, '2049-01-01', null, 'super comment 1'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill1 = $data;
		$resp = $this->pageController->webAddBill(
			'superprojS', '2019-01-22', 'two', $idMember2,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, null, $idCat1,
			0, '2049-01-01', null, 'ultra comment 2'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill2 = $data;
		$resp = $this->pageController->webAddBill(
			'superprojS', '2019-01-22', 'three', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, null, null,
			0, '2049-01-01', null, 'mega comment 3'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill3 = $data;

		$nbBills = $this->projectService->getNbBills('superprojS', $idMember1);
		$this->assertEquals(2, $nbBills);
		$nbBills = $this->projectService->getNbBills('superprojS', $idMember2);
		$this->assertEquals(1, $nbBills);
		$nbBills = $this->projectService->getNbBills('superprojS', null, $idCat1);
		$this->assertEquals(2, $nbBills);
		$nbBills = $this->projectService->getNbBills('superprojS', null, null, $idPm1);
		$this->assertEquals(1, $nbBills);

		$resp = $this->pageController->webDeleteProject('superprojS');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
	}

	public function createAndPopulateProject($projectId) {
		$resp = $this->pageController->webCreateProject($projectId, 'SuperProj', 'toto');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals($projectId, $data['id']);
		$resp = $this->pageController->webAddMember($projectId, 'member1');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];
		$resp = $this->pageController->webAddMember($projectId, 'member2');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];
		$resp = $this->pageController->webAddMember($projectId, 'member3');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember3 = $data['id'];
		$resp = $this->pageController->webAddMember($projectId, 'member4');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idMember4 = $data['id'];
		$resp = $this->pageController->addCategory($projectId, 'cat1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idCat1 = $resp->getData();
		$resp = $this->pageController->addPaymentMode($projectId, 'pm1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$idPm1 = $resp->getData();

		// search bills
		$resp = $this->pageController->webAddBill(
			$projectId, '2019-01-22', 'one', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, $idPm1, $idCat1,
			0, '2049-01-01', null, 'super comment 1'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill1 = $data;
		$resp = $this->pageController->webAddBill(
			$projectId, '2019-01-22', 'two', $idMember2,
			$idMember1.','.$idMember3, 22.5, Application::FREQUENCIES['no'], null, null, $idCat1,
			0, '2049-01-01', null, 'ultra comment 2'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill2 = $data;
		$resp = $this->pageController->webAddBill(
			$projectId, '2019-01-22', 'three', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCIES['no'], null, null, null,
			0, '2049-01-01', null, 'mega comment 3'
		);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$idBill3 = $data;

		return $this->projectService->getProjectInfo($projectId);
	}

	public function testGetSettlement() {
		$project = $this->createAndPopulateProject('testGetSettlement');
		$member1 = $this->projectService->getMemberByName('testGetSettlement', 'member1');
		$idMember1 = $member1['id'];
		$member2 = $this->projectService->getMemberByName('testGetSettlement', 'member2');
		$idMember2 = $member2['id'];
		$member3 = $this->projectService->getMemberByName('testGetSettlement', 'member3');
		$idMember3 = $member3['id'];
		$member4 = $this->projectService->getMemberByName('testGetSettlement', 'member4');
		$idMember4 = $member4['id'];

		$resp = $this->pageController->webGetProjectSettlement('testGetSettlement', $idMember3);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$respData = $resp->getData();
		$data = $respData['transactions'];
		foreach ($data as $transaction) {
			$this->assertTrue($transaction['from'] === $idMember3 || $transaction['to'] === $idMember3);
		}

		// member who is not involved in any bill
		$resp = $this->pageController->webGetProjectSettlement('testGetSettlement', $idMember4);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$respData = $resp->getData();
		$data = $respData['transactions'];
		foreach ($data as $transaction) {
			$this->assertTrue($transaction['from'] === $idMember4 || $transaction['to'] === $idMember4);
		}

		$this->projectService->deleteProject('testGetSettlement');
	}

	public function testDeleteMember() {
		$projectId = 'tdm';
		$project = $this->createAndPopulateProject($projectId);
		$member1 = $this->projectService->getMemberByName($projectId, 'member1');
		$idMember1 = $member1['id'];
		$member2 = $this->projectService->getMemberByName($projectId, 'member2');
		$idMember2 = $member2['id'];
		$member3 = $this->projectService->getMemberByName($projectId, 'member3');
		$idMember3 = $member3['id'];
		$member4 = $this->projectService->getMemberByName($projectId, 'member4');
		$idMember4 = $member4['id'];

		$result = $this->projectService->deleteMember($projectId, $idMember1);
		$this->assertTrue(isset($result['success']));
		$member = $this->projectService->getMemberById($projectId, $idMember1);
		$this->assertNotNull($member);
		$this->assertFalse($member['activated']);

		$result = $this->projectService->deleteMember($projectId, $idMember4);
		$this->assertTrue(isset($result['success']));
		$this->assertNull($this->projectService->getMemberById($projectId, $idMember4));

		$result = $this->projectService->deleteMember($projectId, -1);
		$this->assertFalse(isset($result['success']));

		$this->projectService->deleteProject($projectId);
	}

	public function testShareLink() {
		$projectId = 'tsl';
		$project = $this->createAndPopulateProject($projectId);

		$result = $this->projectService->addPublicShare($projectId);
		$this->assertTrue(isset($result['token']));
		$this->assertTrue(isset($result['id']));
		$token = $result['token'];

		$projInfo = $this->projectService->getProjectInfoFromShareToken($token);
		$this->assertEquals($projectId, $projInfo['projectid']);

		$this->projectService->deleteProject($projectId);
	}

	public function testMoveBill() {
		$projectId = 'original';
		$toProjectId = 'newproject';
		$project = $this->createAndPopulateProject($projectId);
		$toProject = $this->createAndPopulateProject($toProjectId);

		// get the bills created for the first project
		$bills = $this->billMapper->getBills($projectId);

		// find the bill with payment method and category
		$bill = array_filter($bills, static function ($bill) {
			return $bill['paymentmodeid'] !== 0 && $bill['categoryid'] !== 0;
		});
		$bill = array_shift($bill);

		$resp = $this->pageController->webMoveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$respData = $resp->getData();

		// ensure they're not the same bill id
		$this->assertNotEquals($bill['id'], $respData);

		// bill moved, ensure the new bill has the right data in it
		$bill = $this->billMapper->getBill($toProjectId, $respData);

		$this->assertNotNull($bill);

		$destCategory = array_pop($toProject['categories']);
		$destPaymentMode = array_pop($toProject['paymentmodes']);

		$this->assertEquals($destCategory['id'], $bill['categoryid']);
		$this->assertEquals($destPaymentMode['id'], $bill['paymentmodeid']);

		// find the bill that does have a category but not a payment mode
		$bill = array_filter($bills, static function ($bill) {
			return $bill['paymentmodeid'] === 0 && $bill['categoryid'] !== 0;
		});
		$bill = array_shift($bill);

		// create a new payment mode
		$paymentMode = $this->projectService->addPaymentMode($projectId, 'new method', null, '#123123');
		// create a new category
		$category = $this->projectService->addCategory($projectId, 'new category', null, '#123123');
		// ensure it has a new payment mode and category that do not exist in destination
		$this->projectService->editBill(
			$projectId, $bill['id'], null, null, null, null,
			null, null, null, $paymentMode, $category, null
		);

		// finally move to the new project
		$resp = $this->pageController->webMoveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$respData = $resp->getData();

		$bill = $this->billMapper->getBill($toProjectId, $respData);

		$this->assertNotEquals($bill['paymentmodeid'], $paymentMode);
		$this->assertNotEquals($bill['categoryid'], $category);

		// get the bill that has no category and no payment mode
		$bill = array_filter($bills, static function ($bill) {
			return $bill['paymentmodeid'] === 0 && $bill['categoryid'] === 0;
		});
		$bill = array_shift($bill);

		// ensure the bill has multiple owerIds
		$this->assertEquals(2, count($bill['owerIds']));

		$originalMember = array_shift($project['members']);

		// re-create destination project so It's completely empty
		$this->projectService->deleteProject($toProjectId);
		$resp = $this->pageController->webCreateProject($toProjectId, 'SuperProj', 'toto');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		// try to move the bill to the new project
		$resp = $this->pageController->webMoveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(403, $status);

		// now create the member in the destination project and try again
		$newMemberId = $this->projectService->addMember($toProjectId, $originalMember['name']);

		// ensure no error happened when creating the new member
		$this->assertFalse(isset($newMemberId['error']));
		$this->assertEquals(2, count($bill['owerIds']));

		$resp = $this->pageController->webMoveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();

		// get the new bill and check the owerIds info too
		$bill = $this->billMapper->getBill($toProjectId, $data);
		$this->assertEquals(1, count($bill['owerIds']));
		// ensure payment mode and category are right too
		$this->assertEquals(0, $bill['paymentmodeid']);
		$this->assertEquals(0, $bill['categoryid']);

		$this->projectService->deleteProject($projectId);
		$this->projectService->deleteProject($toProjectId);
	}
};
