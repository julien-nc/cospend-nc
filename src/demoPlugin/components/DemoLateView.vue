<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<p class="demo-hint">
			{{ t('cospend', 'This view is rendered by the demo plugin too, but its custom element is defined from a dynamic import(): the element was defined after this navItem was registered and opened. Cospend waits for the definition and shows a loading state until the element takes over.') }}
		</p>
		<pre class="demo-context">{{ pretty }}</pre>
	</div>
</template>

<script>
export default {
	name: 'DemoLateView',

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
</style>
