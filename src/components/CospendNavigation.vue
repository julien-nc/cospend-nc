<template>
	<NcAppNavigation>
		<template v-if="!pageIsPublic && !loading" #search>
			<NcAppNavigationSearch v-model="projectFilterQuery"
				label="plop"
				:placeholder="t('cospend', 'Search projects')">
				<template #actions>
					<NcActions>
						<template #icon>
							<FolderPlusIcon />
						</template>
						<NcActionButton
							:close-after-click="true"
							@click="showCreationModal = true">
							<template #icon>
								<PlusIcon />
							</template>
							{{ t('cospend', 'Create empty project') }}
						</NcActionButton>
						<NcActionButton
							:close-after-click="true"
							@click="onImportClick">
							<template #icon>
								<FileImportIcon />
							</template>
							{{ t('cospend', 'Import csv project') }}
						</NcActionButton>
						<NcActionButton
							:close-after-click="true"
							@click="onImportSWClick">
							<template #icon>
								<FileImportIcon />
							</template>
							{{ t('cospend', 'Import SplitWise project') }}
						</NcActionButton>
					</NcActions>
				</template>
			</NcAppNavigationSearch>
		</template>
		<template #list>
			<NewProjectModal v-if="showCreationModal"
				@close="showCreationModal = false" />
			<NcLoadingIcon v-if="loading" :size="24" />
			<NcEmptyContent v-else-if="sortedProjectIds.length === 0"
				:name="t('cospend', 'No projects yet')"
				:title="t('cospend', 'No projects yet')">
				<template #icon>
					<FolderIcon />
				</template>
			</NcEmptyContent>
			<AppNavigationProjectItem
				v-for="id in filteredProjectIds"
				:key="id"
				:project="projects[id]"
				:members="projects[id].members"
				:selected="id === selectedProjectId"
				:selected-member-id="selectedMemberId"
				:member-order="cospend.memberOrder"
				:trashbin-enabled="trashbinEnabled" />
			<AppNavigationUnreachableProjectItem v-for="invite in unreachableProjects"
				:key="'invite-' + invite.id"
				:invite="invite" />
		</template>
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<PendingInvitationsModal v-if="!pageIsPublic && showPendingInvitations"
						:invitations="pendingInvitations"
						@close="showPendingInvitations = false" />
					<NcAppNavigationItem v-if="!pageIsPublic && showMyBalance && myBalance !== null"
						:name="t('cospend', 'My balance')">
						<template #icon>
							<ColoredAvatar :user="currentUserId" />
						</template>
						<template #counter>
							<NcCounterBubble>
								<span :class="balanceClass">{{ myBalance }}</span>
							</NcCounterBubble>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem v-if="!pageIsPublic && pendingInvitations.length > 0"
						:name="t('cospend', 'Pending share invitations')"
						@click="showPendingInvitations = true">
						<template #icon>
							<WebIcon />
						</template>
						<template #counter>
							<NcCounterBubble>
								{{ pendingInvitations.length }}
							</NcCounterBubble>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem v-if="!pageIsPublic && (archivedProjectIds.length > 0 || showArchivedProjects)"
						:name="showArchivedProjects ? t('cospend', 'Show active projects') : t('cospend', 'Show archived projects')"
						@click="toggleArchivedProjects">
						<template #icon>
							<CalendarIcon v-if="showArchivedProjects" />
							<ArchiveLockIcon v-else />
						</template>
						<template #counter>
							<NcCounterBubble>
								{{ sortedProjectIds.length - filteredProjectIds.length }}
							</NcCounterBubble>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem
						:name="t('cospend', 'Cospend settings')"
						@click="showSettings">
						<template #icon>
							<CogIcon />
						</template>
					</NcAppNavigationItem>
				</div>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import WebIcon from 'vue-material-design-icons/Web.vue'
import FolderPlusIcon from 'vue-material-design-icons/FolderPlus.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import ArchiveLockIcon from 'vue-material-design-icons/ArchiveLock.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppNavigationSearch from '@nextcloud/vue/dist/Components/NcAppNavigationSearch.js'

import AppNavigationProjectItem from './AppNavigationProjectItem.vue'
import NewProjectModal from './NewProjectModal.vue'
import PendingInvitationsModal from './PendingInvitationsModal.vue'
import AppNavigationUnreachableProjectItem from './AppNavigationUnreachableProjectItem.vue'
import ColoredAvatar from './avatar/ColoredAvatar.vue'

import cospend from '../state.js'
import * as constants from '../constants.js'
import { strcmp, importCospendProject, importSWProject } from '../utils.js'

import { emit } from '@nextcloud/event-bus'
import { showSuccess } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'CospendNavigation',
	components: {
		ColoredAvatar,
		AppNavigationUnreachableProjectItem,
		PendingInvitationsModal,
		NewProjectModal,
		AppNavigationProjectItem,
		NcAppNavigation,
		NcEmptyContent,
		NcAppNavigationItem,
		NcActionButton,
		NcCounterBubble,
		NcLoadingIcon,
		NcActions,
		NcAppNavigationSearch,
		CogIcon,
		FileImportIcon,
		PlusIcon,
		FolderIcon,
		FolderPlusIcon,
		ArchiveLockIcon,
		CalendarIcon,
		WebIcon,
	},
	props: {
		projects: {
			type: Object,
			required: true,
		},
		selectedProjectId: {
			type: String,
			default: '',
		},
		selectedMemberId: {
			type: Number,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		trashbinEnabled: {
			type: Boolean,
			default: false,
		},
		pendingInvitations: {
			type: Array,
			default: () => [],
		},
		unreachableProjects: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		return {
			opened: false,
			creating: false,
			cospend,
			pageIsPublic: cospend.pageIsPublic,
			importMenuOpen: false,
			importingProject: false,
			showCreationModal: false,
			showArchivedProjects: false,
			showPendingInvitations: false,
			projectFilterQuery: '',
			currentUserId: getCurrentUser()?.uid,
		}
	},
	computed: {
		showMyBalance() {
			return cospend.showMyBalance
		},
		myBalance() {
			return Object.values(this.projects)
				.filter(p => p.archived_ts === null)
				.map(p => {
					const me = p.members.find(m => m.userid === this.currentUserId)
					return me ? me.balance : null
				})
				.filter(b => b !== null)
				.reduce((acc, balance) => acc + balance, 0)
		},
		balanceClass() {
			return {
				balancePositive: this.myBalance >= 0.01,
				balanceNegative: this.myBalance <= -0.01,
			}
		},
		filteredProjectIds() {
			const projectIds = this.showArchivedProjects ? this.archivedProjectIds : this.nonArchivedProjectIds
			return this.projectFilterQuery === ''
				? projectIds
				: projectIds.filter(id => this.projects[id].name.toLowerCase().includes(this.projectFilterQuery.toLowerCase()))
		},
		nonArchivedProjectIds() {
			return this.sortedProjectIds.filter(id => this.projects[id].archived_ts === null)
		},
		archivedProjectIds() {
			return this.sortedProjectIds.filter(id => this.projects[id].archived_ts !== null)
		},
		sortedProjectIds() {
			if (this.cospend.sortOrder === 'name') {
				return Object.keys(this.projects).sort((a, b) => {
					return strcmp(this.projects[a].name, this.projects[b].name)
				})
			} else if (this.cospend.sortOrder === 'change') {
				return Object.keys(this.projects).sort((a, b) => {
					return this.projects[b].lastchanged - this.projects[a].lastchanged
				})
			} else {
				return Object.keys(this.projects)
			}
		},
		editionAccess() {
			return this.selectedProjectId && this.projects[this.selectedProjectId].myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
	},
	beforeMount() {
	},
	methods: {
		toggleArchivedProjects() {
			this.showArchivedProjects = !this.showArchivedProjects
			emit('deselect-project')
		},
		showSettings() {
			emit('show-settings')
		},
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
		},
		onImportClick() {
			importCospendProject(() => {
				this.importingProject = true
			}, (data) => {
				emit('project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingProject = false
			})
		},
		onImportSWClick() {
			importSWProject(() => {
				this.importingProject = true
			}, (data) => {
				emit('project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingProject = false
			})
		},
		updateImportMenuOpen(isOpen) {
			if (!isOpen) {
				this.importMenuOpen = false
			}
		},
	},
}
</script>
<style scoped lang="scss">
// nothing
</style>
