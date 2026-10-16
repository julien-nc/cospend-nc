<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Service;

use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Controller\ApiController;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\BillPayerMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Creating and editing bills with several payers.
 */
#[AllowMockObjectsWithoutExpectations]
class MultiPayerWriteTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;
	private BillPayerMapper $billPayerMapper;
	private IUserSession $userSession;

	private const USER_ID = 'testmpwrite';
	private const PROJECT_IDS = ['mpw', 'mpwtie'];

	public static function setUpBeforeClass(): void {
		$c = (new Application())->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get(self::USER_ID);
		if ($user !== null) {
			$user->delete();
		}
		$userManager->createUser(self::USER_ID, 'T0T0T0');
	}

	public static function tearDownAfterClass(): void {
		$c = (new Application())->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get(self::USER_ID);
		if ($user !== null) {
			$user->delete();
		}
	}

	protected function setUp(): void {
		$request = $this->getMockBuilder('\OCP\IRequest')->disableOriginalConstructor()->getMock();
		$c = (new Application())->getContainer();
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->billPayerMapper = $c->get(BillPayerMapper::class);
		// DEC-08's tie break prefers the acting user, which the service reads from the
		// session. Without one it can only fall back to name order, so the branch would
		// never be exercised.
		$this->userSession = $c->get(IUserSession::class);
		$this->userSession->setUser($c->get(IUserManager::class)->get(self::USER_ID));
		$this->apiController = new ApiController(
			Application::APP_ID, $request,
			$c->get(IManager::class), $c->get(IL10N::class),
			$c->get(BillMapper::class), $c->get(ProjectMapper::class),
			$this->localProjectService, $c->get(CospendService::class),
			$c->get(ActivityManager::class), $c->get(IRootFolder::class),
			self::USER_ID
		);
		$this->deleteTestProjects();
	}

	protected function tearDown(): void {
		$this->deleteTestProjects();
		$this->userSession->setUser(null);
	}

	private function deleteTestProjects(): void {
		foreach (self::PROJECT_IDS as $projectId) {
			try {
				$this->localProjectService->deleteProject($projectId);
			} catch (\Throwable) {
			}
		}
	}

	/**
	 * @param array<string, string|null> $members name => userId
	 * @return array<string, int>
	 */
	private function makeProject(string $projectId, array $members = ['m1' => null, 'm2' => null, 'm3' => null]): array {
		$resp = $this->apiController->createProject($projectId, 'Multi payer write');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$ids = [];
		foreach ($members as $name => $userId) {
			$resp = $this->apiController->createMember($projectId, $name, $userId);
			$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
			$ids[$name] = $resp->getData()['id'];
		}
		return $ids;
	}

	/**
	 * @param array<string, int> $ids
	 */
	private function createBill(string $projectId, array $ids, float $amount, ?array $payers, ?int $payer = null) {
		return $this->apiController->createBill(
			$projectId, '2024-01-15', 'dinner', $payer,
			implode(',', [$ids['m1'], $ids['m2'], $ids['m3']]), $amount, Application::FREQUENCY_NO,
			null, null, null, 0, null, null, null, null, $payers
		);
	}

	public function testCreatingABillWithSeveralPayersStoresThemAndPicksTheLargestAsPrimary(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m2'], 'amount' => 6.0],
			['id' => $ids['m1'], 'amount' => 18.0],
		]);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$billId = $resp->getData();

		$rows = $this->billPayerMapper->getPayersOfBill($billId);
		$this->assertCount(2, $rows);

		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		// listed second, but it is the larger contribution
		$this->assertSame($ids['m1'], $bill['payer_id']);
		$this->assertFalse($bill['payersFallback']);
	}

	/**
	 * The payers are the whole description of who paid, so `payer` need not be sent.
	 */
	public function testPayerArgumentIsNotRequiredWhenPayersAreGiven(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		], null);

		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
	}

	/**
	 * INV-01 is enforced on write; only drift from outside can break it afterwards.
	 */
	public function testPayersThatDoNotAddUpAreRejected(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 5.0],
		]);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
		$this->assertArrayHasKey('payers', $resp->getData());
	}

	/**
	 * A one-entry list is a single-payer bill: it stores no rows, so such a bill stays
	 * identical on disk to one created before this feature existed.
	 */
	public function testASinglePayerEntryStoresNoRows(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		// payer repeats what the list already says: redundant, so it passes without noise
		$resp = $this->createBill($projectId, $ids, 24.0, [['id' => $ids['m2'], 'amount' => 24.0]], $ids['m2']);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$billId = $resp->getData();

		$this->assertCount(0, $this->billPayerMapper->getPayersOfBill($billId));
		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$this->assertSame([], $bill['payers']);
		$this->assertSame($ids['m2'], $bill['payer_id']);
	}

	public function testInvalidPayersAreRejected(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		// same member twice
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 12.0],
			['id' => $ids['m1'], 'amount' => 12.0],
		]);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());

		// not a member of this project
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 12.0],
			['id' => -1, 'amount' => 12.0],
		]);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());

		// a negative contribution is not an interface artefact, so it must not vanish quietly
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 30.0],
			['id' => $ids['m2'], 'amount' => -6.0],
		]);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
	}

	/**
	 * A checked member with an empty amount is an ordinary state of the form. Discarding
	 * those entries here keeps the rule in one place instead of making the client filter
	 * them out before sending.
	 */
	public function testZeroAmountsAreDiscardedRatherThanRefused(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
			['id' => $ids['m3'], 'amount' => 0.0],
		]);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill($projectId, $resp->getData())->getData();
		$this->assertCount(2, $bill['payers']);
		$this->assertNotContains($ids['m3'], array_column($bill['payers'], 'id'));
	}

	/**
	 * Once the zeroes are gone a single payer is left, which is not a split: no rows are
	 * stored and that member becomes the payer, even though no `payer` was sent.
	 */
	public function testOneEffectivePayerCollapsesToThatMember(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m2'], 'amount' => 24.0],
			['id' => $ids['m3'], 'amount' => 0.0],
		], null);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$billId = $resp->getData();
		$this->assertCount(0, $this->billPayerMapper->getPayersOfBill($billId));
		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$this->assertSame($ids['m2'], $bill['payer_id']);
	}

	/**
	 * `null` and `[]` must not collapse into one another: `null` is "leave the payers alone",
	 * which is what an old client and a partial edit send, while `[]` is how the interface
	 * says a bill has gone back to a single payer.
	 */
	public function testEmptyListRemovesTheSplitWhileNullLeavesItAlone(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);
		$billId = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		])->getData();

		// null: a partial edit touching only the title
		$this->apiController->editBill($projectId, $billId, null, 'lunch');
		$this->assertCount(2, $this->billPayerMapper->getPayersOfBill($billId));

		// []: back to a single payer, with the payer stated explicitly
		$resp = $this->apiController->editBill(
			$projectId, $billId, null, null, $ids['m3'], null, null, null, null, null, null,
			null, null, null, null, null, null, []
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$this->assertSame([], $bill['payers']);
		$this->assertSame($ids['m3'], $bill['payer_id']);
	}

	/**
	 * The contract promises no order, so the rows are stored in a canonical one rather than
	 * in whatever order the client happened to send.
	 */
	public function testPayersAreStoredInACanonicalOrder(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$billId = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m3'], 'amount' => 4.0],
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 2.0],
		])->getData();

		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$this->assertSame(
			[$ids['m1'], $ids['m3'], $ids['m2']],
			array_column($bill['payers'], 'id'),
			'largest contribution first, whatever order it arrived in'
		);
	}

	/**
	 * When both arrive, the payers list is the single source of who paid. A `payer` naming
	 * someone outside it would silently lose to the list, producing a plausible bill that is
	 * not the one asked for.
	 */
	public function testAPayerOutsideThePayersListIsRefused(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		// two real payers, but payer names a third member
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		], $ids['m3']);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
		$this->assertArrayHasKey('payers', $resp->getData());

		// one survivor after the zero is dropped, and payer disagrees with it
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m2'], 'amount' => 24.0],
			['id' => $ids['m3'], 'amount' => 0.0],
		], $ids['m1']);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
	}

	/**
	 * Naming someone who is in the list is redundant, not contradictory.
	 */
	public function testAPayerInsideThePayersListIsAccepted(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		// not the primary, but present: the list still decides
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		], $ids['m2']);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill($projectId, $resp->getData())->getData();
		$this->assertSame($ids['m1'], $bill['payer_id'], 'the list decides, not the argument');
	}

	/**
	 * The error has to say which row to look at: with three payers "invalid amount" is not
	 * something the user can act on.
	 */
	public function testPayerErrorsNameTheMember(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 30.0],
			['id' => $ids['m2'], 'amount' => -6.0],
		]);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
		$this->assertStringContainsString('m2', $resp->getData()['payers']);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 12.0],
			['id' => $ids['m1'], 'amount' => 12.0],
		]);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());
		$this->assertStringContainsString('m1', $resp->getData()['payers']);
	}

	/**
	 * DEC-08 tiebreak. On an exact tie the acting user wins over name order, so that the
	 * bill reads as "you paid" to the person who entered it.
	 */
	public function testTieIsBrokenByActingUserThenByName(): void {
		$projectId = 'mpwtie';
		// 'zoe' belongs to the acting user and sorts last by name
		$ids = $this->makeProject($projectId, ['m1' => null, 'm2' => null, 'm3' => null, 'zoe' => self::USER_ID]);

		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['zoe'], 'amount' => 12.0],
			['id' => $ids['m1'], 'amount' => 12.0],
		]);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$bill = $this->apiController->getBill($projectId, $resp->getData())->getData();
		$this->assertSame($ids['zoe'], $bill['payer_id'], 'the acting user wins an exact tie');

		// without the acting user among them, the first by name wins
		$resp = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m2'], 'amount' => 12.0],
			['id' => $ids['m1'], 'amount' => 12.0],
		]);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$bill = $this->apiController->getBill($projectId, $resp->getData())->getData();
		$this->assertSame($ids['m1'], $bill['payer_id']);
	}

	public function testEditingReplacesThePayersWholesale(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);
		$billId = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		])->getData();

		$resp = $this->apiController->editBill(
			$projectId, $billId, null, null, null, null, null, null, null, null, null,
			null, null, null, null, null, null,
			[['id' => $ids['m2'], 'amount' => 20.0], ['id' => $ids['m3'], 'amount' => 4.0]]
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$byMemberId = array_column($bill['payers'], 'amount', 'id');
		$this->assertCount(2, $byMemberId);
		$this->assertEquals(20.0, $byMemberId[$ids['m2']]);
		$this->assertEquals(4.0, $byMemberId[$ids['m3']]);
		$this->assertSame($ids['m2'], $bill['payer_id']);
	}

	/**
	 * DEC-03. An old client re-sending the payer field it read is echoing, not asking for
	 * a change: the split must survive. Otherwise every edit from an old Android build
	 * would silently erase the other payers' contributions.
	 */
	public function testAnEchoedPayerLeavesTheSplitAlone(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);
		$billId = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		])->getData();

		// an old client sends back payer_id unchanged, while renaming the bill
		$resp = $this->apiController->editBill($projectId, $billId, null, 'lunch', $ids['m1']);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$this->assertCount(2, $bill['payers'], 'an echo must not drop the split');
		$this->assertSame('lunch', $bill['what']);
	}

	/**
	 * DEC-03, the other half. A different payer is a deliberate statement that someone
	 * else paid, which the split contradicts, so the split goes.
	 */
	public function testADifferentPayerCollapsesTheSplit(): void {
		$projectId = 'mpw';
		$ids = $this->makeProject($projectId);
		$billId = $this->createBill($projectId, $ids, 24.0, [
			['id' => $ids['m1'], 'amount' => 18.0],
			['id' => $ids['m2'], 'amount' => 6.0],
		])->getData();

		$resp = $this->apiController->editBill($projectId, $billId, null, null, $ids['m3']);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill($projectId, $billId)->getData();
		$this->assertSame([], $bill['payers']);
		$this->assertSame($ids['m3'], $bill['payer_id']);
		$this->assertFalse($bill['payersFallback']);
	}
}
