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
 * @method void setUserId(string|null $userId)
 * @method string|null getUserId()
 * @method void setType(string $type)
 * @method string getType()
 * @method void setAccessLevel(int $accessLevel)
 * @method int getAccessLevel()
 * @method void setManuallyAdded(int $manuallyAdded)
 * @method int getManuallyAdded()
 * @method void setLabel(string|null $label)
 * @method string|null getLabel()
 * @method void setPassword(string|null $password)
 * @method string|null getPassword()
 * @method void setUserCloudId(string|null $userCloudId)
 * @method string|null getUserCloudId()
 * @method void setState(int|null $state)
 * @method int|null getState()
 */
class Share extends Entity implements \JsonSerializable {

	public const TYPE_FEDERATION = 'f';
	public const TYPE_PUBLIC_LINK = 'l';
	public const TYPE_USER = 'u';
	public const TYPE_GROUP = 'g';
	public const TYPE_CIRCLE = 'c';

	protected $projectId;
	protected $userId;
	protected $type;
	protected $accessLevel;
	protected $manuallyAdded;
	protected $label;
	protected $password;
	protected $userCloudId;
	protected $state;

	public function __construct() {
		$this->addType('project_id', 'string');
		$this->addType('user_id', 'string');
		$this->addType('type', 'string');
		$this->addType('access_level', 'integer');
		$this->addType('manually_added', 'integer');
		$this->addType('label', 'string');
		$this->addType('password', 'string');
		$this->addType('user_cloud_id', 'string');
		$this->addType('state', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectid' => $this->getProjectId(),
			'userid' => $this->getUserId(),
			'type' => $this->getType(),
			'accesslevel' => $this->getAccessLevel(),
			'manuallyAdded' => $this->getManuallyAdded() === 1,
			'label' => $this->getLabel(),
			'password' => $this->getPassword(),
			'userCloudId' => $this->getUserCloudId(),
			'state' => $this->getState(),
		];
	}
}
