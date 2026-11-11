<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<p class="demo-hint">
			{{ t('cospend', 'Demo plugin list pane. Click a project to select it.') }}
		</p>
		<div v-for="project in projects" :key="project.id" class="demo-project">
			<strong>{{ project.name }}</strong>
			<NcButton class="demo-button"
				@click="selectProject(project.id)">
				{{ t('cospend', 'Select') }}
			</NcButton>
		</div>
		<DemoContextInspector :context="pluginContext" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { emit } from '@nextcloud/event-bus'
import DemoContextInspector from './DemoContextInspector.vue'

export default {
	name: 'DemoMainListView',

	components: {
		NcButton,
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

	computed: {
		projects() {
			return Object.values(this.pluginContext?.projects ?? {})
		},
	},

	methods: {
		selectProject(projectId) {
			emit('cospend:select-project', { projectId })
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
	/* the hint sits at the top-left, leave room for the floating navigation toggle
	   button (same clearance as the BillList header, minus the container padding) */
	margin-left: calc(var(--default-clickable-area) + 12px - 16px);
}
.demo-hint {
	color: var(--color-text-maxcontrast);
}
.demo-project {
	display: flex;
	gap: 8px;
	align-items: center;
}
.demo-button {
	align-self: flex-start;
}
</style>
