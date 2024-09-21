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
 * @method void setExchangeRate(float|null $exchangeRate)
 * @method float|null getExchangeRate()
 */
class Currency extends Entity implements \JsonSerializable {
	protected string $projectId = '';
	protected ?string $name = null;
	protected ?float $exchangeRate = null;

	public function __construct() {
		$this->addType('project_id', 'string');
		$this->addType('name', 'string');
		$this->addType('exchange_rate', 'float');
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
