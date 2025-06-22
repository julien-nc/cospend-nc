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

import { createApp } from 'vue'
import App from './App.vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { loadState } from '@nextcloud/initial-state'
import SmartTable from 'vuejs-smart-table'
import { hexToDarkerHex } from './utils.js'
import * as network from './network.js'
import { initState } from './state.js'
import '../css/cospend.scss'

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
				OCA.Cospend.state.restoredCurrentProjectId = optionsValues[k]
			} else if (k === 'useTime') {
				OCA.Cospend.state.useTime = optionsValues[k] !== '0'
			} else if (k === 'showMyBalance') {
				OCA.Cospend.state.showMyBalance = optionsValues[k] !== '0'
			} else {
				OCA.Cospend.state[k] = optionsValues[k]
			}
		}
	}
	// get path restore projectId and billId, this overrides saved options
	const restoredCurrentProjectId = loadState('cospend', 'pathProjectId')
	if (restoredCurrentProjectId !== '') {
		OCA.Cospend.state.restoredCurrentProjectId = restoredCurrentProjectId
	}
	const restoredCurrentBillId = loadState('cospend', 'pathBillId')
	if (restoredCurrentBillId !== 0) {
		OCA.Cospend.state.restoredCurrentBillId = restoredCurrentBillId
	}
	console.debug('restored project ID', OCA.Cospend.state.restoredCurrentProjectId)
	console.debug('restored bill ID', OCA.Cospend.state.restoredCurrentBillId)
	main()
}

document.addEventListener('DOMContentLoaded', (event) => {
	initState()
	OCA.Cospend.state.pageIsPublic = (document.URL.includes('/cospend/project') || document.URL.includes('/cospend/s/'))
	if (!OCA.Cospend.state.pageIsPublic) {
		restoreOptions()
		OCA.Cospend.state.activity_enabled = loadState('cospend', 'activity_enabled') === '1'
	} else {
		OCA.Cospend.state.projectid = loadState('cospend', 'projectid')
		OCA.Cospend.state.password = loadState('cospend', 'password')
		// TODO restore project when accessed via token, following projectid is wrong as it's a token
		OCA.Cospend.state.restoredCurrentProjectId = OCA.Cospend.state.projectid
		main()
	}
	if (OCA.Theming) {
		const c = OCA.Theming.color
		// invalid color
		if (!c || (c.length !== 4 && c.length !== 7)) {
			OCA.Cospend.state.themeColor = '#0082C9'
		} else if (c.length === 4) { // compact
			OCA.Cospend.state.themeColor = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3]
		} else if (c.length === 7) { // normal
			OCA.Cospend.state.themeColor = c
		}
	} else {
		OCA.Cospend.state.themeColor = '#0082C9'
	}
	OCA.Cospend.state.themeColorDark = hexToDarkerHex(OCA.Cospend.state.themeColor)
})

function main() {
	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.use(SmartTable)
	app.mount('#content')
}
