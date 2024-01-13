<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2024
 */

namespace OCA\Cospend\Db;

use OCA\Cospend\Utils;
use OCP\AppFramework\Db\Entity;
use OCP\IAvatarManager;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getProjectid()
 * @method void setProjectid(string $projectid)
 * @method string getName()
 * @method void setName(string $name)
 * @method float getWeight()
 * @method void setWeight(float $weight)
 * @method int getActivated()
 * @method void setActivated(int $activated)
 * @method int getLastchanged()
 * @method void setLastchanged(int $lastchanged)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method string|null getUserid()
 * @method void setUserid(string|null $userid)
 */
class Member extends Entity implements \JsonSerializable {

	protected $projectid;
	protected $name;
	protected $weight;
	protected $activated;
	protected $lastchanged;
	protected $color;
	protected $userid;

	private $avatarManager;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectid', 'string');
		$this->addType('name', 'string');
		$this->addType('weight', 'float');
		$this->addType('activated', 'integer');
		$this->addType('lastchanged', 'integer');
		$this->addType('color', 'string');
		$this->addType('userid', 'string');
		$this->avatarManager = \OC::$server->get(IAvatarManager::class);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'weight' => $this->weight,
			'activated' => $this->activated === 1,
			'lastchanged' => $this->lastchanged,
			'userid' => $this->userid,
			'color' => $this->getColorArray(),
		];
	}

	private function getColorArray(): array {
		if ($this->color === null) {
			$av = $this->avatarManager->getGuestAvatar($this->name);
			$avBgColor = $av->avatarBackgroundColor($this->name);
			return [
				'r' => $avBgColor->red(),
				'g' => $avBgColor->green(),
				'b' => $avBgColor->blue(),
			];
		}
		return Utils::hexToRgb($this->color);
	}
}
