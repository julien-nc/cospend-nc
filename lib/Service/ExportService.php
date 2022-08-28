<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\Cospend\Service;

use DateTime;
use OC\User\NoUserException;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Exception\ErrorMessageException;
use OCA\Cospend\Utils;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class ExportService extends AbstractService {
	/**
	 * @var IDBConnection
	 */
	private $dbConnection;

	/**
	 * @var ProjectService
	 */
	protected $projectService;

	/**
	 * @param IL10N $translation
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IRootFolder $rootFolder
	 * @param LoggerInterface $logger
	 * @param IDBConnection $dbConnection
	 * @param ProjectService $projectService
	 */
	public function __construct(IL10N           $translation,
								IConfig         $config,
								IUserManager    $userManager,
								IRootFolder     $rootFolder,
								LoggerInterface $logger,
								IDBConnection   $dbConnection,
								ProjectService  $projectService
	) {
		parent::__construct($translation, $config, $userManager, $rootFolder, $logger);

		$this->dbConnection = $dbConnection;
		$this->projectService = $projectService;
	}

	/**
	 * auto export
	 * triggered by NC cron job
	 *
	 * export projects
	 *
	 * @return void
	 */
	public function cronAutoExport(): void {
		$queryBuilder = $this->dbConnection->getQueryBuilder();

		foreach ($this->userManager->search('') as $user) {
			$userId = $user->getUID();
			$outputPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');

			$queryBuilder->select('id', 'autoexport')
				->from('cospend_projects')
				->where(
					$queryBuilder->expr()->eq('userid', $queryBuilder->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$queryBuilder->expr()->neq('autoexport', $queryBuilder->createNamedParameter(Application::FREQUENCIES['no'], IQueryBuilder::PARAM_STR))
				);

			try {
				$request = $queryBuilder->executeQuery();
			} catch (Exception $exception) {
				$this->logger->error($exception->getMessage(), ['exception' => $exception]);

				continue;
			}

			while ($row = $request->fetch()) {
				try {
					$exportName = $row['id'] . $this->getAutoExportSuffix($row['autoexport']) . '.csv';
					$userFolder = $this->rootFolder->getUserFolder($userId);

					// check if file already exists
					if (!$userFolder->nodeExists($outputPath . '/' . $exportName)) {
						$this->exportCsvProject($row['id'], $userId, $exportName);
					}
				} catch (NotPermittedException|NoUserException $exception) {
					$this->logger->debug($exception->getMessage(), ['exception' => $exception]);
				}
			}

			$request->closeCursor();
			$queryBuilder = $queryBuilder->resetQueryParts();
		}
	}

	/**
	 * Export project in CSV
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string $userId
	 * @return array
	 */
	public function exportCsvProject(string $projectId, string $userId, ?string $name = null): array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$outputPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');

			if (!is_null($name)) {
				$filename = $name;
				if (!Utils::endswith($filename, '.csv')) {
					$filename .= '.csv';
				}
			} else {
				$filename = $projectId . '.csv';
			}

			try {
				$file = $this->createExportFile($userFolder, $outputPath, $filename);
			} catch (ErrorMessageException $exception) {
				return ['message' => $exception->getMessage()];
			}

			$projectInfo = $this->projectService->getProjectInfo($projectId);
			$bills = $this->projectService->getBills($projectId);
			$handler = $file->fopen('w');
		} catch (Exception|LockedException|NotPermittedException|NoUserException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);

			return ['message' => $this->translation->t('Access denied')];
		}

		list($memberIdToName, $memberIdToWeight, $memberIdToActive) = $this->writeMembers($handler, $projectInfo['members']);
		$this->writeBills($handler, $bills, $memberIdToName, $memberIdToWeight, $memberIdToActive);
		$this->writeCategories($handler, $projectInfo['categories']);
		$this->writePaymentModes($handler, $projectInfo['paymentmodes']);
		$this->writeCurrencies($handler, $projectInfo['currencies'], $projectInfo['currencyname']);

		fclose($handler);
		try {
			$file->touch();
		} catch (InvalidPathException|NotFoundException|NotPermittedException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);
			// ignore exception as the file is already written
		}

		return ['path' => $outputPath . '/' . $filename];
	}

	/**
	 * Export settlement plan in CSV.
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function exportCsvSettlement(string $projectId, string $userId, ?int $centeredOn = null, ?int $maxTimestamp = null): array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$outputPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
			$filename = $projectId . '-settlement.csv';

			try {
				$file = $this->createExportFile($userFolder, $outputPath, $filename);
			} catch (ErrorMessageException $exception) {
				return ['message' => $exception->getMessage()];
			}

			$settlement = $this->projectService->getProjectSettlement($projectId, $centeredOn, $maxTimestamp);
			$members = $this->projectService->getMembers($projectId);
			$handler = $file->fopen('w');
		} catch (LockedException|NotPermittedException|NoUserException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);

			return ['message' => $this->translation->t('Access denied')];
		}

		$memberIdToName = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
		}

		fwrite(
			$handler,
			'"' . $this->translation->t('Who pays?') . '",' .
			'"' . $this->translation->t('To whom?') . '",' .
			'"' . $this->translation->t('How much?') . '"' .
			"\n"
		);

		foreach ($settlement['transactions'] as $transaction) {
			fwrite(
				$handler,
				'"' . $memberIdToName[$transaction['from']] . '",' .
				'"' . $memberIdToName[$transaction['to']] . '",' .
				(float)$transaction['amount'] .
				"\n"
			);
		}

		fclose($handler);
		try {
			$file->touch();
		} catch (InvalidPathException|NotFoundException|NotPermittedException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);
			// ignore exception as the file is already written
		}

		return ['path' => $outputPath . '/' . $filename];
	}

	/**
	 * @param string $projectId
	 * @param string $userId
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $category
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param bool $showDisabled
	 * @param int|null $currencyId
	 * @return array
	 */
	public function exportCsvStatistics(string $projectId, string $userId, ?int $tsMin = null, ?int $tsMax = null,
										?int   $paymentModeId = null, ?int $category = null,
										?float $amountMin = null, ?float $amountMax = null,
										bool   $showDisabled = true, ?int $currencyId = null): array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$outputPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
			$filename = $projectId . '-stats.csv';

			try {
				$file = $this->createExportFile($userFolder, $outputPath, $filename);
			} catch (ErrorMessageException $exception) {
				return ['message' => $exception->getMessage()];
			}

			$allStats = $this->projectService->getProjectStatistics(
				$projectId, 'lowername', $tsMin, $tsMax, $paymentModeId,
				$category, $amountMin, $amountMax, $showDisabled, $currencyId
			);
			$handler = $file->fopen('w');
		} catch (Exception|LockedException|NotPermittedException|NoUserException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);

			return ['message' => $this->translation->t('Access denied')];
		}

		fwrite(
			$handler,
			'"' . $this->translation->t('Member name') . '",' .
			'"' . $this->translation->t('Paid') . '",' .
			'"' . $this->translation->t('Spent') . '",' .
			'"' . $this->translation->t('Balance') . '"' .
			"\n"
		);

		foreach ($allStats['stats'] as $stat) {
			fwrite(
				$handler,
				'"' . $stat['member']['name'] . '",' .
				(float)$stat['paid'] . ',' .
				(float)$stat['spent'] . ',' .
				(float)$stat['balance'] .
				"\n"
			);
		}

		fclose($handler);
		try {
			$file->touch();
		} catch (InvalidPathException|NotFoundException|NotPermittedException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);
			// ignore exception as the file is already written
		}

		return ['path' => $outputPath . '/' . $filename];
	}

	/**
	 * @param string $autoExport
	 * @return string
	 */
	protected function getAutoExportSuffix(string $autoExport): string {
		date_default_timezone_set('UTC');

		switch ($autoExport) {
			case Application::FREQUENCIES['daily']:
				return '_' . $this->translation->t('daily') . '_' . (new DateTime('yesterday'))->format('Y-m-d');

			case Application::FREQUENCIES['weekly']:
				return '_' . $this->translation->t('weekly') . '_' . (new DateTime('sunday last week'))->format('Y-m-d');

			case Application::FREQUENCIES['bi_weekly']:
				$exportDate = new DateTime('sunday last week');
				if ($exportDate->format('W') % 2 === 1) {
					$exportDate->modify('-1 week');
				}

				return '_' . $this->translation->t('bi_weekly') . '_' . $exportDate->format('Y-m-d');

			case Application::FREQUENCIES['semi_monthly']:
				$currentDay = (int)(new DateTime())->format('d');
				if ($currentDay === 1 || $currentDay > 15) {
					return '_' . $this->translation->t('semi_monthly') . '_' . DateTime::createFromFormat('d', 15)->format('Y-m-d');
				} else {
					return '_' . $this->translation->t('semi_monthly') . '_' . DateTime::createFromFormat('d', 1)->format('Y-m-d');
				}

			case Application::FREQUENCIES['monthly']:
				return '_' . $this->translation->t('monthly') . '_' . (new DateTime('last month'))->format('Y-m');

			case Application::FREQUENCIES['yearly']:
				return '_' . $this->translation->t('yearly') . '_' . (new DateTime('last year'))->format('Y');

			default:
				return '_unknown_frequency' . (new DateTime())->format('Y-m-d');
		}
	}

	/**
	 * Create and return the export file. If an error occurs, a error message string is returned.
	 *
	 * @param Folder $userFolder
	 * @param string $outPath
	 * @param string $filename
	 * @return File
	 * @throws ErrorMessageException
	 */
	protected function createExportFile(Folder $userFolder, string $outPath, string $filename): File {
		$folder = $this->getExportDirectory($userFolder, $outPath);

		try {
			if ($folder->nodeExists($filename)) {
				$folder->get($filename)->delete();
			}

			return $folder->newFile($filename);
		} catch (InvalidPathException|NotFoundException|NotPermittedException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);

			throw new ErrorMessageException($this->translation->t('Impossible to create %1$s', [$filename]));
		}
	}

	/**
	 * Return the directory where things will be exported. If the directory does not exist, it will be created.
	 * If an error occurs, a error message string is returned.
	 *
	 * @param Folder $userFolder
	 * @param string $outPath
	 * @return Folder
	 * @throws ErrorMessageException
	 */
	protected function getExportDirectory(Folder $userFolder, string $outPath): Folder {
		try {
			if (!$userFolder->nodeExists($outPath)) {
				$folder = $userFolder->newFolder($outPath);
			} else {
				$folder = $userFolder->get($outPath);
			}
		} catch (NotFoundException|NotPermittedException $exception) {
			$this->logger->debug($exception->getMessage(), ['exception' => $exception]);

			throw new ErrorMessageException($this->translation->t('Impossible to create %1$s', [$outPath]));
		}

		if ($folder->getType() !== FileInfo::TYPE_FOLDER) {
			throw new ErrorMessageException($this->translation->t('%1$s is not a folder', [$outPath]));
		} elseif (!$folder->isCreatable()) {
			throw new ErrorMessageException($this->translation->t('%1$s is not writeable', [$outPath]));
		} else {
			return $folder;
		}
	}

	/**
	 * @param $handler
	 * @param array $members
	 * @return array[]
	 */
	protected function writeMembers($handler, array $members): array {
		$memberIdToName = [];
		$memberIdToWeight = [];
		$memberIdToActive = [];

		fwrite(
			$handler,
			'name,weight,active,color' . "\n"
		);

		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
			$memberIdToWeight[$member['id']] = $member['weight'];
			$memberIdToActive[$member['id']] = (int)$member['activated'];

			fwrite(
				$handler,
				'"' . $member['name'] . '",' .
				(float)$member['weight'] . ',' .
				(int)$member['activated'] . ',' .
				'"' . sprintf("#%02x%02x%02x", $member['color']['r'] ?? 0, $member['color']['g'] ?? 0, $member['color']['b'] ?? 0) . '"' .
				"\n"
			);
		}

		return [$memberIdToName, $memberIdToWeight, $memberIdToActive];
	}

	/**
	 * @param $handler
	 * @param array $bills
	 * @param array $memberIdToName
	 * @param array $memberIdToWeight
	 * @param array $memberIdToActive
	 * @return void
	 */
	protected function writeBills($handler, array $bills, array $memberIdToName, array $memberIdToWeight, array $memberIdToActive): void {
		fwrite($handler, "\n");
		fwrite(
			$handler,
			'what,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,paymentmodeid,comment' . "\n"
		);

		foreach ($bills as $bill) {
			$owerNames = [];
			foreach ($bill['owers'] as $ower) {
				$owerNames[] = $ower['name'];
			}

			$payerId = $bill['payer_id'];
			fwrite(
				$handler,
				'"' . $bill['what'] . '",' .
				(float)$bill['amount'] . ',' .
				DateTime::createFromFormat('U', $bill['timestamp'])->format('Y-m-d') . ',' .
				$bill['timestamp'] . ',' .
				'"' . $memberIdToName[$payerId] . '",' .
				(float)$memberIdToWeight[$payerId] . ',' .
				$memberIdToActive[$payerId] . ',' .
				'"' . implode(',', $owerNames) . '",' .
				$bill['repeat'] . ',' .
				$bill['repeatfreq'] . ',' .
				$bill['repeatallactive'] . ',' .
				$bill['repeatuntil'] . ',' .
				$bill['categoryid'] . ',' .
				$bill['paymentmode'] . ',' .
				$bill['paymentmodeid'] . ',' .
				'"' . urlencode($bill['comment']) . '"' .
				"\n"
			);
		}
	}

	/**
	 * @param $handler
	 * @param array $categories
	 * @return void
	 */
	protected function writeCategories($handler, array $categories): void {
		if (count($categories) > 0) {
			fwrite($handler, "\n");
			fwrite($handler, 'categoryname,categoryid,icon,color' . "\n");

			foreach ($categories as $id => $category) {
				fwrite(
					$handler,
					'"' . $category['name'] . '",' .
					(int)$id . ',' .
					'"' . $category['icon'] . '",' .
					'"' . $category['color'] . '"' .
					"\n"
				);
			}
		}
	}

	/**
	 * @param $handler
	 * @param array $paymentModes
	 * @return void
	 */
	protected function writePaymentModes($handler, array $paymentModes): void {
		if (count($paymentModes) > 0) {
			fwrite($handler, "\n");
			fwrite($handler, 'paymentmodename,paymentmodeid,icon,color' . "\n");

			foreach ($paymentModes as $id => $paymentMode) {
				fwrite(
					$handler,
					'"' . $paymentMode['name'] . '",' .
					(int)$id . ',' .
					'"' . $paymentMode['icon'] . '",' .
					'"' . $paymentMode['color'] . '"' .
					"\n"
				);
			}
		}
	}

	/**
	 * @param $handler
	 * @param array $currencies
	 * @param string|null $mainCurrencyName
	 * @return void
	 */
	protected function writeCurrencies($handler, array $currencies, ?string $mainCurrencyName): void {
		if (count($currencies) > 0) {
			fwrite($handler, "\n");
			fwrite($handler, 'currencyname,exchange_rate' . "\n");

			// main currency
			fwrite($handler, '"' . $mainCurrencyName . '",1' . "\n");

			foreach ($currencies as $currency) {
				fwrite(
					$handler,
					'"' . $currency['name'] . '",' .
					(float)$currency['exchange_rate'] .
					"\n"
				);
			}
		}
	}
}
