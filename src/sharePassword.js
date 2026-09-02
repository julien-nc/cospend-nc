/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateUrl } from '@nextcloud/router'

document.addEventListener('DOMContentLoaded', function() {
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
