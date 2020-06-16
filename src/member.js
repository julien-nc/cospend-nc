/*jshint esversion: 6 */

import * as Notification from './notification';
import {getCurrentUser} from '@nextcloud/auth';
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

}

export function getMemberName(projectid, memberid) {
    return cospend.members[projectid][memberid].name;
}

export function getSmartMemberName(projectid, memberid) {
    return (!cospend.pageIsPublic && cospend.members[projectid][memberid].userid === getCurrentUser().uid) ?
        t('cospend', 'You') : getMemberName(projectid, memberid);
}

export function getMemberAvatar(projectid, memberid) {
    var member = cospend.members[projectid][memberid];
    if (member.userid && !cospend.pageIsPublic) {
        return generateUrl('/avatar/' + encodeURIComponent(member.userid) + '/64?v=2');
    } else {
        return generateUrl('/apps/cospend/getAvatar?color=' + member.color + '&name=' + encodeURIComponent(member.name));
    }
}


/*export function createMemberFromUser(projectid, userid, name) {
    if (!name|| name.match(',')) {
        Notification.showTemporary(t('cospend', 'Invalid values'));
        return;
    }
    $('.projectitem[projectid="' + projectid + '"]').addClass('icon-loading-small');
    const req = {
        userid: userid,
        name: name
    };
    const url = generateUrl('/apps/cospend/projects/' + projectid + '/members');
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
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/members/' + memberid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/members/' + memberid);
    }
    $.ajax({
        type: 'PUT',
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

export function addMemberAutocompletion(input, projectid, memberid) {
    const req = {};
    const url = generateUrl('/apps/cospend/projects/' + projectid + '/member-suggestions');
    $.ajax({
        type: 'GET',
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
}*/