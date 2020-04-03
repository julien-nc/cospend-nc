/*jshint esversion: 6 */

import * as constants from './constants';

const cospend = {
    restoredSelectedProjectId: null,
    memberEditionMode: null,
    projectEditionMode: null,
    projectDeletionTimer: {},
    billDeletionTimer: {},
    currencyDeletionTimer: {},
    categoryDeletionTimer: {},
    // indexed by projectid, then by billid
    bills: {},
    // indexed by projectid, then by memberid
    members: {},
    projects: {},
    currentProjectId: null,
    pubLinkData: {
        type: 'l',
        name: null,
        label: t('cospend', 'Add public link'),
        value: ''
    },
    hardCodedCategories: constants.hardCodedCategories,
    paymentModes: constants.paymentModes
};

export default cospend;