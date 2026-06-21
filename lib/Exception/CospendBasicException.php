<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Exception;

use OCP\AppFramework\Http;

class CospendBasicException extends \Exception {
	public function __construct(
		string $message = 'Cospend public auth failed',
		int $code = Http::STATUS_UNAUTHORIZED,
		// extra data to potentially return in an API response
		public array $data = [],
	) {
		parent::__construct($message, $code);
	}
}
