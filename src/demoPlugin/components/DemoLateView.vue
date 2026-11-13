<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<p class="demo-hint">
			{{ t('cospend', 'This view is rendered by the demo plugin too, but its custom element is defined from a dynamic import(): the element was defined after this navItem was registered and opened. Cospend waits for the definition and shows a loading state until the element takes over.') }}
		</p>
		<p class="demo-hint">
			{{ t('cospend', 'The buttons below update the counter badge of the "Demo: late view" nav item in the navigation, like the cumulated balance of the core nav items. The value survives the closing of this view.') }}
		</p>
		<div class="demo-actions">
			<NcButton class="demo-button"
				@click="changeDemoLateCounter(-1)">
				{{ t('cospend', 'Decrease the nav item counter') }}
			</NcButton>
			<NcButton class="demo-button"
				@click="changeDemoLateCounter(1)">
				{{ t('cospend', 'Increase the nav item counter') }}
			</NcButton>
		</div>
		<pre class="demo-context">{{ pretty }}</pre>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { changeDemoLateCounter } from '../navItemCounter.js'

export default {
	name: 'DemoLateView',

	components: {
		NcButton,
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

	computed: {
		pretty() {
			return JSON.stringify(this.pluginContext, null, 2)
		},
	},

	methods: {
		changeDemoLateCounter,
	},
}
</script>

<style scoped>
.demo-view {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	align-items: flex-start;
}

.demo-hint {
	color: var(--color-text-maxcontrast);
}

.demo-context {
	background: var(--color-background-dark);
	padding: 8px;
	border-radius: var(--border-radius);
	overflow: auto;
	max-height: 300px;
	font-size: 12px;
}

.demo-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 8px;
}
.demo-button {
	align-self: flex-start;
}
</style>
