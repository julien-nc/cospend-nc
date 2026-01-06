<template>
	<div id="cospend_prefs" class="section">
		<h2>
			<CospendIcon class="icon" />
			{{ t('cospend', 'Cospend') }}
		</h2>
		<div id="cospend-content">
			<NcFormBox>
				<NcFormBoxSwitch :model-value="state.federation_enabled"
					:disabled="loading"
					@update:model-value="saveFederationEnabled">
					{{ t('cospend', 'Enable Federation in Cospend') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch :model-value="state.balance_past_bills_only"
					:disabled="loading"
					@update:model-value="saveBalancePastBillsOnly">
					{{ t('cospend', 'Only consider past bills to compute balances') }}
				</NcFormBoxSwitch>
			</NcFormBox>
		</div>
	</div>
</template>

<script>
import CospendIcon from '../components/icons/CospendIcon.vue'

import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		CospendIcon,
		NcFormBox,
		NcFormBoxSwitch,
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
		saveOptions(values) {
			this.loading = true
			const req = {
				options: values,
			}
			const url = generateUrl('/apps/cospend/admin-option-values')
			axios.put(url, req)
				.then((response) => {
				})
				.catch((error) => {
					showError(t('cospend', 'Failed to save option values'))
					console.error(error)
				})
				.then(() => {
					this.loading = false
				})
		},
		saveFederationEnabled(value) {
			this.state.federation_enabled = value
			this.saveOptions({ federation_enabled: value ? '1' : '0' })
		},
		saveBalancePastBillsOnly(value) {
			this.state.balance_past_bills_only = value
			this.saveOptions({ balance_past_bills_only: value ? '1' : '0' })
		},
	},
}
</script>

<style scoped lang="scss">
#cospend_prefs {
	#cospend-content {
		margin-left: 40px;
		max-width: 800px;
	}

	h2 {
		display: flex;
		justify-content: start;
		align-items: center;
		.icon {
			margin-right: 8px;
		}
	}
}
</style>
