/*jshint esversion: 6 */

import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import * as constants from './constants';
import cospend from './state';
import {updateProjectBalances} from './project';
import {getUrlParameter, reload} from './utils';
import {getMemberName} from './member';

const undoDeleteBillStyle = 'opacity:1; background-image: url(' + generateUrl('/svg/core/actions/history?color=2AB4FF') + ');';

function cleanStringFromCurrency(projectid, str) {
    let currency, re;
    for (let i = 0; i < cospend.projects[projectid].currencies.length; i++) {
        currency = cospend.projects[projectid].currencies[i];
        re = new RegExp(' \\(\\d+\\.?\\d* ' + currency.name + '\\)', 'g');
        str = str.replace(re, '');
    }
    return str;
}

export function createBill(projectid, what, amount, payer_id, timestamp, owerIds, repeat,
                            custom = false, paymentmode = null, categoryid = null, repeatallactive = 0, repeatuntil = null) {
    $('.loading-bill').addClass('icon-loading-small');
    const req = {
        what: what,
        timestamp: timestamp,
        payer: payer_id,
        payed_for: owerIds.join(','),
        amount: amount,
        repeat: repeat,
        repeatallactive: repeatallactive,
        repeatuntil: repeatuntil,
        paymentmode: paymentmode,
        categoryid: categoryid
    };
    let url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/addBill');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        const billid = response;
        // update dict
        cospend.bills[projectid][billid] = {
            id: billid,
            what: what,
            timestamp: timestamp,
            amount: amount,
            payer_id: payer_id,
            repeat: repeat,
            repeatallactive: repeatallactive,
            repeatuntil: repeatuntil,
            paymentmode: paymentmode,
            categoryid: categoryid
        };
        const billOwers = [];
        for (let i = 0; i < owerIds.length; i++) {
            billOwers.push({id: owerIds[i]});
        }
        cospend.bills[projectid][billid].owers = billOwers;

        // update ui
        const bill = cospend.bills[projectid][billid];
        if (!custom) {
            updateBillItem(projectid, 0, bill);
            updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                paymentmode, categoryid, repeatallactive, repeatuntil);
        } else {
            addBill(projectid, bill);
        }

        updateProjectBalances(projectid);

        Notification.showTemporary(t('cospend', 'Bill created'));
    }).always(function() {
        $('.loading-bill').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to create bill') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function saveBill(projectid, billid, what, amount, payer_id, timestamp, owerIds, repeat,
                          paymentmode = null, categoryid = null, repeatallactive = null, repeatuntil = null) {
    $('.loading-bill').addClass('icon-loading-small');
    const req = {
        what: what,
        timestamp: timestamp,
        payer: payer_id,
        payed_for: owerIds.join(','),
        amount: amount,
        repeat: repeat,
        repeatallactive: repeatallactive,
        repeatuntil: repeatuntil,
        paymentmode: paymentmode,
        categoryid: categoryid
    };
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.billid = billid;
        type = 'POST';
        url = generateUrl('/apps/cospend/editBill');
    } else {
        type = 'PUT';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + billid);
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function() {
        // update dict
        cospend.bills[projectid][billid].what = what;
        cospend.bills[projectid][billid].timestamp = timestamp;
        cospend.bills[projectid][billid].amount = amount;
        cospend.bills[projectid][billid].payer_id = payer_id;
        cospend.bills[projectid][billid].repeat = repeat;
        cospend.bills[projectid][billid].repeatallactive = repeatallactive;
        cospend.bills[projectid][billid].repeatuntil = repeatuntil;
        cospend.bills[projectid][billid].paymentmode = paymentmode;
        cospend.bills[projectid][billid].categoryid = categoryid;
        const billOwers = [];
        for (let i = 0; i < owerIds.length; i++) {
            billOwers.push({id: owerIds[i]});
        }
        cospend.bills[projectid][billid].owers = billOwers;

        // update ui
        const bill = cospend.bills[projectid][billid];
        updateBillItem(projectid, billid, bill);
        const displayedBillTitle = $('#billdetail .bill-title');
        if (parseInt(displayedBillTitle.attr('billid')) === parseInt(billid) &&
            displayedBillTitle.attr('projectid') === projectid) {
            updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                paymentmode, categoryid, repeatallactive, repeatuntil);
        }

        updateProjectBalances(projectid);

        Notification.showTemporary(t('cospend', 'Bill saved'));
    }).always(function() {
        $('.loading-bill').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to save bill') +
            ' ' + (response.responseJSON.message || response.responseJSON)
        );
    });
}

export function updateBillItem(projectid, billid, bill) {
    const billItem = $('.billitem[billid=' + billid + ']');
    const billSelected = billItem.hasClass('selectedbill');
    let selectedClass = '';
    if (billSelected) {
        selectedClass = ' selectedbill';
    }

    let owerNames = '';
    let ower;
    for (let i = 0; i < bill.owers.length; i++) {
        ower = bill.owers[i];
        owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
    }
    owerNames = owerNames.replace(/, $/, '');
    const memberName = getMemberName(projectid, bill.payer_id);

    const links = bill.what.match(/https?:\/\/[^\s]+/gi) || [];
    let formattedLinks = '';
    let linkChars = '';
    for (let i = 0; i < links.length; i++) {
        formattedLinks = formattedLinks + '<a href="' + links[i] + '" target="blank">[' + t('cospend', 'link') + ']</a> ';
        linkChars = linkChars + '  🔗';
    }
    let paymentmodeChar = '';
    // c b f card, cash, check
    if (cospend.paymentModes.hasOwnProperty(bill.paymentmode)) {
        paymentmodeChar = cospend.paymentModes[bill.paymentmode].icon + ' ';
    }
    let categoryChar = '';
    if (cospend.categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = cospend.categories[bill.categoryid].icon + ' ';
    }
    if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
    }
    const whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars;

    const billMom = moment.unix(bill.timestamp);
    const billDate = billMom.format('YYYY-MM-DD');
    const billTime = billMom.format('HH:mm');

    const title = whatFormatted + '\n' + bill.amount.toFixed(2) + '\n' +
        billDate + ' ' + billTime + '\n' + memberName + ' -> ' + owerNames;
    const imgurl = generateUrl('/apps/cospend/getAvatar?color=' +
        cospend.members[projectid][bill.payer_id].color +
        '&name=' + encodeURIComponent(memberName));
    const item = '<a href="#" class="app-content-list-item billitem' + selectedClass + '" billid="' + bill.id + '" projectid="' + projectid + '" title="' + title + '">' +
        '<div class="app-content-list-item-icon" style="background-image: url(' + imgurl + ');"> ' +
        '   <div class="billItemDisabledMask' + (cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled') + '"></div>' +
        '   <div class="billItemRepeatMask' + (bill.repeat === 'n' ? '' : ' show') + '"></div>' +
        '</div>' +
        '<div class="app-content-list-item-line-one">' + whatFormatted + '</div>' +
        '<div class="app-content-list-item-line-two">' + bill.amount.toFixed(2) + ' (' + memberName + ' → ' + owerNames + ')</div>' +
        '<span class="app-content-list-item-details">' + billDate + '</span>' +
        '<div class="icon-delete deleteBillIcon"></div>' +
        '<div class="icon-history undoDeleteBill" style="' + undoDeleteBillStyle + '" title="Undo"></div>' +
        '</a>';
    billItem.replaceWith(item);
    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        $('.billitem[billid=' + bill.id + '] .deleteBillIcon').hide();
    }
}

export function deleteBill(projectid, billid) {
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.billid = billid;
        type = 'POST';
        url = generateUrl('/apps/cospend/deleteBill');
    } else {
        type = 'DELETE';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + billid);
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function() {
        // if the deleted bill was displayed in details, empty detail
        if ($('#billdetail .bill-title').length > 0 && $('#billdetail .bill-title').attr('billid') === billid) {
            $('#billdetail').html('');
        }
        $('.billitem[billid=' + billid + ']').fadeOut('normal', function() {
            $(this).remove();
            if ($('.billitem').length === 0) {
                $('#bill-list').html('<h2 class="nobill">' + t('cospend', 'No bill yet') + '</h2>');
            }
        });
        delete cospend.bills[projectid][billid];
        updateProjectBalances(projectid);
        Notification.showTemporary(t('cospend', 'Deleted bill'));
    }).always(function() {
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete bill') +
            ': ' + response.responseJSON.message
        );
        const deleteBillIcon = $('.billitem[billid=' + billid + '] .deleteBillIcon');
        deleteBillIcon.parent().find('.undoDeleteBill').hide();
        deleteBillIcon.parent().removeClass('deleted');
        deleteBillIcon.show();
    });
}

export function getBills(projectid) {
    $('#bill-list').html('<h2 class="icon-loading-small"></h2>');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/getBills');
        type = 'POST';
        req.projectid = projectid;
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
        type = 'GET';
    }
    cospend.currentGetProjectsAjax = $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        $('#bill-list').html('');
        cospend.bills[projectid] = {};
        if (response.length > 0) {
            let bill;
            for (let i = 0; i < response.length; i++) {
                bill = response[i];
                addBill(projectid, bill);
            }
        } else {
            $('#bill-list').html('<h2 class="nobill">' + t('cospend', 'No bill yet') + '</h2>');
        }
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get bills'));
        $('#bill-list').html('');
    });
}

export function updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                                     paymentmode = null, categoryid = null, repeatallactive = 0,
                                     repeatuntil = null) {
    $('.bill-title').attr('billid', billid);
    let c = '#888888';
    if (billid !== 0) {
        $('.bill-type').hide();
        $('#owerValidate').hide();
        const memberPayer = cospend.members[projectid][payer_id];
        c = '#' + memberPayer.color;
    }

    const links = what.match(/https?:\/\/[^\s]+/gi) || [];
    let formattedLinks = '';
    for (let i = 0; i < links.length; i++) {
        formattedLinks = formattedLinks + '<a href="' + links[i] + '" target="blank">[🔗 ' + t('cospend', 'link') + ']</a> ';
    }
    let paymentmodeChar = '';
    // c b f card, cash, check
    if (cospend.paymentModes.hasOwnProperty(paymentmode)) {
        paymentmodeChar = cospend.paymentModes[paymentmode].icon + ' ';
    }
    let categoryChar = '';
    if (cospend.categories.hasOwnProperty(categoryid)) {
        categoryChar = cospend.categories[categoryid].icon + ' ';
    } else if (cospend.projects[projectid].categories.hasOwnProperty(categoryid)) {
        categoryChar = (cospend.projects[projectid].categories[categoryid].icon || '') + ' ';
    }
    const whatFormatted = paymentmodeChar + categoryChar + what.replace(/https?:\/\/[^\s]+/gi, '');
    $('.bill-title').html(
        '<span class="loading-bill"></span>' +
        '<span class="icon-edit-white"></span>' +
        t('cospend', 'Bill : {what}', {what: whatFormatted}) +
        ' ' + formattedLinks
    );
    $('.bill-title').attr('style', 'background-color: ' + c + ';');
    updateAmountEach(projectid);
}

export function displayBill(projectid, billid) {
    // select bill item
    $('.billitem').removeClass('selectedbill');
    $('.billitem[billid=' + billid + ']').addClass('selectedbill');

    const bill = cospend.bills[projectid][billid];

    const billMom = moment.unix(bill.timestamp);
    const billDate = billMom.format('YYYY-MM-DD');
    const billTime = billMom.format('HH:mm');

    const owers = bill.owers;
    const owerIds = [];
    for (let i = 0; i < owers.length; i++) {
        owerIds.push(owers[i].id);
    }

    let c = '#888888';
    let owerCheckboxes = '';
    let payerOptions = '';
    let member;
    let selected, checked, readonly;
    let color, imgurl;
    for (const memberid in cospend.members[projectid]) {
        member = cospend.members[projectid][memberid];
        // payer
        selected = '';
        if (member.id === bill.payer_id) {
            selected = ' selected';
        }
        // show member if it's the payer or if it's activated
        if (member.activated || member.id === bill.payer_id) {
            payerOptions = payerOptions + '<option value="' + member.id + '"' + selected + '>' + member.name + '</option>';
        }
        // owers
        checked = '';
        if (owerIds.indexOf(member.id) !== -1) {
            checked = ' checked';
        }
        readonly = '';
        if (!member.activated) {
            readonly = ' disabled';
        }
        // show member if it's an ower or if it's activated
        if (member.activated || owerIds.indexOf(member.id) !== -1) {
            color = cospend.members[projectid][member.id].color;
            imgurl = generateUrl('/apps/cospend/getAvatar?color=' + color + '&name=' + encodeURIComponent(member.name));
            owerCheckboxes = owerCheckboxes +
                '<div class="owerEntry">' +
                '<div class="owerAvatar' + (cospend.members[projectid][member.id].activated ? '' : ' owerAvatarDisabled') + '">' +
                '   <div class="disabledMask"></div>' +
                '   <img src="' + imgurl + '"/>' +
                '</div>' +
                '<input id="' + projectid + member.id + '" owerid="' + member.id + '" class="checkbox" type="checkbox"' + checked + readonly + '/>' +
                '<label for="' + projectid + member.id + '" class="checkboxlabel">' + member.name + '</label> ' +
                '<input id="amount' + projectid + member.id + '" owerid="' + member.id + '" class="amountinput" type="number" value="" step="0.01" min="0"/>' +
                '<label for="amount' + projectid + member.id + '" class="numberlabel">' + member.name + '</label>' +
                '<label class="spentlabel"></label>' +
                '</div>';
        }
    }
    let payerDisabled = '';
    if (billid !== 0) {
        // disable payer select if bill is not new
        if (!cospend.members[projectid][bill.payer_id].activated) {
            payerDisabled = ' disabled';
        }
        const memberPayer = cospend.members[projectid][bill.payer_id];
        c = '#' + (memberPayer.color || '888888');
    }
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const whatStr = t('cospend', 'What?');
    const amountStr = t('cospend', 'How much?');
    const payerStr = t('cospend', 'Who payed?');
    const dateStr = t('cospend', 'When?');
    const owersStr = t('cospend', 'For whom?');

    const links = bill.what.match(/https?:\/\/[^\s]+/gi) || [];
    let formattedLinks = '';
    for (let i = 0; i < links.length; i++) {
        formattedLinks = formattedLinks + '<a href="' + links[i] + '" target="blank">[🔗 ' + t('cospend', 'link') + ']</a> ';
    }
    let paymentmodeChar = '';
    // c b f card, cash, check
    if (cospend.paymentModes.hasOwnProperty(bill.paymentmode)) {
        paymentmodeChar = cospend.paymentModes[bill.paymentmode].icon + ' ';
    }
    let categoryChar = '';
    if (cospend.categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = cospend.categories[bill.categoryid].icon + ' ';
    }
    if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
    }
    const whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '');
    const titleStr = t('cospend', 'Bill : {what}', {what: whatFormatted});

    const allStr = t('cospend', 'All');
    const noneStr = t('cospend', 'None');
    const owerValidateStr = t('cospend', 'Create the bill');
    const addFileLinkText = t('cospend', 'Attach public link to personal file');
    const normalBillOption = t('cospend', 'Classic, even split');
    const normalBillHint = t('cospend', 'Classic mode: Choose a payer, enter a bill amount and select who is concerned by the whole spending, the bill is then split equitably between selected members. Real life example: One person pays the whole restaurant bill and everybody agrees to evenly split the cost.');
    const customBillOption = t('cospend', 'Custom owed amount per member');
    const customBillHint = t('cospend', 'Custom mode, uneven split: Choose a payer, ignore the bill amount (which is disabled) and enter a custom owed amount for each member who is concerned. Then press "Create the bills". Multiple bills will be created. Real life example: One person pays the whole restaurant bill but there are big price differences between what each person ate.');
    const personalShareBillOption = t('cospend', 'Even split with optional personal parts');
    const personalShareBillHint = t('cospend', 'Classic+personal mode: This mode is similar to the classic one. Choose a payer and enter a bill amount corresponding to what was actually payed. Then select who is concerned by the bill and optionally set an amount related to personal stuff for some members. Multiple bills will be created: one for the shared spending and one for each personal part. Real life example: We go shopping, part of what was bought concerns the group but someone also added something personal (like a shirt) which the others don\'t want to collectively pay.');
    const billTypeStr = t('cospend', 'Bill type');
    const paymentModeStr = t('cospend', 'Payment mode');
    const categoryStr = t('cospend', 'Category');
    const currencyConvertStr = t('cospend', 'Convert in');
    const timeStr = t('cospend', 'What time?');

    let addFileHtml = '';
    if (!cospend.pageIsPublic) {
        addFileHtml = '<button id="addFileLinkButton"><span class="icon-public"></span>' + addFileLinkText + '</button>';
    }

    let currenciesStr = '';
    if (cospend.projects[projectid].currencyname && cospend.projects[projectid].currencies.length > 0) {
        currenciesStr =
            '<div class="bill-currency-convert">' +
            '<label for="bill-currency">' +
            '    <a class="icon icon-currencies"></a>' +
            '    ' + currencyConvertStr +
            '</label>' +
            '<select id="bill-currency">' +
            '    <option value="">' + cospend.projects[projectid].currencyname + '</option>';
        let currency;
        for (let i = 0; i < cospend.projects[projectid].currencies.length; i++) {
            currency = cospend.projects[projectid].currencies[i];
            currenciesStr += '<option value="' + currency.id + '">' +
                currency.name + ' ⇒ ' + cospend.projects[projectid].currencyname + ' (x' + currency.exchange_rate + ')' +
                '</option>';
        }
        currenciesStr += '</select></div>';
    }

    let detail =
        '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
        '<h2 class="bill-title" projectid="' + projectid + '" billid="' + bill.id + '" style="background-color: ' + c + ';">' +
        '    <span class="loading-bill"></span>' +
        '    <span class="icon-edit-white"></span>' + titleStr + ' ' + formattedLinks +
        '    <button id="owerValidate"><span class="icon-confirm"></span> <span id="owerValidateText">' + owerValidateStr + '</span></button>' +
        '</h2>' +
        '<div class="bill-form">' +
        '    <div class="bill-left">' +
        '        <div class="bill-what">' +
        '            <label for="what">' +
        '                <a class="icon icon-tag"></a>' +
        '                ' + whatStr +
        '            </label>' +
        '            <input type="text" id="what" maxlength="300" class="input-bill-what" value="' + bill.what + '"/>' +
        '        </div>' + addFileHtml +
        '        <div class="bill-amount">' +
        '            <label for="amount">' +
        '                <a class="icon icon-cospend"></a>' +
        '                ' + amountStr +
        '            </label>' +
        '            <input type="number" id="amount" class="input-bill-amount" value="' + bill.amount + '" step="any"/>' +
        '        </div>' +
        '        ' + currenciesStr +
        '        <div class="bill-payer">' +
        '            <label for="payer">' +
        '                <a class="icon icon-user"></a>' +
        '                ' + payerStr +
        '            </label>' +
        '            <select id="payer" class="input-bill-payer"' + payerDisabled + '>' +
        '                ' + payerOptions +
        '            </select>' +
        '        </div>' +
        '        <div class="bill-date">' +
        '            <label for="date">' +
        '                <a class="icon icon-calendar-dark"></a>' +
        '                ' + dateStr +
        '            </label>' +
        '            <input type="date" id="date" class="input-bill-date" value="' + billDate + '"/>' +
        '        </div>' +
        '        <div class="bill-time">' +
        '            <label for="time">' +
        '                <a class="icon icon-time"></a>' +
        '                ' + timeStr +
        '            </label>' +
        '            <input type="time" id="time" class="input-bill-time" value="' + billTime + '"/>' +
        '        </div>' +
        '        <div class="bill-repeat">' +
        '            <label for="repeatbill">' +
        '                <a class="icon icon-play-next"></a>' +
        '                ' + t('cospend', 'Repeat') +
        '            </label>' +
        '            <select id="repeatbill">' +
        '               <option value="n" selected>' + t('cospend', 'No') + '</option>' +
        '               <option value="d">' + t('cospend', 'Daily') + '</option>' +
        '               <option value="w">' + t('cospend', 'Weekly') + '</option>' +
        '               <option value="m">' + t('cospend', 'Monthly') + '</option>' +
        '               <option value="y">' + t('cospend', 'Yearly') + '</option>' +
        '            </select>' +
        '        </div>' +
        '        <div class="bill-repeat-extra">' +
        '            <div class="bill-repeat-include">' +
        '               <input id="repeatallactive" class="checkbox" type="checkbox"/>' +
        '               <label for="repeatallactive" class="checkboxlabel">' +
        '                   ' + t('cospend', 'Include all active member on repeat') +
        '               </label><br/>' +
        '            </div>' +
        '            <div class="bill-repeat-until">' +
        '               <label for="repeatuntil">' +
        '                    <a class="icon icon-pause"></a>' +
        '                   ' + t('cospend', 'Repeat until') +
        '               </label> ' +
        '               <input type="date" id="repeatuntil" class="input-bill-repeatuntil" value="' + bill.repeatuntil + '"/>' +
        '            </div>' +
        '        </div>' +
        '        <div class="bill-payment-mode">' +
        '            <label for="payment-mode">' +
        '                <a class="icon icon-tag"></a>' +
        '                ' + paymentModeStr +
        '            </label>' +
        '            <select id="payment-mode">' +
        '               <option value="n" selected>' + t('cospend', 'None') + '</option>';
    let pm;
    for (const pmId in cospend.paymentModes) {
        pm = cospend.paymentModes[pmId];
        detail += '       <option value="' + pmId + '">' + pm.icon + ' ' + pm.name + '</option>';
    }
    detail +=
        '            </select>' +
        '        </div>' +
        '        <div class="bill-category">' +
        '            <label for="category">' +
        '                <a class="icon icon-category-app-bundles"></a>' +
        '                ' + categoryStr +
        '            </label>' +
        '            <select id="category">' +
        '               <option value="0" selected>' + t('cospend', 'None') + '</option>';
    let cat;
    for (const catId in cospend.projects[projectid].categories) {
        cat = cospend.projects[projectid].categories[catId];
        detail += '       <option value="' + catId + '">' + (cat.icon || '') + ' ' + cat.name + '</option>';
    }
    for (const catId in cospend.categories) {
        cat = cospend.categories[catId];
        detail += '       <option value="' + catId + '">' + cat.icon + ' ' + cat.name + '</option>';
    }
    detail +=
        '            </select>' +
        '        </div>' +
        '    </div>' +
        '    <div class="bill-right">' +
        '        <div class="bill-type">' +
        '            <label class="bill-owers-label">' +
        '                <a class="icon icon-toggle-filelist"></a><span>' + billTypeStr + '</span>' +
        '            </label>' +
        '            <select id="billtype">' +
        '               <option value="normal" selected>' + normalBillOption + '</option>' +
        '               <option value="perso">' + personalShareBillOption + '</option>' +
        '               <option value="custom">' + customBillOption + '</option>' +
        '            </select>' +
        '            <button id="modehintbutton"><span class="icon-details"></span></button>' +
        '            <div class="modehint modenormal">' + normalBillHint + '</div>' +
        '            <div class="modehint modeperso">' + personalShareBillHint + '</div>' +
        '            <div class="modehint modecustom">' + customBillHint + '</div>' +
        '        </div>' +
        '        <div class="bill-owers">' +
        '            <label class="bill-owers-label">' +
        '                <a class="icon icon-group"></a><span>' + owersStr + '</span>' +
        '            </label>' +
        '            <div class="owerAllNoneDiv">' +
        '            <button id="owerAll"><span class="icon-group"></span> ' + allStr + '</button>' +
        '            <button id="owerNone"><span class="icon-disabled-users"></span> ' + noneStr + '</button>' +
        '            </div>' +
        '            ' + owerCheckboxes +
        '        </div>' +
        '    </div>' +
        '</div>';

    $(detail).appendTo('#billdetail');
    $('#billdetail .input-bill-what').focus().select();
    if (billid !== 0) {
        $('#repeatbill').val(bill.repeat);
        $('#payment-mode').val(bill.paymentmode || 'n');
        if (cospend.categories.hasOwnProperty(bill.categoryid) ||
            cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
            $('#category').val(bill.categoryid);
        } else {
            $('#category').val(0);
        }
        $('#repeatallactive').prop('checked', bill.repeatallactive || false);
        if (bill.repeat === 'n') {
            $('.bill-repeat-extra').hide();
        }
    } else {
        $('.bill-type').show();
        $('#owerValidate').show();
        $('.bill-repeat-extra').hide();
    }
    updateAmountEach(projectid);

    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        $('#billdetail button').hide();
        $('#billdetail input').each(function() {
            $(this).prop('readonly', true);
        });
        $('#billdetail select, #billdetail input[type=checkbox]').each(function() {
            $(this).prop('disabled', true);
        });
    }
}

export function addBill(projectid, bill) {
    cospend.bills[projectid][bill.id] = bill;

    const billMom = moment.unix(bill.timestamp);
    const billDate = billMom.format('YYYY-MM-DD');
    const billTime = billMom.format('HH:mm');

    let owerNames = '';
    for (let i = 0; i < bill.owers.length; i++) {
        const ower = bill.owers[i];
        if (!cospend.members[projectid].hasOwnProperty(ower.id)) {
            reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
            return;
        }
        owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
    }
    owerNames = owerNames.replace(/, $/, '');
    let title = '';
    let memberName = '';

    const links = bill.what.match(/https?:\/\/[^\s]+/gi) || [];
    let formattedLinks = '';
    let linkChars = '';
    for (let i = 0; i < links.length; i++) {
        formattedLinks = formattedLinks + '<a href="' + links[i] + '" target="blank">[' + t('cospend', 'link') + ']</a> ';
        linkChars = linkChars + '  🔗';
    }
    let paymentmodeChar = '';
    // c b f card, cash, check
    if (cospend.paymentModes.hasOwnProperty(bill.paymentmode)) {
        paymentmodeChar = cospend.paymentModes[bill.paymentmode].icon + ' ';
    }
    let categoryChar = '';
    if (cospend.categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = cospend.categories[bill.categoryid].icon + ' ';
    }
    if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
    }
    const whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars;

    let imgurl, color;
    let disabled = '';
    let showRepeat = '';
    if (bill.id !== 0) {
        if (!cospend.members[projectid].hasOwnProperty(bill.payer_id)) {
            reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
            return;
        }
        memberName = getMemberName(projectid, bill.payer_id);

        title = whatFormatted + '\n' + bill.amount.toFixed(2) + '\n' +
            billDate + ' ' + billTime + '\n' + memberName + ' → ' + owerNames;

        color = cospend.members[projectid][bill.payer_id].color;
        imgurl = generateUrl('/apps/cospend/getAvatar?color=' + color + '&name=' + encodeURIComponent(memberName));
        // disabled
        disabled = cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled';
        showRepeat = bill.repeat === 'n' ? '' : ' show';
    } else {
        imgurl = generateUrl('/apps/cospend/getAvatar?name=' + encodeURIComponent(' '));
    }
    const item = '<a href="#" class="app-content-list-item billitem" billid="' + bill.id + '" projectid="' + projectid + '" title="' + title + '">' +
        '<div class="app-content-list-item-icon" style="background-image: url(' + imgurl + ');"> ' +
        '   <div class="billItemDisabledMask' + disabled + '"></div>' +
        '   <div class="billItemRepeatMask' + showRepeat + '"></div>' +
        '</div>' +
        '<div class="app-content-list-item-line-one">' + whatFormatted + '</div>' +
        '<div class="app-content-list-item-line-two">' + bill.amount.toFixed(2) + ' (' + memberName + ' → ' + owerNames + ')</div>' +
        '<span class="app-content-list-item-details">' + billDate + '</span>' +
        '<div class="icon-delete deleteBillIcon"></div>' +
        '<div class="icon-history undoDeleteBill" style="' + undoDeleteBillStyle + '" title="Undo"></div>' +
        '</a>';
    $(item).prependTo('.app-content-list');

    $('#bill-list .nobill').remove();

    if (parseInt(getUrlParameter('bill')) === bill.id && getUrlParameter('project') === projectid) {
        displayBill(projectid, bill.id);
    }
    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        $('.billitem[billid=' + bill.id + '] .deleteBillIcon').hide();
    }
}

export function createNormalBill() {
    // get bill info
    const projectid = $('.bill-title').attr('projectid');

    let what = $('.input-bill-what').val();
    const date = $('.input-bill-date').val();
    let time = $('.input-bill-time').val();
    if (!time || time === '') {
        time = '00:00';
    }
    let amount = parseFloat($('.input-bill-amount').val());
    const payer_id = parseInt($('.input-bill-payer').val());
    const repeat = $('#repeatbill').val();
    const repeatallactive = $('#repeatallactive').is(':checked') ? 1 : 0;
    const repeatuntil = $('.input-bill-repeatuntil').val();
    const paymentmode = $('#payment-mode').val();
    const categoryid = $('#category').val();

    let valid = basicBillValueCheck(what, date, time, amount, payer_id);

    const owerIds = [];
    let owerId;
    $('.owerEntry input').each(function() {
        if ($(this).is(':checked')) {
            owerId = parseInt($(this).attr('owerid'));
            if (isNaN(owerId)) {
                valid = false;
            } else {
                owerIds.push(owerId);
            }
        }
    });

    if (owerIds.length === 0) {
        valid = false;
    }

    // if valid, save the bill or create it if needed
    if (valid) {
        // manage currencies
        if ($('#bill-currency') && $('#bill-currency').val()) {
            const currencyId = $('#bill-currency').val();
            const currencies = cospend.projects[projectid].currencies;
            let currency = null;
            for (let i = 0; i < currencies.length; i++) {
                if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                    currency = currencies[i];
                    break;
                }
            }
            if (currency) {
                const userAmount = amount;
                amount = amount * currency.exchange_rate;
                $('#amount').val(amount);
                what += ' (' + userAmount.toFixed(2) + ' ' + currency.name + ')';
                $('#what').val(what);
                $('#bill-currency').val('');
            }
        }
        // get timestamp
        const timestamp = moment(date + ' ' + time).unix();
        createBill(projectid, what, amount, payer_id, timestamp, owerIds, repeat, false,
            paymentmode, categoryid, repeatallactive, repeatuntil);
    } else {
        Notification.showTemporary(t('cospend', 'Bill values are not valid'));
    }
}

export function onBillEdited(amountChanged = false) {
    // get bill info
    const billid = $('.bill-title').attr('billid');
    const projectid = $('.bill-title').attr('projectid');
    updateAmountEach(projectid);

    // if this is a new bill : get out
    if (billid === '0') {
        return;
    }

    let what = $('.input-bill-what').val();
    const date = $('.input-bill-date').val();
    let time = $('.input-bill-time').val();
    if (!time || time === '') {
        time = '00:00';
    }
    let amount = parseFloat($('.input-bill-amount').val());
    const payer_id = parseInt($('.input-bill-payer').val());
    const repeat = $('#repeatbill').val();
    const repeatallactive = $('#repeatallactive').is(':checked') ? 1 : 0;
    const repeatuntil = $('.input-bill-repeatuntil').val();
    const paymentmode = $('#payment-mode').val();
    const categoryid = $('#category').val();

    let valid = basicBillValueCheck(what, date, time, amount, payer_id);

    const owerIds = [];
    let owerId;
    $('.owerEntry input').each(function() {
        if ($(this).is(':checked')) {
            owerId = parseInt($(this).attr('owerid'));
            if (isNaN(owerId)) {
                valid = false;
            } else {
                owerIds.push(owerId);
            }
        }
    });

    if (owerIds.length === 0) {
        valid = false;
    }

    // if valid, save the bill
    if (valid) {
        if (amountChanged) {
            what = cleanStringFromCurrency(projectid, what);
            $('#what').val(what);
        }
        // manage currencies
        if ($('#bill-currency') && $('#bill-currency').val()) {
            const currencyId = $('#bill-currency').val();
            const currencies = cospend.projects[projectid].currencies;
            let currency = null;
            for (let i = 0; i < currencies.length; i++) {
                if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                    currency = currencies[i];
                    break;
                }
            }
            if (currency) {
                const userAmount = amount;
                amount = amount * currency.exchange_rate;
                $('#amount').val(amount);
                what = cleanStringFromCurrency(projectid, what);
                what += ' (' + userAmount.toFixed(2) + ' ' + currency.name + ')';
                $('#what').val(what);
                $('#bill-currency').val('');
            }
        }
        // if values have changed, save the bill
        const oldBill = cospend.bills[projectid][billid];
        // if ower lists don't have the same length, it has changed
        let owersChanged = (oldBill.owers.length !== owerIds.length);
        // same length : check content
        if (!owersChanged) {
            for (let i = 0; i < oldBill.owers.length; i++) {
                if (owerIds.indexOf(oldBill.owers[i].id) === -1) {
                    owersChanged = true;
                    break;
                }
            }
        }
        // get timestamp
        const timestamp = moment(date + ' ' + time).unix();
        if (oldBill.what !== what ||
            oldBill.amount !== amount ||
            oldBill.timestamp !== timestamp ||
            oldBill.repeat !== repeat ||
            oldBill.repeatallactive !== repeatallactive ||
            oldBill.repeatuntil !== repeatuntil ||
            oldBill.payer_id !== payer_id ||
            oldBill.categoryid !== categoryid ||
            oldBill.paymentmode !== paymentmode ||
            owersChanged
        ) {
            saveBill(projectid, billid, what, amount, payer_id, timestamp, owerIds, repeat,
                paymentmode, categoryid, repeatallactive, repeatuntil);
        }
    } else {
        Notification.showTemporary(t('cospend', 'Bill values are not valid'));
    }
}

// create equitable bill with personal parts
export function createEquiPersoBill() {
    const projectid = $('.bill-title').attr('projectid');

    let what = $('.input-bill-what').val();
    const date = $('.input-bill-date').val();
    let time = $('.input-bill-time').val();
    if (!time || time === '') {
        time = '00:00';
    }
    const amount = parseFloat($('.input-bill-amount').val());
    const payer_id = parseInt($('.input-bill-payer').val());
    const repeat = 'n';
    const repeatallactive = 0;
    const repeatuntil = null;
    const paymentmode = $('#payment-mode').val();
    const categoryid = $('#category').val();

    let valid = basicBillValueCheck(what, date, time, amount, payer_id);

    const owerIds = [];
    let owerId;
    $('.owerEntry input').each(function() {
        if ($(this).is(':checked')) {
            owerId = parseInt($(this).attr('owerid'));
            if (isNaN(owerId)) {
                valid = false;
            } else {
                owerIds.push(owerId);
            }
        }
    });

    let tmpAmount;
    if (isNaN(amount) || isNaN(payer_id)) {
        valid = false;
    } else {
        // check if amount - allPersonalParts >= 0
        tmpAmount = amount;
        $('.amountinput').each(function() {
            const owerId = parseInt($(this).attr('owerid'));
            const amountVal = parseFloat($(this).val());
            const owerSelected = $('.owerEntry input[owerid="' + owerId + '"]').is(':checked');
            if (!isNaN(amountVal) && amountVal > 0.0 && owerSelected) {
                tmpAmount = tmpAmount - amountVal;
            }
        });
        if (tmpAmount < 0.0) {
            Notification.showTemporary(t('cospend', 'Personal parts are bigger than the paid amount'));
            return;
        }
    }
    if (owerIds.length === 0) {
        valid = false;
    }

    if (valid) {
        const initWhat = what;
        // manage currencies
        let currency = null;
        let initAmount;
        if ($('#bill-currency') && $('#bill-currency').val()) {
            const currencyId = $('#bill-currency').val();
            const currencies = cospend.projects[projectid].currencies;
            for (let i = 0; i < currencies.length; i++) {
                if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                    currency = currencies[i];
                    break;
                }
            }
        }
        // get timestamp
        const timestamp = moment(date + ' ' + time).unix();
        // create bills related to personal parts
        tmpAmount = amount;
        $('.amountinput').each(function() {
            let oneWhat = initWhat;
            const owerId = parseInt($(this).attr('owerid'));
            let amountVal = parseFloat($(this).val());
            const owerSelected = $('.owerEntry input[owerid="' + owerId + '"]').is(':checked');
            if (!isNaN(amountVal) && amountVal > 0.0 && owerSelected) {
                tmpAmount = tmpAmount - amountVal;
                if (currency !== null) {
                    initAmount = amountVal;
                    amountVal = amountVal * currency.exchange_rate;
                    oneWhat += ' (' + initAmount.toFixed(2) + ' ' + currency.name + ')';
                }
                createBill(projectid, oneWhat, amountVal, payer_id, timestamp, [owerId], repeat, true,
                    paymentmode, categoryid, repeatallactive, repeatuntil);
            }
        });
        // currency conversion for main amount
        if (currency) {
            const userAmount = tmpAmount;
            tmpAmount = tmpAmount * currency.exchange_rate;
            $('#amount').val(tmpAmount);
            what += ' (' + userAmount.toFixed(2) + ' ' + currency.name + ')';
            $('#what').val(what);
            $('#bill-currency').val('');
        }
        // create equitable bill with the rest
        createBill(projectid, what, tmpAmount, payer_id, timestamp, owerIds, repeat, true,
            paymentmode, categoryid, repeatallactive, repeatuntil);
        // empty bill detail
        $('#billdetail').html('');
        // remove new bill line
        $('.billitem[billid=0]').fadeOut('normal', function() {
            $(this).remove();
            if ($('.billitem').length === 0) {
                $('#bill-list').html('<h2 class="nobill">' + t('cospend', 'No bill yet') + '</h2>');
            }
        });
        $('.app-content-list').removeClass('showdetails');
    } else {
        Notification.showTemporary(t('cospend', 'Invalid values'));
    }
}

export function createCustomAmountBill() {
    const projectid = $('.bill-title').attr('projectid');

    const what = $('.input-bill-what').val();
    const date = $('.input-bill-date').val();
    let time = $('.input-bill-time').val();
    if (!time || time === '') {
        time = '00:00';
    }
    const amount = parseFloat($('.input-bill-amount').val());
    const payer_id = parseInt($('.input-bill-payer').val());
    const repeat = 'n';
    const repeatallactive = 0;
    const repeatuntil = null;
    const paymentmode = $('#payment-mode').val();
    const categoryid = $('#category').val();

    const valid = basicBillValueCheck(what, date, time, amount, payer_id);

    if (valid) {
        const initWhat = what;
        // manage currencies
        let initAmount;
        let currency = null;
        if ($('#bill-currency') && $('#bill-currency').val()) {
            const currencyId = $('#bill-currency').val();
            const currencies = cospend.projects[projectid].currencies;
            for (let i = 0; i < currencies.length; i++) {
                if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                    currency = currencies[i];
                    break;
                }
            }
        }
        // get timestamp
        const timestamp = moment(date + ' ' + time).unix();
        let total = 0;
        $('.amountinput').each(function() {
            let oneWhat = initWhat;
            const owerId = parseInt($(this).attr('owerid'));
            let amountVal = parseFloat($(this).val());
            if (!isNaN(amountVal) && amountVal > 0.0) {
                total = total + amountVal;
                if (currency !== null) {
                    initAmount = amountVal;
                    amountVal = amountVal * currency.exchange_rate;
                    oneWhat += ' (' + initAmount.toFixed(2) + ' ' + currency.name + ')';
                }
                createBill(projectid, oneWhat, amountVal, payer_id, timestamp, [owerId], repeat, true,
                    paymentmode, categoryid, repeatallactive, repeatuntil);
            }
        });
        // if something was actually created, clean up
        if (total > 0) {
            // empty bill detail
            $('#billdetail').html('');
            // remove new bill line
            $('.billitem[billid=0]').fadeOut('normal', function() {
                $(this).remove();
                if ($('.billitem').length === 0) {
                    $('#bill-list').html('<h2 class="nobill">' + t('cospend', 'No bill yet') + '</h2>');
                }
            });
            $('.app-content-list').removeClass('showdetails');
        } else {
            Notification.showTemporary(t('cospend', 'There is no custom amount'));
        }
    } else {
        Notification.showTemporary(t('cospend', 'Invalid values'));
    }
}

function updateAmountEach(projectid) {
    const amount = $('#amount').val();
    const nbChecked = $('.owerEntry .checkbox:checked').length;
    let weightSum = 0;
    let oneWeight, mid, owerVal;
    const billType = $('#billtype').val();
    const billId = parseInt($('#billdetail .bill-title').attr('billid'));
    $('.spentlabel').text('');
    if (nbChecked > 0 &&
        (billId !== 0 || billType === 'normal') &&
        !isNaN(amount) &&
        parseFloat(amount) > 0.0) {
        $('.owerEntry .checkbox:checked').each(function() {
            mid = $(this).attr('owerid');
            weightSum += cospend.members[projectid][mid].weight;
        });
        oneWeight = parseFloat(amount) / weightSum;
        $('.owerEntry .checkbox:checked').each(function() {
            mid = $(this).attr('owerid');
            owerVal = oneWeight * cospend.members[projectid][mid].weight;
            $(this).parent().find('.spentlabel').text('(' + owerVal.toFixed(2) + ')');
        });
    }
}

function basicBillValueCheck(what, date, time, amount, payer_id) {
    let valid = true;
    if (what === null || what === '' || what.match(',')) {
        valid = false;
    }
    if (date === null || date === '' || date.match(/^\d\d\d\d-\d\d-\d\d$/g) === null) {
        valid = false;
    }
    if (time === null || time === '' || time.match(/^\d\d:\d\d$/g) === null) {
        valid = false;
    }
    if (isNaN(amount) || isNaN(payer_id)) {
        valid = false;
    }
    return valid;
}
