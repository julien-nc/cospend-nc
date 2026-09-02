<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setProjectId(string $projectId)
 * @method string getProjectId()
 * @method void setBillTitle(string $billTitle)
 * @method string getBillTitle()
 * @method void setCategoryId(int|null $categoryId)
 * @method int|null getCategoryId()
 * @method void setLastChanged(int $lastChanged)
 * @method int getLastChanged()
 * @method void setCreatedAt(int $createdAt)
 * @method int getCreatedAt()
 */
class AutoCategoryMapping extends Entity implements \JsonSerializable {

	protected $projectId;
	protected $billTitle;
	protected $categoryId;
	protected $lastChanged;
	protected $createdAt;

	public function __construct() {
		$this->addType('projectId', Types::STRING);
		$this->addType('billTitle', Types::STRING);
		$this->addType('categoryId', Types::INTEGER);
		$this->addType('lastChanged', Types::INTEGER);
		$this->addType('createdAt', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'project_id' => $this->getProjectId(),
			'bill_title' => $this->getBillTitle(),
			'category_id' => $this->getCategoryId(),
			'last_changed' => $this->getLastChanged(),
			'created_at' => $this->getCreatedAt(),
		];
	}
}
