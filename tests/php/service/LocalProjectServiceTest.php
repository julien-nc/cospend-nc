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
namespace OCA\Cospend\Service;

use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Controller\ApiController;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\MemberMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;

use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\TestCase;

class LocalProjectServiceTest extends TestCase {

	private LocalProjectService $localProjectService;

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
		$appName = 'cospend';
		$request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();

		$app = new Application();
		$c = $app->getContainer();
		$sc = $c->get(IServerContainer::class);
		$this->billMapper = $c->get(BillMapper::class);
		$this->memberMapper = $c->get(MemberMapper::class);
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->apiController = new ApiController(
			$appName,
			$request,
			$c->get(IManager::class),
			$sc->getL10N($c->get('AppName')),
			$this->billMapper,
			$c->get(ProjectMapper::class),
			$this->localProjectService,
			$c->get(CospendService::class),
			$c->get(ActivityManager::class),
			$c->get(IRootFolder::class),
			'test'
		);

		$this->apiController2 = new ApiController(
			$appName,
			$request,
			$c->get(IManager::class),
			$sc->getL10N($c->get('AppName')),
			$this->billMapper,
			$c->get(ProjectMapper::class),
			$this->localProjectService,
			$c->get(CospendService::class),
			$c->get(ActivityManager::class),
			$c->get(IRootFolder::class),
			'test2'
		);

		$this->deleteTestProjects();
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

	protected function tearDown(): void	{
		$this->deleteTestProjects();
	}

	private function deleteTestProjects(): void {
		// in case there was a failure and something was not deleted
		$projIds = [
			'superproj',
			'projtodel',
			'original',
			'newproject',
			'superprojS',
		];
		foreach ($projIds as $projId) {
			try {
				$this->localProjectService->deleteProject($projId);
			} catch (\Throwable $t) {
			}
		}
	}

	public function testPage() {
		// CREATE PROJECT
		$result = $this->localProjectService->createProject('SuperProj', 'superproj', null, 'toto');
		$this->assertEquals('superproj', $result['id']);

		$this->expectException(\OCP\DB\Exception::class);
		$this->localProjectService->createProject('SuperProj', 'superproj', null, 'toto');

		$this->expectException(CospendBasicException::class);
		$resp = $this->localProjectService->createProject('super/proj', 'SuperProj', 'toto');

		// get project names
		$res = $this->localProjectService->getProjectNames(null);
		$this->assertEquals(0, count($res));

		// create members
		$member = $this->localProjectService->createMember('superproj', 'bobby', 1, true, '', null);
		$idMember1 = $member['id'];

		$member = $this->localProjectService->createMember('superproj', 'robert');
		$idMember2 = $member['id'];

		$resp = $this->apiController->createMember('superproj', 'robert3');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember3 = $data['id'];

		// get members
		$members = $this->localProjectService->getMembers('superproj', 'name', 0);
		$this->assertEquals(3, count($members));
		$members = $this->localProjectService->getMembers('superproj', 'name', 2147483646);
		$this->assertEquals(0, count($members));

		// already exists
		$res = $this->localProjectService->createMember('superproj', 'robert3');
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		// invalid name
		$res = $this->localProjectService->createMember('superproj', 'robert/4');
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		$res = $this->localProjectService->createMember('superproj', '');
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		// invalid weight
		$res = $this->localProjectService->createMember('superproj', 'robert4', 0.0);
		$this->assertTrue(isset($res['error']));
		$this->assertFalse(isset($res['id']));

		// delete the member
		$resp = $this->apiController->editMember('superproj', $idMember3, null, null, false);
		$this->assertNull($resp->getData());
		$this->assertNull($this->localProjectService->getMemberById('superproj', $idMember3));

		$resp = $this->apiController->createMember('superproj', 'robert4', 'test', 1.2, 0, '#123456');
		$status = $resp->getStatus();
		$data = $resp->getData();
		$this->assertEquals(Http::STATUS_OK, $status, json_encode($data));
		$idMember4 = $data['id'];

		$member = $this->localProjectService->getMemberByUserid('superproj', 'test');
		$this->assertNotNull($member);
		$this->assertTrue(isset($member['name']));
		$this->assertEquals('robert4', $member['name']);

		$this->localProjectService->editMember('superproj', $idMember4, null, null, null, null, '');
		$member = $this->localProjectService->getMemberByUserid('superproj', 'test');
		$this->assertNotNull($member['color']);

		// delete the member
		$this->localProjectService->deleteMember('superproj', $idMember4);
		$this->assertNull($this->localProjectService->getMemberById('superproj', $idMember4));

		try {
			$this->localProjectService->deleteMember('superproj', -1);
			$this->assertFalse(true);
		} catch (CospendBasicException $e) {
		}

		// create member with unauthorized user
		$resp = $this->apiController2->createMember('superproj', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// create member with invalid color
		$resp = $this->apiController->createMember('superproj', 'jojo', null, 1.2, 1, '#zz');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// add categories and payment modes
		$resp = $this->apiController->createCategory('superproj', 'cat1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idCat1 = $resp->getData();
		$resp = $this->apiController->createCategory('superproj', 'cat2', 'a', '#456789', 3);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idCat2 = $resp->getData();
		$resp = $this->apiController->createCategory('superproj', 'cat3', 'a', '#456789', 4);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idCat3 = $resp->getData();

		// delete category
		$this->localProjectService->deleteCategory('superproj', $idCat3);
		try {
			$cat3 = $this->localProjectService->getCategory('superproj', $idCat3);
			$this->assertFalse(true);
		} catch (CospendBasicException $e) {
		}

		try {
			$this->localProjectService->deleteCategory('superproj', -1);
			$this->assertFalse(true);
		} catch (DoesNotExistException $e) {
		}

		// check cat values
		$cat2 = $this->localProjectService->getCategory('superproj', $idCat2);
		$this->assertNotNull($cat2);
		$this->assertEquals('cat2', $cat2['name']);
		$this->assertEquals('a', $cat2['icon']);
		$this->assertEquals('#456789', $cat2['color']);

		$res = $this->localProjectService->editCategory('superproj', $idCat2, 'cat2_renamed', 'b', '#987654');
		$this->assertFalse(isset($res['message']));
		$res = $this->localProjectService->editCategory('superproj', $idCat2, '', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$res = $this->localProjectService->editCategory('superproj', -1, 'cat2_renamed', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$cat2 = $this->localProjectService->getCategory('superproj', $idCat2);
		$this->assertNotNull($cat2);
		$this->assertEquals('cat2_renamed', $cat2['name']);
		$this->assertEquals('b', $cat2['icon']);
		$this->assertEquals('#987654', $cat2['color']);

		$resp = $this->apiController->createPaymentMode('superproj', 'pm1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idPm1 = $resp->getData();
		$resp = $this->apiController->createPaymentMode('superproj', 'pm2', 'a', '#456789', 3);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idPm2 = $resp->getData();
		$resp = $this->apiController->createPaymentMode('superproj', 'pm3', 'a', '#456789', 4);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idPm3 = $resp->getData();

		// delete pm
		$this->localProjectService->deletePaymentMode('superproj', $idPm3);
		try {
			$pm3 = $this->localProjectService->getPaymentMode('superproj', $idPm3);
			$this->assertFalse(true);
		} catch (CospendBasicException $e) {
		}

		try {
			$this->localProjectService->deletePaymentMode('superproj', -1);
			$this->assertFalse(true);
		} catch (DoesNotExistException $e) {
		}

		// check pm values
		$pm2 = $this->localProjectService->getPaymentMode('superproj', $idPm2);
		$this->assertNotNull($pm2);
		$this->assertEquals('pm2', $pm2['name']);
		$this->assertEquals('a', $pm2['icon']);
		$this->assertEquals('#456789', $pm2['color']);

		$res = $this->localProjectService->editPaymentMode('superproj', $idPm2, 'pm2_renamed', 'b', '#987654');
		$this->assertFalse(isset($res['message']));
		$res = $this->localProjectService->editPaymentMode('superproj', $idPm2, '', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$res = $this->localProjectService->editPaymentMode('superproj', -1, 'pm2_renamed', 'b', '#987654');
		$this->assertTrue(isset($res['message']));
		$pm2 = $this->localProjectService->getPaymentMode('superproj', $idPm2);
		$this->assertNotNull($pm2);
		$this->assertEquals('pm2_renamed', $pm2['name']);
		$this->assertEquals('b', $pm2['icon']);
		$this->assertEquals('#987654', $pm2['color']);

		// create project with no contact email
		$result = $this->localProjectService->createProject('dummy proj', 'dummyproj', null, 'test');
		$this->assertTrue(isset($result['id']));
		$this->assertEquals('dummyproj', $result['id']);
		// delete this project
		$this->localProjectService->deleteProject('dummyproj');
		// delete unexisting project
		try {
			$this->localProjectService->deleteProject('dummyproj2');
			$this->assertFalse(true);
		} catch (CospendBasicException $e) {
		}

		// get members
		$resp = $this->apiController->getLocalProjects();
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		//var_dump($data);
		$this->assertEquals(1, count($data));
		$this->assertEquals(2, count($data[0]['members']));

		// get project info
		$resp = $this->apiController->getProjectInfo('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('superproj', $data['id']);
		$this->assertEquals('SuperProj', $data['name']);
		$this->assertEquals('test', $data['userid']);
		foreach ($data['balance'] as $mid => $balance) {
			$this->assertEquals(0, $balance);
		}
		foreach ($data['members'] as $mid => $memberInfo) {
			$this->assertTrue(in_array($memberInfo['name'], ['robert', 'bobby']));
		}

		// TODO find a way to register the user permission middleware
		//		$resp = $this->apiController->getProjectInfo('superprojdoesnotexist');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// edit member
		$resp = $this->apiController->editMember('superproj', $idMember1, 'roberto', 1.2, true, '#112233', 'test');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		$this->assertEquals('test', $data['userid']);
		$this->assertEquals('roberto', $data['name']);
		$this->assertEquals(1.2, $data['weight']);
		$this->assertTrue($data['activated']);
		$resp = $this->apiController->editMember('superproj', $idMember1, 'roberto', 1, true, '', '');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals(null, $data['userid']);

		//		$resp = $this->apiController->editMember('superprojdoesnotexist', $idMember1, 'roberto', 1, true);
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);
		//		$data = $resp->getData();
		//		$this->assertTrue(isset($data['message']));
		//		$this->assertFalse(isset($data['id']));

		// member does not exist
		$resp = $this->apiController->editMember('superproj', -1, 'roberto', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['name']));
		$this->assertFalse(isset($data['id']));

		// name the user like an existing user
		$resp = $this->apiController->editMember('superproj', $idMember1, 'robert', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['name']));
		$this->assertFalse(isset($data['id']));

		// invalid name
		$resp = $this->apiController->editMember('superproj', $idMember1, 'robert/invalid', 1, true);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['name']));
		$this->assertFalse(isset($data['id']));

		// invalid color
		$resp = $this->apiController->editMember('superproj', $idMember1, 'robertvalid', 1, true, '#zz');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['color']));
		$this->assertFalse(isset($data['id']));

		// invalid weight
		$resp = $this->apiController->editMember('superproj', $idMember1, 'robert3', 0, true);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['weight']));
		$this->assertFalse(isset($data['id']));

		// create bills
		$resp = $this->apiController->createBill(
			'superproj', '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, $idPm1, $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idBill1 = $data;

		// check bill values
		$bill = $this->billMapper->getBill('superproj', $idBill1);
		$this->assertNotNull($bill);
		$this->assertEquals('boomerang', $bill['what']);
		$this->assertEquals('2019-01-22', $bill['date']);
		$this->assertEquals($idMember1, $bill['payer_id']);
		$this->assertEquals(22.5, $bill['amount']);
		$this->assertEquals(Application::FREQUENCY_NO, $bill['repeat']);
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

		$resp = $this->apiController->createBill('superproj', '2019-01-25', 'agua', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idBill2 = $data;

		// with null data
		$resp = $this->apiController->createBill(
			'superproj', '2019-01-25', null, $idMember2, $idMember1, 12.3, 'n',
			null, null, null, 0, ''
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idBill3 = $data;

		$member2BillIds = $this->memberMapper->getBillIdsOfMember($idMember2);
		$this->assertTrue(in_array($idBill3, $member2BillIds));

		$this->localProjectService->deleteBill('superproj', $idBill3);

		// check payment mode old id is set when using one default payment mode
		// get a default payment mode
		$pms = $this->localProjectService->getCategoriesOrPaymentModes('superproj', false);
		$oneDefPm = null;
		foreach ($pms as $pm) {
			if (isset($pm['old_id']) && $pm['old_id'] !== null && $pm['old_id'] !== '') {
				$oneDefPm = $pm;
				break;
			}
		}
		$this->assertNotNull($oneDefPm);
		// add a bill with this payment mode
		$resp = $this->apiController->createBill(
			'superproj', '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, $oneDefPm['id'], $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
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
		$resp = $this->apiController->editBill(
			'superproj', $idBillPm, '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, $otherDefPm['id'], $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals($idBillPm, $data);

		$bill = $this->billMapper->getBill('superproj', $idBillPm);
		$this->assertNotNull($bill);
		$this->assertEquals($otherDefPm['old_id'], $bill['paymentmode']);
		$this->assertEquals($otherDefPm['id'], $bill['paymentmodeid']);

		$resp = $this->apiController->editBill(
			'superproj', $idBillPm, '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, $oneDefPm['old_id'], null, $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals($idBillPm, $data);
		$bill = $this->billMapper->getBill('superproj', $idBillPm);
		$this->assertNotNull($bill);
		$this->assertEquals($oneDefPm['old_id'], $bill['paymentmode']);
		$this->assertEquals($oneDefPm['id'], $bill['paymentmodeid']);
		$this->localProjectService->deleteBill('superproj', $idBillPm);

		// add bill with old pm id, it should affect the matching default pm
		$resp = $this->apiController->createBill(
			'superproj', '2019-01-22', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO,
			'c', null, $idCat1,
			0, '2049-01-01'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idBillOldPmId = $data;
		$bill = $this->billMapper->getBill('superproj', $idBillOldPmId);
		$this->assertNotNull($bill);
		$this->assertEquals('c', $bill['paymentmode']);
		$this->assertTrue(isset($bill['paymentmodeid']));
		$pmId = $bill['paymentmodeid'];
		$pm = $this->localProjectService->getPaymentMode('superproj', $pmId);
		$this->assertNotNull($pm);
		$this->assertEquals('c', $pm['old_id']);
		$this->localProjectService->deleteBill('superproj', $idBillOldPmId);

		// more invalid data
		$resp = $this->apiController->createBill(
			'superproj', '2019-01-25', null, null, $idMember1, 12.3, 'n',
			null, null, null, 0, '',
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->createBill(
			'superproj', '2019-01-25', null, $idMember2, $idMember1, null, 'n',
			null, null, null, 0, '',
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		//		$resp = $this->apiController->createBill('superprojdoesnotexist', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, 'n');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		$resp = $this->apiController->createBill('superproj', 'aa-aa', 'lala', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['date']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '2019-01-20', 'lala', -1, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['payer']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '2019-01-20', 'lala', $idMember2, -1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['payed_for']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '2019-01-20', 'lala', $idMember2, '', 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['payed_for']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, '');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['repeat']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1, 12.3, 'zzz');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['repeat']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '', 'lala', $idMember2, $idMember1, 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['message']));
		$this->assertFalse(isset($data['inserted_id']));

		$resp = $this->apiController->createBill('superproj', '2019-01-20', 'lala', $idMember2, $idMember1.',aa', 12.3, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['error'], $data['error']['payed_for']));
		$this->assertFalse(isset($data['inserted_id']));

		// get all bill ids
		$ids = $this->billMapper->getAllBillIds('superproj');
		$this->assertTrue(in_array($idBill1, $ids));

		// edit bill
		$resp = $this->apiController->editBill(
			'superproj', $idBill1, '2039-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_MONTHLY, null,
			$idPm2, $idCat2, 1, '2021-09-10',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// check bill values
		$bill = $this->billMapper->getBill('superproj', $idBill1);
		$this->assertNotNull($bill);
		$this->assertEquals('kangaroo', $bill['what']);
		$this->assertEquals('2039-02-02', $bill['date']);
		$this->assertEquals($idMember2, $bill['payer_id']);
		$this->assertEquals(99, $bill['amount']);
		$this->assertEquals(Application::FREQUENCY_MONTHLY, $bill['repeat']);
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
		$this->localProjectService->editProject(
			'superproj', 'proj', null,
			null, null, null,
			Application::SORT_ORDER_MOST_USED, Application::SORT_ORDER_MOST_USED
		);
		// check categories/pm
		$cats = $this->localProjectService->getCategoriesOrPaymentModes('superproj');
		$this->assertTrue(count($cats) === count($this->localProjectService->defaultCategories) + 2);
		$this->assertEquals(0, $cats[$idCat2]['order'], 'order of cat2 should be 0 but is ' . $cats[$idCat2]['order']);
		$pms = $this->localProjectService->getCategoriesOrPaymentModes('superproj', false);
		$this->assertTrue(count($pms) === count($this->localProjectService->defaultPaymentModes) + 2);
		$this->assertEquals(0, $pms[$idPm2]['order'], 'order of pm2 should be 0 but is ' . $pms[$idPm2]['order']);

		// set cat/pm order
		$this->localProjectService->editProject(
			'superproj', 'proj', null,
			null, null, null,
			Application::SORT_ORDER_RECENTLY_USED, Application::SORT_ORDER_RECENTLY_USED
		);
		// check categories/pm
		$cats = $this->localProjectService->getCategoriesOrPaymentModes('superproj');
		$this->assertEquals(count($this->localProjectService->defaultCategories) + 2, count($cats));
		$this->assertEquals(0, $cats[$idCat2]['order']);
		$pms = $this->localProjectService->getCategoriesOrPaymentModes('superproj', false);
		$this->assertEquals(count($this->localProjectService->defaultPaymentModes) + 2, count($pms));
		$this->assertEquals(0, $pms[$idPm2]['order']);

		$resp = $this->apiController->editBill(
			'superproj', $idBill1, null, 'boomerang', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_MONTHLY, null,
			null, null, 1, '',
			123456789, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// check bill values
		$bill = $this->billMapper->getBill('superproj', $idBill1);
		$this->assertNotNull($bill);
		$this->assertEquals(123456789, $bill['timestamp']);

		//		$resp = $this->apiController->editBill('superprojdoesnotexist', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		$resp = $this->apiController->editBill('superproj', -1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->editBill(
			'superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_MONTHLY . 'wrong_value', null,
			null, null, null, null,
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->editBill('superproj', $idBill1, '2019-01-20', '', $idMember1, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$resp = $this->apiController->editBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, '');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// invalid date
		$resp = $this->apiController->editBill('superproj', $idBill1, 'aaa', 'boomerang', $idMember1, $idMember1.','.$idMember2, 99, '');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->editBill('superproj', $idBill1, '2019-01-20', 'boomerang', 0, $idMember1.','.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->editBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, '0,'.$idMember2, 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->editBill('superproj', $idBill1, '2019-01-20', 'boomerang', $idMember1, 'aa', 99, 'n');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// currencies
		$this->localProjectService->editProject('superproj', 'SuperProj', null, null, 'euro');
		$currencyId = $this->localProjectService->createCurrency('superproj', 'dollar', 1.5);
		$this->assertTrue($currencyId > 0);

		$currencyId2 = $this->localProjectService->createCurrency('superproj', 'dollar2', 1.5);
		$this->assertTrue($currencyId2 > 0);
		$res = $this->localProjectService->editCurrency('superproj', $currencyId2, 'dolrenamed', 2);
		$this->assertFalse(isset($res['message']));
		$this->assertEquals('dolrenamed', $res['name']);
		$this->assertEquals(2, $res['exchange_rate']);
		$this->assertEquals($currencyId2, $res['id']);
		try {
			$res = $this->localProjectService->editCurrency('superproj', $currencyId2, '', 0);
			$this->assertFalse(true);
		} catch (CospendBasicException) {
		}
		try {
		$res = $this->localProjectService->editCurrency('superproj', -1, 'dolrenamed', 2);
			$this->assertFalse(true);
		} catch (CospendBasicException) {
		}
		$this->localProjectService->deleteCurrency('superproj', $currencyId2);
		try {
			$this->localProjectService->deleteCurrency('superproj', -1);
			$this->assertFalse(true);
		} catch (CospendBasicException) {
		}

		// share link
		$res = $this->localProjectService->createPublicShare('superproj');
		$this->assertTrue(isset($res['token'], $res['id']));
		$this->assertTrue($res['id'] > 0);
		$shareLinkId = $res['id'];
		$shareLinkToken = $res['token'];
		$res = $this->localProjectService->createPublicShare('superproj');
		$this->assertTrue(isset($res['id'], $res['token']));
		$this->assertTrue($res['id'] > 0);
		$shareLinkId2 = $res['id'];
		$res = $this->localProjectService->deletePublicShare('superproj', $shareLinkId2);
		$this->assertTrue(isset($res['success']));
		$res = $this->localProjectService->deletePublicShare('superproj', -1);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));

		$res = $this->localProjectService->editShareAccess('superproj', $shareLinkId, 'lala', 'passpass');
		$this->assertTrue(isset($res['success']));
		$this->assertFalse(isset($res['message']));
		$res = $this->localProjectService->editShareAccessLevel('superproj', $shareLinkId, Application::ACCESS_LEVEL_ADMIN);
		$this->assertTrue(isset($res['success']));
		$this->assertFalse(isset($res['message']));
		$res = $this->localProjectService->editShareAccess('superproj', -1, 'lala', 'passpass');
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));
		$res = $this->localProjectService->editShareAccessLevel('superproj', -1, Application::ACCESS_LEVEL_ADMIN);
		$this->assertFalse(isset($res['success']));
		$this->assertTrue(isset($res['message']));
		$res = $this->localProjectService->getPublicShares('superproj');
		$this->assertEquals(1, count($res));
		$this->assertEquals($shareLinkToken, $res[0]['token']);
		$this->assertEquals('lala', $res[0]['label']);
		$this->assertEquals('passpass', $res[0]['password']);
		$this->assertEquals(Application::ACCESS_LEVEL_ADMIN, $res[0]['accesslevel']);
		$this->assertEquals($shareLinkId, $res[0]['id']);

		// get project stats

		//		$resp = $this->apiController->getProjectstatistics('superprojdoesnotexist');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		$resp = $this->apiController->getProjectstatistics('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$stats = $data['stats'];
		// check member stats
		$id1Found = false;
		$id2Found = false;
		foreach ($stats as $stat) {
			if ($stat['member']['id'] === $idMember1) {
				$this->assertEquals((99 / 2 - 12.3), $stat['balance']);
				$this->assertEquals(99, $stat['paid']);
				$this->assertEquals((99 / 2 + 12.3), $stat['spent']);
				$id1Found = true;
			} elseif ($stat['member']['id'] === $idMember2) {
				$this->assertEquals((12.3 - 99 / 2), $stat['balance']);
				$this->assertEquals(12.3, $stat['paid']);
				$this->assertEquals(99 / 2, $stat['spent']);
				$id2Found = true;
			}
		}
		$this->assertTrue($id1Found);
		$this->assertTrue($id2Found);

		// stats with currency
		$resp = $this->apiController->getProjectstatistics(
			'superproj', null, null, null, null,
			null, null, '1', $currencyId
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// get project settlement plan

		//		$resp = $this->apiController->getProjectsettlement('superprojdoesnotexist');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		$resp = $this->apiController->getProjectsettlement('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$respData = $resp->getData();
		$data = $respData['transactions'];
		$id1Found = false;
		foreach ($data as $transaction) {
			if ($transaction['from'] === $idMember2 && $transaction['to'] === $idMember1) {
				$this->assertEquals((99 / 2 - 12.3), $transaction['amount']);
				$id1Found = true;
			}
		}
		$this->assertTrue($id1Found);

		// auto settlement
		//		$resp = $this->apiController->autoSettlement('superprojdoesnotexist');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		$resp = $this->apiController->autoSettlement('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data);

		// check balances are back to zero
		$resp = $this->apiController->getProjectstatistics('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$stats = $data['stats'];
		// check member stats
		$id1Found = false;
		$id2Found = false;
		foreach ($stats as $stat) {
			if ($stat['member']['id'] === $idMember1) {
				$this->assertEquals(0, $stat['balance']);
				$this->assertEquals(99, $stat['paid']);
				$this->assertEquals((99 / 2 + 12.3) + (99 / 2 - 12.3), $stat['spent']);
				$id1Found = true;
			} elseif ($stat['member']['id'] === $idMember2) {
				$this->assertEquals(0, $stat['balance']);
				$this->assertEquals(12.3 + (99 / 2 - 12.3), $stat['paid']);
				$this->assertEquals(99 / 2, $stat['spent']);
				$id2Found = true;
			}
		}
		$this->assertTrue($id1Found);
		$this->assertTrue($id2Found);

		// check number of bills
		$resp = $this->apiController->getBills('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$nbBills = count($data['bills']);
		$this->assertTrue($nbBills > 0);

		// get bills with limit
		$resp = $this->apiController->getBills('superproj', null, null, $nbBills - 1);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$limitedNbBills = count($data['bills']);
		$this->assertTrue($limitedNbBills < $nbBills);
		$this->assertEquals($nbBills - 1, $limitedNbBills);

		// DELETE BILL
		$resp = $this->apiController->deleteBill('superproj', $idBill1);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data);

		// delete bill that does not exist
		$resp = $this->apiController->deleteBill('superproj', -1);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_NOT_FOUND, $status);

		// DELETE BILL of unexisting project
		//		$resp = $this->apiController->deleteBill('superprojLALA', $idBill1);
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// delete bill when deletion is disabled
		$resp = $this->apiController->editProject(
			'superproj', null, null,
			null, true,
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// try to delete a bill
		$resp = $this->apiController->deleteBill('superproj', $idBill1);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_FORBIDDEN, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data);
		// reset bill deletion in project
		$resp = $this->apiController->editProject(
			'superproj', null, null,
			null, false,
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// check number of bills again
		$resp = $this->apiController->getBills('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$nbBills2 = count($data['bills']);
		$this->assertEquals($nbBills - 1, $nbBills2);

		//		$resp = $this->apiController->getBills('superprojLALA');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// EDIT PROJECT
		$resp = $this->apiController->editProject(
			'superproj', 'newname',
			Application::FREQUENCY_MONTHLY, '', false,
			Application::SORT_ORDER_MANUAL, Application::SORT_ORDER_MANUAL
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data);

		// invalid email
		$resp = $this->apiController->editProject('superproj', 'newname', 'invalid email!');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// user can't edit this project (test is not the owner of superprojLALA)
		//		$resp = $this->apiController->editProject('superprojLALA', 'newname');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// project does not exist
		//		$resp = $this->apiController->editProject('doesnotexit', 'newname');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		try {
			$this->localProjectService->editProject('blabla', 'plop');
			$this->assertFalse(true);
		} catch (CospendBasicException) {
		}

		// invalid name
		$resp = $this->apiController->editProject('superproj', '');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// invalid category sort
		$resp = $this->apiController->editProject(
			'superproj', 'newname',
			Application::FREQUENCY_MONTHLY, 'euro', null,
			'zzz', Application::SORT_ORDER_MANUAL
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// invalid payment mode sort
		$resp = $this->apiController->editProject(
			'superproj', 'newname',
			Application::FREQUENCY_MONTHLY, 'euro', null,
			Application::SORT_ORDER_MANUAL, 'zzz'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// invalid auto export frequency
		$resp = $this->apiController->editProject(
			'superproj', 'newname',
			'zzz', 'euro', null,
			Application::SORT_ORDER_MANUAL, Application::SORT_ORDER_MANUAL
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// repeat bills
		// bill with no enabled owers
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_YEARLY, null,
			$idPm2, $idCat2, 0, '2021-03-10',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// disable users
		$resp = $this->apiController->editMember('superproj', $idMember1, null, null, false);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		$resp = $this->apiController->editMember('superproj', $idMember2, null, null, false);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		// repeat
		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		$this->assertEquals(0, count($repeated));
		// enable users
		$resp = $this->apiController->editMember('superproj', $idMember1, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));
		$resp = $this->apiController->editMember('superproj', $idMember2, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertFalse(isset($data['message']));
		$this->assertTrue(isset($data['id']));

		// yearly
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_YEARLY, null,
			$idPm2, $idCat2, 0, '2021-03-10',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill, 'repeated bill should not be null');
		$this->assertEquals(
			Application::FREQUENCY_NO,
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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// yearly freq 2
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_YEARLY, null,
			$idPm2, $idCat2, 1, '2021-03-10',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// monthly
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_MONTHLY, null,
			$idPm2, $idCat2, 1, '2019-05-10',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// monthly freq 2
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_MONTHLY, null,
			$idPm2, $idCat2, 1, '2019-06-10',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// daily
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_DAILY, null,
			$idPm2, $idCat2, 1, '2019-02-12',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// daily freq 2
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-02-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_DAILY, null,
			$idPm2, $idCat2, 1, '2019-02-12',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// weekly
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_WEEKLY, null,
			$idPm2, $idCat2, 1, '2019-03-18',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// weekly freq 2
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_WEEKLY, null,
			$idPm2, $idCat2, 1, '2019-03-18',
			null, 'newcom', 2
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// bi weekly
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_BI_WEEKLY, null,
			$idPm2, $idCat2, 1, '2019-04-03',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// semi monthly
		$resp = $this->apiController->editBill(
			'superproj', $idBill2, '2019-03-02', 'kangaroo', $idMember2,
			$idMember1.','.$idMember2, 99, Application::FREQUENCY_SEMI_MONTHLY, null,
			$idPm2, $idCat2, 1, '2019-04-14',
			null, 'newcom', 1
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$repeated = $this->localProjectService->cronRepeatBills($idBill2);
		// check repeated bill repeat value
		$repeatedBill = $this->billMapper->getBill('superproj', $idBill2);
		$this->assertNotNull($repeatedBill);
		$this->assertEquals(Application::FREQUENCY_NO, $repeatedBill['repeat']);

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
			$this->localProjectService->deleteBill('superproj', $r['new_bill_id']);
		}

		// DELETE PROJECT
		$resp = $this->apiController->deleteProject('superproj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data['message'] ?? '');

		// DELETE PROJECT which does not exist
		//		$resp = $this->apiController->deleteProject('superprojdontexist');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// CREATE PROJECT to delete
		$resp = $this->apiController->createProject('projtodel', 'ProjToDel', 'weakpasswd');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('projtodel', $data['id']);

		// attempt to delete : wrong user
		//		$resp = $this->apiController2->deleteProject('projtodel');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// share the project with second user
		$resp = $this->apiController->createUserShare('projtodel', 'test2', Application::ACCESS_LEVEL_MAINTAINER);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$shareId2 = $resp->getData()['id'];
		// already shared
		$resp = $this->apiController->createUserShare('projtodel', 'test2', Application::ACCESS_LEVEL_MAINTAINER);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));
		// non-existing user
		$resp = $this->apiController->createUserShare('projtodel', 'test2_doesnotexist', Application::ACCESS_LEVEL_MAINTAINER);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));

		// share the project with owner
		$resp = $this->apiController->createUserShare('projtodel', 'test');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);
		$data = $resp->getData();
		$this->assertTrue(isset($data['message']));
		$this->assertFalse(isset($data['id']));

		$resp = $this->localProjectService->createUserShare('projtodel', 'test', 'test2');
		$this->assertTrue(isset($resp['message']));
		$this->assertFalse(isset($resp['id']));

		// make someone having shared access share to someone else with higher access level
		// in this case, test2 shares to test3 with admin access
		$res = $this->localProjectService->createUserShare('projtodel', 'test3', 'test2', Application::ACCESS_LEVEL_ADMIN);
		$this->assertTrue(isset($res['message']));
		$this->assertFalse(isset($res['id']));
		// but with equal access level, it's fine
		$res = $this->localProjectService->createUserShare('projtodel', 'test3', 'test2', Application::ACCESS_LEVEL_MAINTAINER);
		$this->assertFalse(isset($res['message']));
		$this->assertTrue(isset($res['id']));

		// get projects of second user
		$resp = $this->apiController2->getLocalProjects();
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals(1, count($data));
		$this->assertEquals('projtodel', $data[0]['id']);

		$resp = $this->apiController2->getProjectInfo('projtodel');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// delete the user share
		$resp = $this->apiController->deleteUserShare('projtodel', $shareId2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		//		$resp = $this->apiController2->getProjectInfo('projtodel');
		//		$status = $resp->getStatus();
		//		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $status);

		// get projects of second user to check if access to project was removed
		$resp = $this->apiController2->getLocalProjects();
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals(0, count($data));

		// add a group share
		$resp = $this->apiController->createGroupShare('projtodel', 'group2test');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$groupShareId = $resp->getData()['id'];

		$resp = $this->apiController->createGroupShare('projtodel', 'group2test');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		$resp = $this->apiController->createGroupShare('projtodel', 'group2testLALA');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// get projects of second user to see if access to shared project is possible
		$resp = $this->apiController2->getLocalProjects();
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals(1, count($data));
		$this->assertEquals('projtodel', $data[0]['id']);

		$resp = $this->apiController2->getProjectInfo('projtodel');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// delete the group share
		$resp = $this->apiController->deleteGroupShare('projtodel', $groupShareId);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		$resp = $this->apiController->deleteGroupShare('projtodel', -7777);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// then it should be ok to delete
		$resp = $this->apiController->deleteProject('projtodel');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data['message'] ?? '');
	}

	public function testSearchBills() {
		$resp = $this->apiController->createProject('superprojS', 'SuperProj');
		echo 'CRPRO '.json_encode($resp->getData());
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('superprojS', $data['id']);
		$resp = $this->apiController->createMember('superprojS', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];
		$resp = $this->apiController->createMember('superprojS', 'robert');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];

		// search bills
		$resp = $this->apiController->createBill(
			'superprojS', '2019-01-22', 'one', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, null, null,
			0, '2049-01-01', null, 'super comment 1'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBillSearch1 = $data;
		$resp = $this->apiController->createBill(
			'superprojS', '2019-01-22', 'two', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, null, null,
			0, '2049-01-01', null, 'ultra comment 2'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idBillSearch2 = $data;
		$resp = $this->apiController->createBill(
			'superprojS', '2019-01-22', 'three', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, null, null,
			0, '2049-01-01', null, 'mega comment 3'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idBillSearch3 = $data;

		$bills = $this->billMapper->searchBills('superprojS', 'mega');
		$this->assertEquals(1, count($bills));
		$this->assertEquals($idBillSearch3, $bills[0]['id']);
		$bills = $this->billMapper->searchBills('superprojS', 'two');
		$this->assertEquals(1, count($bills));
		$this->assertEquals($idBillSearch2, $bills[0]['id']);

		$resp = $this->apiController->deleteProject('superprojS');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
	}

	public function testgetNbBills() {
		$resp = $this->apiController->createProject('superprojS', 'SuperProj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals('superprojS', $data['id']);
		$resp = $this->apiController->createMember('superprojS', 'bobby');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];
		$resp = $this->apiController->createMember('superprojS', 'robert');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];
		$resp = $this->apiController->createCategory('superprojS', 'cat1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idCat1 = $resp->getData();
		$resp = $this->apiController->createPaymentMode('superprojS', 'pm1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idPm1 = $resp->getData();

		// search bills
		$resp = $this->apiController->createBill(
			'superprojS', '2019-01-22', 'one', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, $idPm1, $idCat1,
			0, '2049-01-01', null, 'super comment 1'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBill1 = $data;
		$resp = $this->apiController->createBill(
			'superprojS', '2019-01-22', 'two', $idMember2,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, null, $idCat1,
			0, '2049-01-01', null, 'ultra comment 2'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBill2 = $data;
		$resp = $this->apiController->createBill(
			'superprojS', '2019-01-22', 'three', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, null, null,
			0, '2049-01-01', null, 'mega comment 3'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBill3 = $data;

		$nbBills = $this->billMapper->countBills('superprojS', $idMember1);
		$this->assertEquals(2, $nbBills);
		$nbBills = $this->billMapper->countBills('superprojS', $idMember2);
		$this->assertEquals(1, $nbBills);
		$nbBills = $this->billMapper->countBills('superprojS', null, $idCat1);
		$this->assertEquals(2, $nbBills);
		$nbBills = $this->billMapper->countBills('superprojS', null, null, $idPm1);
		$this->assertEquals(1, $nbBills);

		$resp = $this->apiController->deleteProject('superprojS');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
	}

	public function createAndPopulateProject($projectId): ?array {
		$resp = $this->apiController->createProject($projectId, 'SuperProj', 'toto');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals($projectId, $data['id']);
		$resp = $this->apiController->createMember($projectId, 'member1');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember1 = $data['id'];
		$resp = $this->apiController->createMember($projectId, 'member2');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember2 = $data['id'];
		$resp = $this->apiController->createMember($projectId, 'member3');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$idMember3 = $data['id'];
		$resp = $this->apiController->createMember($projectId, 'member4');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idMember4 = $data['id'];
		$resp = $this->apiController->createCategory($projectId, 'cat1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idCat1 = $resp->getData();
		$resp = $this->apiController->createPaymentMode($projectId, 'pm1', 'i', '#123465', 2);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$idPm1 = $resp->getData();

		// search bills
		$resp = $this->apiController->createBill(
			$projectId, '2019-01-22', 'one', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, $idPm1, $idCat1,
			0, '2049-01-01', null, 'super comment 1'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBill1 = $data;
		$resp = $this->apiController->createBill(
			$projectId, '2019-01-22', 'two', $idMember2,
			$idMember1.','.$idMember3, 22.5, Application::FREQUENCY_NO, null, null, $idCat1,
			0, '2049-01-01', null, 'ultra comment 2'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBill2 = $data;
		$resp = $this->apiController->createBill(
			$projectId, '2019-01-22', 'three', $idMember1,
			$idMember1.','.$idMember2, 22.5, Application::FREQUENCY_NO, null, null, null,
			0, '2049-01-01', null, 'mega comment 3'
		);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		// $data = $resp->getData();
		// $idBill3 = $data;

		return $this->localProjectService->getProjectInfo($projectId);
	}

	public function testGetSettlement() {
		$this->createAndPopulateProject('testGetSettlement');
		$this->localProjectService->getMemberByName('testGetSettlement', 'member1');
		// $idMember1 = $member1['id'];
		$this->localProjectService->getMemberByName('testGetSettlement', 'member2');
		// $idMember2 = $member2['id'];
		$member3 = $this->localProjectService->getMemberByName('testGetSettlement', 'member3');
		$idMember3 = $member3['id'];
		$member4 = $this->localProjectService->getMemberByName('testGetSettlement', 'member4');
		$idMember4 = $member4['id'];

		$resp = $this->apiController->getProjectsettlement('testGetSettlement', $idMember3);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$respData = $resp->getData();
		$data = $respData['transactions'];
		foreach ($data as $transaction) {
			$this->assertTrue($transaction['from'] === $idMember3 || $transaction['to'] === $idMember3);
		}

		// member who is not involved in any bill
		$resp = $this->apiController->getProjectsettlement('testGetSettlement', $idMember4);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$respData = $resp->getData();
		$data = $respData['transactions'];
		foreach ($data as $transaction) {
			$this->assertTrue($transaction['from'] === $idMember4 || $transaction['to'] === $idMember4);
		}

		$this->localProjectService->deleteProject('testGetSettlement');
	}

	public function testDeleteMember() {
		$projectId = 'tdm';
		$this->createAndPopulateProject($projectId);
		$member1 = $this->localProjectService->getMemberByName($projectId, 'member1');
		$idMember1 = $member1['id'];
		$this->localProjectService->getMemberByName($projectId, 'member2');
		// $idMember2 = $member2['id'];
		$this->localProjectService->getMemberByName($projectId, 'member3');
		// $idMember3 = $member3['id'];
		$member4 = $this->localProjectService->getMemberByName($projectId, 'member4');
		$idMember4 = $member4['id'];

		$this->localProjectService->deleteMember($projectId, $idMember1);
		$member = $this->localProjectService->getMemberById($projectId, $idMember1);
		$this->assertNotNull($member);
		$this->assertFalse($member['activated']);

		$this->localProjectService->deleteMember($projectId, $idMember4);
		$this->assertNull($this->localProjectService->getMemberById($projectId, $idMember4));

		try {
			$this->localProjectService->deleteMember($projectId, -1);
			$this->assertFalse(true);
		} catch (CospendBasicException) {
		}

		$this->localProjectService->deleteProject($projectId);
	}

	public function testShareLink() {
		$projectId = 'tsl';
		$this->createAndPopulateProject($projectId);

		$result = $this->localProjectService->createPublicShare($projectId);
		$this->assertTrue(isset($result['token']));
		$this->assertTrue(isset($result['id']));
		$token = $result['token'];

		$projInfo = $this->localProjectService->getLinkShareInfoFromShareToken($token);
		$this->assertEquals($projectId, $projInfo['projectid']);

		$this->localProjectService->deleteProject($projectId);
	}

	public function testMoveBill() {
		$projectId = 'original';
		$toProjectId = 'newproject';
		$project = $this->createAndPopulateProject($projectId);
		$toProject = $this->createAndPopulateProject($toProjectId);

		// get the bills created for the first project
		$bills = $this->billMapper->getBillsClassic($projectId);

		// find the bill with payment method and category
		$bill = array_filter($bills, static function ($bill) {
			return $bill['paymentmodeid'] !== 0 && $bill['categoryid'] !== 0;
		});
		$bill = array_shift($bill);

		$resp = $this->apiController->moveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
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
		$paymentModeId = $this->localProjectService->createPaymentMode($projectId, 'new method', null, '#123123');
		// create a new category
		$category = $this->localProjectService->createCategory($projectId, 'new category', null, '#123123');
		// ensure it has a new payment mode and category that do not exist in destination
		$this->localProjectService->editBill(
			$projectId, $bill['id'], null, null, null, null,
			null, null, null, $paymentModeId, $category
		);

		// finally move to the new project
		$resp = $this->apiController->moveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$respData = $resp->getData();

		$bill = $this->billMapper->getBill($toProjectId, $respData);

		$this->assertNotEquals($bill['paymentmodeid'], $paymentModeId);
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
		$this->localProjectService->deleteProject($toProjectId);
		$resp = $this->apiController->createProject($toProjectId, 'SuperProj', 'toto');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);

		// try to move the bill to the new project
		$resp = $this->apiController->moveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $status);

		// now create the member in the destination project and try again
		$newMemberId = $this->localProjectService->createMember($toProjectId, $originalMember['name']);

		// ensure no error happened when creating the new member
		$this->assertFalse(isset($newMemberId['error']));
		$this->assertEquals(2, count($bill['owerIds']));

		$resp = $this->apiController->moveBill($projectId, $bill['id'], $toProjectId);
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();

		// get the new bill and check the owerIds info too
		$bill = $this->billMapper->getBill($toProjectId, $data);
		$this->assertEquals(1, count($bill['owerIds']));
		// ensure payment mode and category are right too
		$this->assertEquals(0, $bill['paymentmodeid']);
		$this->assertEquals(0, $bill['categoryid']);

		$this->localProjectService->deleteProject($projectId);
		$this->localProjectService->deleteProject($toProjectId);
	}
}
