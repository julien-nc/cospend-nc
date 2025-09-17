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

import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'

const app = createApp(AdminSettings, {})
app.mixin({ methods: { t, n } })
app.mount('#cospend_prefs')
