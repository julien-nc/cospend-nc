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
namespace OCA\Cospend;

use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Controller\ApiController;
use OCA\Cospend\Controller\PublicApiController;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\MemberMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\ShareMapper;
use OCA\Cospend\Exception\CospendPublicAuthNotValidException;
use OCA\Cospend\Exception\CospendUserPermissionsException;
use OCA\Cospend\Middleware\PublicAuthMiddleware;
use OCA\Cospend\Middleware\UserPermissionMiddleware;
use OCA\Cospend\Service\CospendService;
use OCA\Cospend\Service\LocalProjectService;
use OCA\Cospend\Service\UserService;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;

use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MiddlewaresTest extends TestCase {

	private ApiController $apiController;
	private ApiController $apiController2;
	private IRequest|\PHPUnit\Framework\MockObject\MockObject $request;
	private ProjectMapper $projectMapper;
	private UserPermissionMiddleware $userPermissionMiddleware;
	private PublicAuthMiddleware $publicAuthMiddleware;
	private BillMapper $billMapper;
	private LocalProjectService $projectService;
	private PublicApiController $publicApiController;

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

		// CREATE DUMMY USERS
		$u1 = $userManager->createUser('test', 'T0T0T0');
		$u1->setEMailAddress('toto@toto.net');
		$u2 = $userManager->createUser('test2', 'T0T0T0');
		$groupManager = $c->get(IGroupManager::class);
		$groupManager->createGroup('group1test');
		$groupManager->get('group1test')->addUser($u1);
		$groupManager->createGroup('group2test');
		$groupManager->get('group2test')->addUser($u2);
	}

	protected function setUp(): void {
		$appName = 'cospend';
		$this->request = $this->createMock(IRequest::class);

		$app = new Application();
		$c = $app->getContainer();
		$sc = $c->get(IServerContainer::class);
		$l10n = $c->get(IL10N::class);
		$this->billMapper = $c->get(BillMapper::class);
		$this->memberMapper = $c->get(MemberMapper::class);
		$this->projectMapper = $c->get(ProjectMapper::class);

		$activityManager = new ActivityManager(
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

		$activityManager2 = new ActivityManager(
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

		$this->projectService = $c->get(LocalProjectService::class);
		$this->cospendService = $c->get(CospendService::class);
		$this->shareMapper = $c->get(ShareMapper::class);

		$this->apiController = new ApiController(
			$appName,
			$this->request,
			$c->get(IShareManager::class),
			$sc->getL10N($c->get('AppName')),
			$this->billMapper,
			$this->projectMapper,
			$this->projectService,
			$this->cospendService,
			$activityManager,
			$c->get(IRootFolder::class),
			'test'
		);

		$this->apiController2 = new ApiController(
			$appName,
			$this->request,
			$c->get(IShareManager::class),
			$sc->getL10N($c->get('AppName')),
			$this->billMapper,
			$this->projectMapper,
			$this->projectService,
			$this->cospendService,
			$activityManager2,
			$c->get(IRootFolder::class),
			'test2'
		);

		$this->publicApiController = new PublicApiController(
			$appName,
			$this->request,
			$l10n,
			$this->billMapper,
			$this->shareMapper,
			$this->projectService,
			$activityManager,
		);

		$this->userPermissionMiddleware = new UserPermissionMiddleware(
			$this->projectService,
			$this->request,
			$c->get(IL10N::class),
			$c->get(LoggerInterface::class),
		);

		$this->publicAuthMiddleware = new PublicAuthMiddleware(
			$c->get(ShareMapper::class),
			$this->request,
			$c->get(IL10N::class),
			$c->get(LoggerInterface::class),
			$c->get(IConfig::class),
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
		$groupManager = $c->get(IGroupManager::class);
		$groupManager->get('group1test')->delete();
		$groupManager->get('group2test')->delete();
	}

	protected function tearDown(): void {
		// in case there was a failure and something was not deleted
		$this->apiController->deleteProject('superproj');
		$this->apiController->deleteProject('projtodel');
		$this->apiController->deleteProject('original');
		$this->apiController->deleteProject('newproject');
	}

	public function testUserPermission() {
		$projectId = 'superproj';
		// CREATE PROJECT owned by test
		$resp = $this->apiController->createProject($projectId, 'SuperProj');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals($projectId, $data['id']);

		$this->request
			->expects($this->any())
			->method('getParam')
			->with('projectId')
			->willReturn($projectId);

		// owner
		$this->userPermissionMiddleware->beforeController($this->apiController, 'getProjectInfo');
		// other user with no access
		try {
			$this->userPermissionMiddleware->beforeController($this->apiController2, 'getProjectInfo');
			$this->assertTrue(false, 'Permission check should fail with apiController2');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendUserPermissionsException::class, $e);
		}

		// other user
		$resp = $this->apiController->createUserShare($projectId, 'test2', Application::ACCESS_LEVEL_PARTICIPANT);
		$shareId = $resp->getData()['id'];
		// other user with enough access level
		$this->userPermissionMiddleware->beforeController($this->apiController2, 'getProjectInfo');
		// other user with insufficient access level
		try {
			$this->userPermissionMiddleware->beforeController($this->apiController2, 'editMember');
			$this->assertTrue(false, 'Permission check should fail with apiController2');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendUserPermissionsException::class, $e);
		}
		// other user after changing share access level
		$this->apiController->editSharedAccessLevel($projectId, $shareId, Application::ACCESS_LEVEL_MAINTAINER);
		$this->userPermissionMiddleware->beforeController($this->apiController2, 'editMember');
		// after deleting shared access
		$this->apiController->deleteUserShare($projectId, $shareId);
		try {
			$this->userPermissionMiddleware->beforeController($this->apiController2, 'getProjectInfo');
			$this->assertTrue(false, 'Permission check should fail with apiController2');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendUserPermissionsException::class, $e);
		}

		// other user member of a group with which the project is shared
		$resp = $this->apiController->createGroupShare($projectId, 'group2test', Application::ACCESS_LEVEL_PARTICIPANT);
		$shareId = $resp->getData()['id'];
		$this->userPermissionMiddleware->beforeController($this->apiController2, 'getProjectInfo');
		try {
			$this->userPermissionMiddleware->beforeController($this->apiController2, 'editMember');
			$this->assertTrue(false, 'Permission check should fail with apiController2');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendUserPermissionsException::class, $e);
		}
		$this->apiController->editSharedAccessLevel($projectId, $shareId, Application::ACCESS_LEVEL_MAINTAINER);
		$this->userPermissionMiddleware->beforeController($this->apiController2, 'editMember');
		$this->apiController->deleteGroupShare($projectId, $shareId);
		try {
			$this->userPermissionMiddleware->beforeController($this->apiController2, 'getProjectInfo');
			$this->assertTrue(false, 'Permission check should fail with apiController2');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendUserPermissionsException::class, $e);
		}
	}

	public function testPublicAuth() {
		$projectId = 'projtodel';
		// CREATE PROJECT owned by test
		$resp = $this->apiController->createProject($projectId, 'whatever');
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_OK, $status);
		$data = $resp->getData();
		$this->assertEquals($projectId, $data['id']);

		// create public share
		$resp = $this->apiController->createPublicShare($projectId);
		$data = $resp->getData();
		$shareId = $data['id'];
		$shareToken = $data['userid'];
		$requestResponse = [
			'token' => $shareToken,
			'password' => 'no-password',
		];

		$this->request
			->expects($this->any())
			->method('getParam')
			->willReturnCallback(static function ($key) use (&$requestResponse) {
				return $key === 'token'
					? $requestResponse['token']
					: $requestResponse['password'];
			});

		// success
		$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicGetProjectInfo');

		// should work with any password
		$requestResponse['token'] = $shareToken;
		$requestResponse['password'] = 'any password';
		$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicGetProjectInfo');

		// not enough permissions
		try {
			$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicEditMember');
			$this->assertTrue(false, 'Permission check should fail');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendPublicAuthNotValidException::class, $e);
		}
		// enough permissions
		$this->apiController->editSharedAccessLevel($projectId, $shareId, Application::ACCESS_LEVEL_MAINTAINER);
		$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicEditMember');

		// wrong token
		$requestResponse['token'] = 'wrong token';
		$requestResponse['password'] = 'any password';

		try {
			$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicGetProjectInfo');
			$this->assertTrue(false, 'Permission check should fail');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendPublicAuthNotValidException::class, $e);
		}

		// set password
		$this->apiController->editSharedAccess($projectId, $shareId, null, 'new password');
		$requestResponse['token'] = $shareToken;
		$requestResponse['password'] = 'new password';
		$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicGetProjectInfo');
		$requestResponse['token'] = $shareToken;
		$requestResponse['password'] = 'wrong password';
		try {
			$this->publicAuthMiddleware->beforeController($this->publicApiController, 'publicGetProjectInfo');
			$this->assertTrue(false, 'Permission check should fail');
		} catch (\Exception $e) {
			$this->assertInstanceOf(CospendPublicAuthNotValidException::class, $e);
		}
	}
}
