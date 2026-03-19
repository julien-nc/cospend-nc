<?php

declare(strict_types=1);

namespace OCA\Cospend\Federation;

use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Db\ShareMapper;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CloudFederationProviderCospendTest extends TestCase {

	private ICloudIdManager&MockObject $cloudIdManager;
	private IUserManager&MockObject $userManager;
	private AddressHandler&MockObject $addressHandler;
	private FederationManager&MockObject $federationManager;
	private ShareMapper&MockObject $shareMapper;
	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private INotificationManager&MockObject $notificationManager;
	private InvitationMapper&MockObject $invitationMapper;
	private LoggerInterface&MockObject $logger;

	private CloudFederationProviderCospend $provider;

	protected function setUp(): void {
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->federationManager = $this->createMock(FederationManager::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->invitationMapper = $this->createMock(InvitationMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);

		$this->provider = new CloudFederationProviderCospend(
			$this->cloudIdManager,
			$this->userManager,
			$this->addressHandler,
			$this->federationManager,
			$this->shareMapper,
			$this->config,
			$this->appConfig,
			$this->notificationManager,
			$this->invitationMapper,
			$this->logger,
			$cacheFactory,
		);
	}

	private function makeShare(string $shareWith = 'localuser'): ICloudFederationShare&MockObject {
		$share = $this->createMock(ICloudFederationShare::class);
		$share->method('getShareType')->willReturn('user');
		$share->method('getShareWith')->willReturn($shareWith);
		$share->method('getShareSecret')->willReturn('secret123');
		$share->method('getProviderId')->willReturn('proj1');
		$share->method('getResourceName')->willReturn('My Project');
		$share->method('getSharedByDisplayName')->willReturn('Alice');
		$share->method('getSharedBy')->willReturn('alice@remote.example.com');
		$share->method('getOwnerDisplayName')->willReturn('Alice');
		$share->method('getOwner')->willReturn('alice@remote.example.com');
		$share->method('getProtocol')->willReturn(['invitedCloudId' => $shareWith . '@local.example.com']);
		return $share;
	}

	public function testShareReceivedThrowsWhenFederationDisabled(): void {
		$this->appConfig->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'federation_enabled', '0', true, '0'],
			]);

		$this->expectException(ProviderCouldNotAddShareException::class);

		$this->provider->shareReceived($this->makeShare());
	}

	public function testShareReceivedThrowsWhenIncomingDisabled(): void {
		$this->appConfig->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'federation_enabled', '0', true, '1'],
				[Application::APP_ID, 'federation_incoming_enabled', '1', true, '0'],
			]);

		$this->expectException(ProviderCouldNotAddShareException::class);

		$this->provider->shareReceived($this->makeShare());
	}

	public function testShareReceivedProceedsWhenBothEnabled(): void {
		$this->appConfig->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'federation_enabled', '0', true, '1'],
				[Application::APP_ID, 'federation_incoming_enabled', '1', true, '1'],
			]);

		// Remote URL handling
		$this->addressHandler->method('splitUserRemote')
			->willReturn(['alice', 'https://remote.example.com']);
		$this->addressHandler->method('urlContainProtocol')->willReturn(true);

		$localUser = $this->createMock(\OCP\IUser::class);
		$localUser->method('getUID')->willReturn('localuser');
		$this->userManager->method('get')->willReturn($localUser);

		$invitation = $this->createMock(\OCA\Cospend\Db\Invitation::class);
		$invitation->method('getId')->willReturn(42);
		$this->federationManager->method('addRemoteProject')->willReturn($invitation);

		$notification = $this->createMock(\OCP\Notification\INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$result = $this->provider->shareReceived($this->makeShare());

		$this->assertEquals('42', $result);
	}
}
