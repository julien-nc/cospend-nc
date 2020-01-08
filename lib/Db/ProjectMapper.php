<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Cospend\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class ProjectMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'cospend_projects');
    }

    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*cospend_projects` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }

}
