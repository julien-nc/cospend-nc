/* jshint esversion: 6 */

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

import Vue from 'vue'
import './bootstrap'
import App from './App'
import vueAwesomeCountdown from 'vue-awesome-countdown'
import VueClipboard from 'vue-clipboard2'
import SmartTable from 'vuejs-smart-table'
import Transitions from 'vue2-transitions'
import { hexToDarkerHex } from './utils'
import * as network from './network'
import cospend from './state'

Vue.use(vueAwesomeCountdown, 'vac')
Vue.use(VueClipboard)
Vue.use(SmartTable)
Vue.use(Transitions)

// eslint-disable-next-line
'use strict'

function restoreOptions() {
	network.getOptionValues(getOptionValuesSuccess)
}

function getOptionValuesSuccess(response) {
	let optionsValues = {}
	optionsValues = response.values
	if (optionsValues) {
		for (const k in optionsValues) {
			if (k === 'selectedProject') {
				cospend.restoredCurrentProjectId = optionsValues[k]
			} else if (k === 'outputDirectory') {
				cospend.outputDirectory = optionsValues[k]
			} else if (k === 'sortOrder') {
				cospend.sortOrder = optionsValues[k]
			} else if (k === 'maxPrecision') {
				cospend.maxPrecision = optionsValues[k]
			}
		}
	}
	main()
}

document.addEventListener('DOMContentLoaded', function(event) {
	cospend.pageIsPublic = (document.URL.indexOf('/cospend/project') !== -1 || document.URL.indexOf('/cospend/s/') !== -1)
	if (!cospend.pageIsPublic) {
		restoreOptions()
	} else {
		cospend.projectid = document.getElementById('projectid').textContent
		cospend.password = document.getElementById('password').textContent
		cospend.restoredCurrentProjectId = cospend.projectid
		main()
	}
	if (OCA.Theming) {
		const c = OCA.Theming.color
		// invalid color
		if (!c || (c.length !== 4 && c.length !== 7)) {
			cospend.themeColor = '#0082C9'
		} else if (c.length === 4) { // compact
			cospend.themeColor = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3]
		} else if (c.length === 7) { // normal
			cospend.themeColor = c
		}
	} else {
		cospend.themeColor = '#0082C9'
	}
	cospend.themeColorDark = hexToDarkerHex(cospend.themeColor)
})

function main() {
	// eslint-disable-next-line
	new Vue({
		el: '#content',
		render: h => h(App),
	})
}
