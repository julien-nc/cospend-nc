<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Federation integration test context for Cospend.
 *
 * Uses OCS API with Basic Auth exclusively (no cookie-based auth) to avoid
 * session issues with PHP's built-in web server across two instances.
 */
class FederationContext implements Context {
	private string $localServerUrl;
	private string $remoteServerUrl;
	private string $currentServer = 'LOCAL';

	private string $localRootDir;
	private string $remoteRootDir;
	private string $remoteConfigDir;

	private string $currentUser = 'admin';
	private ?ResponseInterface $response = null;

	/** @var array|null Last project created on LOCAL */
	private ?array $project = null;
	/** @var int|null Invitation ID received on REMOTE */
	private ?int $pendingInvitationId = null;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope): void {
		$this->localServerUrl = rtrim(getenv('TEST_SERVER_URL') ?: 'http://localhost:8080/', '/');
		$this->remoteServerUrl = rtrim(getenv('TEST_REMOTE_URL') ?: 'http://localhost:8280/', '/');
		$this->localRootDir = getenv('NEXTCLOUD_HOST_ROOT_DIR') ?: '';
		$this->remoteRootDir = getenv('NEXTCLOUD_REMOTE_ROOT_DIR') ?: '';
		$this->remoteConfigDir = getenv('NEXTCLOUD_REMOTE_CONFIG_DIR') ?: '';
	}

	// ---- Helpers ----

	private function getServerUrl(?string $server = null): string {
		$server = $server ?? $this->currentServer;
		return $server === 'REMOTE' ? $this->remoteServerUrl : $this->localServerUrl;
	}

	private function getRootDir(?string $server = null): string {
		$server = $server ?? $this->currentServer;
		return $server === 'REMOTE' ? $this->remoteRootDir : $this->localRootDir;
	}

	private function getOccEnvPrefix(?string $server = null): string {
		$server = $server ?? $this->currentServer;
		if ($server === 'REMOTE' && !empty($this->remoteConfigDir)) {
			return 'NEXTCLOUD_CONFIG_DIR=' . escapeshellarg($this->remoteConfigDir) . ' ';
		}
		return '';
	}

	private function getPassword(string $user): string {
		return ($user === 'admin') ? 'admin' : '123456';
	}

	private function occ(string $server, string $command): void {
		$rootDir = $this->getRootDir($server);
		$envPrefix = $this->getOccEnvPrefix($server);
		exec("{$envPrefix}php {$rootDir}/occ {$command} 2>&1", $output, $returnCode);
		if ($returnCode !== 0) {
			throw new \RuntimeException("occ {$command} failed on {$server}: " . implode("\n", $output));
		}
	}

	/**
	 * Send an OCS request with Basic Auth to the given (or current) server.
	 */
	private function sendOCSRequest(
		string $method,
		string $path,
		array $data = [],
		?string $user = null,
		?string $server = null,
	): ResponseInterface {
		$user = $user ?? $this->currentUser;
		$baseUrl = $this->getServerUrl($server);
		$url = $baseUrl . '/ocs/v2.php/' . ltrim($path, '/');

		$client = new Client(['http_errors' => false]);
		$options = [
			'auth' => [$user, $this->getPassword($user)],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json',
			],
		];
		if (!empty($data)) {
			$options['json'] = $data;
		}

		$this->response = $client->request($method, $url, $options);
		return $this->response;
	}

	private function getOCSResponseCode(): int {
		$this->response->getBody()->seek(0);
		$json = json_decode((string)$this->response->getBody(), true);
		return (int)($json['ocs']['meta']['statuscode'] ?? $this->response->getStatusCode());
	}

	private function getOCSData(): array {
		$this->response->getBody()->seek(0);
		$json = json_decode((string)$this->response->getBody(), true);
		return $json['ocs']['data'] ?? [];
	}

	// ---- Step definitions ----

	/**
	 * @Given /^using server "([^"]*)"$/
	 */
	public function usingServer(string $server): void {
		$this->currentServer = strtoupper($server);
	}

	/**
	 * @Given /^acting as "([^"]*)"$/
	 */
	public function actingAs(string $user): void {
		$this->currentUser = $user;
	}

	/**
	 * @Given /^federation is enabled on "([^"]*)"$/
	 */
	public function federationIsEnabledOn(string $server): void {
		$this->occ($server, 'config:app:set cospend federation_enabled --value=1');
		$this->occ($server, 'config:app:set cospend federation_incoming_enabled --value=1');
		$this->occ($server, 'config:app:set cospend federation_outgoing_enabled --value=1');
		$this->occ($server, 'config:app:set files_sharing outgoing_server2server_share_enabled --value=yes');
		$this->occ($server, 'config:app:set files_sharing incoming_server2server_share_enabled --value=yes');
	}

	/**
	 * @Given /^user "([^"]*)" exists on "([^"]*)"$/
	 */
	public function userExistsOn(string $user, string $server): void {
		$password = $this->getPassword($user);
		$rootDir = $this->getRootDir($server);
		$envPrefix = $this->getOccEnvPrefix($server);
		exec("{$envPrefix}OC_PASS={$password} php {$rootDir}/occ user:add --password-from-env {$user} 2>&1", $output, $returnCode);
		if ($returnCode !== 0) {
			// User already exists — reset password to ensure it matches
			exec("{$envPrefix}OC_PASS={$password} php {$rootDir}/occ user:resetpassword --password-from-env {$user} 2>&1");
		}
	}

	/**
	 * @Given /^"([^"]*)" creates a project "([^"]*)" on "([^"]*)"$/
	 */
	public function userCreatesProject(string $user, string $name, string $server): void {
		$this->sendOCSRequest('POST', 'apps/cospend/api/v1/projects', [
			'name' => $name,
			'id' => strtolower(str_replace(' ', '-', $name)),
		], $user, $server);
		Assert::assertEquals(200, $this->getOCSResponseCode(), 'Failed to create project: ' . $this->response->getBody());
		$this->project = $this->getOCSData();
		Assert::assertArrayHasKey('id', $this->project, 'Project creation did not return an id');
	}

	/**
	 * @When /^"([^"]*)" on "([^"]*)" shares project with federated user "([^"]*)"$/
	 */
	public function userSharesProjectWithFederatedUser(string $user, string $server, string $remoteUser): void {
		Assert::assertNotNull($this->project, 'No project created yet');
		$remoteServerUrl = ($server === 'LOCAL') ? $this->remoteServerUrl : $this->localServerUrl;
		$userCloudId = $remoteUser . '@' . $remoteServerUrl;

		$this->sendOCSRequest('POST', 'apps/cospend/api/v1/projects/' . $this->project['id'] . '/federated-share', [
			'userCloudId' => $userCloudId,
		], $user, $server);
	}

	/**
	 * @Then /^the OCS status code should be "([^"]*)"$/
	 */
	public function theOCSStatusCodeShouldBe(string $code): void {
		Assert::assertEquals((int)$code, $this->getOCSResponseCode(),
			'Unexpected OCS status code. Body: ' . $this->response->getBody());
	}

	/**
	 * @Then /^"([^"]*)" on "([^"]*)" has (\d+) pending invitation[s]?$/
	 */
	public function userHasPendingInvitations(string $user, string $server, int $expected): void {
		// OCM shares may take a moment to propagate — retry a few times
		$invitations = [];
		for ($i = 0; $i < 10; $i++) {
			$this->sendOCSRequest('GET', 'apps/cospend/api/v1/federation/pending-invitations', [], $user, $server);
			$invitations = $this->getOCSData();
			if (count($invitations) >= $expected) {
				break;
			}
			usleep(500000); // 500ms
		}
		Assert::assertCount($expected, $invitations, "Expected {$expected} pending invitations but got " . count($invitations));
		if ($expected > 0) {
			$this->pendingInvitationId = (int)$invitations[0]['id'];
		}
	}

	/**
	 * @When /^"([^"]*)" on "([^"]*)" accepts the pending invitation$/
	 */
	public function userAcceptsPendingInvitation(string $user, string $server): void {
		Assert::assertNotNull($this->pendingInvitationId, 'No pending invitation found in previous step');
		$this->sendOCSRequest('POST', 'apps/cospend/api/v1/federation/invitation/' . $this->pendingInvitationId, [], $user, $server);
		Assert::assertEquals(200, $this->getOCSResponseCode(), 'Failed to accept invitation: ' . $this->response->getBody());
	}

	/**
	 * @When /^"([^"]*)" on "([^"]*)" rejects the pending invitation$/
	 */
	public function userRejectsPendingInvitation(string $user, string $server): void {
		Assert::assertNotNull($this->pendingInvitationId, 'No pending invitation found in previous step');
		$this->sendOCSRequest('DELETE', 'apps/cospend/api/v1/federation/invitation/' . $this->pendingInvitationId, [], $user, $server);
		Assert::assertEquals(200, $this->getOCSResponseCode(), 'Failed to reject invitation: ' . $this->response->getBody());
	}

	/**
	 * @Then /^"([^"]*)" on "([^"]*)" can see the federated project "([^"]*)"$/
	 */
	public function userCanSeeFederatedProject(string $user, string $server, string $projectName): void {
		// After accepting, the federated project list should reflect it — retry for propagation
		$found = false;
		for ($i = 0; $i < 10; $i++) {
			$this->sendOCSRequest('GET', 'apps/cospend/api/v1/federated-projects', [], $user, $server);
			$projects = $this->getOCSData();
			foreach ($projects as $project) {
				if ($project['remoteProjectName'] === $projectName) {
					$found = true;
					break 2;
				}
			}
			usleep(500000);
		}
		Assert::assertTrue($found, "Federated project '{$projectName}' not found for {$user} on {$server}");
	}

	/**
	 * @Then /^"([^"]*)" on "([^"]*)" cannot see any federated projects$/
	 */
	public function userCannotSeeAnyFederatedProjects(string $user, string $server): void {
		$this->sendOCSRequest('GET', 'apps/cospend/api/v1/federated-projects', [], $user, $server);
		$projects = $this->getOCSData();
		Assert::assertCount(0, $projects, 'Expected no federated projects but found: ' . count($projects));
	}

	/**
	 * @Then /^"([^"]*)" on "([^"]*)" has (\d+) federated share[s]? on the project$/
	 */
	public function projectHasFederatedShares(string $user, string $server, int $expected): void {
		Assert::assertNotNull($this->project, 'No project created yet');
		$this->sendOCSRequest('GET', 'apps/cospend/api/v1/projects/' . $this->project['id'] . '/info', [], $user, $server);
		$data = $this->getOCSData();
		$federatedShares = array_filter($data['shares'] ?? [], fn($s) => $s['type'] === 'f');
		Assert::assertCount($expected, $federatedShares, "Expected {$expected} federated shares but found " . count($federatedShares));
	}

	/**
	 * @When /^"([^"]*)" on "([^"]*)" removes the federated share$/
	 */
	public function userRemovesFederatedShare(string $user, string $server): void {
		Assert::assertNotNull($this->project, 'No project created yet');
		$this->sendOCSRequest('GET', 'apps/cospend/api/v1/projects/' . $this->project['id'] . '/info', [], $user, $server);
		$data = $this->getOCSData();
		$federatedShare = null;
		foreach ($data['shares'] ?? [] as $share) {
			if ($share['type'] === 'f') {
				$federatedShare = $share;
				break;
			}
		}
		Assert::assertNotNull($federatedShare, 'No federated share found on project');

		$this->sendOCSRequest('DELETE', 'apps/cospend/api/v1/projects/' . $this->project['id'] . '/federated-share/' . $federatedShare['id'], [], $user, $server);
		Assert::assertEquals(200, $this->getOCSResponseCode(), 'Failed to remove federated share: ' . $this->response->getBody());
	}

	/**
	 * @Given /^outgoing federation is disabled on "([^"]*)"$/
	 */
	public function outgoingFederationIsDisabledOn(string $server): void {
		$this->occ($server, 'config:app:set cospend federation_outgoing_enabled --value=0');
	}

	/**
	 * @Given /^incoming federation is disabled on "([^"]*)"$/
	 */
	public function incomingFederationIsDisabledOn(string $server): void {
		$this->occ($server, 'config:app:set cospend federation_incoming_enabled --value=0');
	}

	/**
	 * @Then /^"([^"]*)" on "([^"]*)" has (\d+) pending invitation[s]? after waiting$/
	 */
	public function userHasPendingInvitationsAfterWaiting(string $user, string $server, int $expected): void {
		// Allow time for OCM unshare notification to arrive
		$invitations = [];
		for ($i = 0; $i < 10; $i++) {
			$this->sendOCSRequest('GET', 'apps/cospend/api/v1/federation/pending-invitations', [], $user, $server);
			$invitations = $this->getOCSData();
			if (count($invitations) === $expected) {
				break;
			}
			usleep(500000);
		}
		Assert::assertCount($expected, $invitations, "Expected {$expected} pending invitations but found " . count($invitations));
	}
}
