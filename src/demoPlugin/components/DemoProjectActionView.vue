<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo project action') }}</h3>
		<p>
			<strong>{{ project?.name }}</strong>
			({{ t('cospend', 'access level') }}: {{ pluginContext?.accessLevel }})
		</p>
		<div v-for="member in members" :key="member.id" class="demo-project">
			{{ member.name }}: {{ member.balance }}
		</div>
		<div class="demo-actions">
			<NcButton class="demo-button"
				:disabled="members.length === 0"
				@click="createDemoBill()">
				{{ t('cospend', 'Create a demo bill in this project') }}
			</NcButton>
			<NcButton class="demo-button"
				@click="toggleArchive()">
				{{ project?.archived_ts ? t('cospend', 'Unarchive this project') : t('cospend', 'Archive this project') }}
			</NcButton>
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

export default {
	name: 'DemoProjectActionView',

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
		members() {
			return Object.values(this.pluginContext?.members?.[this.projectId] ?? {})
				.filter((m) => m.activated)
		},
	},

	methods: {
		createDemoBill() {
			const members = this.members
			if (members.length === 0) {
				return
			}
			emit('cospend:create-bill', {
				projectId: this.projectId,
				bill: {
					what: 'Demo bill (project action)',
					amount: 10,
					payer_id: members[0].id,
					owerIds: members.map((m) => m.id),
					timestamp: Math.floor(Date.now() / 1000),
				},
			})
		},
		refreshProject() {
			emit('cospend:refresh-project', { projectId: this.projectId })
		},
		toggleArchive() {
			emit('cospend:archive-project', { projectId: this.projectId })
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
