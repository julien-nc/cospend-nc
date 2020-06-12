/*jshint esversion: 6 */

import Vue from 'vue';
import './bootstrap';
import MoneyBusterLink from './MoneyBusterLink';
import Settlement from './Settlement';
import Statistics from './Statistics';
import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import * as Chart from 'chart.js';
import * as constants from './constants';
import {getBills} from './bill';
import {
    copyToClipboard,
    getUrlParameter,
    Timer,
    slugify,
    saveOptionValue
} from './utils';
import {
    addMember,
    getMemberAvatar,
} from './member';
import {addShare} from './share';
import cospend from './state';
import {
    exportProject,
    exportSettlement,
    exportStatistics
} from './importExport';

//import SortedTablePlugin from 'vue-sorted-table';
//Vue.use(SortedTablePlugin);
import SmartTable from 'vuejs-smart-table'
Vue.use(SmartTable)

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
                $('#projectnameinput').focus().select();
            });
        }
    });

    $('#projectnameinput, #projectpasswordinput').on('keyup', function (e) {
        if (e.key === 'Enter') {
            const name = $('#projectnameinput').val();
            const id = slugify(name);
            const password = $('#projectpasswordinput').val();
            if (name && id) {
                createProject(id, name, password);
            } else {
                Notification.showTemporary(t('cospend', 'Invalid values'));
            }
        }
    });

    $('#newprojectform').submit(function (e) {
        const name = $('#projectnameinput').val();
        const id = slugify(name);
        const password = $('#projectpasswordinput').val();
        if (name && id) {
            createProject(id, name, password);
        } else {
            Notification.showTemporary(t('cospend', 'Invalid values'));
        }
        e.preventDefault();
    });

    $('#createproject').click(function () {
        const name = $('#projectnameinput').val();
        const id = slugify(name);
        const password = $('#projectpasswordinput').val();
        if (name && id) {
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

    //$('body').on('change', '#date-min-stats, #date-max-stats, #payment-mode-stats, ' +
    //    '#category-stats, #amount-min-stats, #amount-max-stats, ' +
    //    '#showDisabled, #currency-stats', function () {
    //        const projectid = cospend.currentProjectId;
    //        const dateMin = $('#date-min-stats').val();
    //        const dateMax = $('#date-max-stats').val();
    //        const tsMin = (dateMin !== '') ? moment(dateMin).unix() : null;
    //        const tsMax = (dateMax !== '') ? moment(dateMax).unix() + 24*60*60 - 1 : null;
    //        const paymentMode = $('#payment-mode-stats').val();
    //        const category = $('#category-stats').val();
    //        const amountMin = $('#amount-min-stats').val();
    //        const amountMax = $('#amount-max-stats').val();
    //        const showDisabled = $('#showDisabled').is(':checked');
    //        const currencyId = $('#currency-stats').val();
    //        getProjectStatistics(projectid, tsMin, tsMax, paymentMode, category, amountMin, amountMax, showDisabled, currencyId, dateMin, dateMax);
    //    });

    $('body').on('click', '.getProjectSettlement', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        if (cospend.currentProjectId !== projectid) {
            selectProject($('.projectitem[projectid="' + projectid + '"]'));
        }
        displaySettlement(projectid);
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
        const tsMin = (dateMin !== '') ? moment(dateMin).unix() : null;
        const tsMax = (dateMax !== '') ? moment(dateMax).unix() + 24*60*60 - 1 : null;
        const paymentMode = $('#payment-mode-stats').val();
        const category = $('#category-stats').val();
        const amountMin = $('#amount-min-stats').val();
        const amountMax = $('#amount-max-stats').val();
        const showDisabled = $('#showDisabled').is(':checked');
        const currencyId = $('#currency-stats').val();

        exportStatistics(projectid, tsMin, tsMax, paymentMode, category, amountMin, amountMax, showDisabled, currencyId);
    });

    $('body').on('click', '.exportSettlement', function() {
        const projectid = $(this).attr('projectid');
        let centeredOn = parseInt($('#settle-member-center').val());
        if (centeredOn === 0) {
            centeredOn = null;
        }
        exportSettlement(projectid, centeredOn);
    });

    $('body').on('click', '.autoSettlement', function() {
        const projectid = $(this).attr('projectid');
        let centeredOn = parseInt($('#settle-member-center').val());
        if (centeredOn === 0) {
            centeredOn = null;
        }
        autoSettlement(projectid, centeredOn);
    });

    //$('body').on('change', '#categoryMemberSelect', function() {
    //    displayCategoryMemberChart();
    //});

    //$('body').on('change', '#memberPolarSelect', function() {
    //    displayMemberPolarChart();
    //});

    $('body').on('click', '.getProjectStats', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        if (cospend.currentProjectId !== projectid) {
            selectProject($('.projectitem[projectid="' + projectid + '"]'));
        }
        displayStatistics(projectid);
    });
}

export function createProject(id, name, password) {
    if (!name || name.match('/') ||
        !id || id.match('/')
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
    const url = generateUrl('/apps/cospend/projects');
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
    let url;
    const project = cospend.projects[projectid];
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    $.ajax({
        type: 'PUT',
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
    let url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/projects/' + projectid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    $.ajax({
        type: 'DELETE',
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
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    cospend.currentGetProjectsAjax = $.ajax({
        type: 'GET',
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
            cospend.billLists = {};
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

export function getProjectStatistics(projectid, tsMin=null, tsMax=null, paymentMode=null, category=null,
                                      amountMin=null, amountMax=null, showDisabled=true, currencyId=null, dateMin=null, dateMax=null) {
    $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
    const req = {
        tsMin: tsMin,
        tsMax: tsMax,
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

export function displaySettlement(projectid) {
    // unselect bill
    $('.billitem').removeClass('selectedbill');

    const projectName = getProjectName(projectid);
    const container = $('#billdetail');
    container.html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Settlement of project {name}', {name: projectName});
    let exportButton = null;
    if (!cospend.pageIsPublic) {
        exportButton = $('<button/>', {class: 'exportSettlement', projectid: projectid})
            .append($('<span/>', {class: 'icon-save'}))
            .append(t('cospend', 'Export'));
    }
    const autoSettleButton = $('<button/>', {class: 'autoSettlement', projectid: projectid})
        .append($('<span/>', {class: 'icon-add'}))
        .append(t('cospend', 'Add these payments to project'));
    container.append($('<div/>', {id: 'app-details-toggle', tabindex: '0', class: 'icon-confirm'}));
    container.append(
        $('<h2/>', {id: 'settlementTitle'})
            .append($('<span/>', {class: 'icon-reimburse'}))
            .append(titleStr)
            .append(exportButton)
            .append(autoSettleButton)
    )
    .append($('<div/>', {id: 'settlement-div'}));

    new Vue({
        el: "#settlement-div",
        render: h => h(Settlement),
    });

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

    const projectName = getProjectName(projectid);
    const container = $('#billdetail');
    container.html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: projectName});

    container.append($('<div/>', {id: 'app-details-toggle', tabindex: 0, class: 'icon-confirm'}));
    const title = $('<h2/>', {id: 'mbTitle'})
        .text(titleStr)
        .prepend($('<span/>', {class: 'icon-phone'}));
    container.append(title)
        .append($('<div/>', {id: 'mb-link'}))

    new Vue({
        el: "#mb-link",
        render: h => h(MoneyBusterLink),
    });
}

function getCategory(projectid, catId) {
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

export function displayStatistics(projectid) {
                                  //  , allStats, dateMin = null, dateMax = null, paymentMode = null, category = null,
                                  // amountMin = null, amountMax = null, showDisabled = true, currencyId = null) {
    // deselect bill
    $('.billitem').removeClass('selectedbill');

    //const statList = allStats.stats;
    //const monthlyStats = allStats.monthlyStats;
    //const categoryStats = allStats.categoryStats;
    //const categoryMemberStats = allStats.categoryMemberStats;
    //const categoryMonthlyStats = allStats.categoryMonthlyStats;
    //const memberIds = allStats.memberIds;
    //cospend.currentStats = allStats;
    cospend.currentStatsProjectId = projectid;
    //let color;
    //const isFiltered = ((dateMin !== null && dateMin !== '')
    //    || (dateMax !== null && dateMax !== '')
    //    || (paymentMode !== null && paymentMode !== 'n')
    //    || (category !== null && parseInt(category) !== 0)
    //    || (amountMin !== null && amountMin !== '')
    //    || (amountMax !== null && amountMax !== '')
    //);

    const project = cospend.projects[projectid];
    const projectName = getProjectName(projectid);
    const container = $('#billdetail')
    container.html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Statistics of project {name}', {name: projectName});
    const nameStr = t('cospend', 'Member name');
    const paidStr = t('cospend', 'Paid');
    const spentStr = t('cospend', 'Spent');
    const balanceStr = t('cospend', 'Balance');
    const filteredBalanceStr = t('cospend', 'Filtered balance');

    let exportButton = null;
    if (!cospend.pageIsPublic) {
        exportButton = $('<button/>', {class: 'exportStats', projectid: projectid})
            .append($('<span/>', {class: 'icon-save'}))
            .append(t('cospend', 'Export'));
    }

    container.append($('<div/>', {id: 'app-details-toggle', tabindex: 0, class: 'icon-confirm'}))
        .append(
            $('<h2/>', {id: 'statsTitle'})
                .append($('<span/>', {class: 'icon-category-monitoring'}))
                .append(titleStr)
                .append(exportButton)
        )
        .append(
            $('<div/>', {id: 'stats-div'})
        );

    new Vue({
        el: "#stats-div",
        render: h => h(Statistics),
    });
}

export function getProjectName(projectid) {
    return cospend.projects[projectid].name;
}

export function updateProjectBalances(projectid) {
    const req = {};
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    cospend.currentGetProjectsAjax = $.ajax({
        type: 'GET',
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
    const renameStr = t('cospend', 'Rename');
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
    guestLink = window.location.protocol + '//' + window.location.host + guestLink;
    const guestAccessLevel = parseInt(project.guestaccesslevel);
    const li = $('<li/>', {class: 'projectitem collapsible', projectid: projectid})
        .append(
            $('<a/>', {class: 'icon-folder', href: '#', title: projectid})
                .append($('<span/>').text(name))
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-utils'})
                .append(
                    $('<ul/>')
                        .append(
                            $('<li/>', {class: 'app-navigation-entry-utils-counter'})
                                .append($('<span/>').text(project.members.length))
                        )
                        .append(
                            $('<li/>', {class: 'app-navigation-entry-utils-menu-button shareProjectButton'})
                                .append($('<button/>', {class: 'icon-shar'}))
                        )
                        .append(
                            $('<li/>', {class: 'app-navigation-entry-utils-menu-button projectMenuButton'})
                                .append($('<button/>'))
                        )
                )
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-edit'})
                .append(
                    $('<div/>')
                        .append($('<input/>', {type: 'text', maxlength: 300, value: project.name, class: 'editProjectInput'}))
                        .append($('<input/>', {type: 'submit', value: '', class: 'icon-close editProjectClose'}))
                        .append($('<input/>', {type: 'submit', value: '', class: 'icon-checkmark editProjectOk'}))
                )
        )
        .append(
            $('<ul/>', {class: 'app-navigation-entry-share'})
                .append(
                    $('<li/>', {class: 'shareinputli', title: shareTitle})
                        .append($('<input/>', {type: 'text', maxlength: 300, class: 'shareinput', placeholder: defaultShareText}))
                )
                .append(
                    $('<li/>', {class: 'addpubshareitem'})
                        .append(
                            $('<a/>', {class: 'icon-public', href: '#'})
                                .append($('<span/>').text(t('cospend', 'Add public link')))
                        )
                        .append(
                            $('<div/>', {class: 'app-navigation-entry-utils'})
                                .append(
                                    $('<ul/>')
                                        .append(
                                            $('<li/>', {class: 'app-navigation-entry-utils-menu-button addPublicShareButton'})
                                                .append($('<button/>', {class: 'icon-add'}))
                                        )
                                )
                        )
                )
        )
        .append(
            $('<div/>', {class: 'newmemberdiv'})
                .append($('<input/>', {class: 'newmembername', maxlength: 300, type: 'text', value: '', placeholder: t('cospend', 'New member name')}))
                .append($('<button/>', {class: 'newmemberbutton icon-add'}))
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-menu'})
                .append(
                    $('<ul/>')
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: 'addMember'})
                                        .append($('<span/>', {class: 'icon-add'}))
                                        .append($('<span/>').text(addMemberStr))
                                )
                        )
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: 'copyProjectGuestLink', title: guestLink, style: 'padding-right: 0px !important;'})
                                        .append($('<span/>', {class: 'icon-clippy'}))
                                        .append($('<span/>', {class: 'guest-link-label'}).text(guestAccessStr + ' '))
                                        .append(
                                            $('<div/>', {class: 'guestaccesslevel'})
                                                .append($('<div/>', {
                                                    class: 'icon-user-admin accesslevelguest accesslevelAdmin ' +
                                                        (guestAccessLevel === constants.ACCESS.ADMIN ? 'accesslevelActive' : ''),
                                                    title: t('cospend', 'Admin: edit/delete project + maintener permissions')
                                                }))
                                                .append($('<div/>', {
                                                    class: 'icon-category-customization accesslevelguest accesslevelMaintener ' +
                                                        (guestAccessLevel === constants.ACCESS.MAINTENER ? 'accesslevelActive' : ''),
                                                    title: t('cospend', 'Maintener: add/edit members/categories/currencies + participant permissions')
                                                }))
                                                .append($('<div/>', {
                                                    class: 'icon-rename accesslevelguest accesslevelParticipant ' +
                                                        (guestAccessLevel === constants.ACCESS.PARTICIPANT ? 'accesslevelActive' : ''),
                                                    title: t('cospend', 'Participant: add/edit/delete bills + viewer permissions')
                                                }))
                                                .append($('<div/>', {
                                                    class: 'icon-toggle accesslevelguest accesslevelViewer ' +
                                                        (guestAccessLevel === constants.ACCESS.VIEWER ? 'accesslevelActive' : ''),
                                                    title: t('cospend', 'Viewer')
                                                }))
                                        )
                                )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'moneyBusterProjectUrl'})
                                    .append($('<span/>', {class: 'icon-phone'}))
                                    .append($('<span/>').text(moneyBusterUrlStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'editProjectName'})
                                    .append($('<span/>', {class: 'icon-rename'}))
                                    .append($('<span/>').text(renameStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'editProjectPassword'})
                                    .append($('<span/>', {class: 'icon-password'}))
                                    .append($('<span/>').text(changePwdStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'manageProjectCategories'})
                                    .append($('<span/>', {class: 'icon-category-app-bundles'}))
                                    .append($('<span/>').text(manageCategoriesStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'manageProjectCurrencies'})
                                    .append($('<span/>', {class: 'icon-currencies'}))
                                    .append($('<span/>').text(manageCurrenciesStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'getProjectStats'})
                                    .append($('<span/>', {class: 'icon-category-monitoring'}))
                                    .append($('<span/>').text(displayStatsStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'getProjectSettlement'})
                                    .append($('<span/>', {class: 'icon-reimburse'}))
                                    .append($('<span/>').text(settleStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'exportProject'})
                                    .append($('<span/>', {class: 'icon-save'}))
                                    .append($('<span/>').text(exportStr))
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'autoexportProject'})
                                    .append($('<span/>', {class: 'icon-schedule'}))
                                    .append($('<span/>', {class: 'autoexportLabel'}).text(autoexportStr))
                                    .append(
                                        $('<select/>', {class: 'autoexportSelect'})
                                            .append($('<option/>', {value: 'n'}).text(t('cospend', 'No')))
                                            .append($('<option/>', {value: 'd'}).text(t('cospend', 'Daily')))
                                            .append($('<option/>', {value: 'w'}).text(t('cospend', 'Weekly')))
                                            .append($('<option/>', {value: 'm'}).text(t('cospend', 'Monthly')))
                                    )
                            )
                        )
                        .append(
                            $('<li/>').append(
                                $('<a/>', {href: '#', class: 'deleteProject'})
                                    .append($('<span/>', {class: 'icon-delete'}))
                                    .append($('<span/>').text(deleteStr))
                            )
                        )
                )
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-deleted'})
                .append($('<div/>', {class: 'app-navigation-entry-deleted-description'}).text(deletedStr))
                .append($('<button/>', {class: 'app-navigation-entry-deleted-button icon-history undoDeleteProject', title: t('cospend', 'Undo')}))
        )
        .append($('<ul/>', {class: 'memberlist'}))

    $('#projectlist').append(li);

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
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/guest-access-level');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/guest-access-level');
    }
    $.ajax({
        type: 'PUT',
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

export function autoSettlement(projectid, centeredOn=null) {
    $('.autoSettlement[projectid="' + projectid + '"] span').addClass('icon-loading-small');
    const req = {
        centeredOn: centeredOn
    };
    let url, type;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/'+ projectid +'/auto-settlement');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/autosettlement');
    }
    $.ajax({
        type: 'GET',
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
