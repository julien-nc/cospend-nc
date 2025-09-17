/**
 * Nextcloud - cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */
// import { linkTo } from '@nextcloud/router'
// import { getRequestToken } from '@nextcloud/auth'

// __webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
// __webpack_public_path__ = linkTo('cospend', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('cospend_activity', async (el, { widget }) => {
		const { createApp } = await import('vue')
		const { default: Dashboard } = await import('./views/Dashboard.vue')

		const app = createApp(Dashboard, {
			title: widget.title,
		})
		app.mixin({ methods: { t, n } })
		app.mount(el)
	})
})
