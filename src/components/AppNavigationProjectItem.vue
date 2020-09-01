<template>
	<AppNavigationItem v-if="deleting"
		title="Are you sure?"
		:undo="true"
		@undo="cancelDeletion">
		<template slot="counter">
			<vac :end-time="new Date().getTime() + (7000)">
				<template v-slot:process="{ timeObj }">
					<span>{{ `${timeObj.s}` }}</span>
				</template>
				<!--template v-slot:finish>
					<span>Done!</span>
				</template-->
			</vac>
		</template>
	</AppNavigationItem>
	<AppNavigationItem v-else
		icon="icon-folder"
		:title="project.name"
		:class="{'selectedproject': selected}"
		:allowCollapse="true"
		:open="selected"
		:forceMenu="false"
		@click="onProjectClick">
		<template slot="counter">
			<ActionButton v-if="!pageIsPublic"
				icon="icon-shared"
				class="detailButton"
				@click="onShareClick" />
			<ActionButton
				icon="icon-settings-dark"
				class="detailButton"
				@click="onDetailClick" />
		</template>
		<template slot="actions">
			<ActionButton v-if="maintenerAccess"
				icon="icon-user"
				@click="onAddMemberClick">
				{{ t('cospend', 'Add member') }}
			</ActionButton>
			<ActionButton
				icon="icon-category-monitoring"
				@click="onStatsClick">
				{{ t('cospend', 'Statistics') }}
			</ActionButton>
			<ActionButton
				icon="icon-reimburse"
				@click="onSettleClick">
				{{ t('cospend', 'Project settlement') }}
			</ActionButton>
			<ActionButton v-if="adminAccess"
				icon="icon-delete"
				@click="onDeleteProjectClick">
				{{ t('cospend', 'Delete') }}
			</ActionButton>
		</template>
		<template>
			<AppNavigationItem v-if="members.length === 0"
				icon="icon-category-disabled"
				:title="t('cospend', 'No members yet')" />
			<AppNavigationMemberItem v-for="member in members"
				:key="member.id"
				class="memberItem"
				:member="member"
				:projectId="project.id"
				:inNavigation="true"
				@memberEdited="onMemberEdited" />
		</template>
	</AppNavigationItem>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import AppNavigationMemberItem from './AppNavigationMemberItem'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

import cospend from '../state'
import * as constants from '../constants'
import { Timer } from '../utils'

export default {
	name: 'AppNavigationProjectItem',
	components: {
		AppNavigationMemberItem,
		AppNavigationItem,
		ActionButton,
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
	},
	beforeMount() {
	},
	methods: {
		onProjectClick() {
			this.$emit('projectClicked', this.project.id)
		},
		onDeleteProjectClick() {
			this.deleting = true
			const that = this
			this.deletionTimer = new Timer(() => {
				// that.deleting = false
				that.$emit('deleteProject', that.project.id)
			}, 7000)
		},
		cancelDeletion() {
			this.deleting = false
			this.deletionTimer.pause()
			delete this.deletionTimer
		},
		onStatsClick() {
			this.$emit('statsClicked', this.project.id)
		},
		onSettleClick() {
			this.$emit('settleClicked', this.project.id)
		},
		onDetailClick() {
			this.$emit('detailClicked', this.project.id)
		},
		onShareClick() {
			this.$emit('shareClicked', this.project.id)
		},
		onAddMemberClick() {
			this.$emit('newMemberClicked', this.project.id)
		},
		onMemberEdited(projectid, memberid) {
			this.$emit('memberEdited', projectid, memberid)
		},
	},
}
</script>

<style scoped lang="scss">
.memberItem {
	padding-left: 30px !important;
}
</style>
