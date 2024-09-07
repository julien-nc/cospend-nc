import cospend from './state.js'
import * as constants from './constants.js'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

export function getOptionValues() {
	const url = generateUrl('/apps/cospend/option-values')
	const req = {}
	return axios.get(url, req)
}

export function saveOptionValues(optionValues) {
	if (!cospend.pageIsPublic) {
		const req = {
			options: optionValues,
		}
		console.debug('save', optionValues)
		const url = generateUrl('/apps/cospend/option-values')
		axios.put(url, req)
			.then((response) => {
			})
			.catch((error) => {
				showError(t('cospend', 'Failed to save option values'))
				console.error(error)
			})
	}
}

export function exportProject(filename, projectId, projectName) {
	const req = {
		params: {
			name: filename,
		},
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/export-csv-project', { projectId })

	axios.get(url, req)
		.then((response) => {
			showSuccess(t('cospend', 'Project {name} exported in {path}', { name: projectName, path: response.data.ocs.data.path }))
		})
		.catch((error) => {
			showError(t('cospend', 'Failed to export project'))
			console.error(error)
		})
}

export function getLocalProjects() {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects')
	return axios.get(url)
}

export function getFederatedProjectIds() {
	const url = generateOcsUrl('/apps/cospend/api/v1/federated-projects')
	return axios.get(url)
}

export function getBills(
	projectId, offset, limit,
	payerId = null, categoryId = null, paymentModeId = null,
	includeBillId = null, searchTerm = null, deleted = false,
) {
	const req = {
		params: {
			offset,
			limit,
			reverse: true,
			payerId,
			categoryId,
			paymentModeId,
			includeBillId,
			searchTerm,
			deleted: deleted ? 1 : 0,
		},
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills', { projectId })
	return axios.get(url, req)
}

export function createProject(name, id) {
	const req = {
		id,
		name,
		password: null,
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/projects')
	return axios.post(url, req)
}

export function deleteProject(projectId) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/' + projectId)
	} else {
		url = generateOcsUrl('/apps/cospend/api/v1/public/projects/' + cospend.projectid + '/' + cospend.password)
	}
	return axios.delete(url)
}

export function getProjectInfo(projectId) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}', { projectId })
	return axios.get(url)
}

export function createMember(projectId, name, userId) {
	const req = {
		name,
	}
	if (userId !== null) {
		req.userId = userId
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/members', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/members', { projectId })
	return axios.post(url, req)
}

export function editMember(projectId, member) {
	const memberId = member.id
	const req = {
		name: member.name,
		weight: member.weight,
		activated: member.activated ? 'true' : 'false',
		color: member.color,
		userId: (member.userid === null) ? '' : member.userid,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/members/{memberId}', { projectId: cospend.projectid, password: cospend.password, memberId })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/members/{memberId}', { projectId, memberId })
	return axios.put(url, req)
}

export function editProject(project, password) {
	const projectId = project.id
	const req = {
		name: project.name,
		autoExport: project.autoexport,
		currencyName: project.currencyname,
		deletionDisabled: project.deletiondisabled,
		categorySort: project.categorysort,
		paymentModeSort: project.paymentmodesort,
		archivedTs: project.archived_ts,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}', { projectId })
	return axios.put(url, req)
}

export function repeatBill(projectId, billId) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills/{billId}/repeat', { projectId: cospend.projectid, password: cospend.password, billId })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills/{billId}/repeat', { projectId, billId })
	return axios.get(url)
}

export function editBill(projectId, bill) {
	const req = {
		what: bill.what,
		comment: bill.comment,
		timestamp: bill.timestamp,
		payer: bill.payer_id,
		payedFor: bill.owerIds.join(','),
		amount: bill.amount,
		repeat: bill.repeat,
		repeatAllActive: bill.repeatallactive ? 1 : 0,
		repeatUntil: bill.repeatuntil,
		repeatFreq: bill.repeatfreq ? bill.repeatfreq : 1,
		paymentModeId: bill.paymentmodeid,
		categoryId: bill.categoryid,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills/{billId}', { projectId: cospend.projectid, password: cospend.password, billId: bill.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills/{billId}', { projectId, billId: bill.id })
	return axios.put(url, req)
}

export function editBills(projectId, billIds, categoryId, paymentModeId) {
	const req = {
		what: null,
		comment: null,
		timestamp: null,
		payer: null,
		payedFor: null,
		amount: null,
		repeat: null,
		repeatAllActive: null,
		repeatUntil: null,
		paymentModeId,
		categoryId,
		billIds,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills', { projectId })
	return axios.put(url, req)
}

export function restoreBill(projectId, bill) {
	const req = {
		deleted: 0,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills/{billId}', { projectId: cospend.projectid, password: cospend.password, billId: bill.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills/{billId}', { projectId, billId: bill.id })
	return axios.put(url, req)
}

export function restoreBills(projectId, billIds) {
	const req = {
		deleted: 0,
		billIds,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills', { projectId })
	return axios.put(url, req)
}

export function createBill(projectId, req) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills', { projectId })
	return axios.post(url, req)
}

export function moveBill(fromProjectId, billId, toProjectId) {
	const req = { toProjectId }
	const url = generateOcsUrl('/apps/cospend/api/v1/projects/{fromProjectId}/bills/{billId}/move', { fromProjectId, billId })

	return axios.post(url, req)
}

export function generatePublicLinkToFile(targetPath) {
	const req = {
		path: targetPath,
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/public-file-share')
	return axios.post(url, req)
}

export function clearTrashBin(projectId) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/trash-bin', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/trash-bin', { projectId })
	return axios.delete(url)
}

export function deleteBill(projectId, bill) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills/{billId}', { projectId: cospend.projectid, password: cospend.password, billId: bill.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills/{billId}', { projectId, billId: bill.id })
	return axios.delete(url)
}

export function deleteBills(projectId, billIds) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/bills', { projectId })
	} else {
		url = generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/bills', { projectId: cospend.projectid, password: cospend.password })
	}
	const req = {
		params: {
			billIds,
		},
	}
	return axios.delete(url, req)
}

export function importProject(targetPath, isSplitWise) {
	const req = {
		params: {
			path: targetPath,
		},
	}
	let url
	if (isSplitWise) {
		url = generateOcsUrl('/apps/cospend/api/v1/import-sw-project')
	} else {
		url = generateOcsUrl('/apps/cospend/api/v1/import-csv-project')
	}
	return axios.get(url, req)
}

export function createCategory(projectId, name, icon, color, order) {
	const req = {
		name,
		icon,
		color,
		order,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/category', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/category', { projectId })
	return axios.post(url, req)
}

export function createPaymentMode(projectId, name, icon, color, order) {
	const req = {
		name,
		icon,
		color,
		order,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/paymentmode', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/paymentmode', { projectId })
	return axios.post(url, req)
}

export function deleteCategory(projectId, categoryId) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/category/{categoryId}', { projectId: cospend.projectid, password: cospend.password, categoryId })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/category/{categoryId}', { projectId, categoryId })
	return axios.delete(url)
}

export function deletePaymentMode(projectId, pmId) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/paymentmode/{pmId}', { projectId: cospend.projectid, password: cospend.password, pmId })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/paymentmode/{pmId}', { projectId, pmId })
	return axios.delete(url)
}

export function editCategory(projectId, category, backupCategory) {
	const req = {
		name: category.name,
		icon: category.icon,
		color: category.color,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/category/{categoryId}', { projectId: cospend.projectid, password: cospend.password, categoryId: category.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/category/{categoryId}', { projectId, categoryId: category.id })
	return axios.put(url, req)
}

export function editPaymentMode(projectId, pm, backupPm) {
	const req = {
		name: pm.name,
		icon: pm.icon,
		color: pm.color,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/paymentmode/{pmId}', { projectId: cospend.projectid, password: cospend.password, pmId: pm.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/paymentmode/{pmId}', { projectId, pmId: pm.id })
	return axios.put(url, req)
}

export function saveCategoryOrder(projectId, order) {
	const req = {
		order,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/category-order', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/category-order', { projectId })
	return axios.put(url, req)
}

export function savePaymentModeOrder(projectId, order) {
	const req = {
		order,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/paymentmode-order', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/paymentmode-order', { projectId })
	return axios.put(url, req)
}

export function createCurrency(projectId, name, rate, successCB) {
	const req = {
		name,
		rate,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/currency', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/currency', { projectId })
	axios.post(url, req)
		.then((response) => {
			successCB(response.data.ocs.data, name, rate)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add currency')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
		})
}

export function deleteCurrency(projectId, currency, successCB) {
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/currency/{currencyId}', { projectId: cospend.projectid, password: cospend.password, currencyId: currency.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/currency/{currencyId}', { projectId, currencyId: currency.id })
	axios.delete(url)
		.then((response) => {
			successCB(currency)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete currency')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
		})
}

export function editCurrency(projectId, currency, backupCurrency, failCB) {
	const req = {
		name: currency.name,
		rate: currency.exchange_rate,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/currency/{currencyId}', { projectId: cospend.projectid, password: cospend.password, currencyId: currency.id })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/currency/{currencyId}', { projectId, currencyId: currency.id })
	axios.put(url, req)
		.then((response) => {
		})
		.catch((error) => {
			failCB(currency, backupCurrency)
			showError(
				t('cospend', 'Failed to edit currency')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
		})
}

export function getStats(projectId, params, isFiltered, successCB, doneCB) {
	const req = {
		params,
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/statistics', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/statistics', { projectId })
	axios.get(url, req)
		.then((response) => {
			successCB(response.data.ocs.data, isFiltered)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to get statistics')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
			console.debug(error)
		})
		.then(() => {
			doneCB()
		})
}

export function exportStats(projectId, params, doneCB) {
	const req = {
		params,
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/export-csv-statistics', { projectId })
	axios.get(url, req)
		.then((response) => {
			showSuccess(t('cospend', 'Project statistics exported in {path}', { path: response.data.ocs.data.path }))
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to export project statistics')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
		})
		.then(() => {
			doneCB()
		})
}

export function getSettlement(projectId, centeredOn, maxTimestamp) {
	if (parseInt(centeredOn) === 0) {
		centeredOn = null
	}
	const req = {
		params: {
			centeredOn,
			maxTimestamp,
		},
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/settlement', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/settlement', { projectId })
	return axios.get(url, req)
}

export function autoSettlement(projectId, centeredOn, maxTimestamp, precision, successCB) {
	const req = {
		params: {
			centeredOn: (parseInt(centeredOn) === 0) ? null : centeredOn,
			precision,
			maxTimestamp,
		},
	}
	const url = cospend.pageIsPublic
		? generateOcsUrl('/apps/cospend/api/v1/public/projects/{projectId}/{password}/auto-settlement', { projectId: cospend.projectid, password: cospend.password })
		: generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/auto-settlement', { projectId })
	axios.get(url, req)
		.then((response) => {
			successCB()
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add project settlement bills')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
		})
}

export function exportSettlement(projectId, centeredOn, maxTimestamp, successCB) {
	const req = {
		params: {
			centeredOn: (parseInt(centeredOn) === 0) ? null : centeredOn,
			maxTimestamp,
		},
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/export-csv-settlement', { projectId })
	axios.get(url, req)
		.then((response) => {
			successCB(response.data.ocs.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to export project settlement')
				+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
			)
		})
}

export function createSharedAccess(projectId, sh) {
	const req = {
		accessLevel: sh.accesslevel || constants.ACCESS.PARTICIPANT,
		manuallyAdded: sh.manually_added,
	}
	let url
	if (sh.type === constants.SHARE_TYPE.USER) {
		req.userId = sh.user
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/user-share', { projectId })
	} else if (sh.type === constants.SHARE_TYPE.GROUP) {
		req.groupId = sh.user
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/group-share', { projectId })
	} else if (sh.type === constants.SHARE_TYPE.CIRCLE) {
		req.circleId = sh.user
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/circle-share', { projectId })
	} else if (sh.type === constants.SHARE_TYPE.PUBLIC_LINK) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/public-share', { projectId })
	} else if (sh.type === constants.SHARE_TYPE.FEDERATED) {
		req.userCloudId = sh.user
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/federated-share', { projectId })
	}
	return axios.post(url, req)
}

export function setSharedAccessLevel(projectId, access, accessLevel) {
	const req = {
		accessLevel,
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/share-access-level/{shId}', { projectId, shId: access.id })
	return axios.put(url, req)
}

export function editSharedAccess(projectId, access, label, password) {
	const req = {
		label,
		password,
	}
	const url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/share-access/{shId}', { projectId, shId: access.id })
	return axios.put(url, req)
}

export function deleteSharedAccess(projectId, access) {
	const shId = access.id
	let url
	if (access.type === constants.SHARE_TYPE.USER) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/user-share/{shId}', { projectId, shId })
	} else if (access.type === constants.SHARE_TYPE.GROUP) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/group-share/{shId}', { projectId, shId })
	} else if (access.type === constants.SHARE_TYPE.CIRCLE) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/circle-share/{shId}', { projectId, shId })
	} else if (access.type === constants.SHARE_TYPE.PUBLIC_LINK) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/public-share/{shId}', { projectId, shId })
	} else if (access.type === constants.SHARE_TYPE.FEDERATED) {
		url = generateOcsUrl('/apps/cospend/api/v1/projects/{projectId}/federated-share/{shId}', { projectId, shId })
	}
	return axios.delete(url)
}

export function getPendingInvitations() {
	const url = generateOcsUrl('/apps/cospend/api/v1/federation/pending-invitations')
	return axios.get(url)
}

export function acceptPendingInvitation(invitationId) {
	const req = {}
	const url = generateOcsUrl('/apps/cospend/api/v1/federation/invitation/{invitationId}', { invitationId })
	return axios.post(url, req)
}

export function rejectPendingInvitation(invitationId) {
	const req = {}
	const url = generateOcsUrl('/apps/cospend/api/v1/federation/invitation/{invitationId}', { invitationId })
	return axios.delete(url, req)
}
