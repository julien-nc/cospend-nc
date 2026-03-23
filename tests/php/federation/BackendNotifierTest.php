<?php

declare(strict_types=1);

namespace OCA\Cospend\Federation;

use OCA\Cospend\Db\Project;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BackendNotifierTest extends TestCase {

	private ICloudFederationFactory&MockObject $cloudFederationFactory;
	private AddressHandler&MockObject $addressHandler;
	private LoggerInterface&MockObject $logger;
	private ICloudFederationProviderManager&MockObject $federationProviderManager;
	private IUserManager&MockObject $userManager;
	private IURLGenerator&MockObject $urlGenerator;
	private ICloudIdManager&MockObject $cloudIdManager;
	private RestrictionValidator&MockObject $restrictionValidator;
	private IClientService&MockObject $clientService;

	private BackendNotifier $backendNotifier;

	protected function setUp(): void {
		$this->cloudFederationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->federationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->restrictionValidator = $this->createMock(RestrictionValidator::class);
		$this->clientService = $this->createMock(IClientService::class);

		$this->backendNotifier = new BackendNotifier(
			$this->cloudFederationFactory,
			$this->addressHandler,
			$this->logger,
			$this->federationProviderManager,
			$this->userManager,
			$this->urlGenerator,
			$this->cloudIdManager,
			$this->restrictionValidator,
			$this->clientService,
		);
	}

	/**
	 * Set up the mocks shared by all sendRemoteShare scenarios:
	 * cloud ID resolution, restriction validator, project owner lookup,
	 * AddressHandler (for prepareRemoteUrl), and the OCM share factory + send.
	 */
	private function setUpSendRemoteShareMocks(string $remoteUrl = 'https://remote.example.com'): void {
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')->willReturn('remoteuser');
		$cloudId->method('getRemote')->willReturn($remoteUrl);
		$cloudId->method('getId')->willReturn('remoteuser@' . $remoteUrl);
		$this->cloudIdManager->method('resolveCloudId')->willReturn($cloudId);

		$this->restrictionValidator->method('isAllowedToInvite');

		$projectOwner = $this->createMock(IUser::class);
		$projectOwner->method('getCloudId')->willReturn('owner@local.example.com');
		$projectOwner->method('getDisplayName')->willReturn('Project Owner');
		$this->userManager->method('get')->willReturn($projectOwner);

		// prepareRemoteUrl: urlContainProtocol returns true so the URL is used as-is
		$this->addressHandler->method('urlContainProtocol')->willReturn(true);

		$federationShare = $this->createMock(ICloudFederationShare::class);
		$federationShare->method('getProtocol')->willReturn([]);
		$federationShare->method('setProtocol')->willReturn(null);
		$this->cloudFederationFactory->method('getCloudFederationShare')->willReturn($federationShare);

		$ocmResponse = $this->createMock(IResponse::class);
		$ocmResponse->method('getStatusCode')->willReturn(201);
		$ocmResponse->method('getBody')->willReturn(json_encode([
			'recipientDisplayName' => 'Remote User',
			'recipientUserId' => '',
		]));
		$this->federationProviderManager->method('sendCloudShare')->willReturn($ocmResponse);
	}

	private function makeCapabilitiesResponse(array $capabilities): IResponse&MockObject {
		$body = json_encode(['ocs' => ['data' => ['capabilities' => $capabilities]]]);
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($body);
		return $response;
	}

	private function makeCapabilitiesClient(IResponse $response): IClient&MockObject {
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($response);
		return $client;
	}

	public function testSendRemoteShareThrowsWhenRemoteFederationDisabled(): void {
		$this->setUpSendRemoteShareMocks();

		$capResponse = $this->makeCapabilitiesResponse([
			'cospend' => ['federation' => ['enabled' => false]],
		]);
		$this->clientService->method('newClient')->willReturn($this->makeCapabilitiesClient($capResponse));

		$sharedBy = $this->createMock(IUser::class);
		$sharedBy->method('getCloudId')->willReturn('owner@local.example.com');
		$sharedBy->method('getDisplayName')->willReturn('Owner');

		$project = new Project();
		$project->setId('proj1');
		$project->setName('Test Project');
		$project->setUserId('owner');

		$this->expectException(CospendBasicException::class);
		$this->expectExceptionMessage('Remote server does not support Cospend federation');

		$this->backendNotifier->sendRemoteShare('proj1', 'token123', 'remoteuser@remote.example.com', $sharedBy, 'user', $project);
	}

	public function testSendRemoteShareSucceedsWhenRemoteFederationEnabled(): void {
		$this->setUpSendRemoteShareMocks();

		$capResponse = $this->makeCapabilitiesResponse([
			'cospend' => ['federation' => ['enabled' => true]],
		]);
		$this->clientService->method('newClient')->willReturn($this->makeCapabilitiesClient($capResponse));

		$sharedBy = $this->createMock(IUser::class);
		$sharedBy->method('getCloudId')->willReturn('owner@local.example.com');
		$sharedBy->method('getDisplayName')->willReturn('Owner');

		$project = new Project();
		$project->setId('proj1');
		$project->setName('Test Project');
		$project->setUserId('owner');

		$result = $this->backendNotifier->sendRemoteShare('proj1', 'token123', 'remoteuser@remote.example.com', $sharedBy, 'user', $project);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('cloudId', $result);
	}

	public function testSendRemoteShareProceedsWhenCapabilitiesFetchFails(): void {
		$this->setUpSendRemoteShareMocks();

		// Simulate a network error fetching capabilities
		$client = $this->createMock(IClient::class);
		$client->method('get')->willThrowException(new \Exception('Connection refused'));
		$this->clientService->method('newClient')->willReturn($client);

		$sharedBy = $this->createMock(IUser::class);
		$sharedBy->method('getCloudId')->willReturn('owner@local.example.com');
		$sharedBy->method('getDisplayName')->willReturn('Owner');

		$project = new Project();
		$project->setId('proj1');
		$project->setName('Test Project');
		$project->setUserId('owner');

		// Should not throw — gracefully skips the check and proceeds to OCM send
		$result = $this->backendNotifier->sendRemoteShare('proj1', 'token123', 'remoteuser@remote.example.com', $sharedBy, 'user', $project);

		$this->assertIsArray($result);
	}

	public function testSendRemoteShareProceedsWhenNoCospendCapabilityKey(): void {
		$this->setUpSendRemoteShareMocks();

		// Remote is a Nextcloud server but doesn't expose the cospend key (older version)
		$capResponse = $this->makeCapabilitiesResponse([
			'files' => ['undelete' => true],
		]);
		$this->clientService->method('newClient')->willReturn($this->makeCapabilitiesClient($capResponse));

		$sharedBy = $this->createMock(IUser::class);
		$sharedBy->method('getCloudId')->willReturn('owner@local.example.com');
		$sharedBy->method('getDisplayName')->willReturn('Owner');

		$project = new Project();
		$project->setId('proj1');
		$project->setName('Test Project');
		$project->setUserId('owner');

		// Should not throw — absence of the key is treated as "unknown, proceed"
		$result = $this->backendNotifier->sendRemoteShare('proj1', 'token123', 'remoteuser@remote.example.com', $sharedBy, 'user', $project);

		$this->assertIsArray($result);
	}
}
