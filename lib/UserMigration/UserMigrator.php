<?php

declare(strict_types=1);

namespace OCA\Cospend\UserMigration;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\Project;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\ProjectService;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OCP\UserMigration\UserMigrationException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class UserMigrator implements IMigrator, ISizeEstimationMigrator {
	use TMigratorBasicVersionHandling;

	private const PATH_ROOT = Application::APP_ID . '/';
	private ProjectService $projectService;
	private IL10N $l10n;
	private ProjectMapper $projectMapper;

	public function __construct(
		ProjectService $projectService,
		ProjectMapper $projectMapper,
		IL10N $l10n
	) {
		$this->l10n = $l10n;
		$this->projectService = $projectService;
		$this->projectMapper = $projectMapper;
	}

	/**
	 * Returns an estimate of the exported data size in KiB.
	 * Should be fast, favor performance over accuracy.
	 *
	 * @since 25.0.0
	 */
	public function getEstimatedExportSize(IUser $user): int {
		$size = 100; // 100KiB for user data JSON
		return $size;
	}

	/**
	 * Export user data
	 *
	 * @throws UserMigrationException
	 * @since 24.0.0
	 */
	public function export(IUser $user, IExportDestination $exportDestination, OutputInterface $output): void {
		$output->writeln('Exporting Cospend projects in ' . self::PATH_ROOT . '…');
		$userId = $user->getUID();
		/** @var Project[] $projects */
		$projects = $this->projectMapper->getProjects($userId);
		foreach ($projects as $project) {
			try {
				$exportFilePath = self::PATH_ROOT . $project->getId() . '.csv';
				$content = '';
				foreach ($this->projectService->getJsonProject($project->getId()) as $chunk) {
					$content .= $chunk;
				}
				$exportDestination->addFileContents($exportFilePath, $content);
			} catch (Throwable $e) {
				throw new UserMigrationException('Could not export Cospend projects', 0, $e);
			}
		}
	}

	/**
	 * Import user data
	 *
	 * @throws UserMigrationException
	 * @since 24.0.0
	 */
	public function import(IUser $user, IImportSource $importSource, OutputInterface $output): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No version for ' . static::class . ', skipping import…');
			return;
		}

		$output->writeln('Importing Cospend projects from ' . self::PATH_ROOT . '…');

		$userId = $user->getUID();
		$fileList = $importSource->getFolderListing(self::PATH_ROOT);
		foreach ($fileList as $fileName) {
			try {
				$handler = $importSource->getFileAsStream(self::PATH_ROOT . $fileName);
				$projectName = preg_replace('/\.csv$/', '', $fileName);
				$this->projectService->importCsvProjectAtomicWrapper($handler, $userId, $projectName);
			} catch (Throwable $e) {
//				throw new UserMigrationException('Could not import Cospend project in ' . $fileName, 0, $e);
			}
		}
	}

	/**
	 * Returns the unique ID
	 *
	 * @since 24.0.0
	 */
	public function getId(): string {
		return 'cospend';
	}

	/**
	 * Returns the display name
	 *
	 * @since 24.0.0
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Cospend');
	}

	/**
	 * Returns the description
	 *
	 * @since 24.0.0
	 */
	public function getDescription(): string {
		return $this->l10n->t('Cospend projects');
	}
}
