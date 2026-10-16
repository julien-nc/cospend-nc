<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Service;

use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Controller\ApiController;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ImportExportTest extends TestCase {

	private const PROJECT_ID = 'roundtrip';
	private const EXPORT_PATH = '/Cospend/roundtrip.csv';

	private LocalProjectService $localProjectService;
	private ApiController $apiController;
	private IRootFolder $rootFolder;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$userManager = $c->get(IUserManager::class);
		foreach (['test', 'test2', 'test3'] as $userId) {
			$user = $userManager->get($userId);
			if ($user !== null) {
				$user->delete();
			}
		}

		$u1 = $userManager->createUser('test', 'T0T0T0');
		$u1->setSystemEMailAddress('toto@toto.net');
		$u2 = $userManager->createUser('test2', 'T0T0T0');
		$u3 = $userManager->createUser('test3', 'T0T0T0');

		$groupManager = $c->get(IGroupManager::class);
		$group1 = $groupManager->get('group1test');
		if ($group1 !== null) {
			$group1->delete();
		}
		$group2 = $groupManager->get('group2test');
		if ($group2 !== null) {
			$group2->delete();
		}
		$groupManager->createGroup('group1test');
		$groupManager->get('group1test')->addUser($u1);
		$groupManager->createGroup('group2test');
		$groupManager->get('group2test')->addUser($u2);
	}

	protected function setUp(): void {
		$request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();

		$app = new Application();
		$c = $app->getContainer();
		$this->localProjectService = $c->get(LocalProjectService::class);
		$this->rootFolder = $c->get(IRootFolder::class);
		$this->apiController = new ApiController(
			Application::APP_ID,
			$request,
			$c->get(IManager::class),
			$c->get(IL10N::class),
			$c->get(\OCA\Cospend\Db\BillMapper::class),
			$c->get(\OCA\Cospend\Db\ProjectMapper::class),
			$this->localProjectService,
			$c->get(CospendService::class),
			$c->get(ActivityManager::class),
			$this->rootFolder,
			'test'
		);

		$this->deleteTestArtifacts();
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		foreach (['test', 'test2', 'test3'] as $userId) {
			$user = $userManager->get($userId);
			if ($user !== null) {
				$user->delete();
			}
		}

		$groupManager = $c->get(IGroupManager::class);
		$group1 = $groupManager->get('group1test');
		if ($group1 !== null) {
			$group1->delete();
		}
		$group2 = $groupManager->get('group2test');
		if ($group2 !== null) {
			$group2->delete();
		}
	}

	protected function tearDown(): void {
		$this->deleteTestArtifacts();
	}

	public function testProjectExportAndImportRoundTrip(): void {
		$createdProject = $this->localProjectService->createProject(self::PROJECT_ID, self::PROJECT_ID, null, 'test');
		$this->assertSame(self::PROJECT_ID, $createdProject['id']);

		$alice = $this->localProjectService->createMember(self::PROJECT_ID, 'Alice', 1.0, true, '#112233');
		$bob = $this->localProjectService->createMember(self::PROJECT_ID, 'Bob', 2.0, true, '#445566');
		$carol = $this->localProjectService->createMember(self::PROJECT_ID, 'Carol', 1.5, false, '#778899');

		$foodCategoryResponse = $this->apiController->createCategory(self::PROJECT_ID, 'Food', 'fork', '#AA5500');
		$this->assertSame(Http::STATUS_OK, $foodCategoryResponse->getStatus());
		$foodCategoryId = $foodCategoryResponse->getData();

		$travelCategoryResponse = $this->apiController->createCategory(self::PROJECT_ID, 'Travel', 'train', '#0055AA');
		$this->assertSame(Http::STATUS_OK, $travelCategoryResponse->getStatus());
		$travelCategoryId = $travelCategoryResponse->getData();

		$cashPaymentModeResponse = $this->apiController->createPaymentMode(self::PROJECT_ID, 'Cash', 'cash', '#228833');
		$this->assertSame(Http::STATUS_OK, $cashPaymentModeResponse->getStatus());
		$cashPaymentModeId = $cashPaymentModeResponse->getData();

		$wirePaymentModeResponse = $this->apiController->createPaymentMode(self::PROJECT_ID, 'Wire', 'bank', '#663399');
		$this->assertSame(Http::STATUS_OK, $wirePaymentModeResponse->getStatus());
		$wirePaymentModeId = $wirePaymentModeResponse->getData();

		$this->localProjectService->editProject(self::PROJECT_ID, self::PROJECT_ID, null, null, 'USD');
		$this->localProjectService->createCurrency(self::PROJECT_ID, 'CHF', 0.92);

		$dinnerTimestamp = 1705276800;
		$trainTimestamp = 1706745600;

		$dinnerResponse = $this->apiController->createBill(
			self::PROJECT_ID,
			null,
			'Dinner',
			$alice['id'],
			$alice['id'] . ',' . $bob['id'],
			42.5,
			Application::FREQUENCY_NO,
			null,
			$cashPaymentModeId,
			$foodCategoryId,
			0,
			null,
			$dinnerTimestamp,
			'pizza & drinks',
			1,
		);
		$this->assertSame(Http::STATUS_OK, $dinnerResponse->getStatus());

		$trainResponse = $this->apiController->createBill(
			self::PROJECT_ID,
			null,
			'Train tickets',
			$bob['id'],
			$alice['id'] . ',' . $bob['id'] . ',' . $carol['id'],
			99.99,
			Application::FREQUENCY_MONTHLY,
			null,
			$wirePaymentModeId,
			$travelCategoryId,
			1,
			'2024-06-01',
			$trainTimestamp,
			'',
			2,
		);
		$this->assertSame(Http::STATUS_OK, $trainResponse->getStatus());

		$originalProjectInfo = $this->localProjectService->getProjectInfo(self::PROJECT_ID);
		$originalBills = $this->localProjectService->getBills(self::PROJECT_ID)['bills'];

		$exportResponse = $this->apiController->exportCsvProject(self::PROJECT_ID);
		$this->assertSame(Http::STATUS_OK, $exportResponse->getStatus());
		$this->assertSame(['path' => self::EXPORT_PATH], $exportResponse->getData());

		$exportedContent = $this->getUserFileContent(self::EXPORT_PATH);
		$this->assertStringContainsString("name,weight,active,color\n", $exportedContent);
		$this->assertStringContainsString('"Alice",1,1,"#112233"', $exportedContent);
		$this->assertStringContainsString('"Bob",2,1,"#445566"', $exportedContent);
		$this->assertStringContainsString('"Carol",1.5,0,"#778899"', $exportedContent);
		$this->assertStringContainsString("\nwhat,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,paymentmodeid,comment,deleted,payers\n", $exportedContent);
		$this->assertStringContainsString(
			'"Dinner",42.5,2024-01-15,1705276800,"Alice",1,1,',
			$exportedContent
		);
		$this->assertStringContainsString(
			',n,1,0,,' . $foodCategoryId . ',n,' . $cashPaymentModeId . ',"pizza+%26+drinks",0,""' . "\n",
			$exportedContent
		);
		$this->assertStringContainsString(
			'"Train tickets",99.99,2024-02-01,1706745600,"Bob",2,1,',
			$exportedContent
		);
		$this->assertStringContainsString(
			',m,2,1,2024-06-01,' . $travelCategoryId . ',n,' . $wirePaymentModeId . ',"",0,""' . "\n",
			$exportedContent
		);
		$this->assertStringContainsString("\ncategoryname,categoryid,icon,color\n", $exportedContent);
		$this->assertStringContainsString('"Food",' . $foodCategoryId . ',"fork","#AA5500"', $exportedContent);
		$this->assertStringContainsString('"Travel",' . $travelCategoryId . ',"train","#0055AA"', $exportedContent);
		$this->assertStringContainsString("\npaymentmodename,paymentmodeid,icon,color\n", $exportedContent);
		$this->assertStringContainsString('"Cash",' . $cashPaymentModeId . ',"cash","#228833"', $exportedContent);
		$this->assertStringContainsString('"Wire",' . $wirePaymentModeId . ',"bank","#663399"', $exportedContent);
		$this->assertStringContainsString("\ncurrencyname,exchange_rate\n", $exportedContent);
		$this->assertStringContainsString('"USD",1', $exportedContent);
		$this->assertStringContainsString('"CHF",0.92', $exportedContent);

		$importResponse = $this->apiController->importCsvProject(self::EXPORT_PATH);
		$this->assertSame(Http::STATUS_OK, $importResponse->getStatus());
		$importedProjectInfo = $importResponse->getData();
		$this->assertSame(self::PROJECT_ID . '-1', $importedProjectInfo['id']);
		$this->assertSame(self::PROJECT_ID, $importedProjectInfo['name']);

		$importedBills = $this->localProjectService->getBills($importedProjectInfo['id'])['bills'];

		$this->assertSame(
			$this->normalizeProjectSnapshot($originalProjectInfo, $originalBills),
			$this->normalizeProjectSnapshot($importedProjectInfo, $importedBills),
		);
	}

	public function testMultiPayerBillSurvivesExportAndImport(): void {
		$createdProject = $this->localProjectService->createProject(self::PROJECT_ID, self::PROJECT_ID, null, 'test');
		$this->assertSame(self::PROJECT_ID, $createdProject['id']);

		$alice = $this->localProjectService->createMember(self::PROJECT_ID, 'Alice', 1.0, true, '#112233');
		$bob = $this->localProjectService->createMember(self::PROJECT_ID, 'Bob', 1.0, true, '#445566');
		// The comma has to survive inside the JSON cell, and Zoe pays without owing anything,
		// so the import only learns she exists from `payers`.
		$zoe = $this->localProjectService->createMember(self::PROJECT_ID, 'Zoe, the banker', 1.0, true, '#778899');

		$billResponse = $this->apiController->createBill(
			self::PROJECT_ID,
			null,
			'Hotel',
			$alice['id'],
			$alice['id'] . ',' . $bob['id'],
			100.0,
			Application::FREQUENCY_NO,
			null,
			null,
			null,
			0,
			null,
			1705276800,
			'',
			1,
			[
				['id' => $alice['id'], 'amount' => 60.0],
				['id' => $zoe['id'], 'amount' => 40.0],
			],
		);
		$this->assertSame(Http::STATUS_OK, $billResponse->getStatus());

		$originalProjectInfo = $this->localProjectService->getProjectInfo(self::PROJECT_ID);
		$originalBills = $this->localProjectService->getBills(self::PROJECT_ID)['bills'];
		$this->assertCount(2, $originalBills[0]['payers']);

		$exportResponse = $this->apiController->exportCsvProject(self::PROJECT_ID);
		$this->assertSame(Http::STATUS_OK, $exportResponse->getStatus());
		$exportedContent = $this->getUserFileContent(self::EXPORT_PATH);
		$this->assertStringContainsString(
			',"[{""name"":""Alice"",""amount"":60},{""name"":""Zoe, the banker"",""amount"":40}]"' . "\n",
			$exportedContent
		);

		$importResponse = $this->apiController->importCsvProject(self::EXPORT_PATH);
		$this->assertSame(Http::STATUS_OK, $importResponse->getStatus());
		$importedProjectInfo = $importResponse->getData();
		$importedBills = $this->localProjectService->getBills($importedProjectInfo['id'])['bills'];

		$this->assertSame(
			$this->normalizeProjectSnapshot($originalProjectInfo, $originalBills),
			$this->normalizeProjectSnapshot($importedProjectInfo, $importedBills),
		);

		// and the balances the split produces come back identical, which is the point of it
		$this->assertEqualsWithDelta(
			array_values(array_map(
				static fn (array $m) => $m['balance'],
				$this->sortedBalancesByName($originalProjectInfo)
			)),
			array_values(array_map(
				static fn (array $m) => $m['balance'],
				$this->sortedBalancesByName($this->localProjectService->getProjectInfo($importedProjectInfo['id']))
			)),
			0.0001,
		);
	}

	/**
	 * @param array $projectInfo
	 * @return array<string, array{balance: float}>
	 */
	private function sortedBalancesByName(array $projectInfo): array {
		$byName = [];
		foreach ($projectInfo['members'] as $member) {
			$byName[$member['name']] = ['balance' => (float)$projectInfo['balance'][$member['id']]];
		}
		ksort($byName);
		return $byName;
	}

	/**
	 * A CSV written before the `payers` column existed must import exactly as it did then.
	 *
	 * The reader is header-keyed, so the column is simply absent here — this pins that its
	 * absence produces no payer rows and changes nothing else. Verified against the real thing
	 * as well: the same file imported by the pre-change code and by this one produces
	 * byte-identical projects.
	 */
	public function testLegacyCsvWithoutPayersColumnStillImports(): void {
		$legacy = <<<CSV
			name,weight,active,color
			"Alice",1,1,"#112233"
			"Bob",2,1,"#445566"
			"Carol",1.5,0,"#778899"

			what,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,paymentmodeid,comment,deleted
			"Dinner",42.5,2024-01-15,1705276800,"Alice",1,1,"Alice,Bob",n,1,0,,1001,n,2001,"pizza+%26+drinks",0
			"Train tickets",99.99,2024-02-01,1706745600,"Bob",2,1,"Alice,Bob,Carol",m,2,1,2024-06-01,1002,n,2002,"",0
			"Refund to Alice",12,2023-11-05,1699142400,"Carol",1.5,0,"Alice",n,1,0,,-11,n,0,"",1
			"Weekly coffee",3.75,2024-03-01,1709251200,"Alice",1,1,"Alice",w,1,0,,0,n,0,"with+a+comma%2C+and+%22quotes%22",0

			categoryname,categoryid,icon,color
			"Food",1001,"fork","#AA5500"
			"Travel",1002,"train","#0055AA"

			paymentmodename,paymentmodeid,icon,color
			"Cash",2001,"cash","#228833"
			"Wire",2002,"bank","#663399"

			currencyname,exchange_rate
			"USD",1
			"CHF",0.92

			CSV;

		$this->writeUserFile(self::EXPORT_PATH, $legacy);

		$importResponse = $this->apiController->importCsvProject(self::EXPORT_PATH);
		$this->assertSame(Http::STATUS_OK, $importResponse->getStatus());
		$projectInfo = $importResponse->getData();

		$memberNames = array_map(static fn (array $m) => $m['name'], $projectInfo['members']);
		sort($memberNames);
		$this->assertSame(['Alice', 'Bob', 'Carol'], $memberNames);
		$byName = [];
		foreach ($projectInfo['members'] as $member) {
			$byName[$member['name']] = $member;
		}
		$this->assertSame(2.0, (float)$byName['Bob']['weight']);
		$this->assertFalse((bool)$byName['Carol']['activated']);
		$this->assertSame('USD', $projectInfo['currencyname']);

		$bills = $this->localProjectService->getBills($projectInfo['id'], deleted: null)['bills'];
		$this->assertCount(4, $bills);

		$categoryNames = [];
		foreach ($projectInfo['categories'] as $id => $category) {
			$categoryNames[(int)$id] = $category['name'];
		}
		$paymentModeNames = [];
		foreach ($projectInfo['paymentmodes'] as $id => $paymentMode) {
			$paymentModeNames[(int)$id] = $paymentMode['name'];
		}
		$memberNamesById = [];
		foreach ($projectInfo['members'] as $member) {
			$memberNamesById[$member['id']] = $member['name'];
		}

		$byWhat = [];
		foreach ($bills as $bill) {
			// the whole point: a file with no payers column creates no payer rows at all
			$this->assertSame([], $bill['payers'], $bill['what']);
			$this->assertFalse($bill['payersFallback'], $bill['what']);
			$owerNames = array_map(static fn (array $o) => $memberNamesById[$o['id']], $bill['owers']);
			sort($owerNames);
			$byWhat[$bill['what']] = [
				'amount' => (float)$bill['amount'],
				'payer' => $memberNamesById[$bill['payer_id']],
				'owers' => $owerNames,
				'timestamp' => (int)$bill['timestamp'],
				'repeat' => $bill['repeat'],
				'repeatfreq' => (int)$bill['repeatfreq'],
				'repeatallactive' => (int)$bill['repeatallactive'],
				'repeatuntil' => $bill['repeatuntil'],
				'category' => $categoryNames[$bill['categoryid']] ?? $bill['categoryid'],
				'paymentmode' => $paymentModeNames[$bill['paymentmodeid']] ?? null,
				'comment' => $bill['comment'],
				'deleted' => (int)$bill['deleted'],
			];
		}

		$this->assertSame([
			'amount' => 42.5,
			'payer' => 'Alice',
			'owers' => ['Alice', 'Bob'],
			'timestamp' => 1705276800,
			'repeat' => 'n',
			'repeatfreq' => 1,
			'repeatallactive' => 0,
			'repeatuntil' => null,
			'category' => 'Food',
			'paymentmode' => 'Cash',
			// urlencoded on the way out, so `&` has to survive the round trip
			'comment' => 'pizza & drinks',
			'deleted' => 0,
		], $byWhat['Dinner']);

		$this->assertSame([
			'amount' => 99.99,
			'payer' => 'Bob',
			'owers' => ['Alice', 'Bob', 'Carol'],
			'timestamp' => 1706745600,
			'repeat' => 'm',
			'repeatfreq' => 2,
			'repeatallactive' => 1,
			'repeatuntil' => '2024-06-01',
			'category' => 'Travel',
			'paymentmode' => 'Wire',
			'comment' => '',
			'deleted' => 0,
		], $byWhat['Train tickets']);

		// a built-in category id is negative and is NOT remapped, unlike the two above
		$this->assertSame(Application::CATEGORY_REIMBURSEMENT, $byWhat['Refund to Alice']['category']);
		$this->assertSame(1, $byWhat['Refund to Alice']['deleted']);
		$this->assertSame('Carol', $byWhat['Refund to Alice']['payer']);

		$this->assertSame('w', $byWhat['Weekly coffee']['repeat']);
		$this->assertSame('with a comma, and "quotes"', $byWhat['Weekly coffee']['comment']);

		$balancesByName = [];
		foreach ($projectInfo['members'] as $member) {
			$balancesByName[$member['name']] = round((float)$projectInfo['balance'][$member['id']], 6);
		}
		ksort($balancesByName);
		$this->assertSame(['Alice' => 6.113333, 'Bob' => 27.216667, 'Carol' => -33.33], $balancesByName);
	}

	/**
	 * The oldest shape: only the columns the reader actually requires. Every optional one is
	 * absent, `payers` among them.
	 */
	public function testMinimalLegacyCsvStillImports(): void {
		$this->writeUserFile(self::EXPORT_PATH, <<<CSV
			what,amount,date,payer_name,payer_weight,owers
			"Beer",10,2024-01-15,"Anna",1,"Anna,Ben"
			"Taxi",25.5,2024-01-16,"Ben",2,"Anna,Ben"

			CSV);

		$importResponse = $this->apiController->importCsvProject(self::EXPORT_PATH);
		$this->assertSame(Http::STATUS_OK, $importResponse->getStatus());
		$projectInfo = $importResponse->getData();

		$bills = $this->localProjectService->getBills($projectInfo['id'])['bills'];
		$this->assertCount(2, $bills);
		foreach ($bills as $bill) {
			$this->assertSame([], $bill['payers']);
			$this->assertFalse($bill['payersFallback']);
		}

		$balancesByName = [];
		foreach ($projectInfo['members'] as $member) {
			$balancesByName[$member['name']] = round((float)$projectInfo['balance'][$member['id']], 6);
		}
		ksort($balancesByName);
		$this->assertSame(['Anna' => -7.75, 'Ben' => 7.75], $balancesByName);
	}

	private function writeUserFile(string $path, string $content): void {
		$userFolder = $this->rootFolder->getUserFolder('test');
		if ($userFolder->nodeExists($path)) {
			$userFolder->get($path)->delete();
		}
		$userFolder->newFile($path, $content);
	}

	private function deleteTestArtifacts(): void {
		for ($suffix = 0; $suffix <= 5; $suffix++) {
			$projectId = $suffix === 0 ? self::PROJECT_ID : self::PROJECT_ID . '-' . $suffix;
			try {
				$this->localProjectService->deleteProject($projectId);
			} catch (\Throwable) {
			}
		}

		$userFolder = $this->rootFolder->getUserFolder('test');
		if ($userFolder->nodeExists(self::EXPORT_PATH)) {
			$userFolder->get(self::EXPORT_PATH)->delete();
		}
	}

	private function getUserFileContent(string $path): string {
		$userFolder = $this->rootFolder->getUserFolder('test');
		$this->assertTrue($userFolder->nodeExists($path));
		$file = $userFolder->get($path);
		$this->assertInstanceOf(File::class, $file);
		$handle = $file->fopen('r');
		$this->assertNotFalse($handle);
		$content = stream_get_contents($handle);
		fclose($handle);
		$this->assertIsString($content);
		return $content;
	}

	private function normalizeProjectSnapshot(array $projectInfo, array $bills): array {
		$memberNamesById = [];
		$members = array_map(function (array $member) use (&$memberNamesById) {
			$memberNamesById[$member['id']] = $member['name'];
			return [
				'name' => $member['name'],
				'weight' => (float)$member['weight'],
				'activated' => (bool)$member['activated'],
				'color' => $this->rgbArrayToHex($member['color']),
			];
		}, $projectInfo['members']);
		usort($members, static fn (array $a, array $b) => [$a['name']] <=> [$b['name']]);

		$categoryNamesById = [];
		$categories = [];
		foreach ($projectInfo['categories'] as $category) {
			$categoryNamesById[$category['id']] = $category['name'];
			$categories[] = [
				'name' => $category['name'],
				'icon' => $category['icon'],
				'color' => $category['color'],
			];
		}
		usort($categories, static fn (array $a, array $b) => [$a['name'], $a['icon'], $a['color']] <=> [$b['name'], $b['icon'], $b['color']]);

		$paymentModeNamesById = [];
		$paymentModes = [];
		foreach ($projectInfo['paymentmodes'] as $paymentMode) {
			$paymentModeNamesById[$paymentMode['id']] = $paymentMode['name'];
			$paymentModes[] = [
				'name' => $paymentMode['name'],
				'icon' => $paymentMode['icon'],
				'color' => $paymentMode['color'],
			];
		}
		usort($paymentModes, static fn (array $a, array $b) => [$a['name'], $a['icon'], $a['color']] <=> [$b['name'], $b['icon'], $b['color']]);

		$currencies = array_map(static fn (array $currency) => [
			'name' => $currency['name'],
			'exchange_rate' => (float)$currency['exchange_rate'],
		], $projectInfo['currencies']);
		usort($currencies, static fn (array $a, array $b) => [$a['name']] <=> [$b['name']]);

		$normalizedBills = array_map(function (array $bill) use ($memberNamesById, $categoryNamesById, $paymentModeNamesById) {
			$owerNames = array_map(static function (array $ower) use ($memberNamesById) {
				return $ower['name'] ?? $memberNamesById[$ower['id']];
			}, $bill['owers']);
			sort($owerNames);

			// Named, and in stored order: comparing ids would pass on an import that dropped
			// every secondary payer, which is exactly the regression this snapshot must catch.
			$payers = array_map(static function (array $payer) use ($memberNamesById) {
				return [$memberNamesById[$payer['id']], (float)$payer['amount']];
			}, $bill['payers'] ?? []);

			return [
				'what' => $bill['what'],
				'comment' => $bill['comment'] ?? '',
				'payer' => $memberNamesById[$bill['payer_id']],
				'payers' => $payers,
				'owers' => $owerNames,
				'amount' => (float)$bill['amount'],
				'timestamp' => (int)$bill['timestamp'],
				'repeat' => $bill['repeat'],
				'repeatfreq' => (int)$bill['repeatfreq'],
				'repeatallactive' => (int)$bill['repeatallactive'],
				'repeatuntil' => $this->normalizeNullableString($bill['repeatuntil'] ?? null),
				// "none" reaches us as 0, not null, so look the id up rather than testing it
				'category' => $categoryNamesById[$bill['categoryid']] ?? null,
				'paymentmode' => $paymentModeNamesById[$bill['paymentmodeid']] ?? $this->normalizeNullableString($bill['paymentmode'] ?? null),
				'deleted' => (int)$bill['deleted'],
			];
		}, $bills);
		usort($normalizedBills, static fn (array $a, array $b) => [$a['timestamp'], $a['what']] <=> [$b['timestamp'], $b['what']]);

		return [
			'name' => $projectInfo['name'],
			'currencyname' => $projectInfo['currencyname'],
			'members' => $members,
			'categories' => $categories,
			'paymentmodes' => $paymentModes,
			'currencies' => $currencies,
			'bills' => $normalizedBills,
		];
	}

	private function rgbArrayToHex(array $color): string {
		return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
	}

	private function normalizeNullableString(?string $value): ?string {
		return $value === '' ? null : $value;
	}
}
