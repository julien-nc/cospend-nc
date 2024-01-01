<?php

declare(strict_types=1);

namespace OCA\Cospend\Exception;

use OCP\AppFramework\Http;

class CospendUserPermissionsException extends \Exception {
	public function __construct($message = 'Cospend user permissions check failed', $code = Http::STATUS_UNAUTHORIZED) {
		parent::__construct($message, $code);
	}
}
