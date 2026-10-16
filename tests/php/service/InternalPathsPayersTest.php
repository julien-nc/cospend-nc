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
use OCA\Cospend\Utils;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * The paths inside the app that duplicate a bill: moving it to another project and repeating
 * it on a schedule.
 *
 * They are also where the invariant is defended. The read side tolerates payer rows that no
 * longer add up, because an external client can produce that state; code in this app must not.
 * Rather than teaching the service to recognise its own callers — a flag that can be forged,
 * forgotten, and that would make a leaf function depend on the call graph — the internal set is
 * small and closed, and each member of it creates a bill carrying its payers rather than
 * changing an amount without them. These tests are what keeps that true.
 */
#[AllowMockObjectsWithoutExpectations]
class InternalPathsPayersTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;
	private BillMapper $billMapper;
	private BillPayerMapper $billPayerMapper;

	private const USER_ID = 'testinternalpaths';
	private const PROJECT_IDS = ['ipfrom', 'ipto', 'iprepeat'];

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
		$this->billMapper = $c->get(BillMapper::class);
		$this->billPayerMapper = $c->get(BillPayerMapper::class);
		$this->apiController = new ApiController(
			Application::APP_ID, $request,
			$c->get(IManager::class), $c->get(IL10N::class),
			$this->billMapper, $c->get(ProjectMapper::class),
			$this->localProjectService, $c->get(CospendService::class),
			$c->get(ActivityManager::class), $c->get(IRootFolder::class),
			self::USER_ID
		);
		$this->deleteTestProjects();
	}

	protected function tearDown(): void {
		$this->deleteTestProjects();
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
	 * @param list<string> $memberNames
	 * @return array<string, int>
	 */
	private function makeProject(string $projectId, array $memberNames): array {
		$resp = $this->apiController->createProject($projectId, $projectId);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$ids = [];
		foreach ($memberNames as $name) {
			$ids[$name] = $this->apiController->createMember($projectId, $name)->getData()['id'];
		}
		return $ids;
	}

	/**
	 * @param array<string, int> $ids
	 */
	private function makeSplitBill(string $projectId, array $ids, float $amount, array $payers, ?string $repeat = null, ?int $timestamp = null): int {
		$resp = $this->apiController->createBill(
			$projectId, '2024-01-15', 'dinner', null, implode(',', array_values($ids)),
			$amount, $repeat ?? Application::FREQUENCY_NO,
			null, null, null, 0, null, $timestamp, null, null, $payers
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		return $resp->getData();
	}

	/**
	 * INV-14: after any internal operation, a bill either has no payer rows or they account
	 * for its whole amount. There is no third state for code in this app to leave behind.
	 */
	private function assertBillInvariantHolds(string $projectId, int $billId): void {
		$bill = $this->billMapper->getBill($projectId, $billId);
		$this->assertNotNull($bill);
		if ($bill['payers'] === []) {
			$this->assertFalse($bill['payersFallback']);
			return;
		}
		$this->assertTrue(
			Utils::payersCoverAmount(array_column($bill['payers'], 'amount'), (float)$bill['amount']),
			'payer rows must account for the bill amount after an internal operation',
		);
		$this->assertFalse($bill['payersFallback']);
	}

	public function testMovingABillCarriesItsPayersToTheTargetProject(): void {
		$from = $this->makeProject('ipfrom', ['Val', 'Mary', 'Bob']);
		$to = $this->makeProject('ipto', ['Val', 'Mary', 'Bob']);
		$billId = $this->makeSplitBill('ipfrom', $from, 24.0, [
			['id' => $from['Val'], 'amount' => 18.0],
			['id' => $from['Mary'], 'amount' => 6.0],
		]);

		$resp = $this->apiController->moveBill('ipfrom', $billId, 'ipto');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$newBillId = $resp->getData();

		$moved = $this->billMapper->getBill('ipto', $newBillId);
		$byMemberId = array_column($moved['payers'], 'amount', 'id');
		// the ids are the target project's, matched by name
		$this->assertEquals(18.0, $byMemberId[$to['Val']]);
		$this->assertEquals(6.0, $byMemberId[$to['Mary']]);
		$this->assertSame($to['Val'], $moved['payer_id']);
		$this->assertBillInvariantHolds('ipto', $newBillId);
	}

	/**
	 * Matching only some payers would force a choice between inventing a member for the rest
	 * and dropping what they put in. The move is refused whole, and the message says who is
	 * missing so the user can create them.
	 */
	public function testMovingIsRefusedWhenAPayerIsMissingThere(): void {
		$from = $this->makeProject('ipfrom', ['Val', 'Mary', 'Bob']);
		$this->makeProject('ipto', ['Val', 'Bob']);
		$billId = $this->makeSplitBill('ipfrom', $from, 24.0, [
			['id' => $from['Val'], 'amount' => 18.0],
			['id' => $from['Mary'], 'amount' => 6.0],
		]);

		$resp = $this->apiController->moveBill('ipfrom', $billId, 'ipto');
		$data = $resp->getData();
		$this->assertArrayHasKey('message', $data);
		$this->assertStringContainsString('Mary', $data['message']);

		// the original is untouched, so nothing was half-moved
		$this->assertNotNull($this->billMapper->getBill('ipfrom', $billId));
		$this->assertBillInvariantHolds('ipfrom', $billId);
	}

	public function testMovingASinglePayerBillIsUnchanged(): void {
		$from = $this->makeProject('ipfrom', ['Val', 'Mary', 'Bob']);
		$to = $this->makeProject('ipto', ['Val', 'Mary', 'Bob']);
		$resp = $this->apiController->createBill(
			'ipfrom', '2024-01-15', 'taxi', $from['Bob'], implode(',', array_values($from)),
			30.0, Application::FREQUENCY_NO
		);
		$billId = $resp->getData();

		$resp = $this->apiController->moveBill('ipfrom', $billId, 'ipto');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$moved = $this->billMapper->getBill('ipto', $resp->getData());
		$this->assertSame([], $moved['payers']);
		$this->assertSame($to['Bob'], $moved['payer_id']);
	}

	/**
	 * Without copying the set, every occurrence would silently come back with a single payer:
	 * data corruption on a recurring schedule rather than a one-off mistake.
	 */
	public function testRepeatingABillCarriesItsPayers(): void {
		$ids = $this->makeProject('iprepeat', ['Val', 'Mary', 'Bob']);
		$billId = $this->makeSplitBill(
			'iprepeat', $ids, 24.0,
			[['id' => $ids['Val'], 'amount' => 18.0], ['id' => $ids['Mary'], 'amount' => 6.0]],
			Application::FREQUENCY_DAILY, (new \DateTime('-5 days'))->getTimestamp()
		);

		$created = $this->localProjectService->repeatBill('iprepeat', $billId);
		$this->assertNotEmpty($created);

		$bills = $this->billMapper->getBillsClassic('iprepeat');
		$repeated = array_values(array_filter($bills, static fn (array $b): bool => $b['id'] !== $billId));
		$this->assertNotEmpty($repeated);
		foreach ($repeated as $bill) {
			$byMemberId = array_column($bill['payers'], 'amount', 'id');
			$this->assertEquals(18.0, $byMemberId[$ids['Val']], 'each occurrence keeps the split');
			$this->assertEquals(6.0, $byMemberId[$ids['Mary']]);
			$this->assertBillInvariantHolds('iprepeat', $bill['id']);
		}

		// the source bill keeps its own split: clearing the repeat flag re-sends the same
		// payer id, which is an echo rather than a change of payer
		$source = $this->billMapper->getBill('iprepeat', $billId);
		$this->assertCount(2, $source['payers']);
	}

	/**
	 * A bill whose payers no longer add up is already treated as paid by payer_id alone.
	 * Repeating it reproduces that, rather than copying a split the balances are ignoring
	 * and having the copy refused by validation.
	 */
	public function testRepeatingADriftedBillReproducesWhatTheBalancesUse(): void {
		$ids = $this->makeProject('iprepeat', ['Val', 'Mary', 'Bob']);
		$billId = $this->makeSplitBill(
			'iprepeat', $ids, 24.0,
			[['id' => $ids['Val'], 'amount' => 18.0], ['id' => $ids['Mary'], 'amount' => 6.0]],
			Application::FREQUENCY_DAILY, (new \DateTime('-5 days'))->getTimestamp()
		);
		// an outside change to the amount, leaving the payers behind
		$this->apiController->editBill('iprepeat', $billId, null, null, null, null, 30.0);
		$this->assertTrue($this->billMapper->getBill('iprepeat', $billId)['payersFallback']);

		$this->localProjectService->repeatBill('iprepeat', $billId);

		$bills = $this->billMapper->getBillsClassic('iprepeat');
		$repeated = array_values(array_filter($bills, static fn (array $b): bool => $b['id'] !== $billId));
		$this->assertNotEmpty($repeated);
		foreach ($repeated as $bill) {
			$this->assertSame([], $bill['payers'], 'the ignored split is not carried over');
			$this->assertSame($ids['Val'], $bill['payer_id']);
			$this->assertBillInvariantHolds('iprepeat', $bill['id']);
		}
	}
}
