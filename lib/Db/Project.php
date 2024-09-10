<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getId()
 * @method void setId(string $id)
 * @method string getUserId()
 * @method void setUserid(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method string getAutoExport()
 * @method void setAutoExport(string $autoExport)
 * @method int getLastChanged()
 * @method void setLastChanged(int $lastChanged)
 * @method int getDeletionDisabled()
 * @method void setDeletionDisabled(int $deletionDisabled)
 * @method string getCategorySort()
 * @method void setCategorySort(string $categorySort)
 * @method string getPaymentModeSort()
 * @method void setPaymentModeSort(string $paymentModeSort)
 * @method string getCurrencyName()
 * @method void setCurrencyName(string $currencyName)
 * @method int getArchivedTs()
 * @method void setArchivedTs(int $archivedTs)
 */
class Project extends Entity implements \JsonSerializable {

	protected ?string $userId = null;
	protected string $name = '';
	protected string $email = '';
	protected string $autoExport = 'n';
	protected int $lastChanged = 0;
	protected int $deletionDisabled = 0;
	protected string $categorySort = 'a';
	protected string $paymentModeSort = 'a';
	protected ?string $currencyName = null;
	protected ?int $archivedTs = null;

	public function __construct() {
		$this->addType('id', 'string');
		$this->addType('user_id', 'string');
		$this->addType('name', 'string');
		$this->addType('email', 'string');
		$this->addType('auto_export', 'string');
		$this->addType('last_changed', 'integer');
		$this->addType('deletion_disabled', 'integer');
		$this->addType('category_sort', 'string');
		$this->addType('payment_mode_sort', 'string');
		$this->addType('currency_name', 'string');
		$this->addType('archived_ts', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'userid' => $this->userId,
			'name' => $this->name,
			'email' => $this->email,
			'autoexport' => $this->autoExport,
			'lastchanged' => $this->lastChanged,
			'deletiondisabled' => $this->deletionDisabled === 1,
			'categorysort' => $this->categorySort,
			'paymentmodesort' => $this->paymentModeSort,
			'currencyname' => $this->currencyName,
			'archived_ts' => $this->archivedTs,
		];
	}
}
