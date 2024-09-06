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

export const PROJECT_ARCHIVED_TS_UNSET = -1
export const PROJECT_ARCHIVED_TS_NOW = 0

export const SHARE_TYPE = {
	PUBLIC_LINK: 'l',
	USER: 'u',
	GROUP: 'g',
	CIRCLE: 'c',
	FEDERATED: 'f',
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
	RECENTLY_USED: 'r',
}

export const hardCodedCategories = {
	'-11': {
		id: -11,
		name: t('cospend', 'Reimbursement'),
		icon: '💰',
		color: '#e1d85a',
	},
}
