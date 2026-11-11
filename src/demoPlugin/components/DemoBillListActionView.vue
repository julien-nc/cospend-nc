<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo bill list action') }}</h3>
		<p v-if="project">
			<strong>{{ project.name }}</strong>
			— {{ project.nb_bills }} {{ t('cospend', 'bills') }},
			{{ formatBalance(project.total_spent, project.precision) }} {{ project.currencyname ?? '' }}
		</p>
		<div class="demo-actions">
			<NcButton class="demo-button"
				@click="refreshProject()">
				{{ t('cospend', 'Refresh this project') }}
			</NcButton>
		</div>
		<DemoContextInspector :context="pluginContext" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { emit } from '@nextcloud/event-bus'
import DemoContextInspector from './DemoContextInspector.vue'
import { formatBalance } from '../utils.js'

export default {
	name: 'DemoBillListActionView',

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
		project() {
			return this.pluginContext?.project
		},
	},

	methods: {
		formatBalance,
		refreshProject() {
			emit('cospend:refresh-project', { projectId: this.projectId })
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
