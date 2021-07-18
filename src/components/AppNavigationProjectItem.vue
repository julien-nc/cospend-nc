<template>
	<AppNavigationItem v-if="deleting"
		title="Are you sure?"
		:undo="true"
		@undo="cancelDeletion">
		<template slot="counter">
			<vac :end-time="new Date().getTime() + (7000)">
				<template #process="{ timeObj }">
					<span>{{ `${timeObj.s}` }}</span>
				</template>
				<!--template v-slot:finish>
					<span>Done!</span>
				</template-->
			</vac>
		</template>
	</AppNavigationItem>
	<AppNavigationItem v-else
		:icon="selected ? 'icon-folder' : 'icon-filetype-folder-drag-accept'"
		:title="project.name"
		:class="{ selectedproject: selected }"
		:allow-collapse="true"
		:open="selected"
		:force-menu="false"
		@click="onProjectClick">
		<template slot="counter">
			<Actions>
				<ActionButton v-if="!pageIsPublic"
					icon="icon-shared"
					class="detailButton"
					@click="onShareClick" />
			</Actions>
			<Actions>
				<ActionButton
					icon="icon-settings-dark"
					class="detailButton"
					@click="onDetailClick" />
			</Actions>
		</template>
		<template slot="actions">
			<ActionButton v-if="maintenerAccess"
				icon="icon-user"
				@click="onAddMemberClick">
				{{ t('cospend', 'Add member') }}
			</ActionButton>
			<ActionButton
				icon="icon-category-monitoring"
				:close-after-click="true"
				@click="onStatsClick">
				{{ t('cospend', 'Statistics') }}
			</ActionButton>
			<ActionButton
				icon="icon-reimburse"
				:close-after-click="true"
				@click="onSettleClick">
				{{ t('cospend', 'Project settlement') }}
			</ActionButton>
			<ActionButton v-if="adminAccess"
				icon="icon-delete"
				:close-after-click="true"
				@click="onDeleteProjectClick">
				{{ t('cospend', 'Delete') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationItem v-if="members.length === 0"
				icon="icon-category-disabled"
				:title="t('cospend', 'No members yet')" />
			<AppNavigationMemberItem v-for="member in sortedMembers"
				:key="member.id"
				class="memberItem"
				:member="member"
				:selected="selectedMemberId === member.id"
				:project-id="project.id"
				:in-navigation="true"
				:precision="precision"
				@click="onMemberClick(member.id)"
				@member-edited="onMemberEdited" />
		</template>
	</AppNavigationItem>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import AppNavigationMemberItem from './AppNavigationMemberItem'

import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

import cospend from '../state'
import * as constants from '../constants'
import { Timer, strcmp } from '../utils'

export default {
	name: 'AppNavigationProjectItem',
	components: {
		AppNavigationMemberItem,
		AppNavigationItem,
		ActionButton,
		Actions,
	},
	directives: {
		ClickOutside,
	},
	props: {
		project: {
			type: Object,
			required: true,
		},
		members: {
			type: Array,
			required: true,
		},
		selected: {
			type: Boolean,
			required: true,
		},
		selectedMemberId: {
			type: Number,
			default: null,
		},
		memberOrder: {
			type: String,
			default: 'name',
		},
	},
	data() {
		return {
			deleting: false,
			deletionTimer: null,
		}
	},
	computed: {
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		maintenerAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.MAINTENER
		},
		adminAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.ADMIN
		},
		precision() {
			// here we determine how much precision is necessary to display correct balances (#117)
			return this.project.precision
		},
		sortedMembers() {
			if (this.memberOrder === 'name') {
				return this.members.slice().sort((a, b) => {
					return strcmp(a.name, b.name)
				})
			} else if (this.memberOrder === 'balance') {
				return this.members.slice().sort((a, b) => {
					return a.balance > b.balance
						? -1
						: a.balance < b.balance
							? 1
							: 0
				})
			}
			return this.members
		},
	},
	beforeMount() {
	},
	methods: {
		onProjectClick(e) {
			if (e.target.tagName === 'SPAN') {
				this.$emit('project-clicked', this.project.id)
			}
		},
		onMemberClick(memberId) {
			this.$emit('member-click', memberId)
		},
		onDeleteProjectClick() {
			this.deleting = true
			this.deletionTimer = new Timer(() => {
				this.$emit('delete-project', this.project.id)
			}, 7000)
		},
		cancelDeletion() {
			this.deleting = false
			this.deletionTimer.pause()
			delete this.deletionTimer
		},
		onStatsClick() {
			this.$emit('stats-clicked', this.project.id)
		},
		onSettleClick() {
			this.$emit('settle-clicked', this.project.id)
		},
		onDetailClick() {
			this.$emit('detail-clicked', this.project.id)
		},
		onShareClick() {
			this.$emit('share-clicked', this.project.id)
		},
		onAddMemberClick() {
			this.$emit('new-member-clicked', this.project.id)
		},
		onMemberEdited(projectid, memberid) {
			this.$emit('member-edited', projectid, memberid)
		},
	},
}
</script>

<style scoped lang="scss">
.memberItem {
	height: 44px;
	padding-left: 30px !important;
	padding-right: 0 !important;
}

::v-deep .icon-reimburse {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../../img/reimburse.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center;
	-webkit-mask: url('./../../img/reimburse.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
}

::v-deep .detailButton {
	border-radius: 50%;
	&:hover {
		background-color: var(--color-background-darker);
	}
	button {
		padding-right: 0 !important;
		border-radius: 50%;
	}
}
</style>
