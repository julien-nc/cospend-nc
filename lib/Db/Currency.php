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
 * @method \void setProjectId(string $projectId)
 * @method \string getProjectId()
 * @method \void setName(string $name)
 * @method \string getName()
 * @method \void setExchangeRate(float $exchangeRate)
 * @method \float getExchangeRate()
 */
class Currency extends Entity implements \JsonSerializable {

	protected $projectId;
	protected $name;
	protected $exchangeRate;

	public function __construct() {
		$this->addType('projectId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('exchangeRate', Types::FLOAT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectid' => $this->getProjectId(),
			'name' => $this->getName(),
			'exchange_rate' => $this->getExchangeRate(),
		];
	}
}
