<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend\Exception;

use OCP\AppFramework\Http;

class CospendPublicAuthNotValidException extends \Exception {
	public function __construct(
		string $message = 'Cospend public auth failed',
		int $code = Http::STATUS_UNAUTHORIZED,
		public string $token = '',
		public string $password = '',
		public string $reason = '',
	) {
		parent::__construct($message, $code);
	}
}
