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
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Existing behaviour that the multi-payer work depends on, pinned before it is extended.
 *
 * Neither of these is about multiple payers. They are the two questions the payer design ran
 * into — what repetition does with a deactivated payer, and whether a member name containing a
 * comma survives a CSV round trip — answered by execution rather than by reading.
 */
#[AllowMockObjectsWithoutExpectations]
class PreexistingBehaviourTest extends TestCase {

	private LocalProjectService $localProjectService;
	private ApiController $apiController;

	private const USER_ID = 'testpreexist';
	private const PROJECT_IDS = ['pxrepeat', 'pxcsv', 'pxcsv-imported'];

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
	 * Repetition drops deactivated owers and cancels itself when none are left, but it has no
	 * equivalent rule for the payer. This pins what actually happens today, before multi-payer
	 * has to decide what it should do.
	 */
	public function testRepeatWithADeactivatedPayer(): void {
		$projectId = 'pxrepeat';
		$resp = $this->apiController->createProject($projectId, 'Repeat');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());
		$ids = [];
		foreach (['m1', 'm2'] as $name) {
			$ids[$name] = $this->apiController->createMember($projectId, $name)->getData()['id'];
		}

		// a daily bill dated well in the past, so one repetition is due
		$resp = $this->apiController->createBill(
			$projectId, null, 'rent', $ids['m1'], $ids['m1'] . ',' . $ids['m2'],
			10.0, Application::FREQUENCY_DAILY, null, null, null, 0, null,
			(new \DateTime('-5 days'))->getTimestamp()
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));
		$billId = $resp->getData();

		// the payer leaves the project
		$this->localProjectService->deleteMember($projectId, $ids['m1']);
		$member = $this->localProjectService->getMemberById($projectId, $ids['m1']);
		$this->assertFalse($member['activated'], 'the payer is deactivated, not removed');

		$created = $this->localProjectService->repeatBill($projectId, $billId);

		$bills = $this->apiController->getBills($projectId)->getData()['bills'];
		$payerIds = array_values(array_unique(array_column($bills, 'payer_id')));

		// Documented, not endorsed: repetition does not look at whether the payer is still
		// active, so the deactivated member keeps being credited by every new occurrence.
		$this->assertNotEmpty($created, 'the bill still repeats');
		$this->assertSame([$ids['m1']], $payerIds);
	}

	/**
	 * The payer is exported as a quoted single cell, while the owers are exported as names
	 * joined by commas inside one cell and split back on commas. A member name containing a
	 * comma therefore round trips on the payer side and not on the ower side.
	 */
	public function testMemberNameWithACommaThroughACsvRoundTrip(): void {
		$projectId = 'pxcsv';
		$resp = $this->apiController->createProject($projectId, 'Csv');
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus());

		$comma = $this->apiController->createMember($projectId, 'Doe, John');
		$this->assertEquals(Http::STATUS_OK, $comma->getStatus(), json_encode($comma->getData()));
		$commaId = $comma->getData()['id'];
		$plainId = $this->apiController->createMember($projectId, 'Plain')->getData()['id'];

		$resp = $this->apiController->createBill(
			$projectId, '2024-01-15', 'dinner', $commaId,
			$commaId . ',' . $plainId, 20.0, Application::FREQUENCY_NO
		);
		$this->assertEquals(Http::STATUS_OK, $resp->getStatus(), json_encode($resp->getData()));

		$export = $this->apiController->exportCsvProject($projectId);
		$this->assertEquals(Http::STATUS_OK, $export->getStatus(), json_encode($export->getData()));

		$import = $this->apiController->importCsvProject($export->getData()['path']);
		$this->assertEquals(Http::STATUS_OK, $import->getStatus(), json_encode($import->getData()));
		$importedId = $import->getData()['id'];

		$names = array_column($this->localProjectService->getMembers($importedId), 'name');
		sort($names);

		try {
			// The payer is written as its own quoted cell, so the comma survives.
			$this->assertContains('Doe, John', $names, 'the payer name round trips intact');

			// The owers are written as names joined by commas inside a single cell and split
			// back on commas, so the same name arrives as two spurious members. Pre-existing
			// and unrelated to multiple payers: recorded here so a CSV change is not blamed
			// for it, and so the quoted-cell treatment the payer already gets is the one to
			// copy rather than a new ad-hoc separator.
			$this->assertSame(['Doe', 'Doe, John', 'John', 'Plain'], $names);
		} finally {
			try {
				$this->localProjectService->deleteProject($importedId);
			} catch (\Throwable) {
			}
		}
	}
}
