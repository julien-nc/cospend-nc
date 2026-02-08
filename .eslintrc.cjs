module.exports = {
	globals: {
		appVersion: true
	},
	parserOptions: {
		requireConfigFile: false,
		parser: '@typescript-eslint/parser'
	},
	extends: [
		'@nextcloud'
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'import/extensions': 'off',
		'vue/no-v-model-argument': 'off'
	}
}
