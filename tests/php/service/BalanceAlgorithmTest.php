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
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests for the balance and settlement algorithm.
 *
 * These pin CURRENT behaviour of LocalProjectService::getBalance(), settle(),
 * orderBalance(), reduceBalance() and centeredSettle(). They are deliberately
 * arithmetic-heavy: before this file the only numeric coverage of the balance
 * algorithm was a single 2-member, equal-weight, 2-bill case inside
 * LocalProjectServiceTest::testPage(), and settle()/reduceBalance() had no
 * amount assertions at all.
 *
 * Every expected value is derived in a comment from the algorithm itself, so a
 * failure can be attributed to either the code or the derivation.
 *
 * All amounts and weights are chosen to be exactly representable in binary
 * floating point, so the assertions do not depend on a comparison epsilon.
 */
#[AllowMockObjectsWithoutExpectations]
class BalanceAlgorithmTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;

	private const USER_ID = 'testbalance';

	private const PROJECT_IDS = [
		'balgweight',
		'balgexcluded',
		'balggreedy',
		'balgcentered',
		'balgts',
		'balgcur',
		'balgzero',
	];

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
		$appName = Application::APP_ID;
		$request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();

		$app = new Application();
		$c = $app->getContainer();
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->apiController = new ApiController(
			$appName,
			$request,
			$c->get(IManager::class),
			$c->get(IL10N::class),
			$c->get(BillMapper::class),
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
	 * Create a project and its members.
	 *
	 * @param string $projectId
	 * @param array<string, float> $members name => weight
	 * @return array<string, int> name => member id
	 */
	private function makeProject(string $projectId, array $members): array {
		$resp = $this->apiController->createProject($projectId, 'Balance test');
		$this->assertEquals(
			Http::STATUS_OK, $resp->getStatus(),
			$projectId . ' :: ' . json_encode($resp->getData())
		);

		$ids = [];
		foreach ($members as $name => $weight) {
			$resp = $this->apiController->createMember($projectId, $name, null, $weight);
			$this->assertEquals(
				Http::STATUS_OK, $resp->getStatus(),
				$name . ' :: ' . json_encode($resp->getData())
			);
			$ids[$name] = $resp->getData()['id'];
		}
		return $ids;
	}

	/**
	 * @param list<int> $owerIds
	 */
	private function makeBill(
		string $projectId, int $payerId, array $owerIds, float $amount, ?int $timestamp = null,
	): int {
		$resp = $this->apiController->createBill(
			$projectId, '2024-01-15', 'bill', $payerId,
			implode(',', $owerIds), $amount, Application::FREQUENCY_NO,
			null, null, null, 0, null, $timestamp
		);
		$this->assertEquals(
			Http::STATUS_OK, $resp->getStatus(),
			'createBill :: ' . json_encode($resp->getData())
		);
		return $resp->getData();
	}

	/**
	 * @return array<int, array{balance: float|int, paid: float|int, spent: float|int}>
	 */
	private function statsByMemberId(string $projectId, ?int $currencyId = null): array {
		$resp = $this->apiController->getProjectStatistics(
			$projectId, null, null, null, null, null, null, '1', $currencyId
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		$byId = [];
		foreach ($resp->getData()['stats'] as $stat) {
			$byId[$stat['member']['id']] = [
				'balance' => $stat['balance'],
				'paid' => $stat['paid'],
				'spent' => $stat['spent'],
			];
		}
		return $byId;
	}

	/**
	 * @return list<array{from: int, to: int, amount: float}>
	 */
	private function transactions(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): array {
		$resp = $this->apiController->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		return $resp->getData()['transactions'];
	}

	/**
	 * The ower split is proportional to each ower's MEMBER weight (not a per-bill
	 * value), and the payer may also be an ower — they are credited the full amount
	 * and debited their own share in the same pass.
	 *
	 * Bill: 40, payer m1, owers m1(w1) + m2(w2) + m3(w1).
	 *   nbOwerShares = 1 + 2 + 1 = 4
	 *   m1 spent = 40 / 4 * 1 = 10
	 *   m2 spent = 40 / 4 * 2 = 20
	 *   m3 spent = 40 / 4 * 1 = 10
	 *   m1 balance = +40 - 10 = 30 ; m2 = -20 ; m3 = -10
	 */
	public function testWeightedSplitAcrossOwersIncludingThePayer(): void {
		$projectId = 'balgweight';
		$ids = $this->makeProject($projectId, ['m1' => 1.0, 'm2' => 2.0, 'm3' => 1.0]);
		$this->makeBill($projectId, $ids['m1'], [$ids['m1'], $ids['m2'], $ids['m3']], 40.0);

		$stats = $this->statsByMemberId($projectId);

		$this->assertEquals(40, $stats[$ids['m1']]['paid']);
		$this->assertEquals(10, $stats[$ids['m1']]['spent']);
		$this->assertEquals(30, $stats[$ids['m1']]['balance']);

		$this->assertEquals(0, $stats[$ids['m2']]['paid']);
		$this->assertEquals(20, $stats[$ids['m2']]['spent']);
		$this->assertEquals(-20, $stats[$ids['m2']]['balance']);

		$this->assertEquals(0, $stats[$ids['m3']]['paid']);
		$this->assertEquals(10, $stats[$ids['m3']]['spent']);
		$this->assertEquals(-10, $stats[$ids['m3']]['balance']);

		// The whole point of a balance: it sums to zero across the project.
		$sum = $stats[$ids['m1']]['balance'] + $stats[$ids['m2']]['balance'] + $stats[$ids['m3']]['balance'];
		$this->assertEquals(0, $sum);
	}

	/**
	 * A payer who is not among the owers is credited without being debited.
	 *
	 * Bill: 30, payer m1, owers m2(w2) + m3(w1).
	 *   nbOwerShares = 3 ; m2 spent = 20 ; m3 spent = 10
	 *   m1 balance = +30 ; m2 = -20 ; m3 = -10
	 */
	public function testPayerExcludedFromOwersIsCreditedOnly(): void {
		$projectId = 'balgexcluded';
		$ids = $this->makeProject($projectId, ['m1' => 1.0, 'm2' => 2.0, 'm3' => 1.0]);
		$this->makeBill($projectId, $ids['m1'], [$ids['m2'], $ids['m3']], 30.0);

		$stats = $this->statsByMemberId($projectId);

		$this->assertEquals(30, $stats[$ids['m1']]['paid']);
		$this->assertEquals(0, $stats[$ids['m1']]['spent']);
		$this->assertEquals(30, $stats[$ids['m1']]['balance']);
		$this->assertEquals(-20, $stats[$ids['m2']]['balance']);
		$this->assertEquals(-10, $stats[$ids['m3']]['balance']);
	}

	/**
	 * settle() pairs the largest creditor with the largest debtor, repeatedly.
	 *
	 * Balances from the bill below: m1 +30, m2 -20, m3 -10.
	 *   round 1: largest creditor m1(30), largest debtor m2(-20)
	 *            -> min(30, 20) = 20, transaction m2 -> m1 : 20
	 *            m2 settles to 0 (dropped), m1 pushed back with 10
	 *   round 2: m1(10) vs m3(-10) -> 10, transaction m3 -> m1 : 10
	 *   round 3: no crediters left -> stop
	 * Exactly 2 transactions, which is minimal for 1 creditor and 2 debtors.
	 */
	public function testGreedySettlementAmountsAndTransactionCount(): void {
		$projectId = 'balggreedy';
		$ids = $this->makeProject($projectId, ['m1' => 1.0, 'm2' => 2.0, 'm3' => 1.0]);
		$this->makeBill($projectId, $ids['m1'], [$ids['m1'], $ids['m2'], $ids['m3']], 40.0);

		$transactions = $this->transactions($projectId);

		$this->assertCount(2, $transactions);

		$byFrom = [];
		foreach ($transactions as $transaction) {
			$byFrom[$transaction['from']] = $transaction;
		}

		$this->assertArrayHasKey($ids['m2'], $byFrom);
		$this->assertEquals($ids['m1'], $byFrom[$ids['m2']]['to']);
		$this->assertEquals(20, $byFrom[$ids['m2']]['amount']);

		$this->assertArrayHasKey($ids['m3'], $byFrom);
		$this->assertEquals($ids['m1'], $byFrom[$ids['m3']]['to']);
		$this->assertEquals(10, $byFrom[$ids['m3']]['amount']);
	}

	/**
	 * centeredSettle() emits one transaction per member with a non-zero balance,
	 * each for that member's FULL balance, always involving the centered member.
	 * Members with a zero balance are skipped entirely.
	 *
	 * Two bills, each 40, one paid by m1 and one paid by m2, both split over
	 * m1(w1) + m2(w2) + m3(w1):
	 *   per bill: m1 spent 10, m2 spent 20, m3 spent 10
	 *   m1 = +40 - 20 = 20 ; m2 = +40 - 40 = 0 ; m3 = -20
	 * Centered on m1: m2 has balance 0 and is skipped, so exactly one
	 * transaction remains, m3 -> m1 for 20.
	 */
	public function testCenteredSettlementUsesFullBalancesAndSkipsZeroes(): void {
		$projectId = 'balgcentered';
		$ids = $this->makeProject($projectId, ['m1' => 1.0, 'm2' => 2.0, 'm3' => 1.0]);
		$owers = [$ids['m1'], $ids['m2'], $ids['m3']];
		$this->makeBill($projectId, $ids['m1'], $owers, 40.0);
		$this->makeBill($projectId, $ids['m2'], $owers, 40.0);

		$stats = $this->statsByMemberId($projectId);
		$this->assertEquals(20, $stats[$ids['m1']]['balance']);
		$this->assertEquals(0, $stats[$ids['m2']]['balance']);
		$this->assertEquals(-20, $stats[$ids['m3']]['balance']);

		$transactions = $this->transactions($projectId, $ids['m1']);

		$this->assertCount(1, $transactions);
		$this->assertEquals($ids['m3'], $transactions[0]['from']);
		$this->assertEquals($ids['m1'], $transactions[0]['to']);
		$this->assertEquals(20, $transactions[0]['amount']);

		// Centering on the member with a zero balance still only produces
		// transactions that involve them.
		foreach ($this->transactions($projectId, $ids['m2']) as $transaction) {
			$this->assertTrue(
				$transaction['from'] === $ids['m2'] || $transaction['to'] === $ids['m2']
			);
		}
	}

	/**
	 * getProjectSettlement($projectId, null, $maxTimestamp) clamps the balance to
	 * bills at or before $maxTimestamp (BillMapper applies it as tsMax, `lte`).
	 *
	 * Bill A at t=1000 : 40 paid by m1, split m1/m2/m3 -> m1 +30, m2 -20, m3 -10
	 * Bill B at t=3000 : 40 paid by m2, split m1/m2/m3 -> m1 -10, m2 +20, m3 -10
	 * Unclamped totals : m1 +20, m2 0, m3 -20
	 * Clamped at t=2000: only bill A counts -> m1 +30, m2 -20, m3 -10
	 */
	public function testSettlementRespectsMaxTimestamp(): void {
		$projectId = 'balgts';
		$ids = $this->makeProject($projectId, ['m1' => 1.0, 'm2' => 2.0, 'm3' => 1.0]);
		$owers = [$ids['m1'], $ids['m2'], $ids['m3']];
		$this->makeBill($projectId, $ids['m1'], $owers, 40.0, 1000);
		$this->makeBill($projectId, $ids['m2'], $owers, 40.0, 3000);

		// Unclamped: m2 is square, so m3 -> m1 for 20 is the only transaction.
		$all = $this->transactions($projectId);
		$this->assertCount(1, $all);
		$this->assertEquals($ids['m3'], $all[0]['from']);
		$this->assertEquals($ids['m1'], $all[0]['to']);
		$this->assertEquals(20, $all[0]['amount']);

		// Clamped between the two bills: only bill A is visible.
		$clamped = $this->transactions($projectId, null, 2000);
		$this->assertCount(2, $clamped);
		$clampedAmounts = [];
		foreach ($clamped as $transaction) {
			$this->assertEquals($ids['m1'], $transaction['to']);
			$clampedAmounts[$transaction['from']] = $transaction['amount'];
		}
		$this->assertEquals(20, $clampedAmounts[$ids['m2']]);
		$this->assertEquals(10, $clampedAmounts[$ids['m3']]);
	}

	/**
	 * Statistics divide every already-aggregated figure by the currency's exchange
	 * rate. Bills are always stored in the project's main currency; conversion is a
	 * display-time operation and getBalance() itself has no currency awareness.
	 *
	 * Note the branch quirk this pins: with a currency selected, a value of exactly
	 * 0.0 is emitted as the integer 0 rather than 0.0 / 0.0.
	 */
	public function testStatisticsDivideByExchangeRate(): void {
		$projectId = 'balgcur';
		$ids = $this->makeProject($projectId, ['m1' => 1.0, 'm2' => 2.0, 'm3' => 1.0]);
		$this->makeBill($projectId, $ids['m1'], [$ids['m1'], $ids['m2'], $ids['m3']], 40.0);

		$resp = $this->apiController->createCurrency($projectId, 'halfcoin', 2.0);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		$currencyId = $resp->getData();

		$stats = $this->statsByMemberId($projectId, $currencyId);

		// 40 / 2, 10 / 2, 30 / 2
		$this->assertEquals(20, $stats[$ids['m1']]['paid']);
		$this->assertEquals(5, $stats[$ids['m1']]['spent']);
		$this->assertEquals(15, $stats[$ids['m1']]['balance']);

		$this->assertEquals(-10, $stats[$ids['m2']]['balance']);
		$this->assertEquals(-5, $stats[$ids['m3']]['balance']);

		// A member who paid nothing keeps a plain zero through the conversion.
		$this->assertEquals(0, $stats[$ids['m2']]['paid']);
	}

	/**
	 * getBalance() coerces an ower weight of 0.0 to 1.0 before splitting, but that
	 * branch is unreachable through the API: createMember rejects any weight <= 0.
	 * This test pins the guard, so that if member creation ever starts accepting
	 * zero weights the coercion branch stops being dead code and gets real
	 * coverage instead of silently changing every split.
	 */
	public function testMemberWeightMustBePositive(): void {
		$projectId = 'balgzero';
		$this->makeProject($projectId, ['m1' => 1.0]);

		$resp = $this->apiController->createMember($projectId, 'zero', null, 0.0);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $resp->getStatus());

		$this->expectException(CospendBasicException::class);
		$this->localProjectService->createMember($projectId, 'negative', -1.0);
	}
}
