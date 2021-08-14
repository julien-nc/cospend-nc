/* jshint esversion: 6 */

export const ACCESS = {
	NO_ACCESS: 0,
	VIEWER: 1,
	PARTICIPANT: 2,
	MAINTENER: 3,
	ADMIN: 4,
}

export const MEMBER_NAME_EDITION = 1
export const MEMBER_WEIGHT_EDITION = 2

export const PROJECT_NAME_EDITION = 1
export const PROJECT_PASSWORD_EDITION = 2

export const SHARE_TYPE = {
	PUBLIC_LINK: 'l',
	USER: 'u',
	GROUP: 'g',
	CIRCLE: 'c',
}

export const FREQUENCY = {
	NO: 'n',
	DAILY: 'd',
	WEEKLY: 'w',
	BI_WEEKLY: 'b',
	SEMI_MONTHLY: 's',
	MONTHLY: 'm',
	YEARLY: 'y',
}

export const SORT_ORDER = {
	ALPHA: 'a',
	MANUAL: 'm',
	MOST_USED: 'u',
	MOST_RECENTLY_USED: 'r',
}

export const hardCodedCategories = {
	'-11': {
		id: -11,
		name: t('cospend', 'Reimbursement'),
		icon: '💰',
		color: '#e1d85a',
	},
}

export const hardCodedPaymentModes = {
	'-1': {
		id: -1,
		name: t('cospend', 'Credit card'),
		icon: '💳',
		color: '#FF7F50',
	},
	'-2': {
		id: -2,
		name: t('cospend', 'Cash'),
		icon: '💵',
		color: '#556B2F',
	},
	'-3': {
		id: -3,
		name: t('cospend', 'Check'),
		icon: '🎫',
		color: '#A9A9A9',
	},
	'-4': {
		id: -4,
		name: t('cospend', 'Transfer'),
		icon: '⇄',
		color: '#00CED1',
	},
	'-5': {
		id: -5,
		name: t('cospend', 'Online service'),
		icon: '🌎',
		color: '#9932CC',
	},
}

export const paymentModes = {
	c: {
		name: t('cospend', 'Credit card'),
		icon: '💳',
		color: '#FF7F50',
	},
	b: {
		name: t('cospend', 'Cash'),
		icon: '💵',
		color: '#556B2F',
	},
	f: {
		name: t('cospend', 'Check'),
		icon: '🎫',
		color: '#A9A9A9',
	},
	t: {
		name: t('cospend', 'Transfer'),
		icon: '⇄',
		color: '#00CED1',
	},
	o: {
		name: t('cospend', 'Online service'),
		icon: '🌎',
		color: '#9932CC',
	},
}
