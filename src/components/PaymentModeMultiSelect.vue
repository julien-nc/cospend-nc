<template>
	<NcSelect
		:model-value="selectedPmItem"
		class="pmMultiSelect"
		:aria-label-combobox="t('cospend', 'Payment mode selector')"
		label="displayName"
		:disabled="disabled"
		:clearable="false"
		:placeholder="placeholder"
		:options="formattedOptions"
		@update:model-value="onPmSelected" />
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

export default {
	name: 'PaymentModeMultiSelect',

	components: {
		NcSelect,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
		placeholder: {
			type: String,
			required: true,
		},
		paymentModes: {
			type: Array,
			required: true,
		},
		value: {
			type: Object,
			default: () => null,
		},
	},

	data() {
		return {}
	},

	computed: {
		formattedOptions() {
			return this.paymentModes.map(pm => {
				return {
					...pm,
					displayName: pm.icon + ' ' + pm.name,
				}
			})
		},
		selectedPmItem() {
			return this.value
				? {
					...this.value,
					displayName: this.value.icon + ' ' + this.value.name,
				}
				: null
		},
	},

	methods: {
		onPmSelected(selected) {
			this.$emit('input', selected)
		},
	},
}
</script>

<style scoped lang="scss">
.pmMultiSelect {
	height: 48px;
}
</style>
