<?php

declare(strict_types=1);

namespace OCA\Cospend\Attribute;

use Attribute;

/**
 * Attribute for controller methods that require Cospend user permission check
 */
#[Attribute]
class CospendUserPermissions {
	public function __construct(
		protected int $minimumLevel
	) {
	}

	public function getMinimumLevel(): int {
		return $this->minimumLevel;
	}
}
