import * as constants from './constants.js'

const cospend = {
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
	showMyBalance: false,
	// Cross-project balance display settings
	// Controls whether Summary section appears before People section in CrossProjectBalanceView
	showSummaryFirst: true,
	// Controls whether project breakdown details are collapsed by default
	// Set to true for cleaner UI, especially beneficial for users with many projects
	hideProjectsByDefault: true,
}

export default cospend
