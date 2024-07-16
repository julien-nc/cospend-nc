<template>
	<NcSelect
		:value="selectedMemberItem"
		class="memberMultiSelect"
		:input-label="inputLabel"
		:aria-label-combobox="t('cospend', 'Member select')"
		label="displayName"
		:disabled="disabled"
		:placeholder="placeholder"
		:options="formattedOptions"
		:append-to-body="false"
		:clearable="false"
		@input="onMemberSelected">
		<template #option="option">
			<div class="memberSelectOption">
				<CospendTogglableAvatar
					v-if="option.id"
					:enabled="option.activated"
					:color="option.color"
					:size="34"
					:disable-menu="true"
					:disable-tooltip="true"
					:show-user-status="false"
					:is-no-user="option.userid === undefined || option.userid === '' || option.userid === null"
					:user="option.userid"
					:display-name="option.name" />
				<span class="select-display-name">{{ option.displayName }}</span>
			</div>
		</template>
		<template #selected-option="option">
			<div class="memberSelectOption">
				<CospendTogglableAvatar
					v-if="option.id"
					:enabled="option.activated"
					:color="option.color"
					:size="34"
					:disable-menu="true"
					:disable-tooltip="true"
					:show-user-status="false"
					:is-no-user="option.userid === undefined || option.userid === '' || option.userid === null"
					:user="option.userid"
					:display-name="option.name" />
				<span class="select-display-name">{{ option.displayName }}</span>
			</div>
		</template>
	</NcSelect>
</template>

<script>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import CospendTogglableAvatar from './avatar/CospendTogglableAvatar.vue'

import { getSmartMemberName } from '../utils.js'

export default {
	name: 'MemberMultiSelect',

	components: {
		CospendTogglableAvatar,
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
