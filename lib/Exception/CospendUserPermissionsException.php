<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Exception;

use OCP\AppFramework\Http;

class CospendUserPermissionsException extends \Exception {
	public function __construct($message = 'Cospend user permissions check failed', $code = Http::STATUS_UNAUTHORIZED) {
		parent::__construct($message, $code);
	}
}
