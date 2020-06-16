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
import vueAwesomeCountdown from 'vue-awesome-countdown';
Vue.use(vueAwesomeCountdown, 'vac');
import VueClipboard from 'vue-clipboard2'
Vue.use(VueClipboard);
import SmartTable from 'vuejs-smart-table'
Vue.use(SmartTable)
import {
    showSuccess,
    showInfo,
    showError,
} from '@nextcloud/dialogs'
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
                        cospend.outputDirectory = optionsValues[k];
                    }
                }
            }
            main();
        }).fail(function() {
            showError(
                t('cospend', 'Failed to restore options values.')
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
    }

})(jQuery, OC);
