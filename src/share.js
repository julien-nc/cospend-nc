/*jshint esversion: 6 */

import {generateUrl} from "@nextcloud/router";
import * as constants from "./constants";
import {getProjectName} from "./project";
import * as Notification from "./notification";

export function addUserShareDb (projectid, userid, username) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        userid: userid
    };
    const url = generateUrl('/apps/cospend/addUserShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function (response) {
        addShare(projectid, userid, username, response, 'u', constants.ACCESS.PARTICIPANT);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Project {pname} is now shared with {uname}', {
            pname: projectname,
            uname: username
        }));
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add user share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function deleteUserShareDb (projectid, shid) {
    $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] ' +
        '.deleteUserShareButton span:first').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        shid: shid
    };
    const url = generateUrl('/apps/cospend/deleteUserShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function () {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function () {
            li.remove();
        });
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] ' +
            '.deleteUserShareButton span:first').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete user share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addPublicShareDb (projectid) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
    };
    const url = generateUrl('/apps/cospend/addPublicShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function (response) {
        addShare(projectid, null, t('cospend', 'Public share link'), response.id, 'l', constants.ACCESS.PARTICIPANT, response.token);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Public access link added for project {pname}', {pname: projectname}));
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add public share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function deletePublicShareDb (projectid, shid) {
    $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] ' +
        '.deletePublicShareButton span:first').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        shid: shid
    };
    const url = generateUrl('/apps/cospend/deletePublicShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function () {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function () {
            li.remove();
        });
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] ' +
            '.deletePublicShareButton span:first').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete public share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addCircleShareDb (projectid, circleId, circleName) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        circleid: circleId
    };
    const url = generateUrl('/apps/cospend/addCircleShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function (response) {
        addShare(projectid, circleId, circleName, response, 'c', constants.ACCESS.PARTICIPANT);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Project {pname} is now shared with circle {cname}', {
            pname: projectname,
            cname: circleName
        }));
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add circle share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function deleteCircleShareDb (projectid, shid) {
    $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteCircleShareButton').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        shid: shid
    };
    const url = generateUrl('/apps/cospend/deleteCircleShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function () {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function () {
            li.remove();
        });
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteCircleShareButton').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete circle share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addGroupShareDb (projectid, groupid, groupname) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        groupid: groupid
    };
    const url = generateUrl('/apps/cospend/addGroupShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function (response) {
        addShare(projectid, groupid, groupname, response, 'g', constants.ACCESS.PARTICIPANT);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Project {pname} is now shared with group {gname}', {
            pname: projectname,
            gname: groupname
        }));
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add group share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addShare (projectid, elemId, elemName, id, type, accesslevel, token = null) {
    let displayString = elemId;
    if (type === 'c' || type === 'l') {
        displayString = elemName;
    } else if (elemId !== elemName) {
        displayString = elemName + ' (' + elemId + ')';
    }
    let iconClass, deleteButtonClass;
    if (type === 'g') {
        iconClass = 'icon-group';
        deleteButtonClass = 'deleteGroupShareButton';
    } else if (type === 'u') {
        iconClass = 'icon-user';
        deleteButtonClass = 'deleteUserShareButton';
    } else if (type === 'c') {
        iconClass = 'share-icon-circle';
        deleteButtonClass = 'deleteCircleShareButton';
    } else if (type === 'l') {
        iconClass = 'icon-public';
        deleteButtonClass = 'deletePublicShareButton';
    }
    const tokenStr = (type === 'l') ? 'token="' + token + '"' : '';
    let li =
        '<li class="shareitem" shid="' + id + '" ' + tokenStr + ' elemid="' + escapeHTML(elemId) + '" elemname="' + escapeHTML(elemName) + '">' +
        '    <a class="' + iconClass + '" href="#" title="' + projectid + '">' +
        '        <span>' + displayString + '</span>' +
        '    </a>' +
        '    <div class="app-navigation-entry-utils">' +
        '    <ul>';
    if (type === 'l') {
        li +=
            '        <li class="app-navigation-entry-utils-menu-button copyPublicShareButton">' +
            '            <button class="icon-clippy"></button>' +
            '        </li>';
    }
    li +=
        '        <li class="app-navigation-entry-utils-menu-button projectMenuButton">' +
        '            <button></button>' +
        '        </li>' +
        '     </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="accesslevel accesslevelViewer">' +
        '                    <span class="icon-toggle"></span>' +
        '                    <input type="radio" ' + (accesslevel === constants.ACCESS.VIEWER ? 'checked' : '') + '/>' +
        '                    <label>' + t('cospend', 'Viewer') + '</label>' +
        '                </a>' +
        '            </li><li>' +
        '            <li>' +
        '                <a href="#" class="accesslevel accesslevelParticipant" title="' + t('cospend', 'Participant: add/edit/delete bills + viewer permissions') + '">' +
        '                    <span class="icon-rename"></span>' +
        '                    <input type="radio" ' + (accesslevel === constants.ACCESS.PARTICIPANT ? 'checked' : '') + '/>' +
        '                    <label>' + t('cospend', 'Participant') + '</label>' +
        '                </a>' +
        '            </li><li>' +
        '            <li>' +
        '                <a href="#" class="accesslevel accesslevelMaintener" title="' + t('cospend', 'Maintener: add/edit members/categories/currencies + participant permissions') + '">' +
        '                    <span class="icon-category-customization"></span>' +
        '                    <input type="radio" ' + (accesslevel === constants.ACCESS.MAINTENER ? 'checked' : '') + '/>' +
        '                    <label>' + t('cospend', 'Maintener') + '</label>' +
        '                </a>' +
        '            </li><li>' +
        '            <li>' +
        '                <a href="#" class="accesslevel accesslevelAdmin" title="' + t('cospend', 'Admin: edit/delete project + maintener permissions') + '">' +
        '                    <span class="icon-user-admin"></span>' +
        '                    <input type="radio" ' + (accesslevel === constants.ACCESS.ADMIN ? 'checked' : '') + '/>' +
        '                    <label>' + t('cospend', 'Admin') + '</label>' +
        '                </a>' +
        '            </li><li>' +
        '                <a href="#" class="' + deleteButtonClass + '">' +
        '                    <span class="icon-delete"></span>' +
        '                    <span>' + t('cospend', 'Delete') + '</span>' +
        '                </a>' +
        '            </li>' +
        '       </ul>' +
        '   </div>' +
        '</li>';
    $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share').append(li);
    $('.projectitem[projectid="' + projectid + '"] .shareinput').val('');
}

export function deleteGroupShareDb (projectid, shid) {
    $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteGroupShareButton').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        shid: shid
    };
    const url = generateUrl('/apps/cospend/deleteGroupShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function () {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function () {
            li.remove();
        });
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteGroupShareButton').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete group share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function editShareAccessLevelDb (projectid, shid, accesslevel) {
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    $('li[shid="' + shid + '"] .accesslevel span').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        shid: shid,
        accesslevel: accesslevel
    };
    const url = generateUrl('/apps/cospend/editShareAccessLevel');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function () {
        applyShareAccessLevel(projectid, shid, accesslevel);
    }).always(function () {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
        $('li[shid="' + shid + '"] .accesslevel span').removeClass('icon-loading-small');
    }).fail(function (response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit share access level') +
            ': ' + response.responseJSON.message
        );
    });
}

export function applyShareAccessLevel (projectid, shid, accesslevel) {
    const shLine = $('li[shid="' + shid + '"]');
    shLine.find('.accesslevel input[type=radio]').prop('checked', false);
    if (accesslevel === constants.ACCESS.VIEWER) {
        shLine.find('.accesslevelViewer input[type=radio]').prop('checked', true);
    } else if (accesslevel === constants.ACCESS.PARTICIPANT) {
        shLine.find('.accesslevelParticipant input[type=radio]').prop('checked', true);
    } else if (accesslevel === constants.ACCESS.MAINTENER) {
        shLine.find('.accesslevelMaintener input[type=radio]').prop('checked', true);
    } else if (accesslevel === constants.ACCESS.ADMIN) {
        shLine.find('.accesslevelAdmin input[type=radio]').prop('checked', true);
    }
}