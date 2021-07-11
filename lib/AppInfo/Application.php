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

	public const CAT_GROCERY = -1;
	public const CAT_BAR = -2;
	public const CAT_RENT = -3;
	public const CAT_BILL = -4;
	public const CAT_CULTURE = -5;
	public const CAT_HEALTH = -6;
	public const CAT_SHOPPING = -10;
	public const CAT_REIMBURSEMENT = -11;
	public const CAT_RESTAURANT = -12;
	public const CAT_ACCOMODATION = -13;
	public const CAT_TRANSPORT = -14;
	public const CAT_SPORT = -15;

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

