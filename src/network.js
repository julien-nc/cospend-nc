/* jshint esversion: 6 */

import cospend from './state'
import * as constants from './constants'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

export function getOptionValues(successCB) {
	const url = generateUrl('/apps/cospend/option-values')
	const req = {}
	axios.get(url, req)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to restore options values.')
			)
			console.debug(error)
		})
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
					+ ': ' + error.response.request.responseText
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
				t('cospend', 'Cospend setting saved.')
			)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to save Cospend setting.')
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
				+ ': ' + error.response.request.responseText
			)
		})
}

export function getProjects(callback) {
	const req = {}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	axios.get(url, req)
		.then((response) => {
			callback(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to get projects')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function getBills(projectid, offset, limit) {
	const req = {
		params: {
			offset,
			limit,
			reverse: true,
		},
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/apiv3/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
		: generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	return axios.get(url, req)
}

export function createProject(name, id, successCB) {
	const req = {
		id,
		name,
		password: null,
	}
	const url = generateUrl('/apps/cospend/projects')
	axios.post(url, req)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to create project')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function deleteProject(projectid, successCB) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	axios.delete(url)
		.then((response) => {
			successCB(projectid, response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete project')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function updateBalances(projectid, successCB) {
	const req = {}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	axios.get(url, req)
		.then((response) => {
			successCB(projectid, response.data)
		})
		.catch((error) => {
			const msg = (error.response && error.response.request && error.response.request.responseText)
				? error.response.request.responseText
				: error
			showError(
				t('cospend', 'Failed to update balances')
				+ ': ' + msg
			)
		})
}

export function createMember(projectid, name, userid, successCB) {
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
	axios.post(url, req)
		.then((response) => {
			successCB(projectid, name, response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add member')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function editMember(projectid, member, successCB) {
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
	axios.put(url, req)
		.then((response) => {
			successCB(projectid, memberid, response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to save member')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function editProject(project, password, successCB) {
	const projectid = project.id
	const req = {
		name: project.name,
		contact_email: null,
		password,
		autoexport: project.autoexport,
		currencyname: project.currencyname,
		deletion_disabled: project.deletion_disabled,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password)
	}
	axios.put(url, req)
		.then((response) => {
			successCB(password)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to edit project')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function repeatBillNow(projectId, billId) {
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + billId + '/repeat')
		: generateUrl('/apps/cospend/projects/' + projectId + '/bills/' + billId + '/repeat')
	return axios.get(url)
}

export function saveBill(projectid, bill, successCB, doneCB) {
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
		paymentmode: bill.paymentmode,
		categoryid: bill.categoryid,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/bills/' + bill.id)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id)
	}
	axios.put(url, req)
		.then((response) => {
			successCB()
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to save bill')
				+ ': ' + error.response.request.responseText
			)
		})
		.then(() => {
			doneCB()
		})
}

export function saveBills(projectid, billIds, categoryid, paymentmode, successCB) {
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
		paymentmode,
		categoryid,
		billIds,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
	}
	axios.put(url, req)
		.then((response) => {
			successCB(billIds, categoryid, paymentmode)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to save bill')
				+ ': ' + error.response?.request?.responseText
			)
		})
		.then(() => {
		})
}

export function createBill(projectid, mode, req, billToCreate, successCB, doneCB) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/bills')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills')
	}
	axios.post(url, req)
		.then((response) => {
			successCB(response.data, billToCreate, mode)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to create bill')
				+ ': ' + error.response.request.responseText
			)
		})
		.then(() => {
			doneCB()
		})
}

export function generatePublicLinkToFile(targetPath, successCB) {
	const req = {
		path: targetPath,
	}
	const url = generateUrl('/apps/cospend/getPublicFileShare')
	axios.post(url, req)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to generate public link to file')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function deleteBill(projectid, bill, successCB) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/bills/' + bill.id)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id)
	}
	axios.delete(url)
		.then((response) => {
			successCB(bill)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete bill')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function deleteBills(projectid, billIds, successCB) {
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
	axios.delete(url, req)
		.then((response) => {
			successCB(billIds)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete bills')
				+ ': ' + error.response?.request?.responseText
			)
		})
}

export function checkPassword(projectid, password, successCB) {
	const url = generateUrl('/apps/cospend/checkpassword/' + projectid + '/' + password)
	axios.get(url)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(t('cospend', 'Failed to check password.'))
			console.debug(error)
		})
}

export function importProject(targetPath, isSplitWise, successCB) {
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
	axios.get(url, req)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to import project file')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function addCategory(projectid, name, icon, color, successCB) {
	const req = {
		name,
		icon,
		color,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/category')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category')
	}
	axios.post(url, req)
		.then((response) => {
			successCB(response.data, name, icon, color)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add category')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function deleteCategory(projectid, categoryid, successCB) {
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/category/' + categoryid)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + categoryid)
	}
	axios.delete(url)
		.then((response) => {
			successCB(categoryid)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete category')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function editCategory(projectid, category, backupCategory, failCB) {
	const req = {
		name: category.name,
		icon: category.icon,
		color: category.color,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/category/' + category.id)
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + category.id)
	}
	axios.put(url, req)
		.then((response) => {
		})
		.catch((error) => {
			failCB(category, backupCategory)
			showError(
				t('cospend', 'Failed to edit category')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function saveCategoryOrder(projectid, order) {
	const req = {
		projectid,
		order,
	}
	const url = cospend.pageIsPublic
		? generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category-order')
		: generateUrl('/apps/cospend/projects/' + projectid + '/category-order')
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
				+ ': ' + error.response.request.responseText
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
				+ ': ' + error.response.request.responseText
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
				+ ': ' + error.response.request.responseText
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
				+ ': ' + error.response.request.responseText
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
				+ ': ' + error.response.request.responseText
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
				+ ': ' + error.response.request.responseText
			)
		})
}

export function loadUsers(successCB) {
	const url = generateUrl('/apps/cospend/user-list')
	axios.get(url)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to get user list')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function addSharedAccess(projectid, sh, successCB, thenCB = null) {
	const req = {
		accesslevel: sh.accesslevel || constants.ACCESS.PARTICIPANT,
		manually_added: sh.manually_added,
	}
	let url
	if (sh.type === 'u') {
		req.userid = sh.user
		url = generateUrl('/apps/cospend/projects/' + projectid + '/user-share')
	} else if (sh.type === 'g') {
		req.groupid = sh.user
		url = generateUrl('/apps/cospend/projects/' + projectid + '/group-share')
	} else if (sh.type === 'c') {
		req.circleid = sh.user
		url = generateUrl('/apps/cospend/projects/' + projectid + '/circle-share')
	} else if (sh.type === 'l') {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/public-share')
	}
	axios.post(url, req)
		.then((response) => {
			successCB(response.data, sh, projectid)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to add shared access')
				+ ': ' + error.response.request.responseText
			)
		}).then(() => {
			if (thenCB !== null) {
				thenCB()
			}
		})
}

export function setAccessLevel(projectid, access, level, successCB) {
	const req = {
		accesslevel: level,
	}
	const url = generateUrl('/apps/cospend/projects/' + projectid + '/share-access-level/' + access.id)
	axios.put(url, req)
		.then((response) => {
			successCB(access, level)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to edit shared access level')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function deleteAccess(projectid, access, successCB) {
	const shid = access.id
	let url
	if (access.type === 'u') {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/user-share/' + shid)
	} else if (access.type === 'g') {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/group-share/' + shid)
	} else if (access.type === 'c') {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/circle-share/' + shid)
	} else if (access.type === 'l') {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/public-share/' + shid)
	}
	axios.delete(url)
		.then((response) => {
			successCB(access)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to delete shared access')
				+ ': ' + error.response.request.responseText
			)
		})
}

export function setGuestAccessLevel(projectid, level, successCB) {
	const req = {
		accesslevel: level,
	}
	let url
	if (!cospend.pageIsPublic) {
		url = generateUrl('/apps/cospend/projects/' + projectid + '/guest-access-level')
	} else {
		url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/guest-access-level')
	}
	axios.put(url, req)
		.then((response) => {
			successCB(level)
		})
		.catch((error) => {
			showError(
				t('cospend', 'Failed to edit guest access level')
				+ ': ' + error.response.request.responseText
			)
		})
}
