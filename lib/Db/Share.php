<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setProjectid(string $projectid)
 * @method string getProjectid()
 * @method void setUserid(string|null $userid)
 * @method string|null getUserid()
 * @method void setType(string $type)
 * @method string getType()
 * @method void setAccesslevel(int $accesslevel)
 * @method int getAccesslevel()
 * @method void setManuallyAdded(int $manuallyAdded)
 * @method int getManuallyAdded()
 * @method void setLabel(string|null $label)
 * @method string|null getLabel()
 * @method void setPassword(string|null $password)
 * @method string|null getPassword()
 * @method void setRemoteUserId(string|null $remoteUserId)
 * @method string|null getRemoteUserId()
 * @method void setRemoteServerUrl(string|null $remoteServerUrl)
 * @method string|null getRemoteServerUrl()
 */
class Share extends Entity implements \JsonSerializable {
	public const TYPE_FEDERATION = 'f';
	public const TYPE_PUBLIC_LINK = 'l';
	public const TYPE_USER = 'u';
	public const TYPE_GROUP = 'g';
	public const TYPE_CIRCLE = 'c';

	protected string $projectid = '';
	protected ?string $userid = null;
	protected string $type = self::TYPE_USER;
	protected int $accesslevel = 2;
	protected int $manuallyAdded = 1;
	protected ?string $label = null;
	protected ?string $password = null;
	protected ?string $remoteUserId = null;
	protected ?string $remoteServerUrl = null;

	public function __construct() {
		$this->addType('projectid', 'string');
		$this->addType('userid', 'string');
		$this->addType('type', 'string');
		$this->addType('accesslevel', 'integer');
		$this->addType('manuallyAdded', 'integer');
		$this->addType('label', 'string');
		$this->addType('password', 'string');
		$this->addType('remoteUserId', 'string');
		$this->addType('remoteServerUrl', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectid' => $this->getProjectid(),
			'userid' => $this->getUserId(),
			'type' => $this->getType(),
			'accesslevel' => $this->getAccesslevel(),
			'manuallyAdded' => $this->getManuallyAdded(),
			'label' => $this->getLabel(),
			'password' => $this->getPassword(),
			'remoteUserId' => $this->getRemoteUserId(),
			'remoteServerUrl' => $this->getRemoteServerUrl(),
		];
	}
}
