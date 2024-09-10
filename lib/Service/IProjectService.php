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

use OCA\Cospend\ResponseDefinitions;

/**
 * @psalm-import-type CospendProjectInfoPlusExtra from ResponseDefinitions
 * @psalm-import-type CospendMember from ResponseDefinitions
 */
interface IProjectService {

	/**
	 * Delete a project and all associated data
	 *
	 * @param string $projectId
	 * @return void
	 */
	public function deleteProject(string $projectId): void;

	/**
	 * @param string $projectId
	 * @param string $userId
	 * @return array
	 */
	public function getProjectInfoWithAccessLevel(string $projectId, string $userId): array;

	/**
	 * Get project statistics
	 *
	 * @param string $projectId
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
	 */
	public function getStatistics(
		string $projectId, ?int $tsMin = null, ?int $tsMax = null,
		?int $paymentModeId = null, ?int $categoryId = null, ?float $amountMin = null, ?float $amountMax = null,
		bool $showDisabled = true, ?int $currencyId = null, ?int $payerId = null
	): array;

	/**
	 * Generate bills to automatically settle a project
	 *
	 * @param string $projectId
	 * @param int|null $centeredOn
	 * @param int $precision
	 * @param int|null $maxTimestamp
	 * @return void
	 */
	public function autoSettlement(string $projectId, ?int $centeredOn = null, int $precision = 2, ?int $maxTimestamp = null): void;

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
	 * @param string|null $autoExport
	 * @param string|null $currencyName
	 * @param bool|null $deletionDisabled
	 * @param string|null $categorySort
	 * @param string|null $paymentModeSort
	 * @param int|null $archivedTs
	 * @return void
	 */
	public function editProject(
		string  $projectId, ?string $name = null, ?string $contact_email = null,
		?string $autoExport = null, ?string $currencyName = null, ?bool $deletionDisabled = null,
		?string $categorySort = null, ?string $paymentModeSort = null, ?int $archivedTs = null
	): void;

	/**
	 * Add a member to a project
	 *
	 * @param string $projectId
	 * @param string $name
	 * @param float|null $weight
	 * @param bool $active
	 * @param string|null $color
	 * @param string|null $userId
	 * @return CospendMember
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
	 * @return void
	 */
	public function deleteMember(string $projectId, int $memberId): void;

	/**
	 * Get bills of a project
	 *
	 * @param string $projectId
	 * @param int|null $lastChanged
	 * @param int|null $offset
	 * @param int|null $limit
	 * @param bool $reverse
	 * @param int|null $payerId
	 * @param int|null $categoryId
	 * @param int|null $paymentModeId
	 * @param int|null $includeBillId
	 * @param string|null $searchTerm
	 * @param int|null $deleted
	 * @return array
	 */
	public function getBills(
		string $projectId, ?int $lastChanged = null, ?int $offset = 0, ?int $limit = null, bool $reverse = false,
		?int $payerId = null, ?int $categoryId = null, ?int $paymentModeId = null, ?int $includeBillId = null,
		?string $searchTerm = null, ?int $deleted = 0
	): array;

	/**
	 * @param string $projectId
	 * @param int $billId
	 * @return array
	 */
	public function getBill(string $projectId, int $billId): array;

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
	 * @param int $deleted
	 * @param bool $produceActivity
	 * @return int
	 */
	public function createBill(
		string $projectId, ?string $date, ?string $what, ?int $payer, ?string $payedFor,
		?float $amount, ?string $repeat, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, int $repeatAllActive = 0, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null,
		int $deleted = 0, bool $produceActivity = false
	): int;

	/**
	 * Delete a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param bool $force Ignores any deletion protection and forces the deletion of the bill
	 * @param bool $moveToTrash
	 * @param bool $produceActivity
	 * @return void
	 */
	public function deleteBill(
		string $projectId, int $billId, bool $force = false, bool $moveToTrash = true, bool $produceActivity = false
	): void;

	/**
	 * @param string $projectId
	 * @param array $billIds
	 * @param bool $moveToTrash
	 * @return void
	 */
	public function deleteBills(string $projectId, array $billIds, bool $moveToTrash = true): void;

	/**
	 * Edit a bill
	 *
	 * @param string $projectId
	 * @param int $billId
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param int|null $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @param int|null $deleted
	 * @param bool $produceActivity
	 * @return void
	 */
	public function editBill(
		string $projectId, int $billId, ?string $date, ?string $what, ?int $payer, ?string $payedFor,
		?float $amount, ?string $repeat, ?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null, ?int $repeatAllActive = null, ?string $repeatUntil = null,
		?int $timestamp = null, ?string $comment = null, ?int $repeatFreq = null,
		?int $deleted = null, bool $produceActivity = false
	): void;

	/**
	 * @param string $projectId
	 * @param array $billIds
	 * @param string|null $date
	 * @param string|null $what
	 * @param int|null $payer
	 * @param string|null $payedFor
	 * @param float|null $amount
	 * @param string|null $repeat
	 * @param string|null $paymentMode
	 * @param int|null $paymentModeId
	 * @param int|null $categoryId
	 * @param int|null $repeatAllActive
	 * @param string|null $repeatUntil
	 * @param int|null $timestamp
	 * @param string|null $comment
	 * @param int|null $repeatFreq
	 * @param int|null $deleted
	 * @param bool $produceActivity
	 * @return void
	 */
	public function editBills(
		string $projectId, array $billIds, ?string $date = null, ?string $what = null,
		?int $payer = null, ?string $payedFor = null,
		?float $amount = null, ?string $repeat = null,
		?string $paymentMode = null, ?int $paymentModeId = null,
		?int $categoryId = null,
		?int $repeatAllActive = null, ?string $repeatUntil = null, ?int $timestamp = null,
		?string $comment = null, ?int $repeatFreq = null, ?int $deleted = null, bool $produceActivity = false
	): void;

	/**
	 * @param string $projectId
	 * @param int $billId
	 * @return array
	 */
	public function repeatBill(string $projectId, int $billId): array;

	/**
	 * @param string $projectId
	 * @return void
	 */
	public function clearTrashBin(string $projectId): void;

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
	 * @return void
	 */
	public function deletePaymentMode(string $projectId, int $pmId): void;

	/**
	 * @param string $projectId
	 * @param array $order
	 * @return void
	 */
	public function savePaymentModeOrder(string $projectId, array $order): void;

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
	 * @return void
	 */
	public function deleteCategory(string $projectId, int $categoryId): void;

	/**
	 * Save the manual category order
	 *
	 * @param string $projectId
	 * @param array $order
	 * @return void
	 */
	public function saveCategoryOrder(string $projectId, array $order): void;

	/**
	 * Edit a category
	 *
	 * @param string $projectId
	 * @param int $categoryId
	 * @param string|null $name
	 * @param string|null $icon
	 * @param string|null $color
	 * @return array
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
	 */
	public function createCurrency(string $projectId, string $name, float $rate): int;

	/**
	 * Delete one currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @return void
	 */
	public function deleteCurrency(string $projectId, int $currencyId): void;

	/**
	 * Edit a currency
	 *
	 * @param string $projectId
	 * @param int $currencyId
	 * @param string $name
	 * @param float $rate
	 * @return array
	 */
	public function editCurrency(string $projectId, int $currencyId, string $name, float $rate): array;
}
