/* jshint esversion: 6 */

export const ACCESS = {
	VIEWER: 1,
	PARTICIPANT: 2,
	MAINTENER: 3,
	ADMIN: 4,
}

export const MEMBER_NAME_EDITION = 1
export const MEMBER_WEIGHT_EDITION = 2

export const PROJECT_NAME_EDITION = 1
export const PROJECT_PASSWORD_EDITION = 2

export const hardCodedCategories = {
	'-11': {
		id: -11,
		name: t('cospend', 'Reimbursement'),
		icon: '💰',
		color: '#e1d85a',
	},
}

export const paymentModes = {
	c: {
		name: t('cospend', 'Credit card'),
		icon: '💳',
	},
	b: {
		name: t('cospend', 'Cash'),
		icon: '💵',
	},
	f: {
		name: t('cospend', 'Check'),
		icon: '🎫',
	},
	t: {
		name: t('cospend', 'Transfer'),
		icon: '⇄',
	},
	o: {
		name: t('cospend', 'Online service'),
		icon: '🌎',
	},
}
