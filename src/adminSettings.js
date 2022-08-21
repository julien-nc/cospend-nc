/* jshint esversion: 6 */

import * as network from './network.js'

(function() {
	if (!OCA.Cospend) {
		OCA.Cospend = {}
	}
})()

document.addEventListener('DOMContentLoaded', function(event) {
	const anonyCheck = document.getElementById('allowAnonymousCreation')
	anonyCheck.addEventListener('change', (event) => {
		network.setAllowAnonymousCreation(event.target.checked ? '1' : '0')
	})
})
