/*jshint esversion: 6 */
/**
 * Nextcloud - Spend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */
(function ($, OC) {
    'use strict';

    //////////////// VAR DEFINITION /////////////////////
    var MEMBER_NAME_EDITION = 1;
    var MEMBER_WEIGHT_EDITION = 2;

    var PROJECT_NAME_EDITION = 1;
    var PROJECT_PASSWORD_EDITION = 2;

    var spend = {
        restoredSelectedProjectId: null,
        memberEditionMode: null,
        projectEditionMode: null,
        projectDeletionTimer: {},
        billDeletionTimer: {},
        // indexed by projectid, then by billid
        bills: {},
        // indexed by projectid, then by memberid
        members: {},
        projects: {},
        currentProjectId: null
    };

    //////////////// UTILS /////////////////////

    function getLetterColor(letter1, letter2) {
        var letter1Index = letter1.toLowerCase().charCodeAt(0);
        var letter2Index = letter2.toLowerCase().charCodeAt(0);
        var letterCoef = (letter1Index * letter2Index) % 100 / 100;
        var h = letterCoef * 360;
        var s = 45 + letterCoef * 10;
        var l = 50 + letterCoef * 10;
        return {h: Math.round(h), s: Math.round(s), l: Math.round(l)};
    }

    function Timer(callback, delay) {
        var timerId, start, remaining = delay;

        this.pause = function() {
            window.clearTimeout(timerId);
            remaining -= new Date() - start;
        };

        this.resume = function() {
            start = new Date();
            window.clearTimeout(timerId);
            timerId = window.setTimeout(callback, remaining);
        };

        this.resume();
    }

    function pad(n) {
        return (n < 10) ? ('0' + n) : n;
    }

    function endsWith(str, suffix) {
        return str.indexOf(suffix, str.length - suffix.length) !== -1;
    }

    function basename(str) {
        var base = String(str).substring(str.lastIndexOf('/') + 1);
        if (base.lastIndexOf(".") !== -1) {
            base = base.substring(0, base.lastIndexOf("."));
        }
        return base;
    }

    /*
     * get key events
     */
    function checkKey(e) {
        e = e || window.event;
        var kc = e.keyCode;
        //console.log(kc);

        // key '<'
        if (kc === 60 || kc === 220) {
            e.preventDefault();
        }

        if (e.key === 'Escape') {
        }
    }

    function createProject(id, name, password) {
        var req = {
            id: id,
            name: name,
            password: password
        };
        var url = OC.generateUrl('/apps/spend/createProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            addProject({
                id: id,
                name: name,
                contact_email: '',
                members: [],
                active_members: [],
                balance: {}
            });

            var div = $('#newprojectdiv');
            div.slideUp();
            $('#newprojectbutton').removeClass('icon-triangle-s').addClass('icon-triangle-e');
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to create project') + ' ' + response.responseText);
        });
    }

    function createMember(projectid, name) {
        var req = {
            projectid: projectid,
            name: name
        };
        var url = OC.generateUrl('/apps/spend/addMember');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var member = {
                id: response,
                name: name,
                weight: 1,
                activated: true
            };
            // add member to UI
            addMember(projectid, member, 0);
            // fold new member form
            $('#newmemberdiv').slideUp();
            updateNumberOfMember(projectid);
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to add member') + ' ' + response.responseText);
        });
    }

    function editMember(projectid, memberid, newName, newWeight, newActivated) {
        var req = {
            projectid: projectid,
            memberid: memberid,
            name: newName,
            weight: newWeight,
            activated: newActivated
        };
        var url = OC.generateUrl('/apps/spend/editMember');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var memberLine = $('.projectitem[projectid='+projectid+'] ul.memberlist > li[memberid='+memberid+']');
            // update member values
            if (newName) {
                memberLine.find('b.memberName').text(newName);
                spend.members[projectid][memberid].name = newName;
            }
            if (newWeight) {
                memberLine.find('b.memberWeight').text(newWeight);
                spend.members[projectid][memberid].weight = newWeight;
                updateProjectBalances(projectid);
            }
            if (newActivated !== null && newActivated === false) {
                memberLine.find('>a').removeClass('icon-user').addClass('icon-disabled-user');
                memberLine.find('.toggleMember span').first().removeClass('icon-delete').addClass('icon-history');
                memberLine.find('.toggleMember span').eq(1).text(t('spend', 'Reactivate'));
                spend.members[projectid][memberid].activated = newActivated;
            }
            else if (newActivated !== null && newActivated === true) {
                memberLine.find('>a').removeClass('icon-disabled-user').addClass('icon-user');
                memberLine.find('.toggleMember span').first().removeClass('icon-history').addClass('icon-delete');
                memberLine.find('.toggleMember span').eq(1).text(t('spend', 'Remove'));
                spend.members[projectid][memberid].activated = newActivated;
            }
            // remove editing mode
            memberLine.removeClass('editing');
            OC.Notification.showTemporary(t('spend', 'Edited member'));
            // get bills again to refresh names
            getBills(projectid);
            // reset bill edition
            $('#billdetail').html('');
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to edit member') + ' ' + response.responseText);
        });
    }

    function createBill(projectid, what, amount, payer_id, date, owerIds) {
        var req = {
            projectid: projectid,
            what: what,
            date: date,
            payer: payer_id,
            payed_for: owerIds.join(','),
            amount: amount
        };
        var url = OC.generateUrl('/apps/spend/addBill');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var billid = response;
            // update dict
            spend.bills[projectid][billid] = {
                id: billid,
                what: what,
                date: date,
                amount: amount,
                payer_id: payer_id
            };
            var billOwers = [];
            for (var i=0; i < owerIds.length; i++) {
                billOwers.push({id: owerIds[i]});
            }
            spend.bills[projectid][billid].owers = billOwers;

            // update ui
            var bill = spend.bills[projectid][billid];
            updateBillItem(projectid, 0, bill);
            updateDisplayedBill(projectid, billid, what, payer_id);

            updateProjectBalances(projectid);

            OC.Notification.showTemporary(t('spend', 'Bill created'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to create bill') + ' ' + response.responseText);
        });
    }

    function saveBill(projectid, billid, what, amount, payer_id, date, owerIds) {
        var req = {
            projectid: projectid,
            billid: billid,
            what: what,
            date: date,
            payer: payer_id,
            payed_for: owerIds.join(','),
            amount: amount
        };
        var url = OC.generateUrl('/apps/spend/editBill');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // update dict
            spend.bills[projectid][billid].what = what;
            spend.bills[projectid][billid].date = date;
            spend.bills[projectid][billid].amount = amount;
            spend.bills[projectid][billid].payer_id = payer_id;
            var billOwers = [];
            for (var i=0; i < owerIds.length; i++) {
                billOwers.push({id: owerIds[i]});
            }
            spend.bills[projectid][billid].owers = billOwers;

            // update ui
            var bill = spend.bills[projectid][billid];
            updateBillItem(projectid, billid, bill);
            updateDisplayedBill(projectid, billid, what, payer_id);

            updateProjectBalances(projectid);

            OC.Notification.showTemporary(t('spend', 'Bill saved'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to edit project') + ' ' + response.responseText);
        });
    }

    function updateBillItem(projectid, billid, bill) {
        var billItem = $('.billitem[billid='+billid+']');

        var owerNames = '';
        var ower;
        for (var i=0; i < bill.owers.length; i++) {
            ower = bill.owers[i];
            owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
        }
        owerNames = owerNames.replace(/, $/, '');
        var memberName = getMemberName(projectid, bill.payer_id);
        var memberFirstLetter = memberName[0];

        var title = bill.what + '\n' + bill.amount.toFixed(2) + '\n' +
            bill.date + '\n' + memberName + ' -> ' + owerNames;
        var c = getMemberColor(memberName);
        var item = `<a href="#" class="app-content-list-item billitem" billid="${bill.id}" projectid="${projectid}" title="${title}">
            <div class="app-content-list-item-icon" style="background-color: hsl(${c.h}, ${c.s}%, ${c.l}%);">${memberFirstLetter}</div>
            <div class="app-content-list-item-line-one">${bill.what}</div>
            <div class="app-content-list-item-line-two">${bill.amount.toFixed(2)} (${memberName} -> ${owerNames})</div>
            <span class="app-content-list-item-details">${bill.date}</span>
            <div class="icon-delete deleteBillIcon"></div>
            <div class="icon-history undoDeleteBill" title="Undo"></div>
        </a>`;
        billItem.replaceWith(item);
    }

    function editProject(projectid, newName, newEmail, newPassword) {
        var req = {
            projectid: projectid,
            name: newName,
            contact_email: newEmail,
            password: newPassword
        };
        var url = OC.generateUrl('/apps/spend/editProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var projectLine = $('.projectitem[projectid='+projectid+']');
            // update project values
            if (newName) {
                projectLine.find('>a span').text(newName);
                spend.projects[projectid].name = newName;
            }
            // remove editing mode
            projectLine.removeClass('editing');
            // reset bill edition
            $('#billdetail').html('');
            OC.Notification.showTemporary(t('spend', 'Edited project'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to edit project') + ' ' + response.responseText);
        });
    }

    function updateNumberOfMember(projectid) {
        var nbMembers = $('li.projectitem[projectid='+projectid+'] ul.memberlist > li').length;
        $('li.projectitem[projectid='+projectid+'] .app-navigation-entry-utils-counter').text(nbMembers);
    }

    function deleteProject(id) {
        var req = {
            projectid: id
        };
        var url = OC.generateUrl('/apps/spend/deleteProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            $('.projectitem[projectid='+id+']').fadeOut('slow', function() {
                $(this).remove();
            });
            OC.Notification.showTemporary(t('spend', 'Deleted project {id}', {id: id}));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to delete project') + ' ' + response.responseText);
        });
    }

    function deleteBill(projectid, billid) {
        var req = {
            projectid: projectid,
            billid: billid
        };
        var url = OC.generateUrl('/apps/spend/deleteBill');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if ($('#billdetail .bill-title').length > 0 && $('#billdetail .bill-title').attr('billid') === billid) {
                $('#billdetail').html('');
            }
            $('.billitem[billid='+billid+']').fadeOut('slow', function() {
                $(this).remove();
            });
            delete spend.bills[projectid][billid];
            updateProjectBalances(projectid);
            OC.Notification.showTemporary(t('spend', 'Deleted bill'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to delete project') + ' ' + response.responseText);
        });
    }

    function getProjects() {
        var req = {
        };
        var url;
        var type;
        if (!spend.pageIsPublic) {
            url = OC.generateUrl('/apps/spend/getProjects');
            type = 'POST';
        }
        else {
            url = OC.generateUrl(`/apps/spend/api/projects/${spend.projectid}/${spend.password}`);
            type = 'GET';
        }
        spend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total * 100;
                        //$('#loadingpc').text(parseInt(percentComplete) + '%');
                    }
                }, false);

                return xhr;
            }
        }).done(function (response) {
            if (!spend.pageIsPublic) {
                for (var i = 0; i < response.length; i++) {
                    addProject(response[i]);
                }
            }
            else {
                addProject(response);
            }
        }).always(function() {
            spend.currentGetProjectsAjax = null;
        }).fail(function() {
            OC.Notification.showTemporary(t('spend', 'Failed to contact server to get projects'));
        });
    }

    function getProjectStatistics(projectid) {
        var req = {
            projectid: projectid
        };
        var url = OC.generateUrl('/apps/spend/getStatistics');
        spend.currentGetProjectsAjax = $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            displayStatistics(projectid, response);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('spend', 'Failed to get bills'));
        });
    }

    function displayStatistics(projectid, statList) {
        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        var statsStr = `
            <h2 id="statsTitle">${t('spend', 'Statistics of project {name}', {name: projectName})}</h2>
            <table id="statsTable"><thead>
                <th>${t('spend', 'Member name')}</th>
                <th>${t('spend', 'Paid')}</th>
                <th>${t('spend', 'Spent')}</th>
                <th>${t('spend', 'Balance')}</th>
            </thead>
        `;
        var paid, spent, balance, name, balanceClass;
        for (var i=0; i < statList.length; i++) {
            balanceClass = '';
            if (statList[i].balance > 0) {
                balanceClass = ' class="balancePositive"';
            }
            else if (statList[i].balance < 0) {
                balanceClass = ' class="balanceNegative"';
            }
            paid = statList[i].paid.toFixed(2);
            spent = statList[i].spent.toFixed(2);
            balance = statList[i].balance.toFixed(2);
            name = statList[i].member.name;
            statsStr = statsStr + `
                <tr>
                    <td>${name}</td>
                    <td>${paid}</td>
                    <td>${spent}</td>
                    <td${balanceClass}>${balance}</td>
                </tr>
            `;
        }
        statsStr = statsStr + `
            </table>
        `;
        $('#billdetail').html(statsStr);
    }

    function getBills(projectid) {
        var req = {
            projectid: projectid
        };
        var url = OC.generateUrl('/apps/spend/getBills');
        spend.currentGetProjectsAjax = $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            $('#bill-list').html('');
            spend.bills[projectid] = {};
            var bill;
            for (var i = 0; i < response.length; i++) {
                bill = response[i];
                addBill(projectid, bill);
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('spend', 'Failed to get bills'));
        });
    }

    function getProjectName(projectid) {
        return spend.projects[projectid].name;
    }

    function updateDisplayedBill(projectid, billid, what, payer_id) {
        var projectName = getProjectName(projectid);
        $('.bill-title').attr('billid', billid);
        var c = {h: 0, s: 0, l: 50};
        if (billid !== 0) {
            var payerName = getMemberName(projectid, payer_id);
            c = getMemberColor(payerName);
        }
        $('.bill-title').text(
            `${t('spend', 'Bill "{what}" of project {proj}', {what: what, proj: projectName})}`
        );
        $('.bill-title').attr('style', `background-color: hsl(${c.h}, ${c.s}%, ${c.l}%);`);
    }

    function displayBill(projectid, billid) {
        var bill = spend.bills[projectid][billid];
        var projectName = getProjectName(projectid);

        var owers = bill.owers;
        var owerIds = [];
        for (var i=0; i < owers.length; i++) {
            owerIds.push(owers[i].id);
        }

        var c = {h: 0, s: 0, l: 50};
        var owerCheckboxes = '';
        var payerOptions = '';
        var member;
        var selected, checked;
        for (var memberid in spend.members[projectid]) {
            member = spend.members[projectid][memberid];
            // payer
            selected = '';
            if (member.id === bill.payer_id) {
                selected = ' selected';
            }
            payerOptions = payerOptions + `<option value="${member.id}"${selected}>${member.name}</option>`;
            // owers
            checked = '';
            if (owerIds.indexOf(member.id) !== -1) {
                checked = ' checked';
            }
            owerCheckboxes = owerCheckboxes + `
                <div class="owerEntry">
                <input id="${projectid}${member.id}" owerid="${member.id}" type="checkbox"${checked}/>
                <label for="${projectid}${member.id}">${member.name}</label>
                </div>
            `;
        }
        if (billid !== 0) {
            var payerName = getMemberName(projectid, bill.payer_id);
            c = getMemberColor(payerName);
        }
        $('#billdetail').html('');
        var detail = `
            <h2 class="bill-title" projectid="${projectid}" billid="${bill.id}" style="background-color: hsl(${c.h}, ${c.s}%, ${c.l}%);">
                ${t('spend', 'Bill "{what}" of project {proj}', {what: bill.what, proj: projectName})}
            </h2>
            <div class="bill-form">
                <div class="bill-left">
                    <div class="bill-what">
                        <a class="icon icon-tag"></a><span>${t('spend', 'What? (press enter to validate)')}</span><br/>
                        <input type="text" class="input-bill-what" value="${bill.what}"/>
                    </div>
                    <div class="bill-amount">
                        <a class="icon icon-quota"></a><span>${t('spend', 'How much? (press enter to validate)')}</span><br/>
                        <input type="number" class="input-bill-amount" value="${bill.amount}" step="0.01" min="0"/>
                    </div>
                    <div class="bill-payer">
                        <a class="icon icon-user"></a><span>${t('spend', 'Who payed?')}</span><br/>
                        <select class="input-bill-payer">
                            ${payerOptions}
                        </select>
                    </div>
                    <div class="bill-date">
                        <a class="icon icon-calendar-dark"></a><span>${t('spend', 'When?')}</span><br/>
                        <input type="date" class="input-bill-date" value="${bill.date}"/>
                    </div>
                </div>
                <div class="bill-right">
                    <div class="bill-owers">
                        <a class="icon icon-group"></a><span>${t('spend', 'For whom?')}</span>
                        <div class="owerAllNoneDiv">
                        <button id="owerAll">${t('spend', 'All')}</button>
                        <button id="owerNone">${t('spend', 'None')}</button>
                        </div>
                        ${owerCheckboxes}
                    </div>
                </div>
            </div>
        `;

        $(detail).appendTo('#billdetail');
    }

    function getMemberName(projectid, memberid) {
        //var memberName = $('.projectitem[projectid='+projectid+'] .memberlist > li[memberid='+memberid+'] b.memberName').text();
        var memberName = spend.members[projectid][memberid].name;
        return memberName;
    }

    function getMemberColor(memberName) {
        var memberFirstLetter = memberName[0];
        var memberSecondLetter = 'a';
        if (memberName.length > 1) {
            memberSecondLetter = memberName[1];
        }
        var c = getLetterColor(memberFirstLetter, memberSecondLetter);
        return c;
    }

    function addBill(projectid, bill) {
        spend.bills[projectid][bill.id] = bill;

        var owerNames = '';
        var ower;
        for (var i=0; i < bill.owers.length; i++) {
            ower = bill.owers[i];
            owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
        }
        owerNames = owerNames.replace(/, $/, '');
        var title = '';
        var memberName = '';
        var memberFirstLetter;
        var c;
        if (bill.id !== 0) {
            memberName = getMemberName(projectid, bill.payer_id);
            memberFirstLetter = memberName[0];

            title = bill.what + '\n' + bill.amount.toFixed(2) + '\n' +
                bill.date + '\n' + memberName + ' → ' + owerNames;
            c = getMemberColor(memberName);
        }
        else {
            c = {h: 0, s: 0, l: 50};
            memberFirstLetter = '-';
        }
        var item = `<a href="#" class="app-content-list-item billitem" billid="${bill.id}" projectid="${projectid}" title="${title}">
            <div class="app-content-list-item-icon" style="background-color: hsl(${c.h}, ${c.s}%, ${c.l}%);">${memberFirstLetter}</div>
            <div class="app-content-list-item-line-one">${bill.what}</div>
            <div class="app-content-list-item-line-two">${bill.amount.toFixed(2)} (${memberName} → ${owerNames})</div>
            <span class="app-content-list-item-details">${bill.date}</span>
            <div class="icon-delete deleteBillIcon"></div>
            <div class="icon-history undoDeleteBill" title="Undo"></div>
        </a>`;
        $(item).prependTo('.app-content-list');
    }

    function updateProjectBalances(projectid) {
        var req = {
            projectid: projectid
        };
        var url = OC.generateUrl('/apps/spend/getProjectInfo');
        spend.currentGetProjectsAjax = $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            console.log(response);
            var balance, balanceField, balanceClass;
            for (var memberid in response.balance) {
                balance = response.balance[memberid];
                balanceField = $('.projectitem[projectid='+projectid+'] .memberlist > li[memberid='+memberid+'] b.balance');
                balanceField.removeClass('balancePositive').removeClass('balanceNegative');
                if (balance < 0) {
                    balanceClass = 'balanceNegative';
                    balanceField.addClass(balanceClass).text(balance.toFixed(2));
                }
                else if (balance > 0) {
                    balanceClass = 'balancePositive';
                    balanceField.addClass(balanceClass).text(balance.toFixed(2));
                }
                else {
                    balanceField.text(balance.toFixed(2));
                }
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('spend', 'Failed to get bills'));
        });
    }

    function addProject(project) {
        spend.projects[project.id] = project;

        var name = project.name;
        var projectid = project.id;
        var projectSelected = '';
        if (spend.restoredSelectedProjectId === projectid) {
            projectSelected = ' open';
            spend.currentProjectId = projectid;
            getBills(projectid);
        }
        var li = `
            <li class="projectitem collapsible${projectSelected}" projectid="${projectid}">
                <a class="icon-folder" href="#" title="${projectid}">
                    <span>${name}</span>
                </a>
                <div class="app-navigation-entry-utils">
                    <ul>
                        <li class="app-navigation-entry-utils-counter">${project.members.length}</li>
                        <li class="app-navigation-entry-utils-menu-button">
                            <button></button>
                        </li>
                    </ul>
                </div>
                <div class="app-navigation-entry-edit">
                    <div>
                        <input type="text" value="${project.name}" class="editProjectInput">
                        <input type="submit" value="" class="icon-close editProjectClose">
                        <input type="submit" value="" class="icon-checkmark editProjectOk">
                    </div>
                </div>
                <div class="app-navigation-entry-menu">
                    <ul>
                        <li>
                            <a href="#" class="addMember">
                                <span class="icon-add"></span>
                                <span>${t('spend', 'Add member')}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="editProjectName">
                                <span class="icon-rename"></span>
                                <span>${t('spend', 'Rename')}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="editProjectPassword">
                                <span class="icon-rename"></span>
                                <span>${t('spend', 'Change password')}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="deleteProject">
                                <span class="icon-delete"></span>
                                <span>${t('spend', 'Delete')}</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="app-navigation-entry-deleted">
                    <div class="app-navigation-entry-deleted-description">${t('spend', 'Deleted {id}', {id: project.id})}</div>
                    <button class="app-navigation-entry-deleted-button icon-history undoDeleteProject" title="Undo"></button>
                </div>
                <ul class="memberlist"></ul>
            </li>`;

        $(li).appendTo('#projectlist');

        for (var i=0; i < project.members.length; i++) {
            var memberId = project.members[i].id;
            addMember(projectid, project.members[i], project.balance[memberId]);
        }
    }

    function addMember(projectid, member, balance) {
        // add member to dict
        if (!spend.members.hasOwnProperty(projectid)) {
            spend.members[projectid] = {};
        }
        spend.members[projectid][member.id] = member;

        var balanceStr;
        if (balance > 0) {
            balanceStr = '<b class="balance balancePositive">+'+balance.toFixed(2)+'</b>';
        }
        else if (balance < 0) {
            balanceStr = '<b class="balance balanceNegative">'+balance.toFixed(2)+'</b>';
        }
        else {
            balanceStr = '<b class="balance">'+balance.toFixed(2)+'</b>';
        }
        var iconStr, iconToggleStr, toggleStr;
        if (member.activated) {
            iconStr = 'icon-user';
            iconToggleStr = 'icon-delete';
            toggleStr = t('spend', 'Remove');
        }
        else {
            iconStr = 'icon-disabled-user';
            iconToggleStr = 'icon-history';
            toggleStr = t('spend', 'Reactivate');
        }

        var li = `<li memberid="${member.id}" class="memberitem"><a class="${iconStr}" href="#">
                <span><b class="memberName">${member.name}</b> (x<b class="memberWeight">${member.weight}</b>) ${balanceStr}</span>
            </a>
            <div class="app-navigation-entry-utils">
                <ul>
                    <!--li class="app-navigation-entry-utils-counter">1</li-->
                    <li class="app-navigation-entry-utils-menu-button">
                        <button></button>
                    </li>
                </ul>
            </div>
            <div class="app-navigation-entry-menu">
                <ul>
                    <li>
                        <a href="#" class="renameMember">
                            <span class="icon-rename"></span>
                            <span>${t('spend', 'Rename')}</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="editWeightMember">
                            <span class="icon-rename"></span>
                            <span>${t('spend', 'Change weight')}</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="toggleMember">
                            <span class="${iconToggleStr}"></span>
                            <span>${toggleStr}</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="app-navigation-entry-edit">
                <div>
                    <input type="text" value="${member.name}" class="editMemberInput">
                    <input type="submit" value="" class="icon-close editMemberClose">
                    <input type="submit" value="" class="icon-checkmark editMemberOk">
                </div>
            </div>
        </li>`;

        $(li).appendTo('#projectlist li.projectitem[projectid='+projectid+'] .memberlist');
    }

    function onBillEdited() {
        // get bill info
        var billid = $('.bill-title').attr('billid');
        var projectid = $('.bill-title').attr('projectid');
        // check fields validity
        var valid = true;

        var what = $('.input-bill-what').val();
        var date = $('.input-bill-date').val();
        var amount = parseFloat($('.input-bill-amount').val());
        var payer_id = parseInt($('.input-bill-payer').val());
        var owerIds = [];
        var owerId;
        $('.owerEntry input').each(function() {
            if ($(this).is(':checked')) {
                owerId = parseInt($(this).attr('owerid'));
                if (isNaN(owerId)) {
                    valid = false;
                }
                else {
                    owerIds.push(owerId);
                }
            }
        });

        if (what === null || what === '') {
            valid = false;
        }
        if (date === null || date === '' || date.match(/^\d\d\d\d-\d\d-\d\d$/g) === null) {
            valid = false;
        }
        if (isNaN(amount) || isNaN(payer_id)) {
            valid = false;
        }
        if (owerIds.length === 0) {
            valid = false;
        }

        // if valid, save the bill or create it if needed
        if (valid) {
            if (billid === '0') {
                createBill(projectid, what, amount, payer_id, date, owerIds);
            }
            else {
                saveBill(projectid, billid, what, amount, payer_id, date, owerIds);
            }
        }
        else {
            if (billid !== '0') {
                OC.Notification.showTemporary(t('spend', 'Bill values are not valid'));
            }
        }
    }

    function saveOptionValue(optionValues) {
        if (!spend.pageIsPublic) {
            var req = {
                options: optionValues
            };
            var url = OC.generateUrl('/apps/spend/saveOptionValue');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
            }).fail(function() {
                OC.Notification.showTemporary(
                    t('spend', 'Failed to save option values')
                );
            });
        }
    }

    function restoreOptions() {
        var mom;
        var url = OC.generateUrl('/apps/spend/getOptionsValues');
        var req = {
        };
        var optionsValues = {};
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            optionsValues = response.values;
            if (optionsValues) {
                for (var k in optionsValues) {
                    if (k === 'selectedProject') {
                        spend.restoredSelectedProjectId = optionsValues[k];
                    }
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.Notification.showTemporary(
                t('spend', 'Failed to restore options values')
            );
        });
    }

    $(document).ready(function() {
        spend.pageIsPublic = (document.URL.indexOf('/spend/project') !== -1);
        if ( !spend.pageIsPublic ) {
            restoreOptions();
        }
        else {
            //restoreOptionsFromUrlParams();
            spend.projectid = $('#projectid').text();
            spend.password = $('#password').text();
            console.log(spend.projectid+' and '+spend.password);
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
            if (!event.target.matches('#newmemberdiv, #newmemberdiv input, #newmemberdiv label, #newmemberdiv button, .addMember, .addMember span')) {
                $('#newmemberdiv').slideUp();
            }
            //console.log(event.target);
        }

        $('body').on('click', '.app-navigation-entry-utils-menu-button', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });

        $('body').on('click', '.projectitem > a', function(e) {
            var wasOpen = $(this).parent().hasClass('open');
            $('.projectitem.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().addClass('open');
                var projectid = $(this).parent().attr('projectid');
                saveOptionValue({selectedProject: projectid});
                spend.currentProjectId = projectid;
                $('#billdetail').html('');
                getBills(projectid);
            }
        });

        $('#newprojectbutton').click(function() {
            var div = $('#newprojectdiv');
            if (div.is(':visible')) {
                div.slideUp();
                $(this).removeClass('icon-triangle-s').addClass('icon-triangle-e');
            }
            else {
                div.slideDown();
                $(this).removeClass('icon-triangle-e').addClass('icon-triangle-s');
            }
        });

        $('#projectnameinput, #projectidinput, #projectpasswordinput').on('keyup', function(e) {
            if (e.key === 'Enter') {
                var name = $('#projectnameinput').val();
                var id = $('#projectidinput').val();
                var password = $('#projectpasswordinput').val();
                if (name && id && password) {
                    createProject(id, name);
                }
                else {
                    OC.Notification.showTemporary(t('spend', 'Invalid values'));
                }
            }
        });

        $('#createproject').click(function() {
            var name = $('#projectnameinput').val();
            var id = $('#projectidinput').val();
            var password = $('#projectpasswordinput').val();
            if (name && id && password) {
                createProject(id, name);
            }
            else {
                OC.Notification.showTemporary(t('spend', 'Invalid values'));
            }
        });

        $('body').on('click', '.deleteProject', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            $(this).parent().parent().parent().parent().addClass('deleted');
            spend.projectDeletionTimer[projectid] = new Timer(function() {
                deleteProject(projectid);
            }, 7000);
        });

        $('body').on('click', '.undoDeleteProject', function(e) {
            var projectid = $(this).parent().parent().attr('projectid');
            $(this).parent().parent().removeClass('deleted');
            spend.projectDeletionTimer[projectid].pause();
            delete spend.projectDeletionTimer[projectid];
        });

        $('body').on('click', '.addMember', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('projectid');
            var name = $('.projectitem[projectid='+id+'] > a > span').text();
            $('#newmemberdiv').slideDown();
            $('#newmemberdiv #newmemberbutton').text(t('spend', 'Add member to project {pname}', {pname: name}));
            $('#newmemberdiv #newmemberbutton').attr('projectid', id);
        });

        $('#newmemberbutton').click(function() {
            var projectid = $(this).attr('projectid');
            var name = $(this).parent().find('input').val();
            if (projectid && name) {
                createMember(projectid, name);
            }
            else {
                OC.Notification.showTemporary(t('spend', 'Invalid values'));
            }
        });

        $('#newmembername').on('keyup', function(e) {
            if (e.key === 'Enter') {
                var name = $(this).val();
                var projectid = $(this).parent().find('button').attr('projectid');
                if (projectid && name) {
                    createMember(projectid, name);
                }
                else {
                    OC.Notification.showTemporary(t('spend', 'Invalid values'));
                }
            }
        });

        $('body').on('click', '.renameMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var name = $(this).parent().parent().parent().parent().find('a > span > b.memberName').text();
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(name).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            spend.memberEditionMode = MEMBER_NAME_EDITION;
        });

        $('body').on('click', '.editWeightMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var weight = $(this).parent().parent().parent().parent().find('a > span > b.memberWeight').text();
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(weight).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            spend.memberEditionMode = MEMBER_WEIGHT_EDITION;
        });

        $('body').on('click', '.editMemberClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editMemberInput', function(e) {
            if (e.key === 'Enter') {
                var memberid = $(this).parent().parent().parent().attr('memberid');
                var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
                if (spend.memberEditionMode === MEMBER_NAME_EDITION) {
                    var newName = $(this).val();
                    editMember(projectid, memberid, newName, null, null);
                }
                else if (spend.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                    var newWeight = $(this).val();
                    var newName = $(this).parent().parent().parent().find('b.memberName').text();
                    editMember(projectid, memberid, newName, newWeight, null);
                }
            }
        });

        $('body').on('click', '.editMemberOk', function(e) {
            var memberid = $(this).parent().parent().parent().attr('memberid');
            var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            if (spend.memberEditionMode === MEMBER_NAME_EDITION) {
                var newName = $(this).parent().find('.editMemberInput').val();
                editMember(projectid, memberid, newName, null, null);
            }
            else if (spend.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                var newWeight = $(this).parent().find('.editMemberInput').val();
                var newName = $(this).parent().parent().parent().find('b.memberName').text();
                editMember(projectid, memberid, newName, newWeight, null);
            }
        });

        $('body').on('click', '.toggleMember', function(e) {
            var memberid = $(this).parent().parent().parent().parent().attr('memberid');
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var newName = $(this).parent().parent().parent().parent().find('>a span b.memberName').text();
            var activated = $(this).find('span').first().hasClass('icon-history');
            editMember(projectid, memberid, newName, null, activated);
        });

        $('body').on('click', '.editProjectName', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            var name = $(this).parent().parent().parent().parent().find('>a > span').text();
            $(this).parent().parent().parent().parent().find('.editProjectInput').val(name).attr('type', 'text').focus().select();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            spend.projectEditionMode = PROJECT_NAME_EDITION;
        });

        $('body').on('click', '.editProjectPassword', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            $(this).parent().parent().parent().parent().find('.editProjectInput').attr('type', 'password').val('').focus();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            spend.projectEditionMode = PROJECT_PASSWORD_EDITION;
        });

        $('body').on('click', '.editProjectClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editProjectInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $(this).parent().parent().parent().attr('projectid');
                if (spend.projectEditionMode === PROJECT_NAME_EDITION) {
                    var newName = $(this).val();
                    editProject(projectid, newName, null, null);
                }
                else if (spend.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                    var newPassword = $(this).val();
                    var newName = $(this).parent().parent().parent().find('>a span').text();
                    editProject(projectid, newName, null, newPassword);
                }
            }
        });

        $('body').on('click', '.editProjectOk', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            if (spend.projectEditionMode === PROJECT_NAME_EDITION) {
                var newName = $(this).parent().find('.editProjectInput').val();
                editProject(projectid, newName, null, null);
            }
            else if (spend.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                var newPassword = $(this).parent().find('.editProjectInput').val();
                var newName = $(this).parent().parent().parent().find('>a span').text();
                editProject(projectid, newName, null, newPassword);
            }
        });

        $('body').on('click', '.billitem', function(e) {
            if (!$(e.target).hasClass('deleteBillIcon') && !$(e.target).hasClass('undoDeleteBill')) {
                var billid = $(this).attr('billid');
                var projectid = $(this).attr('projectid');
                displayBill(projectid, billid);
            }
        });

        $('body').on('change', '#billdetail input, #billdetail select', function(e) {
            onBillEdited();
        });

        $('body').on('click', '#owerAll', function(e) {
            $('.owerEntry input').prop('checked', true);
            onBillEdited();
        });

        $('body').on('click', '#owerNone', function(e) {
            $('.owerEntry input').prop('checked', false);
            onBillEdited();
        });

        $('body').on('click', '.undoDeleteBill', function(e) {
            var billid = $(this).parent().attr('billid');
            spend.billDeletionTimer[billid].pause();
            delete spend.billDeletionTimer[billid];
            $(this).parent().find('.deleteBillIcon').show();
            $(this).hide();
        });

        $('body').on('click', '.deleteBillIcon', function(e) {
            var billid = $(this).parent().attr('billid');
            if (billid !== '0') {
                var projectid = $(this).parent().attr('projectid');
                $(this).parent().find('.undoDeleteBill').show();
                $(this).hide();
                spend.billDeletionTimer[billid] = new Timer(function() {
                    deleteBill(projectid, billid);
                }, 7000);
            }
            else {
                console.log($('.bill-title'));
                console.log($('.bill-title').attr('billid'));
                if ($('.bill-title').length > 0 && $('.bill-title').attr('billid') === billid) {
                    $('#billdetail').html('');
                }
                $(this).parent().fadeOut('slow', function() {
                    $(this).remove();
                });
            }
        });

        $('body').on('click', '#newBillButton', function(e) {
            var projectid = spend.currentProjectId;
            if (spend.currentProjectId !== null && $('.billitem[billid=0]').length === 0) {
                var bill = {
                    id: 0,
                    what: t('spend', 'New Bill'),
                    date: moment().format('YYYY-MM-DD'),
                    amount: 0.0,
                    payer_id: 0,
                    owers: []
                };
                addBill(projectid, bill)
                displayBill(projectid, bill.id);
            }
        });

        $('body').on('focus', '.input-bill-what, .input-bill-amount, #projectidinput, #projectnameinput, #projectpasswordinput', function(e) {
            $(this).select();
        });

        $('#statsButton').click(function() {
            if (spend.currentProjectId !== null) {
                getProjectStatistics(spend.currentProjectId);
            }
        });

        // last thing to do : get the projects
        getProjects();
    }

})(jQuery, OC);
