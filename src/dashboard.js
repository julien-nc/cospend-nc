/* jshint esversion: 6 */

/**
 * Nextcloud - cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

import Vue from 'vue'
import './bootstrap'
import Dashboard from './views/Dashboard'

console.log('0000000000000000000000000000000')
document.addEventListener('DOMContentLoaded', function() {
	console.debug('1111111111111111111111111111')

	OCA.Dashboard.register('cospend_bills', (el, { widget }) => {
		console.debug('AAAAAAAAAAAAAAAAAAA')
		const View = Vue.extend(Dashboard)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})

})
