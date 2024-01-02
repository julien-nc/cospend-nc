<?php
/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Controller;

use OCA\Cospend\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class UtilsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Delete user options
	 */
	#[NoAdminRequired]
	public function deleteOptionsValues(): DataResponse	{
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $key);
		}

		return new DataResponse(['done' => 1]);
	}

	/**
	 * Save options values to the DB for current user
	 */
	#[NoAdminRequired]
	public function saveOptionValue($options): DataResponse	{
		foreach ($options as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}

		return new DataResponse(['done' => true]);
	}

	/**
	 * get options values from the config for current user
	 */
	#[NoAdminRequired]
	public function getOptionsValues(): DataResponse {
		$ov = array();
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			$ov[$key] = $value;
		}

		return new DataResponse(['values' => $ov]);
	}
}
