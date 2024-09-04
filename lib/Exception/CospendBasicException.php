<?php

declare(strict_types=1);

namespace OCA\Cospend\Exception;

use OCP\AppFramework\Http;

class CospendBasicException extends \Exception {
	public function __construct(
		string $message = 'Cospend public auth failed',
		int $code = Http::STATUS_UNAUTHORIZED,
		// extra data to potnetially return in an API response
		public array $data = [],
	) {
		parent::__construct($message, $code);
	}
}
