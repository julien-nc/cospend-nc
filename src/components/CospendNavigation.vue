<template>
	<NcAppNavigation>
		<template v-if="!pageIsPublic && !loading" #search>
			<NcAppNavigationSearch v-model="projectFilterQuery"
				label="plop"
				:placeholder="t('cospend', 'Search projects')">
				<template #actions>
					<NcActions>
						<template #icon>
							<FolderPlusIcon :title="t('cospend', 'Create a project')" />
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
					<NcAppNavigationItem v-if="!pageIsPublic"
						:name="showMyBalance && hasBalance ? t('cospend', 'Cumulative Balance') : t('cospend', 'Cross-project balances')"
						@click="showCrossProjectBalanceView">
						<template #icon>
							<ColoredAvatar :user="currentUserId" />
						</template>
						<template v-if="showMyBalance && hasBalance" #extra>
							<div class="balance-chips">
								<div v-for="(amount, currency) in myBalanceByCurrency"
									:key="currency"
									class="balance-item">
									<span class="currency-chip">{{ currency }}</span>
									<span class="balance-amount" :class="getBalanceClass(amount)">{{ formatBalanceAmount(amount) }}</span>
								</div>
							</div>
						</template>
					</NcAppNavigationItem>
					<NcAppNavigationItem v-if="!pageIsPublic && pendingInvitations.length > 0"
						:name="t('cospend', 'Pending share invitations')"
						@click="showPendingInvitations = true">
						<template #icon>
							<WebIcon />
						</template>
						<template #counter>
							<NcCounterBubble :count="pendingInvitations.length" />
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
							<NcCounterBubble :count="sortedProjectIds.length - filteredProjectIds.length" />
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

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'

import AppNavigationProjectItem from './AppNavigationProjectItem.vue'
import NewProjectModal from './NewProjectModal.vue'
import PendingInvitationsModal from './PendingInvitationsModal.vue'
import AppNavigationUnreachableProjectItem from './AppNavigationUnreachableProjectItem.vue'
import ColoredAvatar from './avatar/ColoredAvatar.vue'

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
			cospend: OCA.Cospend.state,
			pageIsPublic: OCA.Cospend.state.pageIsPublic,
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
			return this.cospend.showMyBalance
		},
		myBalanceByCurrency() {
			const byCurrency = {}
			Object.values(this.projects)
				.filter(p => p.archived_ts === null)
				.forEach(project => {
					const me = project.members.find(m => m.userid === this.currentUserId)
					if (me && me.balance !== null && me.balance !== undefined) {
						const currency = project.currencyname || 'EUR'
						if (!byCurrency[currency]) {
							byCurrency[currency] = 0
						}
						byCurrency[currency] += me.balance
					}
				})
			return byCurrency
		},
		hasBalance() {
			return Object.keys(this.myBalanceByCurrency).length > 0
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
		formatBalanceAmount(amount) {
			return new Intl.NumberFormat(navigator.language, {
				minimumFractionDigits: 2,
				maximumFractionDigits: this.cospend.maxPrecision || 2,
			}).format(Math.abs(amount))
		},
		getBalanceClass(amount) {
			return {
				balancePositive: amount >= 0.01,
				balanceNegative: amount <= -0.01,
			}
		},
		showCrossProjectBalanceView() {
			emit('show-cross-project-balances')
		},
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
:deep(.app-navigation-entry-wrapper) {
	display: flex !important;
	align-items: center !important;
}

:deep(.app-navigation-entry) {
	display: flex !important;
	align-items: center !important;
	width: 100% !important;
	gap: 0 !important;
}

:deep(.app-navigation-entry__anchor) {
	display: flex !important;
	align-items: center !important;
	flex: 1 !important;
	gap: 12px !important;
}

:deep(.app-navigation-entry__name) {
	white-space: nowrap !important;
	overflow: hidden !important;
	text-overflow: ellipsis !important;
}

:deep(.app-navigation-entry__utils) {
	display: flex !important;
	align-items: center !important;
	justify-content: flex-end !important;
}

.balance-chips {
	display: grid;
	grid-template-columns: max-content max-content;
	column-gap: 4px;
	row-gap: 2px;
	justify-content: end;
	align-items: center;
}

.balance-item {
	display: contents;
}

.balance-amount {
	grid-column: 2;
	justify-self: start;
	text-align: left;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
	font-feature-settings: 'tnum' 1;
}

.currency-chip {
	grid-column: 1;
	justify-self: end;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 2.8em;
	padding: 1px 4px;
	border-radius: 3px;
	background: var(--color-background-dark);
	font-size: 10px;
	font-weight: 700;
	text-align: center;
	text-transform: uppercase;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
	font-feature-settings: 'tnum' 1;
}

.balancePositive,
.balance-positive {
	color: var(--color-success);
}

.balanceNegative,
.balance-negative {
	color: var(--color-error);
}
</style>
