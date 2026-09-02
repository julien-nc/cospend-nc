import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		languageOptions: {
			globals: {
				appVersion: 'readonly',
			},
		},
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/no-v-html': 'off',
			'vue/no-v-model-argument': 'off',
			'vue/max-attributes-per-line': 'off',
			'@stylistic/arrow-parens': 'off',
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'@stylistic/max-statements-per-line': 'off',
			'no-console': 'off',
			'@typescript-eslint/no-unused-vars': 'off',
			'vue/custom-event-name-casing': 'off',
			'vue/no-boolean-default': 'off',
			'@typescript-eslint/no-explicit-any': 'off',
			'vue/new-line-between-multi-line-property': 'off',
			'@stylistic/member-delimiter-style': 'off',
			'vue/no-unused-properties': 'off',
			'vue/attribute-hyphenation': 'off',
			'vue/v-on-event-hyphenation': 'off',
			'@stylistic/function-paren-newline': 'off',
			'no-unused-vars': 'off',
			'@stylistic/exp-list-style': 'off',
			'@stylistic/function-call-argument-newline': 'off',
		},
	},
	{
		ignores: ['src/detect_timezone.js', 'src/L.Control.Elevation.js'],
	},
]
