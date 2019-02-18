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

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#apiCreateProject', 'url' => '/api/projects', 'verb' => 'POST'],
        ['name' => 'page#apiGetProjectInfo', 'url' => '/api/projects/{projectid}/{password}', 'verb' => 'GET'],
        ['name' => 'page#apiSetProjectInfo', 'url' => '/api/projects/{projectid}/{passwd}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteProject', 'url' => '/api/projects/{projectid}/{password}', 'verb' => 'DELETE'],
        ['name' => 'page#apiGetMembers', 'url' => '/api/projects/{projectid}/{password}/members', 'verb' => 'GET'],
        ['name' => 'page#apiAddMember', 'url' => '/api/projects/{projectid}/{password}/members', 'verb' => 'POST'],
        ['name' => 'page#apiEditMember', 'url' => '/api/projects/{projectid}/{password}/members/{memberid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteMember', 'url' => '/api/projects/{projectid}/{password}/members/{memberid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiGetBills', 'url' => '/api/projects/{projectid}/{password}/bills', 'verb' => 'GET'],
        ['name' => 'page#apiAddBill', 'url' => '/api/projects/{projectid}/{password}/bills', 'verb' => 'POST'],
        ['name' => 'page#apiEditBill', 'url' => '/api/projects/{projectid}/{password}/bills/{billid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteBill', 'url' => '/api/projects/{projectid}/{password}/bills/{billid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiGetProjectStatistics', 'url' => '/api/projects/{projectid}/{password}/statistics', 'verb' => 'GET'],
        ['name' => 'page#apiGetProjectSettlement', 'url' => '/api/projects/{projectid}/{password}/settle', 'verb' => 'GET'],
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'POST'],
        ['name' => 'utils#setAllowAnonymousCreation', 'url' => '/setAllowAnonymousCreation', 'verb' => 'POST'],
        ['name' => 'page#getUserList', 'url' => '/getUserList', 'verb' => 'POST'],
        ['name' => 'page#addUserShare', 'url' => '/addUserShare', 'verb' => 'POST'],
        ['name' => 'page#getPublicFileShare', 'url' => '/getPublicFileShare', 'verb' => 'POST'],
        ['name' => 'page#importCsvProject', 'url' => '/importCsvProject', 'verb' => 'POST'],
        ['name' => 'page#exportCsvProject', 'url' => '/exportCsvProject', 'verb' => 'POST'],
        ['name' => 'page#exportCsvStatistics', 'url' => '/exportCsvStatistics', 'verb' => 'POST'],
        ['name' => 'page#exportCsvSettlement', 'url' => '/exportCsvSettlement', 'verb' => 'POST'],
        ['name' => 'page#deleteUserShare', 'url' => '/deleteUserShare', 'verb' => 'POST'],
        ['name' => 'page#webGetProjects', 'url' => 'getProjects', 'verb' => 'POST'],
        ['name' => 'page#webCreateProject', 'url' => 'createProject', 'verb' => 'POST'],
        ['name' => 'page#webDeleteProject', 'url' => 'deleteProject', 'verb' => 'POST'],
        ['name' => 'page#webAddMember', 'url' => 'addMember', 'verb' => 'POST'],
        ['name' => 'page#webEditMember', 'url' => 'editMember', 'verb' => 'POST'],
        ['name' => 'page#webEditProject', 'url' => 'editProject', 'verb' => 'POST'],
        ['name' => 'page#webGetBills', 'url' => 'getBills', 'verb' => 'POST'],
        ['name' => 'page#webGetProjectInfo', 'url' => 'getProjectInfo', 'verb' => 'POST'],
        ['name' => 'page#webAddBill', 'url' => 'addBill', 'verb' => 'POST'],
        ['name' => 'page#webEditBill', 'url' => 'editBill', 'verb' => 'POST'],
        ['name' => 'page#webDeleteBill', 'url' => 'deleteBill', 'verb' => 'POST'],
        ['name' => 'page#webGetProjectStatistics', 'url' => 'getStatistics', 'verb' => 'POST'],
        ['name' => 'page#webGetProjectSettlement', 'url' => 'getSettlement', 'verb' => 'POST'],
        ['name' => 'page#pubLoginProject', 'url' => 'loginproject/{projectid}', 'verb' => 'GET'],
        ['name' => 'page#pubLogin', 'url' => 'login', 'verb' => 'GET'],
        ['name' => 'page#pubProject', 'url' => 'project', 'verb' => 'POST'],
    ]
];
