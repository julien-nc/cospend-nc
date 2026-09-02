/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as constants from './constants.js'

export const defaultState = {
	restoredCurrentProjectId: null,
	restoredCurrentBillId: null,
	currentProjectId: null,
	currentBill: null,
	memberEditionMode: null,
	projectEditionMode: null,
	projectDeletionTimer: {},
	shareDeletionTimer: {},
	billDeletionTimer: {},
	currencyDeletionTimer: {},
	categoryDeletionTimer: {},
	// indexed by projectid, then by billid
	bills: {},
	billLists: {},
	// indexed by projectid, then by memberid
	members: {},
	projects: {},
	hardCodedCategories: constants.hardCodedCategories,
	memberOrder: 'name',
	useTime: true,
	activity_enabled: false,
	restoredCrossProjectMode: null,
	restoredCrossProjectPersonKey: null,
	showMyBalance: false,
}
