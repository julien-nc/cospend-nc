/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	main: 'src/main.js',
	sharePassword: 'src/sharePassword.js',
	adminSettings: 'src/adminSettings.js',
	dashboard: 'src/dashboard.js',
}, {
	config: {
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
			preprocessorOptions: {
				scss: {
					api: 'modern-compiler',
				},
			},
		},
		plugins: [eslint(), stylelint()],
		build: {
			cssCodeSplit: true,
			// causes issues with NcAvatar (TypeError: can't access property "needReadable", this._readableState is undefined)
			/*
			rollupOptions: {
				treeshake: {
					// Remove unused module exports
					moduleSideEffects: false,
					// Optimize property access
					propertyReadSideEffects: false,
					// Remove unused imports
					tryCatchDeoptimization: false
				}
			},
			*/
		},
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
	target: 'esnext',
})
