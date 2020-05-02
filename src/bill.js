/*jshint esversion: 6 */

import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import {getCurrentUser} from '@nextcloud/auth';
import * as constants from './constants';
import cospend from './state';
import {updateProjectBalances} from './project';
import {getUrlParameter, reload} from './utils';
import {getMemberName, getMemberAvatar} from './member';
import {
    delay,
    Timer,
    generatePublicLinkToFile,
    updateCustomAmount
} from './utils';

const undoDeleteBillStyle = 'opacity:1; background-image: url(' + generateUrl('/svg/core/actions/history?color=2AB4FF') + ');';

function searchBills(qs) {
    const pid = cospend.currentProjectId;
    // Make sure to escape user input before creating regex from it:
    var regex = new RegExp(qs.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&"), "i");
    let billItem;
    for (let bid in cospend.bills[pid]) {
        billItem = $('#bill-list .billitem[billid='+bid+']');
        if (regex.test(cospend.bills[pid][bid].what)) {
            billItem.show();
        } else {
            billItem.hide();
        }
    }
}

function resetSearchBills() {
    $('.billitem').show();
}

export function billEvents() {
    cospend.search = new OCA.Search(searchBills, resetSearchBills);

    $('body').on('click', '.billitem', function (e) {
        if (!$(e.target).hasClass('deleteBillIcon') && !$(e.target).hasClass('undoDeleteBill')) {
            const billid = parseInt($(this).attr('billid'));
            const projectid = $(this).attr('projectid');
            displayBill(projectid, billid);
        }
    });

    // what and amount : delay on edition
    $('body').on('keyup paste change', '.input-bill-what, .input-bill-comment', delay(function () {
        onBillEdited();
    }, 2000));
    $('body').on('keyup paste change', '.input-bill-amount', delay(function () {
        onBillEdited(true);
    }, 2000));

    // other bill fields : direct on edition
    $('body').on('change', '.input-bill-date, .input-bill-time, .input-bill-repeatuntil, #billdetail .bill-form select', function () {
        onBillEdited();
    });
    $('body').on('click', '#repeatallactive', function () {
        onBillEdited();
    });

    // show/hide repeatallactive
    $('body').on('change', '#repeatbill', function () {
        if ($(this).val() === 'n') {
            $('.bill-repeat-extra').slideUp();
        } else {
            $('.bill-repeat-extra').slideDown();
        }
    });

    $('body').on('change', '#billdetail .bill-form .bill-owers input[type=checkbox]', function () {
        const billtype = $('#billtype').val();
        if (billtype === 'perso') {
            if ($(this).is(':checked')) {
                $(this).parent().find('input[type=number]').show();
            } else {
                $(this).parent().find('input[type=number]').hide();
            }
        } else {
            onBillEdited();
        }
    });

    $('body').on('click', '#owerAll', function () {
        const billtype = $('#billtype').val();
        const projectid = $(this).parent().parent().parent().parent().parent().find('.bill-title').attr('projectid');
        for (const memberid in cospend.members[projectid]) {
            if (cospend.members[projectid][memberid].activated) {
                $('.bill-owers input[owerid=' + memberid + ']').prop('checked', true);
            }
        }
        if (billtype === 'perso') {
            $('.bill-owers .amountinput').show();
        }
        //$('.owerEntry input').prop('checked', true);
        onBillEdited();
    });

    $('body').on('click', '#owerNone', function () {
        const billtype = $('#billtype').val();
        const projectid = $(this).parent().parent().parent().parent().parent().find('.bill-title').attr('projectid');
        for (const memberid in cospend.members[projectid]) {
            if (cospend.members[projectid][memberid].activated) {
                $('.bill-owers input[owerid=' + memberid + ']').prop('checked', false);
            }
        }
        if (billtype === 'perso') {
            $('.bill-owers .amountinput').hide();
        }
        //$('.owerEntry input').prop('checked', false);
        onBillEdited();
    });

    $('body').on('click', '.undoDeleteBill', function () {
        const billid = $(this).parent().attr('billid');
        cospend.billDeletionTimer[billid].pause();
        delete cospend.billDeletionTimer[billid];
        $(this).parent().find('.deleteBillIcon').show();
        $(this).parent().removeClass('deleted');
        $(this).hide();
    });

    $('body').on('click', '.deleteBillIcon', function () {
        const billid = $(this).parent().attr('billid');
        if (billid !== '0') {
            const projectid = $(this).parent().attr('projectid');
            $(this).parent().find('.undoDeleteBill').show();
            $(this).parent().addClass('deleted');
            $(this).hide();
            cospend.billDeletionTimer[billid] = new Timer(function () {
                deleteBill(projectid, billid);
            }, 7000);
        } else {
            if ($('.bill-title').length > 0 && $('.bill-title').attr('billid') === billid) {
                $('#billdetail').html('');
            }
            $(this).parent().fadeOut('normal', function () {
                $(this).remove();
                if ($('.billitem').length === 0) {
                    $('#bill-list').html('').append($('<h2/>', {class: 'nobill'}).text(t('cospend', 'No bill yet')));
                }
            });
        }
    });

    $('body').on('click', '#newBillButton', function () {
        const projectid = cospend.currentProjectId;
        const activatedMembers = [];
        for (const mid in cospend.members[projectid]) {
            if (cospend.members[projectid][mid].activated) {
                activatedMembers.push(mid);
            }
        }
        if (activatedMembers.length > 1) {
            if (cospend.currentProjectId !== null && $('.billitem[billid=0]').length === 0) {
                const bill = {
                    id: 0,
                    what: t('cospend', 'New Bill'),
                    timestamp: moment().unix(),
                    amount: 0.0,
                    payer_id: 0,
                    repeat: 'n',
                    owers: []
                };
                addBill(projectid, bill);
            }
            displayBill(projectid, 0);
        } else {
            Notification.showTemporary(t('cospend', '2 active members are required to create a bill'));
        }
    });

    $('body').on('click', '#addFileLinkButton', function() {
        OC.dialogs.filepicker(
            t('cospend', 'Choose file'),
            function(targetPath) {
                generatePublicLinkToFile(targetPath, onBillEdited);
            },
            false, null, true
        );
    });

    $('body').on('click', '#modehintbutton', function() {
        const billtype = $('#billtype').val();
        if (billtype === 'normal') {
            if ($('.modenormal').is(':visible')) {
                $('.modenormal').slideUp();
            } else {
                $('.modenormal').slideDown();
            }
            $('.modecustom').slideUp();
            $('.modeperso').slideUp();
        } else if (billtype === 'perso') {
            if ($('.modeperso').is(':visible')) {
                $('.modeperso').slideUp();
            } else {
                $('.modeperso').slideDown();
            }
            $('.modecustom').slideUp();
            $('.modenormal').slideUp();
        } else if (billtype === 'custom') {
            if ($('.modecustom').is(':visible')) {
                $('.modecustom').slideUp();
            } else {
                $('.modecustom').slideDown();
            }
            $('.modenormal').slideUp();
            $('.modeperso').slideUp();
        }
    });

    $('body').on('change', '#billtype', function() {
        $('.modehint').slideUp();
        let owerValidateStr = t('cospend', 'Create the bills');
        const billtype = $(this).val();
        if (billtype === 'normal') {
            owerValidateStr = t('cospend', 'Create the bill');
            $('#owerNone').show();
            $('#owerAll').show();
            $('.bill-owers .checkbox').show();
            $('.bill-owers .checkboxlabel').show();
            $('.bill-owers .numberlabel').hide();
            $('.bill-owers input[type=number]').hide();
            $('#amount').val('0');
            $('#amount').prop('disabled', false);
            $('#repeatbill').prop('disabled', false);
            if ($('#repeatbill').val() !== 'n') {
                $('.bill-repeat-extra').show();
            }
        } else if (billtype === 'custom') {
            $('#owerNone').hide();
            $('#owerAll').hide();
            $('.bill-owers .checkbox').hide();
            $('.bill-owers .checkboxlabel').hide();
            $('.bill-owers .numberlabel').show();
            $('.bill-owers input[type=number]').show();
            updateCustomAmount();
            $('#amount').prop('disabled', true);
            $('#repeatbill').val('n').prop('disabled', true);
            $('.bill-repeat-extra').hide();
        } else if (billtype === 'perso') {
            $('#owerNone').show();
            $('#owerAll').show();
            $('.bill-owers .checkbox').show();
            $('.bill-owers .checkboxlabel').show();
            $('.bill-owers .numberlabel').hide();
            $('.bill-owers input[type=number]').hide();
            $('.bill-owers .checkbox').each(function() {
                if ($(this).is(':checked')) {
                    $(this).parent().find('input[type=number]').show();
                }
            });
            $('#amount').prop('disabled', false);
            $('#repeatbill').val('n').prop('disabled', true);
            $('.bill-repeat-extra').hide();
        }
        $('#owerValidateText').text(owerValidateStr);
    });

    $('body').on('paste change', '.amountinput', function() {
        const billtype = $('#billtype').val();
        if (billtype === 'custom') {
            updateCustomAmount();
        }
    });

    $('body').on('keyup', '.amountinput', function(e) {
        const billtype = $('#billtype').val();
        if (billtype === 'custom') {
            updateCustomAmount();
            if (e.key === 'Enter') {
                createCustomAmountBill();
            }
        } else if (billtype === 'perso') {
            if (e.key === 'Enter') {
                createEquiPersoBill();
            }
        }
    });

    $('body').on('click', '#owerValidate', function() {
        const billtype = $('#billtype').val();
        if (billtype === 'custom') {
            updateCustomAmount();
            createCustomAmountBill();
        } else if (billtype === 'perso') {
            createEquiPersoBill();
        } else if (billtype === 'normal') {
            createNormalBill();
        }
    });

    $('body').on('click', '.owerEntry .owerAvatar', function() {
        const billId = parseInt($('#billdetail .bill-title').attr('billid'));
        const billType = $('#billtype').val();
        if (billId !== 0 || billType === 'normal' || billType === 'perso') {
            $(this).parent().find('input').click();
        }
    });
}

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
                            custom=false, paymentmode=null, categoryid=null, repeatallactive=0,
                            repeatuntil=null, comment=null) {
    $('.loading-bill').addClass('icon-loading-small');
    const req = {
        what: what,
        comment: comment,
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
            comment: comment,
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
                paymentmode, categoryid, repeatallactive, repeatuntil, comment);
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
                          paymentmode=null, categoryid=null, repeatallactive=null, repeatuntil=null,
                          comment=null) {
    $('.loading-bill').addClass('icon-loading-small');
    const req = {
        what: what,
        comment: comment,
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
        cospend.bills[projectid][billid].comment = comment;
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
                paymentmode, categoryid, repeatallactive, repeatuntil, comment);
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
    if (cospend.hardCodedCategories.hasOwnProperty(bill.categoryid)) {
        categoryChar = cospend.hardCodedCategories[bill.categoryid].icon + ' ';
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
    const imgurl = getMemberAvatar(projectid, bill.payer_id);
    const item = $('<a/>', {href: '#', class: 'app-content-list-item billitem' + selectedClass, billid: bill.id, projectid: projectid, title: title})
        .append(
            $('<div/>', {class: 'app-content-list-item-icon', style: 'background-image: url(' + imgurl + ');'})
                .append($('<div/>', {class: 'billItemDisabledMask' + (cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled')}))
                .append($('<div/>', {class: 'billItemRepeatMask' + (bill.repeat === 'n' ? '' : ' show')}))
        )
        .append($('<div/>', {class: 'app-content-list-item-line-one'}).text(whatFormatted))
        .append($('<div/>', {class: 'app-content-list-item-line-two'}).text(bill.amount.toFixed(2) + ' (' + memberName + ' → ' + owerNames + ')'))
        .append($('<span/>', {class: 'app-content-list-item-details'}).text(billDate))
        .append($('<div/>', {class: 'icon-delete deleteBillIcon'}))
        .append($('<div/>', {class: 'icon-history undoDeleteBill', style: undoDeleteBillStyle, title: t('cospend', 'Undo')}))

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
                $('#bill-list').html('').append($('<h2/>', {class: 'nobill'}).text(t('cospend', 'No bill yet')));
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
            $('#bill-list').html('').append($('<h2/>', {class: 'nobill'}).text(t('cospend', 'No bill yet')));
        }
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get bills'));
        $('#bill-list').html('');
    });
}

export function updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                                     paymentmode=null, categoryid=null, repeatallactive=0,
                                     repeatuntil=null, comment=null) {
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
    if (cospend.hardCodedCategories.hasOwnProperty(categoryid)) {
        categoryChar = cospend.hardCodedCategories[categoryid].icon + ' ';
    } else if (cospend.projects[projectid].categories.hasOwnProperty(categoryid)) {
        categoryChar = (cospend.projects[projectid].categories[categoryid].icon || '') + ' ';
    }
    const whatFormatted = paymentmodeChar + categoryChar + what.replace(/https?:\/\/[^\s]+/gi, '');
    $('.bill-title').html('');
    $('.bill-title')
        .append($('<span/>', {class: 'loading-bill'}))
        .append($('<span/>', {class: 'icon-edit-white'}))
        .append(
            t('cospend', 'Bill : {what}', {what: whatFormatted}) +
            ' ' + formattedLinks
        )
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
    let imgurl;
    if (billid !== 0) {
        const memberPayer = cospend.members[projectid][bill.payer_id];
        c = '#' + (memberPayer.color || '888888');
    }
    const whatStr = t('cospend', 'What?');
    const commentStr = t('cospend', 'Comment');
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
    if (cospend.hardCodedCategories.hasOwnProperty(bill.categoryid)) {
        categoryChar = cospend.hardCodedCategories[bill.categoryid].icon + ' ';
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
    const currencyConvertStr = t('cospend', 'Convert to');
    const timeStr = t('cospend', 'What time?');

    $('.app-content-list').addClass('showdetails');
    const container = $('#billdetail');
    container.html('');

    const payerSelect = $('<select/>', {
        id: 'payer', class: 'input-bill-payer',
        disabled: (billid !== 0 && !cospend.members[projectid][bill.payer_id].activated) ? 'disabled' : null
    });
    for (const memberid in cospend.members[projectid]) {
        member = cospend.members[projectid][memberid];
        // show member if it's the payer or if it's activated
        if (member.activated || member.id === bill.payer_id) {
            selected = member.id === bill.payer_id ||
                    (billid === 0 && member.userid === getCurrentUser().uid);
            payerSelect.append($('<option/>', {value: member.id, selected: selected ? 'selected' : null}).text(member.name))
        }
    }

    let currenciesDiv = null;
    if (cospend.projects[projectid].currencyname && cospend.projects[projectid].currencies.length > 0) {
        currenciesDiv = $('<div/>', {class: 'bill-currency-convert'})
            .append(
                $('<label/>', {for: 'bill-currency'})
                    .append($('<a/>', {class: 'icon icon-currencies'}))
                    .append(currencyConvertStr)
            )
            .append(
                $('<select/>', {id: 'bill-currency'})
                    .append($('<option/>', {value: ''}).text(cospend.projects[projectid].currencyname))
            )
        const curSelect = currenciesDiv.find('select');
        let currency;
        for (let i = 0; i < cospend.projects[projectid].currencies.length; i++) {
            currency = cospend.projects[projectid].currencies[i];
            curSelect.append(
                $('<option/>', {value: currency.id})
                    .text(currency.name + ' ⇒ ' + cospend.projects[projectid].currencyname + ' (x' + currency.exchange_rate + ')')
            )
        }
    }

    container.append($('<div/>', {id: 'app-details-toggle', tabindex: 0, class: 'icon-confirm'}))
        .append(
            $('<h2/>', {class: 'bill-title', projectid: projectid, billid: bill.id, style: 'background-color: ' + c + ';'})
                .append($('<span/>', {class: 'loading-bill'}))
                .append($('<span/>', {class: 'icon-edit-white'}))
                .append(titleStr + ' ' + formattedLinks)
                .append(
                    $('<button/>', {id: 'owerValidate'})
                        .append($('<span/> ', {class: 'icon-confirm'}))
                        .append($('<span/>', {id: 'owerValidateText'}).text(owerValidateStr))
                )

        )
        .append(
            $('<div/>', {class: 'bill-form'})
                .append(
                    $('<div/>', {class: 'bill-left'})
                    .append(
                        $('<div/>', {class: 'bill-what'})
                            .append(
                                $('<label/>', {for: 'what'})
                                    .append($('<a/>', {class: 'icon icon-tag'}))
                                    .append(document.createTextNode(whatStr))
                            )
                            .append($('<input/>', {type: 'text', id: 'what', maxlength: 300, class: 'input-bill-what', value: bill.what}))
                    )
                    .append(cospend.pageIsPublic ?
                        null :
                        $('<button/>', {id: 'addFileLinkButton'})
                            .append($('<span/>', {class: 'icon-public'}))
                            .append(document.createTextNode(addFileLinkText))
                    )
                    .append(
                        $('<div/>', {class: 'bill-amount'})
                            .append(
                                $('<label/>', {for: 'amount'})
                                    .append($('<a/>', {class: 'icon icon-cospend'}))
                                    .append(document.createTextNode(amountStr))
                            )
                            .append($('<input/>', {type: 'number', id: 'amount', class: 'input-bill-amount', value: bill.amount, step: 'any'}))
                    )
                    .append(
                        $('<div/>', {class: 'bill-payer'})
                            .append(
                                $('<label/>', {for: 'payer'})
                                    .append($('<a/>', {class: 'icon icon-user'}))
                                    .append(document.createTextNode(payerStr))
                            )
                            .append(payerSelect)
                    )
                    .append(
                        $('<div/>', {class: 'bill-date'})
                            .append(
                                $('<label/>', {for: 'date'})
                                    .append($('<a/>', {class: 'icon icon-calendar-dark'}))
                                    .append(document.createTextNode(dateStr))
                            )
                            .append($('<input/>', {type: 'date', id: 'date', class: 'input-bill-date', value: billDate}))
                    )
                    .append(
                        $('<div/>', {class: 'bill-time'})
                            .append(
                                $('<label/>', {for: 'time'})
                                    .append($('<a/>', {class: 'icon icon-time'}))
                                    .append(document.createTextNode(timeStr))
                            )
                            .append($('<input/>', {type: 'time', id: 'time', class: 'input-bill-time', value: billTime}))
                    )
                    .append(
                        $('<div/>', {class: 'bill-repeat'})
                            .append(
                                $('<label/>', {for: 'repeatbill'})
                                    .append($('<a/>', {class: 'icon icon-play-next'}))
                                    .append(document.createTextNode(t('cospend', 'Repeat')))
                            )
                            .append($('<select/>', {id: 'repeatbill'})
                                .append($('<option/>', {value: 'n', selected: 'selected'}).text(t('cospend', 'No')))
                                .append($('<option/>', {value: 'd'}).text(t('cospend', 'Daily')))
                                .append($('<option/>', {value: 'w'}).text(t('cospend', 'Weekly')))
                                .append($('<option/>', {value: 'm'}).text(t('cospend', 'Monthly')))
                                .append($('<option/>', {value: 'y'}).text(t('cospend', 'Yearly')))
                            )
                    )
                    .append(
                        $('<div/>', {class: 'bill-repeat-extra'})
                            .append(
                                $('<div/>', {class: 'bill-repeat-include'})
                                    .append($('<input/>', {id: 'repeatallactive', class: 'checkbox', type: 'checkbox'}))
                                    .append($('<label/>', {for: 'repeatallactive', class: 'checkboxlabel'})
                                        .text(t('cospend', 'Include all active member on repeat')))
                                    .append($('<br/>'))
                            )
                        .append(
                            $('<div/>', {class: 'bill-repeat-until'})
                                .append(
                                    $('<label/>', {for: 'repeatuntil'})
                                        .append($('<a/>', {class: 'icon icon-pause'}))
                                        .append(document.createTextNode(t('cospend', 'Repeat until')))
                                )
                                .append($('<input/>', {type: 'date', id: 'repeatuntil', class: 'input-bill-repeatuntil', value: bill.repeatuntil}))
                        )
                    )
                    .append(
                        $('<div/>', {class: 'bill-payment-mode'})
                            .append(
                                $('<label/>', {for: 'payment-mode'})
                                    .append($('<a/>', {class: 'icon icon-tag'}))
                                    .append(document.createTextNode(paymentModeStr))
                            )
                            .append($('<select/>', {id: 'payment-mode'})
                                .append($('<option/>', {value: 'n', selected: 'selected'}).text(t('cospend', 'None')))
                            )
                    )
                    .append(
                        $('<div/>', {class: 'bill-category'})
                            .append(
                                $('<label/>', {for: 'category'})
                                    .append($('<a/>', {class: 'icon icon-category-app-bundles'}))
                                    .append(document.createTextNode(categoryStr))
                            )
                            .append($('<select/>', {id: 'category'})
                                .append($('<option/>', {value: '0', selected: 'selected'}).text(t('cospend', 'None')))
                            )
                    )
                    .append(
                        $('<div/>', {class: 'bill-comment'})
                            .append(
                                $('<label/>', {for: 'comment'})
                                    .append($('<a/>', {class: 'icon icon-comment'}))
                                    .append(document.createTextNode(commentStr))
                            )
                            .append($('<textarea/>', {id: 'comment', maxlength: 300, class: 'input-bill-comment', value: bill.comment}))
                    )
                )
                .append(
                    $('<div/>', {class: 'bill-right'})
                        .append(
                            $('<div/>', {class: 'bill-type'})
                                .append(
                                    $('<label/>', {class: 'bill-owers-label'})
                                        .append($('<a/>', {class: 'icon icon-toggle-filelist'}))
                                        .append($('<span/>').text(billTypeStr))
                                )
                                .append(
                                    $('<select/>', {id: 'billtype'})
                                        .append($('<option/>', {value: 'normal', selected: 'selected'}).text(normalBillOption))
                                        .append($('<option/>', {value: 'perso'}).text(personalShareBillOption))
                                        .append($('<option/>', {value: 'custom'}).text(customBillOption))
                                )
                                .append(
                                    $('<button/>', {id: 'modehintbutton'})
                                        .append($('<span/>', {class: 'icon-details'}))
                                )
                                .append($('<div/>', {class: 'modehint modenormal'}).text(normalBillHint))
                                .append($('<div/>', {class: 'modehint modeperso'}).text(personalShareBillHint))
                                .append($('<div/>', {class: 'modehint modecustom'}).text(customBillHint))
                        )
                        .append(
                            $('<div/>', {class: 'bill-owers'})
                                .append(
                                    $('<label/>', {class: 'bill-owers-label'})
                                        .append($('<a/>', {class: 'icon icon-group'}))
                                        .append($('<span/>').text(owersStr))
                                )
                                .append(
                                    $('<div/>', {class: 'owerAllNoneDiv'})
                                        .append(
                                            $('<button/>', {id: 'owerAll'})
                                                .append($('<span/>', {class: 'icon-group'}))
                                                .append(allStr)
                                        )
                                        .append(
                                            $('<button/>', {id: 'owerNone'})
                                                .append($('<span/>', {class: 'icon-disabled-users'}))
                                                .append(noneStr)
                                        )
                                )
                                .append()
                        )
                )
        );

    // owers
    const billOwersDiv = container.find('div.bill-owers');
    for (const memberid in cospend.members[projectid]) {
        member = cospend.members[projectid][memberid];
        // show member if it's an ower or if it's activated
        if (member.activated || owerIds.indexOf(member.id) !== -1) {
            imgurl = getMemberAvatar(projectid, member.id);
            billOwersDiv.append(
                $('<div/>', {class: 'owerEntry'})
                    .append(
                        $('<div/>', {class: 'owerAvatar' + (cospend.members[projectid][member.id].activated ? '' : ' owerAvatarDisabled')})
                            .append($('<div/>', {class: 'disabledMask'}))
                            .append($('<img/>', {src: imgurl}))
                    )
                    .append($('<input/>', {
                        id: projectid + member.id, owerid: member.id, class: 'checkbox', type: 'checkbox',
                        checked: (owerIds.indexOf(member.id) !== -1) ? 'checked' : null,
                        disabled: (member.activated) ? null: 'disabled'
                    }))
                    .append($('<label/>', {for: projectid + member.id, class: 'checkboxlabel'}).text(member.name))
                    .append($('<input/>', {
                        id: 'amount' + projectid + member.id, owerid: member.id,
                        class: 'amountinput', type: 'number', value: '', step: 0.01, min: 0
                    }))
                    .append($('<label/>', {for: 'amount' + projectid + member.id, class: 'numberlabel'}).text(member.name))
                    .append($('<label/>', {class: 'spentlabel'}))
            )
        }
    }

    const payModeSelect = container.find('#payment-mode');
    let pm;
    for (const pmId in cospend.paymentModes) {
        pm = cospend.paymentModes[pmId];
        payModeSelect.append($('<option/>', {value: pmId}).text(pm.icon + ' ' + pm.name));
    }
    // category
    const categorySelect = container.find('#category');
    let cat;
    for (const catId in cospend.projects[projectid].categories) {
        cat = cospend.projects[projectid].categories[catId];
        categorySelect.append($('<option/>', {value: catId}).text((cat.icon || '') + ' ' + cat.name));
    }
    for (const catId in cospend.hardCodedCategories) {
        cat = cospend.hardCodedCategories[catId];
        categorySelect.append($('<option/>', {value: catId}).text(cat.icon + ' ' + cat.name));
    }

    $('#billdetail .input-bill-what').focus().select();
    if (billid !== 0) {
        $('#repeatbill').val(bill.repeat);
        $('#payment-mode').val(bill.paymentmode || 'n');
        if (cospend.hardCodedCategories.hasOwnProperty(bill.categoryid) ||
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
    if (cospend.hardCodedCategories.hasOwnProperty(bill.categoryid)) {
        categoryChar = cospend.hardCodedCategories[bill.categoryid].icon + ' ';
    }
    if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
        categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
    }
    const whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars;

    let imgurl;
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

        imgurl = getMemberAvatar(projectid, bill.payer_id);
        // disabled
        disabled = cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled';
        showRepeat = bill.repeat === 'n' ? '' : ' show';
    } else {
        imgurl = generateUrl('/apps/cospend/getAvatar?name=' + encodeURIComponent(' '));
    }
    const item = $('<a/>', {href: '#', class: 'app-content-list-item billitem', billid: bill.id, projectid: projectid, title: title})
        .append(
            $('<div/>', {class: 'app-content-list-item-icon', style: 'background-image: url(' + imgurl + ');'})
                .append($('<div/>', {class: 'billItemDisabledMask' + disabled}))
                .append($('<div/>', {class: 'billItemRepeatMask' + showRepeat}))
        )
        .append($('<div/>', {class: 'app-content-list-item-line-one'}).text(whatFormatted))
        .append($('<div/>', {class: 'app-content-list-item-line-two'}).text(bill.amount.toFixed(2) + ' (' + memberName + ' → ' + owerNames + ')'))
        .append($('<span/>', {class: 'app-content-list-item-details'}).text(billDate))
        .append($('<div/>', {class: 'icon-delete deleteBillIcon'}))
        .append($('<div/>', {class: 'icon-history undoDeleteBill', style: undoDeleteBillStyle, title: t('cospend', 'Undo')}))

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
    const comment = $('.input-bill-comment').val();
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
            paymentmode, categoryid, repeatallactive, repeatuntil, comment);
    } else {
        Notification.showTemporary(t('cospend', 'Bill values are not valid'));
    }
}

export function onBillEdited(amountChanged=false) {
    // get bill info
    const billid = $('.bill-title').attr('billid');
    const projectid = $('.bill-title').attr('projectid');
    updateAmountEach(projectid);

    // if this is a new bill : get out
    if (billid === '0') {
        return;
    }

    let what = $('.input-bill-what').val();
    const comment = $('.input-bill-comment').val();
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
                paymentmode, categoryid, repeatallactive, repeatuntil, comment);
        }
    } else {
        Notification.showTemporary(t('cospend', 'Bill values are not valid'));
    }
}

// create equitable bill with personal parts
export function createEquiPersoBill() {
    const projectid = $('.bill-title').attr('projectid');

    let what = $('.input-bill-what').val();
    const comment = $('.input-bill-comment').val();
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
                    paymentmode, categoryid, repeatallactive, repeatuntil, comment);
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
            paymentmode, categoryid, repeatallactive, repeatuntil, comment);
        // empty bill detail
        $('#billdetail').html('');
        // remove new bill line
        $('.billitem[billid=0]').fadeOut('normal', function() {
            $(this).remove();
            if ($('.billitem').length === 0) {
                $('#bill-list').html('').append($('<h2/>', {class: 'nobill'}).text(t('cospend', 'No bill yet')));
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
    const comment = $('.input-bill-comment').val();
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
                    paymentmode, categoryid, repeatallactive, repeatuntil, comment);
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
                    $('#bill-list').html('').append($('<h2/>', {class: 'nobill'}).text(t('cospend', 'No bill yet')));
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
    if (what === null || what === '') {
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
