<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo bill action') }}</h3>
		<p>
			<strong>{{ bill?.what }}</strong>
			({{ bill?.amount }})
		</p>
		<div class="demo-actions">
			<NcButton class="demo-button"
				@click="duplicateBill()">
				{{ t('cospend', 'Duplicate this bill') }}
			</NcButton>
			<NcButton class="demo-button"
				@click="deleteBill()">
				{{ t('cospend', 'Delete this bill') }}
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
	name: 'DemoBillActionView',

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
		bill() {
			return this.pluginContext?.bill
		},
	},

	methods: {
		duplicateBill() {
			if (!this.bill) {
				return
			}
			emit('cospend:create-bill', {
				projectId: this.projectId,
				bill: {
					what: this.bill.what,
					comment: this.bill.comment,
					amount: this.bill.amount,
					payer_id: this.bill.payer_id,
					owerIds: this.bill.owerIds ?? [],
					timestamp: Math.floor(Date.now() / 1000),
				},
			})
		},
		refreshProject() {
			emit('cospend:refresh-project', { projectId: this.projectId })
		},
		deleteBill() {
			if (!this.bill) {
				return
			}
			emit('cospend:delete-bill', {
				projectId: this.projectId,
				billId: this.bill.id,
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
