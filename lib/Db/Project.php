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
use OCP\DB\Types;

/**
 * @method \string getId()
 * @method \void setId(\string $id)
 * @method \string|\null getUserId()
 * @method void setUserId(\string|\null $userId)
 * @method \string getName()
 * @method \void setName(\string $name)
 * @method \string getAutoExport()
 * @method \void setAutoExport(\string $autoExport)
 * @method \int getLastChanged()
 * @method \void setLastChanged(\int $lastChanged)
 * @method \int getDeletionDisabled()
 * @method \void setDeletionDisabled(\int $deletionDisabled)
 * @method \string getCategorySort()
 * @method \void setCategorySort(\string $categorySort)
 * @method \string getPaymentModeSort()
 * @method \void setPaymentModeSort(\string $paymentModeSort)
 * @method \string|\null getCurrencyName()
 * @method \void setCurrencyName(\string|\null $currencyName)
 * @method \int|\null getArchivedTs()
 * @method \void setArchivedTs(\int|\null $archivedTs)
 */
class Project extends Entity implements \JsonSerializable {

	protected $userId;
	protected $name;
	protected $autoExport;
	protected $lastChanged;
	protected $deletionDisabled;
	protected $categorySort;
	protected $paymentModeSort;
	protected $currencyName;
	protected $archivedTs;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('userId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('autoExport', Types::STRING);
		$this->addType('lastChanged', Types::INTEGER);
		$this->addType('deletionDisabled', Types::INTEGER);
		$this->addType('categorySort', Types::STRING);
		$this->addType('paymentModeSort', Types::STRING);
		$this->addType('currencyName', Types::STRING);
		$this->addType('archivedTs', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'userid' => $this->getUserId(),
			'name' => $this->getName(),
			'email' => '',
			'autoexport' => $this->getAutoExport(),
			'lastchanged' => $this->getLastChanged(),
			'deletiondisabled' => $this->getDeletionDisabled() === 1,
			'categorysort' => $this->getCategorySort(),
			'paymentmodesort' => $this->getPaymentModeSort(),
			'currencyname' => $this->getCurrencyName(),
			'archived_ts' => $this->getArchivedTs(),
		];
	}
}
