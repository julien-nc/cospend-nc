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
use OCA\Cospend\Notification\Notifier;

$app = new Application();
$container = $app->getContainer();

$manager = \OC::$server->getNotificationManager();
$manager->registerNotifierService(Notifier::class);

$container->query('OCP\INavigationManager')->add(function () use ($container) {
    $urlGenerator = $container->query('OCP\IURLGenerator');
    $l10n = $container->query('OCP\IL10N');
    return [
        'id' => 'cospend',

        'order' => 10,

        // the route that will be shown on startup
        'href' => $urlGenerator->linkToRoute('cospend.page.index'),

        // the icon that will be shown in the navigation
        // this file needs to exist in img/
        'icon' => $urlGenerator->imagePath('cospend', 'app.svg'),

        // the title of your application. This will be used in the
        // navigation or on the settings page of your app
        'name' => $l10n->t('Cospend'),
    ];
});
