<template>
	<div id="cospend_prefs" class="section">
		<h2>
			<CospendIcon class="icon" />
			{{ t('cospend', 'Cospend') }}
		</h2>
		<div id="cospend-content">
			<NcCheckboxRadioSwitch :model-value="state.federation_enabled"
				:disabled="loading"
				type="switch"
				@update:model-value="saveFederationEnabled">
				{{ t('cospend', 'Enable Federation in Cospend') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :model-value="state.balance_past_bills_only"
				:disabled="loading"
				type="switch"
				@update:model-value="saveBalancePastBillsOnly">
				{{ t('cospend', 'Only consider past bills to compute balances') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import CospendIcon from '../components/icons/CospendIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'AdminSettings',

	components: {
		CospendIcon,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			state: loadState('cospend', 'admin-settings', {}),
			loading: false,
		}
	},

	computed: {
	},

	created() {
	},

	mounted() {
	},

	methods: {
		saveFederationEnabled(value) {
			this.loading = true
			OCP.AppConfig.setValue('cospend', 'federation_enabled', value ? '1' : '0', {
				success: () => {
					this.loading = false
					this.state.isFederationEnabled = value
				},
			})
		},
		saveBalancePastBillsOnly(value) {
			this.loading = true
			OCP.AppConfig.setValue('cospend', 'balance_past_bills_only', value ? '1' : '0', {
				success: () => {
					this.loading = false
					this.state.balance_past_bills_only = value
				},
			})
		},
	},
}
</script>

<style scoped lang="scss">
#cospend_prefs {
	#cospend-content {
		margin-left: 40px;
	}

	h2 {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 8px;
		}
	}
}
</style>
