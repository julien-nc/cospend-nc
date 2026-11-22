<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<p class="demo-hint">
			{{ t('cospend', 'Demo plugin settings. A real plugin would persist its settings through its own app; this demo uses localStorage.') }}
		</p>
		<p v-if="pluginContext?.user">
			{{ t('cospend', 'Current user') }}: {{ pluginContext.user.displayName }} ({{ pluginContext.user.uid }})
		</p>
		<label class="demo-setting">
			<input v-model="demoEnabled"
				type="checkbox">
			{{ t('cospend', 'A demo setting (stored in localStorage)') }}
		</label>
		<DemoContextInspector :context="pluginContext" />
	</div>
</template>

<script>
import DemoContextInspector from './DemoContextInspector.vue'

const STORAGE_KEY = 'cospend-demo-plugin-enabled'

export default {
	name: 'DemoSettingsView',

	components: {
		DemoContextInspector,
	},

	props: {
		projectId: {
			type: String,
			default: null,
		},
		pluginContext: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			demoEnabled: localStorage.getItem(STORAGE_KEY) === '1',
		}
	},

	watch: {
		demoEnabled(value) {
			localStorage.setItem(STORAGE_KEY, value ? '1' : '0')
		},
	},
}
</script>

<style scoped>
.demo-view {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	align-items: flex-start;
}
.demo-hint {
	color: var(--color-text-maxcontrast);
}
.demo-setting {
	display: flex;
	gap: 8px;
	align-items: center;
}
</style>
