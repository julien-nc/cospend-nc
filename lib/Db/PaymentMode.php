<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setProjectId(string $projectId)
 * @method string getProjectId()
 * @method void setName(string|null $name)
 * @method string|null getName()
 * @method void setColor(string|null $color)
 * @method string|null getColor()
 * @method void setEncodedIcon(string|null $encodedIcon)
 * @method string|null getEncodedIcon()
 * @method void setOrder(int $order)
 * @method int getOrder()
 * @method void setOldId(string|null $oldId)
 * @method string|null getOldId()
 */
class PaymentMode extends Entity implements \JsonSerializable {

	protected $projectId;
	protected $name;
	protected $color;
	protected $encodedIcon;
	protected $order;
	protected $oldId;

	public function __construct() {
		$this->addType('project_id', 'string');
		$this->addType('name', 'string');
		$this->addType('color', 'string');
		$this->addType('encoded_icon', 'string');
		$this->addType('order', 'integer');
		$this->addType('old_id', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectid' => $this->getProjectId(),
			'name' => $this->getName(),
			'color' => $this->getColor(),
			'icon' => $this->getEncodedIcon() === null ? null : urldecode($this->getEncodedIcon()),
			'order' => $this->getOrder(),
			'old_id' => $this->getOldId(),
		];
	}
}
