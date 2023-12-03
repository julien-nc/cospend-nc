import cospend from './state.js'
import * as constants from './constants.js'
import { generateUrl } from '@nextcloud/router'
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

export function saveOptionValue(optionValues) {
	if (!cospend.pageIsPublic) {
		const req = {
			options: optionValues,
		}
		const url = generateUrl('/apps/cospend/option-value')
		axios.put(url, req)
			.then((response) => {
			})
			.catch((error) => {
				showError(
					t('cospend', 'Failed to save option values')
					+ ': ' + error.response.request.responseText,
				)
			})
	}
}

export function setAllowAnonymousCreation(val) {
	const url = generateUrl('/apps/cospend/allow-anonymous-creation')
	const req = {
		allow: val,
	}
	axios.put(url, req)
		.then((response) => {
			showSuccess(
				t('cospend', 'Cospend setting saved.'),
			)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to save Cospend setting.'),
			)
			console.debug(error)
		})
}

export function exportProject(filename, projectid, projectName) {
	const req = {
		params: {
			name: filename,
		},
	}
	const url = generateUrl('/apps/cospend/export-csv-project/' + projectid)

	axios.get(url, req)
		.then((response) => {
			showSuccess(t('cospend', 'Project {name} exported in {path}', { name: projectName, path: response.data.path }))
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to export project')
				+ ': ' + error.response.request.responseText,
			)
		})
}

export function getProjects() {
	const req = {}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	return axios.get(url, req)
}

export function getBills(
	projectid, offset, limit,
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
		? generateUrl('/apps/cospend/apiv3/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
		: generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	return axios.get(url, req)
}

export function createProject(name, id) {
	const req = {
		id,
		name,
		password: null,
	}
	const url = generateUrl('/apps/cospend/projects')
	return axios.post(url, req)
}

export function deleteProject(projectid) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	return axios.delete(url)
}

export function updateProjectInfo(projectid) {
	const req = {}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	return axios.get(url, req)
}

export function createMember(projectid, name, userid) {
	const req = {
		name,
	}
	if (userid !== null) {
		req.userid = userid
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/members')
	} else {
		url = generateUrl('/apps/cospend/apiv2/projects/' + cospend.projectid + '/' + cospend.password + '/members')
	}
	return axios.post(url, req)
}

export function editMember(projectid, member) {
	const memberid = member.id
	const req = {
		name: member.name,
		weight: member.weight,
		activated: member.activated ? 'true' : 'false',
		color: member.color,
		userid: (member.userid === null) ? '' : member.userid,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/members/' + memberid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/members/' + memberid)
	}
	return axios.put(url, req)
}

export function editProject(project, password) {
	const projectid = project.id
	const req = {
		name: project.name,
		contact_email: null,
		password,
		autoexport: project.autoexport,
		currencyname: project.currencyname,
		deletion_disabled: project.deletiondisabled,
		categorysort: project.categorysort,
		paymentmodesort: project.paymentmodesort,
		archived_ts: project.archived_ts,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
		: generateUrl('/apps/cospend/projects/' + projectid)
	return axios.put(url, req)
}

export function repeatBillNow(projectId, billId) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + billId + '/repeat')
		: generateUrl('/apps/cospend/projects/' + projectId + '/bills/' + billId + '/repeat')
	return axios.get(url)
}

export function saveBill(projectId, bill) {
	const req = {
		what: bill.what,
		comment: bill.comment,
		timestamp: bill.timestamp,
		payer: bill.payer_id,
		payed_for: bill.owerIds.join(','),
		amount: bill.amount,
		repeat: bill.repeat,
		repeatallactive: bill.repeatallactive ? 1 : 0,
		repeatuntil: bill.repeatuntil,
		repeatfreq: bill.repeatfreq ? bill.repeatfreq : 1,
		paymentmodeid: bill.paymentmodeid,
		categoryid: bill.categoryid,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectId + '/bills/' + bill.id)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id)
	}
	return axios.put(url, req)
}

export function restoreBill(projectId, bill) {
	const req = {
		deleted: 0,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id)
		: generateUrl('/apps/cospend/projects/' + projectId + '/bills/' + bill.id)
	return axios.put(url, req)
}

export function saveBills(projectid, billIds, categoryid, paymentmodeid) {
	const req = {
		what: null,
		comment: null,
		timestamp: null,
		payer: null,
		payed_for: null,
		amount: null,
		repeat: null,
		repeatallactive: null,
		repeatuntil: null,
		paymentmodeid,
		categoryid,
		billIds,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
	}
	return axios.put(url, req)
}

export function restoreBills(projectid, billIds) {
	const req = {
		deleted: 0,
		billIds,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
		: generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	return axios.put(url, req)
}

export function createBill(projectid, req) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
		: generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	return axios.post(url, req)
}

export function moveBill(fromProjectId, billId, toProjectId) {
	const req = { toProjectId }
	const url = generateUrl('/apps/cospend/projects/' + fromProjectId + '/bills/' + billId + '/move')

	return axios.post(url, req)
}

export function generatePublicLinkToFile(targetPath) {
	const req = {
		path: targetPath,
	}
	const url = generateUrl('/apps/cospend/getPublicFileShare')
	return axios.post(url, req)
}

export function clearTrashbin(projectId) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/trashbin')
		: generateUrl('/apps/cospend/projects/' + projectId + '/trashbin')
	return axios.delete(url)
}

export function deleteBill(projectid, bill) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id)
		: generateUrl('/apps/cospend/projects/' + projectid + '/bills/' + bill.id)
	return axios.delete(url)
}

export function deleteBills(projectid, billIds) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
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
		url = generateUrl('/apps/cospend/import-sw-project')
	} else {
		url = generateUrl('/apps/cospend/import-csv-project')
	}
	return axios.get(url, req)
}

export function addCategory(projectid, name, icon, color, order) {
	const req = {
		name,
		icon,
		color,
		order,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category')
		: generateUrl('/apps/cospend/projects/' + projectid + '/category')
	return axios.post(url, req)
}

export function addPaymentMode(projectid, name, icon, color, order) {
	const req = {
		name,
		icon,
		color,
		order,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/paymentmode')
		: generateUrl('/apps/cospend/projects/' + projectid + '/paymentmode')
	return axios.post(url, req)
}

export function deleteCategory(projectid, categoryid) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + categoryid)
		: generateUrl('/apps/cospend/projects/' + projectid + '/category/' + categoryid)
	return axios.delete(url)
}

export function deletePaymentMode(projectid, pmid) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/paymentmode/' + pmid)
		: generateUrl('/apps/cospend/projects/' + projectid + '/paymentmode/' + pmid)
	return axios.delete(url)
}

export function editCategory(projectid, category, backupCategory) {
	const req = {
		name: category.name,
		icon: category.icon,
		color: category.color,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + category.id)
		: generateUrl('/apps/cospend/projects/' + projectid + '/category/' + category.id)
	return axios.put(url, req)
}

export function editPaymentMode(projectid, pm, backupPm) {
	const req = {
		name: pm.name,
		icon: pm.icon,
		color: pm.color,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/paymentmode/' + pm.id)
		: generateUrl('/apps/cospend/projects/' + projectid + '/paymentmode/' + pm.id)
	return axios.put(url, req)
}

export function saveCategoryOrder(projectid, order) {
	const req = {
		order,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category-order')
		: generateUrl('/apps/cospend/projects/' + projectid + '/category-order')
	return axios.put(url, req)
}

export function savePaymentModeOrder(projectid, order) {
	const req = {
		order,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/paymentmode-order')
		: generateUrl('/apps/cospend/projects/' + projectid + '/paymentmode-order')
	return axios.put(url, req)
}

export function addCurrency(projectid, name, rate, successCB) {
	const req = {
		name,
		rate,
	}
	let url
	if (!cospend.pageIsPublic) {
		req.projectid = projectid
		url = generateUrl('/apps/cospend/projects/' + projectid + '/currency')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency')
	}
	axios.post(url, req)
		.then((response) => {
			successCB(response.data, name, rate)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add currency')
				+ ': ' + error.response.request.responseText,
			)
		})
}

export function deleteCurrency(projectid, currency, successCB) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/currency/' + currency.id)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currency.id)
	}
	axios.delete(url)
		.then((response) => {
			successCB(currency)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete currency')
				+ ': ' + error.response.request.responseText,
			)
		})
}

export function editCurrency(projectid, currency, backupCurrency, failCB) {
	const req = {
		name: currency.name,
		rate: currency.exchange_rate,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/currency/' + currency.id)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currency.id)
	}
	axios.put(url, req)
		.then((response) => {
		})
		.catch((error) => {
			failCB(currency, backupCurrency)
			showError(
				t('cospend', 'Failed to edit currency')
				+ ': ' + error.response.request.responseText,
			)
		})
}

export function getStats(projectid, params, isFiltered, successCB, doneCB) {
	const req = {
		params,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/statistics')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/statistics')
	}
	axios.get(url, req)
		.then((response) => {
			successCB(response.data, isFiltered)
		})
		.catch((error) => {
			showError(t('cospend', 'Failed to get statistics.'))
			console.debug(error)
		})
		.then(() => {
			doneCB()
		})
}

export function exportStats(projectid, params, doneCB) {
	const req = {
		params,
	}
	const url = generateUrl('/apps/cospend/export-csv-statistics/' + projectid)
	axios.get(url, req)
		.then((response) => {
			showSuccess(t('cospend', 'Project statistics exported in {path}', { path: response.data.path }))
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to export project statistics')
				+ ': ' + error.response.request.responseText,
			)
		})
		.then(() => {
			doneCB()
		})
}

export function getSettlement(projectid, centeredOn, maxTimestamp) {
	if (parseInt(centeredOn) === 0) {
		centeredOn = null
	}
	const req = {
		params: {
			centeredOn,
			maxTimestamp,
		},
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/settlement')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/settle')
	}
	return axios.get(url, req)
}

export function autoSettlement(projectid, centeredOn, maxTimestamp, precision, successCB) {
	const req = {
		params: {
			centeredOn: (parseInt(centeredOn) === 0) ? null : centeredOn,
			precision,
			maxTimestamp,
		},
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/auto-settlement')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/autosettlement')
	}
	axios.get(url, req)
		.then((response) => {
			successCB()
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add project settlement bills')
				+ ': ' + error.response.request.responseText,
			)
		})
}

export function exportSettlement(projectid, centeredOn, maxTimestamp, successCB) {
	const req = {
		params: {
			centeredOn: (parseInt(centeredOn) === 0) ? null : centeredOn,
			maxTimestamp,
		},
	}
	const url = generateUrl('/apps/cospend/export-csv-settlement/' + projectid)
	axios.get(url, req)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to export project settlement')
				+ ': ' + error.response.request.responseText,
			)
		})
}

export function addSharedAccess(projectid, sh) {
	const req = {
		accesslevel: sh.accesslevel || constants.ACCESS.PARTICIPANT,
		manually_added: sh.manually_added,
	}
	let url
	if (sh.type === constants.SHARE_TYPE.USER) {
		req.userid = sh.user
		url = generateUrl('/apps/cospend/projects/' + projectid + '/user-share')
	} else if (sh.type === constants.SHARE_TYPE.GROUP) {
		req.groupid = sh.user
		url = generateUrl('/apps/cospend/projects/' + projectid + '/group-share')
	} else if (sh.type === constants.SHARE_TYPE.CIRCLE) {
		req.circleid = sh.user
		url = generateUrl('/apps/cospend/projects/' + projectid + '/circle-share')
	} else if (sh.type === constants.SHARE_TYPE.PUBLIC_LINK) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/public-share')
	}
	return axios.post(url, req)
}

export function setAccessLevel(projectid, access, level) {
	const req = {
		accesslevel: level,
	}
	const url = generateUrl('/apps/cospend/projects/' + projectid + '/share-access-level/' + access.id)
	return axios.put(url, req)
}

export function editSharedAccess(projectid, access, label, password) {
	const req = {
		label,
		password,
	}
	const url = generateUrl('/apps/cospend/projects/' + projectid + '/share-access/' + access.id)
	return axios.put(url, req)
}

export function deleteAccess(projectid, access) {
	const shid = access.id
	let url
	if (access.type === constants.SHARE_TYPE.USER) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/user-share/' + shid)
	} else if (access.type === constants.SHARE_TYPE.GROUP) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/group-share/' + shid)
	} else if (access.type === constants.SHARE_TYPE.CIRCLE) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/circle-share/' + shid)
	} else if (access.type === constants.SHARE_TYPE.PUBLIC_LINK) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/public-share/' + shid)
	}
	return axios.delete(url)
}

export function setGuestAccessLevel(projectid, level) {
	const req = {
		accesslevel: level,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/guest-access-level')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/guest-access-level')
	}
	return axios.put(url, req)
}
