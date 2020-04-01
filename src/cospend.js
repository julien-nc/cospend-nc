/*jshint esversion: 6 */

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

import * as Notification from './notification';
import * as Chart from 'chart.js/dist/Chart';
import * as moment from 'moment';
import * as constants from './constants';
import {
    MEMBER_NAME_EDITION,
    MEMBER_WEIGHT_EDITION,
    PROJECT_NAME_EDITION,
    PROJECT_PASSWORD_EDITION
} from './constants';
import {generateUrl} from '@nextcloud/router';
import {
    checkKey,
    copyToClipboard,
    delay,
    generatePublicLinkToFile,
    hexToDarkerHex,
    saveOptionValue,
    Timer,
    updateCustomAmount
} from "./utils";
import {
    autoSettlement,
    createProject,
    deleteProject,
    editGuestAccessLevelDb,
    editProject,
    getProjectMoneyBusterLink,
    getProjectName,
    getProjects,
    getProjectSettlement,
    getProjectStatistics,
    selectProject
} from "./project";
import {
    addBill,
    createCustomAmountBill,
    createEquiPersoBill,
    createNormalBill,
    deleteBill,
    displayBill,
    onBillEdited
} from "./bill";
import {
    askChangeMemberColor,
    okMemberColor,
    createMember,
    displayCategoryMemberChart,
    displayMemberPolarChart,
    editMember
} from "./member";
import {
    addUserAutocompletion,
    addPublicShareDb,
    deleteCircleShareDb,
    deleteGroupShareDb,
    deletePublicShareDb,
    deleteUserShareDb,
    editShareAccessLevelDb
} from "./share";
import {
    addCategoryDb,
    deleteCategoryDb,
    editCategoryDb,
    getProjectCategories
} from "./category";
import {
    addCurrencyDb,
    deleteCurrencyDb,
    editCurrencyDb,
    getProjectCurrencies
} from "./currency";
import {
    exportProject,
    exportSettlement,
    exportStatistics,
    importProject,
    importSWProject
} from "./importExport";
import cospend from "./state";


(function ($, OC) {
    'use strict';

    Chart.plugins.register({
        beforeRender: function (chart) {
            if (chart.config.options.showAllTooltips) {
                // create an array of tooltips
                // we can't use the chart tooltip because there is only one tooltip per chart
                chart.pluginTooltips = [];
                chart.config.data.datasets.forEach(function (dataset, i) {
                    chart.getDatasetMeta(i).data.forEach(function (sector, j) {
                        chart.pluginTooltips.push(new Chart.Tooltip({
                            _chart: chart.chart,
                            _chartInstance: chart,
                            _data: chart.data,
                            _options: chart.options.tooltips,
                            _active: [sector]
                        }, chart));
                    });
                });

                // turn off normal tooltips
                chart.options.tooltips.enabled = false;
            }
        },
        afterDraw: function (chart, easing) {
            if (chart.config.options.showAllTooltips) {
                // we don't want the permanent tooltips to animate, so don't do anything till the animation runs atleast once
                if (!chart.allTooltipsOnce) {
                    if (easing !== 1) {
                        return;
                    }
                    chart.allTooltipsOnce = true;
                }

                // turn on tooltips
                chart.options.tooltips.enabled = true;
                Chart.helpers.each(chart.pluginTooltips, function (tooltip) {
                    tooltip.initialize();
                    tooltip.update();
                    // we don't actually need this since we are not animating tooltips
                    tooltip.pivot();
                    tooltip.transition(easing).draw();
                });
                chart.options.tooltips.enabled = false;
            }
        }
    });

    // trick to always show public link item: replace default autocomplete filter function
    const origFilter = $.ui.autocomplete.filter;
    $.ui.autocomplete.filter = function (array, term) {
        const result = [cospend.pubLinkData];
        return result.concat(origFilter(array, term));
    };

    function restoreOptions () {
        const url = generateUrl('/apps/cospend/getOptionsValues');
        const req = {};
        let optionsValues = {};
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            optionsValues = response.values;
            if (optionsValues) {
                for (const k in optionsValues) {
                    if (k === 'selectedProject') {
                        cospend.restoredSelectedProjectId = optionsValues[k];
                    } else if (k === 'outputDirectory') {
                        $('#outputDirectory').text(optionsValues[k]);
                    }
                }
            }
            main();
        }).fail(function () {
            Notification.showTemporary(
                t('cospend', 'Failed to restore options values')
            );
        });
    }

    $(document).ready(function() {
        cospend.pageIsPublic = (document.URL.indexOf('/cospend/project') !== -1 || document.URL.indexOf('/cospend/s/') !== -1);
        if (!cospend.pageIsPublic) {
            restoreOptions();
        } else {
            //restoreOptionsFromUrlParams();
            $('#newprojectbutton').hide();
            $('#set-output-div').hide();
            $('#importProjectButton').hide();
            $('#importSWProjectButton').hide();
            cospend.projectid = $('#projectid').text();
            cospend.password = $('#password').text();
            cospend.restoredSelectedProjectId = cospend.projectid;
            $('#projectid').html('');
            $('#password').html('');
            main();
        }
    });

    function main() {
        // get key events
        document.onkeydown = checkKey;

        window.onclick = function(event) {
            if (!event.target.matches('.app-navigation-entry-utils-menu-button button')) {
                $('.app-navigation-entry-menu.open').removeClass('open');
            }
            if (!event.target.matches('.newmemberdiv, .newmemberdiv input, .newmemberdiv .newmemberbutton, .addMember, .addMember span')) {
                $('.newmemberdiv').slideUp();
            }
        };

        $('body').on('focus', '.shareinput', function() {
            $(this).select();
            const projectid = $(this).parent().parent().parent().attr('projectid');
            addUserAutocompletion($(this), projectid);
        });

        $('body').on('click', '.deleteUserShareButton', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const shid = $(this).parent().parent().parent().parent().attr('shid');
            deleteUserShareDb(projectid, shid);
        });

        $('body').on('click', '.deleteGroupShareButton', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const shid = $(this).parent().parent().parent().parent().attr('shid');
            deleteGroupShareDb(projectid, shid);
        });

        $('body').on('click', '.deleteCircleShareButton', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const shid = $(this).parent().parent().parent().parent().attr('shid');
            deleteCircleShareDb(projectid, shid);
        });

        $('body').on('click', '.deletePublicShareButton', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const shid = $(this).parent().parent().parent().parent().attr('shid');
            deletePublicShareDb(projectid, shid);
        });

        $('body').on('click', '.copyPublicShareButton', function () {
            const token = $(this).parent().parent().parent().attr('token');
            const publicLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/s/' + token);
            copyToClipboard(publicLink);
            Notification.showTemporary(t('cospend', 'Public link copied to clipboard'));
        });

        $('body').on('click', '.addPublicShareButton', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            addPublicShareDb(projectid);
        });

        $('body').on('click', '.accesslevel', function (e) {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const shid = $(this).parent().parent().parent().parent().attr('shid');
            let accesslevel = constants.ACCESS.VIEWER;
            if ($(this).hasClass('accesslevelAdmin')) {
                accesslevel = constants.ACCESS.ADMIN;
            } else if ($(this).hasClass('accesslevelMaintener')) {
                accesslevel = constants.ACCESS.MAINTENER;
            } else if ($(this).hasClass('accesslevelParticipant')) {
                accesslevel = constants.ACCESS.PARTICIPANT;
            }
            editShareAccessLevelDb(projectid, shid, accesslevel);
            e.stopPropagation();
        });

        $('body').on('click', '.accesslevelguest', function () {
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

        $('body').on('click', '.shareProjectButton', function () {
            const shareDiv = $(this).parent().parent().parent().find('.app-navigation-entry-share');
            if (shareDiv.is(':visible')) {
                shareDiv.slideUp();
                $(this).removeClass('activeButton');
            } else {
                shareDiv.slideDown();
                $(this).addClass('activeButton');
            }
        });

        $('body').on('click', '.projectMenuButton, .memberMenuButton', function () {
            const wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });

        $('body').on('click', '.projectitem > a', function () {
            selectProject($(this).parent());
        });

        $('body').on('click', '.projectitem', function (e) {
            if (e.target.tagName === 'LI' && $(e.target).hasClass('projectitem')) {
                selectProject($(this));
            }
        });

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

        $('body').on('click', '.addMember', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');

            const newmemberdiv = $('.projectitem[projectid="' + projectid + '"] .newmemberdiv');
            newmemberdiv.show().attr('style', 'display: inline-flex;');
            const defaultMemberName = t('cospend', 'newMemberName');
            newmemberdiv.find('.newmembername').val(defaultMemberName).focus().select();
        });

        $('body').on('click', '.newmemberbutton', function () {
            const projectid = $(this).parent().parent().attr('projectid');
            const name = $(this).parent().find('input').val();
            if (projectid && name) {
                createMember(projectid, name);
            } else {
                Notification.showTemporary(t('cospend', 'Invalid values'));
            }
        });

        $('body').on('keyup', '.newmembername', function (e) {
            if (e.key === 'Enter') {
                const name = $(this).val();
                const projectid = $(this).parent().parent().attr('projectid');
                if (projectid && name) {
                    createMember(projectid, name);
                } else {
                    Notification.showTemporary(t('cospend', 'Invalid values'));
                }
            }
        });

        $('body').on('click', '.renameMember', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const mid = $(this).parent().parent().parent().parent().attr('memberid');
            const name = cospend.members[projectid][mid].name;
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(name).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            cospend.memberEditionMode = MEMBER_NAME_EDITION;
        });

        $('body').on('click', '.editWeightMember', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const mid = $(this).parent().parent().parent().parent().attr('memberid');
            const weight = cospend.members[projectid][mid].weight;
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(weight).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            cospend.memberEditionMode = MEMBER_WEIGHT_EDITION;
        });

        $('body').on('click', '.editMemberClose', function () {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editMemberInput', function (e) {
            if (e.key === 'Enter') {
                const memberid = $(this).parent().parent().parent().attr('memberid');
                const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
                let newName;
                if (cospend.memberEditionMode === MEMBER_NAME_EDITION) {
                    newName = $(this).val();
                    editMember(projectid, memberid, newName, null, null);
                } else if (cospend.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                    const newWeight = parseFloat($(this).val());
                    if (!isNaN(newWeight)) {
                        newName = cospend.members[projectid][memberid].name;
                        editMember(projectid, memberid, newName, newWeight, null);
                    } else {
                        Notification.showTemporary(t('cospend', 'Invalid weight'));
                    }
                }
            }
        });

        $('body').on('click', '.editMemberOk', function () {
            const memberid = $(this).parent().parent().parent().attr('memberid');
            const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            let newName;
            if (cospend.memberEditionMode === MEMBER_NAME_EDITION) {
                newName = $(this).parent().find('.editMemberInput').val();
                editMember(projectid, memberid, newName, null, null);
            } else if (cospend.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                const newWeight = parseFloat($(this).parent().find('.editMemberInput').val());
                if (!isNaN(newWeight)) {
                    newName = cospend.members[projectid][memberid].name;
                    editMember(projectid, memberid, newName, newWeight, null);
                } else {
                    Notification.showTemporary(t('cospend', 'Invalid weight'));
                }
            }
        });

        $('body').on('click', '.toggleMember', function () {
            const memberid = $(this).parent().parent().parent().parent().attr('memberid');
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const newName = $(this).parent().parent().parent().parent().find('>a span b.memberName').text();
            const activated = $(this).find('span').first().hasClass('icon-history');
            editMember(projectid, memberid, newName, null, activated);
        });

        $('body').on('click', '.editProjectName', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            const name = cospend.projects[projectid].name;
            $(this).parent().parent().parent().parent().find('.editProjectInput').val(name).attr('type', 'text').focus().select();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            cospend.projectEditionMode = PROJECT_NAME_EDITION;
        });

        $('body').on('click', '.editProjectPassword', function () {
            $(this).parent().parent().parent().parent().find('.editProjectInput').attr('type', 'password').val('').focus();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            cospend.projectEditionMode = PROJECT_PASSWORD_EDITION;
        });

        $('body').on('click', '.editProjectClose', function () {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editProjectInput', function (e) {
            if (e.key === 'Enter') {
                let newName;
                const projectid = $(this).parent().parent().parent().attr('projectid');
                if (cospend.projectEditionMode === PROJECT_NAME_EDITION) {
                    newName = $(this).val();
                    editProject(projectid, newName, null, null);
                } else if (cospend.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                    const newPassword = $(this).val();
                    newName = $(this).parent().parent().parent().find('>a span').text();
                    editProject(projectid, newName, null, newPassword);
                }
            }
        });

        $('body').on('click', '.editProjectOk', function () {
            const projectid = $(this).parent().parent().parent().attr('projectid');
            let newName;
            if (cospend.projectEditionMode === PROJECT_NAME_EDITION) {
                newName = $(this).parent().find('.editProjectInput').val();
                editProject(projectid, newName, null, null);
            } else if (cospend.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                const newPassword = $(this).parent().find('.editProjectInput').val();
                newName = $(this).parent().parent().parent().find('>a span').text();
                editProject(projectid, newName, null, newPassword);
            }
        });

        $('body').on('click', '.billitem', function (e) {
            if (!$(e.target).hasClass('deleteBillIcon') && !$(e.target).hasClass('undoDeleteBill')) {
                const billid = parseInt($(this).attr('billid'));
                const projectid = $(this).attr('projectid');
                displayBill(projectid, billid);
            }
        });

        // what and amount : delay on edition
        $('body').on('keyup paste change', '.input-bill-what', delay(function () {
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
                $('.bill-repeat-extra').hide();
            } else {
                $('.bill-repeat-extra').show();
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
                        $('#bill-list').html('<h2 class="nobill">' + t('cospend', 'No bill yet') + '</h2>');
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

        $('body').on('focus', '.input-bill-what, .input-bill-amount, #projectidinput, #projectnameinput, #projectpasswordinput', function () {
            $(this).select();
        });

        $('body').on('click', '.moneyBusterProjectUrl', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectMoneyBusterLink(projectid);
        });

        $('body').on('click', '.getProjectStats', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectStatistics(projectid, null, null, null, -100);
        });

        $('body').on('click', '.manageProjectCurrencies', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectCurrencies(projectid);
        });

        $('body').on('click', '.manageProjectCategories', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectCategories(projectid);
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

        $('body').on('click', '.copyProjectGuestLink', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/loginproject/' + projectid);
            copyToClipboard(guestLink);
            Notification.showTemporary(t('cospend', 'Guest link for \'{pid}\' copied to clipboard', {pid: projectid}));
        });

        let guestLink = generateUrl('/apps/cospend/login');
        guestLink = window.location.protocol + '//' + window.location.host + guestLink;
        $('#generalGuestLinkButton').attr('title', guestLink);

        $('body').on('click', '#generalGuestLinkButton', function () {
            let guestLink = generateUrl('/apps/cospend/login');
            guestLink = window.location.protocol + '//' + window.location.host + guestLink;
            $('<input id="dummycopy">').val(guestLink).appendTo('body').select();
            document.execCommand('copy');
            $('#dummycopy').remove();
            Notification.showTemporary(t('cospend', 'Guest link copied to clipboard'));
        });

        $('body').on('click', '#app-details-toggle', function () {
            $('.app-content-list').removeClass('showdetails');
        });

        $('body').on('click', '#addFileLinkButton', function () {
            OC.dialogs.filepicker(
                t('cospend', 'Choose file'),
                function (targetPath) {
                    generatePublicLinkToFile(targetPath);
                },
                false, null, true
            );
        });

        $('body').on('click', '#importProjectButton', function () {
            OC.dialogs.filepicker(
                t('cospend', 'Choose csv project file'),
                function (targetPath) {
                    importProject(targetPath);
                },
                false,
                ['text/csv'],
                true
            );
        });

        $('body').on('click', '#importSWProjectButton', function () {
            OC.dialogs.filepicker(
                t('cospend', 'Choose SplitWise project file'),
                function (targetPath) {
                    importSWProject(targetPath);
                },
                false,
                ['text/csv'],
                true
            );
        });

        $('body').on('click', '.exportProject', function () {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            exportProject(projectid);
        });

        $('body').on('click', '.autoexportSelect, .accesslevelguest', function (e) {
            e.stopPropagation();
        });

        $('body').on('change', '.autoexportSelect', function () {
            const newval = $(this).val();
            const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            const projectName = getProjectName(projectid);
            editProject(projectid, projectName, null, null, newval);
            $(this).parent().click();
        });

        $('body').on('click', '.exportStats', function () {
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

        $('body').on('click', '.exportSettlement', function () {
            const projectid = $(this).attr('projectid');
            exportSettlement(projectid);
        });

        $('body').on('click', '.autoSettlement', function () {
            const projectid = $(this).attr('projectid');
            autoSettlement(projectid);
        });

        $('body').on('click', '#modehintbutton', function () {
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

        $('body').on('change', '#billtype', function () {
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
                $('.bill-owers .checkbox').each(function () {
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

        $('body').on('paste change', '.amountinput', function () {
            const billtype = $('#billtype').val();
            if (billtype === 'custom') {
                updateCustomAmount();
            }
        });

        $('body').on('keyup', '.amountinput', function (e) {
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

        $('body').on('click', '#owerValidate', function () {
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

        $('body').on('click', '#changeOutputButton', function () {
            OC.dialogs.filepicker(
                t('maps', 'Choose where to write output files (stats, settlement, export)'),
                function (targetPath) {
                    if (targetPath === '') {
                        targetPath = '/';
                    }
                    saveOptionValue({outputDirectory: targetPath});
                    $('#outputDirectory').text(targetPath);
                },
                false,
                'httpd/unix-directory',
                true
            );
        });

        $('body').on('change', '#categoryMemberSelect', function () {
            displayCategoryMemberChart();
        });

        $('body').on('change', '#memberPolarSelect', function () {
            displayMemberPolarChart();
        });

        $('body').on('click', '.memberAvatar', function () {
            const projectid = $(this).parent().parent().parent().attr('projectid');
            const memberid = $(this).parent().attr('memberid');
            askChangeMemberColor(projectid, memberid);
        });

        $('body').on('click', '.editColorMember', function () {
            const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            const memberid = $(this).parent().parent().parent().parent().attr('memberid');
            askChangeMemberColor(projectid, memberid);
        });

        $('body').on('change', '#membercolorinput', function () {
            okMemberColor();
        });

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
            $(this).parent().hide();
            $(this).parent().parent().find('.one-currency-label').show();
        });

        // manage categories TODO
        $('body').on('click', '.addCategoryOk', function () {
            const projectid = $('#catTitle').attr('projectid');
            const name = $('#addCategoryNameInput').val();
            if (name === null || name === '') {
                Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                return;
            }
            const icon = $('#addCategoryIconInput').val();
            if (icon === null || icon === '') {
                Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                return;
            }
            const color = $('#addCategoryColorInput').val();
            if (color === null || color === '') {
                Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                return;
            }
            addCategoryDb(projectid, name, icon, color);
        });

        $('body').on('keyup', '#addCategoryNameInput, #addCategoryIconInput', function (e) {
            if (e.key === 'Enter') {
                const projectid = $('#catTitle').attr('projectid');
                const name = $('#addCategoryNameInput').val();
                if (name === null || name === '') {
                    Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                    return;
                }
                const icon = $('#addCategoryIconInput').val();
                if (icon === null || icon === '') {
                    Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                    return;
                }
                const color = $('#addCategoryColorInput').val();
                if (color === null || color === '') {
                    Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                    return;
                }
                addCategoryDb(projectid, name, icon, color);
            }
        });

        $('body').on('click', '.deleteOneCategory', function () {
            const projectid = $('#catTitle').attr('projectid');
            const categoryId = $(this).parent().parent().attr('categoryid');
            if ($(this).hasClass('icon-history')) {
                $(this).removeClass('icon-history').addClass('icon-delete');
                cospend.categoryDeletionTimer[categoryId].pause();
                delete cospend.categoryDeletionTimer[categoryId];
            } else {
                $(this).addClass('icon-history').removeClass('icon-delete');
                cospend.categoryDeletionTimer[categoryId] = new Timer(function () {
                    deleteCategoryDb(projectid, categoryId);
                }, 7000);
            }
        });

        $('body').on('click', '.editOneCategory', function () {
            $(this).parent().hide();
            $(this).parent().parent().find('.one-category-edit').show()
                .css('display', 'grid')
                .find('.editCategoryNameInput').focus().select();
        });

        $('body').on('click', '.editCategoryOk', function () {
            const projectid = $('#catTitle').attr('projectid');
            const categoryId = $(this).parent().parent().attr('categoryid');
            const name = $(this).parent().find('.editCategoryNameInput').val();
            if (name === null || name === '') {
                Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                return;
            }
            const icon = $(this).parent().find('.editCategoryIconInput').val();
            if (icon === null || icon === '') {
                Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                return;
            }
            const color = $(this).parent().find('.editCategoryColorInput').val();
            if (color === null || color === '') {
                Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                return;
            }
            editCategoryDb(projectid, categoryId, name, icon, color);
        });

        $('body').on('keyup', '.editCategoryNameInput, .editCategoryIconInput', function (e) {
            if (e.key === 'Enter') {
                const projectid = $('#catTitle').attr('projectid');
                const categoryId = $(this).parent().parent().attr('categoryid');
                const name = $(this).parent().find('.editCategoryNameInput').val();
                if (name === null || name === '') {
                    Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                    return;
                }
                const icon = $(this).parent().find('.editCategoryIconInput').val();
                if (icon === null || icon === '') {
                    Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                    return;
                }
                const color = $(this).parent().find('.editCategoryColorInput').val();
                if (color === null || color === '') {
                    Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                    return;
                }
                editCategoryDb(projectid, categoryId, name, icon, color);
            }
        });
        $('body').on('click', '.one-category-label-color', function (e) {
            e.preventDefault();
        });

        $('body').on('click', '.editCategoryClose', function () {
            $(this).parent().hide();
            $(this).parent().parent().find('.one-category-label').show();
        });

        $('body').on('click', '.owerEntry .owerAvatar', function () {
            const billId = parseInt($('#billdetail .bill-title').attr('billid'));
            const billType = $('#billtype').val();
            if (billId !== 0 || billType === 'normal' || billType === 'perso') {
                $(this).parent().find('input').click();
            }
        });

        // context menu (right click)
        $('body').on('contextmenu',
            '.memberitem > .app-navigation-entry-utils, .memberitem > a, .memberitem .memberAvatar, ' +
            '.shareitem > .app-navigation-entry-utils, .shareitem > a, ' +
            '.projectitem > .app-navigation-entry-utils, .projectitem > a ',
            function(e) {
                var menu = $(this).parent().find('> .app-navigation-entry-menu');
                var wasOpen = menu.hasClass('open');
                $('.app-navigation-entry-menu.open').removeClass('open');
                if (!wasOpen) {
                    menu.addClass('open');
                }
                return false;
            }
        );

        // right click on expand icon
        $('body').on('contextmenu', '.projectitem', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).hasClass('projectitem')) {
                var menu = $(this).find('> .app-navigation-entry-menu');
                var wasOpen = menu.hasClass('open');
                $('.app-navigation-entry-menu.open').removeClass('open');
                if (!wasOpen) {
                    menu.addClass('open');
                }
                return false;
            }
        });

        if (OCA.Theming) {
            const c = OCA.Theming.color;
            // invalid color
            if (!c || (c.length !== 4 && c.length !== 7)) {
                cospend.themeColor = '#0082C9';
            }
            // compact
            else if (c.length === 4) {
                cospend.themeColor = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
            }
            // normal
            else if (c.length === 7) {
                cospend.themeColor = c;
            }
        } else {
            cospend.themeColor = '#0082C9';
        }
        cospend.themeColorDark = hexToDarkerHex(cospend.themeColor);

        // last thing to do : get the projects
        getProjects();
    }

})(jQuery, OC);
