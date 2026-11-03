<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Service;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCP\Config\IUserConfig;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Simple IUserSession implementation that records setUser() calls without
 * touching the process-wide server session.
 */
class RecordingUserSession implements IUserSession {
	/**
	 * @var list<string|null>
	 */
	public array $recordedUsers = [];

	public function setUser($user) {
		$this->recordedUsers[] = $user === null ? null : $user->getUID();
	}

	public function getUser() {
		return null;
	}

	public function isLoggedIn() {
		return false;
	}

	public function login($uid, $password) {
		return false;
	}

	public function logout() {
	}

	public function setVolatileActiveUser(?IUser $user): void {
	}

	public function getImpersonatingUserID(): ?string {
		return null;
	}

	public function setImpersonatingUserID(bool $useCurrentUser = true): void {
	}

	public function isIncognitoMode(): bool {
		return false;
	}

	public function setIncognitoMode(bool $mode): void {
	}
}

#[AllowMockObjectsWithoutExpectations]
class CospendServiceTest extends TestCase {

	private const PROJECT_ID = 'autoexportproj';

	private LocalProjectService $localProjectService;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		// clear test user
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('test');
		if ($user !== null) {
			$user->delete();
		}

		$u1 = $userManager->createUser('test', 'T0T0T0');
		$u1->setSystemEMailAddress('toto@toto.net');
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('test');
		$user->delete();
	}

	protected function setUp(): void {
		$app = new Application();
		$c = $app->getContainer();
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->deleteTestProject();
		$this->deleteTestExportFiles();
	}

	protected function tearDown(): void {
		$this->deleteTestProject();
		$this->deleteTestExportFiles();
	}

	private function deleteTestProject(): void {
		// in case there was a failure and something was not deleted
		try {
			$this->localProjectService->deleteProject(self::PROJECT_ID);
		} catch (\Throwable $t) {
		}
	}

	private function deleteTestExportFiles(): void {
		$app = new Application();
		$c = $app->getContainer();
		try {
			$userFolder = $c->get(IRootFolder::class)->getUserFolder('test');
			if ($userFolder->nodeExists('/Cospend')) {
				$folder = $userFolder->get('/Cospend');
				foreach ($folder->search(self::PROJECT_ID) as $node) {
					$node->delete();
				}
			}
		} catch (\Throwable $t) {
		}
	}

	public function testCronAutoExport() {
		$app = new Application();
		$c = $app->getContainer();

		$this->localProjectService->createProject('AutoExport proj', self::PROJECT_ID, null, 'test');
		$member = $this->localProjectService->createMember(self::PROJECT_ID, 'bobby');
		$idMember1 = $member['id'];

		$this->localProjectService->createBill(
			self::PROJECT_ID, '2019-01-22', 'boomerang', $idMember1,
			(string)$idMember1, 22.5, Application::FREQUENCY_NO
		);

		$this->localProjectService->editProject(
			self::PROJECT_ID, 'AutoExport proj',
			null, Application::FREQUENCY_DAILY
		);

		$recordingUserSession = new RecordingUserSession();
		$cospendService = new CospendService(
			$c->get(LocalProjectService::class),
			$c->get(InvitationMapper::class),
			$c->get(ProjectMapper::class),
			$c->get(IRootFolder::class),
			$c->get(IL10N::class),
			$c->get(IUserManager::class),
			$recordingUserSession,
			$c->get(IDBConnection::class),
			$c->get(IUserConfig::class),
		);

		$cospendService->cronAutoExport();

		// check the auto export impersonates the user while exporting
		// and restores the session afterwards
		$this->assertContains('test', $recordingUserSession->recordedUsers);
		$this->assertNull($recordingUserSession->recordedUsers[count($recordingUserSession->recordedUsers) - 1]);

		// the user session must still not be set
		$this->assertNull($c->get(IUserSession::class)->getUser());

		// the export file must have been created
		$userFolder = $c->get(IRootFolder::class)->getUserFolder('test');
		$this->assertTrue($userFolder->nodeExists('/Cospend'));
		$exports = $userFolder->get('/Cospend')->search(self::PROJECT_ID);
		$this->assertEquals(1, count($exports));
		$this->assertTrue(str_starts_with($exports[0]->getName(), self::PROJECT_ID));
		$this->assertTrue(str_ends_with($exports[0]->getName(), '.csv'));
		$this->assertTrue($exports[0]->getSize() > 0);
	}
}
