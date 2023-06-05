import { linkTo } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
__webpack_public_path__ = linkTo('cospend', 'js/') // eslint-disable-line

document.addEventListener('DOMContentLoaded', async (event) => {
	const { setAllowAnonymousCreation } = await import(/* webpackChunkName: "admin-settings-lazy" */'./network.js')
	const anonyCheck = document.getElementById('allowAnonymousCreation')
	anonyCheck.addEventListener('change', (event) => {
		setAllowAnonymousCreation(event.target.checked ? '1' : '0')
	})
})
