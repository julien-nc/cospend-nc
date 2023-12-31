<?php

declare(strict_types=1);

namespace OCA\Cospend\Exception;

use OCP\AppFramework\Http;

class CospendPublicAuthNotValidException extends \Exception {
	public function __construct($message = 'Cospend public auth failed', $code = Http::STATUS_UNAUTHORIZED) {
		parent::__construct($message, $code);
	}
}
