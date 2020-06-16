/*jshint esversion: 6 */

/*
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
}

export function addUserAutocompletion(input, projectid) {
    const req = {};
    const url = generateUrl('/apps/cospend/user-list');
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
    });
}
*/