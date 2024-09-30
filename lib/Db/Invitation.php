<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setState(int $state)
 * @method int getState()
 * @method void setAccessToken(string $accessToken)
 * @method string getAccessToken()
 * @method void setRemoteProjectId(string $remoteProjectId)
 * @method string getRemoteProjectId()
 * @method void setRemoteProjectName(string $remoteProjectName)
 * @method string getRemoteProjectName()
 * @method void setRemoteServerUrl(string $remoteServerUrl)
 * @method string getRemoteServerUrl()
 * @method void setInviterCloudId(string $inviterCloudId)
 * @method string getInviterCloudId()
 * @method void setInviterDisplayName(string $inviterDisplayName)
 * @method string getInviterDisplayName()
 */
class Invitation extends Entity implements \JsonSerializable {
	public const STATE_PENDING = 0;
	public const STATE_ACCEPTED = 1;

	protected string $userId = '';
	protected int $state = self::STATE_PENDING;
	protected string $accessToken = '';
	protected string $remoteProjectId = '';
	protected string $remoteProjectName = '...';
	protected string $remoteServerUrl = '';
	protected string $inviterCloudId = '';
	protected string $inviterDisplayName = '';

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('state', 'int');
		$this->addType('accessToken', 'string');
		$this->addType('remoteProjectId', 'string');
		$this->addType('remoteProjectName', 'string');
		$this->addType('remoteServerUrl', 'string');
		$this->addType('inviterCloudId', 'string');
		$this->addType('inviterDisplayName', 'string');
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
