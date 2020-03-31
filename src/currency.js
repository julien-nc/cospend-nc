/*jshint esversion: 6 */

import {generateUrl} from "@nextcloud/router";
import {getProjectName, selectProject} from "./project";
import * as Notification from "./notification";
import * as constants from "./constants";
import cospend from "./state";

export function getProjectCurrencies (projectid) {
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
    }).done(function (response) {
        if (cospend.currentProjectId !== projectid) {
            selectProject($('.projectitem[projectid="' + projectid + '"]'));
        }
        displayCurrencies(projectid, response);
    }).always(function () {
    }).fail(function () {
        Notification.showTemporary(t('cospend', 'Failed to get project currencies'));
        $('#billdetail').html('');
    });
}

export function displayCurrencies (projectid, projectInfo) {
    // deselect bill
    $('.billitem').removeClass('selectedbill');
    const mainCurrencyName = projectInfo.currencyname;
    const currencies = projectInfo.currencies;
    const projectName = getProjectName(projectid);
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Currencies of project {name}', {name: projectName});

    const curStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
        '<h2 id="curTitle" projectid="' + projectid + '"><span class="icon-currencies"></span>' + titleStr + '</h2>' +
        '<div id="manage-currencies">' +
        '    <div id="main-currency-div">' +
        '        <label>' +
        '            <a class="icon icon-tag"></a>' +
        '            ' + t('cospend', 'Main currency') +
        '        </label>' +
        '        <div id="main-currency-label">' +
        '            <label id="main-currency-label-label">' +
        (mainCurrencyName || t('cospend', 'None')) + '</label>' +
        '            <input type="submit" value="" class="icon-rename editMainCurrency">' +
        '        </div>' +
        '        <div id="main-currency-edit">' +
        '            <input type="text" maxlength="64" value="' + (mainCurrencyName || t('cospend', 'Potatoe')) + '" class="editMainCurrencyInput">' +
        '            <input type="submit" value="" class="icon-close editMainCurrencyClose">' +
        '            <input type="submit" value="" class="icon-checkmark editMainCurrencyOk">' +
        '        </div>' +
        '    </div><hr/>' +
        '    <div id="currencies-div">' +
        '        <div id="add-currency-div">' +
        '            <label>' +
        '                <a class="icon icon-add"></a>' +
        '                ' + t('cospend', 'Add currency') +
        '            </label>' +
        '            <div id="add-currency">' +
        '                <label for="addCurrencyNameInput">' + t('cospend', 'Name') + '</label>' +
        '                <input type="text" value="" maxlength="64" id="addCurrencyNameInput">' +
        '                <label for="addCurrencyRateInput"> ' + t('cospend', 'Exchange rate to main currency') +
        '                   <br/>' + t('cospend', '(1 of this currency = X of main currency)') +
        '                </label>' +
        '                <input type="number" value="1" id="addCurrencyRateInput" step="0.0001" min="0">' +
        '                <input type="submit" value="" class="icon-add addCurrencyOk">' +
        '            </div><hr/>' +
        '        </div>' +
        '        <br/>' +
        '        <label>' +
        '            <a class="icon icon-currencies"></a>' +
        '            ' + t('cospend', 'Currency list') +
        '        </label><br/><br/>' +
        '        <div id="currency-list">' +
        '        </div>' +
        '    </div>' +
        '</div>';

    $('#billdetail').html(curStr);
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

export function addCurrencyDb (projectid, name, rate) {
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
    }).done(function (response) {
        addCurrency(projectid, {name: name, exchange_rate: rate, id: response});
        cospend.projects[projectid].currencies.push({
            name: name,
            exchange_rate: rate,
            id: response
        });
        Notification.showTemporary(t('cospend', 'Currency {n} added', {n: name}));
    }).always(function () {
        $('.addCurrencyOk').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add currency') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function addCurrency (projectid, currency) {
    const curStr = '<div class="one-currency" projectid="' + projectid + '" currencyid="' + currency.id + '">' +
        '    <div class="one-currency-label">' +
        '        <label class="one-currency-label-label">' +
        currency.name + ' (x' + currency.exchange_rate + ')</label>' +
        '        <input type="submit" value="" class="icon-rename editOneCurrency">' +
        '        <input type="submit" value="" class="icon-delete deleteOneCurrency">' +
        '    </div>' +
        '    <div class="one-currency-edit">' +
        '        <label>' + t('cospend', 'Name') + '</label>' +
        '        <input type="text" value="' + currency.name + '" maxlength="64" class="editCurrencyNameInput">' +
        '        <label> ' + t('cospend', 'Exchange rate to main currency') +
        '           <br/>' + t('cospend', '(1 of this currency = X of main currency)') +
        '        </label>' +
        '        <input type="number" value="' + currency.exchange_rate + '" class="editCurrencyRateInput" step="0.0001" min="0">' +
        '        <input type="submit" value="" class="icon-close editCurrencyClose">' +
        '        <input type="submit" value="" class="icon-checkmark editCurrencyOk">' +
        '    </div>' +
        '</div>';
    $('#currency-list').append(curStr);
}

export function deleteCurrencyDb (projectid, currencyId) {
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
    }).done(function () {
        $('.one-currency[currencyid=' + currencyId + ']').remove();
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
    }).always(function () {
        $('.one-currency[currencyid=' + currencyId + '] .deleteOneCurrency').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete currency') +
            ': ' + response.responseJSON.message
        );
    });
}

export function editCurrencyDb (projectid, currencyId, name, rate) {
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
    }).done(function () {
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
    }).always(function () {
        $('.one-currency[currencyid=' + currencyId + '] .editCurrencyOk').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit currency') +
            '; ' + response.responseJSON.message
        );
    });
}