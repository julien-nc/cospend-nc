/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'

console.error('process.env.NODE_ENV', process.env.NODE_ENV)
const isProduction = process.env.NODE_ENV === 'production'

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
		plugins: [eslint(), stylelint()],
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
})
