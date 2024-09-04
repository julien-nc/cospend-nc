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
		$invitations = $this->invitationMapper->getInvitationsForUser($this->userId, Invitation::STATE_ACCEPTED, $remoteServerUrl, $remoteProjectId);
		if (empty($invitations)) {
			throw new \Exception('Federated project "' . $federatedProjectId . '" not found for user ' . $this->userId);
		}
		$invitation = $invitations[0];

		$endpoint = str_replace('{token}', $invitation->getToken(), $endpoint);
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
		$invitations = $this->invitationMapper->getInvitationsForUser($this->userId, Invitation::STATE_ACCEPTED, $remoteServerUrl, $remoteProjectId);
		if (empty($invitations)) {
			throw new \Exception('Federated project "' . $projectId . '" not found for user ' . $this->userId);
		}
		$invitation = $invitations[0];
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
		?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
		?string $categorysort = null, ?string $paymentmodesort = null, ?int $archivedTs = null
	): void {
		$params = [
			'name' => $name,
			'contact_email' => $contact_email,
			'autoexport' => $autoexport,
			'currencyname' => $currencyname,
			'deletion_disabled' => $deletion_disabled,
			'categorysort' => $categorysort,
			'paymentmodesort' => $paymentmodesort,
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

	public function editBill(
		string $projectId, int $billId, ?string $date, ?string $what, ?int $payer, ?string $payed_for,
		?float $amount, ?string $repeat, ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null, ?string $repeatuntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatfreq = null,
		?int $deleted = null
	): array {
	}

	public function moveBill(string $projectId, int $billId, string $toProjectId): array {
	}

	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int {
	}

	public function deletePaymentMode(string $projectId, int $pmId): array {
	}

	public function savePaymentModeOrder(string $projectId, array $order): bool {
	}

	public function editPaymentMode(
		string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array {
	}

	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int {
	}

	public function deleteCategory(string $projectId, int $categoryId): array {
	}

	public function saveCategoryOrder(string $projectId, array $order): bool {
	}

	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array {
	}

	public function createCurrency(string $projectId, string $name, float $rate): int {
	}

	public function deleteCurrency(string $projectId, int $currencyId): array {
	}

	public function editCurrency(string $projectId, int $currencyId, string $name, float $exchange_rate): array {
	}

	/**
	 * TODO: adjust to get info from remote federated project
	 *
	 * @param string $projectId
	 * @return Generator
	 * @throws \OCP\DB\Exception
	 */
	public function getJsonProject(string $projectId): Generator {
		// members
		yield "name,weight,active,color\n";
		$projectInfo = $this->getProjectInfo($projectId);
		$members = $projectInfo['members'];
		$memberIdToName = [];
		$memberIdToWeight = [];
		$memberIdToActive = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
			$memberIdToWeight[$member['id']] = $member['weight'];
			$memberIdToActive[$member['id']] = (int) $member['activated'];
			$c = $member['color'];
			yield '"' . $member['name'] . '",'
				. (float) $member['weight'] . ','
				. (int) $member['activated'] . ',"'
				. sprintf("#%02x%02x%02x", $c['r'] ?? 0, $c['g'] ?? 0, $c['b'] ?? 0) . '"'
				. "\n";
		}
		// bills
		yield "\nwhat,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,paymentmodeid,comment,deleted\n";
		$bills = $this->billMapper->getBills(
			$projectId, null, null, null, null, null,
			null, null, null, null, false, null, null
		);
		foreach ($bills as $bill) {
			$owerNames = [];
			foreach ($bill['owers'] as $ower) {
				$owerNames[] = $ower['name'];
			}
			$owersTxt = implode(',', $owerNames);

			$payer_id = $bill['payer_id'];
			$payer_name = $memberIdToName[$payer_id];
			$payer_weight = $memberIdToWeight[$payer_id];
			$payer_active = $memberIdToActive[$payer_id];
			$dateTime = DateTime::createFromFormat('U', $bill['timestamp']);
			$oldDateStr = $dateTime->format('Y-m-d');
			yield '"' . $bill['what'] . '",'
				. (float) $bill['amount'] . ','
				. $oldDateStr . ','
				. $bill['timestamp'] . ',"'
				. $payer_name . '",'
				. (float) $payer_weight . ','
				. $payer_active . ',"'
				. $owersTxt . '",'
				. $bill['repeat'] . ','
				. $bill['repeatfreq'] . ','
				. $bill['repeatallactive'] .','
				. $bill['repeatuntil'] . ','
				. $bill['categoryid'] . ','
				. $bill['paymentmode'] . ','
				. $bill['paymentmodeid'] . ',"'
				. urlencode($bill['comment']) . '",'
				. $bill['deleted']
				. "\n";
		}

		// write categories
		$categories = $projectInfo['categories'];
		if (count($categories) > 0) {
			yield "\ncategoryname,categoryid,icon,color\n";
			foreach ($categories as $id => $cat) {
				yield '"' . $cat['name'] . '",' .
					(int) $id . ',"' .
					$cat['icon'] . '","' .
					$cat['color'] . '"' .
					"\n";
			}
		}

		// write payment modes
		$paymentModes = $projectInfo['paymentmodes'];
		if (count($paymentModes) > 0) {
			yield "\npaymentmodename,paymentmodeid,icon,color\n";
			foreach ($paymentModes as $id => $pm) {
				yield '"' . $pm['name'] . '",' .
					(int) $id . ',"' .
					$pm['icon'] . '","' .
					$pm['color'] . '"' .
					"\n";
			}
		}

		// write currencies
		$currencies = $projectInfo['currencies'];
		if (count($currencies) > 0) {
			yield "\ncurrencyname,exchange_rate\n";
			// main currency
			yield '"' . $projectInfo['currencyname'] . '",1' . "\n";
			foreach ($currencies as $cur) {
				yield '"' . $cur['name']
					. '",' . (float) $cur['exchange_rate']
					. "\n";
			}
		}

		return [];
	}
}
