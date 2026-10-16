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
use OCA\Cospend\Db\ProjectMapper;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Balances and statistics for bills paid by more than one member.
 *
 * The scenario throughout: a 24 bill split evenly over three members, paid 18 by m1
 * and 6 by m2. Every figure below is exactly representable in binary, so the
 * assertions do not lean on a comparison epsilon.
 */
#[AllowMockObjectsWithoutExpectations]
class MultiPayerBalanceTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;
	private BillPayerMapper $billPayerMapper;

	private const USER_ID = 'testmultipayer';
	private const PROJECT_IDS = ['mpbal', 'mpdrift'];

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
	 * 24 split evenly over m1/m2/m3 (8 each), paid 18 by m1 and 6 by m2.
	 *
	 * @return array{0: array<string, int>, 1: int}
	 */
	private function makeSplitBill(string $projectId): array {
		$resp = $this->apiController->createProject($projectId, 'Multi payer');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$ids = [];
		foreach (['m1', 'm2', 'm3'] as $name) {
			$resp = $this->apiController->createMember($projectId, $name);
			$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
			$ids[$name] = $resp->getData()['id'];
		}

		$resp = $this->apiController->createBill(
			$projectId, '2024-01-15', 'dinner', $ids['m1'],
			implode(',', [$ids['m1'], $ids['m2'], $ids['m3']]), 24.0, Application::FREQUENCY_NO
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$billId = $resp->getData();

		foreach ([[$ids['m1'], 18.0], [$ids['m2'], 6.0]] as [$memberId, $amount]) {
			$billPayer = new BillPayer();
			$billPayer->setBillId($billId);
			$billPayer->setMemberId($memberId);
			$billPayer->setAmount($amount);
			$this->billPayerMapper->insert($billPayer);
		}

		return [$ids, $billId];
	}

	/** @return array<int, array{balance: float|int, paid: float|int, spent: float|int}> */
	private function statsByMemberId(string $projectId): array {
		$resp = $this->apiController->getProjectStatistics($projectId);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		$byId = [];
		foreach ($resp->getData()['stats'] as $stat) {
			$byId[$stat['member']['id']] = $stat;
		}
		return $byId;
	}

	/**
	 * Each payer is credited what they put in, not the whole bill.
	 *
	 *   m1 = +18 - 8 = 10 ; m2 = +6 - 8 = -2 ; m3 = -8
	 */
	public function testEachPayerIsCreditedTheirOwnContribution(): void {
		$projectId = 'mpbal';
		[$ids] = $this->makeSplitBill($projectId);

		$stats = $this->statsByMemberId($projectId);

		$this->assertEquals(18, $stats[$ids['m1']]['paid']);
		$this->assertEquals(6, $stats[$ids['m2']]['paid']);
		$this->assertEquals(0, $stats[$ids['m3']]['paid']);

		$this->assertEquals(8, $stats[$ids['m1']]['spent']);
		$this->assertEquals(8, $stats[$ids['m2']]['spent']);
		$this->assertEquals(8, $stats[$ids['m3']]['spent']);

		$this->assertEquals(10, $stats[$ids['m1']]['balance']);
		$this->assertEquals(-2, $stats[$ids['m2']]['balance']);
		$this->assertEquals(-8, $stats[$ids['m3']]['balance']);

		$sum = $stats[$ids['m1']]['balance'] + $stats[$ids['m2']]['balance'] + $stats[$ids['m3']]['balance'];
		$this->assertEquals(0, $sum, 'balances must still cancel out');
	}

	/**
	 * Every ower's share is attributed across the payers in proportion to what each
	 * put in: m1 owns 18/24 of it, m2 owns 6/24.
	 *
	 *   each ower spent 8 -> m1 covered 6 of it, m2 covered 2
	 */
	public function testWhoPaidForWhomSplitsProportionallyBetweenPayers(): void {
		$projectId = 'mpbal';
		[$ids] = $this->makeSplitBill($projectId);

		$resp = $this->apiController->getProjectStatistics($projectId);
		$paidFor = $resp->getData()['membersPaidFor'];

		foreach (['m1', 'm2', 'm3'] as $ower) {
			$this->assertEquals(6, $paidFor[$ids['m1']][$ids[$ower]]);
			$this->assertEquals(2, $paidFor[$ids['m2']][$ids[$ower]]);
			$this->assertEquals(8, $paidFor['total'][$ids[$ower]]);
		}

		// each payer's row adds up to what they actually paid
		$this->assertEquals(18, $paidFor[$ids['m1']]['total']);
		$this->assertEquals(6, $paidFor[$ids['m2']]['total']);
	}

	/**
	 * Settlement consumes the balance map only, so a multi-payer bill needs no special
	 * handling there — but the plan it produces has to be right.
	 *
	 *   m1 +10, m2 -2, m3 -8  ->  m3 pays m1 8, then m2 pays m1 2
	 */
	public function testSettlementOfAMultiPayerBill(): void {
		$projectId = 'mpbal';
		[$ids] = $this->makeSplitBill($projectId);

		$resp = $this->apiController->getProjectSettlement($projectId);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		$transactions = $resp->getData()['transactions'];

		$this->assertCount(2, $transactions);
		$byFrom = [];
		foreach ($transactions as $transaction) {
			$byFrom[$transaction['from']] = $transaction;
		}
		$this->assertEquals($ids['m1'], $byFrom[$ids['m3']]['to']);
		$this->assertEquals(8, $byFrom[$ids['m3']]['amount']);
		$this->assertEquals($ids['m1'], $byFrom[$ids['m2']]['to']);
		$this->assertEquals(2, $byFrom[$ids['m2']]['amount']);
	}

	public function testMonthlyAndCategoryStatsAttributePerPayer(): void {
		$projectId = 'mpbal';
		[$ids] = $this->makeSplitBill($projectId);

		$data = $this->apiController->getProjectStatistics($projectId)->getData();

		$monthly = $data['memberMonthlyPaidStats']['2024-01'];
		$this->assertEquals(18, $monthly[$ids['m1']]);
		$this->assertEquals(6, $monthly[$ids['m2']]);
		$this->assertEquals(0, $monthly[$ids['m3']]);
		// the all-members line (key 0) is the sum of the per-member lines beside it
		$this->assertEquals(24, $monthly[0]);

		$category = $data['categoryMemberStats'][0];
		$this->assertEquals(18, $category[$ids['m1']]);
		$this->assertEquals(6, $category[$ids['m2']]);
	}

	/**
	 * DEC-21(b). Someone raises the total from 24 to 30 without touching the payers,
	 * so the rows no longer account for the bill. The balances ignore them and credit
	 * payer_id with the whole amount instead.
	 *
	 * The point is not that this is right — it is that it stays *coherent*: credits and
	 * debits still cancel. Attributing 24 of credit against 30 of debt would leave the
	 * project balances failing to sum to zero, which breaks the settlement plan and the
	 * displayed precision for everyone.
	 */
	public function testDriftFallsBackToThePrimaryPayerAndStillBalances(): void {
		$projectId = 'mpdrift';
		[$ids, $billId] = $this->makeSplitBill($projectId);

		$this->apiController->editBill($projectId, $billId, null, null, null, null, 30.0);

		$stats = $this->statsByMemberId($projectId);

		// payer_id is m1, so m1 is credited the full 30; each ower now owes 10
		$this->assertEquals(30, $stats[$ids['m1']]['paid']);
		$this->assertEquals(0, $stats[$ids['m2']]['paid'], 'the ignored split contributes nothing');
		$this->assertEquals(20, $stats[$ids['m1']]['balance']);
		$this->assertEquals(-10, $stats[$ids['m2']]['balance']);
		$this->assertEquals(-10, $stats[$ids['m3']]['balance']);

		$sum = $stats[$ids['m1']]['balance'] + $stats[$ids['m2']]['balance'] + $stats[$ids['m3']]['balance'];
		$this->assertEquals(0, $sum, 'a drifted bill must not break the zero-sum invariant');
	}

	/**
	 * The split itself is kept on disk and reported as ignored, so the interface can say
	 * so instead of quietly showing a single payer.
	 */
	public function testDriftKeepsTheSplitAndFlagsIt(): void {
		$projectId = 'mpdrift';
		[$ids, $billId] = $this->makeSplitBill($projectId);
		$this->apiController->editBill($projectId, $billId, null, null, null, null, 30.0);

		$bill = $this->apiController->getBill($projectId, $billId)->getData();

		$this->assertCount(2, $bill['payers']);
		$this->assertTrue($bill['payersFallback']);
		$this->assertEquals($ids['m1'], $bill['payer_id']);
	}
}
