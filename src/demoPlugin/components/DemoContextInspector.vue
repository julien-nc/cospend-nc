<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<details class="demo-context-inspector">
		<summary>pluginContext</summary>
		<pre>{{ pretty }}</pre>
	</details>
</template>

<script>
export default {
	name: 'DemoContextInspector',

	props: {
		context: {
			type: Object,
			default: null,
		},
	},

	computed: {
		pretty() {
			return JSON.stringify(this.context, null, 2)
		},
	},
}
</script>

<style scoped>
.demo-context-inspector {
	margin-top: 12px;
}
.demo-context-inspector pre {
	background: var(--color-background-dark);
	padding: 8px;
	border-radius: var(--border-radius);
	overflow: auto;
	max-height: 300px;
	font-size: 12px;
}
</style>
