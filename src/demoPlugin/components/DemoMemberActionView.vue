<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo member action') }}</h3>
		<p>
			<strong>{{ member?.name }}</strong>
			({{ t('cospend', 'balance') }}: {{ formatBalance(member?.balance, pluginContext?.project?.precision) }})
		</p>
		<div class="demo-actions">
			<NcButton class="demo-button"
				@click="createBillPaidByMember()">
				{{ t('cospend', 'Create a bill paid by {name}', { name: member?.name ?? '?' }) }}
			</NcButton>
			<NcButton class="demo-button"
				@click="createBillWithResult()">
				{{ t('cospend', 'Create a bill and get the result') }}
			</NcButton>
			<NcButton class="demo-button"
				@click="refreshProject()">
				{{ t('cospend', 'Refresh this project') }}
			</NcButton>
		</div>
		<p v-if="lastResult" class="demo-result">
			{{ lastResult }}
		</p>
		<DemoContextInspector :context="pluginContext" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { emit } from '@nextcloud/event-bus'
import { showSuccess, showError } from '@nextcloud/dialogs'
import DemoContextInspector from './DemoContextInspector.vue'
import { formatBalance } from '../utils.js'

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

	data() {
		return {
			lastResult: null,
		}
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
		formatBalance,
		buildDemoBill() {
			return {
				what: 'Demo bill (member action)',
				amount: 5,
				payer_id: this.member.id,
				owerIds: this.activeMembers.map((m) => m.id),
				timestamp: Math.floor(Date.now() / 1000),
			}
		},
		createBillPaidByMember() {
			if (!this.member || this.activeMembers.length === 0) {
				return
			}
			// Cospend suppresses its own toast when a callback is provided,
			// so the plugin can show one that mentions it created the bill
			emit('cospend:create-bill', {
				projectId: this.projectId,
				bill: this.buildDemoBill(),
				onDone: (result) => {
					if (result.ok) {
						showSuccess(t('cospend', 'Bill created by plugin'))
					} else {
						showError(t('cospend', 'Failed to create bill: {error}', { error: result.error }))
					}
				},
			})
		},
		// the payload may carry an onDone callback to get the outcome instead of toasts
		async createBillWithResult() {
			if (!this.member || this.activeMembers.length === 0) {
				return
			}
			const result = await new Promise((resolve) => {
				emit('cospend:create-bill', {
					projectId: this.projectId,
					bill: this.buildDemoBill(),
					onDone: resolve,
				})
			})
			this.lastResult = result.ok
				? t('cospend', 'Bill {id} created', { id: result.billId })
				: t('cospend', 'Failed to create bill: {error}', { error: result.error })
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
.demo-result {
	margin: 0;
}
</style>
