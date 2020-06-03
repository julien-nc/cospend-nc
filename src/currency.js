/*jshint esversion: 6 */

import Vue from 'vue';
import './bootstrap';
import CurrencyManagement from './CurrencyManagement';
import {generateUrl} from '@nextcloud/router';
import {
    getProjectName,
    selectProject
} from './project';
import * as Notification from './notification';
import cospend from './state';

export function currencyEvents() {
    $('body').on('click', '.manageProjectCurrencies', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        getProjectCurrencies(projectid);
    });
}

export function getProjectCurrencies(projectid) {
    $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/getProjectInfo');
        type = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
        type = 'GET';
    }
    cospend.currentGetProjectsAjax = $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        if (cospend.currentProjectId !== projectid) {
            selectProject($('.projectitem[projectid="' + projectid + '"]'));
        }
        cospend.currencies = response.currencies;
        displayCurrencies(projectid, response);
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get project currencies'));
        $('#billdetail').html('');
    });
}

export function displayCurrencies(projectid, projectInfo) {
    // deselect bill
    $('.billitem').removeClass('selectedbill');
    const projectName = getProjectName(projectid);
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Currencies of project {name}', {name: projectName});

    $('#billdetail')
        .append($('<div/>', {id: 'app-details-toggle', tabindex: 0, class: 'icon-confirm'}))
        .append(
            $('<h2/>', {id: 'curTitle', projectid: projectid})
                .append($('<span/>', {class: 'icon-currencies'}))
                .append(titleStr)
        )
        .append($('<div/>', {id: 'manage-currencies'}));

    new Vue({
        el: "#manage-currencies",
        render: h => h(CurrencyManagement),
    });
}
