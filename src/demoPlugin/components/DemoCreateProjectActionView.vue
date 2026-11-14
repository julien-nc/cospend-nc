<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo create project action') }}</h3>
		<p class="demo-hint">
			{{ t('cospend', 'This view is rendered by the demo plugin from the "Create a project" menu. It only uses the data given through its props and interacts with Cospend through event-bus action events.') }}
		</p>
		<div class="demo-actions">
			<NcTextField v-model="projectName"
				class="demo-input"
				:label="t('cospend', 'Project name')"
				:placeholder="t('cospend', 'Demo project')" />
			<NcButton class="demo-button"
				:disabled="projectName.trim() === ''"
				@click="createProject()">
				{{ t('cospend', 'Create this project') }}
			</NcButton>
		</div>
		<DemoContextInspector :context="pluginContext" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { emit } from '@nextcloud/event-bus'
import DemoContextInspector from './DemoContextInspector.vue'

export default {
	name: 'DemoCreateProjectActionView',

	components: {
		NcButton,
		NcTextField,
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
			projectName: '',
			// name of a project whose creation is not confirmed yet (null when none)
			pendingName: null,
			// project ids known when the creation was requested
			pendingProjectIds: [],
		}
	},

	watch: {
		// pluginContext is a new plain object on every Cospend state change
		pluginContext(context) {
			if (this.pendingName === null) {
				return
			}
			const created = Object.values(context?.projects ?? {})
				.find((p) => p.name === this.pendingName && !this.pendingProjectIds.includes(p.id))
			if (created) {
				this.pendingName = null
				this.pendingProjectIds = []
				// the plugin owns its view: close it now that the project exists
				emit('cospend:close-plugin-view', { pluginId: 'demo' })
			}
		},
	},

	methods: {
		createProject() {
			const name = this.projectName.trim()
			if (name === '') {
				return
			}
			this.pendingProjectIds = Object.keys(this.pluginContext?.projects ?? {})
			this.pendingName = name
			emit('cospend:create-project', { name })
			this.projectName = ''
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

.demo-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 8px;
}

.demo-input {
	width: 240px;
}

.demo-button {
	align-self: flex-start;
}
</style>
