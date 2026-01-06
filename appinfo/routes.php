<?php

/**
 * Nextcloud - cospend
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2018
 */

$requirements = [
	'apiVersion' => '(v1)',
];

$publicRequirements = [
	'apiVersion' => '(v1)',
	'token' => '^[a-zA-Z0-9]{4,64}$',
];

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#getSvgFromApp', 'url' => '/svg/{fileName}', 'verb' => 'GET'],
		['name' => 'page#indexProject', 'url' => '/p/{projectId}', 'verb' => 'GET'],
		['name' => 'page#indexBill', 'url' => '/p/{projectId}/b/{billId}', 'verb' => 'GET'],
		['name' => 'page#pubProject', 'url' => 'project', 'verb' => 'POST'],
		['name' => 'page#publicShareLinkPage', 'url' => 's/{token}', 'verb' => 'GET'],

		['name' => 'page#getOptionsValues', 'url' => '/option-values', 'verb' => 'GET'],
		['name' => 'page#saveOptionValues', 'url' => '/option-values', 'verb' => 'PUT'],
		['name' => 'page#saveAdminOptionValues', 'url' => '/admin-option-values', 'verb' => 'PUT'],

		// OLD API for client using guest access (projectId + password) or public link (token + optional password)
		['name' => 'oldApi#preflighted_cors', 'url' => '/api/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
		['name' => 'oldApi#preflighted_cors', 'url' => '/apiv2/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],

		['name' => 'oldApi#apiGetProjectInfo', 'url' => '/api/projects/{token}/{password}', 'verb' => 'GET'],
		['name' => 'oldApi#apiSetProjectInfo', 'url' => '/api/projects/{token}/{password}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiDeleteProject', 'url' => '/api/projects/{token}/{password}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiGetMembers', 'url' => '/api/projects/{token}/{password}/members', 'verb' => 'GET'],
		['name' => 'oldApi#apiAddMember', 'url' => '/api/projects/{token}/{password}/members', 'verb' => 'POST'],
		['name' => 'oldApi#apiv2AddMember', 'url' => '/apiv2/projects/{token}/{password}/members', 'verb' => 'POST'],
		['name' => 'oldApi#apiEditMember', 'url' => '/api/projects/{token}/{password}/members/{memberid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiDeleteMember', 'url' => '/api/projects/{token}/{password}/members/{memberid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiGetBills', 'url' => '/api/projects/{token}/{password}/bills', 'verb' => 'GET'],
		['name' => 'oldApi#apiAddBill', 'url' => '/api/projects/{token}/{password}/bills', 'verb' => 'POST'],
		['name' => 'oldApi#apiRepeatBill', 'url' => '/api/projects/{token}/{password}/bills/{billId}/repeat', 'verb' => 'GET'],
		['name' => 'oldApi#apiEditBill', 'url' => '/api/projects/{token}/{password}/bills/{billid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiEditBills', 'url' => '/api/projects/{token}/{password}/bills', 'verb' => 'PUT'],
		['name' => 'oldApi#apiDeleteBill', 'url' => '/api/projects/{token}/{password}/bills/{billid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiDeleteBills', 'url' => '/api/projects/{token}/{password}/bills', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiClearTrashBin', 'url' => '/api/projects/{token}/{password}/trashbin', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiv2GetBills', 'url' => '/apiv2/projects/{token}/{password}/bills', 'verb' => 'GET'],
		['name' => 'oldApi#apiv3GetBills', 'url' => '/apiv3/projects/{token}/{password}/bills', 'verb' => 'GET'],
		['name' => 'oldApi#apiGetProjectStatistics', 'url' => '/api/projects/{token}/{password}/statistics', 'verb' => 'GET'],
		['name' => 'oldApi#apiGetProjectSettlement', 'url' => '/api/projects/{token}/{password}/settle', 'verb' => 'GET'],
		['name' => 'oldApi#apiAutoSettlement', 'url' => '/api/projects/{token}/{password}/autosettlement', 'verb' => 'GET'],
		['name' => 'oldApi#apiAddCurrency', 'url' => '/api/projects/{token}/{password}/currency', 'verb' => 'POST'],
		['name' => 'oldApi#apiEditCurrency', 'url' => '/api/projects/{token}/{password}/currency/{currencyid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiDeleteCurrency', 'url' => '/api/projects/{token}/{password}/currency/{currencyid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiAddPaymentMode', 'url' => '/api/projects/{token}/{password}/paymentmode', 'verb' => 'POST'],
		['name' => 'oldApi#apiEditPaymentMode', 'url' => '/api/projects/{token}/{password}/paymentmode/{pmid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiSavePaymentModeOrder', 'url' => '/api/projects/{token}/{password}/paymentmode-order', 'verb' => 'PUT'],
		['name' => 'oldApi#apiDeletePaymentMode', 'url' => '/api/projects/{token}/{password}/paymentmode/{pmid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiAddCategory', 'url' => '/api/projects/{token}/{password}/category', 'verb' => 'POST'],
		['name' => 'oldApi#apiEditCategory', 'url' => '/api/projects/{token}/{password}/category/{categoryid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiSaveCategoryOrder', 'url' => '/api/projects/{token}/{password}/category-order', 'verb' => 'PUT'],
		['name' => 'oldApi#apiDeleteCategory', 'url' => '/api/projects/{token}/{password}/category/{categoryid}', 'verb' => 'DELETE'],

		// OLD API for logged in clients
		['name' => 'oldApi#preflighted_cors', 'url' => '/api-priv/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],

		['name' => 'oldApi#apiPing', 'url' => '/api/ping', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivGetProjects', 'url' => '/api-priv/projects', 'verb' => 'GET'],
		// Moneybuster still uses this old endpoint...
		['name' => 'oldApi#apiPrivGetProjects2', 'url' => '/getProjects', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivCreateProject', 'url' => '/api-priv/projects', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivGetProjectInfo', 'url' => '/api-priv/projects/{projectId}', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivSetProjectInfo', 'url' => '/api-priv/projects/{projectId}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiPrivDeleteProject', 'url' => '/api-priv/projects/{projectId}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiPrivGetMembers', 'url' => '/api-priv/projects/{projectId}/members', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivAddMember', 'url' => '/api-priv/projects/{projectId}/members', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivEditMember', 'url' => '/api-priv/projects/{projectId}/members/{memberid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiPrivDeleteMember', 'url' => '/api-priv/projects/{projectId}/members/{memberid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiPrivGetBills', 'url' => '/api-priv/projects/{projectId}/bills', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivAddBill', 'url' => '/api-priv/projects/{projectId}/bills', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivEditBill', 'url' => '/api-priv/projects/{projectId}/bills/{billid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiPrivDeleteBill', 'url' => '/api-priv/projects/{projectId}/bills/{billid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiPrivClearTrashBin', 'url' => '/api-priv/projects/{projectId}/trashbin', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiPrivGetProjectStatistics', 'url' => '/api-priv/projects/{projectId}/statistics', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivGetProjectSettlement', 'url' => '/api-priv/projects/{projectId}/settle', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivAutoSettlement', 'url' => '/api-priv/projects/{projectId}/autosettlement', 'verb' => 'GET'],
		['name' => 'oldApi#apiPrivAddCurrency', 'url' => '/api-priv/projects/{projectId}/currency', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivEditCurrency', 'url' => '/api-priv/projects/{projectId}/currency/{currencyid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiPrivDeleteCurrency', 'url' => '/api-priv/projects/{projectId}/currency/{currencyid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiPrivAddPaymentMode', 'url' => '/api-priv/projects/{projectId}/paymentmode', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivEditPaymentMode', 'url' => '/api-priv/projects/{projectId}/paymentmode/{pmid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiPrivDeletePaymentMode', 'url' => '/api-priv/projects/{projectId}/paymentmode/{pmid}', 'verb' => 'DELETE'],
		['name' => 'oldApi#apiPrivAddCategory', 'url' => '/api-priv/projects/{projectId}/category', 'verb' => 'POST'],
		['name' => 'oldApi#apiPrivEditCategory', 'url' => '/api-priv/projects/{projectId}/category/{categoryid}', 'verb' => 'PUT'],
		['name' => 'oldApi#apiPrivDeleteCategory', 'url' => '/api-priv/projects/{projectId}/category/{categoryid}', 'verb' => 'DELETE'],
	],
	// NEW API: all OCS
	// - same API for logged in web interface and logged in clients
	// - same API for public access (share link) with web page or clients
	'ocs' => [
		['name' => 'api#ping', 'url' => '/api/{apiVersion}/ping', 'verb' => 'GET', 'requirements' => $requirements],
		// federation
		['name' => 'federation#getRemoteUserAvatar', 'url' => '/api/{apiVersion}/remote/avatar/{size}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'federation#getPendingShares', 'url' => '/api/{apiVersion}/federation/pending-invitations', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'federation#acceptShare', 'url' => '/api/{apiVersion}/federation/invitation/{id}', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'federation#rejectShare', 'url' => '/api/{apiVersion}/federation/invitation/{id}', 'verb' => 'DELETE', 'requirements' => $requirements],
		// projects
		['name' => 'api#getLocalProjects', 'url' => '/api/{apiVersion}/projects', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#getFederatedProjects', 'url' => '/api/{apiVersion}/federated-projects', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#createProject', 'url' => '/api/{apiVersion}/projects', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#deleteProject', 'url' => '/api/{apiVersion}/projects/{projectId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#editProject', 'url' => '/api/{apiVersion}/projects/{projectId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#getProjectInfo', 'url' => '/api/{apiVersion}/projects/{projectId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#exportCsvProject', 'url' => '/api/{apiVersion}/projects/{projectId}/export-csv-project', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#importCsvProject', 'url' => '/api/{apiVersion}/import-csv-project', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#importSWProject', 'url' => '/api/{apiVersion}/import-sw-project', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#getProjectStatistics', 'url' => '/api/{apiVersion}/projects/{projectId}/statistics', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#exportCsvStatistics', 'url' => '/api/{apiVersion}/projects/{projectId}/export-csv-statistics', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#getProjectSettlement', 'url' => '/api/{apiVersion}/projects/{projectId}/settlement', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#autoSettlement', 'url' => '/api/{apiVersion}/projects/{projectId}/auto-settlement', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#exportCsvSettlement', 'url' => '/api/{apiVersion}/projects/{projectId}/export-csv-settlement', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#getPublicFileShare', 'url' => '/api/{apiVersion}/public-file-share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'publicApi#publicDeleteProject', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditProject', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicGetProjectInfo', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicGetProjectStatistics', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/statistics', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicGetProjectSettlement', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/settlement', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicAutoSettlement', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/auto-settlement', 'verb' => 'GET', 'requirements' => $publicRequirements],
		// bills
		['name' => 'api#createBill', 'url' => '/api/{apiVersion}/projects/{projectId}/bills', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#getBill', 'url' => '/api/{apiVersion}/projects/{projectId}/bills/{billId}', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#getBills', 'url' => '/api/{apiVersion}/projects/{projectId}/bills', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#ClearTrashBin', 'url' => '/api/{apiVersion}/projects/{projectId}/trash-bin', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#deleteBill', 'url' => '/api/{apiVersion}/projects/{projectId}/bills/{billId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#deleteBills', 'url' => '/api/{apiVersion}/projects/{projectId}/bills', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#editBill', 'url' => '/api/{apiVersion}/projects/{projectId}/bills/{billId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#editBills', 'url' => '/api/{apiVersion}/projects/{projectId}/bills', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#moveBill', 'url' => '/api/{apiVersion}/projects/{projectId}/bills/{billId}/move', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#repeatBill', 'url' => '/api/{apiVersion}/projects/{projectId}/bills/{billId}/repeat', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'publicApi#publicCreateBill', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills', 'verb' => 'POST', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicRepeatBill', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills/{billId}/repeat', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicGetBill', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills/{billId}', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicGetBills', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicClearTrashBin', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/trash-bin', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicDeleteBill', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills/{billId}', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicDeleteBills', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditBill', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills/{billId}', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditBills', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/bills', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		// members
		['name' => 'api#getMembers', 'url' => '/api/{apiVersion}/projects/{projectId}/members', 'verb' => 'GET', 'requirements' => $requirements],
		['name' => 'api#createMember', 'url' => '/api/{apiVersion}/projects/{projectId}/members', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#editMember', 'url' => '/api/{apiVersion}/projects/{projectId}/members/{memberId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#deleteMember', 'url' => '/api/{apiVersion}/projects/{projectId}/members/{memberId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'publicApi#publicGetMembers', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/members', 'verb' => 'GET', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicCreateMember', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/members', 'verb' => 'POST', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditMember', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/members/{memberId}', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicDeleteMember', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/members/{memberId}', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		// shares
		['name' => 'api#createUserShare', 'url' => '/api/{apiVersion}/projects/{projectId}/user-share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#deleteUserShare', 'url' => '/api/{apiVersion}/projects/{projectId}/user-share/{shId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#createGroupShare', 'url' => '/api/{apiVersion}/projects/{projectId}/group-share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#deleteGroupShare', 'url' => '/api/{apiVersion}/projects/{projectId}/group-share/{shId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#createCircleShare', 'url' => '/api/{apiVersion}/projects/{projectId}/circle-share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#deleteCircleShare', 'url' => '/api/{apiVersion}/projects/{projectId}/circle-share/{shId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#createPublicShare', 'url' => '/api/{apiVersion}/projects/{projectId}/public-share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#deletePublicShare', 'url' => '/api/{apiVersion}/projects/{projectId}/public-share/{shId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#createFederatedShare', 'url' => '/api/{apiVersion}/projects/{projectId}/federated-share', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#deleteFederatedShare', 'url' => '/api/{apiVersion}/projects/{projectId}/federated-share/{shId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'api#editSharedAccessLevel', 'url' => '/api/{apiVersion}/projects/{projectId}/share-access-level/{shId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#editSharedAccess', 'url' => '/api/{apiVersion}/projects/{projectId}/share-access/{shId}', 'verb' => 'PUT', 'requirements' => $requirements],
		// currencies
		['name' => 'api#createCurrency', 'url' => '/api/{apiVersion}/projects/{projectId}/currency', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#editCurrency', 'url' => '/api/{apiVersion}/projects/{projectId}/currency/{currencyId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#deleteCurrency', 'url' => '/api/{apiVersion}/projects/{projectId}/currency/{currencyId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'publicApi#publicCreateCurrency', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/currency', 'verb' => 'POST', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditCurrency', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/currency/{currencyId}', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicDeleteCurrency', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/currency/{currencyId}', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		// payment modes
		['name' => 'api#createPaymentMode', 'url' => '/api/{apiVersion}/projects/{projectId}/paymentmode', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#editPaymentMode', 'url' => '/api/{apiVersion}/projects/{projectId}/paymentmode/{pmId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#savePaymentModeOrder', 'url' => '/api/{apiVersion}/projects/{projectId}/paymentmode-order', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#deletePaymentMode', 'url' => '/api/{apiVersion}/projects/{projectId}/paymentmode/{pmId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'publicApi#publicCreatePaymentMode', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/paymentmode', 'verb' => 'POST', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditPaymentMode', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/paymentmode/{pmId}', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicSavePaymentModeOrder', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/paymentmode-order', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicDeletePaymentMode', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/paymentmode/{pmId}', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
		// categories
		['name' => 'api#createCategory', 'url' => '/api/{apiVersion}/projects/{projectId}/category', 'verb' => 'POST', 'requirements' => $requirements],
		['name' => 'api#editCategory', 'url' => '/api/{apiVersion}/projects/{projectId}/category/{categoryId}', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#saveCategoryOrder', 'url' => '/api/{apiVersion}/projects/{projectId}/category-order', 'verb' => 'PUT', 'requirements' => $requirements],
		['name' => 'api#deleteCategory', 'url' => '/api/{apiVersion}/projects/{projectId}/category/{categoryId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		['name' => 'publicApi#publicCreateCategory', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/category', 'verb' => 'POST', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicEditCategory', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/category/{categoryId}', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicSaveCategoryOrder', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/category-order', 'verb' => 'PUT', 'requirements' => $publicRequirements],
		['name' => 'publicApi#publicDeleteCategory', 'url' => '/api/{apiVersion}/public/projects/{token}/{password}/category/{categoryId}', 'verb' => 'DELETE', 'requirements' => $publicRequirements],
	],
];
