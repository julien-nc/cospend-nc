<?php

declare(strict_types=1);

namespace OCA\Cospend\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Federation\TrustedServers;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Federation\ICloudId;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RestrictionValidatorTest extends TestCase {

	private AddressHandler&MockObject $addressHandler;
	private IAppManager&MockObject $appManager;
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;

	private RestrictionValidator $validator;

	protected function setUp(): void {
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->validator = new RestrictionValidator(
			$this->addressHandler,
			$this->appManager,
			$this->appConfig,
			$this->logger,
		);
	}

	private function makeCloudId(string $user = 'alice', string $remote = 'remote.example.com'): ICloudId&MockObject {
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')->willReturn($user);
		$cloudId->method('getRemote')->willReturn($remote);
		$cloudId->method('getId')->willReturn($user . '@' . $remote);
		return $cloudId;
	}

	private function makeUser(): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('localuser');
		return $user;
	}

	public function testAllowedWhenOutgoingEnabledAndCloudIdValid(): void {
		$this->appConfig->method('getAppValueBool')
			->willReturnMap([
				['federation_outgoing_enabled', true, true],
				['federation_only_trusted_servers', false, false],
			]);

		// Should not throw
		$this->validator->isAllowedToInvite($this->makeUser(), $this->makeCloudId());
		$this->addToAssertionCount(1);
	}

	public function testThrowsWhenOutgoingFederationDisabled(): void {
		$this->appConfig->method('getAppValueBool')
			->willReturnMap([
				['federation_outgoing_enabled', true, false],
				['federation_only_trusted_servers', false, false],
			]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('outgoing');

		$this->validator->isAllowedToInvite($this->makeUser(), $this->makeCloudId());
	}

	public function testThrowsWhenCloudIdUserEmpty(): void {
		$this->appConfig->method('getAppValueBool')->willReturn(true);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')->willReturn('');
		$cloudId->method('getRemote')->willReturn('remote.example.com');
		$cloudId->method('getId')->willReturn('@remote.example.com');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('cloudId');

		$this->validator->isAllowedToInvite($this->makeUser(), $cloudId);
	}

	public function testThrowsWhenCloudIdRemoteEmpty(): void {
		$this->appConfig->method('getAppValueBool')->willReturn(true);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')->willReturn('alice');
		$cloudId->method('getRemote')->willReturn('');
		$cloudId->method('getId')->willReturn('alice@');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('cloudId');

		$this->validator->isAllowedToInvite($this->makeUser(), $cloudId);
	}

	public function testOutgoingCheckRunsBeforeTrustedServersCheck(): void {
		// outgoing disabled should be caught before we even check trusted servers
		$this->appConfig->method('getAppValueBool')
			->willReturnMap([
				['federation_outgoing_enabled', true, false],
				['federation_only_trusted_servers', false, true],
			]);

		// federation app must NOT be queried
		$this->appManager->expects($this->never())->method('isEnabledForUser');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('outgoing');

		$this->validator->isAllowedToInvite($this->makeUser(), $this->makeCloudId());
	}
}
