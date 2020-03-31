/*jshint esversion: 6 */

export const ACCESS = {
    VIEWER: 1,
    PARTICIPANT: 2,
    MAINTENER: 3,
    ADMIN: 4
};

export const MEMBER_NAME_EDITION = 1;
export const MEMBER_WEIGHT_EDITION = 2;

export const PROJECT_NAME_EDITION = 1;
export const PROJECT_PASSWORD_EDITION = 2;

export const categories = {
    '-1': {
        name: t('cospend', 'Grocery'),
        icon: '🛒',
        color: '#ffaa00'
    },
    '-2': {
        name: t('cospend', 'Bar/Party'),
        icon: '🎉',
        color: '#aa55ff'
    },
    '-3': {
        name: t('cospend', 'Rent'),
        icon: '🏠',
        color: '#da8733'
    },
    '-4': {
        name: t('cospend', 'Bill'),
        icon: '🌩',
        color: '#4aa6b0'
    },
    '-5': {
        name: t('cospend', 'Excursion/Culture'),
        icon: '🚸',
        color: '#0055ff'
    },
    '-6': {
        name: t('cospend', 'Health'),
        icon: '💚',
        color: '#bf090c'
    },
    '-10': {
        name: t('cospend', 'Shopping'),
        icon: '🛍',
        color: '#e167d1'
    },
    '-11': {
        name: t('cospend', 'Reimbursement'),
        icon: '💰',
        color: '#e1d85a'
    },
    '-12': {
        name: t('cospend', 'Restaurant'),
        icon: '🍴',
        color: '#d0d5e1'
    },
    '-13': {
        name: t('cospend', 'Accommodation'),
        icon: '🛌',
        color: '#5de1a3'
    },
    '-14': {
        name: t('cospend', 'Transport'),
        icon: '🚌',
        color: '#6f2ee1'
    },
    '-15': {
        name: t('cospend', 'Sport'),
        icon: '🎾',
        color: '#69e177'
    },
};

export const paymentModes = {
    c: {
        name: t('cospend', 'Credit card'),
        icon: '💳'
    },
    b: {
        name: t('cospend', 'Cash'),
        icon: '💵'
    },
    f: {
        name: t('cospend', 'Check'),
        icon: '🎫'
    },
    t: {
        name: t('cospend', 'Transfer'),
        icon: '⇄'
    },
};