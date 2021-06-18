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
		:class="{'selectedproject': selected}"
		:allow-collapse="true"
		:open="selected"
		:force-menu="false"
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
			<AppNavigationMemberItem v-for="member in members"
				:key="member.id"
				class="memberItem"
				:member="member"
				:project-id="project.id"
				:in-navigation="true"
				:precision="precision"
				@member-edited="onMemberEdited" />
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
		precision() {
			// here we determine how much precision is necessary to display correct balances (#117)
			return this.project.precision
		},
	},
	beforeMount() {
	},
	methods: {
		onProjectClick() {
			this.$emit('project-clicked', this.project.id)
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
</style>
