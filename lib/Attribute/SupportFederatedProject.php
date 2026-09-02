<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Attribute;

use Attribute;

#[Attribute]
class SupportFederatedProject {
	/**
	 * Attribute for ApiController methods that support federated projects
	 * This requires the method to have a $projectId parameter. If the project is federated, the federation service will
	 * be used
	 */
	public function __construct(
	) {
	}
}
