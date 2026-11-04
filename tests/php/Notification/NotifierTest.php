<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Notification;

use OCA\Cospend\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class NotifierTest extends TestCase {

	private IFactory&MockObject $l10nFactory;
	private IUserManager&MockObject $userManager;
	private IURLGenerator&MockObject $urlGenerator;
	private INotification&MockObject $notification;

	private Notifier $notifier;

	protected function setUp(): void {
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->notification = $this->createMock(INotification::class);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $args = []): string => vsprintf($text, $args));
		$this->l10nFactory->method('get')->willReturn($l);

		$this->notifier = new Notifier(
			$this->l10nFactory,
			$this->userManager,
			$this->urlGenerator,
		);
	}

	public function testThrowsOnOtherAppNotification(): void {
		$this->notification->method('getApp')->willReturn('other_app');
		$this->expectException(UnknownNotificationException::class);
		$this->notifier->prepare($this->notification, 'en');
	}

	public function testThrowsOnUnknownSubject(): void {
		$this->notification->method('getApp')->willReturn(Application::APP_ID);
		$this->notification->method('getSubject')->willReturn('unknown_subject');
		$this->expectException(UnknownNotificationException::class);
		$this->notifier->prepare($this->notification, 'en');
	}

	#[DataProvider(methodName: 'subjectProvider')]
	public function testPreparesUserShareNotification(string $subject, string $expectedRichSubject): void {
		$this->notification->method('getApp')->willReturn(Application::APP_ID);
		$this->notification->method('getSubject')->willReturn($subject);
		$this->notification->method('getSubjectParameters')->willReturn(['user1', 'Project name']);
		$this->notification->method('getObjectId')->willReturn('project_id');

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('User DisplayName');
		$this->userManager->method('get')->willReturn($user);

		$this->notification->expects($this->once())->method('setParsedSubject')->willReturnSelf();
		$this->notification->expects($this->once())->method('setRichMessage')->willReturnSelf();
		$this->notification->expects($this->once())->method('setLink')->willReturnSelf();
		$this->notification->expects($this->once())->method('setIcon')->willReturnSelf();

		$result = $this->notifier->prepare($this->notification, 'en');
		self::assertSame($this->notification, $result);
	}

	public static function subjectProvider(): array {
		return [
			['add_user_share', '{user} shared project {project} with you'],
			['delete_user_share', '{user} stopped sharing project {project} with you'],
		];
	}

	public function testPreparesRemoteCospendShareNotification(): void {
		$this->notification->method('getApp')->willReturn(Application::APP_ID);
		$this->notification->method('getSubject')->willReturn('remote_cospend_share');
		$this->notification->method('getSubjectParameters')->willReturn([
			'sharedByDisplayName' => 'User DisplayName',
			'sharedByFederatedId' => 'user1@remote.example.com',
			'remoteProjectName' => 'Remote project name',
			'remoteProjectId' => 'remote_project_id',
		]);

		$this->notification->expects($this->once())->method('setParsedSubject')->willReturnSelf();
		$this->notification->expects($this->once())->method('setParsedMessage')->willReturnSelf();
		$this->notification->expects($this->once())->method('setLink')->willReturnSelf();
		$this->notification->expects($this->once())->method('setIcon')->willReturnSelf();

		$result = $this->notifier->prepare($this->notification, 'en');
		self::assertSame($this->notification, $result);
	}
}
