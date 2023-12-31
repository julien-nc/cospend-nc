<?php
/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Cospend\Controller;

use DateTime;
use OC\User\NoUserException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Share\IShare;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;

use OCA\Cospend\Db\BillMapper;
use OCA\Cospend\Service\ProjectService;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;

class PublicApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IManager $shareManager,
		private IUserManager $userManager,
		private IL10N $trans,
		private BillMapper $billMapper,
		private ProjectService $projectService,
		private ActivityManager $activityManager,
		private IDBConnection $dbconnection,
		private IRootFolder $root,
		private IInitialState $initialStateService,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
		private ?string $userId
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * Check if project password is valid
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return bool
	 */
	private function checkLogin(string $projectId, string $password): bool {
		if ($projectId === '' || $projectId === null
			|| $password === '' || $password === null
		) {
			return false;
		} else {
			$qb = $this->dbconnection->getQueryBuilder();
			$qb->select('id', 'password')
			   ->from('cospend_projects', 'p')
			   ->where(
				   $qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_STR))
			   );
			$req = $qb->executeQuery();
			$dbPassword = null;
			$row = $req->fetch();
			if ($row !== false) {
				$dbPassword = $row['password'];
			}
			$req->closeCursor();
			$qb->resetQueryParts();
			return (
				$dbPassword !== null &&
				password_verify($password, $dbPassword)
			);
		}
	}

	/**
	 * Delete a project
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeleteProject(string $projectId, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['admin'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['admin'])
		) {
			$result = $this->projectService->deleteProject($publicShareInfo['projectid'] ?? $projectId);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse(['message' => $result['error']], Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Clear the trashbin
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicClearTrashbin(string $projectId, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			try {
				$this->billMapper->deleteDeletedBills($publicShareInfo['projectid']);
				return new DataResponse('');
			} catch (\Exception | \Throwable $e) {
				return new DataResponse('', Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to clear the trashbin')],
				Http::STATUS_FORBIDDEN
			);
		}
	}

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $billId
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeleteBill(string $projectId, string $password, int $billId, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$billObj = null;
			if ($this->billMapper->getBill($publicShareInfo['projectid'] ?? $projectId, $billId) !== null) {
				$billObj = $this->billMapper->find($billId);
			}

			$result = $this->projectService->deleteBill($publicShareInfo['projectid'] ?? $projectId, $billId, false, $moveToTrash);
			if (isset($result['success'])) {
				if (!is_null($billObj)) {
					if (is_null($publicShareInfo)) {
						$authorFullText = $this->trans->t('Guest access');
					} elseif ($publicShareInfo['label']) {
						$authorName = $publicShareInfo['label'];
						$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
					} else {
						$authorFullText = $this->trans->t('Share link');
					}
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_BILL, $billObj,
						ActivityManager::SUBJECT_BILL_DELETE,
						['author' => $authorFullText]
					);
				}
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete multiple bills
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param array $billIds
	 * @param bool $moveToTrash
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeleteBills(string $projectId, string $password, array $billIds, bool $moveToTrash = true): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			if (is_null($publicShareInfo)) {
				$authorFullText = $this->trans->t('Guest access');
			} elseif ($publicShareInfo['label']) {
				$authorName = $publicShareInfo['label'];
				$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
			} else {
				$authorFullText = $this->trans->t('Share link');
			}
			foreach ($billIds as $billid) {
				$billObj = null;
				if ($this->billMapper->getBill($publicShareInfo['projectid'] ?? $projectId, $billid) !== null) {
					$billObj = $this->billMapper->find($billid);
				}

				$result = $this->projectService->deleteBill($publicShareInfo['projectid'] ?? $projectId, $billid, false, $moveToTrash);
				if (!isset($result['success'])) {
					return new DataResponse($result, Http::STATUS_NOT_FOUND);
				} else {
					if (!is_null($billObj)) {
						$this->activityManager->triggerEvent(
							ActivityManager::COSPEND_OBJECT_BILL, $billObj,
							ActivityManager::SUBJECT_BILL_DELETE,
							['author' => $authorFullText]
						);
					}
				}
			}
			return new DataResponse('OK');
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get project information
	 *
	 * @param string $projectId
	 * @param string $password
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicGetProjectInfo(string $projectId, string $password): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$projectInfo = $this->projectService->getProjectInfo($publicShareInfo['projectid'] ?? $projectId);
			if ($projectInfo !== null) {
				unset($projectInfo['userid']);
				// for public link share: set the visible access level for frontend
				if ($publicShareInfo !== null) {
					$projectInfo['myaccesslevel'] = $publicShareInfo['accesslevel'];
				} else {
					// my access level is the guest one
					$projectInfo['myaccesslevel'] = $projectInfo['guestaccesslevel'];
				}
				return new DataResponse($projectInfo);
			} else {
				return new DataResponse(
					['message' => $this->trans->t('Project not found')],
					Http::STATUS_NOT_FOUND
				);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Bad password or share link')],
				Http::STATUS_BAD_REQUEST
			);
		}
	}

	/**
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param string $showDisabled
	 * @param int|null $currencyId
	 * @param int|null $payerId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicGetProjectStatistics(
		string $projectId, string $password, ?int $tsMin = null, ?int $tsMax = null,
		?int   $paymentModeId = null, ?int $categoryId = null,
		?float $amountMin = null, ?float $amountMax = null,
		string $showDisabled = '1', ?int $currencyId = null, ?int $payerId = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$result = $this->projectService->getProjectStatistics(
				$publicShareInfo['projectid'] ?? $projectId, 'lowername', $tsMin, $tsMax,
				$paymentModeId, $categoryId, $amountMin, $amountMax, $showDisabled === '1', $currencyId,
				$payerId
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get project settlement info
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicGetProjectSettlement(string $projectId, string $password, ?int $centeredOn = null, ?int $maxTimestamp = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$result = $this->projectService->getProjectSettlement(
				$publicShareInfo['projectid'] ?? $projectId, $centeredOn, $maxTimestamp
			);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get automatic settlement plan
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicAutoSettlement(
		string $projectId, string $password, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$result = $this->projectService->autoSettlement(
				$publicShareInfo['projectid'] ?? $projectId, $centeredOn, $precision, $maxTimestamp
			);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a project member
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $memberId
	 * @param string|null $name
	 * @param float|null $weight
	 * @param null $activated
	 * @param string|null $color
	 * @param string|null $userid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditMember(
		string $projectId, string $password, int $memberId, ?string $name = null, ?float $weight = null,
			   $activated = null, ?string $color = null, ?string $userid = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			if ($activated === 'true') {
				$activated = true;
			} elseif ($activated === 'false') {
				$activated = false;
			}
			$result = $this->projectService->editMember(
				$publicShareInfo['projectid'] ?? $projectId, $memberId, $name, $userid, $weight, $activated, $color
			);
			if (count($result) === 0) {
				return new DataResponse(null);
			} elseif (array_key_exists('activated', $result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a bill
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $billId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $categoryid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @param int|null $deleted
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditBill(
		string $projectId, string $password, int $billId, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$result = $this->projectService->editBill(
				$publicShareInfo['projectid'] ?? $projectId, $billId, $date, $what, $payer, $payed_for,
				$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
				$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, null, $deleted
			);
			if (isset($result['edited_bill_id'])) {
				$billObj = $this->billMapper->find($billId);
				if (is_null($publicShareInfo)) {
					$authorFullText = $this->trans->t('Guest access');
				} elseif ($publicShareInfo['label']) {
					$authorName = $publicShareInfo['label'];
					$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
				} else {
					$authorFullText = $this->trans->t('Share link');
				}
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_UPDATE,
					['author' => $authorFullText]
				);

				return new DataResponse($result['edited_bill_id']);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit multiple bills
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param array $billIds
	 * @param int|null $categoryid
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @param int|null $deleted
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditBills(
		string  $projectId, string $password, array $billIds, ?int $categoryid = null, ?string $date = null,
		?string $what = null, ?int $payer = null, ?string $payed_for = null, ?float $amount = null,
		?string $repeat = 'n', ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $repeatallactive = null,
		?string $repeatuntil = null, ?int $timestamp = null, ?string $comment = null,
		?int $repeatfreq = null, ?int $deleted = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			if (is_null($publicShareInfo)) {
				$authorFullText = $this->trans->t('Guest access');
			} elseif ($publicShareInfo['label']) {
				$authorName = $publicShareInfo['label'];
				$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
			} else {
				$authorFullText = $this->trans->t('Share link');
			}
			$paymentModes = $this->projectService->getCategoriesOrPaymentModes($publicShareInfo['projectid'] ?? $projectId, false);
			foreach ($billIds as $billid) {
				$result = $this->projectService->editBill(
					$publicShareInfo['projectid'] ?? $projectId, $billid, $date, $what, $payer, $payed_for,
					$amount, $repeat, $paymentmode, $paymentmodeid, $categoryid,
					$repeatallactive, $repeatuntil, $timestamp, $comment, $repeatfreq, $paymentModes, $deleted
				);
				if (isset($result['edited_bill_id'])) {
					$billObj = $this->billMapper->find($billid);
					$this->activityManager->triggerEvent(
						ActivityManager::COSPEND_OBJECT_BILL, $billObj,
						ActivityManager::SUBJECT_BILL_UPDATE,
						['author' => $authorFullText]
					);
				} else {
					return new DataResponse($result, Http::STATUS_BAD_REQUEST);
				}
			}
			return new DataResponse($billIds);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to edit this bill')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Trigger bill repetition for a specific bill
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $billId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicRepeatBill(string $projectId, string $password, int $billId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			// TODO check if bill is in this project
			$result = $this->projectService->cronRepeatBills($billId);
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a project
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $autoexport
	 * @param string|null $currencyname
	 * @param bool|null $deletion_disabled
	 * @param string|null $categorysort
	 * @param string|null $paymentmodesort
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditProject(
		string $projectId, string $password, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null,
		?bool $deletion_disabled = null, ?string $categorysort = null, ?string $paymentmodesort = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			$publicShareInfo !== null
			&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
			&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['admin']
		) {
			$result = $this->projectService->editProject(
				$publicShareInfo['projectid'] ?? $projectId, $name, $contact_email, null, $autoexport,
				$currencyname, $deletion_disabled, $categorysort, $paymentmodesort
			);
			if (isset($result['success'])) {
				return new DataResponse('UPDATED');
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a bill
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $categoryid
	 * @param int $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicCreateBill(
		string $projectId, string $password, ?string $date = null, ?string $what = null, ?int $payer = null,
		?string $payed_for = null, ?float $amount = null, string $repeat = 'n',
		?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, int $repeatallactive = 0, ?string $repeatuntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatfreq = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['participant'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['participant'])
		) {
			$result = $this->projectService->addBill(
				$publicShareInfo['projectid'] ?? $projectId, $date, $what, $payer, $payed_for, $amount,
				$repeat, $paymentmode, $paymentmodeid, $categoryid, $repeatallactive,
				$repeatuntil, $timestamp, $comment, $repeatfreq
			);
			if (isset($result['inserted_id'])) {
				$billObj = $this->billMapper->find($result['inserted_id']);
				if (is_null($publicShareInfo)) {
					$authorFullText = $this->trans->t('Guest access');
				} elseif ($publicShareInfo['label']) {
					$authorName = $publicShareInfo['label'];
					$authorFullText = $this->trans->t('Share link (%s)', [$authorName]);
				} else {
					$authorFullText = $this->trans->t('Share link');
				}
				$this->activityManager->triggerEvent(
					ActivityManager::COSPEND_OBJECT_BILL, $billObj,
					ActivityManager::SUBJECT_BILL_CREATE,
					['author' => $authorFullText]
				);
				return new DataResponse($result['inserted_id']);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add bills')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a project member
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param string $name
	 * @param float $weight
	 * @param int $active
	 * @param string|null $color
	 * @param string|null $userid
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicCreateMember(
		string $projectId, string $password, string $name, float $weight = 1, int $active = 1,
		?string $color = null, ?string $userid = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addMember(
				$publicShareInfo['projectid'] ?? $projectId, $name, $weight, $active !== 0, $color, $userid
			);
			if (!isset($result['error'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result['error'], Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to add members')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get a project's bill list
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $lastchanged
	 * @param int|null $offset
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $payerId
	 * @param int|null $categoryId
	 * @param int|null $paymentModeId
	 * @param int|null $includeBillId
	 * @param string|null $searchTerm
	 * @param int|null $deleted
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicGetBills(
		string $projectId, string $password, ?int $lastchanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			if ($limit) {
				$bills = $this->billMapper->getBillsWithLimit(
					$publicShareInfo['projectid'] ?? $projectId, null, null,
					null, $paymentModeId, $categoryId, null, null,
					$lastchanged, $limit, $reverse, $offset, $payerId, $includeBillId, $searchTerm, $deleted
				);
			} else {
				$bills = $this->billMapper->getBills(
					$publicShareInfo['projectid'] ?? $projectId, null, null,
					null, $paymentModeId, $categoryId, null, null,
					$lastchanged, null, $reverse, $payerId, $deleted
				);
			}
			$billIds = $this->projectService->getAllBillIds($publicShareInfo['projectid'] ?? $projectId, $deleted);
			$ts = (new DateTime())->getTimestamp();
			$result = [
				'nb_bills' => $this->billMapper->countBills(
					$publicShareInfo['projectid'] ?? $projectId, $payerId, $categoryId, $paymentModeId, $deleted
				),
				'bills' => $bills,
				'allBillIds' => $billIds,
				'timestamp' => $ts,
			];
			return new DataResponse($result);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get a project's member list
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int|null $lastChanged
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicGetMembers(string $projectId, string $password, ?int $lastChanged = null): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if ($this->checkLogin($projectId, $password)
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password']))
		) {
			$members = $this->projectService->getMembers($publicShareInfo['projectid'] ?? $projectId, null, $lastChanged);
			return new DataResponse($members);
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete or disable a member
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $memberId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeleteMember(string $projectId, string $password, int $memberId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deleteMember($publicShareInfo['projectid'] ?? $projectId, $memberId);
			if (isset($result['success'])) {
				return new DataResponse('OK');
			} else {
				return new DataResponse($result, Http::STATUS_NOT_FOUND);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('Unauthorized action')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a payment mode
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicCreatePaymentMode(string $projectId, string $password, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addPaymentMode(
				$publicShareInfo['projectid'] ?? $projectId, $name, $icon, $color, $order
			);
			if (is_numeric($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a payment mode
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $pmId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditPaymentMode(
		string $projectId, string $password, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->editPaymentMode(
				$publicShareInfo['projectid'] ?? $projectId, $pmId, $name, $icon, $color
			);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Save payment modes order
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param array $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicSavePaymentModeOrder(string $projectId, string $password, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			if ($this->projectService->savePaymentModeOrder($publicShareInfo['projectid'] ?? $projectId, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete a payment mode
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $pmId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeletePaymentMode(string $projectId, string $password, int $pmId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deletePaymentMode($publicShareInfo['projectid'] ?? $projectId, $pmId);
			if (isset($result['success'])) {
				return new DataResponse($pmId);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage payment modes')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a category
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicCreateCategory(string $projectId, string $password, string $name, ?string $icon, string $color, ?int $order = 0): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addCategory(
				$publicShareInfo['projectid'] ?? $projectId, $name, $icon, $color, $order
			);
			if (is_numeric($result)) {
				// inserted category id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a category
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditCategory(
		string $projectId, string $password, int $categoryId,
		?string $name = null, ?string $icon = null, ?string $color = null
	): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->editCategory(
				$publicShareInfo['projectid'] ?? $projectId, $categoryId, $name, $icon, $color
			);
			if (is_array($result)) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Save categories order
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param array $order
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicSaveCategoryOrder(string $projectId, string $password, array $order): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			if ($this->projectService->saveCategoryOrder($publicShareInfo['projectid'] ?? $projectId, $order)) {
				return new DataResponse(true);
			} else {
				return new DataResponse(false, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $categoryId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeleteCategory(string $projectId, string $password, int $categoryId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deleteCategory($publicShareInfo['projectid'] ?? $projectId, $categoryId);
			if (isset($result['success'])) {
				return new DataResponse($categoryId);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage categories')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Create a currency
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicCreateCurrency(string $projectId, string $password, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->addCurrency($publicShareInfo['projectid'] ?? $projectId, $name, $rate);
			if (is_numeric($result)) {
				// inserted currency id
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicEditCurrency(string $projectId, string $password, int $currencyId, string $name, float $rate): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->editCurrency(
				$publicShareInfo['projectid'] ?? $projectId, $currencyId, $name, $rate
			);
			if (!isset($result['message'])) {
				return new DataResponse($result);
			} else {
				return new DataResponse($result, Http::STATUS_FORBIDDEN);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	/**
	 * Delete a currency
	 *
	 * @param string $projectId
	 * @param string $password
	 * @param int $currencyId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[CORS]
	public function publicDeleteCurrency(string $projectId, string $password, int $currencyId): DataResponse {
		$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($projectId);
		if (
			($this->checkLogin($projectId, $password) && $this->projectService->getGuestAccessLevel($projectId) >= Application::ACCESS_LEVELS['maintainer'])
			|| ($publicShareInfo !== null
				&& (is_null($publicShareInfo['password']) || $password === $publicShareInfo['password'])
				&& $publicShareInfo['accesslevel'] >= Application::ACCESS_LEVELS['maintainer'])
		) {
			$result = $this->projectService->deleteCurrency($publicShareInfo['projectid'] ?? $projectId, $currencyId);
			if (isset($result['success'])) {
				return new DataResponse($currencyId);
			} else {
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(
				['message' => $this->trans->t('You are not allowed to manage currencies')],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}
}
