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
        displayCurrencies(projectid);
    });
}

export function displayCurrencies(projectid) {
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
