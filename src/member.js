/*jshint esversion: 6 */

import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import {rgbObjToHex} from './utils';
import {updateProjectBalances} from './project';
import {getBills} from './bill';
import * as constants from './constants';
import cospend from './state';

export function memberEvents() {
    $('body').on('focus', '.newmembername', function () {
        $(this).select();
        const projectid = $(this).parent().parent().attr('projectid');
        addMemberAutocompletion($(this), projectid);
    });
    $('body').on('focus', '.editMemberInput', function () {
        $(this).select();
        const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
        const memberid = $(this).parent().parent().parent().attr('memberid');
        addMemberAutocompletion($(this), projectid, memberid);
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
            if (cospend.autoCompletePrevent) {
                cospend.autoCompletePrevent = false;
            } else {
                const name = $(this).val();
                const projectid = $(this).parent().parent().attr('projectid');
                if (projectid && name) {
                    createMember(projectid, name);
                } else {
                    Notification.showTemporary(t('cospend', 'Invalid values'));
                }
            }
        } else if (e.key === 'Escape') {
            $('.newmemberdiv').slideUp();
        }
    });

    $('body').on('click', '.renameMember', function () {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const mid = $(this).parent().parent().parent().parent().attr('memberid');
        const name = cospend.members[projectid][mid].name;
        $(this).parent().parent().parent().parent().find('.editMemberInput').val(name).focus().select();
        $('.memberlist li').removeClass('editing');
        $(this).parent().parent().parent().parent().addClass('editing');
        cospend.memberEditionMode = constants.MEMBER_NAME_EDITION;
    });

    $('body').on('click', '.editWeightMember', function () {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const mid = $(this).parent().parent().parent().parent().attr('memberid');
        const weight = cospend.members[projectid][mid].weight;
        $(this).parent().parent().parent().parent().find('.editMemberInput').val(weight).focus().select();
        $('.memberlist li').removeClass('editing');
        $(this).parent().parent().parent().parent().addClass('editing');
        cospend.memberEditionMode = constants.MEMBER_WEIGHT_EDITION;
    });

    $('body').on('click', '.editMemberClose', function () {
        $(this).parent().parent().parent().removeClass('editing');
    });

    $('body').on('keyup', '.editMemberInput', function (e) {
        if (e.key === 'Enter') {
            const memberid = $(this).parent().parent().parent().attr('memberid');
            const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            let newName;
            if (cospend.memberEditionMode === constants.MEMBER_NAME_EDITION) {
                if (cospend.autoCompletePrevent) {
                    cospend.autoCompletePrevent = false;
                } else {
                    newName = $(this).val();
                    editMember(projectid, memberid, newName, null, null, null, '');
                }
            } else if (cospend.memberEditionMode === constants.MEMBER_WEIGHT_EDITION) {
                const newWeight = parseFloat($(this).val());
                if (!isNaN(newWeight)) {
                    newName = cospend.members[projectid][memberid].name;
                    editMember(projectid, memberid, newName, newWeight, null);
                } else {
                    Notification.showTemporary(t('cospend', 'Invalid weight'));
                }
            }
        } else if (e.key === 'Escape') {
            $(this).parent().parent().parent().removeClass('editing');
        }
    });

    $('body').on('click', '.editMemberOk', function () {
        const memberid = $(this).parent().parent().parent().attr('memberid');
        const projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
        let newName;
        if (cospend.memberEditionMode === constants.MEMBER_NAME_EDITION) {
            newName = $(this).parent().find('.editMemberInput').val();
            editMember(projectid, memberid, newName, null, null, null, '');
        } else if (cospend.memberEditionMode === constants.MEMBER_WEIGHT_EDITION) {
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

    $('body').on('click', '.memberAvatar', function() {
        const projectid = $(this).parent().parent().parent().attr('projectid');
        const memberid = $(this).parent().attr('memberid');
        askChangeMemberColor(projectid, memberid);
    });

    $('body').on('click', '.editColorMember', function() {
        const projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
        const memberid = $(this).parent().parent().parent().parent().attr('memberid');
        askChangeMemberColor(projectid, memberid);
    });

    $('body').on('change', '#membercolorinput', function() {
        okMemberColor();
    });
}

export function getMemberName(projectid, memberid) {
    //const memberName = $('.projectitem[projectid="'+projectid+'"] .memberlist > li[memberid='+memberid+'] b.memberName').text();
    return cospend.members[projectid][memberid].name;
}

export function getMemberAvatar(projectid, memberid) {
    var member = cospend.members[projectid][memberid];
    if (member.userid && !cospend.pageIsPublic) {
        return generateUrl('/avatar/' + encodeURIComponent(member.userid) + '/64?v=2');
    } else {
        return generateUrl('/apps/cospend/getAvatar?color=' + member.color + '&name=' + encodeURIComponent(member.name));
    }
}

export function createMember(projectid, name) {
    if (!name || name.match(',')) {
        Notification.showTemporary(t('cospend', 'Invalid values'));
        return;
    }
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        name: name
    };
    let url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/addMember');
    } else {
        url = generateUrl('/apps/cospend/apiv2/projects/' + cospend.projectid + '/' + cospend.password + '/members');
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        // add member to UI
        addMember(projectid, response, 0);
        // fold new member form
        $('.newmemberdiv').slideUp();
        updateNumberOfMember(projectid);
        $('#billdetail').html('');
        Notification.showTemporary(t('cospend', 'Created member {name}', {name: name}));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add member') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function createMemberFromUser(projectid, userid, name) {
    if (!name|| name.match(',')) {
        Notification.showTemporary(t('cospend', 'Invalid values'));
        return;
    }
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        projectid: projectid,
        userid: userid,
        name: name
    };
    let url = generateUrl('/apps/cospend/addMember');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        // add member to UI
        addMember(projectid, response, 0);
        // fold new member form
        $('.newmemberdiv').slideUp();
        updateNumberOfMember(projectid);
        $('#billdetail').html('');
        Notification.showTemporary(t('cospend', 'Created member {name}', {name: name}));
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"]').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add member') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function askChangeMemberColor(projectid, memberid) {
    cospend.changingColorProjectId = projectid;
    cospend.changingColorMemberId = memberid;
    const currentColor = '#' + cospend.members[projectid][memberid].color;
    $('#membercolorinput').val(currentColor);
    $('#membercolorinput').click();
}

export function okMemberColor() {
    const color = $('#membercolorinput').val();
    const projectid = cospend.changingColorProjectId;
    const memberid = cospend.changingColorMemberId;
    editMember(
        projectid, memberid,
        cospend.members[projectid][memberid].name,
        cospend.members[projectid][memberid].weight,
        cospend.members[projectid][memberid].activated,
        color.replace('#', '')
    );
}

export function editMember(projectid, memberid, newName, newWeight=null, newActivated=null, color=null, userid=null) {
    $('.projectitem[projectid="' + projectid + '"] ul.memberlist > li[memberid=' + memberid + ']')
        .addClass('icon-loading-small')
        .removeClass('editing');
    const req = {
        name: newName,
        weight: newWeight,
        activated: newActivated,
    };
    if (color) {
        req.color = color;
    }
    if (userid !== null) {
        req.userid = userid;
    }
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.memberid = memberid;
        url = generateUrl('/apps/cospend/editMember');
        type = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/members/' + memberid);
        type = 'PUT';
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        const memberLine = $('.projectitem[projectid="' + projectid + '"] ul.memberlist > li[memberid=' + memberid + ']');
        // update member values
        cospend.members[projectid][memberid].color = rgbObjToHex(response.color).replace('#', '');
        if (newWeight) {
            cospend.members[projectid][memberid].weight = newWeight;
            updateProjectBalances(projectid);
        }
        if (newName) {
            const weight = parseFloat(cospend.members[projectid][memberid].weight);
            memberLine.find('b.memberName').text(
                newName + ((weight !== 1.0) ? (' (x' + cospend.members[projectid][memberid].weight + ')') : '')
            );
            cospend.members[projectid][memberid].name = newName;
        }
        // update title
        memberLine.find('b.memberName').attr('title', newName + ' (x' + cospend.members[projectid][memberid].weight + ')');
        if (newActivated !== null && newActivated === false) {
            memberLine.find('.toggleMember span').first().removeClass('icon-delete').addClass('icon-history');
            memberLine.find('.toggleMember span').eq(1).text(t('cospend', 'Reactivate'));
            cospend.members[projectid][memberid].activated = newActivated;
        } else if (newActivated !== null && newActivated === true) {
            memberLine.find('.toggleMember span').first().removeClass('icon-history').addClass('icon-delete');
            memberLine.find('.toggleMember span').eq(1).text(t('cospend', 'Deactivate'));
            cospend.members[projectid][memberid].activated = newActivated;
        }
        if (userid !== null) {
            cospend.members[projectid][memberid].userid = userid;
        }
        // update icon
        const imgurl = getMemberAvatar(projectid, memberid);
        if (cospend.members[projectid][memberid].activated) {
            memberLine.find('.memberAvatar').removeClass('memberAvatarDisabled');
        } else {
            memberLine.find('.memberAvatar').addClass('memberAvatarDisabled');
        }
        memberLine.find('.memberAvatar img').attr('src', imgurl);

        Notification.showTemporary(t('cospend', 'Member saved'));
        // get bills again to refresh names
        getBills(projectid);
        // reset bill edition
        $('#billdetail').html('');
    }).always(function() {
        $('.projectitem[projectid="' + projectid + '"] ul.memberlist > li[memberid=' + memberid + ']').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to save member') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function updateNumberOfMember(projectid) {
    const nbMembers = $('li.projectitem[projectid="' + projectid + '"] ul.memberlist > li').length;
    $('li.projectitem[projectid="' + projectid + '"] .app-navigation-entry-utils-counter span').text(nbMembers);
}

export function addMember(projectid, member, balance) {
    // add member to dict
    cospend.members[projectid][member.id] = {
        id: member.id,
        name: member.name,
        userid: member.userid,
        activated: member.activated,
        weight: member.weight,
        color: rgbObjToHex(member.color).replace('#', '')
    };

    let invisibleClass = '';
    let balanceClass = '';
    if (balance >= 0.01) {
        balanceClass = ' balancePositive';
    } else if (balance <= -0.01) {
        balanceClass = ' balanceNegative';
    } else {
        if (!member.activated) {
            invisibleClass = ' invisibleMember';
        }
    }
    const color = cospend.members[projectid][member.id].color;
    let imgurl = getMemberAvatar(projectid, member.id);

    const renameStr = t('cospend', 'Rename');
    const changeWeightStr = t('cospend', 'Change weight');
    const changeColorStr = t('cospend', 'Change color');

    const container = $('#projectlist li.projectitem[projectid="' + projectid + '"] .memberlist');
    container.append(
        $('<li/>', {memberid: member.id, class: 'memberitem' + invisibleClass})
            .append(
                $('<div/>', {class: 'memberAvatar' + (member.activated ? '' : ' memberAvatarDisabled')})
                    .append($('<div/>', {class: 'disabledMask'}))
                    .append($('<img/>', {src: imgurl}))
            )
            .append(
                $('<a/>', {class: 'member-list-icon', href: '#'})
                    .append(
                        $('<span/>', {class: 'memberNameBalance'})
                            .append($('<b/>', {class: 'memberName', title: member.name + ' (x' + member.weight + ')'})
                                .text(member.name + ((parseFloat(member.weight) !== 1.0) ? (' (x' + member.weight + ')') : ''))
                            )
                            .append($('<b/>', {class: 'balance' + balanceClass}).text(balance.toFixed(2)))
                    )
            )
            .append(
                $('<div/>', {class: 'app-navigation-entry-utils'})
                    .append(
                        $('<ul/>')
                            .append(
                                $('<li/>', {class: 'app-navigation-entry-utils-menu-button memberMenuButton'})
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
                                        $('<a/>', {href: '#', class: 'renameMember'})
                                            .append($('<span/>', {class: 'icon-rename'}))
                                            .append($('<span/>').text(renameStr))
                                    )
                            )
                            .append(
                                $('<li/>')
                                    .append(
                                        $('<a/>', {href: '#', class: 'editWeightMember'})
                                            .append($('<span/>', {class: 'icon-quota'}))
                                            .append($('<span/>').text(changeWeightStr))
                                    )
                            )
                            .append(
                                $('<li/>')
                                    .append(
                                        $('<a/>', {href: '#', class: 'editColorMember'})
                                            .append($('<span/>', {class: 'icon-palette'}))
                                            .append($('<span/>').text(changeColorStr))
                                    )
                            )
                            .append(
                                $('<li/>')
                                    .append(
                                        $('<a/>', {href: '#', class: 'toggleMember'})
                                            .append($('<span/>', {class: member.activated ? 'icon-delete' : 'icon-history'}))
                                            .append($('<span/>').text(member.activated ? t('cospend', 'Deactivate') : t('cospend', 'Reactivate')))
                                    )
                            )
                    )
            )
            .append(
                $('<div/>', {class: 'app-navigation-entry-edit'})
                    .append(
                        $('<div/>')
                            .append($('<input/>', {type: 'text', value: member.name, class: 'editMemberInput'}))
                            .append($('<input/>', {type: 'submit', value: '', class: 'icon-close editMemberClose'}))
                            .append($('<input/>', {type: 'submit', value: '', class: 'icon-checkmark editMemberOk'}))
                    )
            )
    );

    if (cospend.projects[projectid].myaccesslevel < constants.ACCESS.MAINTENER) {
        $('li.projectitem[projectid="' + projectid + '"] .renameMember').hide();
        $('li.projectitem[projectid="' + projectid + '"] .editWeightMember').hide();
        $('li.projectitem[projectid="' + projectid + '"] .editColorMember').hide();
        $('li.projectitem[projectid="' + projectid + '"] .toggleMember').hide();
    }
}

export function addMemberAutocompletion(input, projectid, memberid) {
    const req = {projectid: projectid};
    const url = generateUrl('/apps/cospend/getMemberSuggestions');
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
        if (memberid) {
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
        }
        cospend.pubLinkData.projectid = null;

        input.autocomplete({
            source: data,
            select: function(event, ui) {
                const it = ui.item;
                if (event.key === 'Enter') {
                    cospend.autoCompletePrevent = true;
                }
                if (it.type === 'g') {
                    createMembersFromGroup(it.projectid, it.id, it.name);
                } else if (it.type === 'u') {
                    if (memberid) {
                        editMember(it.projectid, memberid, it.name, null, true, false, it.id);
                    } else {
                        createMemberFromUser(it.projectid, it.id, it.name);
                    }
                } else if (it.type === 'c') {
                    createMembersFromCircle(it.projectid, it.id, it.name);
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
                }
                button = $('<button/>', {class: 'shareCompleteIcon ' + iconClass, style: style});
            }
            return $('<li></li>')
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