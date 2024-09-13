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

/**
 * @method string getWhat()
 * @method void setWhat(string $what)
 * @method string|null getComment()
 * @method void setComment(string|null $comment)
 * @method int getPayerId()
 * @method void setPayerId(int $payerId)
 * @method float getAmount()
 * @method void setAmount(float $amount)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method string getRepeat()
 * @method void setRepeat(string $repeat)
 * @method int getRepeatAllActive()
 * @method void setRepeatAllActive(int $repeatAllActive)
 * @method string|null getRepeatUntil()
 * @method void setRepeatUntil(string|null $repeatUntil)
 * @method int getRepeatFrequency()
 * @method void setRepeatFrequency(int $repeatFrequency)
 * @method string getProjectId()
 * @method void setProjectId(string $projectId)
 * @method int|null getCategoryId()
 * @method void setCategoryId(int|null $categoryId)
 * @method string|null getPaymentMode()
 * @method void setPaymentMode(string|null $paymentMode)
 * @method int|null getPaymentModeId()
 * @method void setPaymentModeId(int|null $paymentModeId)
 * @method int getLastChanged()
 * @method void setLastChanged(int $lastChanged)
 * @method int getDeleted()
 * @method void setDeleted(int $deleted) */
class Bill extends Entity implements \JsonSerializable {

	protected string $what = '';
	protected ?string $comment = null;
	protected int $payerId = 0;
	protected float $amount = 0;
	protected int $timestamp = 0;
	protected string $repeat = 'n';
	protected int $repeatAllActive = 0;
	protected ?string $repeatUntil = null;
	protected int $repeatFrequency = 1;
	protected string $projectId = '';
	protected ?int $categoryId = null;
	protected ?string $paymentMode = null;
	protected ?int $paymentModeId = 0;
	protected int $lastChanged = 0;
	protected int $deleted = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('what', 'string');
		$this->addType('comment', 'string');
		$this->addType('payer_id', 'integer');
		$this->addType('timestamp', 'integer');
		$this->addType('amount', 'float');
		$this->addType('repeat', 'string');
		$this->addType('repeat_all_active', 'integer');
		$this->addType('repeat_until', 'string');
		$this->addType('repeat_frequency', 'integer');
		$this->addType('project_id', 'string');
		$this->addType('category_id', 'integer');
		$this->addType('payment_mode', 'string');
		$this->addType('payment_mode_id', 'integer');
		$this->addType('last_changed', 'integer');
		$this->addType('deleted', 'integer');
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
			'date' => DateTime::createFromFormat('U', (string)$this->getTimestamp())->format('Y-m-d'),
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
