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

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \string getWhat()
 * @method \void setWhat(string $what)
 * @method \string|\null getComment()
 * @method \void setComment(string|null $comment)
 * @method \int getPayerId()
 * @method \void setPayerId(int $payerId)
 * @method \float getAmount()
 * @method \void setAmount(float $amount)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 * @method \string getRepeat()
 * @method \void setRepeat(string $repeat)
 * @method \int getRepeatAllActive()
 * @method \void setRepeatAllActive(int $repeatAllActive)
 * @method \string|\null getRepeatUntil()
 * @method \void setRepeatUntil(string|null $repeatUntil)
 * @method \int getRepeatFrequency()
 * @method \void setRepeatFrequency(int $repeatFrequency)
 * @method \string getProjectId()
 * @method \void setProjectId(string $projectId)
 * @method \int|\null getCategoryId()
 * @method \void setCategoryId(int|null $categoryId)
 * @method \string|\null getPaymentMode()
 * @method \void setPaymentMode(string|null $paymentMode)
 * @method \int|\null getPaymentModeId()
 * @method \void setPaymentModeId(int|null $paymentModeId)
 * @method \int getLastChanged()
 * @method \void setLastChanged(int $lastChanged)
 * @method \int getDeleted()
 * @method \void setDeleted(int $deleted) */
class Bill extends Entity implements \JsonSerializable {

	protected $what;
	protected $comment;
	protected $payerId;
	protected $amount;
	protected $timestamp;
	protected $repeat;
	protected $repeatAllActive;
	protected $repeatUntil;
	protected $repeatFrequency;
	protected $projectId;
	protected $categoryId;
	protected $paymentMode;
	protected $paymentModeId;
	protected $lastChanged;
	protected $deleted;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('what', Types::STRING);
		$this->addType('comment', Types::STRING);
		$this->addType('payerId', Types::INTEGER);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('amount', Types::FLOAT);
		$this->addType('repeat', Types::STRING);
		$this->addType('repeatAllActive', Types::INTEGER);
		$this->addType('repeatUntil', Types::STRING);
		$this->addType('repeatFrequency', Types::INTEGER);
		$this->addType('projectId', Types::STRING);
		$this->addType('categoryId', Types::INTEGER);
		$this->addType('paymentMode', Types::STRING);
		$this->addType('paymentModeId', Types::INTEGER);
		$this->addType('lastChanged', Types::INTEGER);
		$this->addType('deleted', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'projectid' => $this->getProjectId(),
			'what' => $this->getWhat(),
			'comment' => $this->getComment() ?? '',
			'payer_id' => $this->getPayerId(),
			'timestamp' => $this->getTimestamp(),
			// TODO replace all DateTime::createFromFormat('U' by @blabla
			'date' => (new DateTime('@' . $this->getTimestamp()))->format('Y-m-d'),
			'amount' => $this->getAmount(),
			'repeat' => $this->getRepeat(),
			'repeatallactive' => $this->getRepeatAllActive(),
			'repeatuntil' => $this->getRepeatUntil(),
			'repeatfreq' => $this->getRepeatFrequency(),
			'categoryid' => $this->getCategoryId(),
			'paymentmode' => $this->getPaymentMode(),
			'paymentmodeid' => $this->getPaymentModeId(),
			'deleted' => $this->getDeleted(),
			'lastchanged' => $this->getLastChanged(),
		];
	}
}
