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

use OCA\Cospend\Capabilities;
use OCA\Cospend\Dashboard\CospendWidget;
use OCA\Cospend\Middleware\PublicAuthMiddleware;
use OCA\Cospend\Middleware\UserPermissionMiddleware;
use OCA\Cospend\Notification\Notifier;
use OCA\Cospend\Search\CospendSearchProvider;
use OCA\Cospend\UserMigration\UserMigrator;
use OCP\AppFramework\App;

use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;

class Application extends App implements IBootstrap {

	public const APP_ID = 'cospend';

	public const CATEGORY_REIMBURSEMENT = -11;

	public const SORT_ORDER_ALPHA = 'a';
	public const SORT_ORDER_MANUAL = 'm';
	public const SORT_ORDER_MOST_USED = 'u';
	public const SORT_ORDER_RECENTLY_USED = 'r';
	public const SORT_ORDERS = [
		self::SORT_ORDER_ALPHA,
		self::SORT_ORDER_MANUAL,
		self::SORT_ORDER_MOST_USED,
		self::SORT_ORDER_RECENTLY_USED,
	];

	public const FREQUENCY_NO = 'n';
	public const FREQUENCY_DAILY = 'd';
	public const FREQUENCY_WEEKLY = 'w';
	public const FREQUENCY_BI_WEEKLY = 'b';
	public const FREQUENCY_SEMI_MONTHLY = 's';
	public const FREQUENCY_MONTHLY = 'm';
	public const FREQUENCY_YEARLY = 'y';
	public const FREQUENCIES = [
		self::FREQUENCY_NO,
		self::FREQUENCY_DAILY,
		self::FREQUENCY_WEEKLY,
		self::FREQUENCY_BI_WEEKLY,
		self::FREQUENCY_SEMI_MONTHLY,
		self::FREQUENCY_MONTHLY,
		self::FREQUENCY_YEARLY,
	];

	public const ACCESS_LEVEL_NONE = 0;
	public const ACCESS_LEVEL_VIEWER = 1;
	public const ACCESS_LEVEL_PARTICIPANT = 2;
	public const ACCESS_LEVEL_MAINTAINER = 3;
	public const ACCESS_LEVEL_ADMIN = 4;
	public const ACCESS_LEVELS = [
		self::ACCESS_LEVEL_NONE,
		self::ACCESS_LEVEL_VIEWER,
		self::ACCESS_LEVEL_PARTICIPANT,
		self::ACCESS_LEVEL_MAINTAINER,
		self::ACCESS_LEVEL_ADMIN,
	];

	public const SHARE_TYPE_FEDERATION = 'f';
	public const SHARE_TYPE_PUBLIC_LINK = 'l';
	public const SHARE_TYPE_USER = 'u';
	public const SHARE_TYPE_GROUP = 'g';
	public const SHARE_TYPE_CIRCLE = 'c';
	public const SHARE_TYPES = [
		self::SHARE_TYPE_FEDERATION,
		self::SHARE_TYPE_PUBLIC_LINK,
		self::SHARE_TYPE_USER,
		self::SHARE_TYPE_GROUP,
		self::SHARE_TYPE_CIRCLE,
	];

	public const HARDCODED_CATEGORIES = [
		-11 => [
			'icon' => '💰',
		],
	];

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
		// TODO
		// - rename db columns with underscores, change new APIs param names, keep a second jsonSerialize method for old APIs
		// - check if it makes sense to have a paypal integration
		// - check how to switch to numerical project IDs (keep unique slug for client compatibility)
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerSearchProvider(CospendSearchProvider::class);
		$context->registerDashboardWidget(CospendWidget::class);

		$context->registerUserMigrator(UserMigrator::class);

		$context->registerMiddleware(PublicAuthMiddleware::class);
		$context->registerMiddleware(UserPermissionMiddleware::class);

		$context->registerCapability(Capabilities::class);
	}

	public function boot(IBootContext $context): void {
		Util::addStyle(self::APP_ID, 'cospend-search');
	}
}
