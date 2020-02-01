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

        // api for client using guest access (password)
        [
            'name'         => 'page#preflighted_cors',
            'url'          => '/api/{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        [
            'name'         => 'page#preflighted_cors',
            'url'          => '/apiv2/{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        ['name' => 'page#apiPing', 'url' => '/api/ping', 'verb' => 'GET'],
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
        ['name' => 'page#apiv2GetBills', 'url' => '/apiv2/projects/{projectid}/{password}/bills', 'verb' => 'GET'],
        ['name' => 'page#apiGetProjectStatistics', 'url' => '/api/projects/{projectid}/{password}/statistics', 'verb' => 'GET'],
        ['name' => 'page#apiGetProjectSettlement', 'url' => '/api/projects/{projectid}/{password}/settle', 'verb' => 'GET'],
        ['name' => 'page#apiAutoSettlement', 'url' => '/api/projects/{projectid}/{password}/autosettlement', 'verb' => 'GET'],
        ['name' => 'page#apiAddCurrency', 'url' => '/api/projects/{projectid}/{password}/currency', 'verb' => 'POST'],
        ['name' => 'page#apiEditCurrency', 'url' => '/api/projects/{projectid}/{password}/currency/{currencyid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteCurrency', 'url' => '/api/projects/{projectid}/{password}/currency/{currencyid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiAddCategory', 'url' => '/api/projects/{projectid}/{password}/category', 'verb' => 'POST'],
        ['name' => 'page#apiEditCategory', 'url' => '/api/projects/{projectid}/{password}/category/{categoryid}', 'verb' => 'PUT'],
        ['name' => 'page#apiDeleteCategory', 'url' => '/api/projects/{projectid}/{password}/category/{categoryid}', 'verb' => 'DELETE'],

        // api for logged in clients
        [
            'name'         => 'page#preflighted_cors',
            'url'          => '/api-priv/{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        ['name' => 'page#apiPrivGetProjectInfo', 'url' => '/api-priv/projects/{projectid}', 'verb' => 'GET'],
        ['name' => 'page#apiPrivSetProjectInfo', 'url' => '/api-priv/projects/{projectid}', 'verb' => 'PUT'],
        ['name' => 'page#apiPrivDeleteProject', 'url' => '/api-priv/projects/{projectid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiPrivGetMembers', 'url' => '/api-priv/projects/{projectid}/members', 'verb' => 'GET'],
        ['name' => 'page#apiPrivAddMember', 'url' => '/api-priv/projects/{projectid}/members', 'verb' => 'POST'],
        ['name' => 'page#apiPrivEditMember', 'url' => '/api-priv/projects/{projectid}/members/{memberid}', 'verb' => 'PUT'],
        ['name' => 'page#apiPrivDeleteMember', 'url' => '/api-priv/projects/{projectid}/members/{memberid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiPrivGetBills', 'url' => '/api-priv/projects/{projectid}/bills', 'verb' => 'GET'],
        ['name' => 'page#apiPrivAddBill', 'url' => '/api-priv/projects/{projectid}/bills', 'verb' => 'POST'],
        ['name' => 'page#apiPrivEditBill', 'url' => '/api-priv/projects/{projectid}/bills/{billid}', 'verb' => 'PUT'],
        ['name' => 'page#apiPrivDeleteBill', 'url' => '/api-priv/projects/{projectid}/bills/{billid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiPrivGetProjectStatistics', 'url' => '/api-priv/projects/{projectid}/statistics', 'verb' => 'GET'],
        ['name' => 'page#apiPrivGetProjectSettlement', 'url' => '/api-priv/projects/{projectid}/settle', 'verb' => 'GET'],
        ['name' => 'page#apiPrivAutoSettlement', 'url' => '/api-priv/projects/{projectid}/autosettlement', 'verb' => 'GET'],
        ['name' => 'page#apiPrivAddCurrency', 'url' => '/api-priv/projects/{projectid}/currency', 'verb' => 'POST'],
        ['name' => 'page#apiPrivEditCurrency', 'url' => '/api-priv/projects/{projectid}/currency/{currencyid}', 'verb' => 'PUT'],
        ['name' => 'page#apiPrivDeleteCurrency', 'url' => '/api-priv/projects/{projectid}/currency/{currencyid}', 'verb' => 'DELETE'],
        ['name' => 'page#apiPrivAddCategory', 'url' => '/api-priv/projects/{projectid}/category', 'verb' => 'POST'],
        ['name' => 'page#apiPrivEditCategory', 'url' => '/api-priv/projects/{projectid}/category/{categoryid}', 'verb' => 'PUT'],
        ['name' => 'page#apiPrivDeleteCategory', 'url' => '/api-priv/projects/{projectid}/category/{categoryid}', 'verb' => 'DELETE'],

        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'POST'],
        ['name' => 'utils#setAllowAnonymousCreation', 'url' => '/setAllowAnonymousCreation', 'verb' => 'POST'],
        ['name' => 'page#getUserList', 'url' => '/getUserList', 'verb' => 'POST'],
        ['name' => 'page#addCurrency', 'url' => '/addCurrency', 'verb' => 'POST'],
        ['name' => 'page#editCurrency', 'url' => '/editCurrency', 'verb' => 'POST'],
        ['name' => 'page#deleteCurrency', 'url' => '/deleteCurrency', 'verb' => 'POST'],
        ['name' => 'page#addCategory', 'url' => '/addCategory', 'verb' => 'POST'],
        ['name' => 'page#editCategory', 'url' => '/editCategory', 'verb' => 'POST'],
        ['name' => 'page#deleteCategory', 'url' => '/deleteCategory', 'verb' => 'POST'],
        ['name' => 'page#addUserShare', 'url' => '/addUserShare', 'verb' => 'POST'],
        ['name' => 'page#addGroupShare', 'url' => '/addGroupShare', 'verb' => 'POST'],
        ['name' => 'page#addCircleShare', 'url' => '/addCircleShare', 'verb' => 'POST'],
        ['name' => 'page#editSharePermissions', 'url' => '/editSharePermissions', 'verb' => 'POST'],
        ['name' => 'page#editGuestPermissions', 'url' => '/editGuestPermissions', 'verb' => 'POST'],
        ['name' => 'page#getPublicFileShare', 'url' => '/getPublicFileShare', 'verb' => 'POST'],
        ['name' => 'page#importCsvProject', 'url' => '/importCsvProject', 'verb' => 'POST'],
        ['name' => 'page#importSWProject', 'url' => '/importSWProject', 'verb' => 'POST'],
        ['name' => 'page#exportCsvProject', 'url' => '/exportCsvProject', 'verb' => 'POST'],
        ['name' => 'page#exportCsvStatistics', 'url' => '/exportCsvStatistics', 'verb' => 'POST'],
        ['name' => 'page#exportCsvSettlement', 'url' => '/exportCsvSettlement', 'verb' => 'POST'],
        ['name' => 'page#deleteUserShare', 'url' => '/deleteUserShare', 'verb' => 'POST'],
        ['name' => 'page#deleteGroupShare', 'url' => '/deleteGroupShare', 'verb' => 'POST'],
        ['name' => 'page#deleteCircleShare', 'url' => '/deleteCircleShare', 'verb' => 'POST'],
        ['name' => 'page#webGetProjects', 'url' => 'getProjects', 'verb' => 'POST'],
        ['name' => 'page#webCreateProject', 'url' => 'createProject', 'verb' => 'POST'],
        ['name' => 'page#webDeleteProject', 'url' => 'deleteProject', 'verb' => 'POST'],
        ['name' => 'page#webAddExternalProject', 'url' => 'addExternalProject', 'verb' => 'POST'],
        ['name' => 'page#webAddMember', 'url' => 'addMember', 'verb' => 'POST'],
        ['name' => 'page#webEditMember', 'url' => 'editMember', 'verb' => 'POST'],
        ['name' => 'page#webEditProject', 'url' => 'editProject', 'verb' => 'POST'],
        ['name' => 'page#webEditExternalProject', 'url' => 'editExternalProject', 'verb' => 'POST'],
        ['name' => 'page#webDeleteExternalProject', 'url' => 'deleteExternalProject', 'verb' => 'POST'],
        ['name' => 'page#webGetBills', 'url' => 'getBills', 'verb' => 'POST'],
        ['name' => 'page#webGetProjectInfo', 'url' => 'getProjectInfo', 'verb' => 'POST'],
        ['name' => 'page#webAddBill', 'url' => 'addBill', 'verb' => 'POST'],
        ['name' => 'page#webEditBill', 'url' => 'editBill', 'verb' => 'POST'],
        ['name' => 'page#webDeleteBill', 'url' => 'deleteBill', 'verb' => 'POST'],
        ['name' => 'page#webGetProjectStatistics', 'url' => 'getStatistics', 'verb' => 'POST'],
        ['name' => 'page#webGetProjectSettlement', 'url' => 'getSettlement', 'verb' => 'POST'],
        ['name' => 'page#webAutoSettlement', 'url' => 'autoSettlement', 'verb' => 'POST'],
        ['name' => 'page#pubLoginProjectPassword', 'url' => 'loginproject/{projectid}/{password}', 'verb' => 'GET'],
        ['name' => 'page#pubLoginProject', 'url' => 'loginproject/{projectid}', 'verb' => 'GET'],
        ['name' => 'page#pubLogin', 'url' => 'login', 'verb' => 'GET'],
        ['name' => 'page#pubProject', 'url' => 'project', 'verb' => 'POST'],

        ['name' => 'utils#getAvatar', 'url' => 'getAvatar', 'verb' => 'GET'],
    ]
];
