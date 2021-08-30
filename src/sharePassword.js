/**
 * Nextcloud - cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

import { generateUrl } from '@nextcloud/router'

// eslint-disable-next-line
'use strict'

document.addEventListener('DOMContentLoaded', function(event) {
	const pwdInput = document.getElementById('passwordInput')
	pwdInput.value = ''
	pwdInput.focus()
	pwdInput.select()
	main()
})

function main() {
	const url = generateUrl('/apps/cospend/project')
	const form = document.getElementById('loginform')
	form.setAttribute('action', url)

	/*
	const pwdInput = document.getElementById('passwordInput')
	if (pwdInput.value.length > 0) {
		form.submit()
	}
	*/
}
