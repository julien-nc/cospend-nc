<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="demo-view">
		<h3>{{ t('cospend', 'Demo bill form extra') }}</h3>
		<p>
			{{ t('cospend', 'This view is rendered by the demo plugin, right below the bill form. It can read the bill being edited through the pluginContext prop and change its values with the cospend:set-bill-values event, like a user edit (saved by default).') }}
		</p>
		<p v-if="bill">
			<strong>{{ bill.what }}</strong>
			({{ formatAmount(bill.amount, pluginContext?.project?.precision) }})
		</p>
		<div class="demo-actions">
			<NcButton class="demo-button"
				:disabled="!editionAccess"
				@click="setBillValues(true)">
				{{ t('cospend', 'Set bill values (and save)') }}
			</NcButton>
			<NcButton class="demo-button"
				:disabled="!editionAccess"
				@click="setBillValues(false)">
				{{ t('cospend', 'Set bill values (without saving)') }}
			</NcButton>
		</div>
		<DemoContextInspector :context="pluginContext" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { emit } from '@nextcloud/event-bus'
import DemoContextInspector from './DemoContextInspector.vue'
import { formatAmount } from '../utils.js'

export default {
	name: 'DemoBillFormExtraView',

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
		editionAccess() {
			return (this.pluginContext?.accessLevel ?? 0) >= 2
		},
	},

	methods: {
		formatAmount,
		setBillValues(save) {
			const members = Object.values(this.pluginContext?.members?.[this.projectId] ?? {})
			emit('cospend:set-bill-values', {
				pluginId: 'demo',
				entryPointId: 'demo-billform-extra',
				values: {
					what: 'Set by the demo plugin',
					amount: 42.5,
					owerIds: members.filter((member) => member.activated).map((member) => member.id),
				},
				save,
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
