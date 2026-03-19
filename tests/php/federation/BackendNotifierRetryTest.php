<?php

declare(strict_types=1);

namespace OCA\Cospend\Federation;

use OCA\Cospend\Db\RetryNotification;
use OCA\Cospend\Db\RetryNotificationMapper;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IResponse;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\OCM\Exceptions\OCMProviderException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BackendNotifierRetryTest extends TestCase {

	private ICloudFederationFactory&MockObject $cloudFederationFactory;
	private ICloudFederationProviderManager&MockObject $federationProviderManager;
	private RetryNotificationMapper&MockObject $retryNotificationMapper;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;

	/** @var BackendNotifier&MockObject */
	private BackendNotifier $backendNotifier;

	protected function setUp(): void {
		$this->cloudFederationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->federationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->retryNotificationMapper = $this->createMock(RetryNotificationMapper::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->timeFactory->method('getTime')->willReturn(1000000);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());

		$this->backendNotifier = new BackendNotifier(
			$this->cloudFederationFactory,
			$this->createMock(AddressHandler::class),
			$this->logger,
			$this->federationProviderManager,
			$this->createMock(IUserManager::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ICloudIdManager::class),
			$this->createMock(RestrictionValidator::class),
			$this->retryNotificationMapper,
			$this->timeFactory,
		);
	}

	private function makeNotification(string $type = FederationManager::NOTIFICATION_SHARE_ACCEPTED): ICloudFederationNotification&MockObject {
		$notification = $this->createMock(ICloudFederationNotification::class);
		$notification->method('getMessage')->willReturn([
			'notificationType' => $type,
			'resourceType' => FederationManager::COSPEND_PROJECT_RESOURCE,
			'providerId' => 'proj1',
			'notification' => ['sharedSecret' => 'token123', 'cloudId' => 'alice@remote.example.com'],
		]);
		return $notification;
	}

	private function makeHttpResponse(int $status, string $body = ''): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		return $response;
	}

	public function testSendUpdateToRemoteReturnsTrueOnSuccess(): void {
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_CREATED));

		// No retry should be queued on success
		$this->retryNotificationMapper->expects($this->never())->method('insert');

		$result = $this->invokeProtected('sendUpdateToRemote', ['https://remote.example.com', $this->makeNotification()]);

		$this->assertTrue($result);
	}

	public function testSendUpdateToRemoteQueuesRetryOnNetworkFailure(): void {
		$this->federationProviderManager->method('sendCloudNotification')
			->willThrowException(new OCMProviderException('connection refused'));

		$this->retryNotificationMapper->expects($this->once())->method('insert')
			->with($this->callback(function (RetryNotification $r) {
				return $r->getRemoteServer() === 'https://remote.example.com'
					&& $r->getNumAttempts() === 1
					&& $r->getNotificationType() === FederationManager::NOTIFICATION_SHARE_ACCEPTED;
			}));

		$result = $this->invokeProtected('sendUpdateToRemote', ['https://remote.example.com', $this->makeNotification()]);

		$this->assertFalse($result);
	}

	public function testSendUpdateToRemoteQueuesRetryOnNon201StatusCode(): void {
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_INTERNAL_SERVER_ERROR));

		$this->retryNotificationMapper->expects($this->once())->method('insert');

		$result = $this->invokeProtected('sendUpdateToRemote', ['https://remote.example.com', $this->makeNotification()]);

		$this->assertFalse($result);
	}

	public function testSendUpdateToRemoteReturnsNullOnResourceNotFound(): void {
		$body = json_encode(['message' => FederationManager::OCM_RESOURCE_NOT_FOUND]);
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_BAD_REQUEST, $body));

		// null = give up, no retry
		$this->retryNotificationMapper->expects($this->never())->method('insert');

		$result = $this->invokeProtected('sendUpdateToRemote', ['https://remote.example.com', $this->makeNotification()]);

		$this->assertNull($result);
	}

	public function testSendUpdateToRemoteDoesNotRetryWhenRetryFalse(): void {
		$this->federationProviderManager->method('sendCloudNotification')
			->willThrowException(new OCMProviderException('down'));

		$this->retryNotificationMapper->expects($this->never())->method('insert');

		$result = $this->invokeProtected('sendUpdateToRemote', ['https://remote.example.com', $this->makeNotification(), 0, false]);

		$this->assertFalse($result);
	}

	public function testSendUpdateToRemoteDoesNotRetryWhenTryIsNonZero(): void {
		$this->federationProviderManager->method('sendCloudNotification')
			->willThrowException(new OCMProviderException('down'));

		// try=2 means we are already in a retry loop; do not insert a new row
		$this->retryNotificationMapper->expects($this->never())->method('insert');

		$result = $this->invokeProtected('sendUpdateToRemote', ['https://remote.example.com', $this->makeNotification(), 2]);

		$this->assertFalse($result);
	}

	public function testRetrySendingDeletesOnSuccess(): void {
		$retryNotification = new RetryNotification();
		$retryNotification->setRemoteServer('https://remote.example.com');
		$retryNotification->setNumAttempts(3);
		$retryNotification->setNotificationType(FederationManager::NOTIFICATION_SHARE_ACCEPTED);
		$retryNotification->setResourceType(FederationManager::COSPEND_PROJECT_RESOURCE);
		$retryNotification->setProviderId('proj1');
		$retryNotification->setNotification(json_encode(['sharedSecret' => 'tok', 'cloudId' => 'bob@remote.example.com']));

		$ocmNotification = $this->makeNotification();
		$this->cloudFederationFactory->method('getCloudFederationNotification')->willReturn($ocmNotification);

		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_CREATED));

		$this->retryNotificationMapper->expects($this->once())->method('delete')->with($retryNotification);
		$this->retryNotificationMapper->expects($this->never())->method('update');

		$this->invokeProtected('retrySendingFailedNotification', [$retryNotification]);
	}

	public function testRetrySendingDeletesOnNullResponse(): void {
		$retryNotification = new RetryNotification();
		$retryNotification->setRemoteServer('https://remote.example.com');
		$retryNotification->setNumAttempts(3);
		$retryNotification->setNotificationType(FederationManager::NOTIFICATION_SHARE_ACCEPTED);
		$retryNotification->setResourceType(FederationManager::COSPEND_PROJECT_RESOURCE);
		$retryNotification->setProviderId('proj1');
		$retryNotification->setNotification(json_encode(['sharedSecret' => 'tok', 'cloudId' => 'bob@remote.example.com']));

		$ocmNotification = $this->makeNotification();
		$this->cloudFederationFactory->method('getCloudFederationNotification')->willReturn($ocmNotification);

		$body = json_encode(['message' => FederationManager::OCM_RESOURCE_NOT_FOUND]);
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_BAD_REQUEST, $body));

		$this->retryNotificationMapper->expects($this->once())->method('delete')->with($retryNotification);
		$this->retryNotificationMapper->expects($this->never())->method('update');

		$this->invokeProtected('retrySendingFailedNotification', [$retryNotification]);
	}

	public function testRetrySendingDeletesWhenMaxAttemptsReached(): void {
		$retryNotification = new RetryNotification();
		$retryNotification->setRemoteServer('https://remote.example.com');
		$retryNotification->setNumAttempts(RetryNotification::MAX_NUM_ATTEMPTS);
		$retryNotification->setNotificationType(FederationManager::NOTIFICATION_SHARE_ACCEPTED);
		$retryNotification->setResourceType(FederationManager::COSPEND_PROJECT_RESOURCE);
		$retryNotification->setProviderId('proj1');
		$retryNotification->setNotification(json_encode(['sharedSecret' => 'tok', 'cloudId' => 'bob@remote.example.com']));

		$ocmNotification = $this->makeNotification();
		$this->cloudFederationFactory->method('getCloudFederationNotification')->willReturn($ocmNotification);

		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_INTERNAL_SERVER_ERROR));

		$this->retryNotificationMapper->expects($this->once())->method('delete')->with($retryNotification);
		$this->retryNotificationMapper->expects($this->never())->method('update');

		$this->invokeProtected('retrySendingFailedNotification', [$retryNotification]);
	}

	public function testRetrySendingUpdatesAttemptCountOnStillFailing(): void {
		$retryNotification = new RetryNotification();
		$retryNotification->setRemoteServer('https://remote.example.com');
		$retryNotification->setNumAttempts(3);
		$retryNotification->setNotificationType(FederationManager::NOTIFICATION_SHARE_ACCEPTED);
		$retryNotification->setResourceType(FederationManager::COSPEND_PROJECT_RESOURCE);
		$retryNotification->setProviderId('proj1');
		$retryNotification->setNotification(json_encode(['sharedSecret' => 'tok', 'cloudId' => 'bob@remote.example.com']));

		$ocmNotification = $this->makeNotification();
		$this->cloudFederationFactory->method('getCloudFederationNotification')->willReturn($ocmNotification);

		$this->federationProviderManager->method('sendCloudNotification')
			->willThrowException(new OCMProviderException('still down'));

		$this->retryNotificationMapper->expects($this->never())->method('delete');
		$this->retryNotificationMapper->expects($this->once())->method('update')
			->with($this->callback(function (RetryNotification $r) {
				return $r->getNumAttempts() === 4;
			}));

		$this->invokeProtected('retrySendingFailedNotification', [$retryNotification]);
	}

	public function testGetRetryDelaySchedule(): void {
		// Attempts 1-4: 5 min
		$this->assertSame(5 * 60, $this->invokeProtected('getRetryDelay', [1]));
		$this->assertSame(5 * 60, $this->invokeProtected('getRetryDelay', [4]));
		// Attempt 5: 25 min
		$this->assertSame(5 * 5 * 60, $this->invokeProtected('getRetryDelay', [5]));
		// Attempt 10: 50 min
		$this->assertSame(10 * 5 * 60, $this->invokeProtected('getRetryDelay', [10]));
		// Attempts 11+: 8 h
		$this->assertSame(8 * 3600, $this->invokeProtected('getRetryDelay', [11]));
		$this->assertSame(8 * 3600, $this->invokeProtected('getRetryDelay', [20]));
	}

	public function testRetrySendingFailedNotificationsCallsEachDue(): void {
		$r1 = new RetryNotification();
		$r1->setRemoteServer('https://remote1.example.com');
		$r1->setNumAttempts(1);
		$r1->setNotificationType(FederationManager::NOTIFICATION_SHARE_ACCEPTED);
		$r1->setResourceType(FederationManager::COSPEND_PROJECT_RESOURCE);
		$r1->setProviderId('proj1');
		$r1->setNotification(json_encode(['sharedSecret' => 'tok', 'cloudId' => 'a@r.com']));

		$r2 = new RetryNotification();
		$r2->setRemoteServer('https://remote2.example.com');
		$r2->setNumAttempts(1);
		$r2->setNotificationType(FederationManager::NOTIFICATION_SHARE_DECLINED);
		$r2->setResourceType(FederationManager::COSPEND_PROJECT_RESOURCE);
		$r2->setProviderId('proj2');
		$r2->setNotification(json_encode(['sharedSecret' => 'tok2', 'cloudId' => 'b@r.com']));

		$this->retryNotificationMapper->method('getAllDue')->willReturn([$r1, $r2]);

		$notification = $this->makeNotification();
		$this->cloudFederationFactory->method('getCloudFederationNotification')->willReturn($notification);

		// Both succeed
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($this->makeHttpResponse(Http::STATUS_CREATED));

		$this->retryNotificationMapper->expects($this->exactly(2))->method('delete');

		$this->backendNotifier->retrySendingFailedNotifications(new \DateTime());
	}

	/**
	 * Helper to call protected/private methods
	 */
	private function invokeProtected(string $method, array $args = []): mixed {
		$reflection = new \ReflectionMethod($this->backendNotifier, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($this->backendNotifier, $args);
	}
}
