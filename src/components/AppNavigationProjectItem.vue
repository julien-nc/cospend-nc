<template>
	<NcAppNavigationItem v-if="deleting"
		:name="t('cospend', 'Are you sure?')"
		:undo="true"
		@undo="cancelDeletion">
		<template #counter>
			<vac :end-time="new Date().getTime() + (7000)">
				<template #process="{ timeObj }">
					<span>{{ `${timeObj.s}` }}</span>
				</template>
				<!--template v-slot:finish>
					<span>Done!</span>
				</template-->
			</vac>
		</template>
	</NcAppNavigationItem>
	<NcAppNavigationItem v-else
		:name="project.name"
		:allow-collapse="true"
		:open="selected"
		:active="selected"
		:force-display-actions="true"
		:force-menu="false"
		:menu-open="menuOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen"
		@click="onProjectClick">
		<template #icon>
			<FolderIcon v-if="selected && !project.archived_ts"
				class="icon folder-icon-primary"
				:size="20" />
			<FolderOutlineIcon v-if="!selected && !project.archived_ts"
				class="icon folder-icon"
				:size="20" />
			<ArchiveIcon v-if="selected && project.archived_ts"
				class="icon folder-icon-primary"
				:size="20" />
			<ArchiveOutlineIcon v-if="!selected && project.archived_ts"
				class="icon folder-icon"
				:size="20" />
		</template>
		<!--template #counter>
		</template-->
		<template #actions>
			<NcActionButton
				:close-after-click="true"
				class="detailButton"
				@click="onDetailClick">
				<template #icon>
					<CogIcon :size="20" />
				</template>
				{{ t('cospend', 'Settings') }}
			</NcActionButton>
			<NcActionButton v-if="!pageIsPublic"
				:close-after-click="true"
				class="detailButton"
				@click="onShareClick">
				<template #icon>
					<ShareVariantIcon :size="20" />
				</template>
				{{ t('cospend', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="maintenerAccess"
				@click="onAddMemberClick">
				<template #icon>
					<AccountIcon :size="20" />
				</template>
				{{ t('cospend', 'Add member') }}
			</NcActionButton>
			<NcActionButton
				:close-after-click="true"
				@click="onTrashbinClick">
				<template #icon>
					<DeleteVariantIcon />
				</template>
				{{ trashbinActionLabel }}
			</NcActionButton>
			<NcActionButton
				:close-after-click="true"
				@click="onStatsClick">
				<template #icon>
					<ChartLineIcon
						class="icon"
						:size="20" />
				</template>
				{{ t('cospend', 'Statistics') }}
			</NcActionButton>
			<NcActionButton
				:close-after-click="true"
				@click="onSettleClick">
				<template #icon>
					<ReimburseIcon :size="20" />
				</template>
				{{ t('cospend', 'Project settlement') }}
			</NcActionButton>
			<NcActionButton v-if="adminAccess && !project.archived_ts"
				:close-after-click="true"
				@click="onArchiveProjectClick">
				<template #icon>
					<ArchiveIcon :size="20" />
				</template>
				{{ t('cospend', 'Archive') }}
			</NcActionButton>
			<NcActionButton v-if="adminAccess && project.archived_ts"
				:close-after-click="true"
				@click="onArchiveProjectClick">
				<template #icon>
					<ArchiveCancelIcon :size="20" />
				</template>
				{{ t('cospend', 'Unarchive') }}
			</NcActionButton>
			<NcActionButton v-if="adminAccess"
				:close-after-click="true"
				@click="onDeleteProjectClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('cospend', 'Delete') }}
			</NcActionButton>
		</template>
		<template #default>
			<NcAppNavigationItem v-if="members.length < 1"
				:name="t('cospend', 'Add a member')"
				@click="onAddMemberClick">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<AppNavigationMemberItem v-for="member in sortedMembers"
				:key="member.id"
				class="memberItem"
				:member="member"
				:selected="selectedMemberId === member.id"
				:project-id="project.id"
				:in-navigation="true"
				:precision="precision"
				@click="onMemberClick(member.id)" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import DeleteVariantIcon from 'vue-material-design-icons/DeleteVariant.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import ArchiveCancelIcon from 'vue-material-design-icons/ArchiveCancel.vue'
import ArchiveOutlineIcon from 'vue-material-design-icons/ArchiveOutline.vue'

import ReimburseIcon from './icons/ReimburseIcon.vue'

import AppNavigationMemberItem from './AppNavigationMemberItem.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

import ClickOutside from 'vue-click-outside'
import { emit } from '@nextcloud/event-bus'
import cospend from '../state.js'
import * as constants from '../constants.js'
import { Timer, getSortedMembers } from '../utils.js'

export default {
	name: 'AppNavigationProjectItem',
	components: {
		ReimburseIcon,
		AppNavigationMemberItem,
		NcAppNavigationItem,
		NcActionButton,
		ChartLineIcon,
		FolderIcon,
		FolderOutlineIcon,
		CogIcon,
		ShareVariantIcon,
		AccountIcon,
		PlusIcon,
		DeleteIcon,
		DeleteVariantIcon,
		ArchiveIcon,
		ArchiveCancelIcon,
		ArchiveOutlineIcon,
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
		trashbinEnabled: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			deleting: false,
			deletionTimer: null,
			menuOpen: false,
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
			return getSortedMembers(this.members, this.memberOrder)
		},
		trashbinActionLabel() {
			return this.selected && this.trashbinEnabled
				? t('cospend', 'Close trashbin')
				: t('cospend', 'Show trashbin')
		},
	},
	beforeMount() {
	},
	methods: {
		onProjectClick() {
			emit('project-clicked', this.project.id)
		},
		onMemberClick(memberId) {
			emit('member-click', { projectId: this.project.id, memberId })
		},
		onArchiveProjectClick() {
			emit('archive-project', this.project.id)
		},
		onDeleteProjectClick() {
			this.deleting = true
			this.deletionTimer = new Timer(() => {
				emit('delete-project', this.project.id)
			}, 7000)
		},
		cancelDeletion() {
			this.deleting = false
			this.deletionTimer.pause()
			delete this.deletionTimer
		},
		onStatsClick() {
			emit('stats-clicked', this.project.id)
		},
		onTrashbinClick() {
			emit('trashbin-clicked', this.project.id)
		},
		onSettleClick() {
			emit('settle-clicked', this.project.id)
		},
		onDetailClick() {
			emit('detail-clicked', this.project.id)
		},
		onShareClick() {
			emit('share-clicked', this.project.id)
		},
		onAddMemberClick() {
			emit('new-member-clicked', this.project.id)
		},
		onUpdateMenuOpen(isOpen) {
			this.menuOpen = isOpen
		},
	},
}
</script>

<style scoped lang="scss">
.memberItem {
	height: 44px;
	// padding-left: 30px !important;
	padding-right: 0 !important;
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
