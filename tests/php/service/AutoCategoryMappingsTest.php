<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Service;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the auto-categorisation feature (gh-402)
 */
class AutoCategoryMappingsTest extends TestCase {

	private LocalProjectService $localProjectService;
	private BillMapper $billMapper;
	private IAppConfig $appConfig;
	private int $memberId;
	private int $groceriesCatId;
	private int $fuelCatId;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$userManager = $c->get(IUserManager::class);
		if ($userManager->get('testautocat') === null) {
			$userManager->createUser('testautocat', 'T0T0T0AutoCat!');
		}
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('testautocat');
		if ($user !== null) {
			$user->delete();
		}
	}

	protected function setUp(): void {
		$app = new Application();
		$c = $app->getContainer();
		$this->billMapper = $c->get(BillMapper::class);
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->appConfig = $c->get(IAppConfig::class);

		$this->deleteTestProjects();
		$this->appConfig->setValueString(Application::APP_ID, 'auto_categorization_enabled', '1', lazy: true);

		$this->localProjectService->createProject('AutoCat', 'autocatproj', null, 'testautocat');
		$member = $this->localProjectService->createMember('autocatproj', 'bobby');
		$this->memberId = $member['id'];
		$this->groceriesCatId = $this->localProjectService->createCategory('autocatproj', 'Groceries', '🛒', '#00ff00');
		$this->fuelCatId = $this->localProjectService->createCategory('autocatproj', 'Fuel', '⛽', '#ff0000');
	}

	protected function tearDown(): void {
		$this->deleteTestProjects();
		$this->appConfig->setValueString(Application::APP_ID, 'auto_categorization_enabled', '1', lazy: true);
	}

	private function deleteTestProjects(): void {
		foreach (['autocatproj', 'autocattarget'] as $projId) {
			try {
				$this->localProjectService->deleteProject($projId);
			} catch (\Throwable $t) {
			}
		}
	}

	private function createBill(string $what, ?int $categoryId = null, bool $autoCategorise = true): int {
		return $this->localProjectService->createBill(
			'autocatproj', null, $what, $this->memberId, (string)$this->memberId, 10.0,
			'n', null, null, $categoryId, 0, null, 1700000000, null, null, 0, false, $autoCategorise
		);
	}

	public function testMappingCrud() {
		$mappingId = $this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		$this->assertGreaterThan(0, $mappingId);

		$mappings = $this->localProjectService->getAutoCategoryMappings('autocatproj');
		$this->assertCount(1, $mappings);
		$this->assertEquals('Aldi', $mappings[0]['bill_title']);
		$this->assertEquals($this->groceriesCatId, $mappings[0]['category_id']);

		$edited = $this->localProjectService->editAutoCategoryMapping('autocatproj', $mappingId, 'Aldi Sued', $this->fuelCatId);
		$this->assertEquals('Aldi Sued', $edited['bill_title']);
		$this->assertEquals($this->fuelCatId, $edited['category_id']);

		$this->localProjectService->deleteAutoCategoryMapping('autocatproj', $mappingId);
		$this->assertCount(0, $this->localProjectService->getAutoCategoryMappings('autocatproj'));
	}

	public function testDuplicateMappingIsRejected() {
		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		try {
			$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->fuelCatId);
			$this->fail('duplicate mapping should throw');
		} catch (CospendBasicException $e) {
			$this->assertEquals(Http::STATUS_CONFLICT, $e->getCode());
		}
		// the duplicate check is case-insensitive
		try {
			$this->localProjectService->createAutoCategoryMapping('autocatproj', 'ALDI', $this->fuelCatId);
			$this->fail('case-variant duplicate mapping should throw');
		} catch (CospendBasicException $e) {
			$this->assertEquals(Http::STATUS_CONFLICT, $e->getCode());
		}
	}

	public function testEditMappingDuplicateAndNotFound() {
		$mappingId1 = $this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		$mappingId2 = $this->localProjectService->createAutoCategoryMapping('autocatproj', 'Shell', $this->fuelCatId);

		// editing a mapping into a case-variant of another one is rejected
		try {
			$this->localProjectService->editAutoCategoryMapping('autocatproj', $mappingId2, 'aldi', $this->fuelCatId);
			$this->fail('case-variant duplicate edit should throw');
		} catch (CospendBasicException $e) {
			$this->assertEquals(Http::STATUS_CONFLICT, $e->getCode());
		}

		// changing only the case of a mapping's own title is fine
		$edited = $this->localProjectService->editAutoCategoryMapping('autocatproj', $mappingId1, 'ALDI', $this->groceriesCatId);
		$this->assertEquals('ALDI', $edited['bill_title']);

		try {
			$this->localProjectService->editAutoCategoryMapping('autocatproj', 12345678, 'Lidl', $this->groceriesCatId);
			$this->fail('editing an unknown mapping should throw');
		} catch (CospendBasicException $e) {
			$this->assertEquals(Http::STATUS_NOT_FOUND, $e->getCode());
		}
	}

	public function testAutoCategorizeBillLookup() {
		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);

		// lookup is case-insensitive
		$this->assertEquals($this->groceriesCatId, $this->localProjectService->autoCategorizeBill('autocatproj', 'aldi'));
		$this->assertEquals($this->groceriesCatId, $this->localProjectService->autoCategorizeBill('autocatproj', 'ALDI'));
		$this->assertNull($this->localProjectService->autoCategorizeBill('autocatproj', 'Lidl'));
		$this->assertNull($this->localProjectService->autoCategorizeBill('autocatproj', ''));
	}

	public function testBillCreationIsAutoCategorized() {
		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);

		$billId = $this->createBill('ALDI');
		$this->assertEquals($this->groceriesCatId, $this->billMapper->find($billId)->getCategoryId());

		// opt-out is honored
		$billId = $this->createBill('aldi', null, false);
		$this->assertEquals(0, $this->billMapper->find($billId)->getCategoryId());

		// an explicitly provided category wins over the mapping
		$billId = $this->createBill('Aldi', $this->fuelCatId);
		$this->assertEquals($this->fuelCatId, $this->billMapper->find($billId)->getCategoryId());

		// no mapping, no category
		$billId = $this->createBill('Lidl');
		$this->assertEquals(0, $this->billMapper->find($billId)->getCategoryId());
	}

	public function testBillEditionIsAutoCategorized() {
		$billId = $this->createBill('Aldi');
		$this->assertEquals(0, $this->billMapper->find($billId)->getCategoryId());

		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		$this->localProjectService->editBill(
			'autocatproj', $billId, null, null, null, null, null, 'n',
			null, null, null, null, null, null, 'edited comment'
		);
		$this->assertEquals($this->groceriesCatId, $this->billMapper->find($billId)->getCategoryId());
	}

	public function testTogglesDisableAutoCategorization() {
		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);

		// per-project toggle
		$this->localProjectService->editProject('autocatproj', null, null, null, null, null, null, null, null, '0');
		$billId = $this->createBill('Aldi');
		$this->assertEquals(0, $this->billMapper->find($billId)->getCategoryId());
		$this->localProjectService->editProject('autocatproj', null, null, null, null, null, null, null, null, '1');

		// global toggle
		$this->appConfig->setValueString(Application::APP_ID, 'auto_categorization_enabled', '0', lazy: true);
		$billId = $this->createBill('Aldi');
		$this->assertEquals(0, $this->billMapper->find($billId)->getCategoryId());
		$this->appConfig->setValueString(Application::APP_ID, 'auto_categorization_enabled', '1', lazy: true);

		$billId = $this->createBill('Aldi');
		$this->assertEquals($this->groceriesCatId, $this->billMapper->find($billId)->getCategoryId());
	}

	public function testRetroactiveAutoCategorization() {
		$billId1 = $this->createBill('Aldi');
		$billId2 = $this->createBill('aldi');
		$billId3 = $this->createBill('Shell');
		$categorizedBillId = $this->createBill('Aldi', $this->fuelCatId);

		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		$count = $this->localProjectService->autoCategorizeProjectBills('autocatproj');
		$this->assertEquals(2, $count);

		$this->assertEquals($this->groceriesCatId, $this->billMapper->find($billId1)->getCategoryId());
		$this->assertEquals($this->groceriesCatId, $this->billMapper->find($billId2)->getCategoryId());
		$this->assertEquals(0, $this->billMapper->find($billId3)->getCategoryId());
		// already categorized bills are left alone
		$this->assertEquals($this->fuelCatId, $this->billMapper->find($categorizedBillId)->getCategoryId());
	}

	public function testCategoryDeletionNullifiesMappings() {
		$mappingId = $this->localProjectService->createAutoCategoryMapping('autocatproj', 'Shell', $this->fuelCatId);
		$this->localProjectService->deleteCategory('autocatproj', $this->fuelCatId);

		$mappings = $this->localProjectService->getAutoCategoryMappings('autocatproj');
		$this->assertCount(1, $mappings);
		$this->assertEquals($mappingId, $mappings[0]['id']);
		$this->assertNull($mappings[0]['category_id']);

		// a nullified mapping does not categorize anything
		$this->assertNull($this->localProjectService->autoCategorizeBill('autocatproj', 'Shell'));
	}

	public function testCopyMappings() {
		$this->localProjectService->createProject('AutoCatTarget', 'autocattarget', null, 'testautocat');
		$targetGroceriesCatId = $this->localProjectService->createCategory('autocattarget', 'groceries', '🛒', '#00ff00');

		$aldiMappingId = $this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Shell', $this->fuelCatId);

		// Groceries matches by name (case-insensitive), Fuel does not exist in the target
		$result = $this->localProjectService->copyAutoCategoryMappings('autocatproj', 'autocattarget');
		$this->assertEquals(1, $result['imported']);
		$this->assertEquals(1, $result['skipped']);
		$this->assertCount(1, $result['errors']);

		$targetMappings = $this->localProjectService->getAutoCategoryMappings('autocattarget');
		$this->assertCount(1, $targetMappings);
		$this->assertEquals('Aldi', $targetMappings[0]['bill_title']);
		$this->assertEquals($targetGroceriesCatId, $targetMappings[0]['category_id']);

		// copying again skips the existing mapping
		$result = $this->localProjectService->copyAutoCategoryMappings('autocatproj', 'autocattarget');
		$this->assertEquals(0, $result['imported']);
		$this->assertEquals(2, $result['skipped']);

		// single-mapping copy of an already existing mapping
		$result = $this->localProjectService->copyAutoCategoryMappings('autocatproj', 'autocattarget', $aldiMappingId);
		$this->assertEquals(0, $result['imported']);
		$this->assertEquals(1, $result['skipped']);

		// single-mapping copy with an unknown id
		try {
			$this->localProjectService->copyAutoCategoryMappings('autocatproj', 'autocattarget', 12345678);
			$this->fail('copying an unknown mapping should throw');
		} catch (CospendBasicException $e) {
			$this->assertEquals(Http::STATUS_NOT_FOUND, $e->getCode());
		}
	}

	public function testProjectDeletionRemovesMappings() {
		$this->localProjectService->createAutoCategoryMapping('autocatproj', 'Aldi', $this->groceriesCatId);
		$this->assertCount(1, $this->localProjectService->getAutoCategoryMappings('autocatproj'));

		$this->localProjectService->deleteProject('autocatproj');
		$this->assertCount(0, $this->localProjectService->getAutoCategoryMappings('autocatproj'));
	}
}
