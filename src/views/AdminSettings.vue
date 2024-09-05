<template>
	<div id="cospend_prefs" class="section">
		<h2>
			<CospendIcon class="icon" />
			{{ t('cospend', 'Cospend') }}
		</h2>
		<div id="cospend-content">
			<div>
				<NcCheckboxRadioSwitch :checked="isFederationEnabled"
					:disabled="loading"
					type="switch"
					@update:checked="saveFederationEnabled">
					{{ t('cospend', 'Enable Federation in Cospend') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
	</div>
</template>

<script>
import CospendIcon from '../components/icons/CospendIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { loadState } from '@nextcloud/initial-state'

const FEDERATION_ENABLED = loadState('cospend', 'federation_enabled', false)

export default {
	name: 'AdminSettings',

	components: {
		CospendIcon,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			isFederationEnabled: FEDERATION_ENABLED,
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
					this.isFederationEnabled = value
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
