<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<p class="demo-hint">
			{{ t('cospend', 'Demo sidebar tab. Rename a member to trigger a member save through an action event.') }}
		</p>
		<div v-for="member in members" :key="member.id" class="demo-project">
			{{ member.name }}: {{ member.balance }}
			<NcButton class="demo-button"
				@click="renameMember(member)">
				{{ t('cospend', 'Rename with "(demo)" suffix') }}
			</NcButton>
		</div>
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

export default {
	name: 'DemoSidebarTabView',

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
		members() {
			return Object.values(this.pluginContext?.members?.[this.projectId] ?? {})
				.filter((m) => m.activated)
		},
	},

	methods: {
		renameMember(member) {
			if (member.name.includes('(demo)')) {
				return
			}
			emit('cospend:save-member', {
				projectId: this.projectId,
				member: {
					id: member.id,
					name: member.name + ' (demo)',
				},
			})
		},
		refreshProject() {
			emit('cospend:refresh-project', { projectId: this.projectId })
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
