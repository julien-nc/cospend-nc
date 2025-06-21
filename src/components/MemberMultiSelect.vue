<template>
	<NcSelect
		:model-value="selectedMemberItem"
		class="memberMultiSelect"
		:input-label="inputLabel"
		:aria-label-combobox="inputLabel ? undefined : t('cospend', 'Member select')"
		label="displayName"
		:disabled="disabled"
		:placeholder="placeholder"
		:options="formattedOptions"
		:append-to-body="false"
		:clearable="false"
		@update:model-value="onMemberSelected">
		<template #option="option">
			<div class="memberSelectOption">
				<MemberAvatar
					v-if="option.id"
					:member="option"
					:hide-status="true"
					:size="34" />
				<span class="select-display-name">{{ option.displayName }}</span>
			</div>
		</template>
		<template #selected-option="option">
			<div class="memberSelectOption">
				<MemberAvatar
					v-if="option.id"
					:member="option"
					:hide-status="true"
					:size="24" />
				<span class="select-display-name">{{ option.displayName }}</span>
			</div>
		</template>
	</NcSelect>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

import MemberAvatar from './avatar/MemberAvatar.vue'

import { getSmartMemberName } from '../utils.js'

export default {
	name: 'MemberMultiSelect',

	components: {
		MemberAvatar,
		NcSelect,
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
		inputLabel: {
			type: [String, null],
			default: null,
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
	//height: 44px;

	.memberSelectOption {
		display: flex;
		align-items: center;
	}

	.select-display-name {
		margin-left: 5px;
		margin-right: auto;
		text-overflow: ellipsis;
		overflow: hidden;
		white-space: nowrap;
	}
}
</style>
