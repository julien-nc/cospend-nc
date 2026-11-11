<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo member action') }}</h3>
		<p>
			<strong>{{ member?.name }}</strong>
			({{ t('cospend', 'balance') }}: {{ member?.balance }})
		</p>
		<div class="demo-actions">
			<NcButton class="demo-button"
				@click="createBillPaidByMember()">
				{{ t('cospend', 'Create a bill paid by {name}', { name: member?.name ?? '?' }) }}
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
	name: 'DemoMemberActionView',

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
		member() {
			return this.pluginContext?.member
		},
		activeMembers() {
			return Object.values(this.pluginContext?.members?.[this.projectId] ?? {})
				.filter((m) => m.activated)
		},
	},

	methods: {
		createBillPaidByMember() {
			if (!this.member || this.activeMembers.length === 0) {
				return
			}
			emit('cospend:create-bill', {
				projectId: this.projectId,
				bill: {
					what: 'Demo bill (member action)',
					amount: 5,
					payer_id: this.member.id,
					owerIds: this.activeMembers.map((m) => m.id),
					timestamp: Math.floor(Date.now() / 1000),
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
