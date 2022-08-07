<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\Cospend\Controller;

use OCA\Cospend\Service\ExportService;
use OCA\Cospend\Service\ProjectService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class ExportController extends ApiController {
	/**
	 * @var IL10N
	 */
	protected $translation;

	/**
	 * @var ProjectService
	 */
	protected $projectService;

	/**
	 * @var ExportService
	 */
	protected $exportService;

	/**
	 * @var string|null
	 */
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $translation
	 * @param ProjectService $projectService
	 * @param ExportService $exportService
	 * @param string|null $userId
	 */
	public function __construct(string         $appName,
								IRequest       $request,
								IL10N          $translation,
								ProjectService $projectService,
								ExportService  $exportService,
								?string        $userId) {
		parent::__construct($appName, $request, 'GET');

		$this->translation = $translation;
		$this->projectService = $projectService;
		$this->exportService = $exportService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function exportCsvProject(string $projectId, ?string $name = null, ?string $uid = null): DataResponse {
		$userId = $uid;
		if ($this->userId) {
			$userId = $this->userId;
		}

		if ($this->projectService->userCanAccessProject($userId, $projectId)) {
			$result = $this->exportService->exportCsvProject($projectId, $userId, $name);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->translation->t('You are not allowed to export this project')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function exportCsvSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->exportService->exportCsvSettlement($projectId, $this->userId, $centeredOn, $maxTimestamp);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->translation->t('You are not allowed to export this project settlement')],
				403
			);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function exportCsvStatistics(string $projectId, ?int $tsMin = null, ?int $tsMax = null,
										?int   $paymentModeId = null, ?int $category = null,
										?float $amountMin = null, ?float $amountMax = null, int $showDisabled = 1,
										?int   $currencyId = null): DataResponse {
		if ($this->projectService->userCanAccessProject($this->userId, $projectId)) {
			$result = $this->exportService->exportCsvStatistics(
				$projectId, $this->userId, $tsMin, $tsMax,
				$paymentModeId, $category, $amountMin, $amountMax,
				$showDisabled !== 0, $currencyId
			);
			if (isset($result['path'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, 400);
			}
		} else {
			return new DataResponse(
				['message' => $this->translation->t('You are not allowed to export this project statistics')],
				403
			);
		}
	}
}
