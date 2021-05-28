<?php
/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2018
 */

namespace OCA\Cospend\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\Notification\IManager as INotificationManager;

use OCA\Cospend\Search\CospendSearchProvider;
use OCA\Cospend\Dashboard\CospendWidget;
use OCA\Cospend\Notification\Notifier;

/**
 * Class Application
 *
 * @package OCA\Cospend\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'cospend';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		// content of app.php
		//$manager = \OC::$server->getNotificationManager();
		$manager = $container->query(INotificationManager::class);
		$manager->registerNotifierService(Notifier::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerSearchProvider(CospendSearchProvider::class);
		$context->registerDashboardWidget(CospendWidget::class);
	}

	public function boot(IBootContext $context): void {
	}

}

