<?php

declare(strict_types=1);

namespace OCA\Cospend\UserMigration;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\Project;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Service\CospendService;
use OCA\Cospend\Service\LocalProjectService;
use OCP\IConfig;
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

	private const PATH_ROOT = Application::APP_ID;
	private const PROJECTS_PATH = self::PATH_ROOT . '/projects';
	private const SETTINGS_PATH = self::PATH_ROOT . '/settings.json';

	public function __construct(
		private LocalProjectService $projectService,
		private ProjectMapper $projectMapper,
		private CospendService $cospendService,
		private IConfig $config,
		private IL10N $l10n,
	) {
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
		$output->writeln('Exporting Cospend projects in ' . self::PROJECTS_PATH . '…');
		$userId = $user->getUID();
		/** @var Project[] $projects */
		$projects = $this->projectMapper->getProjects($userId);
		foreach ($projects as $project) {
			try {
				$exportFilePath = self::PROJECTS_PATH . '/' . $project->getId() . '.csv';
				$content = '';
				foreach ($this->projectService->getJsonProject($project->getId()) as $chunk) {
					$content .= $chunk;
				}
				$exportDestination->addFileContents($exportFilePath, $content);
			} catch (Throwable $e) {
				throw new UserMigrationException('Could not export Cospend projects', 0, $e);
			}
		}

		// settings
		$userSettings = [];
		foreach ($this->config->getUserKeys($userId, Application::APP_ID) as $key) {
			$value = $this->config->getUserValue($userId, Application::APP_ID, $key, null);
			if ($value !== null) {
				$userSettings[$key] = $value;
			}
		}
		$exportDestination->addFileContents(self::SETTINGS_PATH, json_encode($userSettings));
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

		$output->writeln('Importing Cospend projects from ' . self::PROJECTS_PATH . '…');
		// no idea why this does not work
		// zip->locateName($path) works with the trashbin app but not here
		/*
		if (!$importSource->pathExists(self::PATH_ROOT)) {
			$output->writeln('No Cospend directory, skipping import…');
			return;
		}
		if (!$importSource->pathExists(self::PROJECTS_PATH)) {
			$output->writeln('No "projects" directory for Cospend, skipping import…');
			return;
		}
		*/

		$userId = $user->getUID();
		$fileList = $importSource->getFolderListing(self::PROJECTS_PATH . '/');
		foreach ($fileList as $fileName) {
			try {
				$handler = $importSource->getFileAsStream(self::PROJECTS_PATH . '/' . $fileName);
				$projectName = preg_replace('/\.csv$/', '', $fileName);
				$this->cospendService->importCsvProjectAtomicWrapper($handler, $userId, $projectName);
			} catch (Throwable $e) {
				// throw new UserMigrationException('Could not import Cospend project in ' . $fileName, 0, $e);
				$output->writeln('Error when importing Cospend project in ' . $fileName);
			}
		}

		// settings
		if ($importSource->pathExists(self::SETTINGS_PATH)) {
			$settingsFileContent = $importSource->getFileContents(self::SETTINGS_PATH);
			$settings = json_decode($settingsFileContent, true);
			if ($settings !== false && is_array($settings)) {
				foreach ($settings as $key => $value) {
					$this->config->setUserValue($userId, Application::APP_ID, $key, (string)$value);
				}
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
		return $this->l10n->t('Cospend projects and user settings');
	}
}
