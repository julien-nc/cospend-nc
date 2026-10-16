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
use OCA\Cospend\Db\BillPayer;
use OCA\Cospend\Db\BillPayerMapper;
use OCA\Cospend\Db\MemberMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Storage-layer behaviour of cospend_bill_payers.
 *
 * The service write path does not create payer rows yet, so these tests insert
 * them through BillPayerMapper directly. That is deliberate: it exercises the
 * referential guarantees on their own, before any higher-level code depends on
 * them.
 */
#[AllowMockObjectsWithoutExpectations]
class BillPayerStorageTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;
	private BillMapper $billMapper;
	private BillPayerMapper $billPayerMapper;
	private MemberMapper $memberMapper;

	private const USER_ID = 'testbillpayer';

	private const PROJECT_IDS = ['bpsguard', 'bpsbill', 'bpsproj', 'bpstrash', 'bpscli', 'bpsuniq', 'bpsread', 'bpsfilter'];

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get(self::USER_ID);
		if ($user !== null) {
			$user->delete();
		}
		$userManager->createUser(self::USER_ID, 'T0T0T0');
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get(self::USER_ID);
		if ($user !== null) {
			$user->delete();
		}
	}

	protected function setUp(): void {
		$request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();

		$app = new Application();
		$c = $app->getContainer();
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->billMapper = $c->get(BillMapper::class);
		$this->billPayerMapper = $c->get(BillPayerMapper::class);
		$this->memberMapper = $c->get(MemberMapper::class);
		$this->apiController = new ApiController(
			Application::APP_ID,
			$request,
			$c->get(IManager::class),
			$c->get(IL10N::class),
			$this->billMapper,
			$c->get(ProjectMapper::class),
			$this->localProjectService,
			$c->get(CospendService::class),
			$c->get(ActivityManager::class),
			$c->get(IRootFolder::class),
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
	 * @return array{0: array<string, int>, 1: int} member ids, bill id
	 */
	private function makeProjectWithBill(string $projectId): array {
		$resp = $this->apiController->createProject($projectId, 'Bill payer test');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$ids = [];
		foreach (['m1', 'm2', 'm3'] as $name) {
			$resp = $this->apiController->createMember($projectId, $name);
			$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
			$ids[$name] = $resp->getData()['id'];
		}

		// m3 is deliberately neither the main payer nor an ower.
		$resp = $this->apiController->createBill(
			$projectId, '2024-01-15', 'dinner', $ids['m1'],
			$ids['m1'] . ',' . $ids['m2'], 22.0, Application::FREQUENCY_NO
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		return [$ids, $resp->getData()];
	}

	private function addPayerRow(int $billId, int $memberId, float $amount): BillPayer {
		$billPayer = new BillPayer();
		$billPayer->setBillId($billId);
		$billPayer->setMemberId($memberId);
		$billPayer->setAmount($amount);
		return $this->billPayerMapper->insert($billPayer);
	}

	private function countPayerRows(int $billId): int {
		return count($this->billPayerMapper->getPayersOfBill($billId));
	}

	/**
	 * A member who only ever appears in cospend_bill_payers must still count as
	 * involved, otherwise deleteMember() hard-deletes them.
	 */
	public function testSecondaryPayerCountsAsInvolvedInTheBill(): void {
		$projectId = 'bpsguard';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);

		$this->assertSame([], $this->memberMapper->getBillIdsOfMember($ids['m3']));

		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$found = $this->memberMapper->getBillIdsOfMember($ids['m3']);
		$this->assertNotEmpty($found);
		$this->assertContains($billId, array_map('intval', $found));
	}

	/**
	 * The consequence of the guard above: deleting such a member deactivates them
	 * rather than removing the row a payer record still points at.
	 */
	public function testDeletingASecondaryPayerDeactivatesInsteadOfRemovingTheRow(): void {
		$projectId = 'bpsguard';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$this->localProjectService->deleteMember($projectId, $ids['m3']);

		$member = $this->localProjectService->getMemberById($projectId, $ids['m3']);
		$this->assertNotNull($member, 'a member referenced by cospend_bill_payers must survive deletion');
		$this->assertFalse($member['activated']);

		// Contrast: a member involved in nothing at all is still hard-deleted.
		$resp = $this->apiController->createMember($projectId, 'uninvolved');
		$uninvolvedId = $resp->getData()['id'];
		$this->localProjectService->deleteMember($projectId, $uninvolvedId);
		$this->assertNull($this->localProjectService->getMemberById($projectId, $uninvolvedId));
	}

	public function testHardDeletingABillRemovesItsPayerRows(): void {
		$projectId = 'bpsbill';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);
		$this->assertSame(2, $this->countPayerRows($billId));

		// Soft delete leaves the relation intact, so a restore needs no work.
		$this->localProjectService->deleteBill($projectId, $billId, true, true);
		$this->assertSame(2, $this->countPayerRows($billId));

		// The second delete is the real one.
		$this->localProjectService->deleteBill($projectId, $billId, true, true);
		$this->assertSame(0, $this->countPayerRows($billId));
	}

	public function testDeletingAProjectRemovesItsPayerRows(): void {
		$projectId = 'bpsproj';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m3'], 2.0);
		$this->assertSame(1, $this->countPayerRows($billId));

		$this->localProjectService->deleteProject($projectId);

		$this->assertSame(0, $this->countPayerRows($billId));
	}

	public function testClearingTheTrashBinRemovesPayerRows(): void {
		$projectId = 'bpstrash';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$this->localProjectService->deleteBill($projectId, $billId, true, true);
		$this->assertSame(1, $this->countPayerRows($billId), 'trashed bills keep their payers');

		$this->localProjectService->clearTrashBin($projectId);
		$this->assertSame(0, $this->countPayerRows($billId));
	}

	public function testBulkDeleteReportsAndRemovesPayerRows(): void {
		$projectId = 'bpscli';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$deleted = $this->billMapper->deleteBills($projectId);

		$this->assertSame(1, $deleted['bills']);
		$this->assertSame(2, $deleted['billOwers']);
		$this->assertSame(2, $deleted['billPayers']);
		$this->assertSame(0, $this->countPayerRows($billId));
	}

	/**
	 * A single-payer bill carries an empty relation and is never in fallback, so the
	 * payload shape is uniform and the client needs no special case for it.
	 */
	public function testSinglePayerBillExposesAnEmptyPayersArray(): void {
		$projectId = 'bpsread';
		[, $billId] = $this->makeProjectWithBill($projectId);

		$bill = $this->billMapper->getBill($projectId, $billId);

		$this->assertSame([], $bill['payers']);
		$this->assertFalse($bill['payersFallback']);
	}

	/**
	 * The payers relation is exposed as id + amount only. No name, weight or colour:
	 * a member rename has to reach old bills, which it cannot do through a copy.
	 */
	public function testPayersAreExposedAsIdAndAmountOnly(): void {
		$projectId = 'bpsread';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$bill = $this->billMapper->getBill($projectId, $billId);

		$this->assertCount(2, $bill['payers']);
		foreach ($bill['payers'] as $payer) {
			$this->assertSame(['id', 'amount'], array_keys($payer));
		}

		$byMemberId = array_column($bill['payers'], 'amount', 'id');
		$this->assertEquals(20.0, $byMemberId[$ids['m1']]);
		$this->assertEquals(2.0, $byMemberId[$ids['m3']]);
	}

	/**
	 * Payer rows that account for the whole bill are authoritative, so no fallback.
	 */
	public function testCoveringPayersAreNotFlaggedAsFallback(): void {
		$projectId = 'bpsread';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$bill = $this->billMapper->getBill($projectId, $billId);

		$this->assertFalse($bill['payersFallback']);
	}

	/**
	 * The drift case: the rows no longer add up to the bill amount, so the balances
	 * ignore them and credit payer_id instead. The payload has to say so, because the
	 * client must never recompute this comparison with a tolerance of its own.
	 */
	public function testPayersThatDoNotCoverTheAmountAreFlaggedAsFallback(): void {
		$projectId = 'bpsread';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		// Someone raised the total from 22 to 24 without touching the payers.
		$this->apiController->editBill($projectId, $billId, null, null, null, null, 24.0);

		$bill = $this->billMapper->getBill($projectId, $billId);

		$this->assertCount(2, $bill['payers'], 'the split is kept, not hidden');
		$this->assertTrue($bill['payersFallback']);
	}

	/**
	 * Float noise below half a cent must not trip the flag, or the marker would cry
	 * wolf on arithmetic the user cannot see.
	 */
	public function testSubCentFloatNoiseDoesNotTripTheFallbackFlag(): void {
		$projectId = 'bpsread';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		// 7.33 + 7.33 + 7.34 does not land exactly on 22.0 in binary.
		$this->addPayerRow($billId, $ids['m1'], 7.33);
		$this->addPayerRow($billId, $ids['m2'], 7.33);
		$this->addPayerRow($billId, $ids['m3'], 7.34);

		$bill = $this->billMapper->getBill($projectId, $billId);

		$this->assertFalse($bill['payersFallback']);
	}

	/**
	 * All three read paths must agree; a bill read through the list must not look
	 * different from the same bill read on its own.
	 */
	public function testEveryReadPathAttachesTheSamePayers(): void {
		$projectId = 'bpsread';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$single = $this->billMapper->getBill($projectId, $billId);
		$classic = $this->billMapper->getBillsClassic($projectId);
		$limited = $this->billMapper->getBillsWithLimit($projectId);

		$this->assertCount(1, $classic);
		$this->assertCount(1, $limited);
		$this->assertEquals($single['payers'], $classic[0]['payers']);
		$this->assertEquals($single['payers'], $limited[0]['payers']);
		$this->assertSame($single['payersFallback'], $classic[0]['payersFallback']);
		$this->assertSame($single['payersFallback'], $limited[0]['payersFallback']);
	}

	/**
	 * DEC-04: the payerId filter means "contributed to", not "is the primary payer".
	 * Clicking a member in the navigation asks for the expenses they are involved in;
	 * dropping the one where they put in 2 EUR would be wrong.
	 *
	 * The three filtered reads must give the same answer, since they back the same
	 * navigation click: the list, the paginated list and the bill counter badge.
	 */
	public function testFilteringByPayerMatchesAdditionalPayersToo(): void {
		$projectId = 'bpsfilter';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);

		// m3 is neither the main payer nor an ower, so nothing matches yet.
		$this->assertSame(0, $this->billMapper->countBills($projectId, $ids['m3']));
		$this->assertCount(0, $this->billMapper->getBillsClassic($projectId, null, null, null, null, null, null, null, null, null, false, $ids['m3']));

		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$this->assertSame(1, $this->billMapper->countBills($projectId, $ids['m3']));
		$classic = $this->billMapper->getBillsClassic($projectId, null, null, null, null, null, null, null, null, null, false, $ids['m3']);
		$this->assertCount(1, $classic);
		$this->assertSame($billId, $classic[0]['id']);
		$limited = $this->billMapper->getBillsWithLimit($projectId, null, null, null, null, null, null, null, null, null, false, 0, $ids['m3']);
		$this->assertCount(1, $limited);
		$this->assertSame($billId, $limited[0]['id']);
	}

	/**
	 * The counter must not double-count a bill whose member is both the main payer and
	 * carries a payer row — the filter is a subquery precisely so no join can inflate it.
	 */
	public function testFilteringDoesNotDoubleCountAMemberWhoIsAlsoTheMainPayer(): void {
		$projectId = 'bpsfilter';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m1'], 20.0);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		// m1 is the main payer AND has a payer row.
		$this->assertSame(1, $this->billMapper->countBills($projectId, $ids['m1']));
		$this->assertCount(1, $this->billMapper->getBillsClassic($projectId, null, null, null, null, null, null, null, null, null, false, $ids['m1']));
	}

	/**
	 * With no payer rows anywhere the filter must behave exactly as it did before, which
	 * is the ~95% case and what the existing countBills assertions pin.
	 */
	public function testFilteringIsUnchangedForSinglePayerBills(): void {
		$projectId = 'bpsfilter';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);

		$this->assertSame(1, $this->billMapper->countBills($projectId, $ids['m1']));
		$this->assertSame(0, $this->billMapper->countBills($projectId, $ids['m2']));
		$this->assertSame(0, $this->billMapper->countBills($projectId, $ids['m3']));
	}

	/**
	 * A member may contribute to a bill at most once; a duplicate row would credit
	 * that member's balance twice.
	 */
	public function testAMemberCannotBeAPayerOfTheSameBillTwice(): void {
		$projectId = 'bpsuniq';
		[$ids, $billId] = $this->makeProjectWithBill($projectId);
		$this->addPayerRow($billId, $ids['m3'], 2.0);

		$this->expectException(\OCP\DB\Exception::class);
		$this->addPayerRow($billId, $ids['m3'], 5.0);
	}
}
