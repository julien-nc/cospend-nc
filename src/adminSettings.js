/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'

const app = createApp(AdminSettings, {})
app.mixin({ methods: { t, n } })
app.mount('#cospend_prefs')
