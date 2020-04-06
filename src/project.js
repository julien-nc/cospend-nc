/*jshint esversion: 6 */

import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import 'sorttable';
import kjua from 'kjua';
import * as Chart from 'chart.js';
import * as constants from './constants';
import {getBills} from './bill';
import {
    copyToClipboard,
    getUrlParameter,
    Timer,
    saveOptionValue
} from './utils';
import {
    addMember,
} from './member';
import {addShare} from './share';
import cospend from './state';
import {
    exportProject,
    exportSettlement,
    exportStatistics
} from "./importExport";

/**
 * Nextcloud - cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

export function projectEvents() {
    $('#newprojectbutton').click(function () {
        const div = $('#newprojectdiv');
        if (div.is(':visible')) {
            $(this).removeClass('icon-triangle-s').addClass('icon-triangle-e');
            div.slideUp('normal', function () {
                $('#newBillButton').fadeIn();
            });
        } else {
            $(this).removeClass('icon-triangle-e').addClass('icon-triangle-s');
            div.slideDown('normal', function () {
                $('#newBillButton').fadeOut();
                $('#projectidinput').focus().select();
            });
        }
    });

    $('#projectnameinput, #projectidinput, #projectpasswordinput').on('keyup', function (e) {
        if (e.key === 'Enter') {
            const name = $('#projectnameinput').val();
            const id = $('#projectidinput').val();
            const password = $('#projectpasswordinput').val();
            if (name && id && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                createProject(id, name, password);
            } else {
                Notification.showTemporary(t('cospend', 'Invalid values'));
            }
        }
    });

    $('#newprojectform').submit(function (e) {
        const name = $('#projectnameinput').val();
        const id = $('#projectidinput').val();
        const password = $('#projectpasswordinput').val();
        if (name && id && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
            createProject(id, name, password);
        } else {
            Notification.showTemporary(t('cospend', 'Invalid values'));
        }
        e.preventDefault();
    });

    $('#createproject').click(function () {
        const name = $('#projectnameinput').val();
        const id = $('#projectidinput').val();
        const password = $('#projectpasswordinput').val();
        if (name && id && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
            createProject(id, name, password);
        } else {
            Notification.showTemporary(t('cospend', 'Invalid values'));
        }
    });

    $('body').on('click', '.deleteProject', function () {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        $(this).parent().parent().parent().parent().addClass('deleted');
        cospend.projectDeletionTimer[projectid] = new Timer(function () {
            deleteProject(projectid);
        }, 7000);
    });

    $('body').on('click', '.undoDeleteProject', function () {
        const projectid = $(this).parent().parent().attr('projectid');
        $(this).parent().parent().removeClass('deleted');
        cospend.projectDeletionTimer[projectid].pause();
        delete cospend.projectDeletionTimer[projectid];
    });

    $('body').on('click', '.projectitem > a', function() {
        selectProject($(this).parent());
    });

    $('body').on('click', '.projectitem', function(e) {
        if (e.target.tagName === 'LI' && $(e.target).hasClass('projectitem')) {
            selectProject($(this));
        }
    });

    $('body').on('click', '.editProjectName', function () {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        const name = cospend.projects[projectid].name;
        $(this).parent().parent().parent().parent().find('.editProjectInput').val(name).attr('type', 'text').focus().select();
        $('#projectlist > li').removeClass('editing');
        $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
        cospend.projectEditionMode = constants.PROJECT_NAME_EDITION;
    });

    $('body').on('click', '.editProjectPassword', function () {
        $(this).parent().parent().parent().parent().find('.editProjectInput').attr('type', 'password').val('').focus();
        $('#projectlist > li').removeClass('editing');
        $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
        cospend.projectEditionMode = constants.PROJECT_PASSWORD_EDITION;
    });

    $('body').on('click', '.editProjectClose', function () {
        $(this).parent().parent().parent().removeClass('editing');
    });

    $('body').on('keyup', '.editProjectInput', function (e) {
        if (e.key === 'Enter') {
            let newName;
            const projectid = $(this).parent().parent().parent().attr('projectid');
            if (cospend.projectEditionMode === constants.PROJECT_NAME_EDITION) {
                newName = $(this).val();
                editProject(projectid, newName, null, null);
            } else if (cospend.projectEditionMode === constants.PROJECT_PASSWORD_EDITION) {
                const newPassword = $(this).val();
                newName = $(this).parent().parent().parent().find('>a span').text();
                editProject(projectid, newName, null, newPassword);
            }
        }
    });

    $('body').on('click', '.editProjectOk', function () {
        const projectid = $(this).parent().parent().parent().attr('projectid');
        let newName;
        if (cospend.projectEditionMode === constants.PROJECT_NAME_EDITION) {
            newName = $(this).parent().find('.editProjectInput').val();
            editProject(projectid, newName, null, null);
        } else if (cospend.projectEditionMode === constants.PROJECT_PASSWORD_EDITION) {
            const newPassword = $(this).parent().find('.editProjectInput').val();
            newName = $(this).parent().parent().parent().find('>a span').text();
            editProject(projectid, newName, null, newPassword);
        }
    });

    $('body').on('change', '#date-min-stats, #date-max-stats, #payment-mode-stats, ' +
        '#category-stats, #amount-min-stats, #amount-max-stats, ' +
        '#showDisabled, #currency-stats', function () {
            const projectid = cospend.currentProjectId;
            const dateMin = $('#date-min-stats').val();
            const dateMax = $('#date-max-stats').val();
            const paymentMode = $('#payment-mode-stats').val();
            const category = $('#category-stats').val();
            const amountMin = $('#amount-min-stats').val();
            const amountMax = $('#amount-max-stats').val();
            const showDisabled = $('#showDisabled').is(':checked');
            const currencyId = $('#currency-stats').val();
            getProjectStatistics(projectid, dateMin, dateMax, paymentMode, category, amountMin, amountMax, showDisabled, currencyId);
        });

    $('body').on('click', '.getProjectSettlement', function () {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        getProjectSettlement(projectid);
    });

    $('body').on('click', '.copyProjectGuestLink', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/loginproject/' + projectid);
        copyToClipboard(guestLink);
        Notification.showTemporary(t('cospend', 'Guest link for \'{pid}\' copied to clipboard', {pid: projectid}));
    });

    $('body').on('click', '.accesslevelguest', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        let accesslevel = constants.ACCESS.VIEWER;
        if ($(this).hasClass('accesslevelAdmin')) {
            accesslevel = constants.ACCESS.ADMIN;
        } else if ($(this).hasClass('accesslevelMaintener')) {
            accesslevel = constants.ACCESS.MAINTENER;
        } else if ($(this).hasClass('accesslevelParticipant')) {
            accesslevel = constants.ACCESS.PARTICIPANT;
        }
        editGuestAccessLevelDb(projectid, accesslevel);
    });

    $('body').on('click', '.exportProject', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        exportProject(projectid);
    });

    $('body').on('click', '.autoexportSelect, .accesslevelguest', function(e) {
        e.stopPropagation();
    });

    $('body').on('change', '.autoexportSelect', function() {
        const newval = $(this).val();
        const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
        const projectName = getProjectName(projectid);
        editProject(projectid, projectName, null, null, newval);
        $(this).parent().click();
    });

    $('body').on('click', '.exportStats', function() {
        const projectid = $(this).attr('projectid');

        const dateMin = $('#date-min-stats').val();
        const dateMax = $('#date-max-stats').val();
        const paymentMode = $('#payment-mode-stats').val();
        const category = $('#category-stats').val();
        const amountMin = $('#amount-min-stats').val();
        const amountMax = $('#amount-max-stats').val();
        const showDisabled = $('#showDisabled').is(':checked');
        const currencyId = $('#currency-stats').val();

        exportStatistics(projectid, dateMin, dateMax, paymentMode, category, amountMin, amountMax, showDisabled, currencyId);
    });

    $('body').on('click', '.exportSettlement', function() {
        const projectid = $(this).attr('projectid');
        exportSettlement(projectid);
    });

    $('body').on('click', '.autoSettlement', function() {
        const projectid = $(this).attr('projectid');
        autoSettlement(projectid);
    });

    $('body').on('change', '#categoryMemberSelect', function() {
        displayCategoryMemberChart();
    });

    $('body').on('change', '#memberPolarSelect', function() {
        displayMemberPolarChart();
    });

    $('body').on('click', '.getProjectStats', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        getProjectStatistics(projectid, null, null, null, -100);
    });
}

export function createProject(id, name, password) {
    if (!name || name.match(',') || name.match('/') ||
        !id || id.match(',') || id.match('/')
    ) {
        Notification.showTemporary(t('cospend', 'Invalid values'));
        return;
    }
    $('#createproject').addClass('icon-loading-small');
    const req = {
        id: id,
        name: name,
        password: password
    };
    const url = generateUrl('/apps/cospend/createProject');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        addProject(response);
        const div = $('#newprojectdiv');
        $('#newprojectbutton').removeClass('icon-triangle-s').addClass('icon-triangle-e');
        div.slideUp('normal', function() {
            $('#newBillButton').fadeIn();
        });
        // select created project
        selectProject($('.projectitem[projectid="' + id + '"]'));
    }).always(function() {
        $('#createproject').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(t('cospend', 'Failed to create project') + ': ' + response.responseJSON.message);
    });
}

export function editProject(projectid, newName, newEmail, newPassword, newAutoexport = null, newcurrencyname = null) {
    const req = {
        name: newName,
        contact_email: newEmail,
        password: newPassword,
        autoexport: newAutoexport,
        currencyname: newcurrencyname
    };
    let url, type;
    const project = cospend.projects[projectid];
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        type = 'POST';
        url = generateUrl('/apps/cospend/editProject');
    } else {
        type = 'PUT';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function() {
        const projectLine = $('.projectitem[projectid="' + projectid + '"]');
        // update project values
        if (newName) {
            const displayedName = escapeHTML(newName);
            projectLine.find('>a span').html(displayedName);
            cospend.projects[projectid].name = newName;
        }
        if (newPassword) {
            if (cospend.pageIsPublic) {
                cospend.password = newPassword;
            } else {
                cospend.projects[projectid].password = newPassword;
            }
        }
        if (newcurrencyname !== null) {
            project.currencyname = newcurrencyname || null;
        }
        // update deleted text
        const projectName = cospend.projects[projectid].name;
        projectLine.find('.app-navigation-entry-deleted-description').text(
            t('cospend', 'Deleted {name}', {name: projectName})
        );
        // remove editing mode
        projectLine.removeClass('editing');
        if (newcurrencyname === null) {
            // reset bill edition
            $('#billdetail').html('');
        } else {
            $('#main-currency-label-label').text(newcurrencyname || t('cospend', 'None'));
            $('#main-currency-label').show();
            $('#main-currency-edit').hide();
        }
        Notification.showTemporary(t('cospend', 'Project saved'));
    }).always(function() {
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit project') +
            ': ' + (response.responseJSON.message || response.responseJSON.name || response.responseJSON.contact_email)
        );
    });
}

export function deleteProject(projectid) {
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/deleteProject');
        type = 'POST';
    } else {
        type = 'DELETE';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function() {
        $('.projectitem[projectid="' + projectid + '"]').fadeOut('normal', function() {
            $(this).remove();
        });
        if (cospend.currentProjectId === projectid) {
            $('#bill-list').html('');
            $('#billdetail').html('');
        }
        if (cospend.pageIsPublic) {
            const redirectUrl = generateUrl('/apps/cospend/login');
            window.location.replace(redirectUrl);
        }
        Notification.showTemporary(t('cospend', 'Deleted project {id}', {id: projectid}));
    }).always(function() {
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete project') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function getProjects() {
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/getProjects');
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
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.addEventListener('progress', function(evt) {
                if (evt.lengthComputable) {
                    //const percentComplete = evt.loaded / evt.total * 100;
                    //$('#loadingpc').text(parseInt(percentComplete) + '%');
                }
            }, false);

            return xhr;
        }
    }).done(function(response) {
        if (!cospend.pageIsPublic) {
            $('.projectitem').remove();
            $('#bill-list').html('');
            cospend.bills = {};
            cospend.members = {};
            cospend.projects = {};
            for (let i = 0; i < response.length; i++) {
                addProject(response[i]);
            }
        } else {
            if (!response.myaccesslevel) {
                response.myaccesslevel = response.guestaccesslevel;
            }
            addProject(response);
            $('.projectitem').addClass('open');
            cospend.currentProjectId = cospend.projectid;
            getBills(cospend.projectid);
        }
    }).always(function() {
        cospend.currentGetProjectsAjax = null;
    }).fail(function(response) {
        Notification.showTemporary(t('cospend', 'Failed to get projects') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function getProjectStatistics(projectid, dateMin = null, dateMax = null, paymentMode = null, category = null,
                                      amountMin = null, amountMax = null, showDisabled = true, currencyId = null) {
    $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
    const req = {
        dateMin: dateMin,
        dateMax: dateMax,
        paymentMode: paymentMode,
        category: category,
        amountMin: amountMin,
        amountMax: amountMax,
        showDisabled: showDisabled ? '1' : '0',
        currencyId: currencyId
    };
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        type = 'POST';
        url = generateUrl('/apps/cospend/getStatistics');
    } else {
        type = 'GET';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/statistics');
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
        if (cospend.currentCategoryMemberChart) {
            cospend.currentCategoryMemberChart.destroy();
            delete cospend.currentCategoryMemberChart;
        }
        if (cospend.currentMemberPolarChart) {
            cospend.currentMemberPolarChart.destroy();
            delete cospend.currentMemberPolarChart;
        }
        displayStatistics(projectid, response, dateMin, dateMax, paymentMode, category,
            amountMin, amountMax, showDisabled, currencyId);
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get statistics'));
        $('#billdetail').html('');
    });
}

export function getProjectSettlement(projectid) {
    $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        type = 'POST';
        url = generateUrl('/apps/cospend/getSettlement');
    } else {
        type = 'GET';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/settle');
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
        displaySettlement(projectid, response);
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get settlement'));
        $('#billdetail').html('');
    });
}

export function displaySettlement(projectid, transactionList) {
    // unselect bill
    $('.billitem').removeClass('selectedbill');

    const projectName = getProjectName(projectid);
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Settlement of project {name}', {name: projectName});
    const fromStr = t('cospend', 'Who pays?');
    const toStr = t('cospend', 'To whom?');
    const howMuchStr = t('cospend', 'How much?');
    let exportStr = '';
    if (!cospend.pageIsPublic) {
        exportStr = ' <button class="exportSettlement" projectid="' + projectid + '"><span class="icon-save"></span>' + t('cospend', 'Export') + '</button>';
    }
    const autoSettleStr = ' <button class="autoSettlement" projectid="' + projectid + '"><span class="icon-add"></span>' + t('cospend', 'Add these payments to project') + '</button>';
    let settlementStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
        '<h2 id="settlementTitle"><span class="icon-reimburse"></span>' + titleStr + exportStr + autoSettleStr + '</h2>' +
        '<table id="settlementTable" class="sortable"><thead>' +
        '<th>' + fromStr + '</th>' +
        '<th>' + toStr + '</th>' +
        '<th class="sorttable_numeric">' + howMuchStr + '</th>' +
        '</thead>';
    let amount, memberFrom, memberTo, imgurlFrom, imgurlTo;
    for (let i = 0; i < transactionList.length; i++) {
        amount = transactionList[i].amount.toFixed(2);
        memberFrom = cospend.members[projectid][transactionList[i].from];
        memberTo = cospend.members[projectid][transactionList[i].to];
        imgurlFrom = generateUrl('/apps/cospend/getAvatar?color=' + memberFrom.color + '&name=' + encodeURIComponent(memberFrom.name));
        imgurlTo = generateUrl('/apps/cospend/getAvatar?color=' + memberTo.color + '&name=' + encodeURIComponent(memberTo.name));
        if (amount !== '0.00') {
            settlementStr = settlementStr +
                '<tr>' +
                '<td style="border: 2px solid #' + memberFrom.color + ';">' +
                '<div class="owerAvatar' + (memberFrom.activated ? '' : ' owerAvatarDisabled') + '">' +
                '   <div class="disabledMask"></div>' +
                '   <img src="' + imgurlFrom + '"/>' +
                '</div>' +
                memberFrom.name + '</td>' +
                '<td style="border: 2px solid #' + memberTo.color + ';">' +
                '<div class="owerAvatar' + (memberTo.activated ? '' : ' owerAvatarDisabled') + '">' +
                '   <div class="disabledMask"></div>' +
                '   <img src="' + imgurlTo + '"/>' +
                '</div>' +
                memberTo.name + '</td>' +
                '<td>' + amount + '</td>' +
                '</tr>';
        }
    }
    settlementStr = settlementStr + '</table>';
    $('#billdetail').html(settlementStr);
    window.sorttable.makeSortable(document.getElementById('settlementTable'));

    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        $('.autoSettlement').hide();
    }
}

export function getProjectMoneyBusterLink(projectid) {
    // unselect bill
    $('.billitem').removeClass('selectedbill');

    if (cospend.currentProjectId !== projectid) {
        selectProject($('.projectitem[projectid="' + projectid + '"]'));
    }

    const url = 'https://net.eneiluj.moneybuster.cospend/' + window.location.host + generateUrl('').replace('/index.php', '') + projectid + '/';

    const projectName = getProjectName(projectid);
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: projectName});
    const mbStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
        '<h2 id="mbTitle"><span class="icon-phone"></span>' + titleStr + '</h2>' +
        '<div id="qrcodediv"></div>' +
        '<label id="mbUrlLabel">' + url + '</label>' +
        '<br/>' +
        '<label id="mbUrlHintLabel">' +
        t('cospend', 'Scan this QRCode with an Android phone with MoneyBuster installed and open the link or simply send the link to another Android phone.') +
        '</label>' +
        '<label id="mbUrlHintLabel">' +
        t('cospend', 'Android will know MoneyBuster can open such a link (based on the \'https://net.eneiluj.moneybuster.cospend\' part) and you will be able to add the project.') +
        '</label>';
    $('#billdetail').html(mbStr);

    const img = new Image();
    // wait for the image to be loaded to generate the QRcode
    img.onload = function() {
        const qr = kjua({
            text: url,
            crisp: false,
            render: 'canvas',
            minVersion: 6,
            ecLevel: 'H',
            size: 210,
            back: "#ffffff",
            fill: cospend.themeColorDark,
            rounded: 100,
            quiet: 1,
            mode: 'image',
            mSize: 20,
            mPosX: 50,
            mPosY: 50,
            image: img,
            label: 'no label',
        });
        $('#qrcodediv').append(qr);
    };
    img.onerror = function() {
        const qr = kjua({
            text: url,
            crisp: false,
            render: 'canvas',
            minVersion: 6,
            ecLevel: 'H',
            size: 210,
            back: "#ffffff",
            fill: cospend.themeColorDark,
            rounded: 100,
            quiet: 1,
            mode: 'label',
            mSize: 10,
            mPosX: 50,
            mPosY: 50,
            image: img,
            label: 'Cospend',
            fontcolor: '#000000',
        });
        $('#qrcodediv').append(qr);
    };

    // dirty trick to get image URL from css url()... Anyone knows better ?
    img.src = $('#dummylogo').css('content').replace('url("', '').replace('")', '');
}

export function displayStatistics(projectid, allStats, dateMin = null, dateMax = null, paymentMode = null, category = null,
                                   amountMin = null, amountMax = null, showDisabled = true, currencyId = null) {
    // deselect bill
    $('.billitem').removeClass('selectedbill');

    const statList = allStats.stats;
    const monthlyStats = allStats.monthlyStats;
    const categoryStats = allStats.categoryStats;
    const categoryMemberStats = allStats.categoryMemberStats;
    const categoryMonthlyStats = allStats.categoryMonthlyStats;
    const memberIds = allStats.memberIds;
    cospend.currentStats = allStats;
    cospend.currentStatsProjectId = projectid;
    let color;
    const isFiltered = ((dateMin !== null && dateMin !== '')
        || (dateMax !== null && dateMax !== '')
        || (paymentMode !== null && paymentMode !== 'n')
        || (category !== null && parseInt(category) !== 0)
        || (amountMin !== null && amountMin !== '')
        || (amountMax !== null && amountMax !== '')
    );

    const project = cospend.projects[projectid];
    const projectName = getProjectName(projectid);
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Statistics of project {name}', {name: projectName});
    const nameStr = t('cospend', 'Member name');
    const paidStr = t('cospend', 'Paid');
    const spentStr = t('cospend', 'Spent');
    const balanceStr = t('cospend', 'Balance');
    const filteredBalanceStr = t('cospend', 'Filtered balance');
    let exportStr = '';

    function category_from_id(catId) {
        let catName, catColor;
        if (cospend.hardCodedCategories.hasOwnProperty(catId)) {
            catName = cospend.hardCodedCategories[catId].icon + ' ' + cospend.hardCodedCategories[catId].name;
            catColor = cospend.hardCodedCategories[catId].color;
        } else if (cospend.projects[projectid].categories.hasOwnProperty(catId)) {
            catName = (cospend.projects[projectid].categories[catId].icon || '') +
                ' ' + cospend.projects[projectid].categories[catId].name;
            catColor = cospend.projects[projectid].categories[catId].color || 'red';
        } else {
            catName = t('cospend', 'No category');
            catColor = '#000000';
        }

        return {
            name: catName,
            color: catColor,
        }
    }

    let totalPayed = 0.0;
    for (let i = 0; i < statList.length; i++) {
        totalPayed += statList[i].paid;
    }

    if (!cospend.pageIsPublic) {
        exportStr = ' <button class="exportStats" projectid="' + projectid + '"><span class="icon-save"></span>' + t('cospend', 'Export') + '</button>';
    }
    const totalPayedText = '<p class="totalPayedText">' +
        t('cospend', 'Total payed by all the members: {t}', {t: totalPayed.toFixed(2)}) + '</p>';
    let statsStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
        '<h2 id="statsTitle"><span class="icon-category-monitoring"></span>' + titleStr + exportStr + '</h2>' +
        '<div id="stats-filters">' +
        '    <label for="date-min-stats">' + t('cospend', 'Minimum date') + ': </label><input type="date" id="date-min-stats"/>' +
        '    <label for="date-max-stats">' + t('cospend', 'Maximum date') + ': </label><input type="date" id="date-max-stats"/>' +
        '    <label for="payment-mode-stats">' +
        '        <a class="icon icon-tag"></a>' +
        '        ' + t('cospend', 'Payment mode') +
        ':   </label>' +
        '    <select id="payment-mode-stats">' +
        '       <option value="n" selected>' + t('cospend', 'All') + '</option>';
    let pm;
    for (const pmId in cospend.paymentModes) {
        pm = cospend.paymentModes[pmId];
        statsStr += '       <option value="' + pmId + '">' + pm.icon + ' ' + pm.name + '</option>';
    }
    statsStr +=
        '    </select>' +
        '    <label for="category-stats">' +
        '        <a class="icon icon-category-app-bundles"></a>' +
        '        ' + t('cospend', 'Category') +
        ':   </label>' +
        '    <select id="category-stats">' +
        '       <option value="0">' + t('cospend', 'All') + '</option>' +
        '       <option value="-100" selected>' + t('cospend', 'All except reimbursement') + '</option>';
    let cat;
    for (const catId in cospend.projects[projectid].categories) {
        cat = cospend.projects[projectid].categories[catId];
        statsStr += '       <option value="' + catId + '">' + (cat.icon || '') + ' ' + cat.name + '</option>';
    }
    for (const catId in cospend.hardCodedCategories) {
        cat = cospend.hardCodedCategories[catId];
        statsStr += '       <option value="' + catId + '">' + cat.icon + ' ' + cat.name + '</option>';
    }
    statsStr +=
        '    </select>' +
        '    <label for="amount-min-stats">' + t('cospend', 'Minimum amount') + ': </label><input type="number" id="amount-min-stats"/>' +
        '    <label for="amount-max-stats">' + t('cospend', 'Maximum amount') + ': </label><input type="number" id="amount-max-stats"/>' +
        '    <label for="currency-stats">' + t('cospend', 'Currency of statistic values') + ': </label>' +
        '    <select id="currency-stats">' +
        '       <option value="0">' + (project.currencyname || t('cospend', 'Main project\'s currency')) + '</option>';
    let currency;
    for (let i = 0; i < project.currencies.length; i++) {
        currency = project.currencies[i];
        statsStr += '<option value="' + currency.id + '">' + currency.name + ' (x' + currency.exchange_rate + ')</option>';
    }
    statsStr +=
        '    </select>' +
        '    <input id="showDisabled" class="checkbox" type="checkbox"/>' +
        '    <label for="showDisabled" class="checkboxlabel">' + t('cospend', 'Show disabled members') + '</label> ' +
        '</div>' +
        '<br/>' +
        totalPayedText +
        '<br/><hr/><h2 class="statTableTitle">' + t('cospend', 'Global stats') + '</h2>' +
        '<table id="statsTable" class="sortable"><thead>' +
        '<th>' + nameStr + '</th>' +
        '<th class="sorttable_numeric">' + paidStr + '</th>' +
        '<th class="sorttable_numeric">' + spentStr + '</th>';
    if (isFiltered) {
        statsStr += '<th class="sorttable_numeric">' + filteredBalanceStr + '</th>';
    }
    statsStr +=
        '<th class="sorttable_numeric">' + balanceStr + '</th>' +
        '</thead>';
    let paid, spent, balance, filteredBalance, name, balanceClass,
        filteredBalanceClass, member, imgurl;
    for (let i = 0; i < statList.length; i++) {
        member = cospend.members[projectid][statList[i].member.id];
        balanceClass = '';
        if (statList[i].balance > 0) {
            balanceClass = ' class="balancePositive"';
        } else if (statList[i].balance < 0) {
            balanceClass = ' class="balanceNegative"';
        }
        filteredBalanceClass = '';
        if (statList[i].filtered_balance > 0) {
            filteredBalanceClass = ' class="balancePositive"';
        } else if (statList[i].filtered_balance < 0) {
            filteredBalanceClass = ' class="balanceNegative"';
        }
        paid = statList[i].paid.toFixed(2);
        spent = statList[i].spent.toFixed(2);
        balance = statList[i].balance.toFixed(2);
        filteredBalance = statList[i].filtered_balance.toFixed(2);
        name = statList[i].member.name;
        color = '#' + member.color;
        imgurl = generateUrl('/apps/cospend/getAvatar?color=' + member.color + '&name=' + encodeURIComponent(member.name));
        statsStr +=
            '<tr>' +
            '<td style="border: 2px solid ' + color + ';">' +
            '<div class="owerAvatar' + (member.activated ? '' : ' owerAvatarDisabled') + '">' +
            '   <div class="disabledMask"></div>' +
            '   <img src="' + imgurl + '"/>' +
            '</div>' +
            name +
            '</td>' +
            '<td style="border: 2px solid ' + color + ';">' + paid + '</td>' +
            '<td style="border: 2px solid ' + color + ';">' + spent + '</td>';
        if (isFiltered) {
            statsStr += '<td style="border: 2px solid ' + color + ';"' + filteredBalanceClass + '>' + filteredBalance + '</td>';
        }
        statsStr +=
            '<td style="border: 2px solid ' + color + ';"' + balanceClass + '>' + balance + '</td>' +
            '</tr>';
    }
    statsStr += '</table>';
    // monthly stats
    statsStr += '<h2 class="statTableTitle">' + t('cospend', 'Monthly stats') + '</h2>';
    statsStr += '<table id="monthlyTable" class="sortable"><thead>' +
        '<th>' + t('cospend', 'Member/Month') + '</th>';
    for (const month in monthlyStats) {
        statsStr += '<th class="sorttable_numeric"><span>' + month + '</span></th>';
    }
    statsStr += '</thead>';
    const mids = memberIds.slice();
    mids.push('0');
    let mid;
    for (let i = 0; i < mids.length; i++) {
        mid = mids[i];
        member = cospend.members[projectid][mid];
        if (parseInt(mid) === 0) {
            color = 'var(--color-border-dark)';
            statsStr += '<tr>';
            statsStr += '<td><b>' + t('cospend', 'All members') + '</b></td>';
        } else {
            color = '#' + member.color;
            imgurl = generateUrl('/apps/cospend/getAvatar?color=' + member.color + '&name=' + encodeURIComponent(member.name));
            statsStr += '<tr>';
            statsStr += '<td style="border: 2px solid ' + color + ';">' +
                '<div class="owerAvatar' + (member.activated ? '' : ' owerAvatarDisabled') + '">' +
                '   <div class="disabledMask"></div>' +
                '   <img src="' + imgurl + '"/>' +
                '</div>' +
                cospend.members[projectid][mid].name +
                '</td>';
        }
        for (const month in monthlyStats) {
            statsStr += '<td style="border: 2px solid ' + color + ';">';
            statsStr += monthlyStats[month][mid].toFixed(2);
            statsStr += '</td>';
        }
        statsStr += '</tr>';
    }
    statsStr += '</table>';
    statsStr += '<canvas id="memberMonthlyChart"></canvas>';
    statsStr += '<hr/><canvas id="categoryMonthlyChart"></canvas>';
    statsStr += '<hr/><canvas id="memberChart"></canvas>';
    statsStr += '<hr/><canvas id="categoryChart"></canvas>';
    statsStr += '<hr/><select id="categoryMemberSelect">';
    for (const catId in categoryMemberStats) {
        category = category_from_id(catId);
        statsStr += '<option value="' + catId + '">' + category.name + '</option>';
    }
    statsStr += '</select>';
    statsStr += '<canvas id="categoryMemberChart"></canvas>';
    statsStr += '<hr/><select id="memberPolarSelect">';
    for (let i = 0; i < memberIds.length; i++) {
        mid = memberIds[i];
        statsStr += '<option value="' + mid + '">' +
            cospend.members[projectid][mid].name + '</option>';
    }
    statsStr += '</select>';
    statsStr += '<canvas id="memberPolarChart"></canvas>';

    $('#billdetail').html(statsStr);

    // CHARTS
    let catIdInt;

    // Get all months of the dataset:
    let months = [];
    for (const catId in categoryMonthlyStats) {
        for (const month in categoryMonthlyStats[catId]) {
            months.push(month);
        }
    }
    const distinctMonths = [...new Set(months)];
    distinctMonths.sort();

    // Loop over all categories:
    let monthlyDatasets = [];
    for (const catId in categoryMonthlyStats) {
        catIdInt = parseInt(catId);
        category = category_from_id(catId);

        // Build time series:
        let paid = [];
        for(const month of distinctMonths) {
            if(typeof categoryMonthlyStats[catId][month] === 'undefined') {
                paid.push(0);
            } else {
                paid.push(categoryMonthlyStats[catId][month]);
            }
        }

        monthlyDatasets.push({
            label: category.name,
            // FIXME hacky way to change alpha channel:
            backgroundColor: category.color + "4D",
            pointBackgroundColor: category.color,
            borderColor: category.color,
            pointHighlightStroke: category.color,
            fill: '-1',
            lineTension: 0,
            data: paid,
        })
    }
    // First dataset fill should go down to x-axis:
    monthlyDatasets[0].fill = 'origin';

    new Chart($('#categoryMonthlyChart'), {
        type: 'line',
        data: {
            labels: distinctMonths,
            datasets: monthlyDatasets,
        },
        options: {
            scales: {
                yAxes: [{
                    stacked: true
                }]
            },
            title: {
                display: true,
                text: t('cospend', 'Payments per category per month')
            },
            responsive: true,
            showAllTooltips: false,
            legend: {
                position: 'left'
            }
        }
    });

    // Go through all project members
    let memberDatasets = [];
    for(const member_id of memberIds.slice()) {
        member = cospend.members[projectid][member_id];

        // Build time series:
        let paid = [];
        for(const month of distinctMonths) {
            paid.push(monthlyStats[month][member_id]);
        }

        memberDatasets.push({
            label: member.name,
            // FIXME hacky way to change alpha channel:
            backgroundColor: "#" + member.color + "4D",
            pointBackgroundColor: "#" + member.color,
            borderColor: "#" + member.color,
            pointHighlightStroke: "#" + member.color,
            fill: '-1',
            lineTension: 0,
            data: paid,
        })
    }
    // First dataset fill should go down to x-axis:
    memberDatasets[0].fill = 'origin';

    new Chart($('#memberMonthlyChart'), {
        type: 'line',
        data: {
            labels: distinctMonths,
            datasets: memberDatasets,
        },
        options: {
            scales: {
                yAxes: [{
                    stacked: true
                }]
            },
            title: {
                display: true,
                text: t('cospend', 'Payments per member per month')
            },
            responsive: true,
            showAllTooltips: false,
            legend: {
                position: 'left'
            }
        }
    });

    const memberBackgroundColors = [];
    const memberData = {
        // 2 datasets: paid and spent
        datasets: [{
            data: [],
            backgroundColor: []
        }, {
            data: [],
            backgroundColor: []
        }
        ],
        labels: []
    };
    let sumPaid = 0;
    let sumSpent = 0;
    for (let i = 0; i < statList.length; i++) {
        paid = statList[i].paid.toFixed(2);
        spent = statList[i].spent.toFixed(2);
        sumPaid += parseFloat(paid);
        sumSpent += parseFloat(spent);
        name = statList[i].member.name;
        color = '#' + cospend.members[projectid][statList[i].member.id].color;
        memberData.datasets[0].data.push(paid);
        memberData.datasets[1].data.push(spent);

        memberBackgroundColors.push(color);

        memberData.labels.push(name);
    }
    memberData.datasets[0].backgroundColor = memberBackgroundColors;
    memberData.datasets[1].backgroundColor = memberBackgroundColors;

    if (statList.length > 0 && sumPaid > 0.0 && sumSpent > 0.0) {
        new Chart($('#memberChart'), {
            type: 'pie',
            data: memberData,
            options: {
                title: {
                    display: true,
                    text: t('cospend', 'Who paid (outside circle) and spent (inside pie)?')
                },
                responsive: true,
                showAllTooltips: false,
                legend: {
                    position: 'left'
                }
            }
        });
    }
    // category chart
    const categoryData = {
        datasets: [{
            data: [],
            backgroundColor: []
        }],
        labels: []
    };
    for (const catId in categoryStats) {
        paid = categoryStats[catId].toFixed(2);
        catIdInt = parseInt(catId);
        category = category_from_id(catId);

        categoryData.datasets[0].data.push(paid);
        categoryData.datasets[0].backgroundColor.push(category.color);
        categoryData.labels.push(category.name);
    }
    if (Object.keys(categoryStats).length > 0) {
        new Chart($('#categoryChart'), {
            type: 'pie',
            data: categoryData,
            options: {
                title: {
                    display: true,
                    text: t('cospend', 'What was paid per category?')
                },
                responsive: true,
                showAllTooltips: false,
                legend: {
                    position: 'left'
                }
            }
        });
    }

    if (memberIds.length > 0) {
        // make tables sortable
        window.sorttable.makeSortable(document.getElementById('statsTable'));
        window.sorttable.makeSortable(document.getElementById('monthlyTable'));
    }

    if (dateMin) {
        $('#date-min-stats').val(dateMin);
    }
    if (dateMax) {
        $('#date-max-stats').val(dateMax);
    }
    if (paymentMode) {
        $('#payment-mode-stats').val(paymentMode);
    }
    if (category) {
        $('#category-stats').val(category);
    }
    if (amountMin) {
        $('#amount-min-stats').val(amountMin);
    }
    if (amountMax) {
        $('#amount-max-stats').val(amountMax);
    }
    if (showDisabled) {
        $('#showDisabled').prop('checked', true);
    }
    if (currencyId) {
        $('#currency-stats').val(currencyId);
    }

    displayCategoryMemberChart();
    displayMemberPolarChart();
}

export function getProjectName(projectid) {
    return cospend.projects[projectid].name;
}

export function updateProjectBalances(projectid) {
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
        let balance, balanceField, balanceClass, balanceTxt;
        for (const memberid in response.balance) {
            balance = response.balance[memberid];
            balanceField = $('.projectitem[projectid="' + projectid + '"] .memberlist > li[memberid=' + memberid + '] b.balance');
            balanceField.removeClass('balancePositive').removeClass('balanceNegative');
            // just in case make member visible
            $('.memberitem[memberid=' + memberid + ']').removeClass('invisibleMember');
            if (balance <= -0.01) {
                balanceClass = 'balanceNegative';
                balanceTxt = balance.toFixed(2);
                balanceField.addClass(balanceClass).text(balanceTxt);
            } else if (balance >= 0.01) {
                balanceClass = 'balancePositive';
                balanceTxt = '+' + balance.toFixed(2);
                balanceField.addClass(balanceClass).text(balanceTxt);
            } else {
                balanceField.text('0.00');
                // hide member if balance == 0 and disabled
                if (!cospend.members[projectid][memberid].activated) {
                    $('.memberitem[memberid=' + memberid + ']').addClass('invisibleMember');
                }
            }
        }
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to update balances'));
    });
}

export function addProject(project) {
    cospend.projects[project.id] = project;
    cospend.members[project.id] = {};

    const name = project.name;
    const projectid = project.id;
    const addMemberStr = t('cospend', 'Add member');
    const guestAccessStr = t('cospend', 'Guest access link');
    const renameStr = t('cospend', 'Change title');
    const changePwdStr = t('cospend', 'Change password');
    const displayStatsStr = t('cospend', 'Display statistics');
    const settleStr = t('cospend', 'Settle the project');
    const exportStr = t('cospend', 'Export to csv');
    const autoexportStr = t('cospend', 'Auto export');
    const manageCurrenciesStr = t('cospend', 'Manage currencies');
    const manageCategoriesStr = t('cospend', 'Manage categories');
    const deleteStr = t('cospend', 'Delete');
    const moneyBusterUrlStr = t('cospend', 'Link/QRCode for MoneyBuster');
    const deletedStr = t('cospend', 'Deleted {name}', {name: name});
    const shareTitle = t('cospend', 'Press enter to validate');
    const defaultShareText = t('cospend', 'User, group or circle name...');
    let guestLink;
    guestLink = generateUrl('/apps/cospend/loginproject/' + projectid);
    guestLink = window.location.protocol + '//' + window.location.hostname + guestLink;
    const guestAccessLevel = parseInt(project.guestaccesslevel);
    let li =
        '<li class="projectitem collapsible" projectid="' + projectid + '">' +
        '    <a class="icon-folder" href="#" title="' + projectid + '">' +
        '        <span>' + name + '</span>' +
        '    </a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-counter"><span>' + project.members.length + '</span></li>';
    li = li + '            <li class="app-navigation-entry-utils-menu-button shareProjectButton">' +
        '                <button class="icon-shar"></button>' +
        '            </li>';
    li = li + '            <li class="app-navigation-entry-utils-menu-button projectMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-edit">' +
        '        <div>' +
        '            <input type="text" maxlength="300" value="' + project.name + '" class="editProjectInput">' +
        '            <input type="submit" value="" class="icon-close editProjectClose">' +
        '            <input type="submit" value="" class="icon-checkmark editProjectOk">' +
        '        </div>' +
        '    </div>';
    li = li + '    <ul class="app-navigation-entry-share">' +
        '           <li class="shareinputli" title="' + shareTitle + '"><input type="text" maxlength="300" class="shareinput" placeholder="' + defaultShareText + '"/></li>';
    li +=
        '           <li class="addpubshareitem">' +
        '               <a class="icon-public" href="#">' +
        '                   <span>' + t('cospend', 'Add public link') + '</span>' +
        '               </a>' +
        '               <div class="app-navigation-entry-utils">' +
        '                   <ul>' +
        '                       <li class="app-navigation-entry-utils-menu-button addPublicShareButton">' +
        '                           <button class="icon-add"></button>' +
        '                       </li>' +
        '                   </ul>' +
        '               </div>' +
        '            </li>' +
        '          </ul>';
    li = li + '    <div class="newmemberdiv">' +
        '        <input class="newmembername" maxlength="300" type="text" value=""/>' +
        '        <button class="newmemberbutton icon-add"></button>' +
        '    </div>' +

        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="addMember">' +
        '                    <span class="icon-add"></span>' +
        '                    <span>' + addMemberStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="copyProjectGuestLink" title="' + guestLink + '" style="padding-right: 0px !important;">' +
        '                    <span class="icon-clippy"></span>' +
        '                    <span class="guest-link-label">' + guestAccessStr + '&nbsp</span>' +
        '                    <div class="guestaccesslevel">' +
        '                       <div class="icon-user-admin accesslevelguest accesslevelAdmin ' + (guestAccessLevel === constants.ACCESS.ADMIN ? 'accesslevelActive' : '') + '" ' +
        '                       title="' + t('cospend', 'Admin: edit/delete project + maintener permissions') + '"></div>' +
        '                       <div class="icon-category-customization accesslevelguest accesslevelMaintener ' + (guestAccessLevel === constants.ACCESS.MAINTENER ? 'accesslevelActive' : '') + '" ' +
        '                       title="' + t('cospend', 'Maintener: add/edit members/categories/currencies + participant permissions') + '"></div>' +
        '                       <div class="icon-rename accesslevelguest accesslevelParticipant ' + (guestAccessLevel === constants.ACCESS.PARTICIPANT ? 'accesslevelActive' : '') + '" ' +
        '                       title="' + t('cospend', 'Participant: add/edit/delete bills + viewer permissions') + '"></div>' +
        '                       <div class="icon-toggle accesslevelguest accesslevelViewer ' + (guestAccessLevel === constants.ACCESS.VIEWER ? 'accesslevelActive' : '') + '" ' +
        '                       title="' + t('cospend', 'Viewer') + '"></div>' +
        '                    </div>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="moneyBusterProjectUrl">' +
        '                    <span class="icon-phone"></span>' +
        '                    <span>' + moneyBusterUrlStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="editProjectName">' +
        '                    <span class="icon-rename"></span>' +
        '                    <span>' + renameStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="editProjectPassword">' +
        '                    <span class="icon-password"></span>' +
        '                    <span>' + changePwdStr + '</span>' +
        '                </a>' +
        '            </li>';
    li = li + '            <li>' +
        '                <a href="#" class="manageProjectCategories">' +
        '                    <span class="icon-category-app-bundles"></span>' +
        '                    <span>' + manageCategoriesStr + '</span>' +
        '                </a>' +
        '            </li>';
    li = li + '            <li>' +
        '                <a href="#" class="manageProjectCurrencies">' +
        '                    <span class="icon-currencies"></span>' +
        '                    <span>' + manageCurrenciesStr + '</span>' +
        '                </a>' +
        '            </li>';
    li = li + '            <li>' +
        '                <a href="#" class="getProjectStats">' +
        '                    <span class="icon-category-monitoring"></span>' +
        '                    <span>' + displayStatsStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="getProjectSettlement">' +
        '                    <span class="icon-reimburse"></span>' +
        '                    <span>' + settleStr + '</span>' +
        '                </a>' +
        '            </li>';
    li = li + '            <li>' +
        '                <a href="#" class="exportProject">' +
        '                    <span class="icon-save"></span>' +
        '                    <span>' + exportStr + '</span>' +
        '                </a>' +
        '            </li>';
    li = li + '            <li>' +
        '                <a href="#" class="autoexportProject">' +
        '                    <span class="icon-schedule"></span>' +
        '                    <span class="autoexportLabel">' + autoexportStr + '</span>' +
        '                    <select class="autoexportSelect">' +
        '                       <option value="n">' + t('cospend', 'No') + '</option>' +
        '                       <option value="d">' + t('cospend', 'Daily') + '</option>' +
        '                       <option value="w">' + t('cospend', 'Weekly') + '</option>' +
        '                       <option value="m">' + t('cospend', 'Monthly') + '</option>' +
        '                    </select>' +
        '                </a>' +
        '            </li>';
    li = li + '            <li>' +
        '                <a href="#" class="deleteProject">' +
        '                    <span class="icon-delete"></span>' +
        '                    <span>' + deleteStr + '</span>' +
        '                </a>' +
        '            </li>';
    li = li + '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-deleted">' +
        '        <div class="app-navigation-entry-deleted-description">' + deletedStr + '</div>' +
        '        <button class="app-navigation-entry-deleted-button icon-history undoDeleteProject" title="Undo"></button>' +
        '    </div>' +
        '    <ul class="memberlist"></ul>' +
        '</li>';

    $(li).appendTo('#projectlist');

    // select project if it was the last selected (option restore on page load)
    if (!getUrlParameter('project') && cospend.restoredSelectedProjectId === projectid) {
        selectProject($('.projectitem[projectid="' + projectid + '"]'));
    } else if (getUrlParameter('project') === projectid) {
        selectProject($('.projectitem[projectid="' + projectid + '"]'));
    }

    $('.projectitem[projectid="' + projectid + '"] .autoexportSelect').val(project.autoexport);

    if (cospend.pageIsPublic) {
        $('.projectitem[projectid="' + projectid + '"] .shareProjectButton').hide();
        $('.projectitem[projectid="' + projectid + '"] .exportProject').parent().hide();
    }

    for (let i = 0; i < project.members.length; i++) {
        const memberId = project.members[i].id;
        addMember(projectid, project.members[i], project.balance[memberId]);
    }

    if (project.shares) {
        for (let i = 0; i < project.shares.length; i++) {
            const userid = project.shares[i].userid;
            const username = project.shares[i].name;
            const shid = project.shares[i].id;
            const accesslevel = parseInt(project.shares[i].accesslevel);
            addShare(projectid, userid, username, shid, 'u', accesslevel);
        }
    }

    if (project.group_shares) {
        for (let i = 0; i < project.group_shares.length; i++) {
            const groupid = project.group_shares[i].groupid;
            const groupname = project.group_shares[i].name;
            const shid = project.group_shares[i].id;
            const accesslevel = parseInt(project.group_shares[i].accesslevel);
            addShare(projectid, groupid, groupname, shid, 'g', accesslevel);
        }
    }

    if (project.circle_shares) {
        for (let i = 0; i < project.circle_shares.length; i++) {
            const circleid = project.circle_shares[i].circleid;
            const circlename = project.circle_shares[i].name;
            const shid = project.circle_shares[i].id;
            const accesslevel = parseInt(project.circle_shares[i].accesslevel);
            addShare(projectid, circleid, circlename, shid, 'c', accesslevel);
        }
    }

    if (project.public_shares) {
        for (let i = 0; i < project.public_shares.length; i++) {
            const token = project.public_shares[i].token;
            const shid = project.public_shares[i].id;
            const accesslevel = parseInt(project.public_shares[i].accesslevel);
            addShare(projectid, null, t('cospend', 'Public share link'), shid, 'l', accesslevel, token);
        }
    }

    if (project.myaccesslevel < constants.ACCESS.ADMIN) {
        $('li.projectitem[projectid="' + project.id + '"] .autoexportSelect').prop('disabled', true);
        $('li.projectitem[projectid="' + project.id + '"] .editProjectName').hide();
        $('li.projectitem[projectid="' + project.id + '"] .editProjectPassword').hide();
        $('li.projectitem[projectid="' + project.id + '"] .deleteProject').hide();
        if (project.myaccesslevel < constants.ACCESS.MAINTENER) {
            $('li.projectitem[projectid="' + project.id + '"] .addMember').hide();
            if (project.myaccesslevel < constants.ACCESS.PARTICIPANT) {
                $('li.projectitem[projectid="' + project.id + '"] .deleteUserShareButton').hide();
                $('li.projectitem[projectid="' + project.id + '"] .shareinput').hide();
            }
        }
    }

    //// set selected project
    //if (cospend.restoredSelectedProjectId === projectid) {
    //    $('.projectitem').removeClass('selectedproject');
    //    $('.projectitem[projectid="'+projectid+'"]').addClass('selectedproject');
    //    $('.app-navigation-entry-utils-counter').removeClass('highlighted');
    //    $('.projectitem[projectid="'+projectid+'"] .app-navigation-entry-utils-counter').addClass('highlighted');
    //}
}

export function selectProject(projectitem) {
    const projectid = projectitem.attr('projectid');
    const wasOpen = projectitem.hasClass('open');
    const wasSelected = (cospend.currentProjectId === projectid);
    if (cospend.projects[projectid].myaccesslevel <= constants.ACCESS.VIEWER) {
        if ($('#newBillButton').is(':visible')) {
            $('#newBillButton').fadeOut();
        }
    } else {
        if (!$('#newBillButton').is(':visible')) {
            $('#newBillButton').fadeIn();
        }
    }
    $('.projectitem.open').removeClass('open');
    if (!wasOpen) {
        projectitem.addClass('open');

        if (!wasSelected) {
            saveOptionValue({selectedProject: projectid});
            cospend.currentProjectId = projectid;
            $('.projectitem').removeClass('selectedproject');
            $('.projectitem[projectid="' + projectid + '"]').addClass('selectedproject');
            $('.app-navigation-entry-utils-counter').removeClass('highlighted');
            $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-utils-counter').addClass('highlighted');

            $('#billdetail').html('');
            getBills(projectid);
        }
    }
}

export function editGuestAccessLevelDb(projectid, accesslevel) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    $('li[projectid="' + projectid + '"] .accesslevelguest').addClass('icon-loading-small');
    const req = {
        accesslevel: accesslevel
    };
    let method, url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/editGuestAccessLevel');
        method = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/guest-access-level');
        method = 'PUT';
    }
    $.ajax({
        type: method,
        url: url,
        data: req,
        async: true
    }).done(function() {
        applyGuestAccessLevel(projectid, accesslevel);
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
        $('li[projectid="' + projectid + '"] .accesslevelguest').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit guest access level') +
            ': ' + response.responseJSON.message
        );
    });
}

export function applyGuestAccessLevel(projectid, accesslevel) {
    const projectLine = $('#projectlist li[projectid="' + projectid + '"]');
    projectLine.find('.accesslevelguest').removeClass('accesslevelActive');
    if (accesslevel === constants.ACCESS.VIEWER) {
        projectLine.find('.accesslevelguest.accesslevelViewer').addClass('accesslevelActive');
    } else if (accesslevel === constants.ACCESS.PARTICIPANT) {
        projectLine.find('.accesslevelguest.accesslevelParticipant').addClass('accesslevelActive');
    } else if (accesslevel === constants.ACCESS.MAINTENER) {
        projectLine.find('.accesslevelguest.accesslevelMaintener').addClass('accesslevelActive');
    } else if (accesslevel === constants.ACCESS.ADMIN) {
        projectLine.find('.accesslevelguest.accesslevelAdmin').addClass('accesslevelActive');
    }
}

export function autoSettlement(projectid) {
    $('.autoSettlement[projectid="' + projectid + '"] span').addClass('icon-loading-small');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/autoSettlement');
        type = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/autosettlement');
        type = 'GET';
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true
    }).done(function() {
        updateProjectBalances(projectid);
        getBills(projectid);
        Notification.showTemporary(t('cospend', 'Project settlement bills added'));
    }).always(function() {
        $('.autoSettlement[projectid="' + projectid + '"] span').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add project settlement bills') +
            ': ' + response.responseJSON.message
        );
    });
}

export function displayMemberPolarChart() {
    const categoryMemberStats = cospend.currentStats.categoryMemberStats;
    const projectid = cospend.currentStatsProjectId;
    let scroll = false;
    if (cospend.currentMemberPolarChart) {
        cospend.currentMemberPolarChart.destroy();
        delete cospend.currentMemberPolarChart;
        scroll = true;
    }
    const selectedMemberId = $('#memberPolarSelect').val();
    const memberName = cospend.members[projectid][selectedMemberId].name;

    if (Object.keys(categoryMemberStats).length === 0) {
        return;
    }

    const memberData = {
        datasets: [{
            data: [],
            backgroundColor: []
        }],
        labels: []
    };
    let catName, paid, color;
    for (const catId in categoryMemberStats) {
        //memberName = cospend.members[projectid][mid].name;
        if (cospend.hardCodedCategories.hasOwnProperty(catId)) {
            catName = cospend.hardCodedCategories[catId].icon + ' ' + cospend.hardCodedCategories[catId].name;
            color = cospend.hardCodedCategories[catId].color;
        } else if (cospend.projects[projectid].categories.hasOwnProperty(catId)) {
            catName = (cospend.projects[projectid].categories[catId].icon || '') +
                ' ' + cospend.projects[projectid].categories[catId].name;
            color = cospend.projects[projectid].categories[catId].color || 'red';
        } else {
            catName = t('cospend', 'No category');
            color = 'black';
        }
        paid = categoryMemberStats[catId][selectedMemberId].toFixed(2);
        memberData.datasets[0].data.push(paid);
        memberData.datasets[0].backgroundColor.push(color);
        memberData.labels.push(catName);
    }
    cospend.currentMemberPolarChart = new Chart($('#memberPolarChart'), {
        type: 'polarArea',
        data: memberData,
        options: {
            title: {
                display: true,
                text: t('cospend', 'What kind of member is "{m}"?', {m: memberName})
            },
            responsive: true,
            showAllTooltips: false,
            legend: {
                position: 'left'
            }
        }
    });
    if (scroll) {
        $(window).scrollTop($('#memberPolarSelect').position().top);
    }
}

export function displayCategoryMemberChart() {
    const categoryMemberStats = cospend.currentStats.categoryMemberStats;
    const projectid = cospend.currentStatsProjectId;
    let scroll = false;
    if (cospend.currentCategoryMemberChart) {
        cospend.currentCategoryMemberChart.destroy();
        delete cospend.currentCategoryMemberChart;
        scroll = true;
    }
    const selectedCatId = $('#categoryMemberSelect').val();
    let catName;
    if (selectedCatId === null || selectedCatId === '') {
        return;
    }
    if (cospend.hardCodedCategories.hasOwnProperty(selectedCatId)) {
        catName = cospend.hardCodedCategories[selectedCatId].icon + ' ' + cospend.hardCodedCategories[selectedCatId].name;
    } else if (cospend.projects[projectid].categories.hasOwnProperty(selectedCatId)) {
        catName = (cospend.projects[projectid].categories[selectedCatId].icon || '') +
            ' ' + cospend.projects[projectid].categories[selectedCatId].name;
    } else {
        catName = t('cospend', 'No category');
    }

    const categoryData = {
        datasets: [{
            data: [],
            backgroundColor: []
        }],
        labels: []
    };
    const categoryStats = categoryMemberStats[selectedCatId];
    let memberName, paid, color;
    for (const mid in categoryStats) {
        memberName = cospend.members[projectid][mid].name;
        color = '#' + cospend.members[projectid][mid].color;
        paid = categoryStats[mid].toFixed(2);
        categoryData.datasets[0].data.push(paid);
        categoryData.datasets[0].backgroundColor.push(color);
        categoryData.labels.push(memberName);
    }
    cospend.currentCategoryMemberChart = new Chart($('#categoryMemberChart'), {
        type: 'pie',
        data: categoryData,
        options: {
            title: {
                display: true,
                text: t('cospend', 'Who paid for category "{c}"?', {c: catName})
            },
            responsive: true,
            showAllTooltips: false,
            legend: {
                position: 'left'
            }
        }
    });
    if (scroll) {
        $(window).scrollTop($('#categoryMemberSelect').position().top);
    }
}
