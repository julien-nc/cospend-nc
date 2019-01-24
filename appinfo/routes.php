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

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#apiCreateProject', 'url' => '/api/projects', 'verb' => 'POST'],
        ['name' => 'page#apiGetProjectInfo', 'url' => '/api/projects/{projectid}', 'verb' => 'GET'],
        ['name' => 'page#apiSetProjectInfo', 'url' => '/api/projects/{projectid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteProject', 'url' => '/api/projects/{projectid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiGetMembers', 'url' => '/api/projects/{projectid}/members', 'verb' => 'GET'],
        ['name' => 'page#apiAddMember', 'url' => '/api/projects/{projectid}/members', 'verb' => 'POST'],
        ['name' => 'page#apiEditMember', 'url' => '/api/projects/{projectid}/members/{memberid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteMember', 'url' => '/api/projects/{projectid}/members/{memberid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiGetBills', 'url' => '/api/projects/{projectid}/bills', 'verb' => 'GET'],
        ['name' => 'page#apiAddBill', 'url' => '/api/projects/{projectid}/bills', 'verb' => 'POST'],
        ['name' => 'page#apiEditBill', 'url' => '/api/projects/{projectid}/bills/{billid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteBill', 'url' => '/api/projects/{projectid}/bills/{billid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiGetProjectStatistics', 'url' => '/api/projects/{projectid}/statistics', 'verb' => 'GET'],
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'POST'],
    ]
];
