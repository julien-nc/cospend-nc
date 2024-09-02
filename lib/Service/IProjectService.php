<?php

/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2024
 */

namespace OCA\Cospend\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Generator;
use OC\User\NoUserException;
use OCA\Cospend\Activity\ActivityManager;
use OCA\Cospend\AppInfo\Application;
use OCA\Cospend\Db\Bill;
use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\Member;
use OCA\Cospend\Db\ProjectMapper;
use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Utils;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Throwable;
use function str_replace;

/**
 * @psalm-import-type CospendProjectInfoPlusExtra from ResponseDefinitions
 * @psalm-import-type CospendMember from ResponseDefinitions
 */
interface IProjectService {

	/**
	 * Create a project
	 *
	 * @param string $name
	 * @param string $id
	 * @param string|null $contact_email
	 * @param string $userId
	 * @param bool $createDefaultCategories
	 * @param bool $createDefaultPaymentModes
	 * @return array
	 */
	public function createProject(
		string $name, string $id, ?string $contact_email, string $userId = '',
		bool $createDefaultCategories = true, bool $createDefaultPaymentModes = true
	): array;

	/**
	 * Delete a project and all associated data
	 *
	 * @param string $projectId
	 * @return array
	 */
	public function deleteProject(string $projectId): array;

	/**
	 * @param string $projectId
	 * @param string $userId
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectInfoWithAccessLevel(string $projectId, string $userId): ?array;

	/**
	 * Get project statistics
	 *
	 * @param string $projectId
	 * @param string|null $memberOrder
	 * @param int|null $tsMin
	 * @param int|null $tsMax
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param float|null $amountMin
	 * @param float|null $amountMax
	 * @param bool $showDisabled
	 * @param int|null $currencyId
	 * @param int|null $payerId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getProjectStatistics(
		string $projectId, ?string $memberOrder = null, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		bool $showDisabled = true, ?int $currencyId = null, ?int $payerId = null
	): array;

	/**
	 * Add a bill in a given project
	 *
	 * @param string $projectId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param int $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @param array|null $paymentModes
	 * @param int $deleted
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function createBill(
		string $projectId, ?string $date, ?string $what, ?int $payer, ?string $payedFor,
		?float $amount, ?string $repeat, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null,
		?array $paymentModes = null, int $deleted = 0
	): array;

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param bool $force Ignores any deletion protection and forces the deletion of the bill
	 * @param bool $moveToTrash
	 * @return array
	 */
	public function deleteBill(string $projectId, int $billId, bool $force = false, bool $moveToTrash = true): array;

	/**
	 * Generate bills to automatically settle a project
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): array;

	/**
	 * Get project settlement plan
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int|null $maxTimestamp
	 * @return array
	 */
	public function getProjectSettlement(string $projectId, ?int $centeredOn = null, ?int $maxTimestamp = null): array;

	/**
	 * Edit a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @param string|null $name
	 * @param string|null $userId
	 * @param float|null $weight
	 * @param bool $activated
	 * @param string|null $color
	 * @return array|null
	 */
	public function editMember(
		string $projectId, int $memberId, ?string $name = null, ?string $userId = null,
		?float $weight = null, ?bool $activated = null, ?string $color = null
	): ?array;


	/**
	 * Edit a project
	 *
	 * @param string $projectId
	 * @param string|null $name
	 * @param string|null $contact_email
	 * @param string|null $autoexport
	 * @param string|null $currencyname
	 * @param bool|null $deletion_disabled
	 * @param string|null $categorysort
	 * @param string|null $paymentmodesort
	 * @param int|null $archivedTs
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editProject(
		string  $projectId, ?string $name = null, ?string $contact_email = null,
		?string $autoexport = null, ?string $currencyname = null, ?bool $deletion_disabled = null,
		?string $categorysort = null, ?string $paymentmodesort = null, ?int $archivedTs = null
	): array;

	/**
	 * Add a member to a project
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float|null $weight
	 * @param bool $active
	 * @param string|null $color
	 * @param string|null $userId
	 * @return array{error: string}|CospendMember
	 * @throws \OCP\DB\Exception
	 */
	public function createMember(
		string $projectId, string $name, ?float $weight = 1.0, bool $active = true,
		?string $color = null, ?string $userId = null
	): array;

	/**
	 * Get members of a project
	 *
	 * @param string $projectId
	 * @param string|null $order
	 * @param int|null $lastchanged
	 * @return array
	 */
	public function getMembers(string $projectId, ?string $order = null, ?int $lastchanged = null): array;

	/**
	 * Delete a member
	 *
	 * @param string $projectId
	 * @param int $memberId
	 * @return array
	 */
	public function deleteMember(string $projectId, int $memberId): array;

	/**
	 * Edit a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payed_for
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentmode
	 * @param int|null $paymentmodeid
	 * @param int|null $categoryid
	 * @param int|null $repeatallactive
	 * @param string|null $repeatuntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatfreq
	 * @param int|null $deleted
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editBill(
		string $projectId, int $billId, ?string $date, ?string $what, ?int $payer, ?string $payed_for,
		?float $amount, ?string $repeat, ?string $paymentmode = null, ?int $paymentmodeid = null,
		?int $categoryid = null, ?int $repeatallactive = null, ?string $repeatuntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatfreq = null,
		?int $deleted = null
	): array;

	/**
	 * @param string $projectId
	 * @param int $billId
	 * @param string $toProjectId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function moveBill(string $projectId, int $billId, string $toProjectId): array;

	/**
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return int
	 */
	public function createPaymentMode(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int;

	/**
	 * @param string $projectId
	 * @param int $pmId
	 * @return array|true[]
	 * @throws \OCP\DB\Exception
	 */
	public function deletePaymentMode(string $projectId, int $pmId): array;

	/**
	 * @param string $projectId
	 * @param array $order
	 * @return bool
	 */
	public function savePaymentModeOrder(string $projectId, array $order): bool;

	/**
	 * @param string $projectId
	 * @param int $pmId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return array
	 */
	public function editPaymentMode(
		string $projectId, int $pmId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array;

	/**
	 * Add a new category
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param string|null $icon
	 * @param string $color
	 * @param int|null $order
	 * @return int
	 */
	public function createCategory(string $projectId, string $name, ?string $icon, string $color, ?int $order = 0): int;

	/**
	 * Delete a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteCategory(string $projectId, int $categoryId): array;

	/**
	 * Save the manual category order
	 *
	 * @param string $projectId
	 * @param array $order
	 * @return bool
	 * @throws \OCP\DB\Exception
	 */
	public function saveCategoryOrder(string $projectId, array $order): bool;

	/**
	 * Edit a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editCategory(
		string $projectId, int $categoryId, ?string $name = null, ?string $icon = null, ?string $color = null
	): array;

	/**
	 * Add a currency
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float $rate
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function createCurrency(string $projectId, string $name, float $rate): int;

	/**
	 * Delete one currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function deleteCurrency(string $projectId, int $currencyId): array;

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @param string $name
	 * @param float $exchange_rate
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function editCurrency(string $projectId, int $currencyId, string $name, float $exchange_rate): array;
}
