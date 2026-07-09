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
use OCA\Cospend\Db\ProjectMapper;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\TestCase;

class CrossProjectServiceTest extends TestCase {

	private const USER1 = 'cptest1';
	private const USER2 = 'cptest2';
	private const USER3 = 'cptest3';

	private const PROJECT_ACTIVE = 'cpactive';
	private const PROJECT_ARCHIVED = 'cparchived';
	private const PROJECT_SETTLE_1 = 'cpsettle1';
	private const PROJECT_SETTLE_2 = 'cpsettle2';

	private LocalProjectService $localProjectService;
	private CospendService $cospendService;
	private ApiController $apiController;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);

		foreach ([self::USER1, self::USER2, self::USER3] as $userId) {
			$user = $userManager->get($userId);
			if ($user !== null) {
				$user->delete();
			}
		}

		$u1 = $userManager->createUser(self::USER1, 'S3cureCospendTest!123');
		$u1->setSystemEMailAddress(self::USER1 . '@example.invalid');
		$u2 = $userManager->createUser(self::USER2, 'S3cureCospendTest!123');
		$u2->setSystemEMailAddress(self::USER2 . '@example.invalid');
		$u3 = $userManager->createUser(self::USER3, 'S3cureCospendTest!123');
		$u3->setSystemEMailAddress(self::USER3 . '@example.invalid');
	}

	protected function setUp(): void {
		$appName = Application::APP_ID;
		$app = new Application();
		$c = $app->getContainer();
		$request = $c->get(\OCP\IRequest::class);
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->cospendService = $c->get(CospendService::class);
		$this->apiController = new ApiController(
			$appName,
			$request,
			$c->get(IManager::class),
			$c->get(IL10N::class),
			$c->get(BillMapper::class),
			$c->get(ProjectMapper::class),
			$this->localProjectService,
			$this->cospendService,
			$c->get(ActivityManager::class),
			$c->get(IRootFolder::class),
			self::USER1,
		);

		$this->deleteTestProjects();
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);

		foreach ([self::USER1, self::USER2, self::USER3] as $userId) {
			$user = $userManager->get($userId);
			if ($user !== null) {
				$user->delete();
			}
		}
	}

	protected function tearDown(): void {
		$this->deleteTestProjects();
	}

	private function deleteTestProjects(): void {
		foreach ([
			self::PROJECT_ACTIVE,
			self::PROJECT_ARCHIVED,
			self::PROJECT_SETTLE_1,
			self::PROJECT_SETTLE_2,
		] as $projectId) {
			try {
				$this->localProjectService->deleteProject($projectId);
			} catch (\Throwable $t) {
			}
		}
	}

	/**
	 * @return array{current: int, target: int, disabled: int}
	 */
	private function createProjectWithUsers(string $projectId): array {
		$this->localProjectService->createProject($projectId, $projectId, null, self::USER1);

		$currentMember = $this->localProjectService->getMemberByUserid($projectId, self::USER1);
		if ($currentMember === null) {
			$currentMember = $this->localProjectService->createMember($projectId, 'Current User', 1, true, '', self::USER1);
		}

		$targetMember = $this->localProjectService->getMemberByUserid($projectId, self::USER2);
		if ($targetMember === null) {
			$targetMember = $this->localProjectService->createMember($projectId, 'Target User', 1, true, '', self::USER2);
		}

		$disabledMember = $this->localProjectService->getMemberByUserid($projectId, self::USER3);
		if ($disabledMember === null) {
			$disabledMember = $this->localProjectService->createMember($projectId, 'Disabled User', 1, true, '', self::USER3);
		}

		return [
			'current' => (int)$currentMember['id'],
			'target' => (int)$targetMember['id'],
			'disabled' => (int)$disabledMember['id'],
		];
	}

	private function createSimpleBill(string $projectId, int $payerId, int $owerId, float $amount, string $what): void {
		$resp = $this->apiController->createBill(
			$projectId,
			'2026-05-31',
			$what,
			$payerId,
			(string)$owerId,
			$amount,
			'n',
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
	}

	private function assertReimbursementBillExists(string $projectId, float $expectedAmount): void {
		$resp = $this->apiController->getBills($projectId);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		$data = $resp->getData();
		$bills = $data['bills'] ?? [];

		$found = false;
		foreach ($bills as $bill) {
			if ((int)$bill['categoryid'] === Application::CATEGORY_REIMBURSEMENT
				&& abs((float)$bill['amount'] - $expectedAmount) < 0.001
			) {
				$found = true;
				break;
			}
		}

		$this->assertTrue($found, 'Expected reimbursement bill not found in project ' . $projectId);
	}

	public function testGetCrossProjectBalancesNoProjectsReturnsEmpty(): void {
		$result = $this->cospendService->getCrossProjectBalances(self::USER1);

		$this->assertSame([], $result['currencyTotals']);
		$this->assertSame([], $result['personBalances']);
		$this->assertSame([], $result['summary']);
	}

	public function testGetCrossProjectBalancesExcludesArchivedAndDisabledMembers(): void {
		$active = $this->createProjectWithUsers(self::PROJECT_ACTIVE);
		$archived = $this->createProjectWithUsers(self::PROJECT_ARCHIVED);

		$this->createSimpleBill(self::PROJECT_ACTIVE, $active['target'], $active['current'], 20.0, 'active-target');
		$this->createSimpleBill(self::PROJECT_ACTIVE, $active['disabled'], $active['current'], 15.0, 'active-disabled');
		$this->createSimpleBill(self::PROJECT_ARCHIVED, $archived['target'], $archived['current'], 50.0, 'archived-target');

		$this->localProjectService->editMember(self::PROJECT_ACTIVE, $active['disabled'], null, null, null, false, null);

		$this->localProjectService->editProject(
			self::PROJECT_ARCHIVED,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			time(),
		);

		$result = $this->cospendService->getCrossProjectBalances(self::USER1);
		$this->assertNotEmpty($result['personBalances']);

		$targetEntry = null;
		$disabledEntry = null;
		foreach ($result['personBalances'] as $entry) {
			$uid = $entry['member']['userid'] ?? null;
			if ($uid === self::USER2) {
				$targetEntry = $entry;
			}
			if ($uid === self::USER3) {
				$disabledEntry = $entry;
			}
		}

		$this->assertNotNull($targetEntry);
		$this->assertNull($disabledEntry);

		$projectIds = array_values(array_unique(array_map(static fn (array $p): string => $p['projectId'], $targetEntry['projects'])));
		$this->assertContains(self::PROJECT_ACTIVE, $projectIds);
		$this->assertNotContains(self::PROJECT_ARCHIVED, $projectIds);
	}

	public function testCreateCrossProjectSettlementValidationErrors(): void {
		$resp = $this->apiController->createCrossProjectSettlement(
			self::USER2,
			'Target User',
			'EUR',
			10.0,
			true,
			[],
		);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
		$this->assertStringContainsString('No projects specified', $resp->getData()['message']);

		$resp = $this->apiController->createCrossProjectSettlement(
			self::USER2,
			'Target User',
			'EUR',
			0.0,
			true,
			[
				['projectId' => 'does-not-exist', 'billAmount' => 1.0],
			],
		);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
		$this->assertStringContainsString('Settlement amount must be positive', $resp->getData()['message']);
	}

	public function testCreateCrossProjectSettlementCreatesBillsAcrossProjects(): void {
		$this->createProjectWithUsers(self::PROJECT_SETTLE_1);
		$this->createProjectWithUsers(self::PROJECT_SETTLE_2);

		$this->cospendService->createCrossProjectSettlement(
			self::USER1,
			self::USER2,
			'Target User',
			'EUR',
			16.90,
			true,
			[
				['projectId' => self::PROJECT_SETTLE_1, 'billAmount' => 12.34],
				['projectId' => self::PROJECT_SETTLE_2, 'billAmount' => 4.56, 'comment' => 'cross-project settlement'],
			],
		);

		$this->assertReimbursementBillExists(self::PROJECT_SETTLE_1, 12.34);
		$this->assertReimbursementBillExists(self::PROJECT_SETTLE_2, 4.56);
	}
}
