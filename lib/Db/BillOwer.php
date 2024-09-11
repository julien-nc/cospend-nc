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
 * @method int getBillid()
 * @method void setBillid(int $billid)
 * @method int getMemberid()
 * @method void setMemberid(int $memberid)
 **/
class BillOwer extends Entity implements \JsonSerializable {

	protected int $billid = 0;
	protected int $memberid = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('billid', 'integer');
		$this->addType('memberid', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'billid' => $this->getBillid(),
			'memberid' => $this->getMemberid(),
		];
	}
}
