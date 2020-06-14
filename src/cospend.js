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

import Vue from 'vue';
import './bootstrap';
import App from './App';
import * as Notification from './notification';
import * as Chart from 'chart.js/dist/Chart';
import 'chart.js/dist/Chart.css';
import {generateUrl} from '@nextcloud/router';
import {
    checkKey,
    hexToDarkerHex,
    saveOptionValue,
} from './utils';
import {
    getProjectMoneyBusterLink,
    getProjects,
    projectEvents
} from './project';
import {
    categoryEvents
} from './category';
import {
    currencyEvents
} from './currency';
import {
    memberEvents
} from './member';
import {
    shareEvents
} from './share';
import {
    billEvents
} from './bill';
import {
    importProject,
    importSWProject
} from './importExport';
import cospend from './state';


(function($, OC) {
    'use strict';

    Chart.plugins.register({
        beforeRender: function(chart) {
            if (chart.config.options.showAllTooltips) {
                // create an array of tooltips
                // we can't use the chart tooltip because there is only one tooltip per chart
                chart.pluginTooltips = [];
                chart.config.data.datasets.forEach(function(dataset, i) {
                    chart.getDatasetMeta(i).data.forEach(function(sector, j) {
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
        afterDraw: function(chart, easing) {
            if (chart.config.options.showAllTooltips) {
                // we don't want the permanent tooltips to animate, so don't do anything till the animation runs atleast once
                if (!chart.allTooltipsOnce) {
                    if (easing !== 1) {
                        return;
                    }
                    chart.allTooltipsOnce = true;
                }

                // turn on tooltips
                chart.options.tooltips.enabled = true;
                Chart.helpers.each(chart.pluginTooltips, function(tooltip) {
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

    // trick to always show public link item: replace default autocomplete filter function
    const origFilter = $.ui.autocomplete.filter;
    $.ui.autocomplete.filter = function(array, term) {
        if (cospend.pubLinkData.projectid) {
            const result = [cospend.pubLinkData];
            return result.concat(origFilter(array, term));
        } else {
            return origFilter(array, term);
        }
    };

    function restoreOptions() {
        const url = generateUrl('/apps/cospend/option-values');
        const req = {};
        let optionsValues = {};
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function(response) {
            optionsValues = response.values;
            if (optionsValues) {
                for (const k in optionsValues) {
                    if (k === 'selectedProject') {
                        cospend.restoredCurrentProjectId = optionsValues[k];
                    } else if (k === 'outputDirectory') {
                        $('#outputDirectory').text(optionsValues[k]);
                    }
                }
            }
            main();
        }).fail(function() {
            Notification.showTemporary(
                t('cospend', 'Failed to restore options values')
            );
        });
    }

    $(document).ready(function() {
        cospend.pageIsPublic = (document.URL.indexOf('/cospend/project') !== -1 || document.URL.indexOf('/cospend/s/') !== -1);
        if (!cospend.pageIsPublic) {
            restoreOptions();
        } else {
            //restoreOptionsFromUrlParams();
            $('#newprojectbutton').hide();
            $('#set-output-div').hide();
            $('#importProjectButton').hide();
            $('#importSWProjectButton').hide();
            cospend.projectid = $('#projectid').text();
            cospend.password = $('#password').text();
            cospend.restoredCurrentProjectId = cospend.projectid;
            $('#projectid').html('');
            $('#password').html('');
            main();
        }
        if (OCA.Theming) {
            const c = OCA.Theming.color;
            // invalid color
            if (!c || (c.length !== 4 && c.length !== 7)) {
                cospend.themeColor = '#0082C9';
            }
            // compact
            else if (c.length === 4) {
                cospend.themeColor = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
            }
            // normal
            else if (c.length === 7) {
                cospend.themeColor = c;
            }
        } else {
            cospend.themeColor = '#0082C9';
        }
        cospend.themeColorDark = hexToDarkerHex(cospend.themeColor);
    });

    function main() {
        new Vue({
            el: "#content",
            render: h => h(App),
        });
    }

    function mainOld() {
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

        $('body').on('click', '.projectMenuButton, .memberMenuButton', function() {
            const wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });

        $('body').on('focus', '.input-bill-what, .input-bill-amount, #projectnameinput, #projectpasswordinput', function() {
            $(this).select();
        });

        $('body').on('click', '.moneyBusterProjectUrl', function() {
            const projectid = $(this).parent().parent().parent().parent().attr('projectid');
            getProjectMoneyBusterLink(projectid);
        });

        categoryEvents();
        currencyEvents();
        billEvents();
        memberEvents();
        projectEvents();
        shareEvents();

        let guestLink = generateUrl('/apps/cospend/login');
        guestLink = window.location.protocol + '//' + window.location.host + guestLink;
        $('#generalGuestLinkButton').attr('title', guestLink);

        $('body').on('click', '#generalGuestLinkButton', function() {
            let guestLink = generateUrl('/apps/cospend/login');
            guestLink = window.location.protocol + '//' + window.location.host + guestLink;
            $('<input id="dummycopy">').val(guestLink).appendTo('body').select();
            document.execCommand('copy');
            $('#dummycopy').remove();
            Notification.showTemporary(t('cospend', 'Guest link copied to clipboard'));
        });

        $('body').on('click', '#app-details-toggle', function() {
            $('.app-content-list').removeClass('showdetails');
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

        $('body').on('click', '#changeOutputButton', function() {
            OC.dialogs.filepicker(
                t('maps', 'Choose where to write output files (stats, settlement, export)'),
                function(targetPath) {
                    if (targetPath === '') {
                        targetPath = '/';
                    }
                    saveOptionValue({outputDirectory: targetPath});
                    $('#outputDirectory').text(targetPath);
                },
                false,
                'httpd/unix-directory',
                true
            );
        });

        // context menu (right click)
        $('body').on('contextmenu',
            '.memberitem > .app-navigation-entry-utils, .memberitem > a, .memberitem .memberAvatar, ' +
            '.shareitem > .app-navigation-entry-utils, .shareitem > a, ' +
            '.projectitem > .app-navigation-entry-utils, .projectitem > a ',
            function() {
                const menu = $(this).parent().find('> .app-navigation-entry-menu');
                const wasOpen = menu.hasClass('open');
                $('.app-navigation-entry-menu.open').removeClass('open');
                if (!wasOpen) {
                    menu.addClass('open');
                }
                return false;
            }
        );

        // right click on expand icon
        $('body').on('contextmenu', '.projectitem', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).hasClass('projectitem')) {
                const menu = $(this).find('> .app-navigation-entry-menu');
                const wasOpen = menu.hasClass('open');
                $('.app-navigation-entry-menu.open').removeClass('open');
                if (!wasOpen) {
                    menu.addClass('open');
                }
                return false;
            }
        });


        // last thing to do : get the projects
        getProjects();
    }

})(jQuery, OC);
