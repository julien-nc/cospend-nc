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

    var spend = {
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
            console.log(response);
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
        var li = `<li class="projectitem collapsible" projectid="${projectid}"><a class="icon-folder" href="#" title="${projectid}">
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
            <div class="app-navigation-entry-menu">
                <ul>
                    <li>
                        <a href="#">
                            <span class="icon-add"></span>
                            <span>Add member</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="icon-rename"></span>
                            <span>Edit</span>
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
            balanceStr = '<b class="balancePositive">+'+balance.toFixed(2)+'</b>';
        }
        else {
            balanceStr = '<b class="balanceNegative">'+balance.toFixed(2)+'</b>';
        }

        var li = `<li memberid="${member.id}"><a class="icon-user" href="#">
                <span>${member.name} (x${member.weight}) ${balanceStr}</span>
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
                        <a href="#">
                            <span class="icon-rename"></span>
                            <span>Rename</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
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
        </li>`;

        $(li).appendTo('#projectlist li.projectitem[projectid='+projectid+'] .memberlist');
    }

    $(document).ready(function() {
        spend.pageIsPublic = (document.URL.indexOf('/whatever') !== -1);
        if ( !spend.pageIsPublic ) {
            //restoreOptions();
        }
        else {
            //restoreOptionsFromUrlParams();
        }
        main();
    });

    function main() {

        // get key events
        document.onkeydown = checkKey;

        window.onclick = function(event) {
            if (!event.target.matches('.app-navigation-entry-utils-menu-button button')) {
                console.log(event.target);
                $('.app-navigation-entry-menu.open').removeClass('open');
            }
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

        // last thing to do : get the projects
        if (!spend.pageIsPublic) {
            getProjects();
        }
        else {
        }
    }

})(jQuery, OC);
