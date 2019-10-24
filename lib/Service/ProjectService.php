<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OC\Archive\ZIP;

class ProjectService {

    private $l10n;
    private $logger;
    private $qb;
    private $dbconnection;

    public function __construct (ILogger $logger, IL10N $l10n) {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $this->dbconnection = \OC::$server->getDatabaseConnection();
    }

    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    public function findUsers($id) {
        $userIds = [];
        // get owner with mapper

        // get user shares from project id

        // get group shares from project id
        return $userIds;
    }

}
