/**
 * Nextcloud - Cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2024
 */

import Vue from 'vue'
import AdminSettings from './views/AdminSettings.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(AdminSettings)
new View().$mount('#cospend_prefs')
