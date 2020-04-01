/*jshint esversion: 6 */

import * as Notification from './notification';
import {generateUrl} from '@nextcloud/router';
import {rgbObjToHex} from './utils';
import {updateProjectBalances} from './project';
import {getBills} from './bill';
import * as constants from './constants';
import cospend from './state';
import * as Chart from 'chart.js';

export function getMemberName(projectid, memberid) {
    //const memberName = $('.projectitem[projectid="'+projectid+'"] .memberlist > li[memberid='+memberid+'] b.memberName').text();
    return cospend.members[projectid][memberid].name;
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

export function editMember(projectid, memberid, newName, newWeight, newActivated, color = null) {
    $('.projectitem[projectid="' + projectid + '"] ul.memberlist > li[memberid=' + memberid + ']')
        .addClass('icon-loading-small')
        .removeClass('editing');
    const req = {
        name: newName,
        weight: newWeight,
        activated: newActivated
    };
    if (color) {
        req.color = color;
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
        // update icon
        const imgurl = generateUrl('/apps/cospend/getAvatar?color=' +
            cospend.members[projectid][memberid].color +
            '&name=' + encodeURIComponent(response.name));
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
        activated: member.activated,
        weight: member.weight,
        color: rgbObjToHex(member.color).replace('#', '')
    };

    let invisibleClass = '';
    let balanceStr;
    if (balance >= 0.01) {
        balanceStr = '<b class="balance balancePositive">+' + balance.toFixed(2) + '</b>';
    } else if (balance <= -0.01) {
        balanceStr = '<b class="balance balanceNegative">' + balance.toFixed(2) + '</b>';
    } else {
        balanceStr = '<b class="balance">0.00</b>';
        if (!member.activated) {
            invisibleClass = ' invisibleMember';
        }
    }
    let iconToggleStr, toggleStr, imgurl;
    let lockSpan = '';
    if (member.activated) {
        iconToggleStr = 'icon-delete';
        toggleStr = t('cospend', 'Deactivate');
    } else {
        lockSpan = '<div class="member-list-disabled-icon icon-disabled-user"> </div>';
        iconToggleStr = 'icon-history';
        toggleStr = t('cospend', 'Reactivate');
    }
    const color = cospend.members[projectid][member.id].color;
    imgurl = generateUrl('/apps/cospend/getAvatar?color=' + color + '&name=' + encodeURIComponent(member.name));


    const renameStr = t('cospend', 'Rename');
    const changeWeightStr = t('cospend', 'Change weight');
    const changeColorStr = t('cospend', 'Change color');
    const li =
        '<li memberid="' + member.id + '" class="memberitem' + invisibleClass + '">' +
        '    <div class="memberAvatar' + (member.activated ? '' : ' memberAvatarDisabled') + '">' +
        '       <div class="disabledMask"></div>' +
        '       <img src="' + imgurl + '"/>' +
        '    </div>' +
        '    <a class="member-list-icon" href="#">' +
        '        <span class="memberNameBalance">' +
        '            <b class="memberName" title="' + member.name + ' (x' + member.weight + ')">' +
        member.name + ((parseFloat(member.weight) !== 1.0) ? (' (x' + member.weight + ')') : '') +
        '</b>' +
        balanceStr +
        '        </span>' +
        '    </a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <!--li class="app-navigation-entry-utils-counter">1</li-->' +
        '            <li class="app-navigation-entry-utils-menu-button memberMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="renameMember">' +
        '                    <span class="icon-rename"></span>' +
        '                    <span>' + renameStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="editWeightMember">' +
        '                    <span class="icon-quota"></span>' +
        '                    <span>' + changeWeightStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="editColorMember">' +
        '                    <span class="icon-palette"></span>' +
        '                    <span>' + changeColorStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="toggleMember">' +
        '                    <span class="' + iconToggleStr + '"></span>' +
        '                    <span>' + toggleStr + '</span>' +
        '                </a>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-edit">' +
        '        <div>' +
        '            <input type="text" value="' + member.name + '" class="editMemberInput">' +
        '            <input type="submit" value="" class="icon-close editMemberClose">' +
        '            <input type="submit" value="" class="icon-checkmark editMemberOk">' +
        '        </div>' +
        '    </div>' +
        '</li>';

    $(li).appendTo('#projectlist li.projectitem[projectid="' + projectid + '"] .memberlist');

    if (cospend.projects[projectid].myaccesslevel < constants.ACCESS.MAINTENER) {
        $('li.projectitem[projectid=' + projectid + '] .renameMember').hide();
        $('li.projectitem[projectid=' + projectid + '] .editWeightMember').hide();
        $('li.projectitem[projectid=' + projectid + '] .editColorMember').hide();
        $('li.projectitem[projectid=' + projectid + '] .toggleMember').hide();
    }
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
        if (cospend.categories.hasOwnProperty(catId)) {
            catName = cospend.categories[catId].icon + ' ' + cospend.categories[catId].name;
            color = cospend.categories[catId].color;
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
    if (cospend.categories.hasOwnProperty(selectedCatId)) {
        catName = cospend.categories[selectedCatId].icon + ' ' + cospend.categories[selectedCatId].name;
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
