<?php

namespace OCA\Cospend\Tests\Service;

use OC\User\NoUserException;
use OCA\Cospend\Service\ExportService;
use OCA\Cospend\Service\ProjectService;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class ExportServiceTest extends TestCase {
	/**
	 * @var MockObject|IL10N
	 */
	private $translation;

	/**
	 * @var MockObject|IConfig
	 */
	private $config;

	/**
	 * @var MockObject|IUserManager
	 */
	private $userManager;

	/**
	 * @var MockObject|IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var MockObject|LoggerInterface
	 */
	private $logger;

	/**
	 * @var MockObject|IDBConnection
	 */
	private $dbConnection;

	/**
	 * @var MockObject|ProjectService
	 */
	private $projectService;

	/**
	 * @var ExportService
	 */
	private $exportService;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->translation = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->projectService = $this->createMock(ProjectService::class);

		$this->exportService = new ExportService(
			$this->translation,
			$this->config,
			$this->userManager,
			$this->rootFolder,
			$this->logger,
			$this->dbConnection,
			$this->projectService
		);
	}

	public function testExportCsvProjectUserFolderNoPermission(): void {
		$this->rootFolder
			->method('getUserFolder')
			->willThrowException(new NotPermittedException('not permitted exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Access denied')],
			$this->exportService->exportCsvProject('project-id', 'user-id')
		);
	}

	public function testExportCsvProjectUserDoesNotExist(): void {
		$this->rootFolder
			->method('getUserFolder')
			->willThrowException(new NoUserException('no user exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Access denied')],
			$this->exportService->exportCsvProject('project-id', 'user-id')
		);
	}

	public function testExportCsvProjectFolderCannotBeCreated(): void {
		$projectId = 'project-id';
		$userId = 'user-id';

		$userFolder = $this->createStub(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->with($userId)
			->willReturn($userFolder);

		$outputPath = 'output-path';
		$this->config
			->method('getUserValue')
			->with($userId)
			->willReturn($outputPath);

		$userFolder->method('nodeExists')
			->with($outputPath)
			->willReturn(false);
		$userFolder->method('newFolder')
			->with($outputPath)
			->willThrowException(new NotPermittedException('not permitted exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);

		$userFolder->method('nodeExists')
			->with($outputPath)
			->willReturn(true);
		$userFolder->method('get')
			->with($outputPath)
			->willThrowException(new NotFoundException('not found exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);
	}

	public function testExportCsvProjectFolderIsNotAFolder(): void {
		$projectId = 'project-id';
		$userId = 'user-id';

		$userFolder = $this->createStub(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->with($userId)
			->willReturn($userFolder);

		$outputPath = 'output-path';
		$this->config
			->method('getUserValue')
			->with($userId)
			->willReturn($outputPath);

		$exportFolder = $this->createStub(Folder::class);
		$userFolder->method('nodeExists')
			->with($outputPath)
			->willReturn(true);
		$userFolder->method('get')
			->with($outputPath)
			->willReturn($exportFolder);

		$exportFolder->method('getType')
			->willReturn(FileInfo::TYPE_FILE);
		$this->assertEquals(
			['message' => $this->translation->t('%1$s is not a folder', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);
	}

	public function testExportCsvProjectFolderNotWritable(): void {
		$projectId = 'project-id';
		$userId = 'user-id';

		$userFolder = $this->createStub(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->with($userId)
			->willReturn($userFolder);

		$outputPath = 'output-path';
		$this->config
			->method('getUserValue')
			->with($userId)
			->willReturn($outputPath);

		$exportFolder = $this->createStub(Folder::class);
		$userFolder->method('nodeExists')
			->with($outputPath)
			->willReturn(true);
		$userFolder->method('get')
			->with($outputPath)
			->willReturn($exportFolder);

		$exportFolder->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$exportFolder->method('isCreatable')
			->willReturn(false);
		$this->assertEquals(
			['message' => $this->translation->t('%1$s is not writeable', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);
	}

	public function testExportCsvProjectOldFileCannotBeDeleted(): void {
		$projectId = 'project-id';
		$userId = 'user-id';

		$userFolder = $this->createStub(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->with($userId)
			->willReturn($userFolder);

		$outputPath = 'output-path';
		$this->config
			->method('getUserValue')
			->with($userId)
			->willReturn($outputPath);

		$exportFolder = $this->createStub(Folder::class);
		$userFolder->method('nodeExists')
			->with($outputPath)
			->willReturn(true);
		$userFolder->method('get')
			->with($outputPath)
			->willReturn($exportFolder);

		$exportFolder->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$exportFolder->method('isCreatable')
			->willReturn(true);

		$filename = $projectId . '.csv';

		$exportFolder->method('nodeExists')
			->with($filename)
			->willReturn(true);
		$exportFolder->method('get')
			->with($filename)
			->willThrowException(new NotFoundException('not found exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);

		$fileNode = $this->createStub(Node::class);
		$exportFolder->method('get')
			->with($filename)
			->willReturn($fileNode);
		$fileNode->method('delete')
			->willThrowException(new InvalidPathException('invalid path exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);

		$fileNode->method('delete')
			->willThrowException(new NotFoundException('not found exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);

		$fileNode->method('delete')
			->willThrowException(new NotPermittedException('not permitted exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);
	}

	public function testExportCsvProjectFileCannotBeCreated(): void {
		$projectId = 'project-id';
		$userId = 'user-id';

		$userFolder = $this->createStub(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->with($userId)
			->willReturn($userFolder);

		$outputPath = 'output-path';
		$this->config
			->method('getUserValue')
			->with($userId)
			->willReturn($outputPath);

		$exportFolder = $this->createStub(Folder::class);
		$userFolder->method('nodeExists')
			->with($outputPath)
			->willReturn(true);
		$userFolder->method('get')
			->with($outputPath)
			->willReturn($exportFolder);

		$exportFolder->method('getType')
			->willReturn(FileInfo::TYPE_FOLDER);
		$exportFolder->method('isCreatable')
			->willReturn(true);

		$filename = $projectId . '.csv';
		$exportFolder->method('nodeExists')
			->with($filename)
			->willReturn(false);
		$exportFolder->method('newFile')
			->with($filename)
			->willThrowException(new NotPermittedException('not permitted exception'));
		$this->assertEquals(
			['message' => $this->translation->t('Impossible to create %1$s', [$outputPath])],
			$this->exportService->exportCsvProject($projectId, $userId)
		);
	}
}
