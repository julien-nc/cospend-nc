/* jshint esversion: 6 */

import * as constants from './constants'

const cospend = {
	restoredCurrentProjectId: null,
	urlProjectId: null,
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
	pubLinkData: {
		type: constants.SHARE_TYPE.PUBLIC_LINK,
		name: null,
		label: t('cospend', 'Add public link'),
		value: '',
	},
	hardCodedCategories: constants.hardCodedCategories,
	paymentModes: constants.paymentModes,
	memberOrder: 'name',
	useTime: true,
}

export default cospend
