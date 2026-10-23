<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
				<NcFormBoxSwitch :model-value="state.auto_categorization_enabled"
					:disabled="loading"
					@update:model-value="saveAutoCategorizationEnabled">
					{{ t('cospend', 'Enable auto-categorisation of bills') }}
				</NcFormBoxSwitch>
				<p v-if="state.auto_categorization_enabled" class="auto-cat-help-text">
					{{ t('cospend', 'When enabled, bill titles are checked against per-project mappings on blur. Each project also has its own toggle to opt in or out.') }}
				</p>
			</NcFormBox>
			<NcButton
				v-if="state.auto_categorization_enabled"
				class="auto-categorize-all-btn"
				:disabled="autoCategorizingAll"
				@click="onAutoCategorizeAll">
				<template #icon>
					<NcLoadingIcon v-if="autoCategorizingAll" />
					<AutoFixIcon v-else :size="20" />
				</template>
				{{ t('cospend', 'Auto-categorise all existing bills') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import CospendIcon from '../components/icons/CospendIcon.vue'
import AutoFixIcon from 'vue-material-design-icons/AutoFix.vue'

import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getErrorMessage } from '../utils.js'
import * as network from '../network.js'

export default {
	name: 'AdminSettings',

	components: {
		CospendIcon,
		AutoFixIcon,
		NcFormBox,
		NcFormBoxSwitch,
		NcButton,
		NcLoadingIcon,
	},

	data() {
		return {
			state: loadState('cospend', 'admin-settings', {}),
			loading: false,
			autoCategorizingAll: false,
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
		saveAutoCategorizationEnabled(value) {
			this.state.auto_categorization_enabled = value
			this.saveOptions({ auto_categorization_enabled: value ? '1' : '0' })
		},
		onAutoCategorizeAll() {
			this.autoCategorizingAll = true
			network.autoCategorizeAll().then((response) => {
				const count = response.data.ocs.data
				showSuccess(t('cospend', '{count} bill(s) categorised', { count }))
			}).catch((error) => {
				console.error('Failed to auto-categorize all', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to auto-categorise all bills')))
			}).then(() => {
				this.autoCategorizingAll = false
			})
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

	.auto-categorize-all-btn {
		margin-top: 12px;
	}

	.auto-cat-help-text {
		color: var(--color-text-lighter);
		font-size: 0.9em;
		padding: 4px 0 0 40px;
		margin: 0;
	}
}
</style>
