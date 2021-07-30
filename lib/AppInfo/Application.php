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

use OCP\AppFramework\App;
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

	public const CAT_REIMBURSEMENT = -11;

	public const SORT_ORDER_ALPHA = 'a';
	public const SORT_ORDER_MANUAL = 'm';
	public const SORT_ORDER_MOST_USED = 'u';
	public const SORT_ORDER_MOST_RECENTLY_USED = 'r';

	public const FREQUENCY_NO = 'n';
	public const FREQUENCY_DAILY = 'd';
	public const FREQUENCY_WEEKLY = 'w';
	public const FREQUENCY_BI_WEEKLY = 'b';
	public const FREQUENCY_SEMI_MONTHLY = 's';
	public const FREQUENCY_MONTHLY = 'm';
	public const FREQUENCY_YEARLY = 'y';

	public const NO_ACCESS = 0;
	public const ACCESS_VIEWER = 1;
	public const ACCESS_PARTICIPANT = 2;
	public const ACCESS_MAINTENER = 3;
	public const ACCESS_ADMIN = 4;

	public const SHARE_TYPE_PUBLIC_LINK = 'l';
	public const SHARE_TYPE_USER = 'u';
	public const SHARE_TYPE_GROUP = 'g';
	public const SHARE_TYPE_CIRCLE = 'c';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		// content of app.php
		$manager = $container->get(INotificationManager::class);
		$manager->registerNotifierService(Notifier::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerSearchProvider(CospendSearchProvider::class);
		$context->registerDashboardWidget(CospendWidget::class);
	}

	public function boot(IBootContext $context): void {
	}

}

