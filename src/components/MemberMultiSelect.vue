<template>
	<Multiselect
		:value="value"
		class="memberMultiSelect multiSelect"
		label="displayName"
		track-by="id"
		:disabled="disabled"
		:placeholder="placeholder"
		:options="options"
		:user-select="true"
		:internal-search="true"
		@input="onMemberSelected">
		<template #option="{option}">
			<ColoredAvatar
				class="itemAvatar"
				:color="option.color"
				:size="34"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:is-no-user="option.userid === undefined || option.userid === '' || option.userid === null"
				:user="option.userid"
				:display-name="option.name" />
			<div v-if="!option.activated" class="payerDisabledMask disabled" />
			<span class="select-display-name">{{ option.displayName }}</span>
		</template>
		<template #singleLabel="{option}">
			<ColoredAvatar
				class="itemAvatar"
				:color="option.color"
				:size="34"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:is-no-user="option.userid === undefined || option.userid === '' || option.userid === null"
				:user="option.userid"
				:display-name="option.name" />
			<div v-if="!option.activated" class="payerDisabledMask disabled" />
			<span class="select-display-name">{{ option.displayName }}</span>
		</template>
	</Multiselect>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import ColoredAvatar from './ColoredAvatar'

export default {
	name: 'MemberMultiSelect',

	components: {
		ColoredAvatar,
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
		options: {
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

	methods: {
		onMemberSelected(selected) {
			this.$emit('input', selected)
		},
	},
}
</script>

<style scoped lang="scss">
.memberMultiSelect {
	height: 44px;
}

.select-display-name {
	margin-left: 5px;
	margin-right: auto;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
}

.payerDisabledMask.disabled {
	display: block;
	width: 36px;
	height: 36px;
	background-image: url('../../css/images/forbidden.svg');
	margin: -1px 0 0 -1px;
	position: absolute;
}
</style>
