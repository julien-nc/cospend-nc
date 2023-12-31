<?php

declare(strict_types=1);

namespace OCA\Cospend\Attribute;

use Attribute;

/**
 * Attribute for controller methods that require Cospend public authentication
 */
#[Attribute]
class CospendPublicAuth {
	public function __construct(
		protected int $minimumLevel
	) {
	}

	public function getMinimumLevel(): int {
		return $this->minimumLevel;
	}
}
