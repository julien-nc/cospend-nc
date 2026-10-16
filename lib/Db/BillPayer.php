<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * One payer's contribution to a bill.
 *
 * Rows exist only for bills with more than one payer; a single-payer bill is
 * described entirely by `cospend_bills.payer_id` and has no rows here. Unlike
 * BillOwer, this relation carries a payload: the absolute amount that member put
 * in. When rows exist they are authoritative and must sum to the bill amount.
 *
 * @method \int getBillId()
 * @method \void setBillId(int $billId)
 * @method \int getMemberId()
 * @method \void setMemberId(int $memberId)
 * @method \float getAmount()
 * @method \void setAmount(float $amount)
 **/
class BillPayer extends Entity implements \JsonSerializable {

	protected $billId;
	protected $memberId;
	protected $amount;

	public function __construct() {
		$this->addType('billId', Types::INTEGER);
		$this->addType('memberId', Types::INTEGER);
		$this->addType('amount', Types::FLOAT);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'billid' => $this->getBillId(),
			'memberid' => $this->getMemberId(),
			'amount' => $this->getAmount(),
		];
	}
}
