import { setAllowAnonymousCreation } from './network.js'

document.addEventListener('DOMContentLoaded', (event) => {
	const anonyCheck = document.getElementById('allowAnonymousCreation')
	anonyCheck.addEventListener('change', (event) => {
		setAllowAnonymousCreation(event.target.checked ? '1' : '0')
	})
})
