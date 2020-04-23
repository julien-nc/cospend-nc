/*jshint esversion: 6 */

import {generateUrl} from '@nextcloud/router';
import * as constants from './constants';
import {getProjectName} from './project';
import * as Notification from './notification';
import cospend from './state';
import {
    copyToClipboard,
    Timer
} from './utils';

export function shareEvents() {
    $('body').on('focus', '.shareinput', function() {
        $(this).select();
        const projectid = $(this).parent().parent().parent().attr('projectid');
        addUserAutocompletion($(this), projectid);
    });

    $('body').on('click', '.deleteUserShareButton', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const shid = $(this).parent().parent().parent().parent().attr('shid');
        $(this).parent().parent().parent().parent().addClass('deleted');
        cospend.shareDeletionTimer[shid] = new Timer(function () {
            deleteUserShareDb(projectid, shid);
        }, 7000);
    });

    $('body').on('click', '.deleteGroupShareButton', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const shid = $(this).parent().parent().parent().parent().attr('shid');
        $(this).parent().parent().parent().parent().addClass('deleted');
        cospend.shareDeletionTimer[shid] = new Timer(function () {
            deleteGroupShareDb(projectid, shid);
        }, 7000);
    });

    $('body').on('click', '.deleteCircleShareButton', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const shid = $(this).parent().parent().parent().parent().attr('shid');
        $(this).parent().parent().parent().parent().addClass('deleted');
        cospend.shareDeletionTimer[shid] = new Timer(function () {
            deleteCircleShareDb(projectid, shid);
        }, 7000);
    });

    $('body').on('click', '.deletePublicShareButton', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const shid = $(this).parent().parent().parent().parent().attr('shid');
        $(this).parent().parent().parent().parent().addClass('deleted');
        cospend.shareDeletionTimer[shid] = new Timer(function () {
            deletePublicShareDb(projectid, shid);
        }, 7000);
    });

    $('body').on('click', '.undoDeleteShare', function () {
        const shid = $(this).parent().parent().attr('shid');
        $(this).parent().parent().removeClass('deleted');
        cospend.shareDeletionTimer[shid].pause();
        delete cospend.shareDeletionTimer[shid];
    });

    $('body').on('click', '.copyPublicShareButton', function() {
        const token = $(this).parent().parent().parent().attr('token');
        const publicLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/s/' + token);
        copyToClipboard(publicLink);
        Notification.showTemporary(t('cospend', 'Public link copied to clipboard'));
        const button = $(this).find('button').removeClass('icon-clippy').addClass('icon-checkmark-color');
        new Timer(function () {
            button.addClass('icon-clippy').removeClass('icon-checkmark-color');
        }, 5000);
    });

    $('body').on('click', '.addPublicShareButton', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
        addPublicShareDb(projectid);
    });

    $('body').on('click', '.accesslevel', function(e) {
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

    $('body').on('click', '.shareProjectButton', function() {
        const shareDiv = $(this).parent().parent().parent().find('.app-navigation-entry-share');
        if (shareDiv.is(':visible')) {
            shareDiv.slideUp();
            $(this).removeClass('activeButton');
        } else {
            shareDiv.slideDown();
            $(this).addClass('activeButton');
        }
    });
}

export function addUserShareDb(projectid, userid, username) {
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
    }).done(function(response) {
        addShare(projectid, userid, username, response, 'u', constants.ACCESS.PARTICIPANT);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Project {pname} is now shared with {uname}', {
            pname: projectname,
            uname: username
        }));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add user share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function deleteUserShareDb(projectid, shid) {
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
    }).done(function() {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function() {
            li.remove();
        });
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] ' +
            '.deleteUserShareButton span:first').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete user share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addPublicShareDb(projectid) {
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
    }).done(function(response) {
        addShare(projectid, null, t('cospend', 'Public share link'), response.id, 'l', constants.ACCESS.PARTICIPANT, response.token);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Public access link added for project {pname}', {pname: projectname}));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add public share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function deletePublicShareDb(projectid, shid) {
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
    }).done(function() {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function() {
            li.remove();
        });
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] ' +
            '.deletePublicShareButton span:first').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete public share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addCircleShareDb(projectid, circleId, circleName) {
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
    }).done(function(response) {
        addShare(projectid, circleId, circleName, response, 'c', constants.ACCESS.PARTICIPANT);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Project {pname} is now shared with circle {cname}', {
            pname: projectname,
            cname: circleName
        }));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add circle share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function deleteCircleShareDb(projectid, shid) {
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
    }).done(function() {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function() {
            li.remove();
        });
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteCircleShareButton').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete circle share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addGroupShareDb(projectid, groupid, groupname) {
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
    }).done(function(response) {
        addShare(projectid, groupid, groupname, response, 'g', constants.ACCESS.PARTICIPANT);
        const projectname = getProjectName(projectid);
        Notification.showTemporary(t('cospend', 'Project {pname} is now shared with group {gname}', {
            pname: projectname,
            gname: groupname
        }));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add group share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function addShare(projectid, elemId, elemName, id, type, accesslevel, token = null) {
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
    const li = $('<li/>', {class: 'shareitem', shid: id, token: (type === 'l') ? token : null, elemid: elemId, elemname: elemName})
        .append(
            $('<a/>', {class: iconClass, href: '#', title: projectid})
                .append($('<span/>').text(displayString))
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-utils'})
                .append(
                    $('<ul/>')
                        .append(
                            $('<li/>', {class: 'app-navigation-entry-utils-menu-button projectMenuButton'})
                                .append($('<button/>'))
                        )
                )
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-menu'})
                .append(
                    $('<ul/>')
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: 'accesslevel accesslevelViewer'})
                                        .append($('<span/>', {class: 'icon-toggle'}))
                                        .append($('<input/>', {type: 'radio', checked: (accesslevel === constants.ACCESS.VIEWER)}))
                                        .append($('<label/>').text(t('cospend', 'Viewer')))
                                )
                        )
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: 'accesslevel accesslevelParticipant', title: t('cospend', 'Participant: add/edit/delete bills + viewer permissions')})
                                        .append($('<span/>', {class: 'icon-rename'}))
                                        .append($('<input/>', {type: 'radio', checked: (accesslevel === constants.ACCESS.PARTICIPANT)}))
                                        .append($('<label/>').text(t('cospend', 'Participant')))
                                )
                        )
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: 'accesslevel accesslevelMaintener', title: t('cospend', 'Maintener: add/edit members/categories/currencies + participant permissions')})
                                        .append($('<span/>', {class: 'icon-category-customization'}))
                                        .append($('<input/>', {type: 'radio', checked: (accesslevel === constants.ACCESS.MAINTENER)}))
                                        .append($('<label/>').text(t('cospend', 'Maintener')))
                                )
                        )
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: 'accesslevel accesslevelAdmin', title: t('cospend', 'Admin: edit/delete project + maintener permissions')})
                                        .append($('<span/>', {class: 'icon-user-admin'}))
                                        .append($('<input/>', {type: 'radio', checked: (accesslevel === constants.ACCESS.ADMIN)}))
                                        .append($('<label/>').text(t('cospend', 'Admin')))
                                )
                        )
                        .append(
                            $('<li/>')
                                .append(
                                    $('<a/>', {href: '#', class: deleteButtonClass})
                                        .append($('<span/>', {class: 'icon-delete'}))
                                        .append($('<label/>').text(t('cospend', 'Delete')))
                                )
                        )
                )
        )
        .append(
            $('<div/>', {class: 'app-navigation-entry-deleted'})
                .append($('<div/>', {class: 'app-navigation-entry-deleted-description'}).text(t('cospend', 'Shared access deleted')))
                .append($('<button/>', {class: 'app-navigation-entry-deleted-button icon-history undoDeleteShare', title: t('cospend', 'Undo')}))
        )

    if (type === 'l') {
        li.find('div.app-navigation-entry-utils > ul')
            .prepend(
                $('<li/>', {class: 'app-navigation-entry-utils-menu-button copyPublicShareButton'})
                    .append($('<button/>', {class: 'icon-clippy'}))
            )
    }

    $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share').append(li);
    $('.projectitem[projectid="' + projectid + '"] .shareinput').val('');
}

export function deleteGroupShareDb(projectid, shid) {
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
    }).done(function() {
        const li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
        li.fadeOut('normal', function() {
            li.remove();
        });
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteGroupShareButton').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete group share') +
            ': ' + response.responseJSON.message
        );
    });
}

export function editShareAccessLevelDb(projectid, shid, accesslevel) {
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
    }).done(function() {
        applyShareAccessLevel(projectid, shid, accesslevel);
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
        $('li[shid="' + shid + '"] .accesslevel span').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit share access level') +
            ': ' + response.responseJSON.message
        );
    });
}

export function applyShareAccessLevel(projectid, shid, accesslevel) {
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

export function addUserAutocompletion(input, projectid) {
    const req = {};
    const url = generateUrl('/apps/cospend/getUserList');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        cospend.userIdName = response.users;
        cospend.groupIdName = response.groups;
        cospend.circleIdName = response.circles;
        const data = [];
        let d, name, id;
        for (id in response.users) {
            name = response.users[id];
            d = {
                id: id,
                name: name,
                type: 'u',
                projectid: projectid
            };
            if (id !== name) {
                d.label = name + ' (' + id + ')';
                d.value = name + ' (' + id + ')';
            } else {
                d.label = name;
                d.value = name;
            }
            data.push(d);
        }
        for (id in response.groups) {
            name = response.groups[id];
            d = {
                id: id,
                name: name,
                type: 'g',
                projectid: projectid
            };
            if (id !== name) {
                d.label = name + ' (' + id + ')';
                d.value = name + ' (' + id + ')';
            } else {
                d.label = name;
                d.value = name;
            }
            data.push(d);
        }
        for (id in response.circles) {
            name = response.circles[id];
            d = {
                id: id,
                name: name,
                type: 'c',
                projectid: projectid
            };
            d.label = name;
            d.value = name;
            data.push(d);
        }
        cospend.pubLinkData.projectid = projectid;
        input.autocomplete({
            source: data,
            select: function(e, ui) {
                const it = ui.item;
                if (it.type === 'g') {
                    addGroupShareDb(it.projectid, it.id, it.name);
                } else if (it.type === 'u') {
                    addUserShareDb(it.projectid, it.id, it.name);
                } else if (it.type === 'c') {
                    addCircleShareDb(it.projectid, it.id, it.name);
                } else if (it.type === 'l') {
                    addPublicShareDb(it.projectid);
                }
            }
        }).data('ui-autocomplete')._renderItem = function(ul, item) {
            let button = null;
            let img = null;
            if (item.type === 'u') {
                const imgsrc = generateUrl('/avatar/' + encodeURIComponent(item.id) + '/64?v=2');
                img = $('<img/>', {src: imgsrc, class: 'autocomplete-avatar-img'});
            } else {
                let iconClass = '';
                if (item.type === 'g') {
                    iconClass = 'icon-group';
                } else if (item.type === 'c') {
                    iconClass = 'share-icon-circle';
                } else if (item.type === 'l') {
                    iconClass = 'icon-public';
                }
                button = $('<button/>', {class: 'shareCompleteIcon ' + iconClass});
            }
            return $('<li/>')
                .data('item.autocomplete', item)
                .append(
                    $('<a/>', {class: 'shareCompleteLink'})
                        .append(button)
                        .append(img)
                        .append(' ' + item.label)
                )
                .appendTo(ul);
        };
        //console.log(ii.data('ui-autocomplete'));
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get user list'));
    });
}