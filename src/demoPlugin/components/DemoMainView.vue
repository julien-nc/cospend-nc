<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<p class="demo-hint">
			{{ t('cospend', 'This view is rendered by the demo plugin. It only uses the data given through its props and interacts with Cospend through event-bus action events.') }}
		</p>
		<div v-if="projects.length === 0" class="demo-hint">
			{{ t('cospend', 'No project found.') }}
		</div>
		<div v-for="project in projects" :key="project.id" class="demo-project">
			<strong>{{ project.name }}</strong> ({{ project.id }})
			— {{ project.nb_bills }} {{ t('cospend', 'bills') }},
			{{ project.total_spent }} {{ project.currencyname ?? '' }}
			<NcButton class="demo-button"
				@click="selectProject(project.id)">
				{{ t('cospend', 'Select') }}
			</NcButton>
		</div>
		<div class="demo-actions">
			<NcButton class="demo-button"
				:disabled="projects.length === 0"
				@click="createDemoBill()">
				{{ t('cospend', 'Create a demo bill in the first project') }}
			</NcButton>
			<NcButton class="demo-button"
				@click="refreshAllBalances()">
				{{ t('cospend', 'Refresh all balances') }}
			</NcButton>
			<NcButton class="demo-button"
				@click="deselectProject()">
				{{ t('cospend', 'Deselect the current project') }}
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
	name: 'DemoMainView',

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
		deselectProject() {
			emit('cospend:deselect-project', {})
		},
		refreshAllBalances() {
			for (const projectId of Object.keys(this.pluginContext?.projects ?? {})) {
				emit('cospend:refresh-project', { projectId })
			}
		},
		createDemoBill() {
			const projects = this.pluginContext?.projects ?? {}
			const firstProjectId = Object.keys(projects)[0]
			if (!firstProjectId) {
				return
			}
			const members = Object.values(this.pluginContext?.members?.[firstProjectId] ?? {})
				.filter((m) => m.activated)
			if (members.length === 0) {
				return
			}
			emit('cospend:create-bill', {
				projectId: firstProjectId,
				bill: {
					what: 'Demo bill',
					amount: 10,
					payer_id: members[0].id,
					owerIds: members.map((m) => m.id),
					timestamp: Math.floor(Date.now() / 1000),
				},
			})
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
.demo-project {
	display: flex;
	gap: 8px;
	align-items: center;
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
