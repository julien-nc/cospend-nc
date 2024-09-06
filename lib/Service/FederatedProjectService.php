<?php

/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2024
 */

namespace OCA\Cospend\Service;

use DateTime;
use Generator;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\InvitationMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class FederatedProjectService implements IProjectService {

	private IClient $client;
	public string $userId;

	public function __construct(
		IClientService $clientService,
		IAppManager $appManager,
		private InvitationMapper $invitationMapper,
	) {
		$this->client = $clientService->newClient();
		$appVersion = $appManager->getAppVersion(Application::APP_ID);
		$this->USER_AGENT = 'Nextcloud Cospend v' . $appVersion;
	}

	public static function parseFederatedProjectId(string $federatedProjectId): array {
		return explode('@', $federatedProjectId);
	}

	private function request(string $federatedProjectId, string $endpoint, array $params = [], string $method = 'GET'): mixed {
		[$remoteProjectId, $remoteServerUrl] = $this->parseFederatedProjectId($federatedProjectId);
		try {
			$invitation = $this->invitationMapper->getByRemoteProjectIdAndRemoteServer($this->userId, $remoteProjectId, $remoteServerUrl, Invitation::STATE_ACCEPTED);
		} catch (DoesNotExistException $e) {
			throw new \Exception('Federated project "' . $federatedProjectId . '" not found for user ' . $this->userId);
		}

		$endpoint = str_replace('{token}', $invitation->getAccessToken(), $endpoint);
		$endpoint = str_replace('{password}', 'no-pass', $endpoint);
		$url = 'https://' . $remoteServerUrl . '/ocs/v2.php/apps/cospend/' . $endpoint;

		$options = [
			'headers' => [
				'User-Agent' => $this->USER_AGENT,
				'Accept' => 'application/json',
				'OCS-apirequest' => 'true',
			],
		];

		if (!empty($params)) {
			if ($method === 'GET') {
				// manage array parameters
				$paramsContent = '';
				foreach ($params as $key => $value) {
					if (is_array($value)) {
						foreach ($value as $oneArrayValue) {
							$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
						}
						unset($params[$key]);
					}
				}
				$paramsContent .= http_build_query($params);

				$url .= '?' . $paramsContent;
			} else {
				$options['json'] = $params;
			}
		}

		if ($method === 'GET') {
			$response = $this->client->get($url, $options);
		} elseif ($method === 'POST') {
			$response = $this->client->post($url, $options);
		} elseif ($method === 'PUT') {
			$response = $this->client->put($url, $options);
		} elseif ($method === 'DELETE') {
			$response = $this->client->delete($url, $options);
		} else {
			throw new \Exception('Bad HTTP method');
		}
		$body = $response->getBody();
		$parsedBody = json_decode($body, true);
		return $parsedBody['ocs']['data'];
	}

	public function deleteProject(string $projectId): void {
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}', [], 'DELETE');
	}

	public function getProjectInfoWithAccessLevel(string $projectId, string $userId): ?array {
		$projectInfo = $this->request($projectId, 'api/v1/public/projects/{token}/{password}');
		$projectInfo['id'] = $projectId;
		$projectInfo['federated'] = true;

		[$remoteProjectId, $remoteServerUrl] = $this->parseFederatedProjectId($projectId);
		try {
			$invitation = $this->invitationMapper->getByRemoteProjectIdAndRemoteServer($this->userId, $remoteProjectId, $remoteServerUrl, Invitation::STATE_ACCEPTED);
		} catch (DoesNotExistException $e) {
			throw new \Exception('Federated project "' . $projectId . '" not found for user ' . $this->userId);
		}
		$projectInfo['federation'] = [
			'inviter_cloud_id' => $invitation->getInviterCloudId(),
			'inviter_display_name' => $invitation->getInviterDisplayName(),
			'remote_server_url' => $invitation->getRemoteServerUrl(),
			'remote_project_id' => $invitation->getRemoteProjectId(),
		];

		return $projectInfo;
	}

	public function getBills(
		string $projectId, ?int $lastChanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): array {
		$params = [
			'lastChanged' => $lastChanged,
			'offset' => $offset,
			'limit' => $limit,
			'reverse' => $reverse,
			'payerId' => $payerId,
			'categoryId' => $categoryId,
			'paymentModeId' => $paymentModeId,
			'includeBillId' => $includeBillId,
			'searchTerm' => $searchTerm,
			'deleted' => $deleted,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills', $params);
	}

	public function getBill(string $projectId, int $billId): array {
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills/' . $billId);
	}

	public function createBill(
		string $projectId, ?string $date, ?string $what, ?int $payer, ?string $payedFor,
		?float $amount, ?string $repeat, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null,
		int $deleted = 0, bool $produceActivity = false
	): int {
		$params = [
			'date' => $date,
			'what' => $what,
			'payer' => $payer,
			'payedFor' => $payedFor,
			'amount' => $amount,
			'repeat' => $repeat,
			'paymentMode' => $paymentMode,
			'paymentModeId' => $paymentModeId,
			'categoryId' => $categoryId,
			'repeatAllActive' => $repeatAllActive,
			'repeatUntil' => $repeatUntil,
			'timestamp' => $timestamp,
			'comment' => $comment,
			'repeatFreq' => $repeatFreq,
			'deleted' => $deleted,
			'produceActivity' => $produceActivity,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills', $params, 'POST');
	}

	public function deleteBill(string $projectId, int $billId, bool $force = false, bool $moveToTrash = true, bool $produceActivity = false): void {
		$params = [
			'moveToTrash' => $moveToTrash,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills/' . $billId, $params, 'DELETE');
	}

	public function deleteBills(string $projectId, array $billIds, bool $moveToTrash = true): void {
		$params = [
			'billIds' => $billIds,
			'moveToTrash' => $moveToTrash,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills', $params, 'DELETE');
	}

	public function editBill(
		string $projectId, int $billId, ?string $date, ?string $what, ?int $payer, ?string $payedFor,
		?float $amount, ?string $repeat, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?int $repeatAllActive = null, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null,
		?int $deleted = null, bool $produceActivity = false
	): void {
		$params = [
			'date' => $date,
			'what' => $what,
			'payer' => $payer,
			'payedFor' => $payedFor,
			'amount' => $amount,
			'repeat' => $repeat,
			'paymentMode' => $paymentMode,
			'paymentModeId' => $paymentModeId,
			'categoryId' => $categoryId,
			'repeatAllActive' => $repeatAllActive,
			'repeatUntil' => $repeatUntil,
			'timestamp' => $timestamp,
			'comment' => $comment,
			'repeatFreq' => $repeatFreq,
			'deleted' => $deleted,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills/' . $billId, $params, 'PUT');
	}

	public function editBills(
		string $projectId, array $billIds, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payedFor = null,
		?float $amount = null, ?string $repeat = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null,
		?int $repeatAllActive = null, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null, bool $produceActivity = false
	): void {
		$params = [
			'billIds' => $billIds,
			'date' => $date,
			'what' => $what,
			'payer' => $payer,
			'payedFor' => $payedFor,
			'amount' => $amount,
			'repeat' => $repeat,
			'paymentMode' => $paymentMode,
			'paymentModeId' => $paymentModeId,
			'categoryId' => $categoryId,
			'repeatAllActive' => $repeatAllActive,
			'repeatUntil' => $repeatUntil,
			'timestamp' => $timestamp,
			'comment' => $comment,
			'repeatFreq' => $repeatFreq,
			'deleted' => $deleted,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills', $params, 'PUT');
	}

	public function repeatBill(string $projectId, int $billId): array {
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/bills/' . $billId . '/repeat');
	}

	public function clearTrashBin(string $projectId): void {
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/trash-bin', [], 'DELETE');
	}

	public function getStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		bool $showDisabled = true, ?int $currencyId = null, ?int $payerId = null
	): array {
		$params = [
			'tsMin' => $tsMin,
			'tsMax' => $tsMax,
			'paymentModeId' => $paymentModeId,
			'categoryId' => $categoryId,
			'amountMin' => $amountMin,
			'amountMax' => $amountMax,
			'showDisabled' => $showDisabled ? '1' : '0',
			'currencyId' => $currencyId,
			'payerId' => $payerId,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/statistics', $params);
	}

	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): void {
		$params = [
			'centeredOn' => $centeredOn,
			'precision' => $precision,
			'maxTimestamp' => $maxTimestamp,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/auto-settlement', $params);
	}

	public function getProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): array {
		$params = [
			'centeredOn' => $centeredOn,
			'maxTimestamp' => $maxTimestamp,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/settlement', $params);
	}

	public function editProject(
		string  $projectId, ?string $name = null, ?string $contact_email = null,
		?string $autoExport = null, ?string $currencyName = null, ?bool $deletionDisabled = null,
		?string $categorySort = null, ?string $paymentModeSort = null, ?int $archivedTs = null
	): void {
		$params = [
			'name' => $name,
			'contact_email' => $contact_email,
			'autoExport' => $autoExport,
			'currencyName' => $currencyName,
			'deletionDisabled' => $deletionDisabled,
			'categorySort' => $categorySort,
			'paymentModeSort' => $paymentModeSort,
			'archivedTs' => $archivedTs,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}', $params, 'PUT');
	}

	public function createMember(
		string $projectId, string $name, ?float $weight = 1.0, bool $active = true,
		?string $color = null, ?string $userId = null
	): array {
		$params = [
			'name' => $name,
			'weight' => $weight,
			'active' => $active ? 1 : 0,
			'color' => $color,
			'userId' => $userId,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/members', $params, 'POST');
	}

	public function getMembers(string $projectId, ?string $order = null, ?int $lastchanged = null): array {
		$params = [
			'lastchanged' => $lastchanged,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/members', $params);
	}

	public function deleteMember(string $projectId, int $memberId): void {
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/members/' . $memberId, [], 'DELETE');
	}

	public function editMember(
		string $projectId, int $memberId, ?string $name = null, ?string $userId = null,
		?float $weight = null, ?bool $activated = null, ?string $color = null
	): ?array {
		$params = [
			'name' => $name,
			'userId' => $userId,
			'weight' => $weight,
			'activated' => $activated,
			'color' => $color,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/members/' . $memberId, $params, 'PUT');
	}

	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int {
		$params = [
			'name' => $name,
			'icon' => $icon,
			'color' => $color,
			'order' => $order,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/paymentmode', $params, 'POST');
	}


	public function editPaymentMode(string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null): array {
		$params = [
			'name' => $name,
			'icon' => $icon,
			'color' => $color,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/paymentmode/' . $pmId, $params, 'PUT');
	}

	public function deletePaymentMode(string $projectId, int $pmId): void {
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/paymentmode/' . $pmId, [], 'DELETE');
	}

	public function savePaymentModeOrder(string $projectId, array $order): void {
		$params = [
			'order' => $order,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/paymentmode-order', $params, 'PUT');
	}

	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int {
		$params = [
			'name' => $name,
			'icon' => $icon,
			'color' => $color,
			'order' => $order,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/category', $params, 'POST');
	}

	public function deleteCategory(string $projectId, int $categoryId): void {
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/category/' . $categoryId, [], 'DELETE');
	}

	public function saveCategoryOrder(string $projectId, array $order): void {
		$params = [
			'order' => $order,
		];
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/category-order', $params, 'PUT');
	}

	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array {
		$params = [
			'name' => $name,
			'icon' => $icon,
			'color' => $color,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/category/' . $categoryId, $params, 'PUT');
	}

	public function createCurrency(string $projectId, string $name, float $rate): int {
		$params = [
			'name' => $name,
			'rate' => $rate,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/currency', $params, 'POST');
	}

	public function deleteCurrency(string $projectId, int $currencyId): void {
		$this->request($projectId, 'api/v1/public/projects/{token}/{password}/currency/' . $currencyId, [], 'DELETE');
	}

	public function editCurrency(string $projectId, int $currencyId, string $name, float $rate): array {
		$params = [
			'name' => $name,
			'rate' => $rate,
		];
		return $this->request($projectId, 'api/v1/public/projects/{token}/{password}/currency/' . $currencyId, $params, 'PUT');
	}

	public function getUserProxyAvatar(string $remoteServer, string $user, int $size, bool $darkTheme): FileDisplayResponse {
		$url = $remoteServer . '/index.php/avatar/' . $user . '/' . $size . ($darkTheme ? '/dark' : '');
		$options = [
			'headers' => [
				'User-Agent' => $this->USER_AGENT,
			],
		];
		$response = $this->client->get($url, $options);
		$content = $response->getBody();
		if ($content === '') {
			throw new \Exception('No avatar content received');
		}

		$file = new InMemoryFile($user, $content);

		$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}
}
