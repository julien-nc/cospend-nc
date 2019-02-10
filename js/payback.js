/*jshint esversion: 6 */
/**
 * Nextcloud - payback
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

    var payback = {
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

    function hslToRgb(h, s, l) {
        var r, g, b;

        if(s == 0){
            r = g = b = l; // achromatic
        }else{
            var hue2rgb = function hue2rgb(p, q, t){
                if(t < 0) t += 1;
                if(t > 1) t -= 1;
                if(t < 1/6) return p + (q - p) * 6 * t;
                if(t < 1/2) return q;
                if(t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            }

            var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            var p = 2 * l - q;
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }

        //return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
        //return {r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255)};
        var rgb = {r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255)};
        var hexStringR = rgb.r.toString(16);
        if (hexStringR.length % 2) {
            hexStringR = '0' + hexStringR;
        }
        var hexStringG = rgb.g.toString(16);
        if (hexStringG.length % 2) {
            hexStringG = '0' + hexStringG;
        }
        var hexStringB = rgb.b.toString(16);
        if (hexStringB.length % 2) {
            hexStringB = '0' + hexStringB;
        }
        //console.log('r:'+hexStringR+' g:'+hexStringG+' b:'+hexStringB);
        //console.log('rr:'+rgb.r+' gg:'+rgb.g+' bb:'+rgb.b);
        return hexStringR+hexStringG+hexStringB;
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

    var undoDeleteBillStyle = 'opacity:1; background-image: url('+OC.generateUrl('/svg/core/actions/history?color=2AB4FF')+');';

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
        var url = OC.generateUrl('/apps/payback/createProject');
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
            $('#newprojectbutton').removeClass('icon-triangle-s').addClass('icon-triangle-e');
            div.slideUp('slow', function() {
                $('#newBillButton').fadeIn();
            });
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to create project') + ' ' + response.responseText);
        });
    }

    function createMember(projectid, name) {
        var req = {
            name: name
        };
        var url;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            url = OC.generateUrl('/apps/payback/addMember');
        }
        else {
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password+'/members');
        }
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
            $('.newmemberdiv').slideUp();
            updateNumberOfMember(projectid);
            $('#billdetail').html('');
            OC.Notification.showTemporary(t('payback', 'Created member {name}', {name: name}));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to add member') + ' ' + response.responseText);
        });
    }

    function editMember(projectid, memberid, newName, newWeight, newActivated) {
        var req = {
            name: newName,
            weight: newWeight,
            activated: newActivated
        };
        var url, type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            req.memberid = memberid;
            url = OC.generateUrl('/apps/payback/editMember');
            type = 'POST';
        }
        else {
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password+'/members/'+memberid);
            type = 'PUT';
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var memberLine = $('.projectitem[projectid='+projectid+'] ul.memberlist > li[memberid='+memberid+']');
            // update member values
            if (newName) {
                memberLine.find('b.memberName').text(newName);
                payback.members[projectid][memberid].name = newName;
            }
            if (newWeight) {
                memberLine.find('b.memberWeight').text(newWeight);
                payback.members[projectid][memberid].weight = newWeight;
                updateProjectBalances(projectid);
            }
            if (newActivated !== null && newActivated === false) {
                memberLine.find('>a').removeClass('icon-user').addClass('icon-disabled-user');
                memberLine.find('.toggleMember span').first().removeClass('icon-delete').addClass('icon-history');
                memberLine.find('.toggleMember span').eq(1).text(t('payback', 'Reactivate'));
                payback.members[projectid][memberid].activated = newActivated;
            }
            else if (newActivated !== null && newActivated === true) {
                memberLine.find('>a').removeClass('icon-disabled-user').addClass('icon-user');
                memberLine.find('.toggleMember span').first().removeClass('icon-history').addClass('icon-delete');
                memberLine.find('.toggleMember span').eq(1).text(t('payback', 'Deactivate'));
                payback.members[projectid][memberid].activated = newActivated;
            }
            // anyway : update icon
            var c = getMemberColor(payback.members[projectid][memberid].name);
            var rgbC = hslToRgb(c.h/360, c.s/100, c.l/100);
            var imgurl;
            if (payback.members[projectid][memberid].activated) {
                imgurl = OC.generateUrl(`/svg/core/actions/user?color=${rgbC}`);
            }
            else {
                imgurl = OC.generateUrl(`/svg/core/actions/disabled-user?color=${rgbC}`);
            }
            memberLine.find('>a').attr('style', `background-image: url(${imgurl})`);
            // remove editing mode
            memberLine.removeClass('editing');
            OC.Notification.showTemporary(t('payback', 'Saved member'));
            // get bills again to refresh names
            getBills(projectid);
            // reset bill edition
            $('#billdetail').html('');
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to save member') + ' ' + response.responseText);
        });
    }

    function createBill(projectid, what, amount, payer_id, date, owerIds) {
        var req = {
            what: what,
            date: date,
            payer: payer_id,
            payed_for: owerIds.join(','),
            amount: amount
        };
        var url, type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            url = OC.generateUrl('/apps/payback/addBill');
        }
        else {
            url = OC.generateUrl(`/apps/payback/api/projects/${payback.projectid}/${payback.password}/bills`);
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var billid = response;
            // update dict
            payback.bills[projectid][billid] = {
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
            payback.bills[projectid][billid].owers = billOwers;

            // update ui
            var bill = payback.bills[projectid][billid];
            updateBillItem(projectid, 0, bill);
            updateDisplayedBill(projectid, billid, what, payer_id);

            updateProjectBalances(projectid);

            OC.Notification.showTemporary(t('payback', 'Bill created'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to create bill') + ' ' + response.responseText);
        });
    }

    function saveBill(projectid, billid, what, amount, payer_id, date, owerIds) {
        var req = {
            what: what,
            date: date,
            payer: payer_id,
            payed_for: owerIds.join(','),
            amount: amount
        };
        var url, type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            req.billid = billid;
            type = 'POST';
            url = OC.generateUrl('/apps/payback/editBill');
        }
        else {
            type = 'PUT';
            url = OC.generateUrl(`/apps/payback/api/projects/${payback.projectid}/${payback.password}/bills/${billid}`);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // update dict
            payback.bills[projectid][billid].what = what;
            payback.bills[projectid][billid].date = date;
            payback.bills[projectid][billid].amount = amount;
            payback.bills[projectid][billid].payer_id = payer_id;
            var billOwers = [];
            for (var i=0; i < owerIds.length; i++) {
                billOwers.push({id: owerIds[i]});
            }
            payback.bills[projectid][billid].owers = billOwers;

            // update ui
            var bill = payback.bills[projectid][billid];
            updateBillItem(projectid, billid, bill);
            updateDisplayedBill(projectid, billid, what, payer_id);

            updateProjectBalances(projectid);

            OC.Notification.showTemporary(t('payback', 'Saved bill'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('payback', 'Failed to save bill') +
                ' ' + response.responseText
            );
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
        var item = '<a href="#" class="app-content-list-item billitem" billid="'+bill.id+'" projectid="'+projectid+'" title="'+title+'">' +
            '<div class="app-content-list-item-icon" style="background-color: hsl('+c.h+', '+c.s+'%, '+c.l+'%);">'+memberFirstLetter+'</div>' +
            '<div class="app-content-list-item-line-one">'+bill.what+'</div>' +
            '<div class="app-content-list-item-line-two">'+bill.amount.toFixed(2)+' ('+memberName+' -> '+owerNames+')</div>' +
            '<span class="app-content-list-item-details">'+bill.date+'</span>' +
            '<div class="icon-delete deleteBillIcon"></div>' +
            '<div class="icon-history undoDeleteBill" style="'+undoDeleteBillStyle+'" title="Undo"></div>' +
            '</a>';
        billItem.replaceWith(item);
    }

    function editProject(projectid, newName, newEmail, newPassword) {
        var req = {
            name: newName,
            contact_email: newEmail,
            password: newPassword
        };
        var url, type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            type = 'POST';
            url = OC.generateUrl('/apps/payback/editProject');
        }
        else {
            type = 'PUT';
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var projectLine = $('.projectitem[projectid='+projectid+']');
            // update project values
            if (newName) {
                projectLine.find('>a span').text(newName);
                payback.projects[projectid].name = newName;
            }
            if (payback.pageIsPublic && newPassword) {
                payback.password = newPassword;
            }
            // update deleted text
            var projectName = payback.projects[projectid].name;
            projectLine.find('.app-navigation-entry-deleted-description').text(
                t('payback', 'Deleted {name}', {name: projectName})
            );
            // remove editing mode
            projectLine.removeClass('editing');
            // reset bill edition
            $('#billdetail').html('');
            OC.Notification.showTemporary(t('payback', 'Saved project'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('payback', 'Failed to save project') +
                ' ' + response.responseText
            );
        });
    }

    function updateNumberOfMember(projectid) {
        var nbMembers = $('li.projectitem[projectid='+projectid+'] ul.memberlist > li').length;
        $('li.projectitem[projectid='+projectid+'] .app-navigation-entry-utils-counter span').text(nbMembers);
    }

    function deleteProject(id) {
        var req = {
        };
        var url, type;
        if (!payback.pageIsPublic) {
            req.projectid = id
            url = OC.generateUrl('/apps/payback/deleteProject');
            type = 'POST';
        }
        else {
            type = 'DELETE';
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            $('.projectitem[projectid='+id+']').fadeOut('slow', function() {
                $(this).remove();
            });
            if (payback.currentProjectId === id) {
                $('#bill-list').html('');
                $('#billdetail').html('');
            }
            if (payback.pageIsPublic) {
                var redirectUrl = OC.generateUrl('/apps/payback/login');
                window.location.replace(redirectUrl);
            }
            OC.Notification.showTemporary(t('payback', 'Deleted project {id}', {id: id}));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to delete project') + ' ' + response.responseText);
        });
    }

    function deleteBill(projectid, billid) {
        var req = {
        };
        var url, type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            req.billid = billid;
            type = 'POST';
            url = OC.generateUrl('/apps/payback/deleteBill');
        }
        else {
            type = 'DELETE';
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password+'/bills/'+billid);
        }
        $.ajax({
            type: type,
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
            delete payback.bills[projectid][billid];
            updateProjectBalances(projectid);
            OC.Notification.showTemporary(t('payback', 'Deleted bill'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to delete bill') + ' ' + response.responseText);
        });
    }

    function getProjects() {
        var req = {
        };
        var url;
        var type;
        if (!payback.pageIsPublic) {
            url = OC.generateUrl('/apps/payback/getProjects');
            type = 'POST';
        }
        else {
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password);
            type = 'GET';
        }
        payback.currentGetProjectsAjax = $.ajax({
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
            if (!payback.pageIsPublic) {
                for (var i = 0; i < response.length; i++) {
                    addProject(response[i]);
                }
            }
            else {
                addProject(response);
                $('.projectitem').addClass('open');
                payback.currentProjectId = payback.projectid;
                getBills(payback.projectid);
            }
        }).always(function() {
            payback.currentGetProjectsAjax = null;
        }).fail(function() {
            OC.Notification.showTemporary(t('payback', 'Failed to get projects'));
        });
    }

    function getProjectStatistics(projectid) {
        var req = {
        };
        var url;
        var type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            type = 'POST';
            url = OC.generateUrl('/apps/payback/getStatistics');
        }
        else {
            type = 'GET';
            url = OC.generateUrl(`/apps/payback/api/projects/${payback.projectid}/${payback.password}/statistics`);
        }
        payback.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            displayStatistics(projectid, response);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('payback', 'Failed to get statistics'));
        });
    }

    function getProjectSettlement(projectid) {
        var req = {
        };
        var url;
        var type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            type = 'POST';
            url = OC.generateUrl('/apps/payback/getSettlement');
        }
        else {
            type = 'GET';
            url = OC.generateUrl(`/apps/payback/api/projects/${payback.projectid}/${payback.password}/settle`);
        }
        payback.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            displaySettlement(projectid, response);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('payback', 'Failed to get settlement'));
        });
    }

    function displaySettlement(projectid, transactionList) {
        // unselect bill
        $('.billitem').removeClass('selectedbill');

        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('payback', 'Settlement of project {name}', {name: projectName});
        var fromStr = t('payback', 'Who pays?');
        var toStr = t('payback', 'To whom?');
        var howMuchStr = t('payback', 'How much?');
        var settlementStr = `
            <div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>
            <h2 id="settlementTitle">${titleStr}</h2>
            <table id="settlementTable"><thead>
                <th>${fromStr}</th>
                <th>${toStr}</th>
                <th>${howMuchStr}</th>
            </thead>
        `;
        var whoPaysName, toWhomName, amount;
        for (var i=0; i < transactionList.length; i++) {
            amount = transactionList[i].amount.toFixed(2);
            whoPaysName = getMemberName(projectid, transactionList[i].from);
            toWhomName = getMemberName(projectid, transactionList[i].to);
            settlementStr = settlementStr + `
                <tr>
                    <td>${whoPaysName}</td>
                    <td>${toWhomName}</td>
                    <td>${amount}</td>
                </tr>
            `;
        }
        settlementStr = settlementStr + `
            </table>
        `;
        $('#billdetail').html(settlementStr);
    }

    function displayStatistics(projectid, statList) {
        // unselect bill
        $('.billitem').removeClass('selectedbill');

        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('payback', 'Statistics of project {name}', {name: projectName});
        var nameStr = t('payback', 'Member name');
        var paidStr = t('payback', 'Paid');
        var spentStr = t('payback', 'Spent');
        var balanceStr = t('payback', 'Balance');
        var statsStr = `
            <div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>
            <h2 id="statsTitle">${titleStr}</h2>
            <table id="statsTable"><thead>
                <th>${nameStr}</th>
                <th>${paidStr}</th>
                <th>${spentStr}</th>
                <th>${balanceStr}</th>
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
        };
        var url;
        var type;
        if (!payback.pageIsPublic) {
            url = OC.generateUrl('/apps/payback/getBills');
            type = 'POST';
            req.projectid = projectid;
        }
        else {
            url = OC.generateUrl(`/apps/payback/api/projects/${payback.projectid}/${payback.password}/bills`)
            type = 'GET';
        }
        payback.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            $('#bill-list').html('');
            payback.bills[projectid] = {};
            var bill;
            for (var i = 0; i < response.length; i++) {
                bill = response[i];
                addBill(projectid, bill);
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('payback', 'Failed to get bills'));
        });
    }

    function getProjectName(projectid) {
        return payback.projects[projectid].name;
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
            t('payback', 'Bill "{what}" of project {proj}', {what: what, proj: projectName})
        );
        $('.bill-title').attr('style', `background-color: hsl(${c.h}, ${c.s}%, ${c.l}%);`);
    }

    function displayBill(projectid, billid) {
        // select bill item
        $('.billitem').removeClass('selectedbill');
        $('.billitem[billid='+billid+']').addClass('selectedbill');

        var bill = payback.bills[projectid][billid];
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
        var selected, checked, readonly;
        for (var memberid in payback.members[projectid]) {
            member = payback.members[projectid][memberid];
            // payer
            selected = '';
            if (member.id === bill.payer_id) {
                selected = ' selected';
            }
            // show member if it's the payer or if it's activated
            if (member.activated || member.id === bill.payer_id) {
                payerOptions = payerOptions + `<option value="${member.id}"${selected}>${member.name}</option>`;
            }
            // owers
            checked = '';
            if (owerIds.indexOf(member.id) !== -1) {
                checked = ' checked';
            }
            readonly = '';
            if (!member.activated) {
                readonly = ' disabled';
            }
            // show member if it's an ower or if it's activated
            if (member.activated || owerIds.indexOf(member.id) !== -1) {
                owerCheckboxes = owerCheckboxes + `
                    <div class="owerEntry">
                    <input id="${projectid}${member.id}" owerid="${member.id}" type="checkbox"${checked}${readonly}/>
                    <label for="${projectid}${member.id}">${member.name}</label>
                    </div>
                `;
            }
        }
        var payerDisabled = '';
        if (billid !== 0) {
            // disable payer select if bill is not new
            if (!payback.members[projectid][bill.payer_id].activated) {
                payerDisabled = ' disabled';
            }
            var payerName = getMemberName(projectid, bill.payer_id);
            c = getMemberColor(payerName);
        }
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var whatStr = t('payback', 'What? (press enter to validate)');
        var amountStr = t('payback', 'How much? (press enter to validate)');
        var payerStr = t('payback', 'Who payed?');
        var dateStr = t('payback', 'When?');
        var owersStr = t('payback', 'For whom?');
        var titleStr = t('payback', 'Bill "{what}" of project {proj}', {what: bill.what, proj: projectName});
        var allStr = t('payback', 'All');
        var noneStr = t('payback', 'None');
        var detail = `
            <div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>
            <h2 class="bill-title" projectid="${projectid}" billid="${bill.id}" style="background-color: hsl(${c.h}, ${c.s}%, ${c.l}%);">
                ${titleStr}
            </h2>
            <div class="bill-form">
                <div class="bill-left">
                    <div class="bill-what">
                        <label for="what">
                            <a class="icon icon-tag"></a>
                            ${whatStr}
                        </label>
                        <input type="text" id="what" class="input-bill-what" value="${bill.what}"/>
                    </div>
                    <div class="bill-amount">
                        <label for="amount">
                            <a class="icon icon-quota"></a>
                            ${amountStr}
                        </label>
                        <input type="number" id="amount" class="input-bill-amount" value="${bill.amount}" step="0.01" min="0"/>
                    </div>
                    <div class="bill-payer">
                        <label for="payer">
                            <a class="icon icon-user"></a>
                            ${payerStr}
                        </label>
                        <select id="payer" class="input-bill-payer"${payerDisabled}>
                            ${payerOptions}
                        </select>
                    </div>
                    <div class="bill-date">
                        <label for="date">
                            <a class="icon icon-calendar-dark"></a>
                            ${dateStr}
                        </label>
                        <input type="date" id="date" class="input-bill-date" value="${bill.date}"/>
                    </div>
                </div>
                <div class="bill-right">
                    <div class="bill-owers">
                        <a class="icon icon-group"></a><span>${owersStr}</span>
                        <div class="owerAllNoneDiv">
                        <button id="owerAll">${allStr}</button>
                        <button id="owerNone">${noneStr}</button>
                        </div>
                        ${owerCheckboxes}
                    </div>
                </div>
            </div>
        `;

        $(detail).appendTo('#billdetail');
        $('#billdetail .input-bill-what').focus().select();
    }

    function getMemberName(projectid, memberid) {
        //var memberName = $('.projectitem[projectid='+projectid+'] .memberlist > li[memberid='+memberid+'] b.memberName').text();
        var memberName = payback.members[projectid][memberid].name;
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

    function reload(msg) {
        OC.Notification.showTemporary(msg);
        new Timer(function() {
            location.reload();
        }, 5000);
    }

    function addBill(projectid, bill) {
        payback.bills[projectid][bill.id] = bill;

        var owerNames = '';
        var ower;
        for (var i=0; i < bill.owers.length; i++) {
            ower = bill.owers[i];
            if (!payback.members[projectid].hasOwnProperty(ower.id)) {
                reload(t('payback', 'Member list is not up to date. Reloading in 5 sec.'));
                return;
            }
            owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
        }
        owerNames = owerNames.replace(/, $/, '');
        var title = '';
        var memberName = '';
        var memberFirstLetter;
        var c;
        if (bill.id !== 0) {
            if (!payback.members[projectid].hasOwnProperty(bill.payer_id)) {
                reload(t('payback', 'Member list is not up to date. Reloading in 5 sec.'));
                return;
            }
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
            <div class="icon-history undoDeleteBill" style="${undoDeleteBillStyle}" title="Undo"></div>
        </a>`;
        $(item).prependTo('.app-content-list');
    }

    function updateProjectBalances(projectid) {
        var req = {
        };
        var url;
        var type;
        if (!payback.pageIsPublic) {
            req.projectid = projectid;
            url = OC.generateUrl('/apps/payback/getProjectInfo');
            type = 'POST';
        }
        else {
            url = OC.generateUrl('/apps/payback/api/projects/'+payback.projectid+'/'+payback.password);
            type = 'GET';
        }
        payback.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var balance, balanceField, balanceClass;
            for (var memberid in response.balance) {
                balance = response.balance[memberid];
                balanceField = $('.projectitem[projectid='+projectid+'] .memberlist > li[memberid='+memberid+'] b.balance');
                balanceField.removeClass('balancePositive').removeClass('balanceNegative');
                // just in case make member visible
                $('.memberitem[memberid='+memberid+']').removeClass('invisibleMember');
                if (balance < 0) {
                    balanceClass = 'balanceNegative';
                    balanceField.addClass(balanceClass).text(balance.toFixed(2));
                }
                else if (balance > 0) {
                    balanceClass = 'balancePositive';
                    balanceField.addClass(balanceClass).text('+' + balance.toFixed(2));
                }
                else {
                    balanceField.text(balance.toFixed(2));
                    // hide member if balance == 0 and disabled
                    if (!payback.members[projectid][memberid].activated) {
                        $('.memberitem[memberid='+memberid+']').addClass('invisibleMember');
                    }
                }
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('payback', 'Failed to update balances'));
        });
    }

    function addProject(project) {
        payback.projects[project.id] = project;
        payback.members[project.id] = {};

        var name = project.name;
        var projectid = project.id;
        var projectSelected = '';
        if (payback.restoredSelectedProjectId === projectid) {
            projectSelected = ' open';
            payback.currentProjectId = projectid;
            getBills(projectid);
        }
        var addMemberStr = t('payback', 'Add member');
        var guestAccessStr = t('payback', 'Guest access link');
        var renameStr = t('payback', 'Rename');
        var changePwdStr = t('payback', 'Change password');
        var displayStatsStr = t('payback', 'Display statistics');
        var settleStr = t('payback', 'Settle the project');
        var deleteStr = t('payback', 'Delete');
        var deletedStr = t('payback', 'Deleted {name}', {name: name});
        var extProjUrl = OC.generateUrl('/apps/payback/loginproject/'+projectid);
        var shareTitle = t('payback', 'Press enter to validate');
        extProjUrl = window.location.protocol + '//' + window.location.hostname + extProjUrl;
        var li = `
            <li class="projectitem collapsible${projectSelected}" projectid="${projectid}">
                <a class="icon-folder" href="#" title="${projectid}">
                    <span>${name}</span>
                </a>
                <div class="app-navigation-entry-utils">
                    <ul>
                        <li class="app-navigation-entry-utils-counter"><span>${project.members.length}</span></li>
                        <li class="app-navigation-entry-utils-menu-button shareProjectButton">
                            <button class="icon-share"></button>
                        </li>
                        <li class="app-navigation-entry-utils-menu-button projectMenuButton">
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
                <ul class="app-navigation-entry-share">
                    <li class="shareinputli" title="${shareTitle}"><input type="text" class="shareinput"/></li>
                </ul>

                <div class="newmemberdiv">
                    <input class="newmembername" type="text" value=""/>
                    <button class="newmemberbutton icon-add"></button>
                </div>

                <div class="app-navigation-entry-menu">
                    <ul>
                        <li>
                            <a href="#" class="addMember">
                                <span class="icon-add"></span>
                                <span>${addMemberStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="copyExtProjectUrl" title="${extProjUrl}">
                                <span class="icon-clippy"></span>
                                <span>${guestAccessStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="editProjectName">
                                <span class="icon-rename"></span>
                                <span>${renameStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="editProjectPassword">
                                <span class="icon-rename"></span>
                                <span>${changePwdStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="getProjectStats">
                                <span class="icon-category-monitoring"></span>
                                <span>${displayStatsStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="getProjectSettlement">
                                <span class="icon-category-organization"></span>
                                <span>${settleStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="deleteProject">
                                <span class="icon-delete"></span>
                                <span>${deleteStr}</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="app-navigation-entry-deleted">
                    <div class="app-navigation-entry-deleted-description">${deletedStr}</div>
                    <button class="app-navigation-entry-deleted-button icon-history undoDeleteProject" title="Undo"></button>
                </div>
                <ul class="memberlist"></ul>
            </li>`;

        $(li).appendTo('#projectlist');

        if (payback.pageIsPublic) {
            $('.projectitem[projectid='+projectid+'] .shareProjectButton').hide();
        }

        for (var i=0; i < project.members.length; i++) {
            var memberId = project.members[i].id;
            addMember(projectid, project.members[i], project.balance[memberId]);
        }

        if (project.shares) {
            for (var i=0; i < project.shares.length; i++) {
                var userid = project.shares[i].userid;
                var username = project.shares[i].name;
                addUserShare(projectid, userid, username);
            }
        }

        // set selected project
        if (payback.restoredSelectedProjectId === projectid) {
            $('.projectitem').removeClass('selectedproject');
            $('.projectitem[projectid='+projectid+']').addClass('selectedproject');
            $('.app-navigation-entry-utils-counter').removeClass('highlighted');
            $('.projectitem[projectid='+projectid+'] .app-navigation-entry-utils-counter').addClass('highlighted');
        }
    }

    function addMember(projectid, member, balance) {
        // add member to dict
        payback.members[projectid][member.id] = member;

        var invisibleClass = '';
        var balanceStr;
        if (balance > 0) {
            balanceStr = '<b class="balance balancePositive">+'+balance.toFixed(2)+'</b>';
        }
        else if (balance < 0) {
            balanceStr = '<b class="balance balanceNegative">'+balance.toFixed(2)+'</b>';
        }
        else {
            balanceStr = '<b class="balance">'+balance.toFixed(2)+'</b>';
            if (!member.activated) {
                invisibleClass = ' invisibleMember';
            }
        }
        var iconStr, iconToggleStr, toggleStr, imgurl;
        var c = getMemberColor(member.name);
        var rgbC = hslToRgb(c.h/360, c.s/100, c.l/100);
        if (member.activated) {
            iconStr = 'icon-user';
            iconToggleStr = 'icon-delete';
            toggleStr = t('payback', 'Deactivate');
            imgurl = OC.generateUrl('/svg/core/actions/user?color='+rgbC);
        }
        else {
            iconStr = 'icon-disabled-user';
            iconToggleStr = 'icon-history';
            toggleStr = t('payback', 'Reactivate');
            imgurl = OC.generateUrl('/svg/core/actions/disabled-user?color='+rgbC);
        }


        var renameStr = t('payback', 'Rename');
        var changeWeightStr = t('payback', 'Change weight');
        var li = `
            <li memberid="${member.id}" class="memberitem${invisibleClass}">
                <a class="${iconStr}" style="background-image: url(${imgurl})" href="#">
                    <span>
                        <b class="memberName">${member.name}</b> (x<b class="memberWeight">${member.weight}</b>) ${balanceStr}
                    </span>
                </a>
                <div class="app-navigation-entry-utils">
                    <ul>
                        <!--li class="app-navigation-entry-utils-counter">1</li-->
                        <li class="app-navigation-entry-utils-menu-button memberMenuButton">
                            <button></button>
                        </li>
                    </ul>
                </div>
                <div class="app-navigation-entry-menu">
                    <ul>
                        <li>
                            <a href="#" class="renameMember">
                                <span class="icon-rename"></span>
                                <span>${renameStr}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="editWeightMember">
                                <span class="icon-rename"></span>
                                <span>${changeWeightStr}</span>
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
                OC.Notification.showTemporary(t('payback', 'Bill values are not valid'));
            }
        }
    }

    function saveOptionValue(optionValues) {
        if (!payback.pageIsPublic) {
            var req = {
                options: optionValues
            };
            var url = OC.generateUrl('/apps/payback/saveOptionValue');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
            }).fail(function() {
                OC.Notification.showTemporary(
                    t('payback', 'Failed to save option values')
                );
            });
        }
    }

    function restoreOptions() {
        var mom;
        var url = OC.generateUrl('/apps/payback/getOptionsValues');
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
                        payback.restoredSelectedProjectId = optionsValues[k];
                    }
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.Notification.showTemporary(
                t('payback', 'Failed to restore options values')
            );
        });
    }

    function addUserAutocompletion(input) {
        var req = {
        };
        var url = OC.generateUrl('/apps/payback/getUserList');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            payback.userIdName = response.users;
            var nameList = [];
            var name;
            for (var id in response.users) {
                name = response.users[id];
                nameList.push(name);
            }
            input.autocomplete({
                source: nameList
            });
        }).fail(function() {
            OC.Notification.showTemporary(t('payback', 'Failed to get user list'));
        });
    }

    function addUserShareDb(projectid, userid, username) {
        var req = {
            projectid: projectid,
            userid: userid
        };
        var url = OC.generateUrl('/apps/payback/addUserShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            addUserShare(projectid, userid, username);
            var projectname = getProjectName(projectid);
            OC.Notification.showTemporary(t('payback', 'Shared project {pname} with {uname}', {pname: projectname, uname: username}));
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to add user share') + ' ' + response.responseText);
        });
    }

    function addUserShare(projectid, userid, username) {
        var li = '<li userid="'+escapeHTML(userid)+'" username="' + escapeHTML(username) + '">' +
            '<div class="shareLabel">' + t('phonetrack', 'Shared with {u}', {'u': username}) + '</div>' +
            '<div class="icon-delete deleteUserShareButton"></div></li>';
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share').append(li);
        $('.projectitem[projectid="' + projectid + '"] .shareinput').val('');
    }

    function deleteUserShareDb(projectid, userid) {
        var req = {
            projectid: projectid,
            userid: userid
        };
        var url = OC.generateUrl('/apps/payback/deleteUserShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[userid=' + userid + ']');
            li.fadeOut('slow', function() {
                li.remove();
            });
        }).fail(function(response) {
            OC.Notification.showTemporary(t('payback', 'Failed to delete user share') + ' ' + response.responseText);
        });
    }

    $(document).ready(function() {
        payback.pageIsPublic = (document.URL.indexOf('/payback/project') !== -1);
        if ( !payback.pageIsPublic ) {
            restoreOptions();
        }
        else {
            //restoreOptionsFromUrlParams();
            $('#newprojectbutton').hide();
            payback.projectid = $('#projectid').text();
            payback.password = $('#password').text();
            payback.restoredSelectedProjectId = payback.projectid;
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
            //console.log(event.target);
        }

        $('body').on('focus','.shareinput', function(e) {
            $(this).select();
            addUserAutocompletion($(this));
        });

        $('body').on('keyup','.shareinput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $(this).parent().parent().parent().attr('projectid');
                var username = $(this).val();
                var userId = '';
                for (var id in payback.userIdName) {
                    if (username === payback.userIdName[id]) {
                        userId = id;
                        break;
                    }
                }
                addUserShareDb(projectid, userId, username);
            }
        });

        $('body').on('click', '.deleteUserShareButton', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var userid = $(this).parent().attr('userid');
            deleteUserShareDb(projectid, userid);
        });

        $('body').on('click', '.shareProjectButton', function(e) {
            var shareDiv = $(this).parent().parent().parent().find('.app-navigation-entry-share');
            if (shareDiv.is(':visible')) {
                shareDiv.slideUp();
            }
            else {
                shareDiv.slideDown();
                var defaultShareText = t('payback', 'userName');
                $(this).parent().parent().parent().find('.shareinput').val(defaultShareText).focus().select();
            }
        });

        $('body').on('click', '.projectMenuButton, .memberMenuButton', function(e) {
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
                payback.currentProjectId = projectid;
                $('.projectitem').removeClass('selectedproject');
                $('.projectitem[projectid='+projectid+']').addClass('selectedproject');
                $('.app-navigation-entry-utils-counter').removeClass('highlighted');
                $('.projectitem[projectid='+projectid+'] .app-navigation-entry-utils-counter').addClass('highlighted');

                $('#billdetail').html('');
                getBills(projectid);
            }
        });

        $('body').on('click', '.projectitem', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).hasClass('projectitem')) {
                var wasOpen = $(this).hasClass('open');
                $('.projectitem.open').removeClass('open');
                if (!wasOpen) {
                    $(this).addClass('open');
                    var projectid = $(this).attr('projectid');

                    saveOptionValue({selectedProject: projectid});
                    payback.currentProjectId = projectid;
                    $('.projectitem').removeClass('selectedproject');
                    $('.projectitem[projectid='+projectid+']').addClass('selectedproject');
                    $('.app-navigation-entry-utils-counter').removeClass('highlighted');
                    $('.projectitem[projectid='+projectid+'] .app-navigation-entry-utils-counter').addClass('highlighted');

                    $('#billdetail').html('');
                    getBills(projectid);
                }
            }
        });

        $('#newprojectbutton').click(function() {
            var div = $('#newprojectdiv');
            if (div.is(':visible')) {
                $(this).removeClass('icon-triangle-s').addClass('icon-triangle-e');
                div.slideUp('slow', function() {
                    $('#newBillButton').fadeIn();
                });
            }
            else {
                $(this).removeClass('icon-triangle-e').addClass('icon-triangle-s');
                div.slideDown('slow', function() {
                    $('#newBillButton').fadeOut();
                    $('#projectidinput').focus().select();
                });
            }
        });

        $('#projectnameinput, #projectidinput, #projectpasswordinput').on('keyup', function(e) {
            if (e.key === 'Enter') {
                var name = $('#projectnameinput').val();
                var id = $('#projectidinput').val();
                var password = $('#projectpasswordinput').val();
                if (name && id && password) {
                    createProject(id, name, password);
                }
                else {
                    OC.Notification.showTemporary(t('payback', 'Invalid values'));
                }
            }
        });

        $('#createproject').click(function() {
            var name = $('#projectnameinput').val();
            var id = $('#projectidinput').val();
            var password = $('#projectpasswordinput').val();
            if (name && id && password) {
                createProject(id, name, password);
            }
            else {
                OC.Notification.showTemporary(t('payback', 'Invalid values'));
            }
        });

        $('body').on('click', '.deleteProject', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            $(this).parent().parent().parent().parent().addClass('deleted');
            payback.projectDeletionTimer[projectid] = new Timer(function() {
                deleteProject(projectid);
            }, 7000);
        });

        $('body').on('click', '.undoDeleteProject', function(e) {
            var projectid = $(this).parent().parent().attr('projectid');
            $(this).parent().parent().removeClass('deleted');
            payback.projectDeletionTimer[projectid].pause();
            delete payback.projectDeletionTimer[projectid];
        });

        $('body').on('click', '.addMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            var name = $('.projectitem[projectid='+projectid+'] > a > span').text();

            var newmemberdiv = $('.projectitem[projectid='+projectid+'] .newmemberdiv');
            newmemberdiv.show().attr('style', 'display: inline-flex;');
            var defaultMemberName = t('payback', 'newMemberName');
            newmemberdiv.find('.newmembername').val(defaultMemberName).focus().select();
        });

        $('body').on('click', '.newmemberbutton', function(e) {
            var projectid = $(this).parent().parent().attr('projectid');
            var name = $(this).parent().find('input').val();
            if (projectid && name) {
                createMember(projectid, name);
            }
            else {
                OC.Notification.showTemporary(t('payback', 'Invalid values'));
            }
        });

        $('body').on('keyup', '.newmembername', function(e) {
            if (e.key === 'Enter') {
                var name = $(this).val();
                var projectid = $(this).parent().parent().attr('projectid');
                if (projectid && name) {
                    createMember(projectid, name);
                }
                else {
                    OC.Notification.showTemporary(t('payback', 'Invalid values'));
                }
            }
        });

        $('body').on('click', '.renameMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var name = $(this).parent().parent().parent().parent().find('a > span > b.memberName').text();
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(name).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            payback.memberEditionMode = MEMBER_NAME_EDITION;
        });

        $('body').on('click', '.editWeightMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var weight = $(this).parent().parent().parent().parent().find('a > span > b.memberWeight').text();
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(weight).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            payback.memberEditionMode = MEMBER_WEIGHT_EDITION;
        });

        $('body').on('click', '.editMemberClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editMemberInput', function(e) {
            if (e.key === 'Enter') {
                var memberid = $(this).parent().parent().parent().attr('memberid');
                var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
                if (payback.memberEditionMode === MEMBER_NAME_EDITION) {
                    var newName = $(this).val();
                    editMember(projectid, memberid, newName, null, null);
                }
                else if (payback.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                    var newWeight = $(this).val();
                    var newName = $(this).parent().parent().parent().find('b.memberName').text();
                    editMember(projectid, memberid, newName, newWeight, null);
                }
            }
        });

        $('body').on('click', '.editMemberOk', function(e) {
            var memberid = $(this).parent().parent().parent().attr('memberid');
            var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            if (payback.memberEditionMode === MEMBER_NAME_EDITION) {
                var newName = $(this).parent().find('.editMemberInput').val();
                editMember(projectid, memberid, newName, null, null);
            }
            else if (payback.memberEditionMode === MEMBER_WEIGHT_EDITION) {
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
            payback.projectEditionMode = PROJECT_NAME_EDITION;
        });

        $('body').on('click', '.editProjectPassword', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            $(this).parent().parent().parent().parent().find('.editProjectInput').attr('type', 'password').val('').focus();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            payback.projectEditionMode = PROJECT_PASSWORD_EDITION;
        });

        $('body').on('click', '.editProjectClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editProjectInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $(this).parent().parent().parent().attr('projectid');
                if (payback.projectEditionMode === PROJECT_NAME_EDITION) {
                    var newName = $(this).val();
                    editProject(projectid, newName, null, null);
                }
                else if (payback.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                    var newPassword = $(this).val();
                    var newName = $(this).parent().parent().parent().find('>a span').text();
                    editProject(projectid, newName, null, newPassword);
                }
            }
        });

        $('body').on('click', '.editProjectOk', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            if (payback.projectEditionMode === PROJECT_NAME_EDITION) {
                var newName = $(this).parent().find('.editProjectInput').val();
                editProject(projectid, newName, null, null);
            }
            else if (payback.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                var newPassword = $(this).parent().find('.editProjectInput').val();
                var newName = $(this).parent().parent().parent().find('>a span').text();
                editProject(projectid, newName, null, newPassword);
            }
        });

        $('body').on('click', '.billitem', function(e) {
            if (!$(e.target).hasClass('deleteBillIcon') && !$(e.target).hasClass('undoDeleteBill')) {
                var billid = parseInt($(this).attr('billid'));
                var projectid = $(this).attr('projectid');
                displayBill(projectid, billid);
            }
        });

        $('body').on('change', '#billdetail input, #billdetail select', function(e) {
            onBillEdited();
        });

        $('body').on('click', '#owerAll', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().find('.bill-title').attr('projectid');
            for (var memberid in payback.members[projectid]) {
                if (payback.members[projectid][memberid].activated) {
                    $('.bill-owers input[owerid='+memberid+']').prop('checked', true);
                }
            }
            //$('.owerEntry input').prop('checked', true);
            onBillEdited();
        });

        $('body').on('click', '#owerNone', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().find('.bill-title').attr('projectid');
            for (var memberid in payback.members[projectid]) {
                if (payback.members[projectid][memberid].activated) {
                    $('.bill-owers input[owerid='+memberid+']').prop('checked', false);
                }
            }
            //$('.owerEntry input').prop('checked', false);
            onBillEdited();
        });

        $('body').on('click', '.undoDeleteBill', function(e) {
            var billid = $(this).parent().attr('billid');
            payback.billDeletionTimer[billid].pause();
            delete payback.billDeletionTimer[billid];
            $(this).parent().find('.deleteBillIcon').show();
            $(this).parent().removeClass('deleted');
            $(this).hide();
        });

        $('body').on('click', '.deleteBillIcon', function(e) {
            var billid = $(this).parent().attr('billid');
            if (billid !== '0') {
                var projectid = $(this).parent().attr('projectid');
                $(this).parent().find('.undoDeleteBill').show();
                $(this).parent().addClass('deleted');
                $(this).hide();
                payback.billDeletionTimer[billid] = new Timer(function() {
                    deleteBill(projectid, billid);
                }, 7000);
            }
            else {
                if ($('.bill-title').length > 0 && $('.bill-title').attr('billid') === billid) {
                    $('#billdetail').html('');
                }
                $(this).parent().fadeOut('slow', function() {
                    $(this).remove();
                });
            }
        });

        $('body').on('click', '#newBillButton', function(e) {
            var projectid = payback.currentProjectId;
            var activatedMembers = [];
            for (var mid in payback.members[projectid]) {
                if (payback.members[projectid][mid].activated) {
                    activatedMembers.push(mid);
                }
            }
            if (activatedMembers.length > 1) {
                if (payback.currentProjectId !== null && $('.billitem[billid=0]').length === 0) {
                    var bill = {
                        id: 0,
                        what: t('payback', 'New Bill'),
                        date: moment().format('YYYY-MM-DD'),
                        amount: 0.0,
                        payer_id: 0,
                        owers: []
                    };
                    addBill(projectid, bill)
                    displayBill(projectid, bill.id);
                }
            }
            else {
                OC.Notification.showTemporary(t('payback', '2 active members are required to create a bill'));
            }
        });

        $('body').on('focus', '.input-bill-what, .input-bill-amount, #projectidinput, #projectnameinput, #projectpasswordinput', function(e) {
            $(this).select();
        });

        $('#statsButton').click(function() {
            if (payback.currentProjectId !== null) {
                getProjectStatistics(payback.currentProjectId);
            }
        });

        $('#settleButton').click(function() {
            if (payback.currentProjectId !== null) {
                getProjectSettlement(payback.currentProjectId);
            }
        });

        $('body').on('click', '.getProjectStats', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectStatistics(projectid);
        });

        $('body').on('click', '.getProjectSettlement', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectSettlement(projectid);
        });

        $('body').on('click', '.copyExtProjectUrl', function() {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            var guestLink = OC.generateUrl(`/apps/payback/loginproject/${projectid}`);
            guestLink = window.location.protocol + '//' + window.location.hostname + guestLink;
            var dummy = $('<input id="dummycopy">').val(guestLink).appendTo('body').select()
            document.execCommand('copy');
            $('#dummycopy').remove();
            OC.Notification.showTemporary(t('payback', 'Guest link for \'{pid}\' copied to clipboard', {pid: projectid}));
        });

        var guestLink = OC.generateUrl('/apps/payback/login');
        guestLink = window.location.protocol + '//' + window.location.hostname + guestLink;
        $('#generalGuestLinkButton').attr('title', guestLink);

        $('body').on('click', '#generalGuestLinkButton', function() {
            var guestLink = OC.generateUrl('/apps/payback/login');
            guestLink = window.location.protocol + '//' + window.location.hostname + guestLink;
            var dummy = $('<input id="dummycopy">').val(guestLink).appendTo('body').select()
            document.execCommand('copy');
            $('#dummycopy').remove();
            OC.Notification.showTemporary(t('payback', 'Guest link copied to clipboard'));
        });

        $('body').on('click', '#app-details-toggle', function() {
            $('.app-content-list').removeClass('showdetails');
        });

        // last thing to do : get the projects
        getProjects();
    }

})(jQuery, OC);
