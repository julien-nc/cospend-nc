<template>
	<NcMultiselect
		:value="selectedCategoryItem"
		class="categoryMultiSelect multiSelect"
		label="displayName"
		track-by="id"
		:disabled="disabled"
		:placeholder="placeholder"
		:options="formattedOptions"
		:user-select="false"
		:internal-search="true"
		@input="onCategorySelected" />
</template>

<script>
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect.js'

export default {
	name: 'CategoryMultiSelect',

	components: {
		NcMultiselect,
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
		categories: {
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
			return this.categories.map(c => {
				return {
					...c,
					displayName: c.icon + ' ' + c.name,
				}
			})
		},
		selectedCategoryItem() {
			return this.value
				? {
					...this.value,
					displayName: this.value.icon + ' ' + this.value.name,
				}
				: null
		},
	},

	methods: {
		onCategorySelected(selected) {
			this.$emit('input', selected)
		},
	},
}
</script>

<style scoped lang="scss">
.categoryMultiSelect {
	height: 44px;
}
</style>
