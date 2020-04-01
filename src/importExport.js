/*jshint esversion: 6 */

import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import {endsWith} from './utils';
import {getProjects} from './project';


export function exportProject (projectid) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const timeStamp = Math.floor(Date.now());
    const dateStr = OC.Util.formatDate(timeStamp);
    const filename = projectid + '_' + dateStr + '.csv';
    const req = {
        projectid: projectid,
        name: filename
    };
    const url = generateUrl('/apps/cospend/exportCsvProject');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        Notification.showTemporary(t('cospend', 'Project exported in {path}', {path: response.path}));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to export project') +
            ': ' + response.responseJSON.message
        );
    });
}

export function exportStatistics (projectid, dateMin = null, dateMax = null, paymentMode = null, category = null,
                                  amountMin = null, amountMax = null, showDisabled = true, currencyId = null) {
    $('.exportStats[projectid="' + projectid + '"] span').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        dateMin: dateMin,
        dateMax: dateMax,
        paymentMode: paymentMode,
        category: category,
        amountMin: amountMin,
        amountMax: amountMax,
        showDisabled: showDisabled ? '1' : '0',
        currencyId: currencyId
    };
    const url = generateUrl('/apps/cospend/exportCsvStatistics');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        Notification.showTemporary(t('cospend', 'Project statistics exported in {path}', {path: response.path}));
    }).always(function() {
        $('.exportStats[projectid="' + projectid + '"] span').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to export project statistics') +
            ': ' + response.responseJSON.message
        );
    });
}

export function exportSettlement (projectid) {
    $('.exportSettlement[projectid="' + projectid + '"] span').addClass('icon-loading-small');
    const req = {
        projectid: projectid
    };
    const url = generateUrl('/apps/cospend/exportCsvSettlement');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        Notification.showTemporary(t('cospend', 'Project settlement exported in {path}', {path: response.path}));
    }).always(function() {
        $('.exportSettlement[projectid="' + projectid + '"] span').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to export project settlement') +
            ': ' + response.responseJSON.message
        );
    });
}

export function importProject (targetPath) {
    if (!endsWith(targetPath, '.csv')) {
        Notification.showTemporary(t('cospend', 'Only CSV files can be imported'));
        return;
    }
    $('#addFileLinkButton').addClass('icon-loading-small');
    const req = {
        path: targetPath
    };
    const url = generateUrl('/apps/cospend/importCsvProject');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function() {
        $('#addFileLinkButton').removeClass('icon-loading-small');
        getProjects();
    }).always(function() {
    }).fail(function(response) {
        $('#addFileLinkButton').removeClass('icon-loading-small');
        Notification.showTemporary(
            t('cospend', 'Failed to import project file') +
            ': ' + response.responseJSON.message
        );
    });
}

export function importSWProject (targetPath) {
    if (!endsWith(targetPath, '.csv')) {
        Notification.showTemporary(t('cospend', 'Only CSV files can be imported'));
        return;
    }
    $('#addFileLinkButton').addClass('icon-loading-small');
    const req = {
        path: targetPath
    };
    const url = generateUrl('/apps/cospend/importSWProject');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function() {
        $('#addFileLinkButton').removeClass('icon-loading-small');
        getProjects();
    }).always(function() {
    }).fail(function(response) {
        $('#addFileLinkButton').removeClass('icon-loading-small');
        Notification.showTemporary(
            t('cospend', 'Failed to import project file') +
            ': ' + response.responseJSON.message
        );
    });
}