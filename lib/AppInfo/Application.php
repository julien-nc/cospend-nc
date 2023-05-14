<?php
/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2018
 */

namespace OCA\Cospend\AppInfo;

use OCA\Cospend\UserMigration\UserMigrator;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Cospend\Search\CospendSearchProvider;
use OCA\Cospend\Dashboard\CospendWidget;
use OCA\Cospend\Notification\Notifier;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\Cospend\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'cospend';

	public const CAT_REIMBURSEMENT = -11;

	public const SORT_ORDERS = [
		'alpha' => 'a',
		'manual' => 'm',
		'most_used' => 'u',
		'most_recently_used' => 'r',
	];

	public const FREQUENCIES = [
		'no' => 'n',
		'daily' => 'd',
		'weekly' => 'w',
		'bi_weekly' => 'b',
		'semi_monthly' => 's',
		'monthly' => 'm',
		'yearly' => 'y',
	];

	public const ACCESS_LEVELS = [
		'none' => 0,
		'viewer' => 1,
		'participant' => 2,
		'maintainer' => 3,
		'admin' => 4,
	];

	public const SHARE_TYPES = [
		'public_link' => 'l',
		'user' => 'u',
		'group' => 'g',
		'circle' => 'c',
	];

	public const HARDCODED_CATEGORIES = [
		-11 => [
			'icon' => '💰',
		],
	];

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerSearchProvider(CospendSearchProvider::class);
		$context->registerDashboardWidget(CospendWidget::class);
		$context->registerUserMigrator(UserMigrator::class);
	}

	public function boot(IBootContext $context): void {
		Util::addStyle(self::APP_ID, 'cospend-search');
	}
}

