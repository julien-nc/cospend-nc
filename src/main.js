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
import { reactive } from '@vue/reactivity'
import App from './App.vue'
import '@nextcloud/dialogs/style.css'
import { loadState } from '@nextcloud/initial-state'
import SmartTable from 'vuejs-smart-table'
import { hexToDarkerHex } from './utils.js'
import { defaultState } from './state.js'
import '../css/cospend.scss'

if (!OCA.Cospend) {
	OCA.Cospend = {}
}

document.addEventListener('DOMContentLoaded', (event) => {
	const pageIsPublic = (document.URL.includes('/cospend/project') || document.URL.includes('/cospend/s/'))
	if (!pageIsPublic) {
		const initialState = loadState('cospend', 'cospend-state', {})
		console.debug('[cospend] initial state', initialState)
		OCA.Cospend.state = reactive({
			...defaultState,
			...initialState,
		})
	} else {
		OCA.Cospend.state = reactive({
			...defaultState,
			projectid: loadState('cospend', 'projectid'),
			password: loadState('cospend', 'password'),
			// TODO restore project when accessed via token, following projectid is wrong as it's a token
			restoredCurrentProjectId: loadState('cospend', 'projectid'),
		})
	}
	OCA.Cospend.state.pageIsPublic = pageIsPublic
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
	main()
})

function main() {
	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.use(SmartTable)
	app.mount('#content')
}
