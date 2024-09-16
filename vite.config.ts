/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: 'src/main.js',
	adminSettings: 'src/adminSettings.js',
	sharePassword: 'src/sharePassword.js',
	dashboard: 'src/dashboard.js'
}, {
	config: {
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
		},
	},
	inlineCSS: { relativeCSSInjection: true },
})
