<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Service;

use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Capabilities;
use OCA\Cospend\Controller\ApiController;
use OCA\Cospend\Controller\PublicApiController;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Db\BillPayerMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Db\ShareMapper;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * The public-link surface, which shares its serializer and service with the private API but
 * carries its own duplicated parameter lists, and the capability a client reads before
 * offering the feature at all.
 */
#[AllowMockObjectsWithoutExpectations]
class PublicApiPayersTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;
	private PublicApiController $publicApiController;
	private BillPayerMapper $billPayerMapper;

	private const USER_ID = 'testpublicpayers';
	private const PROJECT_ID = 'pubpay';

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
		$this->publicApiController = new PublicApiController(
			Application::APP_ID, $request,
			$c->get(IL10N::class), $c->get(BillMapper::class), $c->get(ShareMapper::class),
			$this->localProjectService, $c->get(ActivityManager::class)
		);
		$this->deleteTestProject();
	}

	protected function tearDown(): void {
		$this->deleteTestProject();
	}

	private function deleteTestProject(): void {
		try {
			$this->localProjectService->deleteProject(self::PROJECT_ID);
		} catch (\Throwable) {
		}
	}

	/**
	 * @return array{0: array<string, int>, 1: string} member ids, share token
	 */
	private function makeSharedProject(): array {
		$resp = $this->apiController->createProject(self::PROJECT_ID, 'Public payers');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$ids = [];
		foreach (['m1', 'm2', 'm3'] as $name) {
			$ids[$name] = $this->apiController->createMember(self::PROJECT_ID, $name)->getData()['id'];
		}

		$share = $this->localProjectService->createPublicShare(self::PROJECT_ID, self::USER_ID);
		// PublicAuthMiddleware resolves the token to a project on a real request; a unit test
		// has to stand in for it.
		$this->publicApiController->projectId = self::PROJECT_ID;

		return [$ids, $share['userid']];
	}

	public function testCreatingAMultiPayerBillThroughAPublicLink(): void {
		[$ids, $token] = $this->makeSharedProject();

		$resp = $this->publicApiController->publicCreateBill(
			$token, '2024-01-15', 'dinner', null,
			implode(',', array_values($ids)), 24.0, 'n',
			null, null, null, 0, null, null, null, null,
			[['id' => $ids['m1'], 'amount' => 18.0], ['id' => $ids['m2'], 'amount' => 6.0]]
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$billId = $resp->getData();
		$this->assertCount(2, $this->billPayerMapper->getPayersOfBill($billId));

		$bill = $this->apiController->getBill(self::PROJECT_ID, $billId)->getData();
		$this->assertSame($ids['m1'], $bill['payer_id']);
		$this->assertFalse($bill['payersFallback']);
	}

	public function testEditingThePayersThroughAPublicLink(): void {
		[$ids, $token] = $this->makeSharedProject();
		$billId = $this->publicApiController->publicCreateBill(
			$token, '2024-01-15', 'dinner', null,
			implode(',', array_values($ids)), 24.0, 'n',
			null, null, null, 0, null, null, null, null,
			[['id' => $ids['m1'], 'amount' => 18.0], ['id' => $ids['m2'], 'amount' => 6.0]]
		)->getData();

		$resp = $this->publicApiController->publicEditBill(
			$token, $billId, null, null, null, null, null, 'n',
			null, null, null, null, null, null, null, null, null,
			[['id' => $ids['m2'], 'amount' => 20.0], ['id' => $ids['m3'], 'amount' => 4.0]]
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill(self::PROJECT_ID, $billId)->getData();
		$byMemberId = array_column($bill['payers'], 'amount', 'id');
		$this->assertEquals(20.0, $byMemberId[$ids['m2']]);
		$this->assertEquals(4.0, $byMemberId[$ids['m3']]);
		$this->assertSame($ids['m2'], $bill['payer_id']);
	}

	/**
	 * Bulk edit deliberately does not carry payers: setting one split across N bills is
	 * meaningless. It must leave the relation alone rather than clearing it.
	 */
	public function testBulkEditLeavesThePayersAlone(): void {
		[$ids, $token] = $this->makeSharedProject();
		$billId = $this->publicApiController->publicCreateBill(
			$token, '2024-01-15', 'dinner', null,
			implode(',', array_values($ids)), 24.0, 'n',
			null, null, null, 0, null, null, null, null,
			[['id' => $ids['m1'], 'amount' => 18.0], ['id' => $ids['m2'], 'amount' => 6.0]]
		)->getData();

		$resp = $this->publicApiController->publicEditBills($token, [$billId], null, null, 'lunch');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$bill = $this->apiController->getBill(self::PROJECT_ID, $billId)->getData();
		$this->assertCount(2, $bill['payers']);
		$this->assertSame('lunch', $bill['what']);
	}

	/**
	 * A client has to know whether the server can store a split before offering the option,
	 * otherwise the limit is only discovered at save time. The version string is not enough.
	 */
	public function testCapabilitiesAdvertiseMultiPayerSupport(): void {
		$capabilities = (new Application())->getContainer()->get(Capabilities::class)->getCapabilities();

		$this->assertArrayHasKey('multi_payer', $capabilities[Application::APP_ID]);
		$this->assertTrue($capabilities[Application::APP_ID]['multi_payer']);
	}
}
