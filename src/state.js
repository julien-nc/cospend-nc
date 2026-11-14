/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as constants from './constants.js'

export const defaultState = {
	restoredCurrentProjectId: null,
	restoredCurrentBillId: null,
	currentProjectId: null,
	// indexed by projectid, then by memberid
	members: {},
	projects: {},
	hardCodedCategories: constants.hardCodedCategories,
	memberOrder: 'name',
	useTime: true,
	activity_enabled: false,
	showMyBalance: false,
}
