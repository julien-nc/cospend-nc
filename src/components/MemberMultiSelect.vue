<template>
	<Multiselect
		:value="selectedMemberItem"
		class="memberMultiSelect multiSelect"
		label="displayName"
		track-by="id"
		:disabled="disabled"
		:placeholder="placeholder"
		:options="formattedOptions"
		:user-select="true"
		:internal-search="true"
		@input="onMemberSelected">
		<template #option="{option}">
			<ColoredAvatar
				v-if="option.id"
				class="itemAvatar"
				:color="option.color"
				:size="34"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:is-no-user="option.userid === undefined || option.userid === '' || option.userid === null"
				:user="option.userid"
				:display-name="option.name" />
			<div v-if="option.id && !option.activated" class="payerDisabledMask disabled" />
			<span class="select-display-name">{{ option.displayName }}</span>
		</template>
		<template #singleLabel="{option}">
			<ColoredAvatar
				v-if="option.id"
				class="itemAvatar"
				:color="option.color"
				:size="34"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:is-no-user="option.userid === undefined || option.userid === '' || option.userid === null"
				:user="option.userid"
				:display-name="option.name" />
			<div v-if="option.id && !option.activated" class="payerDisabledMask disabled" />
			<span class="select-display-name">{{ option.displayName }}</span>
		</template>
	</Multiselect>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect.js'
import ColoredAvatar from './ColoredAvatar.vue'
import { getSmartMemberName } from '../utils.js'

export default {
	name: 'MemberMultiSelect',

	components: {
		ColoredAvatar,
		Multiselect,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
		placeholder: {
			type: String,
			required: true,
		},
		members: {
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
			return this.members.map(member => {
				return {
					...member,
					displayName: this.myGetSmartMemberName(member),
				}
			})
		},
		selectedMemberItem() {
			return this.value
				? {
					...this.value,
					displayName: this.myGetSmartMemberName(this.value),
				}
				: null
		},
	},

	methods: {
		onMemberSelected(selected) {
			this.$emit('input', selected)
		},
		myGetSmartMemberName(member) {
			if (member.id === null) {
				return member.name
			}
			let smartName = getSmartMemberName(this.projectId, member.id)
			if (smartName === t('cospend', 'You')) {
				smartName += ' (' + member.name + ')'
			}
			return smartName
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
	margin: 0 0 0 -1px;
	position: absolute;
}
</style>
