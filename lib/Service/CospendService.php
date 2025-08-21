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
use OCA\Cospend\Utils;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use Throwable;

class CospendService {

	public function __construct(
		private LocalProjectService $localProjectService,
		private InvitationMapper $invitationMapper,
		private IRootFolder $root,
		private IL10N $l10n,
		private IUserManager $userManager,
		private IDbConnection $db,
		private IConfig $config,
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
		while (($data = fgetcsv($handle, 0, ',')) !== false) {
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
		$projectid = Utils::slugify($projectName);
		$createDefaultCategories = (count($categories) === 0);
		$createDefaultPaymentModes = (count($paymentModes) === 0);
		$projResult = $this->localProjectService->createProject(
			$projectName, $projectid, $userEmail, $userId,
			$createDefaultCategories, $createDefaultPaymentModes
		);
		if (!isset($projResult['id'])) {
			return ['message' => $this->l10n->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
		}
		// set project main currency
		if ($mainCurrencyName !== null) {
			$this->localProjectService->editProject($projectid, $projectName, null, null, $mainCurrencyName);
		}
		// add payment modes
		foreach ($paymentModes as $pm) {
			$insertedPmId = $this->localProjectService->createPaymentMode($projectid, $pm['name'], $pm['icon'], $pm['color']);
			$paymentModeIdConv[$pm['id']] = $insertedPmId;
		}
		// add categories
		foreach ($categories as $cat) {
			$insertedCatId = $this->localProjectService->createCategory($projectid, $cat['name'], $cat['icon'], $cat['color']);
			$categoryIdConv[$cat['id']] = $insertedCatId;
		}
		// add currencies
		foreach ($currencies as $cur) {
			$insertedCurId = $this->localProjectService->createCurrency($projectid, $cur['name'], $cur['exchange_rate']);
		}
		// add members
		foreach ($membersByName as $memberName => $member) {
			try {
				$insertedMember = $this->localProjectService->createMember(
					$projectid, $memberName, $member['weight'], $member['active'], $member['color'] ?? null
				);
			} catch (\Throwable $e) {
				$this->localProjectService->deleteProject($projectid);
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
					$projectid, null, $bill['what'], $payerId,
					$owerIdsStr, $bill['amount'], $bill['repeat'],
					$bill['paymentmode'], $pmId,
					$catId, $bill['repeatallactive'],
					$bill['repeatuntil'], $bill['timestamp'], $bill['comment'], $bill['repeatfreq'],
					$bill['deleted'] ?? 0
				);
			} catch (\Throwable $e) {
				$this->localProjectService->deleteProject($projectid);
				return ['message' => $this->l10n->t('Error when adding bill %1$s', [$bill['what']])];
			}
		}
		return ['project_id' => $projectid];
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
					$projectid = Utils::slugify($projectName);
					// create default categories only if none are found in the CSV
					$createDefaultCategories = (count($categoryNames) === 0);
					$projResult = $this->localProjectService->createProject(
						$projectName, $projectid, $userEmail,
						$userId, $createDefaultCategories
					);
					if (!isset($projResult['id'])) {
						return ['message' => $this->l10n->t('Error in project creation, %1$s', [$projResult['message'] ?? ''])];
					}
					// add categories
					$catNameToId = [];
					foreach ($categoryNames as $categoryName) {
						$insertedCatId = $this->localProjectService->createCategory($projectid, $categoryName, null, '#000000');
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
							$insertedMember = $this->localProjectService->createMember($projectid, $memberName, $weight);
						} catch (\Throwable $e) {
							$this->localProjectService->deleteProject($projectid);
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
								$projectid, null, $bill['what'], $payerId, $owerIdsStr,
								$bill['amount'], Application::FREQUENCY_NO, null, 0, $catId,
								0, null, $bill['timestamp'], null, null
							);
						} catch (\Throwable $e) {
							$this->localProjectService->deleteProject($projectid);
							return ['message' => $this->l10n->t('Error when adding bill %1$s', [$bill['what']])];
						}
					}
					return ['project_id' => $projectid];
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

		$qb = $this->db->getQueryBuilder();

		foreach ($this->userManager->search('') as $u) {
			$uid = $u->getUID();
			$outPath = $this->config->getUserValue($uid, 'cospend', 'outputDirectory', '/Cospend');

			$qb->select('id', 'name', 'auto_export')
				->from('cospend_projects')
				->where(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->neq('auto_export', $qb->createNamedParameter(Application::FREQUENCY_NO, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->executeQuery();

			$dbProjectId = null;
			while ($row = $req->fetch()) {
				$dbProjectId = $row['id'];
				$autoExport = $row['auto_export'];

				$suffix = $dailySuffix;
				// TODO add suffix for all frequencies
				if ($autoExport === Application::FREQUENCY_WEEKLY) {
					$suffix = $weeklySuffix;
				} elseif ($autoExport === Application::FREQUENCY_MONTHLY) {
					$suffix = $monthlySuffix;
				}
				// check if file already exists
				$exportName = $dbProjectId . $suffix . '.csv';

				$userFolder = $this->root->getUserFolder($uid);
				if (!$userFolder->nodeExists($outPath . '/' . $exportName)) {
					$projectInfo = $this->localProjectService->getProjectInfoWithAccessLevel($dbProjectId, $uid);
					$bills = $this->localProjectService->getBills($dbProjectId);
					$this->exportCsvProject($dbProjectId, $uid, $projectInfo, $bills['bills'] ?? [], $exportName);
				}
			}
			$req->closeCursor();
			$qb = $this->db->getQueryBuilder();
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
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
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
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
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
		$outPath = $this->config->getUserValue($userId, 'cospend', 'outputDirectory', '/Cospend');
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
			yield '"' . $bill['what'] . '",'
				. strval((float)$bill['amount']) . ','
				. $oldDateStr . ','
				. $bill['timestamp'] . ',"'
				. $payer_name . '",'
				. strval((float)$payer_weight) . ','
				. $payer_active . ',"'
				. $owersTxt . '",'
				. $bill['repeat'] . ','
				. $bill['repeatfreq'] . ','
				. $bill['repeatallactive'] . ','
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
					(int)$id . ',"' .
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
					(int)$id . ',"' .
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
					. '",' . strval((float)$cur['exchange_rate'])
					. "\n";
			}
		}

		return [];
	}

	/**
     * Get cross-project balances aggregated by person and currency across all projects
     * 
     * This method implements GitHub issue #281 - Cross-project balances feature.
     * It calculates and aggregates debts/credits between the current user and all other
     * members across all projects they participate in, grouped by currency.
     * 
     * The calculation logic:
     * 1. Iterates through all non-archived projects where user is a member
     * 2. For each project, gets the current balance state using existing settlement logic
     * 3. Aggregates balances by person and currency (using userid if available, or name as fallback)
     * 4. Returns currency-grouped summary totals and per-person breakdowns with project details
     * 
     * Balance interpretation (from current user's perspective):
     * - Positive balance = current user owes money to that person
     * - Negative balance = that person owes money to current user
     * 
     * This matches the existing settlement view's calculation logic for consistency.
     * 
     * @param string $userId The current user's Nextcloud user ID
     * @return array Contains:
     *   - currencyTotals: Array keyed by currency with totalOwed, totalOwedTo, netBalance for each
     *   - personBalances: Array of per-person balance details with currency breakdowns
     *   - summary: Human-readable summary arrays for display grouped by currency
     * 
     * @since 1.6.0 Added for cross-project balance aggregation feature
	 * Get cross-project balances aggregated by person and currency across all projects
	 *
	 * This method implements GitHub issue #281 - Cross-project balances feature.
	 * It calculates and aggregates debts/credits between the current user and all other
	 * members across all projects they participate in, grouped by currency.
	 *
	 * The calculation logic:
	 * 1. Iterates through all non-archived projects where user is a member
	 * 2. For each project, gets the current balance state using existing settlement logic
	 * 3. Aggregates balances by person and currency (using userid if available, or name as fallback)
	 * 4. Returns currency-grouped summary totals and per-person breakdowns with project details
	 *
	 * Balance interpretation (from current user's perspective):
	 * - Positive balance = current user owes money to that person
	 * - Negative balance = that person owes money to current user
	 *
	 * This matches the existing settlement view's calculation logic for consistency.
	 *
	 * @param string $userId The current user's Nextcloud user ID
	 * @return array Contains:
	 *   - currencyTotals: Array keyed by currency with totalOwed, totalOwedTo, netBalance for each
	 *   - personBalances: Array of per-person balance details with currency breakdowns
	 *   - summary: Human-readable summary arrays for display grouped by currency
	 *
	 * @since 1.6.0 Added for cross-project balance aggregation feature
	 */
    public function getCrossGroupBalances(string $userId): array {
        $projects = $this->localProjectService->getLocalProjects($userId);
        $currencyTotals = [];
        $personBalances = [];
        
        // Get current user info for filtering projects
        $currentUserId = $userId;
        
        // If no projects, return empty data structure
        if (empty($projects)) {
            return [
                'currencyTotals' => [],
                'personBalances' => [],
                'summary' => []
            ];
        }
        
        foreach ($projects as $project) {
            $projectId = $project['id'];
            $projectName = $project['name'];
            $projectCurrency = $project['currencyname'] ?? 'EUR'; // Default to EUR if not set
            
            // Skip archived projects as they don't contribute to active balances
            if ($project['archived_ts'] !== null) {
                continue;
            }
            
            // Initialize currency totals if needed
            if (!isset($currencyTotals[$projectCurrency])) {
                $currencyTotals[$projectCurrency] = [
                    'currency' => $projectCurrency,
                    'totalOwed' => 0,
                    'totalOwedTo' => 0,
                    'netBalance' => 0
                ];
            }
            
            // Get current balance state for this project using existing balance calculation
            $balances = $this->localProjectService->getProjectBalance($projectId);
            $members = $project['members'] ?? [];
            
            // Find current user's member ID and balance in this project
            $currentUserMemberId = null;
            $currentUserBalance = 0.0;
            
            foreach ($members as $member) {
                if (isset($member['userid']) && $member['userid'] === $currentUserId) {
                    $currentUserMemberId = (int)$member['id'];
                    $currentUserBalance = $balances[$member['id']] ?? 0.0;
                    break;
                }
            }
            
            // If current user is not a member of this project, skip it
            if ($currentUserMemberId === null) {
                continue;
            }
            
            // Calculate cross-member debts for this project and add to aggregated totals
            $this->calculateProjectDebtsWithCurrency($projectId, $currentUserMemberId, $members, $balances, $projectName, $projectCurrency, $personBalances, $currencyTotals);
        }
        
        // Calculate net balances for each currency
        foreach ($currencyTotals as $currency => &$totals) {
            $totals['netBalance'] = $totals['totalOwedTo'] - $totals['totalOwed'];
        }
        unset($totals); // Break reference
        
        // Create summary arrays grouped by currency
        $summary = [];
        
        foreach ($currencyTotals as $currency => $totals) {
            $summary[$currency] = [
                'currency' => $currency,
                'owed' => [],
                'owedTo' => []
            ];
        }
        
        foreach ($personBalances as $personBalance) {
            $member = $personBalance['member'];
            
            foreach ($personBalance['currencyBalances'] as $currency => $currencyBalance) {
                $balance = $currencyBalance['totalBalance'];
                
                if ($balance > 0.01) {
                    $summary[$currency]['owed'][] = [
                        'member' => $member,
                        'amount' => $balance
                    ];
                } elseif ($balance < -0.01) {
                    $summary[$currency]['owedTo'][] = [
                        'member' => $member,
                        'amount' => abs($balance)
                    ];
                }
            }
        }
        
        return [
            'currencyTotals' => array_values($currencyTotals),
            'personBalances' => array_values($personBalances),
            'summary' => $summary
        ];
    }
    
    /**
     * Calculate what the current user owes to/is owed by each member in a specific project with currency support
     * 
     * This method processes each project's balance state and aggregates the relationships
     * between the current user and other members, grouping by currency. It uses the existing 
     * balance calculation logic to ensure consistency with the settlement view.
     * 
     * @param string $projectId Project identifier
     * @param int $currentUserMemberId Current user's member ID in this project  
     * @param array $members All project members
     * @param array $balances Member balances from getProjectBalance()
     * @param string $projectName Project name for display
     * @param string $projectCurrency Project's main currency
     * @param array &$personBalances Reference to aggregated balance array (modified in place)
     * @param array &$currencyTotals Reference to currency totals array (modified in place)
     * 
     * @since 1.6.0 Added for cross-project balance aggregation feature
     */
    private function calculateProjectDebtsWithCurrency(string $projectId, int $currentUserMemberId, array $members, array $balances, string $projectName, string $projectCurrency, array &$personBalances, array &$currencyTotals): void {
        // Process each member to determine relationship with current user
        foreach ($members as $member) {
            if ($member['id'] === $currentUserMemberId) {
                continue; // Skip current user (no debt to self)
            }
            
            if (!($member['activated'] ?? true)) {
                continue; // Skip inactive/deactivated members
            }
            
            $memberBalance = $balances[$member['id']] ?? 0.0;
            $personIdentifier = $this->getPersonIdentifier($member);
            
            // Initialize person data if not seen before across projects
            if (!isset($personBalances[$personIdentifier])) {
                $personBalances[$personIdentifier] = [
                    'member' => [
                        'name' => $member['name'] ?? 'Unknown',
                        'userid' => $member['userid'] ?? null,
                        'id' => $member['id']
                    ],
                    'currencyBalances' => [],
                    'projects' => []
                ];
            }
            
            // Initialize currency balance for this person if needed
            if (!isset($personBalances[$personIdentifier]['currencyBalances'][$projectCurrency])) {
                $personBalances[$personIdentifier]['currencyBalances'][$projectCurrency] = [
                    'currency' => $projectCurrency,
                    'totalBalance' => 0,
                    'projects' => []
                ];
            }
            
            $relationshipBalance = 0.0;
            
            if (abs($memberBalance) > 0.01) {  // Only process significant balances (ignore rounding errors)
                // The member's balance directly represents our relationship:
                // - Positive: we owe them money  
                // - Negative: they owe us money
                $relationshipBalance = $memberBalance;
                
                // Add this project's contribution to the person's currency balance
                $personBalances[$personIdentifier]['currencyBalances'][$projectCurrency]['totalBalance'] += $relationshipBalance;
                $personBalances[$personIdentifier]['currencyBalances'][$projectCurrency]['projects'][] = [
                    'projectId' => $projectId,
                    'projectName' => $projectName,
                    'balance' => $relationshipBalance
                ];
                
                // Also add to main projects array for backward compatibility
                $personBalances[$personIdentifier]['projects'][] = [
                    'projectId' => $projectId,
                    'projectName' => $projectName,
                    'currency' => $projectCurrency,
                    'balance' => $relationshipBalance
                ];
                
                // Update currency totals
                if ($relationshipBalance > 0) {
                    $currencyTotals[$projectCurrency]['totalOwed'] += $relationshipBalance;
                } else {
                    $currencyTotals[$projectCurrency]['totalOwedTo'] += abs($relationshipBalance);
                }
            }
        }
    }
    
    /**
     * Get unique identifier for a person across projects
     * 
     * This method creates consistent identifiers for the same person across multiple
     * projects to enable proper aggregation. It prioritizes Nextcloud user ID when
     * available (for registered users) and falls back to normalized name matching.
     * 
     * Priority order:
     * 1. userid (if the person is a Nextcloud user) - most reliable
     * 2. name (case-insensitive, trimmed) - fallback for guest users
     * 
     * @param array $member Member data containing userid and/or name
     * @return string Unique identifier for cross-project aggregation
     * 
     * @since 1.6.0 Added for cross-project balance aggregation feature
     */
    private function getPersonIdentifier(array $member): string {
        if (!empty($member['userid'])) {
            return 'user:' . $member['userid'];
        }
        return 'name:' . strtolower(trim($member['name'] ?? ''));
    }
    
}
