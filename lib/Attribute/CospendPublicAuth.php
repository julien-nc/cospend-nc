<?php

declare(strict_types=1);

namespace OCA\Cospend\Attribute;

use Attribute;

#[Attribute]
class CospendPublicAuth {
	/**
	 * Attribute for controller methods that require Cospend public authentication.
	 *
	 * This attribute requires that the route has "token" and "password" parameters.
	 *
	 * The controller method can ignore the "password" parameter and even not include it in its signature.
	 * The PublicAuthMiddleware will check if the token points to an existing project,
	 * if the password is correct (or if there is no password for this public shared access)
	 * and if this shared access has at least the minimum level permission
	 */
	public function __construct(
		protected int $minimumLevel,
	) {
	}

	public function getMinimumLevel(): int {
		return $this->minimumLevel;
	}
}
