<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getBillId()
 * @method \void setBillId(int $billId)
 * @method \int getMemberId()
 * @method \void setMemberId(int $memberId)
 **/
class BillOwer extends Entity implements \JsonSerializable {

	protected $billId;
	protected $memberId;

	public function __construct() {
		$this->addType('billId', Types::INTEGER);
		$this->addType('memberId', Types::INTEGER);
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
