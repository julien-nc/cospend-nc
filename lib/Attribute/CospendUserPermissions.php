<?php

declare(strict_types=1);

namespace OCA\Cospend\Attribute;

use Attribute;

#[Attribute]
class CospendUserPermissions {
	/**
	 * Attribute for controller methods that require Cospend user permission check
	 * This requires the method to have a $projectId parameter on which the current user permissions will be checked
	 * The UserPermissionMiddleware will check if the current user has at least the minimum level access to this project
	 * if the project is local
	 */
	public function __construct(
		protected int $minimumLevel
	) {
	}

	public function getMinimumLevel(): int {
		return $this->minimumLevel;
	}
}
