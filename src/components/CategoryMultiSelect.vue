<template>
	<Multiselect
		:value="selectedCategoryItem"
		class="categoryMultiSelect multiSelect"
		label="name"
		track-by="id"
		:disabled="disabled"
		:placeholder="placeholder"
		:options="formattedOptions"
		:user-select="false"
		:internal-search="true"
		@input="onCategorySelected" />
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {
	name: 'CategoryMultiSelect',

	components: {
		Multiselect,
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
					name: c.icon + ' ' + c.name,
					id: c.id,
				}
			})
		},
		selectedCategoryItem() {
			return this.value
				? {
					name: this.value.icon + ' ' + this.value.name,
					id: this.value.id,
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
