<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \void setUserId(string $userId)
 * @method \string getUserId()
 * @method \void setState(int $state)
 * @method \int getState()
 * @method \void setAccessToken(string $accessToken)
 * @method \string getAccessToken()
 * @method \void setRemoteProjectId(string $remoteProjectId)
 * @method \string getRemoteProjectId()
 * @method \void setRemoteProjectName(string $remoteProjectName)
 * @method \string getRemoteProjectName()
 * @method \void setRemoteServerUrl(string $remoteServerUrl)
 * @method \string getRemoteServerUrl()
 * @method \void setInviterCloudId(string $inviterCloudId)
 * @method \string getInviterCloudId()
 * @method \void setInviterDisplayName(string $inviterDisplayName)
 * @method \string getInviterDisplayName()
 */
class Invitation extends Entity implements \JsonSerializable {

	public const STATE_PENDING = 0;
	public const STATE_ACCEPTED = 1;

	protected $userId;
	protected $state;
	protected $accessToken;
	protected $remoteProjectId;
	protected $remoteProjectName;
	protected $remoteServerUrl;
	protected $inviterCloudId;
	protected $inviterDisplayName;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('state', Types::INTEGER);
		$this->addType('accessToken', Types::STRING);
		$this->addType('remoteProjectId', Types::STRING);
		$this->addType('remoteProjectName', Types::STRING);
		$this->addType('remoteServerUrl', Types::STRING);
		$this->addType('inviterCloudId', Types::STRING);
		$this->addType('inviterDisplayName', Types::STRING);
	}

	/**
	 * @return array{id: int, remoteProjectId: string, remoteProjectName: string, remoteServerUrl: string, accessToken: string, state: int, userId: string, inviterCloudId: string, inviterDisplayName: string}
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'state' => $this->getState(),
			'accessToken' => $this->getAccessToken(),
			'remoteProjectId' => $this->getRemoteProjectId(),
			'remoteProjectName' => $this->getRemoteProjectName(),
			'remoteServerUrl' => $this->getRemoteServerUrl(),
			'inviterCloudId' => $this->getInviterCloudId(),
			'inviterDisplayName' => $this->getInviterDisplayName() ?: $this->getInviterCloudId(),
		];
	}
}
