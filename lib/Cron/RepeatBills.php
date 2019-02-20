<?php
/**
 * Nextcloud - Cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\Cospend\Cron;

use \OCA\Cospend\AppInfo\Application;

class RepeatBills extends \OC\BackgroundJob\TimedJob {

    public function __construct() {
        // Run each day
        $this->setInterval(24 * 60 * 60);
    }

    protected function run($argument) {
        (new Application())->getContainer()->query('PageController')->cronRepeatBills();
    }

}
