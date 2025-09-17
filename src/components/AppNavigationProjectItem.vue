<template>
	<NcAppNavigationItem v-if="deleting"
		:name="t('cospend', 'Are you sure?')"
		:undo="true"
		@undo="cancelDeletion">
		<template #counter>
			<Countdown :duration="7" />
		</template>
	</NcAppNavigationItem>
	<NcAppNavigationItem v-else
		:name="project.name"
		:title="title"
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
			<FolderNetworkIcon v-if="project.federated && selected && !project.archived_ts"
				class="icon folder-icon-primary"
				:size="20" />
			<FolderNetworkOutlineIcon v-else-if="project.federated && !selected && !project.archived_ts"
				class="icon folder-icon"
				:size="20" />
			<FolderIcon v-else-if="selected && !project.archived_ts"
				class="icon folder-icon-primary"
				:size="20" />
			<FolderOutlineIcon v-else-if="!selected && !project.archived_ts"
				class="icon folder-icon"
				:size="20" />
			<ArchiveIcon v-else-if="selected && project.archived_ts"
				class="icon folder-icon-primary"
				:size="20" />
			<ArchiveOutlineIcon v-else-if="!selected && project.archived_ts"
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
			<NcActionButton v-if="!pageIsPublic && !project.federated"
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
					<AccountPlusIcon :size="20" />
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
			<NcActionButton v-if="project.federated"
				:close-after-click="true"
				@click="onLeaveShareClick">
				<template #icon>
					<CloseNetworkIcon :size="20" />
				</template>
				{{ t('cospend', 'Leave share') }}
			</NcActionButton>
		</template>
		<template #default>
			<NcAppNavigationItem v-if="members.length < 1"
				:name="t('cospend', 'Add a member')"
				@click="onAddMemberClick">
				<template #icon>
					<AccountPlusIcon />
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
				@safe-click="onMemberClick(member.id)" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import CloseNetworkIcon from 'vue-material-design-icons/CloseNetwork.vue'
import FolderNetworkIcon from 'vue-material-design-icons/FolderNetwork.vue'
import FolderNetworkOutlineIcon from 'vue-material-design-icons/FolderNetworkOutline.vue'
import DeleteVariantIcon from 'vue-material-design-icons/DeleteVariant.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import ArchiveCancelIcon from 'vue-material-design-icons/ArchiveCancel.vue'
import ArchiveOutlineIcon from 'vue-material-design-icons/ArchiveOutline.vue'

import ReimburseIcon from './icons/ReimburseIcon.vue'

import AppNavigationMemberItem from './AppNavigationMemberItem.vue'
import Countdown from './Countdown.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'

import { emit } from '@nextcloud/event-bus'
import * as constants from '../constants.js'
import { Timer, getSortedMembers } from '../utils.js'
import * as network from '../network.js'

export default {
	name: 'AppNavigationProjectItem',
	components: {
		Countdown,
		ReimburseIcon,
		AppNavigationMemberItem,
		NcAppNavigationItem,
		NcActionButton,
		ChartLineIcon,
		FolderIcon,
		FolderOutlineIcon,
		CogIcon,
		ShareVariantIcon,
		AccountPlusIcon,
		DeleteIcon,
		DeleteVariantIcon,
		ArchiveIcon,
		ArchiveCancelIcon,
		ArchiveOutlineIcon,
		FolderNetworkIcon,
		FolderNetworkOutlineIcon,
		CloseNetworkIcon,
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
			cospend: OCA.Cospend.state,
			deleting: false,
			deletionTimer: null,
			menuOpen: false,
		}
	},
	computed: {
		pageIsPublic() {
			return this.cospend.pageIsPublic
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
				? t('cospend', 'Close the trash bin')
				: t('cospend', 'Show the trash bin')
		},
		title() {
			return this.project.federated
				? t('cospend', 'Project {projectName} ({projectId}) shared by {displayName} ({cloudId})', {
					projectId: this.project.federation.remote_project_id,
					projectName: this.project.name,
					cloudId: this.project.federation.inviter_cloud_id,
					displayName: this.project.federation.inviter_display_name,
				})
				: this.project.name !== this.project.id
					? this.project.name + ' (' + this.project.id + ')'
					: this.project.name
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
		onLeaveShareClick() {
			network.rejectInvitation(this.project.federation.invitation_id)
				.then(response => {
				})
				.catch(error => {
					console.error(error)
				})
				.then(() => {
					this.$nextTick(() => {
						emit('remove-project', this.project.id)
					})
				})
		},
		onUpdateMenuOpen(isOpen) {
			this.menuOpen = isOpen
		},
	},
}
</script>

<style scoped lang="scss">
.memberItem {
	padding-left: 20px !important;
}

:deep(.detailButton) {
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
