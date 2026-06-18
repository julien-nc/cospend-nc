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
use OC\User\NoUserException;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\Exception\CospendBasicException;
use OCA\Cospend\Utils;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\Config\IUserConfig;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use Throwable;

class CospendService {

	public function __construct(
		private LocalProjectService $localProjectService,
		private InvitationMapper $invitationMapper,
		private ProjectMapper $projectMapper,
		private IRootFolder $root,
		private IL10N $l10n,
		private IUserManager $userManager,
		private IDbConnection $db,
		private IUserConfig $userConfig,
	) {
	}

	/**
	 * @param string $userId
	 * @return list<array{id: int, remoteProjectId: string, remoteProjectName: string, remoteServerUrl: string, state: int, userId: string, inviterCloudId: string, inviterDisplayName: string}>
	 * @throws Exception
	 */
	public function getFederatedProjects(string $userId): array {
		$invitations = $this->invitationMapper->getInvitationsForUser($userId, Invitation::STATE_ACCEPTED);
		$jsonInvitations = array_map(static function (Invitation $invitation) {
			$jsonInvitation = $invitation->jsonSerialize();
			unset($jsonInvitation['accessToken']);
			return $jsonInvitation;
		}, $invitations);
		return array_values($jsonInvitations);
	}

	/**
	 * Wrap the import process in an atomic DB transaction
	 * This increases insert performance a lot
	 *
	 * importCsvProject() still takes care of cleaning up created entities in case of error
	 * but this could be done by rollBack
	 *
	 * This could be done with TTransactional::atomic() when we drop support for NC < 24
	 *
	 * @param $handle
	 * @param string $userId
	 * @param string $projectName
	 * @return array
	 * @throws Throwable
	 * @throws \OCP\DB\Exception
	 */
	public function importCsvProjectAtomicWrapper($handle, string $userId, string $projectName): array {
		$this->db->beginTransaction();
		try {
			$result = $this->importCsvProjectStream($handle, $userId, $projectName);
			$this->db->commit();
			return $result;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Import CSV project file
	 *
	 * @param string $path
	 * @param string $userId
	 * @return array
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws Throwable
	 * @throws \OCP\DB\Exception
	 */
	public function importCsvProject(string $path, string $userId): array {
		$cleanPath = str_replace(['../', '..\\'], '', $path);
		$userFolder = $this->root->getUserFolder($userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if (($handle = $file->fopen('r')) !== false) {
					$projectName = preg_replace('/\.csv$/', '', $file->getName());
					return $this->importCsvProjectAtomicWrapper($handle, $userId, $projectName);
				} else {
					return ['message' => $this->l10n->t('Access denied')];
				}
			} else {
				return ['message' => $this->l10n->t('Access denied')];
			}
		} else {
			return ['message' => $this->l10n->t('Access denied')];
		}
	}

	/**
	 * @param $handle
	 * @param string $userId
	 * @param string $projectName
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function importCsvProjectStream($handle, string $userId, string $projectName): array {
		$columns = [];
		$membersByName = [];
		$bills = [];
		$currencies = [];
		$mainCurrencyName = null;
		$categories = [];
		$categoryIdConv = [];
		$paymentModes = [];
		$paymentModeIdConv = [];
		$previousLineEmpty = false;
		$currentSection = null;
		$row = 0;
		while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			$uni = array_unique($data);
			if ($data === [null] || (count($uni) === 1 && $uni[0] === '')) {
				$previousLineEmpty = true;
			} elseif ($row === 0 || $previousLineEmpty) {
				// determine which section we're entering
				$previousLineEmpty = false;
				$nbCol = count($data);
				$columns = [];
				for ($c = 0; $c < $nbCol; $c++) {
					if ($data[$c] !== '') {
						$columns[$data[$c]] = $c;
					}
				}
				if (array_key_exists('what', $columns)
					&& array_key_exists('amount', $columns)
					&& (array_key_exists('date', $columns) || array_key_exists('timestamp', $columns))
					&& array_key_exists('payer_name', $columns)
					&& array_key_exists('payer_weight', $columns)
					&& array_key_exists('owers', $columns)
				) {
					$currentSection = 'bills';
				} elseif (array_key_exists('name', $columns)
					&& array_key_exists('weight', $columns)
					&& array_key_exists('active', $columns)
					&& array_key_exists('color', $columns)
				) {
					$currentSection = 'members';
				} elseif (array_key_exists('icon', $columns)
					&& array_key_exists('color', $columns)
					&& array_key_exists('paymentmodeid', $columns)
					&& array_key_exists('paymentmodename', $columns)
				) {
					$currentSection = 'paymentmodes';
				} elseif (array_key_exists('icon', $columns)
					&& array_key_exists('color', $columns)
					&& array_key_exists('categoryid', $columns)
					&& array_key_exists('categoryname', $columns)
				) {
					$currentSection = 'categories';
				} elseif (array_key_exists('exchange_rate', $columns)
					&& array_key_exists('currencyname', $columns)
				) {
					$currentSection = 'currencies';
				} else {
					fclose($handle);
					return ['message' => $this->l10n->t('Malformed CSV, bad column names at line %1$s', [$row + 1])];
				}
			} else {
				// normal line: bill/category/payment mode/currency
				$previousLineEmpty = false;
				if ($currentSection === 'categories') {
					if (mb_strlen($data[$columns['icon']], 'UTF-8') && preg_match('!\S!u', $data[$columns['icon']])) {
						$icon = $data[$columns['icon']];
					} else {
						$icon = null;
					}
					$color = $data[$columns['color']];
					$categoryname = $data[$columns['categoryname']];
					if (!is_numeric($data[$columns['categoryid']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding category %1$s', [$categoryname])];
					}
					$categoryid = (int)$data[$columns['categoryid']];
					$categories[] = [
						'icon' => $icon,
						'color' => $color,
						'id' => $categoryid,
						'name' => $categoryname,
					];
				} elseif ($currentSection === 'paymentmodes') {
					if (mb_strlen($data[$columns['icon']], 'UTF-8') && preg_match('!\S!u', $data[$columns['icon']])) {
						$icon = $data[$columns['icon']];
					} else {
						$icon = null;
					}
					$paymentmodename = $data[$columns['paymentmodename']];
					if (!is_numeric($data[$columns['paymentmodeid']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding payment mode %1$s', [$paymentmodename])];
					}
					$color = $data[$columns['color']];
					$paymentmodeid = (int)$data[$columns['paymentmodeid']];
					$paymentModes[] = [
						'icon' => $icon,
						'color' => $color,
						'id' => $paymentmodeid,
						'name' => $paymentmodename,
					];
				} elseif ($currentSection === 'currencies') {
					$name = $data[$columns['currencyname']];
					if (!is_numeric($data[$columns['exchange_rate']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding currency %1$s', [$name])];
					}
					$exchange_rate = (float)$data[$columns['exchange_rate']];
					if (($exchange_rate) === 1.0) {
						$mainCurrencyName = $name;
					} else {
						$currencies[] = [
							'name' => $name,
							'exchange_rate' => $exchange_rate,
						];
					}
				} elseif ($currentSection === 'members') {
					$name = trim($data[$columns['name']]);
					if (!is_numeric($data[$columns['weight']]) || !is_numeric($data[$columns['active']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Error when adding member %1$s', [$name])];
					}
					$weight = (float)$data[$columns['weight']];
					$active = (int)$data[$columns['active']];
					$color = $data[$columns['color']];
					if (strlen($name) > 0
						&& preg_match('/^#[0-9A-Fa-f]+$/', $color) !== false
					) {
						$membersByName[$name] = [
							'weight' => $weight,
							'active' => $active !== 0,
							'color' => $color,
						];
					} else {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, invalid member on line %1$s', [$row + 1])];
					}
				} elseif ($currentSection === 'bills') {
					$what = $data[$columns['what']];
					if (!is_numeric($data[$columns['amount']])) {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, invalid amount on line %1$s', [$row + 1])];
					}
					$amount = (float)$data[$columns['amount']];
					$timestamp = null;
					// priority to timestamp
					if (array_key_exists('timestamp', $columns)) {
						$timestamp = (int)$data[$columns['timestamp']];
					} elseif (array_key_exists('date', $columns)) {
						$date = $data[$columns['date']];
						$datetime = DateTime::createFromFormat('Y-m-d', $date);
						if ($datetime !== false) {
							$timestamp = $datetime->getTimestamp();
						}
					}
					if ($timestamp === null) {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, missing or invalid date/timestamp on line %1$s', [$row + 1])];
					}
					$payer_name = $data[$columns['payer_name']];
					$payer_weight = $data[$columns['payer_weight']];
					$owers = $data[$columns['owers']];
					$payer_active = array_key_exists('payer_active', $columns) ? $data[$columns['payer_active']] : 1;
					$repeat = array_key_exists('repeat', $columns) ? $data[$columns['repeat']] : Application::FREQUENCY_NO;
					$categoryid = array_key_exists('categoryid', $columns) ? (int)$data[$columns['categoryid']] : null;
					$paymentmode = array_key_exists('paymentmode', $columns) ? $data[$columns['paymentmode']] : null;
					$paymentmodeid = array_key_exists('paymentmodeid', $columns) ? (int)$data[$columns['paymentmodeid']] : null;
					$repeatallactive = array_key_exists('repeatallactive', $columns) ? (int)$data[$columns['repeatallactive']] : 0;
					$repeatuntil = array_key_exists('repeatuntil', $columns) ? $data[$columns['repeatuntil']] : null;
					$repeatfreq = array_key_exists('repeatfreq', $columns) ? (int)$data[$columns['repeatfreq']] : 1;
					$comment = array_key_exists('comment', $columns) ? urldecode($data[$columns['comment']] ?? '') : null;
					$deleted = array_key_exists('deleted', $columns) ? (int)$data[$columns['deleted']] : 0;

					// manage members
					if (!isset($membersByName[$payer_name])) {
						$membersByName[$payer_name] = [
							'active' => ((int)$payer_active) !== 0,
							'weight' => 1.0,
							'color' => null,
						];
						if (is_numeric($payer_weight)) {
							$membersByName[$payer_name]['weight'] = (float)$payer_weight;
						} else {
							fclose($handle);
							return ['message' => $this->l10n->t('Malformed CSV, invalid payer weight on line %1$s', [$row + 1])];
						}
					}
					if (strlen($owers) === 0) {
						fclose($handle);
						return ['message' => $this->l10n->t('Malformed CSV, invalid owers on line %1$s', [$row + 1])];
					}
					if ($what !== 'deleteMeIfYouWant') {
						$owersArray = explode(',', $owers);
						foreach ($owersArray as $ower) {
							$strippedOwer = trim($ower);
							if (strlen($strippedOwer) === 0) {
								fclose($handle);
								return ['message' => $this->l10n->t('Malformed CSV, invalid owers on line %1$s', [$row + 1])];
							}
							if (!isset($membersByName[$strippedOwer])) {
								$membersByName[$strippedOwer]['weight'] = 1.0;
								$membersByName[$strippedOwer]['active'] = true;
								$membersByName[$strippedOwer]['color'] = null;
							}
						}
						$bills[] = [
							'what' => $what,
							'comment' => $comment,
							'timestamp' => $timestamp,
							'amount' => $amount,
							'payer_name' => $payer_name,
							'owers' => $owersArray,
							'paymentmode' => $paymentmode,
							'paymentmodeid' => $paymentmodeid,
							'categoryid' => $categoryid,
							'repeat' => $repeat,
							'repeatuntil' => $repeatuntil,
							'repeatallactive' => $repeatallactive,
							'repeatfreq' => $repeatfreq,
							'deleted' => $deleted,
						];
					}
				}
			}
			$row++;
		}
		fclose($handle);

		$memberNameToId = [];

		// add project
		$user = $this->userManager->get($userId);
		$userEmail = $user->getEMailAddress();
		$projectId = Utils::slugify($projectName);
		if ($projectId === '') {
			$projectId = 'empty';
		}
		$projectId = $this->findUniqueProjectId($projectId);
		$createDefaultCategories = (count($categories) === 0);
		$createDefaultPaymentModes = (count($paymentModes) === 0);
		$projResult = $this->localProjectService->createProject(
			$projectName, $projectId, $userEmail, $userId,
			$createDefaultCategories, $createDefaultPaymentModes
		);
		if (!isset($projResult['id'])) {
			return ['message' => $this->l10n->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
		}
		// set project main currency
		if ($mainCurrencyName !== null) {
			$this->localProjectService->editProject($projectId, $projectName, null, null, $mainCurrencyName);
		}
		// add payment modes
		foreach ($paymentModes as $pm) {
			$insertedPmId = $this->localProjectService->createPaymentMode($projectId, $pm['name'], $pm['icon'], $pm['color']);
			$paymentModeIdConv[$pm['id']] = $insertedPmId;
		}
		// add categories
		foreach ($categories as $cat) {
			$insertedCatId = $this->localProjectService->createCategory($projectId, $cat['name'], $cat['icon'], $cat['color']);
			$categoryIdConv[$cat['id']] = $insertedCatId;
		}
		// add currencies
		foreach ($currencies as $cur) {
			$insertedCurId = $this->localProjectService->createCurrency($projectId, $cur['name'], $cur['exchange_rate']);
		}
		// add members
		foreach ($membersByName as $memberName => $member) {
			try {
				$insertedMember = $this->localProjectService->createMember(
					$projectId, $memberName, $member['weight'], $member['active'], $member['color'] ?? null
				);
			} catch (\Throwable $e) {
				$this->localProjectService->deleteProject($projectId);
				return ['message' => $this->l10n->t('Error when adding member %1$s', [$memberName])];
			}
			$memberNameToId[$memberName] = $insertedMember['id'];
		}
		// add bills
		foreach ($bills as $bill) {
			// manage category id if this is a custom category
			$catId = $bill['categoryid'];
			if ($catId !== null && $catId > 0) {
				$catId = $categoryIdConv[$catId];
			}
			// manage payment mode id if this is a custom payment mode
			$pmId = $bill['paymentmodeid'];
			if ($pmId !== null && $pmId > 0) {
				$pmId = $paymentModeIdConv[$pmId];
			}
			$payerId = $memberNameToId[$bill['payer_name']];
			$owerIds = [];
			foreach ($bill['owers'] as $owerName) {
				$strippedOwer = trim($owerName);
				$owerIds[] = $memberNameToId[$strippedOwer];
			}
			$owerIdsStr = implode(',', $owerIds);
			try {
				$this->localProjectService->createBill(
					$projectId, null, $bill['what'], $payerId,
					$owerIdsStr, $bill['amount'], $bill['repeat'],
					$bill['paymentmode'], $pmId,
					$catId, $bill['repeatallactive'],
					$bill['repeatuntil'], $bill['timestamp'], $bill['comment'], $bill['repeatfreq'],
					$bill['deleted'] ?? 0
				);
			} catch (\Throwable $e) {
				$this->localProjectService->deleteProject($projectId);
				return ['message' => $this->l10n->t('Error when adding bill %1$s', [$bill['what']])];
			}
		}
		return ['project_id' => $projectId];
	}

	private function findUniqueProjectId(string $projectId): string {
		try {
			$this->projectMapper->getById($projectId);
		} catch (DoesNotExistException) {
			// this projectId is free
			return $projectId;
		}
		$suffix = 1;
		while ($suffix < 50) {
			$suffixedProjectId = $projectId . '-' . $suffix;
			try {
				$this->projectMapper->getById($suffixedProjectId);
			} catch (DoesNotExistException) {
				// this projectId is free
				return $suffixedProjectId;
			}
			$suffix++;
		}
		return $projectId . '-' . $suffix;
	}

	/**
	 * Import SplitWise project file
	 *
	 * @param string $path
	 * @param string $userId
	 * @return array
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function importSWProject(string $path, string $userId): array {
		$cleanPath = str_replace(['../', '..\\'], '', $path);
		$userFolder = $this->root->getUserFolder($userId);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if (($handle = $file->fopen('r')) !== false) {
					$columns = [];
					$membersWeight = [];
					$bills = [];
					$owersArray = [];
					$categoryNames = [];
					$row = 0;
					$nbCol = 0;

					$columnNamesLineFound = false;
					while (($data = fgetcsv($handle, 1000, ',')) !== false) {
						// look for column order line
						if (!$columnNamesLineFound) {
							$nbCol = count($data);
							for ($c = 0; $c < $nbCol; $c++) {
								$columns[$data[$c]] = $c;
							}
							if (!array_key_exists('Date', $columns)
								|| !array_key_exists('Description', $columns)
								|| !array_key_exists('Category', $columns)
								|| !array_key_exists('Cost', $columns)
								|| !array_key_exists('Currency', $columns)
							) {
								$columns = [];
								$row++;
								continue;
							}
							$columnNamesLineFound = true;
							// manage members
							$m = 0;
							for ($c = 5; $c < $nbCol; $c++) {
								$owersArray[$m] = $data[$c];
								$m++;
							}
							foreach ($owersArray as $ower) {
								if (strlen($ower) === 0) {
									fclose($handle);
									return ['message' => $this->l10n->t('Malformed CSV, cannot have an empty ower')];
								}
								if (!array_key_exists($ower, $membersWeight)) {
									$membersWeight[$ower] = 1.0;
								}
							}
						} elseif (!isset($data[$columns['Date']]) || empty($data[$columns['Date']])) {
							// skip empty lines
						} elseif (isset($data[$columns['Description']]) && $data[$columns['Description']] === 'Total balance') {
							// skip the total lines
						} else {
							// normal line : bill
							$what = $data[$columns['Description']];
							$cost = trim($data[$columns['Cost']]);
							if (empty($cost)) {
								// skip lines with no cost, it might be the balances line
								$row++;
								continue;
							}
							$date = $data[$columns['Date']];
							$datetime = DateTime::createFromFormat('Y-m-d', $date);
							if ($datetime === false) {
								fclose($handle);
								return ['message' => $this->l10n->t('Malformed CSV, missing or invalid date/timestamp on line %1$s', [$row])];
							}
							$timestamp = $datetime->getTimestamp();

							$categoryName = null;
							// manage categories
							if (array_key_exists('Category', $columns)
								&& $data[$columns['Category']] !== null
								&& $data[$columns['Category']] !== '') {
								$categoryName = $data[$columns['Category']];
								if (!in_array($categoryName, $categoryNames)) {
									$categoryNames[] = $categoryName;
								}
							}

							// new algorithm
							// get those with a negative value, they will be the owers in generated bills
							$negativeCols = [];
							for ($c = 5; $c < $nbCol; $c++) {
								if (!is_numeric($data[$c])) {
									fclose($handle);
									return ['message' => $this->l10n->t('Malformed CSV, bad amount on line %1$s', [$row])];
								}
								$amount = (float)$data[$c];
								if ($amount < 0) {
									$negativeCols[] = $c;
								}
							}
							$owersList = array_map(static function ($c) use ($owersArray) {
								return $owersArray[$c - 5];
							}, $negativeCols);
							// each positive one: bill with member-specific amount (not the full amount), owers are the negative ones
							for ($c = 5; $c < $nbCol; $c++) {
								$amount = (float)$data[$c];
								if ($amount > 0) {
									$payer_name = $owersArray[$c - 5];
									if (empty($payer_name)) {
										fclose($handle);
										return ['message' => $this->l10n->t('Malformed CSV, no payer on line %1$s', [$row])];
									}
									$bill = [
										'what' => $what,
										'timestamp' => $timestamp,
										'amount' => $amount,
										'payer_name' => $payer_name,
										'owers' => $owersList
									];
									if ($categoryName !== null) {
										$bill['category_name'] = $categoryName;
									}
									$bills[] = $bill;
								}
							}
						}
						$row++;
					}
					fclose($handle);

					if (!$columnNamesLineFound) {
						return ['message' => $this->l10n->t('Malformed CSV, impossible to find the column names. Make sure your Splitwise account language is set to English first, then export the project again.')];
					}

					$memberNameToId = [];

					// add project
					$user = $this->userManager->get($userId);
					$userEmail = $user->getEMailAddress();
					$projectName = preg_replace('/\.csv$/', '', $file->getName());
					$projectId = Utils::slugify($projectName);
					if ($projectId === '') {
						$projectId = 'empty';
					}
					$projectId = $this->findUniqueProjectId($projectId);
					// create default categories only if none are found in the CSV
					$createDefaultCategories = (count($categoryNames) === 0);
					$projResult = $this->localProjectService->createProject(
						$projectName, $projectId, $userEmail,
						$userId, $createDefaultCategories
					);
					if (!isset($projResult['id'])) {
						return ['message' => $this->l10n->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
					}
					// add categories
					$catNameToId = [];
					foreach ($categoryNames as $categoryName) {
						$insertedCatId = $this->localProjectService->createCategory($projectId, $categoryName, null, '#000000');
						/*
						if (!is_numeric($insertedCatId)) {
							$this->deleteProject($projectid);
							return ['message' => $this->l10n->t('Error when adding category %1$s', [$categoryName])];
						}
						*/
						$catNameToId[$categoryName] = $insertedCatId;
					}
					// add members
					foreach ($membersWeight as $memberName => $weight) {
						try {
							$insertedMember = $this->localProjectService->createMember($projectId, $memberName, $weight);
						} catch (\Throwable $e) {
							$this->localProjectService->deleteProject($projectId);
							return ['message' => $this->l10n->t('Error when adding member %1$s', [$memberName])];
						}
						$memberNameToId[$memberName] = $insertedMember['id'];
					}
					// add bills
					foreach ($bills as $bill) {
						$payerId = $memberNameToId[$bill['payer_name']];
						$owerIds = [];
						foreach ($bill['owers'] as $owerName) {
							$owerIds[] = $memberNameToId[$owerName];
						}
						$owerIdsStr = implode(',', $owerIds);
						// category
						$catId = null;
						if (array_key_exists('category_name', $bill)
							&& array_key_exists($bill['category_name'], $catNameToId)) {
							$catId = $catNameToId[$bill['category_name']];
						}
						try {
							$this->localProjectService->createBill(
								$projectId, null, $bill['what'], $payerId, $owerIdsStr,
								$bill['amount'], Application::FREQUENCY_NO, null, 0, $catId,
								0, null, $bill['timestamp'], null, null
							);
						} catch (\Throwable $e) {
							$this->localProjectService->deleteProject($projectId);
							return ['message' => $this->l10n->t('Error when adding bill %1$s', [$bill['what']])];
						}
					}
					return ['project_id' => $projectId];
				} else {
					return ['message' => $this->l10n->t('Access denied')];
				}
			} else {
				return ['message' => $this->l10n->t('Access denied')];
			}
		} else {
			return ['message' => $this->l10n->t('Access denied')];
		}
	}

	/**
	 * auto export
	 * triggered by NC cron job
	 *
	 * export projects
	 */
	public function cronAutoExport(): void {
		date_default_timezone_set('UTC');
		// last day
		$now = new DateTime();
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');

		// get begining of today
		$dateMaxDay = new DateTime($y . '-' . $m . '-' . $d);
		$maxDayTimestamp = $dateMaxDay->getTimestamp();
		$minDayTimestamp = $maxDayTimestamp - (24 * 60 * 60);

		$dateMaxDay->modify('-1 day');
		$dailySuffix = '_' . $this->l10n->t('daily') . '_' . $dateMaxDay->format('Y-m-d');

		// last week
		$now = new DateTime();
		while (((int)$now->format('N')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateWeekMax = new DateTime($y . '-' . $m . '-' . $d);
		$maxWeekTimestamp = $dateWeekMax->getTimestamp();
		$minWeekTimestamp = $maxWeekTimestamp - (7 * 24 * 60 * 60);
		$dateWeekMin = new DateTime($y . '-' . $m . '-' . $d);
		$dateWeekMin->modify('-7 day');
		$weeklySuffix = '_' . $this->l10n->t('weekly') . '_' . $dateWeekMin->format('Y-m-d');

		// last month
		$now = new DateTime();
		while (((int)$now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateMonthMax = new DateTime($y . '-' . $m . '-' . $d);
		$maxMonthTimestamp = $dateMonthMax->getTimestamp();
		$now->modify('-1 day');
		while (((int)$now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = (int)$now->format('Y');
		$m = (int)$now->format('m');
		$d = (int)$now->format('d');
		$dateMonthMin = new DateTime($y . '-' . $m . '-' . $d);
		$minMonthTimestamp = $dateMonthMin->getTimestamp();
		$monthlySuffix = '_' . $this->l10n->t('monthly') . '_' . $dateMonthMin->format('Y-m');

		// $weekFilterArray = [];
		// $weekFilterArray['tsmin'] = $minWeekTimestamp;
		// $weekFilterArray['tsmax'] = $maxWeekTimestamp;
		// $dayFilterArray = [];
		// $dayFilterArray['tsmin'] = $minDayTimestamp;
		// $dayFilterArray['tsmax'] = $maxDayTimestamp;
		// $monthFilterArray = [];
		// $monthFilterArray['tsmin'] = $minMonthTimestamp;
		// $monthFilterArray['tsmax'] = $maxMonthTimestamp;

		$userIds = $this->projectMapper->getUserIdsWithAutoExportProjects();

		foreach ($userIds as $uid) {
			$outPath = $this->userConfig->getValueString($uid, Application::APP_ID, 'outputDirectory', '/Cospend', lazy: true);

			$projects = $this->projectMapper->getUserProjectsWithAutoExport($uid);
			if (empty($projects)) {
				continue;
			}

			$userFolder = $this->root->getUserFolder($uid);
			foreach ($projects as $project) {
				/** @var string $dbProjectId */
				$dbProjectId = $project->getId();
				$autoExport = $project->getAutoExport();

				$suffix = $dailySuffix;
				// TODO add suffix for all frequencies
				if ($autoExport === Application::FREQUENCY_WEEKLY) {
					$suffix = $weeklySuffix;
				} elseif ($autoExport === Application::FREQUENCY_MONTHLY) {
					$suffix = $monthlySuffix;
				}
				// check if file already exists
				$exportName = $dbProjectId . $suffix . '.csv';

				if (!$userFolder->nodeExists($outPath . '/' . $exportName)) {
					$projectInfo = $this->localProjectService->getProjectInfoWithAccessLevel($dbProjectId, $uid);
					$bills = $this->localProjectService->getBills($dbProjectId);
					$this->exportCsvProject($dbProjectId, $uid, $projectInfo, $bills['bills'] ?? [], $exportName);
				}
			}
		}
	}

	/**
	 * Create directory where things will be exported
	 *
	 * @param Folder $userFolder
	 * @param string $outPath
	 * @return string
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function createAndCheckExportDirectory(Folder $userFolder, string $outPath): string {
		if (!$userFolder->nodeExists($outPath)) {
			$userFolder->newFolder($outPath);
		}
		if ($userFolder->nodeExists($outPath)) {
			$folder = $userFolder->get($outPath);
			if (!$folder instanceof Folder) {
				return $this->l10n->t('%1$s is not a folder', [$outPath]);
			} elseif (!$folder->isCreatable()) {
				return $this->l10n->t('%1$s is not writeable', [$outPath]);
			} else {
				return '';
			}
		} else {
			return $this->l10n->t('Impossible to create %1$s', [$outPath]);
		}
	}

	/**
	 * Export settlement plan in CSV
	 * controller get the settlement with IProjectService->getSettlement and then calls CospendService export(settlement) method
	 * to store it in the current user's storage
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param array $settlement
	 * @param array $members
	 * @return array
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws InvalidPathException
	 * @throws LockedException
	 */
	public function exportCsvSettlement(string $projectId, string $userId, array $settlement, array $members): array {
		// create export directory if needed
		$outPath = $this->userConfig->getValueString($userId, Application::APP_ID, 'outputDirectory', '/Cospend', lazy: true);
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);
		if (!$folder instanceof Folder) {
			return ['message' => $outPath . ' is not a directory'];
		}

		// create file
		if ($folder->nodeExists($projectId . '-settlement.csv')) {
			$folder->get($projectId . '-settlement.csv')->delete();
		}
		$file = $folder->newFile($projectId . '-settlement.csv');
		$handler = $file->fopen('w');
		fwrite(
			$handler,
			'"' . $this->l10n->t('Who pays?')
			. '","' . $this->l10n->t('To whom?')
			. '","' . $this->l10n->t('How much?')
			. '"' . "\n"
		);
		$transactions = $settlement['transactions'];

		$memberIdToName = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
		}

		foreach ($transactions as $transaction) {
			fwrite(
				$handler,
				'"' . $memberIdToName[$transaction['from']]
				. '","' . $memberIdToName[$transaction['to']]
				. '",' . strval((float)$transaction['amount'])
				. "\n"
			);
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $projectId . '-settlement.csv'];
	}

	/**
	 * controller get the stats with IProjectService->getStatistics and then calls CospendService export(stats) method
	 * to store it in the current user's storage
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param array $statistics
	 * @return array
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function exportCsvStatistics(
		string $projectId, string $userId, array $statistics,
	): array {
		// create export directory if needed
		$outPath = $this->userConfig->getValueString($userId, Application::APP_ID, 'outputDirectory', '/Cospend', lazy: true);
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);
		if (!$folder instanceof Folder) {
			return ['message' => $outPath . ' is not a directory'];
		}

		// create file
		if ($folder->nodeExists($projectId . '-stats.csv')) {
			$folder->get($projectId . '-stats.csv')->delete();
		}
		$file = $folder->newFile($projectId . '-stats.csv');
		$handler = $file->fopen('w');
		fwrite(
			$handler,
			$this->l10n->t('Member name')
			. ',' . $this->l10n->t('Paid')
			. ',' . $this->l10n->t('Spent')
			. ',' . $this->l10n->t('Balance')
			. "\n"
		);
		$stats = $statistics['stats'];

		foreach ($stats as $stat) {
			fwrite(
				$handler,
				'"' . $stat['member']['name']
				. '",' . strval((float)$stat['paid'])
				. ',' . strval((float)$stat['spent'])
				. ',' . strval((float)$stat['balance'])
				. "\n"
			);
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $projectId . '-stats.csv'];
	}

	/**
	 * Export project in CSV
	 *
	 * @param string $projectId
	 * @param string $userId
	 * @param array $projectInfo
	 * @param array $bills
	 * @param string|null $name
	 * @return array
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function exportCsvProject(string $projectId, string $userId, array $projectInfo, array $bills, ?string $name = null): array {
		// create export directory if needed
		$outPath = $this->userConfig->getValueString($userId, Application::APP_ID, 'outputDirectory', '/Cospend', lazy: true);
		$userFolder = $this->root->getUserFolder($userId);
		$msg = $this->createAndCheckExportDirectory($userFolder, $outPath);
		if ($msg !== '') {
			return ['message' => $msg];
		}
		$folder = $userFolder->get($outPath);
		if (!$folder instanceof Folder) {
			return ['message' => $outPath . ' is not a directory'];
		}

		// create file
		$filename = $projectId . '.csv';
		if ($name !== null) {
			$filename = $name;
			if (!str_ends_with($filename, '.csv')) {
				$filename .= '.csv';
			}
		}
		if ($folder->nodeExists($filename)) {
			$folder->get($filename)->delete();
		}
		$file = $folder->newFile($filename);
		$handler = $file->fopen('w');
		foreach ($this->getJsonProject($projectInfo, $bills) as $chunk) {
			fwrite($handler, $chunk);
		}

		fclose($handler);
		$file->touch();
		return ['path' => $outPath . '/' . $filename];
	}

	/**
	 * @param array $projectInfo
	 * @param array $bills
	 * @return Generator
	 */
	public function getJsonProject(array $projectInfo, array $bills): Generator {
		// members
		yield "name,weight,active,color\n";
		$members = $projectInfo['members'];
		$memberIdToName = [];
		$memberIdToWeight = [];
		$memberIdToActive = [];
		foreach ($members as $member) {
			$memberIdToName[$member['id']] = $member['name'];
			$memberIdToWeight[$member['id']] = $member['weight'];
			$memberIdToActive[$member['id']] = (int)$member['activated'];
			$c = $member['color'];
			yield '"' . $member['name'] . '",'
				. strval((float)$member['weight']) . ','
				. (int)$member['activated'] . ',"'
				. sprintf('#%02x%02x%02x', $c['r'] ?? 0, $c['g'] ?? 0, $c['b'] ?? 0) . '"'
				. "\n";
		}
		// bills
		yield "\nwhat,amount,date,timestamp,payer_name,payer_weight,payer_active,owers,repeat,repeatfreq,repeatallactive,repeatuntil,categoryid,paymentmode,paymentmodeid,comment,deleted\n";
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
			// escaping double quotes by doubling them: https://stackoverflow.com/a/17808731
			yield '"' . str_replace('"', '""', $bill['what']) . '",'
				. strval((float)$bill['amount']) . ','
				. $oldDateStr . ','
				. $bill['timestamp'] . ','
				. '"' . str_replace('"', '""', $payer_name) . '",'
				. strval((float)$payer_weight) . ','
				. $payer_active . ','
				. '"' . str_replace('"', '""', $owersTxt) . '",'
				. $bill['repeat'] . ','
				. $bill['repeatfreq'] . ','
				. $bill['repeatallactive'] . ','
				. $bill['repeatuntil'] . ','
				. $bill['categoryid'] . ','
				. $bill['paymentmode'] . ','
				. $bill['paymentmodeid'] . ','
				. '"' . str_replace('"', '""', urlencode($bill['comment'])) . '",'
				. $bill['deleted']
				. "\n";
		}

		// write categories
		$categories = $projectInfo['categories'];
		if (count($categories) > 0) {
			yield "\ncategoryname,categoryid,icon,color\n";
			foreach ($categories as $id => $cat) {
				yield '"' . $cat['name'] . '",'
					. (int)$id . ',"'
					. $cat['icon'] . '","'
					. $cat['color'] . '"'
					. "\n";
			}
		}

		// write payment modes
		$paymentModes = $projectInfo['paymentmodes'];
		if (count($paymentModes) > 0) {
			yield "\npaymentmodename,paymentmodeid,icon,color\n";
			foreach ($paymentModes as $id => $pm) {
				yield '"' . $pm['name'] . '",'
					. (int)$id . ',"'
					. $pm['icon'] . '","'
					. $pm['color'] . '"'
					. "\n";
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
					. '",' . strval((float)$cur['exchange_rate'])
					. "\n";
			}
		}

		return [];
	}

	/**
	 * Aggregate user balances with other members across all non-archived projects.
	 *
	 * @param string $userId
	 * @return array{currencyTotals: list<array{currency: string, totalOwed: float, totalOwedTo: float, netBalance: float}>, personBalances: list<array{personKey: string, member: array{name: string, userid: ?string, id: int}, currencyBalances: array<string, array{currency: string, totalBalance: float, projects: list<array{projectId: string, projectName: string, balance: float}>}>, projects: list<array{projectId: string, projectName: string, currency: string, balance: float}>}>, summary: array<string, array{currency: string, owed: list<array{member: array{name: string, userid: ?string, id: int}, amount: float}>, owedTo: list<array{member: array{name: string, userid: ?string, id: int}, amount: float}>}>}
	 *
	 * @psalm-suppress MoreSpecificReturnType
	 */
	public function getCrossProjectBalances(string $userId): array {
		$projects = $this->localProjectService->getLocalProjects($userId);
		$currencyTotals = [];
		$personBalances = [];

		if (empty($projects)) {
			return [
				'currencyTotals' => [],
				'personBalances' => [],
				'summary' => [],
			];
		}

		foreach ($projects as $project) {
			if (($project['archived_ts'] ?? null) !== null) {
				continue;
			}

			$projectId = $project['id'];
			$projectName = $project['name'];
			$projectCurrency = $project['currencyname'] ?? '';
			if ($projectCurrency === '') {
				$projectCurrency = $this->l10n->t('No currency');
			}

			if (!isset($currencyTotals[$projectCurrency])) {
				$currencyTotals[$projectCurrency] = [
					'currency' => $projectCurrency,
					'totalOwed' => 0.0,
					'totalOwedTo' => 0.0,
					'netBalance' => 0.0,
				];
			}

			$balances = $project['balance'] ?? [];
			$members = $project['members'] ?? [];

			$currentUserMemberId = null;
			foreach ($members as $member) {
				if (($member['userid'] ?? null) === $userId) {
					$currentUserMemberId = (int)$member['id'];
					break;
				}
			}

			if ($currentUserMemberId === null) {
				continue;
			}

			$this->calculateProjectDebtsWithCurrency(
				$projectId,
				$currentUserMemberId,
				$members,
				$balances,
				$projectName,
				$projectCurrency,
				$personBalances,
				$currencyTotals,
			);
		}

		foreach ($currencyTotals as &$totals) {
			$totals['netBalance'] = $totals['totalOwedTo'] - $totals['totalOwed'];
		}
		unset($totals);

		$summary = [];
		foreach ($currencyTotals as $currency => $totals) {
			$summary[$currency] = [
				'currency' => $currency,
				'owed' => [],
				'owedTo' => [],
			];
		}

		foreach ($personBalances as $personBalance) {
			$member = $personBalance['member'];
			foreach ($personBalance['currencyBalances'] as $currency => $currencyBalance) {
				$balance = $currencyBalance['totalBalance'];
				if ($balance > 0.01) {
					$summary[$currency]['owed'][] = [
						'member' => $member,
						'amount' => $balance,
					];
				} elseif ($balance < -0.01) {
					$summary[$currency]['owedTo'][] = [
						'member' => $member,
						'amount' => abs($balance),
					];
				}
			}
		}

		/** @psalm-suppress LessSpecificReturnStatement */
		return [
			'currencyTotals' => array_values($currencyTotals),
			'personBalances' => array_values($personBalances),
			'summary' => $summary,
		];
	}

	/**
	 * @param string $projectId
	 * @param int $currentUserMemberId
	 * @param list<array<string, mixed>> $members
	 * @param array<string, float> $balances
	 * @param string $projectName
	 * @param string $projectCurrency
	 * @param array<string, array<string, mixed>> $personBalances
	 * @param array<string, array{currency: string, totalOwed: float, totalOwedTo: float, netBalance: float}> $currencyTotals
	 * @return void
	 */
	private function calculateProjectDebtsWithCurrency(
		string $projectId,
		int $currentUserMemberId,
		array $members,
		array $balances,
		string $projectName,
		string $projectCurrency,
		array &$personBalances,
		array &$currencyTotals,
	): void {
		foreach ($members as $member) {
			$memberId = (int)($member['id'] ?? 0);
			if ($memberId === $currentUserMemberId) {
				continue;
			}
			if (!(bool)($member['activated'] ?? true)) {
				continue;
			}

			$memberBalance = (float)($balances[(string)$memberId] ?? 0.0);
			if (abs($memberBalance) <= 0.01) {
				continue;
			}

			$personIdentifier = $this->getPersonIdentifier($member);
			if (!isset($personBalances[$personIdentifier])) {
				$personBalances[$personIdentifier] = [
					'personKey' => $personIdentifier,
					'member' => [
						'name' => (string)($member['name'] ?? 'Unknown'),
						'userid' => $member['userid'] ?? null,
						'id' => $memberId,
					],
					'currencyBalances' => [],
					'projects' => [],
				];
			}

			if (!isset($personBalances[$personIdentifier]['currencyBalances'][$projectCurrency])) {
				$personBalances[$personIdentifier]['currencyBalances'][$projectCurrency] = [
					'currency' => $projectCurrency,
					'totalBalance' => 0.0,
					'projects' => [],
				];
			}

			$personBalances[$personIdentifier]['currencyBalances'][$projectCurrency]['totalBalance'] += $memberBalance;
			$personBalances[$personIdentifier]['currencyBalances'][$projectCurrency]['projects'][] = [
				'projectId' => $projectId,
				'projectName' => $projectName,
				'balance' => $memberBalance,
			];
			$personBalances[$personIdentifier]['projects'][] = [
				'projectId' => $projectId,
				'projectName' => $projectName,
				'currency' => $projectCurrency,
				'balance' => $memberBalance,
			];

			if ($memberBalance > 0) {
				$currencyTotals[$projectCurrency]['totalOwed'] += $memberBalance;
			} else {
				$currencyTotals[$projectCurrency]['totalOwedTo'] += abs($memberBalance);
			}
		}
	}

	/**
	 * @param array<string, mixed> $member
	 * @return string
	 */
	private function getPersonIdentifier(array $member): string {
		$userId = (string)($member['userid'] ?? '');
		if ($userId !== '') {
			return 'user=' . $userId;
		}
		return 'name=' . str_replace(' ', '-', strtolower(trim((string)($member['name'] ?? ''))));
	}

	/**
	 * @param string $currentUserId
	 * @param string $targetUserId
	 * @param string $targetUserName
	 * @param string $currency
	 * @param float $totalAmount
	 * @param bool $isPayment
	 * @param list<array{projectId: string, billAmount: float, timestamp?: int, paymentModeId?: int, comment?: string}> $projectBreakdown
	 * @return void
	 */
	public function createCrossProjectSettlement(
		string $currentUserId,
		string $targetUserId,
		string $targetUserName,
		string $currency,
		float $totalAmount,
		bool $isPayment,
		array $projectBreakdown,
	): void {
		if (empty($projectBreakdown)) {
			throw new CospendBasicException('', Http::STATUS_BAD_REQUEST, ['message' => $this->l10n->t('No projects specified for settlement')]);
		}
		if ($totalAmount <= 0) {
			throw new CospendBasicException('', Http::STATUS_BAD_REQUEST, ['message' => $this->l10n->t('Settlement amount must be positive')]);
		}

		$userProjects = $this->localProjectService->getLocalProjects($currentUserId);
		$userProjectIds = array_column($userProjects, 'id');

		$currentUserName = $currentUserId;
		$defaultTimestamp = (new DateTime())->getTimestamp();
		foreach ($projectBreakdown as $projectInfo) {
			$projectId = (string)$projectInfo['projectId'];
			$billAmount = (float)$projectInfo['billAmount'];

			if (!in_array($projectId, $userProjectIds, true)) {
				throw new CospendBasicException('', Http::STATUS_FORBIDDEN, ['message' => $this->l10n->t('Access denied to project %1$s', [$projectId])]);
			}
			if ($billAmount < 0.01) {
				continue;
			}

			$members = $this->localProjectService->getMembers($projectId);
			$currentUserMember = null;
			$targetUserMember = null;

			foreach ($members as $member) {
				if (($member['userid'] ?? null) === $currentUserId) {
					$currentUserMember = $member;
					$currentUserName = $member['name'];
				}
				if (($member['userid'] ?? null) === $targetUserId
					|| (($member['userid'] ?? null) === null && ($member['name'] ?? '') === $targetUserName)
				) {
					$targetUserMember = $member;
				}
			}

			if ($currentUserMember === null || $targetUserMember === null) {
				continue;
			}

			if ($isPayment) {
				$payerId = (int)$currentUserMember['id'];
				$owerId = (int)$targetUserMember['id'];
				$billTitle = $currentUserName . ' -> ' . $targetUserName;
			} else {
				$payerId = (int)$targetUserMember['id'];
				$owerId = (int)$currentUserMember['id'];
				$billTitle = $targetUserName . ' -> ' . $currentUserName;
			}

			$billTimestamp = isset($projectInfo['timestamp']) ? (int)$projectInfo['timestamp'] : $defaultTimestamp;
			$paymentModeId = isset($projectInfo['paymentModeId']) ? (int)$projectInfo['paymentModeId'] : null;
			$comment = isset($projectInfo['comment']) ? trim((string)$projectInfo['comment']) : null;
			if ($comment !== null && strlen($comment) > 300) {
				throw new CospendBasicException('', Http::STATUS_BAD_REQUEST, ['message' => $this->l10n->t('Comment too long for project %1$s (max 300 characters)', [$projectId])]);
			}

			try {
				$billId = $this->localProjectService->createBill(
					$projectId,
					null,
					$billTitle,
					$payerId,
					(string)$owerId,
					$billAmount,
					Application::FREQUENCY_NO,
					null,
					$paymentModeId,
					Application::CATEGORY_REIMBURSEMENT,
					0,
					null,
					$billTimestamp,
					$comment,
					null,
					0,
					true,
				);
				if ($billId <= 0) {
					throw new CospendBasicException('', Http::STATUS_BAD_REQUEST, ['message' => $this->l10n->t('Failed to create bill in project %1$s', [$projectId])]);
				}
			} catch (\Exception $e) {
				throw new CospendBasicException('', Http::STATUS_BAD_REQUEST, ['message' => $this->l10n->t('Failed to create bill in project %1$s: %2$s', [$projectId, $e->getMessage()])]);
			}
		}
	}
}
