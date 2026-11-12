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
	demoPlugin: 'src/demoPlugin/index.js',
}, {
	config: {
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
			/*
			preprocessorOptions: {
				scss: {
					api: 'modern-compiler',
				},
			},
			*/
		},
		plugins: [
			eslint(),
			stylelint(),
		],
		build: {
			cssCodeSplit: true,
			// keep the demo plugin's late-defined view in its own lazy chunk:
			// without this, the default "common" group merges it into an eagerly
			// loaded chunk, turning the dynamic import() showcase into a no-op
			rolldownOptions: {
				output: {
					codeSplitting: {
						groups: [
							{
								name: 'demoLateView',
								test: /DemoLate/,
								priority: 1,
								minSize: 0,
								// only capture the late view modules themselves: their
								// dependencies are shared with all entries and must stay
								// in eager chunks
								includeDependenciesRecursively: false,
							},
						],
					},
				},
			},
			// caused issues with NcAvatar (TypeError: can't access property "needReadable", this._readableState is undefined)
			rollupOptions: {
				treeshake: {
					// Remove unused module exports
					moduleSideEffects: false,
					// Optimize property access
					propertyReadSideEffects: false,
				},
			},
		},
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
	// target: 'esnext',
})
