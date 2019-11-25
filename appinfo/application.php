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

use OCA\Cospend\Controller\PageController;
use OCA\Cospend\Controller\UtilsController;

/**
 * Class Application
 *
 * @package OCA\Cospend\AppInfo
 */
class Application extends App {

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct('cospend', $urlParams);

        $container = $this->getContainer();

    }

}

