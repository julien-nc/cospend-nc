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
(function ($, OC) {
    'use strict';

    //////////////// VAR DEFINITION /////////////////////
    var MEMBER_NAME_EDITION = 1;
    var MEMBER_WEIGHT_EDITION = 2;

    var PROJECT_NAME_EDITION = 1;
    var PROJECT_PASSWORD_EDITION = 2;

    var cospend = {
        restoredSelectedProjectId: null,
        memberEditionMode: null,
        projectEditionMode: null,
        projectDeletionTimer: {},
        billDeletionTimer: {},
        currencyDeletionTimer: {},
        categoryDeletionTimer: {},
        // indexed by projectid, then by billid
        bills: {},
        // indexed by projectid, then by memberid
        members: {},
        projects: {},
        currentProjectId: null,
    };

    cospend.categories = {
        '-1': {
            name: t('cospend', 'Grocery'),
            icon: '🛒',
            color: '#ffaa00'
        },
        '-2': {
            name: t('cospend', 'Bar/Party'),
            icon: '🎉',
            color: '#aa55ff'
        },
        '-3': {
            name: t('cospend', 'Rent'),
            icon: '🏠',
            color: '#da8733'
        },
        '-4': {
            name: t('cospend', 'Bill'),
            icon: '🌩',
            color: '#4aa6b0'
        },
        '-5': {
            name: t('cospend', 'Excursion/Culture'),
            icon: '🚸',
            color: '#0055ff'
        },
        '-6': {
            name: t('cospend', 'Health'),
            icon: '💚',
            color: '#bf090c'
        },
        '-10': {
            name: t('cospend', 'Shopping'),
            icon: '🛍',
            color: '#e167d1'
        },
        '-11': {
            name: t('cospend', 'Reimbursement'),
            icon: '💰',
            color: '#e1d85a'
        },
        '-12': {
            name: t('cospend', 'Restaurant'),
            icon: '🍴',
            color: '#d0d5e1'
        },
        '-13': {
            name: t('cospend', 'Accommodation'),
            icon: '🛌',
            color: '#5de1a3'
        },
        '-14': {
            name: t('cospend', 'Transport'),
            icon: '🚌',
            color: '#6f2ee1'
        },
        '-15': {
            name: t('cospend', 'Sport'),
            icon: '🎾',
            color: '#69e177'
        },
    };

    cospend.paymentModes = {
        c: {
            name: t('cospend', 'Credit card'),
            icon: '💳'
        },
        b: {
            name: t('cospend', 'Cash'),
            icon: '💵'
        },
        f: {
            name: t('cospend', 'Check'),
            icon: '🎫'
        },
        t: {
            name: t('cospend', 'Transfer'),
            icon: '⇄'
        },
    };

    //////////////// UTILS /////////////////////

    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function componentToHex(c) {
        var hex = c.toString(16);
        return hex.length == 1 ? "0" + hex : hex;
    }

    function rgbObjToHex(o) {
        return rgbToHex(o.r, o.g, o.b);
    }

    function rgbToHex(r, g, b) {
        return "#" + componentToHex(parseInt(r)) + componentToHex(parseInt(g)) + componentToHex(parseInt(b));
    }

    function hexToDarkerHex(hex) {
        var rgb = hexToRgb(hex);
        while (getColorBrightness(rgb) > 100) {
            if (rgb.r > 0) rgb.r--;
            if (rgb.g > 0) rgb.g--;
            if (rgb.b > 0) rgb.b--;
        }
        return rgbToHex(rgb.r, rgb.g, rgb.b);
    }

    // this formula was found here : https://stackoverflow.com/a/596243/7692836
    function getColorBrightness(rgb) {
        return 0.2126*rgb.r + 0.7152*rgb.g + 0.0722*rgb.b;
    }

    function Timer(callback, mydelay) {
        var timerId, start, remaining = mydelay;

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

    var mytimer = 0;
    function delay(callback, ms) {
        return function() {
            var context = this, args = arguments;
            clearTimeout(mytimer);
            mytimer = setTimeout(function () {
                callback.apply(context, args);
            }, ms || 0);
        };
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
                    if (easing !== 1)
                        return;
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

    function addExtProject(ncurl, id, password) {
        $('#addextproject').addClass('icon-loading-small');
        var req = {
            id: id,
            url: ncurl,
            password: password
        };
        var url = OC.generateUrl('/apps/cospend/addExternalProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // get project info
            getExternalProject(ncurl, id, password, true);
        }).always(function() {
            $('#addextproject').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(t('cospend', 'Failed to add external project') + ': ' + response.responseJSON.message);
            $('#addextproject').removeClass('icon-loading-small');
        });
    }

    function getExternalProject(ncurl, id, password, select=false) {
        var req = {
        };
        var url = ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + password;
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            response.external = true;
            response.ncurl = ncurl;
            response.password = password;
            response.id = id + '@' + ncurl;
            addProject(response);

            var div = $('#addextprojectdiv');
            $('#addextprojectbutton').removeClass('icon-triangle-s').addClass('icon-triangle-e');
            div.slideUp('normal', function() {
                $('#newBillButton').fadeIn();
                $('#newprojectbutton').fadeIn();
            });

            // select created project
            if (select) {
                selectProject($('.projectitem[projectid="'+response.id+'"]'));
            }
        }).always(function() {
            $('#addextproject').removeClass('icon-loading-small');
        }).fail(function(response) {
            if (select) {
                deleteExternalProject(id + '@' + ncurl);
            }
            OC.Notification.showTemporary(
                t('cospend', 'Failed to get external project') +
                ': ' + (response.responseJSON.message || '')
            );
        });
    }

    function createProject(id, name, password) {
        $('#createproject').addClass('icon-loading-small');
        var req = {
            id: id,
            name: name,
            password: password
        };
        var url = OC.generateUrl('/apps/cospend/createProject');
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
                balance: {},
                external: false,
                guestpermissions: 'edc',
                categories: {},
                currencies: []
            });

            var div = $('#newprojectdiv');
            $('#newprojectbutton').removeClass('icon-triangle-s').addClass('icon-triangle-e');
            div.slideUp('normal', function() {
                $('#newBillButton').fadeIn();
                $('#addextprojectbutton').fadeIn();
            });
            // select created project
            selectProject($('.projectitem[projectid="'+id+'"]'));
        }).always(function() {
            $('#createproject').removeClass('icon-loading-small');
        }).fail(function(response) {
            console.log(response);
            OC.Notification.showTemporary(t('cospend', 'Failed to create project') + ': ' + response.responseJSON.message);
        });
    }

    function createMember(projectid, name) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        var req = {
            name: name
        };
        var url;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/members';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/addMember');
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/members');
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // add member to UI
            addMember(projectid, response, 0);
            // fold new member form
            $('.newmemberdiv').slideUp();
            updateNumberOfMember(projectid);
            $('#billdetail').html('');
            OC.Notification.showTemporary(t('cospend', 'Created member {name}', {name: name}));
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add member') +
                ': ' + response.responseText
            );
        });
    }

    function askChangeMemberColor(projectid, memberid) {
        cospend.changingColorProjectId = projectid;
        cospend.changingColorMemberId = memberid;
        var currentColor = '#'+cospend.members[projectid][memberid].color;
        $('#membercolorinput').val(currentColor);
        $('#membercolorinput').click();
    }

    function okColor() {
        var color = $('#membercolorinput').val();
        var projectid = cospend.changingColorProjectId;
        var memberid = cospend.changingColorMemberId;
        editMember(
            projectid, memberid,
            cospend.members[projectid][memberid].name,
            cospend.members[projectid][memberid].weight,
            cospend.members[projectid][memberid].activated,
            color.replace('#', '')
        );
    }

    function editMember(projectid, memberid, newName, newWeight, newActivated, color=null) {
        $('.projectitem[projectid="'+projectid+'"] ul.memberlist > li[memberid='+memberid+']')
            .addClass('icon-loading-small')
            .removeClass('editing');
        var req = {
            name: newName,
            weight: newWeight,
            activated: newActivated
        };
        if (color) {
            req.color = color;
        }
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/members/' + memberid;
                type = 'PUT';
            }
            else {
                req.projectid = projectid;
                req.memberid = memberid;
                url = OC.generateUrl('/apps/cospend/editMember');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/members/'+memberid);
            type = 'PUT';
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var memberLine = $('.projectitem[projectid="'+projectid+'"] ul.memberlist > li[memberid='+memberid+']');
            // update member values
            cospend.members[projectid][memberid].color = rgbObjToHex(response.color).replace('#', '');
            if (newWeight) {
                cospend.members[projectid][memberid].weight = newWeight;
                updateProjectBalances(projectid);
            }
            if (newName) {
                var weight = parseFloat(cospend.members[projectid][memberid].weight);
                memberLine.find('b.memberName').text(
                    newName + ((weight !== 1.0) ? (' (x'+cospend.members[projectid][memberid].weight+')') : '')
                );
                cospend.members[projectid][memberid].name = newName;
            }
            // update title
            memberLine.find('b.memberName').attr('title', newName+' (x'+cospend.members[projectid][memberid].weight+')');
            if (newActivated !== null && newActivated === false) {
                memberLine.find('.toggleMember span').first().removeClass('icon-delete').addClass('icon-history');
                memberLine.find('.toggleMember span').eq(1).text(t('cospend', 'Reactivate'));
                cospend.members[projectid][memberid].activated = newActivated;
            }
            else if (newActivated !== null && newActivated === true) {
                memberLine.find('.toggleMember span').first().removeClass('icon-history').addClass('icon-delete');
                memberLine.find('.toggleMember span').eq(1).text(t('cospend', 'Deactivate'));
                cospend.members[projectid][memberid].activated = newActivated;
            }
            // update icon
            var imgurl = OC.generateUrl('/apps/cospend/getAvatar?color=' +
                        cospend.members[projectid][memberid].color +
                        '&name='+encodeURIComponent(response.name));
            if (cospend.members[projectid][memberid].activated) {
                memberLine.find('.memberAvatar').removeClass('memberAvatarDisabled');
            }
            else {
                memberLine.find('.memberAvatar').addClass('memberAvatarDisabled');
            }
            memberLine.find('.memberAvatar img').attr('src', imgurl);

            OC.Notification.showTemporary(t('cospend', 'Member saved'));
            // get bills again to refresh names
            getBills(projectid);
            // reset bill edition
            $('#billdetail').html('');
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"] ul.memberlist > li[memberid='+memberid+']').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to save member') +
                ': ' + response.responseText
            );
        });
    }

    function createBill(projectid, what, amount, payer_id, date, owerIds, repeat,
                        custom=false, paymentmode=null, categoryid=null, repeatallactive=0, repeatuntil=null) {
        $('.loading-bill').addClass('icon-loading-small');
        var req = {
            what: what,
            date: date,
            payer: payer_id,
            payed_for: owerIds.join(','),
            amount: amount,
            repeat: repeat,
            repeatallactive: repeatallactive,
            repeatuntil: repeatuntil,
            paymentmode: paymentmode,
            categoryid: categoryid
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/bills';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/addBill');
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/bills');
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var billid = response;
            // update dict
            cospend.bills[projectid][billid] = {
                id: billid,
                what: what,
                date: date,
                amount: amount,
                payer_id: payer_id,
                repeat: repeat,
                repeatallactive: repeatallactive,
                repeatuntil: repeatuntil,
                paymentmode: paymentmode,
                categoryid: categoryid
            };
            var billOwers = [];
            for (var i=0; i < owerIds.length; i++) {
                billOwers.push({id: owerIds[i]});
            }
            cospend.bills[projectid][billid].owers = billOwers;

            // update ui
            var bill = cospend.bills[projectid][billid];
            if (!custom) {
                updateBillItem(projectid, 0, bill);
                updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                                    paymentmode, categoryid, repeatallactive, repeatuntil);
            }
            else {
                addBill(projectid, bill);
            }

            updateProjectBalances(projectid);

            OC.Notification.showTemporary(t('cospend', 'Bill created'));
        }).always(function() {
            $('.loading-bill').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to create bill') +
                ': ' + response.responseText
            );
        });
    }

    function saveBill(projectid, billid, what, amount, payer_id, date, owerIds, repeat,
                      paymentmode=null, categoryid=null, repeatallactive=null, repeatuntil=null) {
        $('.loading-bill').addClass('icon-loading-small');
        var req = {
            what: what,
            date: date,
            payer: payer_id,
            payed_for: owerIds.join(','),
            amount: amount,
            repeat: repeat,
            repeatallactive: repeatallactive,
            repeatuntil: repeatuntil,
            paymentmode: paymentmode,
            categoryid: categoryid
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/bills/' + billid;
                type = 'PUT';
            }
            else {
                req.projectid = projectid;
                req.billid = billid;
                type = 'POST';
                url = OC.generateUrl('/apps/cospend/editBill');
            }
        }
        else {
            type = 'PUT';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/bills/'+billid);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // update dict
            cospend.bills[projectid][billid].what = what;
            cospend.bills[projectid][billid].date = date;
            cospend.bills[projectid][billid].amount = amount;
            cospend.bills[projectid][billid].payer_id = payer_id;
            cospend.bills[projectid][billid].repeat = repeat;
            cospend.bills[projectid][billid].repeatallactive = repeatallactive;
            cospend.bills[projectid][billid].repeatuntil = repeatuntil;
            cospend.bills[projectid][billid].paymentmode = paymentmode;
            cospend.bills[projectid][billid].categoryid = categoryid;
            var billOwers = [];
            for (var i=0; i < owerIds.length; i++) {
                billOwers.push({id: owerIds[i]});
            }
            cospend.bills[projectid][billid].owers = billOwers;

            // update ui
            var bill = cospend.bills[projectid][billid];
            updateBillItem(projectid, billid, bill);
            var displayedBillTitle = $('#billdetail .bill-title');
            if (parseInt(displayedBillTitle.attr('billid')) === parseInt(billid) &&
                displayedBillTitle.attr('projectid') === projectid) {
                updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                                    paymentmode, categoryid, repeatallactive, repeatuntil);
            }

            updateProjectBalances(projectid);

            OC.Notification.showTemporary(t('cospend', 'Bill saved'));
        }).always(function() {
            $('.loading-bill').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to save bill') +
                ' ' + (response.responseJSON.message || response.responseJSON)
            );
        });
    }

    function updateBillItem(projectid, billid, bill) {
        var billItem = $('.billitem[billid='+billid+']');
        var billSelected = billItem.hasClass('selectedbill');
        var selectedClass = '';
        if (billSelected) {
            selectedClass = ' selectedbill';
        }

        var owerNames = '';
        var ower, i;
        for (i=0; i < bill.owers.length; i++) {
            ower = bill.owers[i];
            owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
        }
        owerNames = owerNames.replace(/, $/, '');
        var memberName = getMemberName(projectid, bill.payer_id);

        var links = bill.what.match(/https?:\/\/[^\s]+/gi) || [];
        var formattedLinks = '';
        var linkChars = '';
        for (i=0; i < links.length; i++) {
            formattedLinks = formattedLinks + '<a href="'+links[i]+'" target="blank">['+t('cospend', 'link')+']</a> ';
            linkChars = linkChars + '  🔗';
        }
        var repeatChar = '';
        if (bill.repeat !== 'n') {
            repeatChar = ' ⏩';
        }
        var paymentmodeChar = '';
        // c b f card, cash, check
        if (cospend.paymentModes.hasOwnProperty(bill.paymentmode)) {
            paymentmodeChar = cospend.paymentModes[bill.paymentmode].icon + ' ';
        }
        var categoryChar = '';
        if (cospend.categories.hasOwnProperty(bill.categoryid)) {
            categoryChar = cospend.categories[bill.categoryid].icon + ' ';
        }
        if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
            categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
        }
        var whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars + repeatChar;

        var title = whatFormatted + '\n' + bill.amount.toFixed(2) + '\n' +
            bill.date + '\n' + memberName + ' -> ' + owerNames;
        var imgurl = OC.generateUrl('/apps/cospend/getAvatar?color=' +
                    cospend.members[projectid][bill.payer_id].color +
                    '&name='+encodeURIComponent(memberName));
        var item = '<a href="#" class="app-content-list-item billitem'+selectedClass+'" billid="'+bill.id+'" projectid="'+projectid+'" title="'+title+'">' +
            '<div class="app-content-list-item-icon" style="background-image: url('+imgurl+');"> ' +
            '   <div class="billItemDisabledMask'+(cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled')+'"></div>' +
            '</div>' +
            '<div class="app-content-list-item-line-one">'+whatFormatted+'</div>' +
            '<div class="app-content-list-item-line-two">'+bill.amount.toFixed(2)+' ('+memberName+' → '+owerNames+')</div>' +
            '<span class="app-content-list-item-details">'+bill.date+'</span>' +
            '<div class="icon-delete deleteBillIcon"></div>' +
            '<div class="icon-history undoDeleteBill" style="'+undoDeleteBillStyle+'" title="Undo"></div>' +
            '</a>';
        billItem.replaceWith(item);
    }

    function deleteExternalProject(projectid, updateList=false) {
        var id = projectid.split('@')[0];
        var ncurl = projectid.split('@')[1];

        var req = {
            projectid: id,
            ncurl: ncurl
        };
        var url, type;
        var project = cospend.projects[projectid];
        type = 'POST';
        url = OC.generateUrl('/apps/cospend/deleteExternalProject');
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if (updateList) {
                $('.projectitem[projectid="'+projectid+'"]').fadeOut('normal', function() {
                    $(this).remove();
                });
                if (cospend.currentProjectId === projectid) {
                    $('#bill-list').html('');
                    $('#billdetail').html('');
                }
            }
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete external project') +
                ' ' + response.responseText
            );
        });
    }

    function editExternalProject(projectid, newPassword) {
        var id = projectid.split('@')[0];
        var ncurl = projectid.split('@')[1];

        var req = {
            projectid: id,
            ncurl: ncurl,
            password: newPassword
        };
        var url, type;
        var project = cospend.projects[projectid];
        type = 'POST';
        url = OC.generateUrl('/apps/cospend/editExternalProject');
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to save external project') +
                ' ' + response.responseText
            );
        });
    }

    function editProject(projectid, newName, newEmail, newPassword, newAutoexport=null, newcurrencyname=null) {
        var req = {
            name: newName,
            contact_email: newEmail,
            password: newPassword,
            autoexport: newAutoexport,
            currencyname: newcurrencyname
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password;
                type = 'PUT';
            }
            else {
                req.projectid = projectid;
                type = 'POST';
                url = OC.generateUrl('/apps/cospend/editProject');
            }
        }
        else {
            type = 'PUT';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // we also need to edit the external project on our NC instance
            if (project.external && newPassword) {
                editExternalProject(projectid, newPassword);
            }
            var projectLine = $('.projectitem[projectid="'+projectid+'"]');
            // update project values
            if (newName) {
                var displayedName = escapeHTML(newName);
                if (project.external) {
                    displayedName = '<span class="icon-external" style="display: inline-grid; margin-bottom: -3px;"></span> ' + displayedName;
                }
                projectLine.find('>a span').html(displayedName);
                cospend.projects[projectid].name = newName;
            }
            if (newPassword) {
                if (cospend.pageIsPublic) {
                    cospend.password = newPassword;
                }
                else {
                    cospend.projects[projectid].password = newPassword;
                }
            }
            if (newcurrencyname !== null) {
                project.currencyname = newcurrencyname || null;
            }
            // update deleted text
            var projectName = cospend.projects[projectid].name;
            projectLine.find('.app-navigation-entry-deleted-description').text(
                t('cospend', 'Deleted {name}', {name: projectName})
            );
            // remove editing mode
            projectLine.removeClass('editing');
            if (newcurrencyname === null) {
                // reset bill edition
                $('#billdetail').html('');
            }
            else {
                $('#main-currency-label-label').text(newcurrencyname || t('cospend', 'None'));
                $('#main-currency-label').show();
                $('#main-currency-edit').hide();
            }
            OC.Notification.showTemporary(t('cospend', 'Project saved'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to edit project') +
                ': ' + (response.responseJSON.message || response.responseJSON.name || response.responseJSON.contact_email)
            );
        });
    }

    function updateNumberOfMember(projectid) {
        var nbMembers = $('li.projectitem[projectid="'+projectid+'"] ul.memberlist > li').length;
        $('li.projectitem[projectid="'+projectid+'"] .app-navigation-entry-utils-counter span').text(nbMembers);
    }

    function deleteProject(projectid) {
        var req = {
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password;
                type = 'DELETE';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/deleteProject');
                type = 'POST';
            }
        }
        else {
            type = 'DELETE';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if (project.external) {
                deleteExternalProject(projectid);
            }
            $('.projectitem[projectid="'+projectid+'"]').fadeOut('normal', function() {
                $(this).remove();
            });
            if (cospend.currentProjectId === projectid) {
                $('#bill-list').html('');
                $('#billdetail').html('');
            }
            if (cospend.pageIsPublic) {
                var redirectUrl = OC.generateUrl('/apps/cospend/login');
                window.location.replace(redirectUrl);
            }
            OC.Notification.showTemporary(t('cospend', 'Deleted project {id}', {id: projectid}));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete project') +
                ': ' + response.responseText
            );
        });
    }

    function deleteBill(projectid, billid) {
        var req = {
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/bills/' + billid;
                type = 'DELETE';
            }
            else {
                req.projectid = projectid;
                req.billid = billid;
                type = 'POST';
                url = OC.generateUrl('/apps/cospend/deleteBill');
            }
        }
        else {
            type = 'DELETE';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/bills/'+billid);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            // if the deleted bill was displayed in details, empty detail
            if ($('#billdetail .bill-title').length > 0 && $('#billdetail .bill-title').attr('billid') === billid) {
                $('#billdetail').html('');
            }
            $('.billitem[billid='+billid+']').fadeOut('normal', function() {
                $(this).remove();
                if ($('.billitem').length === 0) {
                    $('#bill-list').html('<h2 class="nobill">'+t('cospend', 'No bill yet')+'</h2>');
                }
            });
            delete cospend.bills[projectid][billid];
            updateProjectBalances(projectid);
            OC.Notification.showTemporary(t('cospend', 'Deleted bill'));
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete bill') +
                ': ' + response.responseJSON.message
            );
            var deleteBillIcon = $('.billitem[billid='+billid+'] .deleteBillIcon');
            deleteBillIcon.parent().find('.undoDeleteBill').hide();
            deleteBillIcon.parent().removeClass('deleted');
            deleteBillIcon.show();
        });
    }

    function getProjects() {
        var req = {
        };
        var url;
        var type;
        if (!cospend.pageIsPublic) {
            url = OC.generateUrl('/apps/cospend/getProjects');
            type = 'POST';
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password);
            type = 'GET';
        }
        cospend.currentGetProjectsAjax = $.ajax({
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
            if (!cospend.pageIsPublic) {
                $('.projectitem').remove();
                $('#bill-list').html('');
                cospend.bills = {};
                cospend.members = {};
                cospend.projects = {};
                for (var i = 0; i < response.length; i++) {
                    // get project info if it's external
                    if (response[i].external) {
                        getExternalProject(response[i].ncurl, response[i].id, response[i].password);
                    }
                    else {
                        response[i].external = false;
                        addProject(response[i]);
                    }
                }
            }
            else {
                response.external = false;
                addProject(response);
                $('.projectitem').addClass('open');
                cospend.currentProjectId = cospend.projectid;
                getBills(cospend.projectid);
            }
        }).always(function() {
            cospend.currentGetProjectsAjax = null;
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to get projects'));
        });
    }

    function getProjectCurrencies(projectid) {
        $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
        var req = {
        };
        var url;
        var type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password;
                type = 'GET';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/getProjectInfo');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password);
            type = 'GET';
        }
        cospend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if (cospend.currentProjectId !== projectid) {
                selectProject($('.projectitem[projectid="'+projectid+'"]'));
            }
            displayCurrencies(projectid, response);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to get project currencies'));
            $('#billdetail').html('');
        });
    }

    function getProjectCategories(projectid) {
        $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
        var req = {
        };
        var url;
        var type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password;
                type = 'GET';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/getProjectInfo');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password);
            type = 'GET';
        }
        cospend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if (cospend.currentProjectId !== projectid) {
                selectProject($('.projectitem[projectid="'+projectid+'"]'));
            }
            displayCategories(projectid, response);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to get project categories'));
            $('#billdetail').html('');
        });
    }

    function getProjectStatistics(projectid, dateMin=null, dateMax=null, paymentMode=null, category=null,
                                  amountMin=null, amountMax=null, showDisabled=true, currencyId=null) {
        $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
        var req = {
            dateMin: dateMin,
            dateMax: dateMax,
            paymentMode: paymentMode,
            category: category,
            amountMin: amountMin,
            amountMax: amountMax,
            showDisabled: showDisabled ? '1' : '0',
            currencyId: currencyId
        };
        var url;
        var type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/statistics';
                type = 'GET';
            }
            else {
                req.projectid = projectid;
                type = 'POST';
                url = OC.generateUrl('/apps/cospend/getStatistics');
            }
        }
        else {
            type = 'GET';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/statistics');
        }
        cospend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if (cospend.currentProjectId !== projectid) {
                selectProject($('.projectitem[projectid="'+projectid+'"]'));
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
            OC.Notification.showTemporary(t('cospend', 'Failed to get statistics'));
            $('#billdetail').html('');
        });
    }

    function getProjectSettlement(projectid) {
        $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
        var req = {
        };
        var url;
        var type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/settle';
                type = 'GET';
            }
            else {
                req.projectid = projectid;
                type = 'POST';
                url = OC.generateUrl('/apps/cospend/getSettlement');
            }
        }
        else {
            type = 'GET';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/settle');
        }
        cospend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            if (cospend.currentProjectId !== projectid) {
                selectProject($('.projectitem[projectid="'+projectid+'"]'));
            }
            displaySettlement(projectid, response);
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to get settlement'));
            $('#billdetail').html('');
        });
    }

    function displaySettlement(projectid, transactionList) {
        // unselect bill
        $('.billitem').removeClass('selectedbill');

        var project = cospend.projects[projectid];
        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('cospend', 'Settlement of project {name}', {name: projectName});
        var fromStr = t('cospend', 'Who pays?');
        var toStr = t('cospend', 'To whom?');
        var howMuchStr = t('cospend', 'How much?');
        var exportStr = '';
        if (!cospend.pageIsPublic && !project.external) {
            exportStr = ' <button class="exportSettlement" projectid="'+projectid+'"><span class="icon-file"></span>'+t('cospend', 'Export')+'</button>';
        }
        var autoSettleStr = ' <button class="autoSettlement" projectid="'+projectid+'"><span class="icon-play"></span>'+t('cospend', 'Add these payments to project')+'</button>';
        var settlementStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
            '<h2 id="settlementTitle"><span class="icon-category-organization"></span>'+titleStr+exportStr+autoSettleStr+'</h2>' +
            '<table id="settlementTable" class="sortable"><thead>' +
            '<th>'+fromStr+'</th>' +
            '<th>'+toStr+'</th>' +
            '<th class="sorttable_numeric">'+howMuchStr+'</th>' +
            '</thead>';
        var whoPaysName, toWhomName, amount;
        for (var i=0; i < transactionList.length; i++) {
            amount = transactionList[i].amount.toFixed(2);
            whoPaysName = getMemberName(projectid, transactionList[i].from);
            toWhomName = getMemberName(projectid, transactionList[i].to);
            if (amount !== '0.00') {
                settlementStr = settlementStr +
                    '<tr>' +
                    '<td>'+whoPaysName+'</td>' +
                    '<td>'+toWhomName+'</td>' +
                    '<td>'+amount+'</td>' +
                    '</tr>';
            }
        }
        settlementStr = settlementStr + '</table>';
        $('#billdetail').html(settlementStr);
        sorttable.makeSortable(document.getElementById('settlementTable'));
    }

    function getProjectMoneyBusterLink(projectid) {
        // unselect bill
        $('.billitem').removeClass('selectedbill');

        if (cospend.currentProjectId !== projectid) {
            selectProject($('.projectitem[projectid="'+projectid+'"]'));
        }

        var project = cospend.projects[projectid];
        var url;

        if (project.external) {
            var id = projectid.split('@')[0];
            var ncurl = project.ncurl;
            var password = project.password;
            url = 'https://net.eneiluj.moneybuster.cospend/' + ncurl.replace(/\/$/, '').replace(/https?:\/\//gi, '') + '/' + id + '/' + password;
        }
        else {
            url = 'https://net.eneiluj.moneybuster.cospend/' + window.location.host + OC.generateUrl('').replace('/index.php', '') + projectid + '/';
        }

        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: projectName});
        var mbStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
            '<h2 id="mbTitle"><span class="icon-phone"></span>'+titleStr+'</h2>' +
            '<div id="qrcodediv"></div>' +
            '<label id="mbUrlLabel">' + url + '</label>' +
            '<br/>' +
            '<label id="mbUrlHintLabel">' +
            t('cospend', 'Scan this QRCode with an Android phone with MoneyBuster installed and open the link or simply send the link to another Android phone.') +
            '</label>';
        $('#billdetail').html(mbStr);

        var img = new Image();
        // wait for the image to be loaded to generate the QRcode
        img.onload = function(){
            var qr = kjua({
                text: url,
                crisp: false,
                render: 'canvas',
                minVersion: 6,
                ecLevel: 'H',
                size: 210,
                back: "#ffffff",
                fill: cospend.themeColorDark,
                rounded: 100,
                quiet: 1,
                mode: 'image',
                mSize: 20,
                mPosX: 50,
                mPosY: 50,
                image: img,
                label: 'no label',
            });
            $('#qrcodediv').append(qr);
        };
        img.onerror = function() {
            var qr = kjua({
                text: url,
                crisp: false,
                render: 'canvas',
                minVersion: 6,
                ecLevel: 'H',
                size: 210,
                back: "#ffffff",
                fill: cospend.themeColorDark,
                rounded: 100,
                quiet: 1,
                mode: 'label',
                mSize: 10,
                mPosX: 50,
                mPosY: 50,
                image: img,
                label: 'Cospend',
                fontcolor: '#000000',
            });
            $('#qrcodediv').append(qr);
        };

        // dirty trick to get image URL from css url()... Anyone knows better ?
        var srcurl = $('#dummylogo').css('content').replace('url("', '').replace('")', '');
        img.src = srcurl;
    }

    function displayCurrencies(projectid, projectInfo) {
        // deselect bill
        $('.billitem').removeClass('selectedbill');
        var project = cospend.projects[projectid];
        var mainCurrencyName = projectInfo.currencyname;
        var currencies = projectInfo.currencies;
        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('cospend', 'Currencies of project {name}', {name: projectName});

        var curStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
            '<h2 id="curTitle" projectid="'+projectid+'"><span class="icon-currencies"></span>'+titleStr+'</h2>' +
            '<div id="manage-currencies">' +
            '    <div id="main-currency-div">' +
            '        <label>' +
            '            <a class="icon icon-tag"></a>' +
            '            '+t('cospend', 'Main currency')+
            '        </label>' +
            '        <div id="main-currency-label">' +
            '            <label id="main-currency-label-label">'+
                         (mainCurrencyName || t('cospend', 'None'))+'</label>' +
            '            <input type="submit" value="" class="icon-rename editMainCurrency">' +
            '        </div>' +
            '        <div id="main-currency-edit">' +
            '            <input type="text" value="'+(mainCurrencyName || t('cospend', 'Potatoe'))+'" class="editMainCurrencyInput">' +
            '            <input type="submit" value="" class="icon-close editMainCurrencyClose">' +
            '            <input type="submit" value="" class="icon-checkmark editMainCurrencyOk">' +
            '        </div>' +
            '    </div><hr/>' +
            '    <div id="currencies-div">' +
            '        <label>' +
            '            <a class="icon icon-add"></a>' +
            '            '+t('cospend', 'Add currency')+
            '        </label>' +
            '        <div id="add-currency">' +
            '            <label for="addCurrencyNameInput">'+t('cospend', 'Name')+'</label>'+
            '            <input type="text" value="" id="addCurrencyNameInput">' +
            '            <label for="addCurrencyRateInput"> '+t('cospend', 'Exchange rate to main currency') +
            '               <br/>' + t('cospend', '(1 of this currency = X of main currency)')+
            '            </label>'+
            '            <input type="number" value="1" id="addCurrencyRateInput" step="0.0001" min="0">' +
            '            <input type="submit" value="" class="icon-add addCurrencyOk">' +
            '        </div><hr/><br/>' +
            '        <label>' +
            '            <a class="icon icon-currencies"></a>' +
            '            '+t('cospend', 'Currency list')+
            '        </label><br/><br/>' +
            '        <div id="currency-list">' +
            '        </div>' +
            '    </div>';
            '</div>';

        $('#billdetail').html(curStr);
        for (var i = 0; i < currencies.length; i++) {
            addCurrency(projectid, currencies[i]);
        }
    }

    function addCurrencyDb(projectid, name, rate) {
        $('.addCurrencyOk').addClass('icon-loading-small');
        var req = {
            name: name,
            rate: rate
        };
        var url;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/currency';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/addCurrency');
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/currency');
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            addCurrency(projectid, {name: name, exchange_rate: rate, id: response});
            cospend.projects[projectid].currencies.push({
                name: name,
                exchange_rate: rate,
                id: response
            });
            OC.Notification.showTemporary(t('cospend', 'Currency {n} added', {n: name}));
        }).always(function() {
            $('.addCurrencyOk').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add currency') +
                ': ' + response.responseText
            );
        });
    }

    function addCurrency(projectid, currency) {
        var curStr = '<div class="one-currency" projectid="'+projectid+'" currencyid="'+currency.id+'">' +
            '    <div class="one-currency-label">' +
            '        <label class="one-currency-label-label">'+
                     currency.name+' (x'+currency.exchange_rate+')</label>' +
            '        <input type="submit" value="" class="icon-rename editOneCurrency">' +
            '        <input type="submit" value="" class="icon-delete deleteOneCurrency">' +
            '    </div>' +
            '    <div class="one-currency-edit">' +
            '        <label>'+t('cospend', 'Name')+'</label>'+
            '        <input type="text" value="'+currency.name+'" class="editCurrencyNameInput">' +
            '        <label> '+t('cospend', 'Exchange rate to main currency') +
            '           <br/>' + t('cospend', '(1 of this currency = X of main currency)')+
            '        </label>'+
            '        <input type="number" value="'+currency.exchange_rate+'" class="editCurrencyRateInput" step="0.0001" min="0">' +
            '        <input type="submit" value="" class="icon-close editCurrencyClose">' +
            '        <input type="submit" value="" class="icon-checkmark editCurrencyOk">' +
            '    </div>' +
            '</div>';
        $('#currency-list').append(curStr);
    }

    function deleteCurrencyDb(projectid, currencyId) {
        $('.one-currency[currencyid='+currencyId+'] .deleteOneCurrency').addClass('icon-loading-small');
        var req = {
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/currency/' + currencyId;
                type = 'DELETE';
            }
            else {
                req.projectid = projectid;
                req.currencyid = currencyId;
                url = OC.generateUrl('/apps/cospend/deleteCurrency');
                type = 'POST';
            }
        }
        else {
            type = 'DELETE';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/currency/'+currencyId);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('.one-currency[currencyid=' + currencyId + ']').remove();
            var currencies = cospend.projects[projectid].currencies;
            var iToDel = null;
            for (var i = 0; i < currencies.length; i++) {
                if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                    iToDel = i;
                    break;
                }
            }
            if (iToDel !== null) {
                currencies.splice(iToDel, 1);
            }
        }).always(function() {
            $('.one-currency[currencyid='+currencyId+'] .deleteOneCurrency').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete currency') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function editCurrencyDb(projectid, currencyId, name, rate) {
        $('.one-currency[currencyid='+currencyId+'] .editCurrencyOk').addClass('icon-loading-small');
        var req = {
            name: name,
            rate: rate
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/currency/' + currencyId;
                type = 'PUT';
            }
            else {
                req.projectid = projectid;
                req.currencyid = currencyId;
                url = OC.generateUrl('/apps/cospend/editCurrency');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/currency/'+currencyId);
            type = 'PUT';
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('.one-currency[currencyid=' + currencyId + '] .one-currency-edit').hide();
            $('.one-currency[currencyid=' + currencyId + '] .one-currency-label').show()
            .find('.one-currency-label-label').text(name+' (x'+rate+')');
            var currencies = cospend.projects[projectid].currencies;
            for (var i = 0; i < currencies.length; i++) {
                if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                    currencies[i].name = name;
                    currencies[i].exchange_rate = rate;
                    break;
                }
            }
        }).always(function() {
            $('.one-currency[currencyid='+currencyId+'] .editCurrencyOk').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to edit currency') +
                '; ' + response.responseJSON.message
            );
        });
    }

    function displayCategories(projectid, projectInfo) {
        // deselect bill
        $('.billitem').removeClass('selectedbill');
        var project = cospend.projects[projectid];
        var categories = projectInfo.categories;
        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('cospend', 'Categories of project {name}', {name: projectName});

        var catStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
            '<h2 id="catTitle" projectid="'+projectid+'"><span class="icon-category-app-bundles"></span>'+titleStr+'</h2>' +
            '<div id="manage-categories">' +
            '    <div id="categories-div">' +
            '        <label>' +
            '            <a class="icon icon-add"></a>' +
            '            '+t('cospend', 'Add category')+
            '        </label>' +
            '        <div id="add-category">' +
            '            <label for="addCategoryIconInput">'+t('cospend', 'Icon')+'</label>'+
            '            <input type="text" value="" id="addCategoryIconInput">' +
            '            <label for="addCategoryNameInput">'+t('cospend', 'Name')+'</label>'+
            '            <input type="text" value="" id="addCategoryNameInput">' +
            '            <label for="addCategoryColorInput">'+t('cospend', 'Color')+'</label>'+
            '            <input type="color" value="" id="addCategoryColorInput">' +
            '            <input type="submit" value="" class="icon-add addCategoryOk">' +
            '        </div><hr/><br/>' +
            '        <label>' +
            '            <a class="icon icon-category-app-bundles"></a>' +
            '            '+t('cospend', 'Category list')+
            '        </label><br/><br/>' +
            '        <div id="category-list">' +
            '        </div>' +
            '    </div>';
            '</div>';

        $('#billdetail').html(catStr);
        for (var catId in categories) {
            addCategory(projectid, catId, categories[catId]);
        }
    }

    function addCategoryDb(projectid, name, icon, color) {
        $('.addCategoryOk').addClass('icon-loading-small');
        var req = {
            name: name,
            icon: icon,
            color: color
        };
        var url;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/category';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/addCategory');
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/category');
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            cospend.projects[projectid].categories[response] = {
                name: name,
                icon: icon,
                color: color
            };
            addCategory(projectid, response, cospend.projects[projectid].categories[response]);
            OC.Notification.showTemporary(t('cospend', 'Category {n} added', {n: name}));
        }).always(function() {
            $('.addCategoryOk').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add category') +
                ': ' + response.responseText
            );
        });
    }

    function addCategory(projectid, catId, category) {
        var catStr = '<div class="one-category" projectid="'+projectid+'" categoryid="'+catId+'">' +
            '    <div class="one-category-label">' +
            '        <label class="one-category-label-icon">'+(category.icon || '')+'</label>' +
            '        <label class="one-category-label-label">'+category.name+'</label>' +
            '        <input class="one-category-label-color" type="color" value="'+category.color+'" readonly/>' +
            '        <input type="submit" value="" class="icon-rename editOneCategory">' +
            '        <input type="submit" value="" class="icon-delete deleteOneCategory">' +
            '    </div>' +
            '    <div class="one-category-edit">' +
            '        <label>'+t('cospend', 'Icon')+'</label>'+
            '        <input type="text" value="'+(category.icon || '')+'" class="editCategoryIconInput">' +
            '        <label>'+t('cospend', 'Name')+'</label>'+
            '        <input type="text" value="'+category.name+'" class="editCategoryNameInput">' +
            '        <label>'+t('cospend', 'Color')+'</label>'+
            '        <input type="color" value="'+category.color+'" class="editCategoryColorInput">' +
            '        <input type="submit" value="" class="icon-close editCategoryClose">' +
            '        <input type="submit" value="" class="icon-checkmark editCategoryOk">' +
            '    </div>' +
            '</div>';
        $('#category-list').append(catStr);
    }

    function deleteCategoryDb(projectid, categoryId) {
        $('.one-category[categoryid='+categoryId+'] .deleteOneCategory').addClass('icon-loading-small');
        var req = {
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/category/' + categoryId;
                type = 'DELETE';
            }
            else {
                req.projectid = projectid;
                req.categoryid = categoryId;
                url = OC.generateUrl('/apps/cospend/deleteCategory');
                type = 'POST';
            }
        }
        else {
            type = 'DELETE';
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/category/'+categoryId);
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('.one-category[categoryid=' + categoryId + ']').remove();
            delete cospend.projects[projectid].categories[categoryId];
        }).always(function() {
            $('.one-category[categoryid='+categoryId+'] .deleteOneCategory').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete category') +
                ': ' + response.responseJSON.message);
        });
    }

    function editCategoryDb(projectid, categoryId, name, icon, color) {
        $('.one-category[categoryid='+categoryId+'] .editCategoryOk').addClass('icon-loading-small');
        var req = {
            name: name,
            icon: icon,
            color: color
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/category/' + categoryId;
                type = 'PUT';
            }
            else {
                req.projectid = projectid;
                req.categoryid = categoryId;
                url = OC.generateUrl('/apps/cospend/editCategory');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/category/'+categoryId);
            type = 'PUT';
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('.one-category[categoryid=' + categoryId + '] .one-category-edit').hide();
            $('.one-category[categoryid=' + categoryId + '] .one-category-label').show()
            .find('.one-category-label-label').text(name);
            $('.one-category[categoryid=' + categoryId + '] .one-category-label .one-category-label-icon').text(icon || '');
            $('.one-category[categoryid=' + categoryId + '] .one-category-label .one-category-label-color').val(color);
            cospend.projects[projectid].categories[categoryId].name = name;
            cospend.projects[projectid].categories[categoryId].icon = icon;
            cospend.projects[projectid].categories[categoryId].color = color;
        }).always(function() {
            $('.one-category[categoryid='+categoryId+'] .editCategoryOk').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to edit category') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function displayStatistics(projectid, allStats, dateMin=null, dateMax=null, paymentMode=null, category=null,
                               amountMin=null, amountMax=null, showDisabled=true, currencyId=null) {
        // deselect bill
        $('.billitem').removeClass('selectedbill');

        var statList = allStats.stats;
        var monthlyStats = allStats.monthlyStats;
        var categoryStats = allStats.categoryStats;
        var categoryMemberStats = allStats.categoryMemberStats;
        var memberIds = allStats.memberIds;
        cospend.currentStats = allStats;
        cospend.currentStatsProjectId = projectid;
        var color;

        var isFiltered = (    (dateMin !== null && dateMin !== '')
                           || (dateMax !== null && dateMax !== '')
                           || (paymentMode !== null && paymentMode !== 'n')
                           || (category !== null && parseInt(category) !== 0)
                           || (amountMin !== null && amountMin !== '')
                           || (amountMax !== null && amountMax !== '')
                        );

        var project = cospend.projects[projectid];
        var projectName = getProjectName(projectid);
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var titleStr = t('cospend', 'Statistics of project {name}', {name: projectName});
        var nameStr = t('cospend', 'Member name');
        var paidStr = t('cospend', 'Paid');
        var spentStr = t('cospend', 'Spent');
        var balanceStr = t('cospend', 'Balance');
        var filteredBalanceStr = t('cospend', 'Filtered balance');
        var exportStr = '';

        var totalPayed = 0.0;
        for (var i=0; i < statList.length; i++) {
            totalPayed += statList[i].paid;
        }

        if (!cospend.pageIsPublic && !project.external) {
            exportStr = ' <button class="exportStats" projectid="'+projectid+'"><span class="icon-file"></span>'+t('cospend', 'Export')+'</button>';
        }
        var totalPayedText = '<p class="totalPayedText">' +
                             t('cospend', 'Total payed by all the members: {t}', {t: totalPayed.toFixed(2)}) + '</p>';
        var statsStr = '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
            '<h2 id="statsTitle"><span class="icon-category-monitoring"></span>'+titleStr+exportStr+'</h2>' +
            '<div id="stats-filters">' +
            '    <label for="date-min-stats">'+t('cospend', 'Minimum date')+': </label><input type="date" id="date-min-stats"/>' +
            '    <label for="date-max-stats">'+t('cospend', 'Maximum date')+': </label><input type="date" id="date-max-stats"/>' +
            '    <label for="payment-mode-stats">' +
            '        <a class="icon icon-tag"></a>' +
            '        '+t('cospend', 'Payment mode')+
            ':   </label>' +
            '    <select id="payment-mode-stats">' +
            '       <option value="n" selected>'+t('cospend', 'All')+'</option>';
        var pm;
        for (var pmId in cospend.paymentModes) {
            pm = cospend.paymentModes[pmId];
            statsStr += '       <option value="'+pmId+'">'+pm.icon+' '+pm.name+'</option>';
        }
        statsStr +=
            '    </select>' +
            '    <label for="category-stats">' +
            '        <a class="icon icon-category-app-bundles"></a>' +
            '        '+t('cospend', 'Category')+
            ':   </label>' +
            '    <select id="category-stats">' +
            '       <option value="0">'+t('cospend', 'All')+'</option>' +
            '       <option value="-100" selected>'+t('cospend', 'All except reimbursement')+'</option>';
        var cat;
        for (var catId in cospend.projects[projectid].categories) {
            cat = cospend.projects[projectid].categories[catId];
            statsStr += '       <option value="'+catId+'">'+(cat.icon || '')+' '+cat.name+'</option>';
        }
        for (var catId in cospend.categories) {
            cat = cospend.categories[catId];
            statsStr += '       <option value="'+catId+'">'+cat.icon+' '+cat.name+'</option>';
        }
        statsStr +=
            '    </select>' +
            '    <label for="amount-min-stats">'+t('cospend', 'Minimum amount')+': </label><input type="number" id="amount-min-stats"/>' +
            '    <label for="amount-max-stats">'+t('cospend', 'Maximum amount')+': </label><input type="number" id="amount-max-stats"/>' +
            '    <label for="currency-stats">'+t('cospend', 'Currency of statistic values')+': </label>' +
            '    <select id="currency-stats">' +
            '       <option value="0">'+(project.currencyname || t('cospend', 'Main project\'s currency'))+'</option>';
        var currency;
        for (var i = 0; i < project.currencies.length; i++) {
            currency = project.currencies[i];
            statsStr += '<option value="'+currency.id+'">'+currency.name+' (x'+currency.exchange_rate+')</option>';
        }
        statsStr +=
            '    </select>' +
            '    <input id="showDisabled" class="checkbox" type="checkbox"/>' +
            '    <label for="showDisabled" class="checkboxlabel">'+t('cospend', 'Show disabled members')+'</label> ' +
            '</div>' +
            '<br/>' +
            totalPayedText +
            '<br/><hr/><h2 class="statTableTitle">'+t('cospend', 'Global stats')+'</h2>' +
            '<table id="statsTable" class="sortable"><thead>' +
            '<th>'+nameStr+'</th>' +
            '<th class="sorttable_numeric">'+paidStr+'</th>' +
            '<th class="sorttable_numeric">'+spentStr+'</th>';
        if (isFiltered) {
            statsStr += '<th class="sorttable_numeric">'+filteredBalanceStr+'</th>';
        }
        statsStr +=
            '<th class="sorttable_numeric">'+balanceStr+'</th>' +
            '</thead>';
        var paid, spent, balance, filteredBalance, name, balanceClass, filteredBalanceClass, member, imgurl;
        for (var i=0; i < statList.length; i++) {
            member = cospend.members[projectid][statList[i].member.id];
            balanceClass = '';
            if (statList[i].balance > 0) {
                balanceClass = ' class="balancePositive"';
            }
            else if (statList[i].balance < 0) {
                balanceClass = ' class="balanceNegative"';
            }
            filteredBalanceClass = '';
            if (statList[i].filtered_balance > 0) {
                filteredBalanceClass = ' class="balancePositive"';
            }
            else if (statList[i].filtered_balance < 0) {
                filteredBalanceClass = ' class="balanceNegative"';
            }
            paid = statList[i].paid.toFixed(2);
            spent = statList[i].spent.toFixed(2);
            balance = statList[i].balance.toFixed(2);
            filteredBalance = statList[i].filtered_balance.toFixed(2);
            name = statList[i].member.name;
            color = '#'+member.color;
            imgurl = OC.generateUrl('/apps/cospend/getAvatar?color=' + member.color + '&name=' + encodeURIComponent(member.name));
            statsStr +=
                '<tr>' +
                '<td style="border: 2px solid '+color+';">'+
                '<div class="owerAvatar'+(member.activated ? '' : ' owerAvatarDisabled')+'">' +
                '   <div class="disabledMask"></div>' +
                '   <img src="' + imgurl + '"/>' +
                '</div>' +
                name+
                '</td>' +
                '<td style="border: 2px solid '+color+';">'+paid+'</td>' +
                '<td style="border: 2px solid '+color+';">'+spent+'</td>';
            if (isFiltered) {
                statsStr += '<td style="border: 2px solid '+color+';"'+filteredBalanceClass+'>'+filteredBalance+'</td>';
            }
            statsStr +=
                '<td style="border: 2px solid '+color+';"'+balanceClass+'>'+balance+'</td>' +
                '</tr>';
        }
        statsStr += '</table>';
        // monthly stats
        statsStr += '<h2 class="statTableTitle">'+t('cospend', 'Monthly stats')+'</h2>';
        statsStr += '<table id="monthlyTable" class="sortable"><thead>' +
            '<th>'+t('cospend', 'Member/Month')+'</th>';
        for (var month in monthlyStats) {
            statsStr += '<th class="sorttable_numeric"><span>'+month+'</span></th>';
        }
        statsStr += '</thead>';
        var mids = memberIds.slice();
        mids.push('0');
        var mid;
        for (var i=0; i < mids.length; i++) {
            mid = mids[i];
            member = cospend.members[projectid][mid];
            if (parseInt(mid) === 0) {
                color = 'var(--color-border-dark)';
                statsStr += '<tr>';
                statsStr += '<td><b>'+t('cospend', 'All members')+'</b></td>';
            }
            else {
                color = '#'+member.color;
                imgurl = OC.generateUrl('/apps/cospend/getAvatar?color=' + member.color + '&name=' + encodeURIComponent(member.name));
                statsStr += '<tr>';
                statsStr += '<td style="border: 2px solid '+color+';">' +
                '<div class="owerAvatar'+(member.activated ? '' : ' owerAvatarDisabled')+'">' +
                '   <div class="disabledMask"></div>' +
                '   <img src="' + imgurl + '"/>' +
                '</div>' +
                cospend.members[projectid][mid].name +
                '</td>';
            }
            for (var month in monthlyStats) {
                statsStr += '<td style="border: 2px solid '+color+';">';
                statsStr += monthlyStats[month][mid].toFixed(2);
                statsStr += '</td>';
            }
            statsStr += '</tr>';
        }
        statsStr += '</table>';

        statsStr += '<hr/><canvas id="memberChart"></canvas>';
        statsStr += '<hr/><canvas id="categoryChart"></canvas>';
        statsStr += '<hr/><select id="categoryMemberSelect">';
        for (var catId in categoryMemberStats) {
            if (parseInt(catId) !== 0 &&
                (cospend.categories.hasOwnProperty(catId) || cospend.projects[projectid].categories.hasOwnProperty(catId))
            ) {
                if (cospend.categories.hasOwnProperty(catId)) {
                    statsStr += '<option value="'+catId+'">'+
                                cospend.categories[catId].icon+' '+
                                cospend.categories[catId].name+'</option>';
                }
                else {
                    statsStr += '<option value="'+catId+'">'+
                                (cospend.projects[projectid].categories[catId].icon || '')+' '+
                                cospend.projects[projectid].categories[catId].name+'</option>';
                }
            }
            else {
                statsStr += '<option value="'+catId+'">'+t('cospend', 'No category')+'</option>';
            }
        }
        statsStr += '</select>';
        statsStr += '<canvas id="categoryMemberChart"></canvas>';
        statsStr += '<hr/><select id="memberPolarSelect">';
        for (var i=0; i < memberIds.length; i++) {
            mid = memberIds[i];
            statsStr += '<option value="'+mid+'">'+
                        cospend.members[projectid][mid].name+'</option>';
        }
        statsStr += '</select>';
        statsStr += '<canvas id="memberPolarChart"></canvas>';

        $('#billdetail').html(statsStr);

        // CHARTS
        var memberBackgroundColors = [];
        var memberData = {
            // 2 datasets: paid and spent
            datasets: [{
                data: [],
                backgroundColor: []
            },{
                data: [],
                backgroundColor: []
            }
        ],
            labels: []
        };
        var sumPaid = 0;
        var sumSpent = 0;
        for (var i=0; i < statList.length; i++) {
            paid = statList[i].paid.toFixed(2);
            spent = statList[i].spent.toFixed(2);
            sumPaid += parseFloat(paid);
            sumSpent += parseFloat(spent);
            name = statList[i].member.name;
            color = '#'+cospend.members[projectid][statList[i].member.id].color;
            memberData.datasets[0].data.push(paid);
            memberData.datasets[1].data.push(spent);

            memberBackgroundColors.push(color);

            memberData.labels.push(name);
        }
        memberData.datasets[0].backgroundColor = memberBackgroundColors;
        memberData.datasets[1].backgroundColor = memberBackgroundColors;

        if (statList.length > 0 && sumPaid > 0.0 && sumSpent > 0.0) {
            var memberPieChart = new Chart($('#memberChart'), {
                type: 'pie',
                data: memberData,
                options: {
                    title: {
                        display: true,
                        text: t('cospend', 'Who paid (outside circle) and spent (inside pie)?')
                    },
                    responsive: true,
                    showAllTooltips: false,
                    legend: {
                        position: 'left'
                    }
                }
            });
        }
        // category chart
        var categoryData = {
            datasets: [{
                data: [],
                backgroundColor: []
            }],
            labels: []
        };
        var catName, catIdInt;
        for (var catId in categoryStats) {
            paid = categoryStats[catId].toFixed(2);
            catIdInt = parseInt(catId);
            if (cospend.categories.hasOwnProperty(catId)) {
                catName = cospend.categories[catId].icon + ' ' + cospend.categories[catId].name;
                color = cospend.categories[catId].color;
            }
            else if (cospend.projects[projectid].categories.hasOwnProperty(catId)) {
                catName = (cospend.projects[projectid].categories[catId].icon || '') +
                    ' ' + cospend.projects[projectid].categories[catId].name;
                color = cospend.projects[projectid].categories[catId].color || 'red';
            }
            else {
                catName = t('cospend', 'No category');
                color = 'black';
            }
            categoryData.datasets[0].data.push(paid);
            categoryData.datasets[0].backgroundColor.push(color);
            categoryData.labels.push(catName);
        }
        if (Object.keys(categoryStats).length > 0) {
            var categoryPieChart = new Chart($('#categoryChart'), {
                type: 'pie',
                data: categoryData,
                options: {
                    title: {
                        display: true,
                        text: t('cospend', 'What was paid per category?')
                    },
                    responsive: true,
                    showAllTooltips: false,
                    legend: {
                        position: 'left'
                    }
                }
            });
        }

        if (memberIds.length > 0) {
            // make tables sortable
            sorttable.makeSortable(document.getElementById('statsTable'));
            sorttable.makeSortable(document.getElementById('monthlyTable'));
        }

        if (dateMin) {
            $('#date-min-stats').val(dateMin);
        }
        if (dateMax) {
            $('#date-max-stats').val(dateMax);
        }
        if (paymentMode) {
            $('#payment-mode-stats').val(paymentMode);
        }
        if (category) {
            $('#category-stats').val(category);
        }
        if (amountMin) {
            $('#amount-min-stats').val(amountMin);
        }
        if (amountMax) {
            $('#amount-max-stats').val(amountMax);
        }
        if (showDisabled) {
            $('#showDisabled').prop('checked', true);
        }
        if (currencyId) {
            $('#currency-stats').val(currencyId);
        }

        displayCategoryMemberChart();
        displayMemberPolarChart();
    }

    function displayCategoryMemberChart() {
        var categoryMemberStats = cospend.currentStats.categoryMemberStats;
        var projectid = cospend.currentStatsProjectId;
        var scroll = false;
        if (cospend.currentCategoryMemberChart) {
            cospend.currentCategoryMemberChart.destroy();
            delete cospend.currentCategoryMemberChart;
            scroll = true;
        }
        var selectedCatId = $('#categoryMemberSelect').val();
        var catName;
        if (selectedCatId === null || selectedCatId === '') {
            return;
        }
        if (cospend.categories.hasOwnProperty(selectedCatId)) {
            catName = cospend.categories[selectedCatId].icon+' '+cospend.categories[selectedCatId].name;
        }
        else if (cospend.projects[projectid].categories.hasOwnProperty(selectedCatId)) {
            catName = (cospend.projects[projectid].categories[selectedCatId].icon || '') +
                ' '+cospend.projects[projectid].categories[selectedCatId].name;
        }
        else {
            catName = t('cospend', 'No category');
        }

        var categoryData = {
            datasets: [{
                data: [],
                backgroundColor: []
            }],
            labels: []
        };
        var categoryStats, memberName, paid, color;
        categoryStats = categoryMemberStats[selectedCatId];
        for (var mid in categoryStats) {
            memberName = cospend.members[projectid][mid].name;
            color = '#'+cospend.members[projectid][mid].color;
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

    function displayMemberPolarChart() {
        var categoryMemberStats = cospend.currentStats.categoryMemberStats;
        var projectid = cospend.currentStatsProjectId;
        var scroll = false;
        if (cospend.currentMemberPolarChart) {
            cospend.currentMemberPolarChart.destroy();
            delete cospend.currentMemberPolarChart;
            scroll = true;
        }
        var selectedMemberId = $('#memberPolarSelect').val();
        var memberName = cospend.members[projectid][selectedMemberId].name;

        if (Object.keys(categoryMemberStats).length === 0) {
            return;
        }

        var memberData = {
            datasets: [{
                data: [],
                backgroundColor: []
            }],
            labels: []
        };
        var catName, paid, color;
        for (var catId in categoryMemberStats) {
            //memberName = cospend.members[projectid][mid].name;
            if (cospend.categories.hasOwnProperty(catId)) {
                catName = cospend.categories[catId].icon+' '+cospend.categories[catId].name;
                color = cospend.categories[catId].color;
            }
            else if (cospend.projects[projectid].categories.hasOwnProperty(catId)) {
                catName = (cospend.projects[projectid].categories[catId].icon || '') +
                    ' '+cospend.projects[projectid].categories[catId].name;
                color = cospend.projects[projectid].categories[catId].color || 'red';
            }
            else {
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

    function getBills(projectid) {
        $('#bill-list').html('<h2 class="icon-loading-small"></h2>');
        var req = {};
        var url;
        var type;

        var project = cospend.projects[projectid];

        if (!cospend.pageIsPublic) {
            if (project.external) {
                type = 'GET';
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/bills';
            }
            else {
                url = OC.generateUrl('/apps/cospend/getBills');
                type = 'POST';
                req.projectid = projectid;
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/bills');
            type = 'GET';
        }
        cospend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            $('#bill-list').html('');
            cospend.bills[projectid] = {};
            if (response.length > 0) {
                var bill;
                for (var i = 0; i < response.length; i++) {
                    bill = response[i];
                    addBill(projectid, bill);
                }
            }
            else {
                $('#bill-list').html('<h2 class="nobill">'+t('cospend', 'No bill yet')+'</h2>');
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to get bills'));
            $('#bill-list').html('');
        });
    }

    function getProjectName(projectid) {
        return cospend.projects[projectid].name;
    }

    function updateDisplayedBill(projectid, billid, what, payer_id, repeat,
                                 paymentmode=null, categoryid=null, repeatallactive=0,
                                 repeatuntil=null) {
        var projectName = getProjectName(projectid);
        $('.bill-title').attr('billid', billid);
        var c = '#888888';
        if (billid !== 0) {
            $('.bill-type').hide();
            $('#owerValidate').hide();
            var memberPayer = cospend.members[projectid][payer_id];
            c = '#'+memberPayer.color;
        }

        var links = what.match(/https?:\/\/[^\s]+/gi) || [];
        var formattedLinks = '';
        for (var i=0; i < links.length; i++) {
            formattedLinks = formattedLinks + '<a href="'+links[i]+'" target="blank">[🔗 '+t('cospend', 'link')+']</a> ';
        }
        var repeatChar = '';
        if (repeat !== 'n') {
            repeatChar = ' ⏩';
        }
        var paymentmodeChar = '';
        // c b f card, cash, check
        if (cospend.paymentModes.hasOwnProperty(paymentmode)) {
            paymentmodeChar = cospend.paymentModes[paymentmode].icon + ' ';
        }
        var categoryChar = '';
        if (cospend.categories.hasOwnProperty(categoryid)) {
            categoryChar = cospend.categories[categoryid].icon + ' ';
        }
        else if (cospend.projects[projectid].categories.hasOwnProperty(categoryid)) {
            categoryChar = (cospend.projects[projectid].categories[categoryid].icon || '') + ' ';
        }
        var whatFormatted = paymentmodeChar + categoryChar + what.replace(/https?:\/\/[^\s]+/gi, '') + repeatChar;
        $('.bill-title').html(
            '<span class="loading-bill"></span>' +
            '<span class="icon-edit-white"></span>' +
            t('cospend', 'Bill : {what}', {what: whatFormatted}) +
            ' ' + formattedLinks
        );
        $('.bill-title').attr('style', 'background-color: '+c+';');
        updateAmountEach(projectid);
    }

    function displayBill(projectid, billid) {
        // select bill item
        $('.billitem').removeClass('selectedbill');
        $('.billitem[billid='+billid+']').addClass('selectedbill');

        var bill = cospend.bills[projectid][billid];
        var projectName = getProjectName(projectid);

        var owers = bill.owers;
        var owerIds = [];
        var i;
        for (i=0; i < owers.length; i++) {
            owerIds.push(owers[i].id);
        }

        var c = '#888888';
        var owerCheckboxes = '';
        var payerOptions = '';
        var member;
        var selected, checked, readonly;
        var color, imgurl;
        for (var memberid in cospend.members[projectid]) {
            member = cospend.members[projectid][memberid];
            // payer
            selected = '';
            if (member.id === bill.payer_id) {
                selected = ' selected';
            }
            // show member if it's the payer or if it's activated
            if (member.activated || member.id === bill.payer_id) {
                payerOptions = payerOptions + '<option value="'+member.id+'"'+selected+'>'+member.name+'</option>';
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
                color = cospend.members[projectid][member.id].color;
                imgurl = OC.generateUrl('/apps/cospend/getAvatar?color=' + color + '&name=' + encodeURIComponent(member.name));
                owerCheckboxes = owerCheckboxes +
                    '<div class="owerEntry">' +
                    '<div class="owerAvatar'+(cospend.members[projectid][member.id].activated ? '' : ' owerAvatarDisabled')+'">' +
                    '   <div class="disabledMask"></div>' +
                    '   <img src="' + imgurl + '"/>' +
                    '</div>' +
                    '<input id="'+projectid+member.id+'" owerid="'+member.id+'" class="checkbox" type="checkbox"'+checked+readonly+'/>' +
                    '<label for="'+projectid+member.id+'" class="checkboxlabel">'+member.name+'</label> ' +
                    '<input id="amount'+projectid+member.id+'" owerid="'+member.id+'" class="amountinput" type="number" value="" step="0.01" min="0"/>' +
                    '<label for="amount'+projectid+member.id+'" class="numberlabel">'+member.name+'</label>' +
                    '<label class="spentlabel"></label>' +
                    '</div>';
            }
        }
        var payerDisabled = '';
        if (billid !== 0) {
            // disable payer select if bill is not new
            if (!cospend.members[projectid][bill.payer_id].activated) {
                payerDisabled = ' disabled';
            }
            var memberPayer = cospend.members[projectid][bill.payer_id];
            c = '#'+(memberPayer.color || '888888');
        }
        $('#billdetail').html('');
        $('.app-content-list').addClass('showdetails');
        var whatStr = t('cospend', 'What?');
        var amountStr = t('cospend', 'How much?');
        var payerStr = t('cospend', 'Who payed?');
        var dateStr = t('cospend', 'When?');
        var owersStr = t('cospend', 'For whom?');

        var links = bill.what.match(/https?:\/\/[^\s]+/gi) || [];
        var formattedLinks = '';
        for (i=0; i < links.length; i++) {
            formattedLinks = formattedLinks + '<a href="'+links[i]+'" target="blank">[🔗 '+t('cospend', 'link')+']</a> ';
        }
        var repeatChar = '';
        if (bill.repeat !== 'n') {
            repeatChar = ' ⏩';
        }
        var paymentmodeChar = '';
        // c b f card, cash, check
        if (cospend.paymentModes.hasOwnProperty(bill.paymentmode)) {
            paymentmodeChar = cospend.paymentModes[bill.paymentmode].icon + ' ';
        }
        var categoryChar = '';
        if (cospend.categories.hasOwnProperty(bill.categoryid)) {
            categoryChar = cospend.categories[bill.categoryid].icon + ' ';
        }
        if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
            categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
        }
        var whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '') + repeatChar;
        var titleStr = t('cospend', 'Bill : {what}', {what: whatFormatted});

        var allStr = t('cospend', 'All');
        var noneStr = t('cospend', 'None');
        var owerValidateStr = t('cospend', 'Create the bill');
        var addFileLinkText = t('cospend', 'Attach public link to personal file');
        var normalBillOption = t('cospend', 'Classic, even split');
        var normalBillHint = t('cospend', 'Classic mode: Choose a payer, enter a bill amount and select who is concerned by the whole spending, the bill is then split equitably between selected members. Real life example: One person pays the whole restaurant bill and everybody agrees to evenly split the cost.');
        var customBillOption = t('cospend', 'Custom owed amount per member');
        var customBillHint = t('cospend', 'Custom mode, uneven split: Choose a payer, ignore the bill amount (which is disabled) and enter a custom owed amount for each member who is concerned. Then press "Create the bills". Multiple bills will be created. Real life example: One person pays the whole restaurant bill but there are big price differences between what each person ate.');
        var personalShareBillOption = t('cospend', 'Even split with optional personal parts');
        var personalShareBillHint = t('cospend', 'Classic+personal mode: This mode is similar to the classic one. Choose a payer and enter a bill amount corresponding to what was actually payed. Then select who is concerned by the bill and optionally set an amount related to personal stuff for some members. Multiple bills will be created: one for the shared spending and one for each personal part. Real life example: We go shopping, part of what was bought concerns the group but someone also added something personal (like a shirt) which the others don\'t want to collectively pay.');
        var billTypeStr = t('cospend', 'Bill type');
        var paymentModeStr = t('cospend', 'Payment mode');
        var categoryStr = t('cospend', 'Category');

        var addFileHtml = '';
        if (!cospend.pageIsPublic) {
            addFileHtml = '<button id="addFileLinkButton"><span class="icon-public"></span>'+addFileLinkText+'</button>';
        }

        var currenciesStr = '';
        if (cospend.projects[projectid].currencyname && cospend.projects[projectid].currencies.length > 0) {
            currenciesStr = '<select id="bill-currency">' +
                '<option value="">' + cospend.projects[projectid].currencyname + '</option>';
            var currency;
            for (var i = 0; i < cospend.projects[projectid].currencies.length; i++) {
                currency = cospend.projects[projectid].currencies[i];
                currenciesStr += '<option value="'+currency.id+'">' +
                    currency.name+' ⇒ '+cospend.projects[projectid].currencyname+' (x'+currency.exchange_rate+')' +
                    '</option>';
            }
            currenciesStr += '</select>';
        }

        var detail =
            '<div id="app-details-toggle" tabindex="0" class="icon-confirm"></div>' +
            '<h2 class="bill-title" projectid="'+projectid+'" billid="'+bill.id+'" style="background-color: '+c+';">' +
            '    <span class="loading-bill"></span>' +
            '    <span class="icon-edit-white"></span>'+titleStr+' '+formattedLinks +
            '    <button id="owerValidate"><span class="icon-confirm"></span> <span id="owerValidateText">'+owerValidateStr+'</span></button>' +
            '</h2>' +
            '<div class="bill-form">' +
            '    <div class="bill-left">' +
            '        <div class="bill-what">' +
            '            <label for="what">' +
            '                <a class="icon icon-tag"></a>' +
            '                '+whatStr+
            '            </label>' +
            '            <input type="text" id="what" class="input-bill-what" value="'+bill.what+'"/>' +
            '        </div>' + addFileHtml +
            '        <div class="bill-amount">' +
            '            <label for="amount">' +
            '                <a class="icon icon-quota"></a>' +
            '                '+amountStr+
            '            </label>' +
            '            <input type="number" id="amount" class="input-bill-amount" value="'+bill.amount+'" step="any"/>' +
            '           ' + currenciesStr +
            '        </div>' +
            '        <div class="bill-payer">' +
            '            <label for="payer">' +
            '                <a class="icon icon-user"></a>' +
            '                '+payerStr+
            '            </label>' +
            '            <select id="payer" class="input-bill-payer"'+payerDisabled+'>' +
            '                '+payerOptions+
            '            </select>' +
            '        </div>' +
            '        <div class="bill-date">' +
            '            <label for="date">' +
            '                <a class="icon icon-calendar-dark"></a>' +
            '                '+dateStr+
            '            </label>' +
            '            <input type="date" id="date" class="input-bill-date" value="'+bill.date+'"/>' +
            '            <label for="repeatbill">' +
            '                <a class="icon icon-play-next"></a>' +
            '                '+t('cospend', 'Repeat this bill every')+
            '            </label>' +
            '            <select id="repeatbill">' +
            '               <option value="n" selected>'+t('cospend', 'do not repeat')+'</option>' +
            '               <option value="d">'+t('cospend', 'day')+'</option>' +
            '               <option value="w">'+t('cospend', 'week')+'</option>' +
            '               <option value="m">'+t('cospend', 'month')+'</option>' +
            '               <option value="y">'+t('cospend', 'year')+'</option>' +
            '            </select>' +
            '           <input id="repeatallactive" class="checkbox" type="checkbox"/>' +
            '           <label for="repeatallactive" class="checkboxlabel">' +
            '               ' + t('cospend', 'Include all active member on repeat') +
            '           </label><br/>' +
            '           <label for="repeatuntil" class="checkboxlabel">' +
            '               ' + t('cospend', 'Repeat until') +
            '           </label> ' +
            '           <input type="date" id="repeatuntil" class="input-bill-repeatuntil" value="'+bill.repeatuntil+'"/>' +
            '        </div>' +
            '        <div class="bill-payment-mode">' +
            '            <label for="payment-mode">' +
            '                <a class="icon icon-tag"></a>' +
            '                '+paymentModeStr+
            '            </label>' +
            '            <select id="payment-mode">' +
            '               <option value="n" selected>'+t('cospend', 'None')+'</option>';
        var pm;
        for (var pmId in cospend.paymentModes) {
            pm = cospend.paymentModes[pmId];
            detail += '       <option value="'+pmId+'">'+pm.icon+' '+pm.name+'</option>';
        }
        detail +=
            '            </select>' +
            '        </div>' +
            '        <div class="bill-category">' +
            '            <label for="category">' +
            '                <a class="icon icon-category-app-bundles"></a>' +
            '                '+categoryStr+
            '            </label>' +
            '            <select id="category">' +
            '               <option value="0" selected>'+t('cospend', 'None')+'</option>';
        var cat;
        for (var catId in cospend.projects[projectid].categories) {
            cat = cospend.projects[projectid].categories[catId];
            detail += '       <option value="'+catId+'">'+(cat.icon || '')+' '+cat.name+'</option>';
        }
        for (var catId in cospend.categories) {
            cat = cospend.categories[catId];
            detail += '       <option value="'+catId+'">'+cat.icon+' '+cat.name+'</option>';
        }
        detail +=
            '            </select>' +
            '        </div>' +
            '    </div>' +
            '    <div class="bill-right">' +
            '        <div class="bill-type">' +
            '            <a class="icon icon-toggle-filelist"></a><span>'+billTypeStr+'</span>' +
            '            <select id="billtype">' +
            '               <option value="normal" selected>' + normalBillOption + '</option>' +
            '               <option value="perso">' + personalShareBillOption + '</option>' +
            '               <option value="custom">' + customBillOption + '</option>' +
            '            </select>' +
            '            <button id="modehintbutton"><span class="icon-details"></span></button>' +
            '            <div class="modehint modenormal">' + normalBillHint + '</div>' +
            '            <div class="modehint modeperso">' + personalShareBillHint + '</div>' +
            '            <div class="modehint modecustom">' + customBillHint + '</div>' +
            '        </div>' +
            '        <div class="bill-owers">' +
            '            <a class="icon icon-group"></a><span>'+owersStr+'</span>' +
            '            <div class="owerAllNoneDiv">' +
            '            <button id="owerAll"><span class="icon-group"></span> '+allStr+'</button>' +
            '            <button id="owerNone"><span class="icon-disabled-users"></span> '+noneStr+'</button>' +
            '            </div>' +
            '            '+owerCheckboxes +
            '        </div>' +
            '    </div>' +
            '</div>';

        $(detail).appendTo('#billdetail');
        $('#billdetail .input-bill-what').focus().select();
        if (billid !== 0) {
            $('#repeatbill').val(bill.repeat);
            $('#payment-mode').val(bill.paymentmode || 'n');
            if (cospend.categories.hasOwnProperty(bill.categoryid) ||
                cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
                $('#category').val(bill.categoryid);
            }
            else {
                $('#category').val(0);
            }
            $('#repeatallactive').prop('checked', bill.repeatallactive || false);
            if (bill.repeat === 'n') {
                $('#repeatallactive').hide();
                $('label[for=repeatallactive]').hide();
                $('#repeatuntil').hide();
                $('label[for=repeatuntil]').hide();
            }
        }
        else {
            $('.bill-type').show();
            $('#owerValidate').show();
            $('#repeatallactive').hide();
            $('label[for=repeatallactive]').hide();
            $('#repeatuntil').hide();
            $('label[for=repeatuntil]').hide();
        }
        updateAmountEach(projectid);
    }

    function updateAmountEach(projectid) {
        var amount = $('#amount').val();
        var nbChecked = $('.owerEntry .checkbox:checked').length;
        var weightSum = 0;
        var oneWeight, mid, owerVal;
        var billType = $('#billtype').val();
        var billId = parseInt($('#billdetail .bill-title').attr('billid'));
        $('.spentlabel').text('');
        if (nbChecked > 0 &&
            (billId !== 0 || billType === 'normal') &&
            !isNaN(amount) &&
            parseFloat(amount) > 0.0) {
            $('.owerEntry .checkbox:checked').each(function() {
                mid = $(this).attr('owerid');
                weightSum += cospend.members[projectid][mid].weight;
            });
            oneWeight = parseFloat(amount) / weightSum;
            $('.owerEntry .checkbox:checked').each(function() {
                mid = $(this).attr('owerid');
                owerVal = oneWeight * cospend.members[projectid][mid].weight;
                $(this).parent().find('.spentlabel').text('('+owerVal.toFixed(2)+')');
            });
        }
    }

    function getMemberName(projectid, memberid) {
        //var memberName = $('.projectitem[projectid="'+projectid+'"] .memberlist > li[memberid='+memberid+'] b.memberName').text();
        var memberName = cospend.members[projectid][memberid].name;
        return memberName;
    }

    function reload(msg) {
        OC.Notification.showTemporary(msg);
        new Timer(function() {
            location.reload();
        }, 5000);
    }

    function addBill(projectid, bill) {
        cospend.bills[projectid][bill.id] = bill;

        var owerNames = '';
        var ower, i;
        for (i=0; i < bill.owers.length; i++) {
            ower = bill.owers[i];
            if (!cospend.members[projectid].hasOwnProperty(ower.id)) {
                reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
                return;
            }
            owerNames = owerNames + getMemberName(projectid, ower.id) + ', ';
        }
        owerNames = owerNames.replace(/, $/, '');
        var title = '';
        var memberName = '';
        var memberFirstLetter;
        var c;

        var links = bill.what.match(/https?:\/\/[^\s]+/gi) || [];
        var formattedLinks = '';
        var linkChars = '';
        for (i=0; i < links.length; i++) {
            formattedLinks = formattedLinks + '<a href="'+links[i]+'" target="blank">['+t('cospend', 'link')+']</a> ';
            linkChars = linkChars + '  🔗';
        }
        var repeatChar = '';
        if (bill.id !== 0 && bill.repeat !== 'n') {
            repeatChar = ' ⏩';
        }
        var paymentmodeChar = '';
        // c b f card, cash, check
        if (cospend.paymentModes.hasOwnProperty(bill.paymentmode)) {
            paymentmodeChar = cospend.paymentModes[bill.paymentmode].icon + ' ';
        }
        var categoryChar = '';
        if (cospend.categories.hasOwnProperty(bill.categoryid)) {
            categoryChar = cospend.categories[bill.categoryid].icon + ' ';
        }
        if (cospend.projects[projectid].categories.hasOwnProperty(bill.categoryid)) {
            categoryChar = (cospend.projects[projectid].categories[bill.categoryid].icon || '') + ' ';
        }
        var whatFormatted = paymentmodeChar + categoryChar + bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars + repeatChar;

        var imgurl, color;
        var disabled = '';
        if (bill.id !== 0) {
            if (!cospend.members[projectid].hasOwnProperty(bill.payer_id)) {
                reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
                return;
            }
            memberName = getMemberName(projectid, bill.payer_id);

            title = whatFormatted + '\n' + bill.amount.toFixed(2) + '\n' +
                bill.date + '\n' + memberName + ' → ' + owerNames;

            color = cospend.members[projectid][bill.payer_id].color;
            imgurl = OC.generateUrl('/apps/cospend/getAvatar?color='+color+'&name='+encodeURIComponent(memberName));
            // disabled
            disabled = cospend.members[projectid][bill.payer_id].activated ? '' : ' disabled';
        }
        else {
            imgurl = OC.generateUrl('/apps/cospend/getAvatar?name='+encodeURIComponent(' '));
        }
        var item = '<a href="#" class="app-content-list-item billitem" billid="'+bill.id+'" projectid="'+projectid+'" title="'+title+'">' +
            '<div class="app-content-list-item-icon" style="background-image: url('+imgurl+');"> ' +
            '   <div class="billItemDisabledMask'+disabled+'"></div>' +
            '</div>'+
            '<div class="app-content-list-item-line-one">'+whatFormatted+'</div>' +
            '<div class="app-content-list-item-line-two">'+bill.amount.toFixed(2)+' ('+memberName+' → '+owerNames+')</div>' +
            '<span class="app-content-list-item-details">'+bill.date+'</span>' +
            '<div class="icon-delete deleteBillIcon"></div>' +
            '<div class="icon-history undoDeleteBill" style="'+undoDeleteBillStyle+'" title="Undo"></div>' +
            '</a>';
        $(item).prependTo('.app-content-list');

        $('#bill-list .nobill').remove();

        if (parseInt(getUrlParameter('bill')) === bill.id && getUrlParameter('project') === projectid) {
            displayBill(projectid, bill.id);
        }
    }

    function updateProjectBalances(projectid) {
        var req = {
        };
        var url;
        var type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password;
                type = 'GET';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/getProjectInfo');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password);
            type = 'GET';
        }
        cospend.currentGetProjectsAjax = $.ajax({
            type: type,
            url: url,
            data: req,
            async: true,
        }).done(function (response) {
            var balance, balanceField, balanceClass, balanceTxt;
            for (var memberid in response.balance) {
                balance = response.balance[memberid];
                balanceField = $('.projectitem[projectid="'+projectid+'"] .memberlist > li[memberid='+memberid+'] b.balance');
                balanceField.removeClass('balancePositive').removeClass('balanceNegative');
                // just in case make member visible
                $('.memberitem[memberid='+memberid+']').removeClass('invisibleMember');
                if (balance <= -0.01) {
                    balanceClass = 'balanceNegative';
                    balanceTxt = balance.toFixed(2);
                    balanceField.addClass(balanceClass).text(balanceTxt);
                }
                else if (balance >= 0.01) {
                    balanceClass = 'balancePositive';
                    balanceTxt = '+' + balance.toFixed(2);
                    balanceField.addClass(balanceClass).text(balanceTxt);
                }
                else {
                    balanceField.text('0.00');
                    // hide member if balance == 0 and disabled
                    if (!cospend.members[projectid][memberid].activated) {
                        $('.memberitem[memberid='+memberid+']').addClass('invisibleMember');
                    }
                }
            }
        }).always(function() {
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to update balances'));
        });
    }

    function addProject(project) {
        cospend.projects[project.id] = project;
        cospend.members[project.id] = {};

        var name = project.name;
        if (project.external) {
            name = '<span class="icon-external" style="display: inline-grid; margin-bottom: -3px;"></span> ' + name;
        }
        var projectid = project.id;
        var addMemberStr = t('cospend', 'Add member');
        var guestAccessStr = t('cospend', 'Guest access link');
        var renameStr = t('cospend', 'Rename');
        var changePwdStr = t('cospend', 'Change password');
        var displayStatsStr = t('cospend', 'Display statistics');
        var settleStr = t('cospend', 'Settle the project');
        var exportStr = t('cospend', 'Export to csv');
        var autoexportStr = t('cospend', 'Auto export');
        var manageCurrenciesStr = t('cospend', 'Manage currencies');
        var manageCategoriesStr = t('cospend', 'Manage categories');
        var deleteStr;
        if (project.external) {
            deleteStr = t('cospend', 'Delete remote project');
        }
        else {
            deleteStr = t('cospend', 'Delete');
        }
        var moneyBusterUrlStr = t('cospend', 'Link/QRCode for MoneyBuster');
        var deletedStr = t('cospend', 'Deleted {name}', {name: name});
        var removeExtStr = t('cospend', 'Remove');
        var shareTitle = t('cospend', 'Press enter to validate');
        var guestLink;
        if (project.external) {
            var id = projectid.split('@')[0];
            guestLink = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/loginproject/' + id;
        }
        else {
            guestLink = OC.generateUrl('/apps/cospend/loginproject/'+projectid);
            guestLink = window.location.protocol + '//' + window.location.hostname + guestLink;
        }
        var editPerm = (project.hasOwnProperty('guestpermissions') && project.guestpermissions.indexOf('e') !== -1);
        var delPerm = (project.hasOwnProperty('guestpermissions') && project.guestpermissions.indexOf('d') !== -1);
        var creaPerm = (project.hasOwnProperty('guestpermissions') && project.guestpermissions.indexOf('c') !== -1);
        var li =
            '<li class="projectitem collapsible" projectid="'+projectid+'">' +
            '    <a class="icon-folder" href="#" title="'+projectid+'">' +
            '        <span>'+name+'</span>' +
            '    </a>' +
            '    <div class="app-navigation-entry-utils">' +
            '        <ul>' +
            '            <li class="app-navigation-entry-utils-counter"><span>'+project.members.length+'</span></li>';
        if (!project.external) {
            li = li + '            <li class="app-navigation-entry-utils-menu-button shareProjectButton">' +
            '                <button class="icon-shar"></button>' +
            '            </li>';
        }
        li = li + '            <li class="app-navigation-entry-utils-menu-button projectMenuButton">' +
            '                <button></button>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
            '    <div class="app-navigation-entry-edit">' +
            '        <div>' +
            '            <input type="text" value="'+project.name+'" class="editProjectInput">' +
            '            <input type="submit" value="" class="icon-close editProjectClose">' +
            '            <input type="submit" value="" class="icon-checkmark editProjectOk">' +
            '        </div>' +
            '    </div>';
        if (!project.external) {
            li = li + '    <ul class="app-navigation-entry-share">' +
            '        <li class="shareinputli" title="'+shareTitle+'"><input type="text" class="shareinput"/></li>' +
            '    </ul>';
        }
        li = li + '    <div class="newmemberdiv">' +
            '        <input class="newmembername" type="text" value=""/>' +
            '        <button class="newmemberbutton icon-add"></button>' +
            '    </div>' +

            '    <div class="app-navigation-entry-menu">' +
            '        <ul>' +
            '            <li>' +
            '                <a href="#" class="addMember">' +
            '                    <span class="icon-add"></span>' +
            '                    <span>'+addMemberStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="copyProjectGuestLink" title="'+guestLink+'">' +
            '                    <span class="icon-clippy"></span>' +
            '                    <span>'+guestAccessStr+'&nbsp</span>' +
            '                    <div class="guestpermissions">' +
            '                       <div class="icon-category-disabled permguest permDelete '+(delPerm ? 'permActive' : '')+'" ' +
            '                       title="'+t('cospend', 'Permission to delete bills')+'"></div>'+
            '                       <div class="icon-rename permguest permEdit '+(editPerm ? 'permActive' : '')+'" ' +
            '                       title="'+t('cospend', 'Permission to edit bills and project')+'"></div>'+
            '                       <div class="icon-add permguest permCreate '+(creaPerm ? 'permActive' : '')+'" ' +
            '                       title="'+t('cospend', 'Permission to create bills')+'"></div>'+
            '                    </div>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="moneyBusterProjectUrl">' +
            '                    <span class="icon-phone"></span>' +
            '                    <span>'+moneyBusterUrlStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="editProjectName">' +
            '                    <span class="icon-rename"></span>' +
            '                    <span>'+renameStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="editProjectPassword">' +
            '                    <span class="icon-rename"></span>' +
            '                    <span>'+changePwdStr+'</span>' +
            '                </a>' +
            '            </li>';
        if (!project.external) {
            li = li + '            <li>' +
            '                <a href="#" class="manageProjectCategories">' +
            '                    <span class="icon-category-app-bundles"></span>' +
            '                    <span>'+manageCategoriesStr+'</span>' +
            '                </a>' +
            '            </li>';
            li = li + '            <li>' +
            '                <a href="#" class="manageProjectCurrencies">' +
            '                    <span class="icon-currencies"></span>' +
            '                    <span>'+manageCurrenciesStr+'</span>' +
            '                </a>' +
            '            </li>';
        }
        li = li + '            <li>' +
            '                <a href="#" class="getProjectStats">' +
            '                    <span class="icon-category-monitoring"></span>' +
            '                    <span>'+displayStatsStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="getProjectSettlement">' +
            '                    <span class="icon-category-organization"></span>' +
            '                    <span>'+settleStr+'</span>' +
            '                </a>' +
            '            </li>';
        if (!project.external) {
            li = li + '            <li>' +
            '                <a href="#" class="exportProject">' +
            '                    <span class="icon-category-office"></span>' +
            '                    <span>'+exportStr+'</span>' +
            '                </a>' +
            '            </li>';
            li = li + '            <li>' +
            '                <a href="#" class="autoexportProject">' +
            '                    <span class="icon-category-office"></span>' +
            '                    <span class="autoexportLabel">'+autoexportStr+'</span>' +
            '                    <select class="autoexportSelect">' +
            '                       <option value="n">'+t('cospend', 'No')+'</option>' +
            '                       <option value="d">'+t('cospend', 'Daily')+'</option>' +
            '                       <option value="w">'+t('cospend', 'Weekly')+'</option>' +
            '                       <option value="m">'+t('cospend', 'Monthly')+'</option>' +
            '                    </select>' +
            '                </a>' +
            '            </li>';
        }
        li = li + '            <li>' +
            '                <a href="#" class="deleteProject">' +
            '                    <span class="icon-delete"></span>' +
            '                    <span>'+deleteStr+'</span>' +
            '                </a>' +
            '            </li>';
        if (project.external) {
            li = li + '            <li>' +
            '                <a href="#" class="removeExternalProject">' +
            '                    <span class="icon-category-disabled"></span>' +
            '                    <span>'+removeExtStr+'</span>' +
            '                </a>' +
            '            </li>';
        }
        li = li + '        </ul>' +
            '    </div>' +
            '    <div class="app-navigation-entry-deleted">' +
            '        <div class="app-navigation-entry-deleted-description">'+deletedStr+'</div>' +
            '        <button class="app-navigation-entry-deleted-button icon-history undoDeleteProject" title="Undo"></button>' +
            '    </div>' +
            '    <ul class="memberlist"></ul>' +
            '</li>';

        $(li).appendTo('#projectlist');

        // select project if it was the last selected (option restore on page load)
        if (!getUrlParameter('project') && cospend.restoredSelectedProjectId === projectid) {
            selectProject($('.projectitem[projectid="'+projectid+'"]'));
        }
        else if (getUrlParameter('project') === projectid) {
            selectProject($('.projectitem[projectid="'+projectid+'"]'));
        }

        if (!project.external) {
            $('.projectitem[projectid="'+projectid+'"] .autoexportSelect').val(project.autoexport);
        }

        if (cospend.pageIsPublic) {
            $('.projectitem[projectid="'+projectid+'"] .shareProjectButton').hide();
            $('.projectitem[projectid="'+projectid+'"] .exportProject').parent().hide();
        }

        var i;
        for (i=0; i < project.members.length; i++) {
            var memberId = project.members[i].id;
            addMember(projectid, project.members[i], project.balance[memberId]);
        }

        if (project.shares) {
            for (i=0; i < project.shares.length; i++) {
                var userid = project.shares[i].userid;
                var username = project.shares[i].name;
                var shid = project.shares[i].id;
                var permissions = project.shares[i].permissions;
                addShare(projectid, userid, username, shid, 'u', permissions);
            }
        }

        if (project.group_shares) {
            for (i=0; i < project.group_shares.length; i++) {
                var groupid = project.group_shares[i].groupid;
                var groupname = project.group_shares[i].name;
                var shid = project.group_shares[i].id;
                var permissions = project.group_shares[i].permissions;
                addShare(projectid, groupid, groupname, shid, 'g', permissions);
            }
        }

        if (project.circle_shares) {
            for (i=0; i < project.circle_shares.length; i++) {
                var circleid = project.circle_shares[i].circleid;
                var circlename = project.circle_shares[i].name;
                var shid = project.circle_shares[i].id;
                var permissions = project.circle_shares[i].permissions;
                addShare(projectid, circleid, circlename, shid, 'c', permissions);
            }
        }

        //// set selected project
        //if (cospend.restoredSelectedProjectId === projectid) {
        //    $('.projectitem').removeClass('selectedproject');
        //    $('.projectitem[projectid="'+projectid+'"]').addClass('selectedproject');
        //    $('.app-navigation-entry-utils-counter').removeClass('highlighted');
        //    $('.projectitem[projectid="'+projectid+'"] .app-navigation-entry-utils-counter').addClass('highlighted');
        //}
    }

    function addMember(projectid, member, balance) {
        // add member to dict
        cospend.members[projectid][member.id] = {
            id: member.id,
            name: member.name,
            activated: member.activated,
            weight: member.weight,
            color: rgbObjToHex(member.color).replace('#', '')
        };

        var invisibleClass = '';
        var balanceStr;
        if (balance >= 0.01) {
            balanceStr = '<b class="balance balancePositive">+'+balance.toFixed(2)+'</b>';
        }
        else if (balance <= -0.01) {
            balanceStr = '<b class="balance balanceNegative">'+balance.toFixed(2)+'</b>';
        }
        else {
            balanceStr = '<b class="balance">0.00</b>';
            if (!member.activated) {
                invisibleClass = ' invisibleMember';
            }
        }
        var iconToggleStr, toggleStr, imgurl;
        var lockSpan = '';
        if (member.activated) {
            iconToggleStr = 'icon-delete';
            toggleStr = t('cospend', 'Deactivate');
        }
        else {
            lockSpan = '<div class="member-list-disabled-icon icon-disabled-user"> </div>';
            iconToggleStr = 'icon-history';
            toggleStr = t('cospend', 'Reactivate');
        }
        var color = cospend.members[projectid][member.id].color;
        imgurl = OC.generateUrl('/apps/cospend/getAvatar?color='+color+'&name='+encodeURIComponent(member.name));


        var renameStr = t('cospend', 'Rename');
        var changeWeightStr = t('cospend', 'Change weight');
        var changeColorStr = t('cospend', 'Change color');
        var li =
            '<li memberid="'+member.id+'" class="memberitem'+invisibleClass+'">' +
            '    <div class="memberAvatar'+(member.activated ? '' : ' memberAvatarDisabled')+'">' +
            '       <div class="disabledMask"></div>' +
            '       <img src="'+imgurl+'"/>' +
            '    </div>' +
            '    <a class="member-list-icon" href="#">' +
            '        <span class="memberNameBalance">' +
            '            <b class="memberName" title="'+member.name+' (x'+member.weight+')">' +
                            member.name + ((parseFloat(member.weight) !== 1.0) ? (' (x'+member.weight+')') : '') +
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
            '                    <span>'+renameStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="editWeightMember">' +
            '                    <span class="icon-rename"></span>' +
            '                    <span>'+changeWeightStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="editColorMember">' +
            '                    <span class="icon-toggle-background"></span>' +
            '                    <span>'+changeColorStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="toggleMember">' +
            '                    <span class="'+iconToggleStr+'"></span>' +
            '                    <span>'+toggleStr+'</span>' +
            '                </a>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
            '    <div class="app-navigation-entry-edit">' +
            '        <div>' +
            '            <input type="text" value="'+member.name+'" class="editMemberInput">' +
            '            <input type="submit" value="" class="icon-close editMemberClose">' +
            '            <input type="submit" value="" class="icon-checkmark editMemberOk">' +
            '        </div>' +
            '    </div>' +
            '</li>';

        $(li).appendTo('#projectlist li.projectitem[projectid="'+projectid+'"] .memberlist');
    }

    function createNormalBill() {
        // get bill info
        var billid = $('.bill-title').attr('billid');
        var projectid = $('.bill-title').attr('projectid');
        // check fields validity
        var valid = true;

        var what = $('.input-bill-what').val();
        var date = $('.input-bill-date').val();
        var amount = parseFloat($('.input-bill-amount').val());
        var payer_id = parseInt($('.input-bill-payer').val());
        var repeat = $('#repeatbill').val();
        var repeatallactive = $('#repeatallactive').is(':checked') ? 1 : 0;
        var repeatuntil = $('.input-bill-repeatuntil').val();
        var paymentmode = $('#payment-mode').val();
        var categoryid = $('#category').val();

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
            // manage currencies
            if ($('#bill-currency') && $('#bill-currency').val()) {
                var currencyId = $('#bill-currency').val();
                var currencies = cospend.projects[projectid].currencies;
                var currency = null;
                for (var i = 0; i < currencies.length; i++) {
                    if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                        currency = currencies[i];
                        break;
                    }
                }
                if (currency) {
                    var userAmount = amount;
                    amount = amount * currency.exchange_rate;
                    $('#amount').val(amount);
                    what += ' ('+userAmount.toFixed(2)+' '+currency.name+')';
                    $('#what').val(what);
                    $('#bill-currency').val('');
                }
            }
            createBill(projectid, what, amount, payer_id, date, owerIds, repeat, false,
                       paymentmode, categoryid, repeatallactive, repeatuntil);
        }
        else {
            OC.Notification.showTemporary(t('cospend', 'Bill values are not valid'));
        }
    }

    function cleanStringFromCurrency(projectid, str) {
        var currency, re;
        for (var i = 0; i < cospend.projects[projectid].currencies.length; i++) {
            currency = cospend.projects[projectid].currencies[i];
            re = new RegExp(' \\(\\d+\\.?\\d* '+currency.name+'\\)','g');
            str = str.replace(re, '');
        }
        return str;
    }

    function onBillEdited(amountChanged=false) {
        // get bill info
        var billid = $('.bill-title').attr('billid');
        var projectid = $('.bill-title').attr('projectid');
        updateAmountEach(projectid);
        // check fields validity
        var valid = true;

        // if this is a new bill : get out
        if (billid === '0') {
            return;
        }

        var what = $('.input-bill-what').val();
        var date = $('.input-bill-date').val();
        var amount = parseFloat($('.input-bill-amount').val());
        var payer_id = parseInt($('.input-bill-payer').val());
        var repeat = $('#repeatbill').val();
        var repeatallactive = $('#repeatallactive').is(':checked') ? 1 : 0;
        var repeatuntil = $('.input-bill-repeatuntil').val();
        var paymentmode = $('#payment-mode').val();
        var categoryid = $('#category').val();

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

        // if valid, save the bill
        if (valid) {
            if (amountChanged) {
                what = cleanStringFromCurrency(projectid, what);
                $('#what').val(what);
            }
            // manage currencies
            if ($('#bill-currency') && $('#bill-currency').val()) {
                var currencyId = $('#bill-currency').val();
                var currencies = cospend.projects[projectid].currencies;
                var currency = null;
                for (var i = 0; i < currencies.length; i++) {
                    if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                        currency = currencies[i];
                        break;
                    }
                }
                if (currency) {
                    var userAmount = amount;
                    amount = amount * currency.exchange_rate;
                    $('#amount').val(amount);
                    what = cleanStringFromCurrency(projectid, what);
                    what += ' ('+userAmount.toFixed(2)+' '+currency.name+')';
                    $('#what').val(what);
                    $('#bill-currency').val('');
                }
            }
            // if values have changed, save the bill
            var oldBill = cospend.bills[projectid][billid];
            // if ower lists don't have the same length, it has changed
            var owersChanged = (oldBill.owers.length !== owerIds.length);
            // same length : check content
            if (!owersChanged) {
                for (var i=0; i < oldBill.owers.length; i++) {
                    if (owerIds.indexOf(oldBill.owers[i].id) === -1) {
                        owersChanged = true;
                        break;
                    }
                }
            }
            if (oldBill.what !== what ||
                oldBill.amount !== amount ||
                oldBill.date !== date ||
                oldBill.repeat !== repeat ||
                oldBill.repeatallactive !== repeatallactive ||
                oldBill.repeatuntil !== repeatuntil ||
                oldBill.payer_id !== payer_id ||
                oldBill.categoryid !== categoryid ||
                oldBill.paymentmode !== paymentmode ||
                owersChanged
            ) {
                saveBill(projectid, billid, what, amount, payer_id, date, owerIds, repeat,
                         paymentmode, categoryid, repeatallactive, repeatuntil);
            }
        }
        else {
            OC.Notification.showTemporary(t('cospend', 'Bill values are not valid'));
        }
    }

    function saveOptionValue(optionValues) {
        if (!cospend.pageIsPublic) {
            var req = {
                options: optionValues
            };
            var url = OC.generateUrl('/apps/cospend/saveOptionValue');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
            }).fail(function() {
                OC.Notification.showTemporary(
                    t('cospend', 'Failed to save option values')
                );
            });
        }
    }

    function restoreOptions() {
        var mom;
        var url = OC.generateUrl('/apps/cospend/getOptionsValues');
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
                        cospend.restoredSelectedProjectId = optionsValues[k];
                    }
                    else if (k === 'outputDirectory') {
                        $('#outputDirectory').text(optionsValues[k])
                    }
                }
            }
            // quite important ;-)
            main();
        }).fail(function() {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to restore options values')
            );
        });
    }

    function addUserAutocompletion(input, projectid) {
        var req = {
        };
        var url = OC.generateUrl('/apps/cospend/getUserList');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            cospend.userIdName = response.users;
            cospend.groupIdName = response.groups;
            cospend.circleIdName = response.circles;
            var data = [];
            var d, name, id;
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
                }
                else {
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
                }
                else {
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
            var ii = input.autocomplete({
                source: data,
                select: function (e, ui) {
                    var it = ui.item;
                    if (it.type === 'g') {
                        addGroupShareDb(it.projectid, it.id, it.name);
                    }
                    else if (it.type === 'u') {
                        addUserShareDb(it.projectid, it.id, it.name);
                    }
                    else if (it.type === 'c') {
                        addCircleShareDb(it.projectid, it.id, it.name);
                    }
                }
            }).data('ui-autocomplete')._renderItem = function(ul, item) {
                var iconClass = 'icon-user';
                if (item.type === 'g') {
                    iconClass = 'icon-group';
                }
                else if (item.type === 'c') {
                    iconClass = 'share-icon-circle';
                }
                var listItem = $('<li></li>')
                    .data('item.autocomplete', item)
                    .append('<a class="shareCompleteLink"><button class="shareCompleteIcon '+iconClass+'"></button> ' + item.label + '</a>')
                    .appendTo(ul);
                return listItem;
            };
        }).fail(function() {
            OC.Notification.showTemporary(t('cospend', 'Failed to get user list'));
        });
    }

    function addUserShareDb(projectid, userid, username) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            userid: userid
        };
        var url = OC.generateUrl('/apps/cospend/addUserShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            addShare(projectid, userid, username, response, 'u', 'edc');
            var projectname = getProjectName(projectid);
            OC.Notification.showTemporary(t('cospend', 'Shared project {pname} with {uname}', {pname: projectname, uname: username}));
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add user share') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function deleteUserShareDb(projectid, shid) {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteUserShareButton').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            shid: shid
        };
        var url = OC.generateUrl('/apps/cospend/deleteUserShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
            li.fadeOut('normal', function() {
                li.remove();
            });
        }).always(function() {
            $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteUserShareButton').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete user share') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function addCircleShareDb(projectid, circleId, circleName) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            circleid: circleId
        };
        var url = OC.generateUrl('/apps/cospend/addCircleShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            addShare(projectid, circleId, circleName, response, 'c', 'edc');
            var projectname = getProjectName(projectid);
            OC.Notification.showTemporary(t('cospend', 'Shared project {pname} with circle {cname}', {pname: projectname, cname: circleName}));
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add circle share') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function deleteCircleShareDb(projectid, shid) {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteCircleShareButton').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            shid: shid
        };
        var url = OC.generateUrl('/apps/cospend/deleteCircleShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
            li.fadeOut('normal', function() {
                li.remove();
            });
        }).always(function() {
            $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteCircleShareButton').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete circle share') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function addGroupShareDb(projectid, groupid, groupname) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            groupid: groupid
        };
        var url = OC.generateUrl('/apps/cospend/addGroupShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            addShare(projectid, groupid, groupname, response, 'g', 'edc');
            var projectname = getProjectName(projectid);
            OC.Notification.showTemporary(t('cospend', 'Shared project {pname} with group {gname}', {pname: projectname, gname: groupname}));
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add group share') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function addShare(projectid, elemId, elemName, id, type, permissions) {
        var displayString = elemId;
        if (type === 'c') {
            displayString = elemName;
        }
        else if (elemId !== elemName) {
            displayString = elemName + ' (' + elemId + ')';
        }
        var iconClass, deleteButtonClass;
        if (type === 'g') {
            iconClass = 'icon-group';
            deleteButtonClass = 'deleteGroupShareButton';
        }
        else if (type === 'u') {
            iconClass = 'icon-user';
            deleteButtonClass = 'deleteUserShareButton';
        }
        else if (type === 'c') {
            iconClass = 'share-icon-circle';
            deleteButtonClass = 'deleteCircleShareButton';
        }
        var editPerm = (permissions.indexOf('e') !== -1);
        var delPerm = (permissions.indexOf('d') !== -1);
        var creaPerm = (permissions.indexOf('c') !== -1);
        var li = '<li shid="'+id+'" elemid="'+escapeHTML(elemId)+'" elemname="' + escapeHTML(elemName) + '">' +
            '<div class="shareLabel"><div class="shareLabelIcon '+iconClass+'">'+
            '</div><span>' + displayString + '</span></div>' +
            '<div class="icon-delete '+deleteButtonClass+'"></div>'+
            '<div class="icon-category-disabled perm permDelete '+(delPerm ? 'permActive' : '')+'" ' +
            'title="'+t('cospend', 'Permission to delete bills')+'"></div>'+
            '<div class="icon-rename perm permEdit '+(editPerm ? 'permActive' : '')+'" ' +
            'title="'+t('cospend', 'Permission to edit bills and project')+'"></div>'+
            '<div class="icon-add perm permCreate '+(creaPerm ? 'permActive' : '')+'" ' +
            'title="'+t('cospend', 'Permission to create bills')+'"></div>'+
            '</li>';
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share').append(li);
        $('.projectitem[projectid="' + projectid + '"] .shareinput').val('');
    }

    function deleteGroupShareDb(projectid, shid) {
        $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteGroupShareButton').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            shid: shid
        };
        var url = OC.generateUrl('/apps/cospend/deleteGroupShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var li = $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + ']');
            li.fadeOut('normal', function() {
                li.remove();
            });
        }).always(function() {
            $('.projectitem[projectid="' + projectid + '"] .app-navigation-entry-share li[shid=' + shid + '] .deleteGroupShareButton').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to delete group share') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function editSharePermissionsDb(projectid, shid, e, d, c) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        $('li[shid="'+shid+'"] .perm').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            shid: shid,
            permissions: (e ? 'e': '') + (c ? 'c': '') + (d ? 'd': '')
        };
        var url = OC.generateUrl('/apps/cospend/editSharePermissions');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            applySharePermissions(projectid, shid, e, d, c);
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
            $('li[shid="'+shid+'"] .perm').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to edit share permissions') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function applySharePermissions(projectid, shid, e, d, c) {
        var shLine = $('li[shid="'+shid+'"]');
        if (e) {
            shLine.find('.permEdit').addClass('permActive');
        }
        else {
            shLine.find('.permEdit').removeClass('permActive');
        }
        if (d) {
            shLine.find('.permDelete').addClass('permActive');
        }
        else {
            shLine.find('.permDelete').removeClass('permActive');
        }
        if (c) {
            shLine.find('.permCreate').addClass('permActive');
        }
        else {
            shLine.find('.permCreate').removeClass('permActive');
        }
    }

    function editGuestPermissionsDb(projectid, e, d, c) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        $('li[projectid="'+projectid+'"] .permguest').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            permissions: (e ? 'e': '') + (c ? 'c': '') + (d ? 'd': '')
        };
        var url = OC.generateUrl('/apps/cospend/editGuestPermissions');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            applyGuestPermissions(projectid, e, d, c);
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
            $('li[projectid="'+projectid+'"] .permguest').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to edit guest permissions') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function applyGuestPermissions(projectid, e, d, c) {
        var projectLine = $('#projectlist li[projectid="'+projectid+'"]');
        if (e) {
            projectLine.find('.permEdit').addClass('permActive');
        }
        else {
            projectLine.find('.permEdit').removeClass('permActive');
        }
        if (d) {
            projectLine.find('.permDelete').addClass('permActive');
        }
        else {
            projectLine.find('.permDelete').removeClass('permActive');
        }
        if (c) {
            projectLine.find('.permCreate').addClass('permActive');
        }
        else {
            projectLine.find('.permCreate').removeClass('permActive');
        }
    }

    function selectProject(projectitem) {
        var projectid = projectitem.attr('projectid');
        var wasOpen = projectitem.hasClass('open');
        var wasSelected = (cospend.currentProjectId === projectid);
        $('.projectitem.open').removeClass('open');
        if (!wasOpen) {
            projectitem.addClass('open');

            if (!wasSelected) {
                saveOptionValue({selectedProject: projectid});
                cospend.currentProjectId = projectid;
                $('.projectitem').removeClass('selectedproject');
                $('.projectitem[projectid="'+projectid+'"]').addClass('selectedproject');
                $('.app-navigation-entry-utils-counter').removeClass('highlighted');
                $('.projectitem[projectid="'+projectid+'"] .app-navigation-entry-utils-counter').addClass('highlighted');

                $('#billdetail').html('');
                getBills(projectid);
            }
        }
    }

    function generatePublicLinkToFile(targetPath) {
        $('.loading-bill').addClass('icon-loading-small');
        var req = {
            path: targetPath
        };
        var url = OC.generateUrl('/apps/cospend/getPublicFileShare');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('.loading-bill').removeClass('icon-loading-small');

            var filePublicUrl = window.location.protocol + '//' + window.location.hostname + OC.generateUrl('/s/'+response.token);

            var what = $('#what').val();
            what = what + ' ' + filePublicUrl;
            $('#what').val(what);
            onBillEdited();
        }).always(function() {
        }).fail(function(response) {
            $('.loading-bill').removeClass('icon-loading-small');
            OC.Notification.showTemporary(
                t('cospend', 'Failed to generate public link to file') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function exportProject(projectid) {
        $('.projectitem[projectid="'+projectid+'"]').addClass('icon-loading-small');
        var timeStamp = Math.floor(Date.now());
        var dateStr = OC.Util.formatDate(timeStamp);
        var filename = projectid + '_' + dateStr + '.csv';
        var req = {
            projectid: projectid,
            name: filename
        };
        var url = OC.generateUrl('/apps/cospend/exportCsvProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('cospend', 'Project exported in {path}', {path: response.path}));
        }).always(function() {
            $('.projectitem[projectid="'+projectid+'"]').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to export project') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function exportStatistics(projectid, dateMin=null, dateMax=null, paymentMode=null, category=null,
                              amountMin=null, amountMax=null, showDisabled=true, currencyId=null) {
        $('.exportStats[projectid="'+projectid+'"] span').addClass('icon-loading-small');
        var req = {
            projectid: projectid,
            dateMin: dateMin,
            dateMax: dateMax,
            paymentMode: paymentMode,
            category: category,
            amountMin: amountMin,
            amountMax: amountMax,
            showDisabled: showDisabled ? '1' : '0',
            currencyId: currencyId
        };
        var url = OC.generateUrl('/apps/cospend/exportCsvStatistics');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('cospend', 'Project statistics exported in {path}', {path: response.path}));
        }).always(function() {
            $('.exportStats[projectid="'+projectid+'"] span').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to export project statistics') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function exportSettlement(projectid) {
        $('.exportSettlement[projectid="'+projectid+'"] span').addClass('icon-loading-small');
        var req = {
            projectid: projectid
        };
        var url = OC.generateUrl('/apps/cospend/exportCsvSettlement');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('cospend', 'Project settlement exported in {path}', {path: response.path}));
        }).always(function() {
            $('.exportSettlement[projectid="'+projectid+'"] span').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to export project settlement') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function autoSettlement(projectid) {
        $('.autoSettlement[projectid="'+projectid+'"] span').addClass('icon-loading-small');
        var req = {
        };
        var url, type;
        var project = cospend.projects[projectid];
        if (!cospend.pageIsPublic) {
            if (project.external) {
                var id = projectid.split('@')[0];
                url = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/api/projects/' + id + '/' + project.password + '/autosettlement';
                type = 'GET';
            }
            else {
                req.projectid = projectid;
                url = OC.generateUrl('/apps/cospend/autoSettlement');
                type = 'POST';
            }
        }
        else {
            url = OC.generateUrl('/apps/cospend/api/projects/'+cospend.projectid+'/'+cospend.password+'/autosettlement');
            type = 'GET';
        }
        $.ajax({
            type: type,
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            updateProjectBalances(projectid);
            getBills(projectid);
            OC.Notification.showTemporary(t('cospend', 'Project settlement bills added'));
        }).always(function() {
            $('.autoSettlement[projectid="'+projectid+'"] span').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(
                t('cospend', 'Failed to add project settlement bills') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function importProject(targetPath) {
        if (!endsWith(targetPath, '.csv')) {
            OC.Notification.showTemporary(t('cospend', 'Only CSV files can be imported'));
            return;
        }
        $('#addFileLinkButton').addClass('icon-loading-small');
        var req = {
            path: targetPath
        };
        var url = OC.generateUrl('/apps/cospend/importCsvProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('#addFileLinkButton').removeClass('icon-loading-small');
            getProjects();
        }).always(function() {
        }).fail(function(response) {
            $('#addFileLinkButton').removeClass('icon-loading-small');
            OC.Notification.showTemporary(
                t('cospend', 'Failed to import project file') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function importSWProject(targetPath) {
        if (!endsWith(targetPath, '.csv')) {
            OC.Notification.showTemporary(t('cospend', 'Only CSV files can be imported'));
            return;
        }
        $('#addFileLinkButton').addClass('icon-loading-small');
        var req = {
            path: targetPath
        };
        var url = OC.generateUrl('/apps/cospend/importSWProject');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('#addFileLinkButton').removeClass('icon-loading-small');
            getProjects();
        }).always(function() {
        }).fail(function(response) {
            $('#addFileLinkButton').removeClass('icon-loading-small');
            OC.Notification.showTemporary(
                t('cospend', 'Failed to import project file') +
                ': ' + response.responseJSON.message
            );
        });
    }

    function updateCustomAmount() {
        var tot = 0;
        $('.amountinput').each(function() {
            var val = parseFloat($(this).val());
            if (!isNaN(val) && val > 0.0) {
                tot = tot + val;
            }
        });
        $('#amount').val(tot);
    }

    // create equitable bill with personal parts
    function createEquiPersoBill() {
        var projectid = $('.bill-title').attr('projectid');

        var what = $('.input-bill-what').val();
        var date = $('.input-bill-date').val();
        var amount = parseFloat($('.input-bill-amount').val());
        var payer_id = parseInt($('.input-bill-payer').val());
        var repeat = 'n';
        var repeatallactive = 0;
        var repeatuntil = null;
        var paymentmode = $('#payment-mode').val();
        var categoryid = $('#category').val();

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

        var valid = true;

        var tmpAmount;

        if (what === null || what === '') {
            valid = false;
        }
        if (date === null || date === '' || date.match(/^\d\d\d\d-\d\d-\d\d$/g) === null) {
            valid = false;
        }
        if (isNaN(amount) || isNaN(payer_id)) {
            valid = false;
        }
        else {
            // check if amount - allPersonalParts >= 0
            tmpAmount = amount;
            $('.amountinput').each(function() {
                var owerId = parseInt($(this).attr('owerid'));
                var amountVal = parseFloat($(this).val());
                var owerSelected = $('.owerEntry input[owerid="'+owerId+'"]').is(':checked');
                if (!isNaN(amountVal) && amountVal > 0.0 && owerSelected) {
                    tmpAmount = tmpAmount - amountVal;
                }
            });
            if (tmpAmount < 0.0) {
                OC.Notification.showTemporary(t('cospend', 'Personal parts are bigger than the paid amount'));
                return;
            }
        }
        if (owerIds.length === 0) {
            valid = false;
        }

        if (valid) {
            var initWhat = what;
            // manage currencies
            var currency = null;
            var initAmount;
            if ($('#bill-currency') && $('#bill-currency').val()) {
                var currencyId = $('#bill-currency').val();
                var currencies = cospend.projects[projectid].currencies;
                for (var i = 0; i < currencies.length; i++) {
                    if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                        currency = currencies[i];
                        break;
                    }
                }
            }
            // create bills related to personal parts
            tmpAmount = amount;
            $('.amountinput').each(function() {
                var oneWhat = initWhat;
                var owerId = parseInt($(this).attr('owerid'));
                var amountVal = parseFloat($(this).val());
                var owerSelected = $('.owerEntry input[owerid="'+owerId+'"]').is(':checked');
                if (!isNaN(amountVal) && amountVal > 0.0 && owerSelected) {
                    tmpAmount = tmpAmount - amountVal;
                    if (currency !== null) {
                        initAmount = amountVal;
                        amountVal = amountVal * currency.exchange_rate;
                        oneWhat += ' ('+initAmount.toFixed(2)+' '+currency.name+')';
                    }
                    createBill(projectid, oneWhat, amountVal, payer_id, date, [owerId], repeat, true,
                               paymentmode, categoryid, repeatallactive, repeatuntil);
                }
            });
            // currency conversion for main amount
            if (currency) {
                var userAmount = tmpAmount;
                tmpAmount = tmpAmount * currency.exchange_rate;
                $('#amount').val(tmpAmount);
                what += ' ('+userAmount.toFixed(2)+' '+currency.name+')';
                $('#what').val(what);
                $('#bill-currency').val('');
            }
            // create equitable bill with the rest
            createBill(projectid, what, tmpAmount, payer_id, date, owerIds, repeat, true,
                       paymentmode, categoryid, repeatallactive, repeatuntil);
            // empty bill detail
            $('#billdetail').html('');
            // remove new bill line
            $('.billitem[billid=0]').fadeOut('normal', function() {
                $(this).remove();
                if ($('.billitem').length === 0) {
                    $('#bill-list').html('<h2 class="nobill">'+t('cospend', 'No bill yet')+'</h2>');
                }
            });
            $('.app-content-list').removeClass('showdetails');
        }
        else {
            OC.Notification.showTemporary(t('cospend', 'Invalid values'));
        }
    }

    function createCustomAmountBill() {
        var projectid = $('.bill-title').attr('projectid');

        var what = $('.input-bill-what').val();
        var date = $('.input-bill-date').val();
        var amount = parseFloat($('.input-bill-amount').val());
        var payer_id = parseInt($('.input-bill-payer').val());
        var repeat = 'n';
        var repeatallactive = 0;
        var repeatuntil = null;
        var paymentmode = $('#payment-mode').val();
        var categoryid = $('#category').val();

        var valid = true;

        if (what === null || what === '') {
            valid = false;
        }
        if (date === null || date === '' || date.match(/^\d\d\d\d-\d\d-\d\d$/g) === null) {
            valid = false;
        }
        if (isNaN(amount) || isNaN(payer_id)) {
            valid = false;
        }

        if (valid) {
            var initWhat = what;
            // manage currencies
            var initAmount;
            var currency = null;
            if ($('#bill-currency') && $('#bill-currency').val()) {
                var currencyId = $('#bill-currency').val();
                var currencies = cospend.projects[projectid].currencies;
                for (var i = 0; i < currencies.length; i++) {
                    if (parseInt(currencies[i].id) === parseInt(currencyId)) {
                        currency = currencies[i];
                        break;
                    }
                }
            }
            var total = 0;
            $('.amountinput').each(function() {
                var oneWhat = initWhat;
                var owerId = parseInt($(this).attr('owerid'));
                var amountVal = parseFloat($(this).val());
                if (!isNaN(amountVal) && amountVal > 0.0) {
                    total = total + amountVal;
                    if (currency !== null) {
                        initAmount = amountVal;
                        amountVal = amountVal * currency.exchange_rate;
                        oneWhat += ' ('+initAmount.toFixed(2)+' '+currency.name+')';
                    }
                    createBill(projectid, oneWhat, amountVal, payer_id, date, [owerId], repeat, true,
                               paymentmode, categoryid, repeatallactive, repeatuntil);
                }
            });
            // if something was actually created, clean up
            if (total > 0) {
                // empty bill detail
                $('#billdetail').html('');
                // remove new bill line
                $('.billitem[billid=0]').fadeOut('normal', function() {
                    $(this).remove();
                    if ($('.billitem').length === 0) {
                        $('#bill-list').html('<h2 class="nobill">'+t('cospend', 'No bill yet')+'</h2>');
                    }
                });
                $('.app-content-list').removeClass('showdetails');
            }
            else {
                OC.Notification.showTemporary(t('cospend', 'There is no custom amount'));
            }
        }
        else {
            OC.Notification.showTemporary(t('cospend', 'Invalid values'));
        }
    }

    function getUrlParameter(sParam) {
        var sPageURL = window.location.search.substring(1);
        var sURLVariables = sPageURL.split('&');
        for (var i = 0; i < sURLVariables.length; i++) {
            var sParameterName = sURLVariables[i].split('=');
            if (sParameterName[0] === sParam) {
                return decodeURIComponent(sParameterName[1]);
            }
        }
    }

    $(document).ready(function() {
        cospend.pageIsPublic = (document.URL.indexOf('/cospend/project') !== -1);
        if ( !cospend.pageIsPublic ) {
            restoreOptions();
        }
        else {
            //restoreOptionsFromUrlParams();
            $('#newprojectbutton').hide();
            $('#set-output-div').hide();
            $('#addextprojectbutton').hide();
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

        $('body').on('focus','.shareinput', function(e) {
            $(this).select();
            var projectid = $(this).parent().parent().parent().attr('projectid');
            addUserAutocompletion($(this), projectid);
        });

        $('body').on('click', '.deleteUserShareButton', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var shid = $(this).parent().attr('shid');
            deleteUserShareDb(projectid, shid);
        });

        $('body').on('click', '.deleteGroupShareButton', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var shid = $(this).parent().attr('shid');
            deleteGroupShareDb(projectid, shid);
        });

        $('body').on('click', '.deleteCircleShareButton', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var shid = $(this).parent().attr('shid');
            deleteCircleShareDb(projectid, shid);
        });

        $('body').on('click', '.perm', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var shid = $(this).parent().attr('shid');
            var e = $(this).parent().find('.permEdit').hasClass('permActive');
            var d = $(this).parent().find('.permDelete').hasClass('permActive');
            var c = $(this).parent().find('.permCreate').hasClass('permActive');
            if ($(this).hasClass('permDelete')) {
                editSharePermissionsDb(projectid, shid, e, !d, c);
            }
            else if ($(this).hasClass('permEdit')) {
                editSharePermissionsDb(projectid, shid, !e, d, c);
            }
            else if ($(this).hasClass('permCreate')) {
                editSharePermissionsDb(projectid, shid, e, d, !c);
            }
        });

        $('body').on('click', '.permguest', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var e = $(this).parent().find('.permEdit').hasClass('permActive');
            var d = $(this).parent().find('.permDelete').hasClass('permActive');
            var c = $(this).parent().find('.permCreate').hasClass('permActive');
            if ($(this).hasClass('permDelete')) {
                editGuestPermissionsDb(projectid, e, !d, c);
            }
            else if ($(this).hasClass('permEdit')) {
                editGuestPermissionsDb(projectid, !e, d, c);
            }
            else if ($(this).hasClass('permCreate')) {
                editGuestPermissionsDb(projectid, e, d, !c);
            }
        });

        $('body').on('click', '.shareProjectButton', function(e) {
            var shareDiv = $(this).parent().parent().parent().find('.app-navigation-entry-share');
            if (shareDiv.is(':visible')) {
                shareDiv.slideUp();
                $(this).removeClass('activeButton');
            }
            else {
                shareDiv.slideDown();
                $(this).addClass('activeButton');
                var defaultShareText = t('cospend', 'user or group name');
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
            selectProject($(this).parent());
        });

        $('body').on('click', '.projectitem', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).hasClass('projectitem')) {
                selectProject($(this));
            }
        });

        $('#newprojectbutton').click(function() {
            var div = $('#newprojectdiv');
            if (div.is(':visible')) {
                $(this).removeClass('icon-triangle-s').addClass('icon-triangle-e');
                div.slideUp('normal', function() {
                    $('#newBillButton').fadeIn();
                    $('#addextprojectbutton').fadeIn();
                });
            }
            else {
                $(this).removeClass('icon-triangle-e').addClass('icon-triangle-s');
                div.slideDown('normal', function() {
                    $('#newBillButton').fadeOut();
                    $('#addextprojectbutton').fadeOut();
                    $('#projectidinput').focus().select();
                });
            }
        });

        $('#projectnameinput, #projectidinput, #projectpasswordinput').on('keyup', function(e) {
            if (e.key === 'Enter') {
                var name = $('#projectnameinput').val();
                var id = $('#projectidinput').val();
                var password = $('#projectpasswordinput').val();
                if (name && id && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                    createProject(id, name, password);
                }
                else {
                    OC.Notification.showTemporary(t('cospend', 'Invalid values'));
                }
            }
        });

        $('#newprojectform').submit(function(e) {
            var name = $('#projectnameinput').val();
            var id = $('#projectidinput').val();
            var password = $('#projectpasswordinput').val();
            if (name && id && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                createProject(id, name, password);
            }
            else {
                OC.Notification.showTemporary(t('cospend', 'Invalid values'));
            }
            e.preventDefault();
        });

        $('#createproject').click(function() {
            var name = $('#projectnameinput').val();
            var id = $('#projectidinput').val();
            var password = $('#projectpasswordinput').val();
            if (name && id && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                createProject(id, name, password);
            }
            else {
                OC.Notification.showTemporary(t('cospend', 'Invalid values'));
            }
        });

        $('#addextprojectbutton').click(function() {
            var div = $('#addextprojectdiv');
            if (div.is(':visible')) {
                $(this).removeClass('icon-triangle-s').addClass('icon-triangle-e');
                div.slideUp('normal', function() {
                    $('#newBillButton').fadeIn();
                    $('#newprojectbutton').fadeIn();
                });
            }
            else {
                $(this).removeClass('icon-triangle-e').addClass('icon-triangle-s');
                div.slideDown('normal', function() {
                    $('#newBillButton').fadeOut();
                    $('#newprojectbutton').fadeOut();
                    $('#ncurlinput').focus().select();
                });
            }
        });

        $('#ncurlinput, #extprojectidinput, #extprojectpasswordinput').on('keyup', function(e) {
            if (e.key === 'Enter') {
                var url = $('#ncurlinput').val();
                var id = $('#extprojectidinput').val();
                var password = $('#extprojectpasswordinput').val();
                if (url && id && password && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                    addExtProject(url, id, password);
                }
                else {
                    OC.Notification.showTemporary(t('cospend', 'Invalid values'));
                }
            }
        });

        $('#addextprojectform').submit(function(e) {
            var url = $('#ncurlinput').val();
            var id = $('#extprojectidinput').val();
            var password = $('#extprojectpasswordinput').val();
            if (url && id && password && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                addExtProject(url, id, password);
            }
            else {
                OC.Notification.showTemporary(t('cospend', 'Invalid values'));
            }
            e.preventDefault();
        });

        $('#addextproject').click(function() {
            var url = $('#ncurlinput').val();
            var id = $('#extprojectidinput').val();
            var password = $('#extprojectpasswordinput').val();
            if (url && id && password && id.indexOf('@') === -1 && id.indexOf('/') === -1 && id.indexOf(' ') === -1) {
                addExtProject(url, id, password);
            }
            else {
                OC.Notification.showTemporary(t('cospend', 'Invalid values'));
            }
        });

        $('body').on('click', '.removeExternalProject', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            deleteExternalProject(projectid, true);
        });

        $('body').on('click', '.deleteProject', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            $(this).parent().parent().parent().parent().addClass('deleted');
            cospend.projectDeletionTimer[projectid] = new Timer(function() {
                deleteProject(projectid);
            }, 7000);
        });

        $('body').on('click', '.undoDeleteProject', function(e) {
            var projectid = $(this).parent().parent().attr('projectid');
            $(this).parent().parent().removeClass('deleted');
            cospend.projectDeletionTimer[projectid].pause();
            delete cospend.projectDeletionTimer[projectid];
        });

        $('body').on('click', '.addMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            var name = $('.projectitem[projectid="'+projectid+'"] > a > span').text();

            var newmemberdiv = $('.projectitem[projectid="'+projectid+'"] .newmemberdiv');
            newmemberdiv.show().attr('style', 'display: inline-flex;');
            var defaultMemberName = t('cospend', 'newMemberName');
            newmemberdiv.find('.newmembername').val(defaultMemberName).focus().select();
        });

        $('body').on('click', '.newmemberbutton', function(e) {
            var projectid = $(this).parent().parent().attr('projectid');
            var name = $(this).parent().find('input').val();
            if (projectid && name) {
                createMember(projectid, name);
            }
            else {
                OC.Notification.showTemporary(t('cospend', 'Invalid values'));
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
                    OC.Notification.showTemporary(t('cospend', 'Invalid values'));
                }
            }
        });

        $('body').on('click', '.renameMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var mid = $(this).parent().parent().parent().parent().attr('memberid');
            var name = cospend.members[projectid][mid].name;
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(name).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            cospend.memberEditionMode = MEMBER_NAME_EDITION;
        });

        $('body').on('click', '.editWeightMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var mid = $(this).parent().parent().parent().parent().attr('memberid');
            var weight = cospend.members[projectid][mid].weight;
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(weight).focus().select();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            cospend.memberEditionMode = MEMBER_WEIGHT_EDITION;
        });

        $('body').on('click', '.editMemberClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editMemberInput', function(e) {
            if (e.key === 'Enter') {
                var memberid = $(this).parent().parent().parent().attr('memberid');
                var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
                var newName;
                if (cospend.memberEditionMode === MEMBER_NAME_EDITION) {
                    newName = $(this).val();
                    editMember(projectid, memberid, newName, null, null);
                }
                else if (cospend.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                    var newWeight = $(this).val();
                    newName = cospend.members[projectid][memberid].name;
                    editMember(projectid, memberid, newName, newWeight, null);
                }
            }
        });

        $('body').on('click', '.editMemberOk', function(e) {
            var memberid = $(this).parent().parent().parent().attr('memberid');
            var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            var newName;
            if (cospend.memberEditionMode === MEMBER_NAME_EDITION) {
                newName = $(this).parent().find('.editMemberInput').val();
                editMember(projectid, memberid, newName, null, null);
            }
            else if (cospend.memberEditionMode === MEMBER_WEIGHT_EDITION) {
                var newWeight = $(this).parent().find('.editMemberInput').val();
                newName = $(this).parent().parent().parent().find('b.memberName').text();
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
            var name = cospend.projects[projectid].name;
            $(this).parent().parent().parent().parent().find('.editProjectInput').val(name).attr('type', 'text').focus().select();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            cospend.projectEditionMode = PROJECT_NAME_EDITION;
        });

        $('body').on('click', '.editProjectPassword', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            $(this).parent().parent().parent().parent().find('.editProjectInput').attr('type', 'password').val('').focus();
            $('#projectlist > li').removeClass('editing');
            $(this).parent().parent().parent().parent().removeClass('open').addClass('editing');
            cospend.projectEditionMode = PROJECT_PASSWORD_EDITION;
        });

        $('body').on('click', '.editProjectClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });

        $('body').on('keyup', '.editProjectInput', function(e) {
            if (e.key === 'Enter') {
                var newName;
                var projectid = $(this).parent().parent().parent().attr('projectid');
                if (cospend.projectEditionMode === PROJECT_NAME_EDITION) {
                    newName = $(this).val();
                    editProject(projectid, newName, null, null);
                }
                else if (cospend.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                    var newPassword = $(this).val();
                    newName = $(this).parent().parent().parent().find('>a span').text();
                    editProject(projectid, newName, null, newPassword);
                }
            }
        });

        $('body').on('click', '.editProjectOk', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var newName;
            if (cospend.projectEditionMode === PROJECT_NAME_EDITION) {
                newName = $(this).parent().find('.editProjectInput').val();
                editProject(projectid, newName, null, null);
            }
            else if (cospend.projectEditionMode === PROJECT_PASSWORD_EDITION) {
                var newPassword = $(this).parent().find('.editProjectInput').val();
                newName = $(this).parent().parent().parent().find('>a span').text();
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

        // what and amount : delay on edition
        $('body').on('keyup paste change', '.input-bill-what', delay(function(e) {
            onBillEdited();
        }, 2000));
        $('body').on('keyup paste change', '.input-bill-amount', delay(function(e) {
            onBillEdited(true);
        }, 2000));

        // other bill fields : direct on edition
        $('body').on('change', '.input-bill-date, .input-bill-repeatuntil, #billdetail .bill-form select', function(e) {
            onBillEdited();
        });
        $('body').on('click', '#repeatallactive', function(e) {
            onBillEdited();
        });

        // show/hide repeatallactive
        $('body').on('change', '#repeatbill', function(e) {
            if ($(this).val() === 'n') {
                $('#repeatallactive').hide();
                $('label[for=repeatallactive]').hide();
                $('#repeatuntil').hide();
                $('label[for=repeatuntil]').hide();
            }
            else {
                $('#repeatallactive').show();
                $('label[for=repeatallactive]').show();
                $('#repeatuntil').show();
                $('label[for=repeatuntil]').show();
            }
        });

        $('body').on('change', '#billdetail .bill-form .bill-owers input[type=checkbox]', function(e) {
            var billtype = $('#billtype').val();
            if (billtype === 'perso') {
                if ($(this).is(':checked')) {
                    $(this).parent().find('input[type=number]').show();
                }
                else {
                    $(this).parent().find('input[type=number]').hide();
                }
            }
            else {
                onBillEdited();
            }
        });

        $('body').on('click', '#owerAll', function(e) {
            var billtype = $('#billtype').val();
            var projectid = $(this).parent().parent().parent().parent().parent().find('.bill-title').attr('projectid');
            for (var memberid in cospend.members[projectid]) {
                if (cospend.members[projectid][memberid].activated) {
                    $('.bill-owers input[owerid='+memberid+']').prop('checked', true);
                }
            }
            if (billtype === 'perso') {
                $('.bill-owers .amountinput').show();
            }
            //$('.owerEntry input').prop('checked', true);
            onBillEdited();
        });

        $('body').on('click', '#owerNone', function(e) {
            var billtype = $('#billtype').val();
            var projectid = $(this).parent().parent().parent().parent().parent().find('.bill-title').attr('projectid');
            for (var memberid in cospend.members[projectid]) {
                if (cospend.members[projectid][memberid].activated) {
                    $('.bill-owers input[owerid='+memberid+']').prop('checked', false);
                }
            }
            if (billtype === 'perso') {
                $('.bill-owers .amountinput').hide();
            }
            //$('.owerEntry input').prop('checked', false);
            onBillEdited();
        });

        $('body').on('click', '.undoDeleteBill', function(e) {
            var billid = $(this).parent().attr('billid');
            cospend.billDeletionTimer[billid].pause();
            delete cospend.billDeletionTimer[billid];
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
                cospend.billDeletionTimer[billid] = new Timer(function() {
                    deleteBill(projectid, billid);
                }, 7000);
            }
            else {
                if ($('.bill-title').length > 0 && $('.bill-title').attr('billid') === billid) {
                    $('#billdetail').html('');
                }
                $(this).parent().fadeOut('normal', function() {
                    $(this).remove();
                    if ($('.billitem').length === 0) {
                        $('#bill-list').html('<h2 class="nobill">'+t('cospend', 'No bill yet')+'</h2>');
                    }
                });
            }
        });

        $('body').on('click', '#newBillButton', function(e) {
            var projectid = cospend.currentProjectId;
            var activatedMembers = [];
            for (var mid in cospend.members[projectid]) {
                if (cospend.members[projectid][mid].activated) {
                    activatedMembers.push(mid);
                }
            }
            if (activatedMembers.length > 1) {
                if (cospend.currentProjectId !== null && $('.billitem[billid=0]').length === 0) {
                    var bill = {
                        id: 0,
                        what: t('cospend', 'New Bill'),
                        date: moment().format('YYYY-MM-DD'),
                        amount: 0.0,
                        payer_id: 0,
                        repeat: 'n',
                        owers: []
                    };
                    addBill(projectid, bill);
                }
                displayBill(projectid, 0);
            }
            else {
                OC.Notification.showTemporary(t('cospend', '2 active members are required to create a bill'));
            }
        });

        $('body').on('focus', '.input-bill-what, .input-bill-amount, #projectidinput, #projectnameinput, #projectpasswordinput', function(e) {
            $(this).select();
        });

        $('body').on('click', '.moneyBusterProjectUrl', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectMoneyBusterLink(projectid);
        });

        $('body').on('click', '.getProjectStats', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectStatistics(projectid, null, null, null, -100);
        });

        $('body').on('click', '.manageProjectCurrencies', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectCurrencies(projectid);
        });

        $('body').on('click', '.manageProjectCategories', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectCategories(projectid);
        });

        $('body').on('change', '#date-min-stats, #date-max-stats, #payment-mode-stats, ' +
                               '#category-stats, #amount-min-stats, #amount-max-stats, ' +
                               '#showDisabled, #currency-stats', function(e) {
            var projectid = cospend.currentProjectId;
            var dateMin = $('#date-min-stats').val();
            var dateMax = $('#date-max-stats').val();
            var paymentMode = $('#payment-mode-stats').val();
            var category = $('#category-stats').val();
            var amountMin = $('#amount-min-stats').val();
            var amountMax = $('#amount-max-stats').val();
            var showDisabled = $('#showDisabled').is(':checked');
            var currencyId = $('#currency-stats').val();
            getProjectStatistics(projectid, dateMin, dateMax, paymentMode, category, amountMin, amountMax, showDisabled, currencyId);
        });

        $('body').on('click', '.getProjectSettlement', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectSettlement(projectid);
        });

        $('body').on('click', '.copyProjectGuestLink', function() {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            var project = cospend.projects[projectid];
            var guestLink;
            if (project.external) {
                var id = projectid.split('@')[0];
                guestLink = project.ncurl.replace(/\/$/, '') + '/index.php/apps/cospend/loginproject/' + id;
            }
            else {
                guestLink = OC.generateUrl('/apps/cospend/loginproject/'+projectid);
                guestLink = window.location.protocol + '//' + window.location.host + guestLink;
            }
            var dummy = $('<input id="dummycopy">').val(guestLink).appendTo('body').select();
            document.execCommand('copy');
            $('#dummycopy').remove();
            OC.Notification.showTemporary(t('cospend', 'Guest link for \'{pid}\' copied to clipboard', {pid: projectid}));
        });

        var guestLink = OC.generateUrl('/apps/cospend/login');
        guestLink = window.location.protocol + '//' + window.location.host + guestLink;
        $('#generalGuestLinkButton').attr('title', guestLink);

        $('body').on('click', '#generalGuestLinkButton', function() {
            var guestLink = OC.generateUrl('/apps/cospend/login');
            guestLink = window.location.protocol + '//' + window.location.host + guestLink;
            var dummy = $('<input id="dummycopy">').val(guestLink).appendTo('body').select();
            document.execCommand('copy');
            $('#dummycopy').remove();
            OC.Notification.showTemporary(t('cospend', 'Guest link copied to clipboard'));
        });

        $('body').on('click', '#app-details-toggle', function() {
            $('.app-content-list').removeClass('showdetails');
        });

        $('body').on('click', '#addFileLinkButton', function() {
            OC.dialogs.filepicker(
                  t('cospend', 'Choose file'),
                  function(targetPath) {
                      generatePublicLinkToFile(targetPath);
                  },
                  false, null, true
              );
        });

        $('body').on('click', '#importProjectButton', function() {
            OC.dialogs.filepicker(
                  t('cospend', 'Choose csv project file'),
                  function(targetPath) {
                      importProject(targetPath);
                  },
                  false,
                  ['text/csv'],
                  true
              );
        });

        $('body').on('click', '#importSWProjectButton', function() {
            OC.dialogs.filepicker(
                  t('cospend', 'Choose SplitWise project file'),
                  function(targetPath) {
                      importSWProject(targetPath);
                  },
                  false,
                  ['text/csv'],
                  true
              );
        });

        $('body').on('click', '.exportProject', function() {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            exportProject(projectid);
        });

        $('body').on('click', '.autoexportSelect, .permguest', function(e) {
            e.stopPropagation();
        });

        $('body').on('change', '.autoexportSelect', function(e) {
            var newval = $(this).val();
            var projectid = $(this).parent().parent().parent().parent().parent().attr('projectid');
            var projectName = getProjectName(projectid);
            editProject(projectid, projectName, null, null, newval);
            $(this).parent().click();
        });

        $('body').on('click', '.exportStats', function() {
            var projectid = $(this).attr('projectid');

            var dateMin = $('#date-min-stats').val();
            var dateMax = $('#date-max-stats').val();
            var paymentMode = $('#payment-mode-stats').val();
            var category = $('#category-stats').val();
            var amountMin = $('#amount-min-stats').val();
            var amountMax = $('#amount-max-stats').val();
            var showDisabled = $('#showDisabled').is(':checked');
            var currencyId = $('#currency-stats').val();

            exportStatistics(projectid, dateMin, dateMax, paymentMode, category, amountMin, amountMax, showDisabled, currencyId);
        });

        $('body').on('click', '.exportSettlement', function() {
            var projectid = $(this).attr('projectid');
            exportSettlement(projectid);
        });

        $('body').on('click', '.autoSettlement', function() {
            var projectid = $(this).attr('projectid');
            autoSettlement(projectid);
        });

        $('body').on('click', '#modehintbutton', function() {
            var billtype = $('#billtype').val();
            if (billtype === 'normal') {
                if ($('.modenormal').is(':visible')) {
                    $('.modenormal').slideUp();
                }
                else {
                    $('.modenormal').slideDown();
                }
                $('.modecustom').slideUp();
                $('.modeperso').slideUp();
            }
            else if (billtype === 'perso') {
                if ($('.modeperso').is(':visible')) {
                    $('.modeperso').slideUp();
                }
                else {
                    $('.modeperso').slideDown();
                }
                $('.modecustom').slideUp();
                $('.modenormal').slideUp();
            }
            else if (billtype === 'custom') {
                if ($('.modecustom').is(':visible')) {
                    $('.modecustom').slideUp();
                }
                else {
                    $('.modecustom').slideDown();
                }
                $('.modenormal').slideUp();
                $('.modeperso').slideUp();
            }
        });

    $('body').on('change', '#billtype', function() {
        $('.modehint').slideUp();
            var owerValidateStr = t('cospend', 'Create the bills');
            var billtype = $(this).val();
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
                    $('#repeatallactive').show();
                    $('label[for=repeatallactive]').show();
                    $('#repeatuntil').show();
                    $('label[for=repeatuntil]').show();
                }
            }
            else if (billtype === 'custom') {
                $('#owerNone').hide();
                $('#owerAll').hide();
                $('.bill-owers .checkbox').hide();
                $('.bill-owers .checkboxlabel').hide();
                $('.bill-owers .numberlabel').show();
                $('.bill-owers input[type=number]').show();
                updateCustomAmount();
                $('#amount').prop('disabled', true);
                $('#repeatbill').val('n').prop('disabled', true);
                $('#repeatallactive').hide();
                $('label[for=repeatallactive]').hide();
                $('#repeatuntil').hide();
                $('label[for=repeatuntil]').hide();
            }
            else if (billtype === 'perso') {
                $('#owerNone').show();
                $('#owerAll').show();
                $('.bill-owers .checkbox').show();
                $('.bill-owers .checkboxlabel').show();
                $('.bill-owers .numberlabel').hide();
                $('.bill-owers input[type=number]').hide();
                $('.bill-owers .checkbox').each(function() {
                    if ($(this).is(':checked')) {
                        $(this).parent().find('input[type=number]').show();
                    }
                });
                $('#amount').prop('disabled', false);
                $('#repeatbill').val('n').prop('disabled', true);
                $('#repeatallactive').hide();
                $('label[for=repeatallactive]').hide();
                $('#repeatuntil').hide();
                $('label[for=repeatuntil]').hide();
            }
            $('#owerValidateText').text(owerValidateStr);
        });

        $('body').on('paste change', '.amountinput', function(e) {
            var billtype = $('#billtype').val();
            if (billtype === 'custom') {
                updateCustomAmount();
            }
        });

        $('body').on('keyup','.amountinput', function(e) {
            var billtype = $('#billtype').val();
            if (billtype === 'custom') {
                updateCustomAmount();
                if (e.key === 'Enter') {
                    createCustomAmountBill();
                }
            }
            else if (billtype === 'perso') {
                if (e.key === 'Enter') {
                    createEquiPersoBill();
                }
            }
        });

        $('body').on('click', '#owerValidate', function() {
            var billtype = $('#billtype').val();
            if (billtype === 'custom') {
                updateCustomAmount();
                createCustomAmountBill();
            }
            else if (billtype === 'perso') {
                createEquiPersoBill();
            }
            else if (billtype === 'normal') {
                createNormalBill();
            }
        });

        $('body').on('click', '#changeOutputButton', function() {
            OC.dialogs.filepicker(
                t('maps', 'Choose where to write output files (stats, settlement, export)'),
                function(targetPath) {
                    if (targetPath === '') {
                        targetPath = '/';
                    }
                    saveOptionValue({outputDirectory: targetPath});
                    $('#outputDirectory').text(targetPath)
                },
                false,
                'httpd/unix-directory',
                true
            );
        });

        $('body').on('change', '#categoryMemberSelect', function(e) {
            displayCategoryMemberChart();
        });

        $('body').on('change', '#memberPolarSelect', function(e) {
            displayMemberPolarChart();
        });

        $('body').on('click', '.memberAvatar', function(e) {
            var projectid = $(this).parent().parent().parent().attr('projectid');
            var memberid = $(this).parent().attr('memberid');
            askChangeMemberColor(projectid, memberid);
        });

        $('body').on('click', '.editColorMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var memberid = $(this).parent().parent().parent().parent().attr('memberid');
            askChangeMemberColor(projectid, memberid);
        });

        $('body').on('change', '#membercolorinput', function(e) {
            okColor();
        });

        // main currency
        $('body').on('click', '.editMainCurrency', function(e) {
            $('#main-currency-label').hide();
            $('#main-currency-edit').show();
            $('.editMainCurrencyInput').focus().select();
        });

        $('body').on('click', '.editMainCurrencyOk', function(e) {
            var projectid = $('#curTitle').attr('projectid');
            var value = $('.editMainCurrencyInput').val();
            var projectName = cospend.projects[projectid].name;
            editProject(projectid, projectName, null, null, null, value);
        });
        $('body').on('keyup', '.editMainCurrencyInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $('#curTitle').attr('projectid');
                var value = $('.editMainCurrencyInput').val();
                var projectName = cospend.projects[projectid].name;
                editProject(projectid, projectName, null, null, null, value);
            }
        });

        $('body').on('click', '.editMainCurrencyClose', function(e) {
            $('#main-currency-label').show();
            $('#main-currency-edit').hide();
        });

        // other currencies
        $('body').on('click', '.addCurrencyOk', function(e) {
            var projectid = $('#curTitle').attr('projectid');
            var name = $('#addCurrencyNameInput').val();
            if (name === null || name === '') {
                OC.Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
                return;
            }
            var rate = parseFloat($('#addCurrencyRateInput').val());
            if (isNaN(rate)) {
                OC.Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
                return;
            }
            addCurrencyDb(projectid, name, rate);
        });

        $('body').on('keyup', '#addCurrencyNameInput, #addCurrencyRateInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $('#curTitle').attr('projectid');
                var name = $('#addCurrencyNameInput').val();
                if (name === null || name === '') {
                    OC.Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
                    return;
                }
                var rate = parseFloat($('#addCurrencyRateInput').val());
                if (isNaN(rate)) {
                    OC.Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
                    return;
                }
                addCurrencyDb(projectid, name, rate);
            }
        });

        $('body').on('click', '.deleteOneCurrency', function(e) {
            var projectid = $('#curTitle').attr('projectid');
            var currencyId = $(this).parent().parent().attr('currencyid');
            if ($(this).hasClass('icon-history')) {
                $(this).removeClass('icon-history').addClass('icon-delete');
                cospend.currencyDeletionTimer[currencyId].pause();
                delete cospend.currencyDeletionTimer[currencyId];
            }
            else {
                $(this).addClass('icon-history').removeClass('icon-delete');
                cospend.currencyDeletionTimer[currencyId] = new Timer(function() {
                    deleteCurrencyDb(projectid, currencyId);
                }, 7000);
            }
        });

        $('body').on('click', '.editOneCurrency', function(e) {
            $(this).parent().hide();
            $(this).parent().parent().find('.one-currency-edit').show()
            .css('display', 'grid')
            .find('.editCurrencyNameInput').focus().select();
        });

        $('body').on('click', '.editCurrencyOk', function(e) {
            var projectid = $('#curTitle').attr('projectid');
            var currencyId = $(this).parent().parent().attr('currencyid');
            var name = $(this).parent().find('.editCurrencyNameInput').val();
            if (name === null || name === '') {
                OC.Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
                return;
            }
            var rate = parseFloat($(this).parent().find('.editCurrencyRateInput').val());
            if (isNaN(rate)) {
                OC.Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
                return;
            }
            editCurrencyDb(projectid, currencyId, name, rate);
        });

        $('body').on('keyup', '.editCurrencyNameInput, .editCurrencyRateInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $('#curTitle').attr('projectid');
                var currencyId = $(this).parent().parent().attr('currencyid');
                var name = $(this).parent().find('.editCurrencyNameInput').val();
                if (name === null || name === '') {
                    OC.Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
                    return;
                }
                var rate = parseFloat($(this).parent().find('.editCurrencyRateInput').val());
                if (isNaN(rate)) {
                    OC.Notification.showTemporary(t('cospend', 'Exchange rate should be a number'));
                    return;
                }
                editCurrencyDb(projectid, currencyId, name, rate);
            }
        });

        $('body').on('click', '.editCurrencyClose', function(e) {
            $(this).parent().hide();
            $(this).parent().parent().find('.one-currency-label').show();
        });

        // manage categories TODO
        $('body').on('click', '.addCategoryOk', function(e) {
            var projectid = $('#catTitle').attr('projectid');
            var name = $('#addCategoryNameInput').val();
            if (name === null || name === '') {
                OC.Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                return;
            }
            var icon = $('#addCategoryIconInput').val();
            if (icon === null || icon === '') {
                OC.Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                return;
            }
            var color = $('#addCategoryColorInput').val();
            if (color === null || color === '') {
                OC.Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                return;
            }
            addCategoryDb(projectid, name, icon, color);
        });

        $('body').on('keyup', '#addCategoryNameInput, #addCategoryIconInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $('#catTitle').attr('projectid');
                var name = $('#addCategoryNameInput').val();
                if (name === null || name === '') {
                    OC.Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                    return;
                }
                var icon = $('#addCategoryIconInput').val();
                if (icon === null || icon === '') {
                    OC.Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                    return;
                }
                var color = $('#addCategoryColorInput').val();
                if (color === null || color === '') {
                    OC.Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                    return;
                }
                addCategoryDb(projectid, name, icon, color);
            }
        });

        $('body').on('click', '.deleteOneCategory', function(e) {
            var projectid = $('#catTitle').attr('projectid');
            var categoryId = $(this).parent().parent().attr('categoryid');
            if ($(this).hasClass('icon-history')) {
                $(this).removeClass('icon-history').addClass('icon-delete');
                cospend.categoryDeletionTimer[categoryId].pause();
                delete cospend.categoryDeletionTimer[categoryId];
            }
            else {
                $(this).addClass('icon-history').removeClass('icon-delete');
                cospend.categoryDeletionTimer[categoryId] = new Timer(function() {
                    deleteCategoryDb(projectid, categoryId);
                }, 7000);
            }
        });

        $('body').on('click', '.editOneCategory', function(e) {
            $(this).parent().hide();
            $(this).parent().parent().find('.one-category-edit').show()
            .css('display', 'grid')
            .find('.editCategoryNameInput').focus().select();
        });

        $('body').on('click', '.editCategoryOk', function(e) {
            var projectid = $('#catTitle').attr('projectid');
            var categoryId = $(this).parent().parent().attr('categoryid');
            var name = $(this).parent().find('.editCategoryNameInput').val();
            if (name === null || name === '') {
                OC.Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                return;
            }
            var icon = $(this).parent().find('.editCategoryIconInput').val();
            if (icon === null || icon === '') {
                OC.Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                return;
            }
            var color = $(this).parent().find('.editCategoryColorInput').val();
            if (color === null || color === '') {
                OC.Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                return;
            }
            editCategoryDb(projectid, categoryId, name, icon, color);
        });

        $('body').on('keyup', '.editCategoryNameInput, .editCategoryIconInput', function(e) {
            if (e.key === 'Enter') {
                var projectid = $('#catTitle').attr('projectid');
                var categoryId = $(this).parent().parent().attr('categoryid');
                var name = $(this).parent().find('.editCategoryNameInput').val();
                if (name === null || name === '') {
                    OC.Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                    return;
                }
                var icon = $(this).parent().find('.editCategoryIconInput').val();
                if (icon === null || icon === '') {
                    OC.Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                    return;
                }
                var color = $(this).parent().find('.editCategoryColorInput').val();
                if (color === null || color === '') {
                    OC.Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                    return;
                }
                editCategoryDb(projectid, categoryId, name, icon, color);
            }
        });
        $('body').on('click', '.one-category-label-color', function (e) {
            e.preventDefault();
        });

        $('body').on('click', '.editCategoryClose', function(e) {
            $(this).parent().hide();
            $(this).parent().parent().find('.one-category-label').show();
        });

        $('body').on('click', '.owerEntry .owerAvatar', function (e) {
            var billId = parseInt($('#billdetail .bill-title').attr('billid'));
            var billType = $('#billtype').val();
            if (billId !== 0 || billType === 'normal' || billType === 'perso') {
                $(this).parent().find('input').click();
            }
        });

        if (OCA.Theming) {
            var c = OCA.Theming.color;
            // invalid color
            if (!c || (c.length !== 4 && c.length !== 7)) {
                cospend.themeColor = '#0082C9';
            }
            // compact
            else if (c.length === 4) {
                cospend.themeColor = '#'+c[1]+c[1]+c[2]+c[2]+c[3]+c[3];
            }
            // normal
            else if (c.length === 7) {
                cospend.themeColor = c;
            }
        }
        else {
            cospend.themeColor = '#0082C9';
        }
        cospend.themeColorDark = hexToDarkerHex(cospend.themeColor);

        // last thing to do : get the projects
        getProjects();
    }

})(jQuery, OC);
