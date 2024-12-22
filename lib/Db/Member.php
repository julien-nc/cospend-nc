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

use OCA\Cospend\ResponseDefinitions;
use OCA\Cospend\Utils;
use OCP\AppFramework\Db\Entity;
use OCP\IAvatarManager;

/**
 * @psalm-import-type CospendMember from ResponseDefinitions
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getProjectId()
 * @method void setProjectId(string $projectId)
 * @method string getName()
 * @method void setName(string $name)
 * @method float getWeight()
 * @method void setWeight(float $weight)
 * @method int getActivated()
 * @method void setActivated(int $activated)
 * @method int getLastChanged()
 * @method void setLastChanged(int $lastChanged)
 * @method string|null getColor()
 * @method void setColor(string|null $color)
 * @method string|null getUserId()
 * @method void setUserId(string|null $userId)
 */
class Member extends Entity implements \JsonSerializable {

	protected $projectId;
	protected $name;
	protected $weight;
	protected $activated;
	protected $lastChanged;
	protected $color;
	protected $userId;

	private $avatarManager;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('project_id', 'string');
		$this->addType('name', 'string');
		$this->addType('weight', 'float');
		$this->addType('activated', 'integer');
		$this->addType('last_changed', 'integer');
		$this->addType('color', 'string');
		$this->addType('user_id', 'string');
		$this->avatarManager = \OC::$server->get(IAvatarManager::class);
	}

	/**
	 * @return CospendMember
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'weight' => $this->getWeight(),
			'activated' => $this->getActivated() === 1,
			'lastchanged' => $this->getLastChanged(),
			'userid' => $this->getUserId(),
			'color' => $this->getColorArray(),
		];
	}

	private function getColorArray(): array {
		if ($this->getColor() === null) {
			$av = $this->avatarManager->getGuestAvatar($this->getName());
			$avBgColor = $av->avatarBackgroundColor($this->getName());
			return [
				'r' => $avBgColor->red(),
				'g' => $avBgColor->green(),
				'b' => $avBgColor->blue(),
			];
		}
		return Utils::hexToRgb($this->getColor());
	}
}
