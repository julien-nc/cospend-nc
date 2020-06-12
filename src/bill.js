/*jshint esversion: 6 */

import Vue from 'vue';
import './bootstrap';
import BillForm from './BillForm';
import BillList from './BillList';
import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import {getCurrentUser} from '@nextcloud/auth';
import * as constants from './constants';
import cospend from './state';
import {updateProjectBalances} from './project';
import {getUrlParameter, reload} from './utils';
import {getMemberName, getSmartMemberName, getMemberAvatar} from './member';
import {
    Timer,
    generatePublicLinkToFile,
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
    if (!cospend.pageIsPublic) {
        cospend.search = new OCA.Search(searchBills, resetSearchBills);
    }

    $(document).on('keypress', function(e) {
        if (e.key === 'Enter' && e.shiftKey) {
            $('#owerValidate').click();
        }
    });

    $('body').on('click', '.billitem', function(e) {
        return;
        if (!$(e.target).hasClass('deleteBillIcon') && !$(e.target).hasClass('undoDeleteBill')) {
            const billid = parseInt($(this).attr('billid'));
            const projectid = $(this).attr('projectid');
            displayBill(projectid, billid);
            updateBillCounters();
        }
    });

    $('body').on('click', '#newBillButton', function() {
        const projectid = cospend.currentProjectId;
        const activatedMembers = [];
        for (const mid in cospend.members[projectid]) {
            if (cospend.members[projectid][mid].activated) {
                activatedMembers.push(mid);
            }
        }
        if (activatedMembers.length > 1) {
            if (cospend.currentProjectId !== null && $('.billitem[billid=0]').length === 0) {
            }
            ///////////////
            displayBill(projectid, 0);
            //updateBillCounters();
            ///////////////
        } else {
            Notification.showTemporary(t('cospend', '2 active members are required to create a bill'));
        }
    });

    //$('body').on('click', '#addFileLinkButton', function() {
    //    OC.dialogs.filepicker(
    //        t('cospend', 'Choose file'),
    //        function(targetPath) {
    //            generatePublicLinkToFile(targetPath, onBillEdited);
    //        },
    //        false, null, true
    //    );
    //});
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
        url = generateUrl('/apps/cospend/projects/' + projectid + '/bills');
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
        updateBillCounters();

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

export function updateBillItem(projectid, billid, bill) {
    const billItem = $('.billitem[billid=' + billid + ']');
    const billSelected = billItem.hasClass('selectedbill');
    const item = generateBillItem(projectid, bill, billSelected);
    billItem.replaceWith(item);
    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        $('.billitem[billid=' + bill.id + '] .deleteBillIcon').hide();
    }
}

function generateBillItem(projectid, bill, selected=false) {
    let selectedClass = '';
    if (selected) {
        selectedClass = ' selectedbill';
    }

    const owerNames = getSmartOwerNames(projectid, bill);

    const billMom = moment.unix(bill.timestamp);
    const billDate = billMom.format('YYYY-MM-DD');
    const billTime = billMom.format('HH:mm');

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

    let title = '';
    let memberName = '';
    let imgurl;
    let disabled = '';
    let showRepeat = '';
    if (bill.id !== 0) {
        if (!cospend.members[projectid].hasOwnProperty(bill.payer_id)) {
            reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
            return;
        }
        if (!cospend.pageIsPublic && cospend.members[projectid][bill.payer_id].userid === getCurrentUser().uid) {
            memberName = t('cospend', 'You');
        } else {
            memberName = getMemberName(projectid, bill.payer_id);
        }

        title = whatFormatted + '\n' + bill.amount.toFixed(2) + '\n' +
            billDate + ' ' + billTime + '\n' + memberName + ' → ' + owerNames;

        imgurl = getMemberAvatar(projectid, bill.payer_id);
        // disabled
        disabled = cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled';
        showRepeat = bill.repeat === 'n' ? '' : ' show';
    } else {
        imgurl = generateUrl('/apps/cospend/getAvatar?name=' + encodeURIComponent(' '));
    }

    const item = $('<a/>', {href: '#', class: 'app-content-list-item billitem' + selectedClass, billid: bill.id, projectid: projectid, title: title})
        .append(
            $('<div/>', {class: 'app-content-list-item-icon', style: 'background-image: url(' + imgurl + ');'})
                .append($('<div/>', {class: 'billItemDisabledMask' + disabled}))
                .append($('<div/>', {class: 'billItemRepeatMask' + showRepeat}))
        )
        .append($('<div/>', {class: 'app-content-list-item-line-one'}).text(whatFormatted))
        .append($('<div/>', {class: 'app-content-list-item-line-two'}).text(bill.amount.toFixed(2) + ' (' + memberName + ' → ' + owerNames + ')'))
        .append(
            $('<span/>', {class: 'app-content-list-item-details'})
                .append($('<span/>', {class: 'bill-counter'}))
                .append($('<span/>').text(' ' + billDate)
            )
        )
        .append($('<div/>', {class: 'icon-delete deleteBillIcon'}))
        .append($('<div/>', {class: 'icon-history undoDeleteBill', style: undoDeleteBillStyle, title: t('cospend', 'Undo')}))

    return item;
}

export function getSmartOwerNames(projectid, bill) {
    const owerIds = [];
    for (let i = 0; i < bill.owers.length; i++) {
        owerIds.push(bill.owers[i].id);
    }
    // get missing members
    let nbMissingEnabledMembers = 0;
    const missingEnabledMemberIds = [];
    for (const memberid in cospend.members[projectid]) {
        if (cospend.members[projectid][memberid].activated &&
            !owerIds.includes(parseInt(memberid))) {
            nbMissingEnabledMembers++;
            missingEnabledMemberIds.push(memberid);
        }
    }

    // 4 cases : all, all except 1, all except 2, custom
    if (nbMissingEnabledMembers === 0) {
        return t('cospend', 'Everyone');
    } else if (nbMissingEnabledMembers === 1 && owerIds.length > 2) {
        const mName = getSmartMemberName(projectid, missingEnabledMemberIds[0]);
        return t('cospend', 'Everyone except {member}', {member: mName});
    } else if (nbMissingEnabledMembers === 2 && owerIds.length > 2) {
        const mName1 = getSmartMemberName(projectid, missingEnabledMemberIds[0]);
        const mName2 = getSmartMemberName(projectid, missingEnabledMemberIds[1]);
        const mName = t('cospend', '{member1} and {member2}', {member1: mName1, member2: mName2})
        return t('cospend', 'Everyone except {member}', {member: mName});
    } else {
        let owerNames = '';
        let ower;
        for (let i = 0; i < bill.owers.length; i++) {
            ower = bill.owers[i];
            if (!cospend.members[projectid].hasOwnProperty(ower.id)) {
                reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
                return;
            }
            owerNames = owerNames + getSmartMemberName(projectid, ower.id) + ', ';
        }
        owerNames = owerNames.replace(/, $/, '');
        return owerNames;
    }
}

export function deleteBill(projectid, billid) {
    const req = {};
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/bills/' + billid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + billid);
    }
    $.ajax({
        type: 'DELETE',
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
            updateBillCounters();
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
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/bills');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
    }
    cospend.currentGetProjectsAjax = $.ajax({
        type: 'GET',
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        $('#bill-list').html('');
        cospend.bills[projectid] = {};
        cospend.billLists[projectid] = response;
        //if (response.length > 0) {
            let bill;
            for (let i = 0; i < response.length; i++) {
                bill = response[i];
                cospend.bills[projectid][bill.id] = bill;
                //addBill(projectid, bill);
            }
            //updateBillCounters();
        //} else {
        //    $('#bill-list').html('').append($('<h2/>', {class: 'nobill'}).text(t('cospend', 'No bill yet')));
        //}
        new Vue({
            el: "#bill-list",
            render: h => h(BillList),
        });
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get bills'));
        $('#bill-list').html('');
    });
}

export function updateBillCounters() {
    const billCounters = $('.bill-counter');
    billCounters.text('');
    const nbCounters = billCounters.length;
    let i = nbCounters;
    billCounters.each(function() {
        if ($(this).parent().parent().hasClass('selectedbill')) {
            $(this).text('[' + i + '/' + nbCounters + ']');
            return false;
        }
        i--;
    });
}

export function displayBill(projectid, billid) {
    if (billid === 0) {
        const billList = cospend.billLists[projectid];
        // remove potentially existing new bill
        for (let i = 0; i < billList.length; i++) {
            if (billList[i].id === 0) {
                billList.splice(i, 1);
                break;
            }
        }
        cospend.currentBill = {
            id: 0,
            what: '',
            timestamp: moment().unix(),
            amount: 0.0,
            payer_id: 0,
            repeat: 'n',
            owers: [],
            owerIds: [],
            paymentmode: 'n',
            categoryid: 0,
            comment: ''
        };
        billList.push(cospend.currentBill);
        // select new bill in case it was not selected yet
        cospend.selectedBillId = billid;
    } else {
        cospend.currentBill = cospend.bills[projectid][billid];
    }
    const container = $('#billdetail');
    container.html('')
        .append($('<div/>', { id: 'bill-form' }));
    new Vue({
        el: "#bill-form",
        render: h => h(BillForm),
    });
}

export function addBill(projectid, bill) {
    cospend.bills[projectid][bill.id] = bill;
    const item = generateBillItem(projectid, bill);
    $(item).prependTo('.app-content-list');

    $('#bill-list .nobill').remove();

    if (parseInt(getUrlParameter('bill')) === bill.id && getUrlParameter('project') === projectid) {
        displayBill(projectid, bill.id);
    }
    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        $('.billitem[billid=' + bill.id + '] .deleteBillIcon').hide();
    }
}
