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
 */
class Bill extends Entity {

	protected $what;
	protected $comment;
	protected $payerid;
	protected $timestamp;
	protected $repeat;
	protected $repeatallactive;
	protected $projectid;
	protected $amount;
	protected $categoryid;
	protected $paymentmode;
	protected $paymentmodeid;
	protected $lastchanged;
	protected $repeatuntil;
	protected $repeatfreq;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('what', 'string');
		$this->addType('comment', 'string');
		$this->addType('payerid', 'integer');
		$this->addType('timestamp', 'integer');
		$this->addType('amount', 'float');
		$this->addType('repeat', 'string');
		$this->addType('repeatallactive', 'integer');
		$this->addType('projectid', 'string');
		$this->addType('categoryid', 'integer');
		$this->addType('paymentmode', 'string');
		$this->addType('paymentmodeid', 'integer');
		$this->addType('lastchanged', 'integer');
		$this->addType('repeatuntil', 'string');
		$this->addType('repeatfreq', 'integer');
	}
}
