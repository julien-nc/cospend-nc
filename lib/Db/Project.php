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
 * @method string getId()
 * @method void setId(string $id)
 * @method string getUserid()
 * @method void setUserid(string $userid)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method string getPassword()
 * @method void setPassword(string $password)
 * @method string getAutoexport()
 * @method void setAutoexport(string $autoexport)
 * @method int getLastchanged()
 * @method void setLastchanged(int $lastchanged)
 * @method int getGuestaccesslevel()
 * @method void setGuestaccesslevel(int $guestaccesslevel)
 * @method int getDeletiondisabled()
 * @method void setDeletiondisabled(int $deletiondisabled)
 * @method string getCategorysort()
 * @method void setCategorysort(string $categorysort)
 * @method string getPaymentmodesort()
 * @method void setPaymentmodesort(string $paymentmodesort)
 * @method string getCurrencyname()
 * @method void setCurrencyname(string $currencyname)
 * @method int getArchivedTs()
 * @method void setArchivedTs(int $archivedTs)
 */
class Project extends Entity implements \JsonSerializable {

	protected $userid;
	protected $name;
	protected $email;
	protected $password;
	protected $autoexport;
	protected $lastchanged;
	protected $guestaccesslevel;
	protected $deletiondisabled;
	protected $categorysort;
	protected $paymentmodesort;
	protected $currencyname;
	protected $archivedTs;

	public function __construct() {
		$this->addType('id', 'string');
		$this->addType('userid', 'string');
		$this->addType('name', 'string');
		$this->addType('email', 'string');
		$this->addType('password', 'string');
		$this->addType('autoexport', 'string');
		$this->addType('lastchanged', 'integer');
		$this->addType('guestaccesslevel', 'integer');
		$this->addType('deletiondisabled', 'integer');
		$this->addType('categorysort', 'string');
		$this->addType('paymentmodesort', 'string');
		$this->addType('currencyname', 'string');
		$this->addType('archived_ts', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'userid' => $this->userid,
			'name' => $this->name,
			'email' => $this->email,
			'password' => $this->password,
			'autoexport' => $this->autoexport,
			'lastchanged' => (int)$this->lastchanged,
			'guestaccesslevel' => (int)$this->guestaccesslevel,
			'deletiondisabled' => ((int)$this->deletiondisabled) === 1,
			'categorysort' => $this->categorysort,
			'paymentmodesort' => $this->paymentmodesort,
			'currencyname' => $this->currencyname,
			'archived' => $this->archived,
		];
	}
}
