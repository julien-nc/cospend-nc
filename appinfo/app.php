<?php
/**
 * Nextcloud - spend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2018
 */

namespace OCA\Spend\AppInfo;

use OCP\AppFramework\App;
use OCA\Spend\Notification\Notifier;

$app = new Application();
$container = $app->getContainer();

$manager = \OC::$server->getNotificationManager();
$manager->registerNotifier(function() {
        return \OC::$server->query(Notifier::class);
}, function() {
        $l = \OC::$server->getL10N('spend');
        return [
                'id' => 'spned',
                'name' => $l->t('Spend'),
        ];
});

$container->query('OCP\INavigationManager')->add(function () use ($container) {
    $urlGenerator = $container->query('OCP\IURLGenerator');
    $l10n = $container->query('OCP\IL10N');
    return [
        'id' => 'spend',

        'order' => 10,

        // the route that will be shown on startup
        'href' => $urlGenerator->linkToRoute('spend.page.index'),

        // the icon that will be shown in the navigation
        // this file needs to exist in img/
        'icon' => $urlGenerator->imagePath('spend', 'app.svg'),

        // the title of your application. This will be used in the
        // navigation or on the settings page of your app
        'name' => $l10n->t('Spend'),
    ];
});
