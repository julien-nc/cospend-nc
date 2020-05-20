/*jshint esversion: 6 */

import {generateUrl} from '@nextcloud/router';
import {
    getProjectName,
    selectProject,
    editProject
} from './project';
import * as Notification from './notification';
import * as constants from './constants';
import cospend from './state';
import {Timer} from "./utils";

export function currencyEvents() {
    // main currency
    $('body').on('click', '.editMainCurrency', function () {
        $('#main-currency-label').hide();
        $('#main-currency-edit').show().css('display', 'grid');
        $('.editMainCurrencyInput').focus().select();
    });

    $('body').on('click', '.editMainCurrencyOk', function () {
        const projectid = $('#curTitle').attr('projectid');
        const value = $('.editMainCurrencyInput').val();
        const projectName = cospend.projects[projectid].name;
        editProject(projectid, projectName, null, null, null, value);
    });
    $('body').on('keyup', '.editMainCurrencyInput', function (e) {
        if (e.key === 'Enter') {
            const projectid = $('#curTitle').attr('projectid');
            const value = $('.editMainCurrencyInput').val();
            const projectName = cospend.projects[projectid].name;
            editProject(projectid, projectName, null, null, null, value);
        }
    });

    $('body').on('click', '.editMainCurrencyClose', function () {
        $('#main-currency-label').show();
        $('#main-currency-edit').hide();
    });

    // other currencies
    $('body').on('click', '.addCurrencyOk', function () {
        const projectid = $('#curTitle').attr('projectid');
        const name = $('#addCurrencyNameInput').val();
        if (name === null || name === '') {
            Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
            return;
        }
        const rate = parseFloat($('#addCurrencyRateInput').val());
        if (isNaN(rate)) {
            Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
            return;
        }
        addCurrencyDb(projectid, name, rate);
    });

    $('body').on('keyup', '#addCurrencyNameInput, #addCurrencyRateInput', function (e) {
        if (e.key === 'Enter') {
            const projectid = $('#curTitle').attr('projectid');
            const name = $('#addCurrencyNameInput').val();
            if (name === null || name === '') {
                Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
                return;
            }
            const rate = parseFloat($('#addCurrencyRateInput').val());
            if (isNaN(rate)) {
                Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
                return;
            }
            addCurrencyDb(projectid, name, rate);
        }
    });

    $('body').on('click', '.deleteOneCurrency', function () {
        const projectid = $('#curTitle').attr('projectid');
        const currencyId = $(this).parent().parent().attr('currencyid');
        if ($(this).hasClass('icon-history')) {
            $(this).removeClass('icon-history').addClass('icon-delete');
            cospend.currencyDeletionTimer[currencyId].pause();
            delete cospend.currencyDeletionTimer[currencyId];
        } else {
            $(this).addClass('icon-history').removeClass('icon-delete');
            cospend.currencyDeletionTimer[currencyId] = new Timer(function () {
                deleteCurrencyDb(projectid, currencyId);
            }, 7000);
        }
    });

    $('body').on('click', '.editOneCurrency', function () {
        $(this).parent().hide();
        $(this).parent().parent().find('.one-currency-edit').show()
            .css('display', 'grid')
            .find('.editCurrencyNameInput').focus().select();
    });

    $('body').on('click', '.editCurrencyOk', function () {
        const projectid = $('#curTitle').attr('projectid');
        const currencyId = $(this).parent().parent().parent().attr('currencyid');
        const name = $(this).parent().parent().find('.editCurrencyNameInput').val();
        if (name === null || name === '') {
            Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
            return;
        }
        const rate = parseFloat($(this).parent().parent().find('.editCurrencyRateInput').val());
        if (isNaN(rate)) {
            Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
            return;
        }
        editCurrencyDb(projectid, currencyId, name, rate);
    });

    $('body').on('keyup', '.editCurrencyNameInput, .editCurrencyRateInput', function (e) {
        if (e.key === 'Enter') {
            const projectid = $('#curTitle').attr('projectid');
            const currencyId = $(this).parent().parent().attr('currencyid');
            const name = $(this).parent().find('.editCurrencyNameInput').val();
            if (name === null || name === '') {
                Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
                return;
            }
            const rate = parseFloat($(this).parent().find('.editCurrencyRateInput').val());
            if (isNaN(rate)) {
                Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
                return;
            }
            editCurrencyDb(projectid, currencyId, name, rate);
        }
    });

    $('body').on('click', '.editCurrencyClose', function () {
        $(this).parent().parent().hide();
        $(this).parent().parent().parent().find('.one-currency-label').show();
    });

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
    const mainCurrencyName = projectInfo.currencyname;
    const currencies = projectInfo.currencies;
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
        .append(
            $('<div/>', {id: 'manage-currencies'})
                .append(
                    $('<div/>', {id: 'main-currency-div'})
                        .append(
                            $('<label/>')
                                .append($('<a/>', {class: 'icon icon-tag'}))
                                .append(t('cospend', 'Main currency'))
                        )
                        .append(
                            $('<div/>', {id: 'main-currency-label'})
                                .append($('<label/>', {id: 'main-currency-label-label'}).text((mainCurrencyName || t('cospend', 'None'))))
                                .append($('<input/>', {type: 'submit', value: '', class: 'icon-rename editMainCurrency'}))
                        )
                        .append(
                            $('<div/>', {id: 'main-currency-edit'})
                                .append($('<input/>', {
                                    type: 'text', maxlength: 64,
                                    value: (mainCurrencyName || ''),
                                    class: 'editMainCurrencyInput',
                                    placeholder: t('cospend', 'Main currency name')
                                }))
                                .append($('<input/>', {type: 'submit', value: '', class: 'icon-close editMainCurrencyClose'}))
                                .append($('<input/>', {type: 'submit', value: '', class: 'icon-checkmark editMainCurrencyOk'}))
                        )
                )
                .append($('<hr/>'))
                .append(
                    $('<div/>', {id: 'currencies-div'})
                        .append(
                            $('<div/>', {id: 'add-currency-div'})
                                .append(
                                    $('<label/>')
                                        .append($('<a/>', {class: 'icon icon-add'}))
                                        .append(t('cospend', 'Add currency'))
                                )
                                .append(
                                    $('<div/>', {id: 'add-currency'})
                                        .append($('<label/>', {for: 'addCurrencyNameInput'}).text(t('cospend', 'Name')))
                                        .append($('<input/>', {type: 'text', value: '', maxlength: 64, id: 'addCurrencyNameInput',
                                                               placeholder: t('cospend', 'New currency name')}))
                                        .append($('<label/>', {for: 'addCurrencyRateInput'}).text(t('cospend', 'Exchange rate to main currency')))
                                        .append($('<input/>', {type: 'number', value: 1, id: 'addCurrencyRateInput', step: 0.0001, min: 0}))
                                        .append($('<label/>', {class: 'addCurrencyRateHint'}).text(t('cospend', '(1 of this currency = X of main currency)')))
                                        .append(
                                            $('<button/>', {class: 'addCurrencyOk'})
                                                .append($('<span/>', {class: 'icon-add'}))
                                                .append($('<span/>').text(t('cospend', 'Add this currency')))
                                        )
                                )
                                .append($('<hr/>'))
                        )
                        .append($('<br/>'))
                        .append(
                            $('<label/>')
                                .append($('<a/>', {class: 'icon icon-currencies'}))
                                .append(t('cospend', 'Currency list'))
                        )
                        .append($('<div/>', {id: 'currency-list'}))
                )
        );

    for (let i = 0; i < currencies.length; i++) {
        addCurrency(projectid, currencies[i]);
    }

    if (cospend.projects[projectid].myaccesslevel < constants.ACCESS.MAINTENER) {
        $('.editMainCurrency').hide();
        $('.editOneCurrency').hide();
        $('.deleteOneCurrency').hide();
        $('#add-currency-div').hide();
    }
}

export function addCurrencyDb(projectid, name, rate) {
    $('.addCurrencyOk').addClass('icon-loading-small');
    const req = {
        name: name,
        rate: rate
    };
    let url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/addCurrency');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency');
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        addCurrency(projectid, {name: name, exchange_rate: rate, id: response});
        cospend.projects[projectid].currencies.push({
            name: name,
            exchange_rate: rate,
            id: response
        });
        Notification.showTemporary(t('cospend', 'Currency {n} added', {n: name}));
    }).always(function() {
        $('.addCurrencyOk').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add currency') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function addCurrency(projectid, currency) {
    const currDiv = $('<div/>', {class: 'one-currency', projectid: projectid, currencyid: currency.id})
        .append(
            $('<div/>', {class: 'one-currency-label'})
                .append($('<label/>', {class: 'one-currency-label-label'}).text(currency.name + ' (x' + currency.exchange_rate + ')'))
                .append($('<input/>', {type: 'submit', value: '', class: 'icon-rename editOneCurrency'}))
                .append($('<input/>', {type: 'submit', value: '', class: 'icon-delete deleteOneCurrency'}))
        )
        .append(
            $('<div/>', {class: 'one-currency-edit'})
                .append($('<label/>').text(t('cospend', 'Name')))
                .append($('<input/>', {type: 'text', value: currency.name, maxlength: 64, class: 'editCurrencyNameInput',
                                       placeholder: t('cospend', 'Currency name')}))
                .append(
                    $('<label/>')
                        .append(t('cospend', 'Exchange rate to main currency'))
                        .append($('<br/>'))
                        .append(t('cospend', '(1 of this currency = X of main currency)'))
                )
                .append($('<input/>', {type: 'number', value: currency.exchange_rate, class: 'editCurrencyRateInput', step: 0.0001, min: 0}))
                .append(
                    $('<div/>')
                        .append(
                            $('<button/>', {class: 'editCurrencyClose'})
                                .append($('<span/>', {class: 'icon-close'}))
                                .append($('<span/>').text(t('cospend', 'Cancel')))
                        )
                        .append(
                            $('<button/>', {class: 'editCurrencyOk'})
                                .append($('<span/>', {class: 'icon-checkmark'}))
                                .append($('<span/>').text(t('cospend', 'Save')))
                        )
                )
        )
    $('#currency-list').append(currDiv);
}

export function deleteCurrencyDb(projectid, currencyId) {
    $('.one-currency[currencyid=' + currencyId + '] .deleteOneCurrency').addClass('icon-loading-small');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.currencyid = currencyId;
        url = generateUrl('/apps/cospend/deleteCurrency');
        type = 'POST';
    } else {
        type = 'DELETE';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currencyId);
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true
    }).done(function() {
        $('.one-currency[currencyid=' + currencyId + ']').fadeOut('normal', function() {
            $(this).remove();
        });
        const currencies = cospend.projects[projectid].currencies;
        let iToDel = null;
        for (let i = 0; i < currencies.length; i++) {
            if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                iToDel = i;
                break;
            }
        }
        if (iToDel !== null) {
            currencies.splice(iToDel, 1);
        }
    }).always(function() {
        $('.one-currency[currencyid=' + currencyId + '] .deleteOneCurrency').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete currency') +
            ': ' + response.responseJSON.message
        );
    });
}

export function editCurrencyDb(projectid, currencyId, name, rate) {
    $('.one-currency[currencyid=' + currencyId + '] .editCurrencyOk').addClass('icon-loading-small');
    const req = {
        name: name,
        rate: rate
    };
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.currencyid = currencyId;
        url = generateUrl('/apps/cospend/editCurrency');
        type = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currencyId);
        type = 'PUT';
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true
    }).done(function() {
        $('.one-currency[currencyid=' + currencyId + '] .one-currency-edit').hide();
        $('.one-currency[currencyid=' + currencyId + '] .one-currency-label').show()
            .find('.one-currency-label-label').text(name + ' (x' + rate + ')');
        const currencies = cospend.projects[projectid].currencies;
        for (let i = 0; i < currencies.length; i++) {
            if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                currencies[i].name = name;
                currencies[i].exchange_rate = rate;
                break;
            }
        }
    }).always(function() {
        $('.one-currency[currencyid=' + currencyId + '] .editCurrencyOk').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit currency') +
            '; ' + response.responseJSON.message
        );
    });
}