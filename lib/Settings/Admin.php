<?php

namespace OCA\Cospend\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(private IConfig $config) {
	}

	/**
	 * @inheritDoc
	 */
	public function getForm(): TemplateResponse {
		$allow = $this->config->getAppValue('cospend', 'allowAnonymousCreation');

		$parameters = [
			'allowAnonymousCreation' => $allow
		];
		return new TemplateResponse('cospend', 'admin', $parameters, '');
	}

	/**
	 * @inheritDoc
	 */
	public function getSection(): string {
		return 'additional';
	}

	/**
	 * @inheritDoc
	 */
	public function getPriority(): int {
		return 5;
	}
}
