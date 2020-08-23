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

use OCA\Cospend\Search\CospendSearchProvider;
use OCA\Cospend\Dashboard\CospendWidget;

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
        $manager = \OC::$server->getNotificationManager();
        $manager->registerNotifierService(Notifier::class);

        $container->query(\OCP\INavigationManager::class)->add(function () use ($container) {
            $urlGenerator = $container->query(\OCP\IURLGenerator::class);
            $l10n = $container->query(\OCP\IL10N::class);
            return [
                'id' => self::APP_ID,

                'order' => 10,

                // the route that will be shown on startup
                'href' => $urlGenerator->linkToRoute('cospend.page.index'),

                // the icon that will be shown in the navigation
                // this file needs to exist in img/
                'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),

                // the title of your application. This will be used in the
                // navigation or on the settings page of your app
                'name' => $l10n->t('Cospend'),
            ];
        });
    }

    public function register(IRegistrationContext $context): void {
        $context->registerSearchProvider(CospendSearchProvider::class);
        $context->registerDashboardWidget(CospendWidget::class);
    }

    public function boot(IBootContext $context): void {
    }

}

