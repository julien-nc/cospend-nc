<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getWhat()
 * @method void setWhat(string $what)
 * @method string getComment()
 * @method void setComment(string $comment)
 * @method int getPayerid()
 * @method void setPayerid(int $payerid)
 * @method int getAmount()
 * @method void setAmount(int $amount)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method string getRepeat()
 * @method void setRepeat(string $repeat)
 * @method int getRepeatallactive()
 * @method void setRepeatallactive(int $repeatallactive)
 * @method string getRepeatuntil()
 * @method void setRepeatuntil(string $repeatuntil)
 * @method int getRepeatfreq()
 * @method void setRepeatfreq(int $repeatfreq)
 * @method string getProjectid()
 * @method void setProjectid(string $projectid)
 * @method int getCategoryid()
 * @method void setCategoryid(int $categoryid)
 * @method string getPaymentmode()
 * @method void setPaymentmode(string $paymentmode)
 * @method int getPaymentmodeid()
 * @method void setPaymentmodeid(int $paymentmodeid)
 * @method int getLastchanged()
 * @method void setLastchanged(int $lastchanged)
 * @method int getDeleted()
 * @method void setDeleted(int $deleted) */
class Bill extends Entity implements \JsonSerializable {

	protected $what;
	protected $comment;
	protected $payerid;
	protected $amount;
	protected $timestamp;
	protected $repeat;
	protected $repeatallactive;
	protected $repeatuntil;
	protected $repeatfreq;
	protected $projectid;
	protected $categoryid;
	protected $paymentmode;
	protected $paymentmodeid;
	protected $lastchanged;
	protected $deleted;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('what', 'string');
		$this->addType('comment', 'string');
		$this->addType('payerid', 'integer');
		$this->addType('timestamp', 'integer');
		$this->addType('amount', 'float');
		$this->addType('repeat', 'string');
		$this->addType('repeatallactive', 'integer');
		$this->addType('repeatuntil', 'string');
		$this->addType('repeatfreq', 'integer');
		$this->addType('projectid', 'string');
		$this->addType('categoryid', 'integer');
		$this->addType('paymentmode', 'string');
		$this->addType('paymentmodeid', 'integer');
		$this->addType('lastchanged', 'integer');
		$this->addType('deleted', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'what' => $this->what,
			'comment' => $this->comment,
			'payerid' => (int)$this->payerid,
			'timestamp' => (int)$this->timestamp,
			'amount' => (int)$this->amount,
			'repeat' => $this->repeat,
			'repeatallactive' => (int)$this->repeatallactive,
			'repeatuntil' => $this->repeatuntil,
			'repeatfreq' => (int)$this->repeatfreq,
			'projectid' => $this->projectid,
			'categoryid' => (int)$this->categoryid,
			'paymentmode' => $this->paymentmode,
			'paymentmodeid' => (int)$this->paymentmodeid,
			'deleted' => (int)$this->deleted,
		];
	}
}
