/**
 * Nextcloud - cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2019
 */

import Vue from 'vue'
import './bootstrap.js'
import App from './App.vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import vueAwesomeCountdown from 'vue-awesome-countdown'
import VueClipboard from 'vue-clipboard2'
import SmartTable from 'vuejs-smart-table'
import { hexToDarkerHex } from './utils.js'
import * as network from './network.js'
import cospend from './state.js'
import '../css/cospend.scss'

Vue.use(vueAwesomeCountdown, 'vac')
Vue.use(VueClipboard)
Vue.use(SmartTable)
Vue.directive('tooltip', Tooltip)

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = generateFilePath('cospend', '', 'js/') // eslint-disable-line

function restoreOptions() {
	network.getOptionValues().then((response) => {
		getOptionValuesSuccess(response.data)
	}).catch((error) => {
		showError(t('cospend', 'Failed to restore options values'))
		console.error(error)
	})
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
			} else if (k === 'memberOrder') {
				cospend.memberOrder = optionsValues[k]
			} else if (k === 'maxPrecision') {
				cospend.maxPrecision = optionsValues[k]
			} else if (k === 'useTime') {
				cospend.useTime = optionsValues[k] !== '0'
			}
		}
	}
	// get path restore projectId and billId, this overrides saved options
	const restoredCurrentProjectId = loadState('cospend', 'pathProjectId')
	if (restoredCurrentProjectId !== '') {
		cospend.restoredCurrentProjectId = restoredCurrentProjectId
	}
	const restoredCurrentBillId = loadState('cospend', 'pathBillId')
	if (restoredCurrentBillId !== 0) {
		cospend.restoredCurrentBillId = restoredCurrentBillId
	}
	console.debug('restored project ID', cospend.restoredCurrentProjectId)
	console.debug('restored bill ID', cospend.restoredCurrentBillId)
	main()
}

document.addEventListener('DOMContentLoaded', (event) => {
	cospend.pageIsPublic = (document.URL.includes('/cospend/project') || document.URL.includes('/cospend/s/'))
	if (!cospend.pageIsPublic) {
		restoreOptions()
		cospend.activity_enabled = loadState('cospend', 'activity_enabled') === '1'
	} else {
		cospend.projectid = loadState('cospend', 'projectid')
		cospend.password = loadState('cospend', 'password')
		// TODO restore project when accessed via token, following projectid is wrong as it's a token
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
	const View = Vue.extend(App)
	new View().$mount('#content')
}
