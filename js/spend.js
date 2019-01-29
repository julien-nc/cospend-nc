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
        projectEditionMode: null
    };

    //////////////// UTILS /////////////////////

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
            $(this).removeClass('icon-triangle-s').addClass('icon-triangle-e');
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
                weight: 1
            };
            addMember(projectid, member, 0);
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
            }
            if (newWeight) {
                memberLine.find('b.memberWeight').text(newWeight);
            }
            // remove editing mode
            memberLine.removeClass('editing');
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to edit member') + ' ' + response.responseText);
        });
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
            }
            // remove editing mode
            projectLine.removeClass('editing');
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
        }).always(function() {
        }).fail(function(response) {
            OC.Notification.showTemporary(t('spend', 'Failed to delete project') + ' ' + response.responseText);
        });
    }

    function getProjects() {
        var req = {
        };
        var url = OC.generateUrl('/apps/spend/getProjects');
        spend.currentGetProjectsAjax = $.ajax({
            type: 'POST',
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
            for (var i = 0; i < response.length; i++) {
                addProject(response[i]);
            }
        }).always(function() {
            spend.currentGetProjectsAjax = null;
        }).fail(function() {
            OC.Notification.showTemporary(t('spend', 'Failed to contact server to get projects'));
        });
    }

    function addProject(project) {

        var name = project.name;
        var projectid = project.id;
        var projectSelected = '';
        if (spend.restoredSelectedProjectId === projectid) {
            projectSelected = ' open';
        }
        var li = `<li class="projectitem collapsible${projectSelected}" projectid="${projectid}"><a class="icon-folder" href="#" title="${projectid}">
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
                            <span>Add member</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="editProjectName">
                            <span class="icon-rename"></span>
                            <span>Rename</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="editProjectPassword">
                            <span class="icon-rename"></span>
                            <span>Change password</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="deleteProject">
                            <span class="icon-delete"></span>
                            <span>Delete</span>
                        </a>
                    </li>
                </ul>
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

        var li = `<li memberid="${member.id}"><a class="icon-user" href="#">
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
                            <span>Rename</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="editWeightMember">
                            <span class="icon-rename"></span>
                            <span>Change weight</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="icon-delete"></span>
                            <span>Remove</span>
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
        spend.pageIsPublic = (document.URL.indexOf('/whatever') !== -1);
        if ( !spend.pageIsPublic ) {
            restoreOptions();
        }
        else {
            //restoreOptionsFromUrlParams();
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
            var id = $(this).parent().parent().parent().parent().attr('projectid');
            deleteProject(id);
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
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(name).focus();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            spend.memberEditionMode = MEMBER_NAME_EDITION;
        });

        $('body').on('click', '.editWeightMember', function(e) {
            var projectid = $(this).parent().parent().parent().parent().parent().parent().attr('projectid');
            var weight = $(this).parent().parent().parent().parent().find('a > span > b.memberWeight').text();
            $(this).parent().parent().parent().parent().find('.editMemberInput').val(weight).focus();
            $('.memberlist li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
            spend.memberEditionMode = MEMBER_WEIGHT_EDITION;
        });

        $('body').on('click', '.editMemberClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
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

        $('body').on('click', '.editProjectName', function(e) {
            var projectid = $(this).parent().parent().parent().parent().attr('projectid');
            var name = $(this).parent().parent().parent().parent().find('>a > span').text();
            $(this).parent().parent().parent().parent().find('.editProjectInput').val(name).attr('type', 'text').focus();
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

        // last thing to do : get the projects
        if (!spend.pageIsPublic) {
            getProjects();
        }
        else {
        }
    }

})(jQuery, OC);
