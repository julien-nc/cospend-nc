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

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getBillId()
 * @method void setBillId(int $billId)
 * @method int getMemberId()
 * @method void setMemberId(int $memberId)
 **/
class BillOwer extends Entity implements \JsonSerializable {

	protected $billId;
	protected $memberId;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('bill_id', 'integer');
		$this->addType('member_id', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'billid' => $this->getBillId(),
			'memberid' => $this->getMemberId(),
		];
	}
}
