<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
						<NcActionButton v-for="action in pluginCreateProjectActions"
							:key="action.plugin.id + ':' + action.ep.id"
							:close-after-click="true"
							@click="onPluginCreateProjectActionClick(action)">
							<template #icon>
								<PluginIcon :src="action.ep.icon" />
							</template>
							{{ action.ep.name }}
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
						:name="t('cospend', 'My cumulated balance')">
						<template #icon>
							<ColoredAvatar :user="currentUserId" />
						</template>
						<template #counter>
							<NcCounterBubble :class="balanceClass"
								:count="myBalance"
								:title="myBalance"
								:raw="true" />
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
					<NcAppNavigationItem v-for="item in pluginNavItems"
						:key="item.plugin.id + '-' + item.ep.id"
						:name="item.ep.name"
						@click="onPluginNavItemClick(item)">
						<template #icon>
							<PluginIcon :src="item.ep.icon" />
						</template>
						<template v-if="item.counter !== undefined && item.counter !== null" #counter>
							<NcCounterBubble :count="item.counter"
								:raw="typeof item.counter === 'string'" />
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

import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { showSuccess } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'

import PluginIcon from '../plugin/PluginIcon.vue'
import { getEntryPoints } from '../plugin/registry.js'

export default {
	name: 'CospendNavigation',
	components: {
		ColoredAvatar,
		AppNavigationUnreachableProjectItem,
		PendingInvitationsModal,
		NewProjectModal,
		AppNavigationProjectItem,
		PluginIcon,
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
			// plugin nav item counter overrides, keyed by 'pluginId::entryPointId';
			// a key holding undefined (or being absent) means "use the registered counter"
			pluginNavItemCounters: {},
		}
	},
	computed: {
		showMyBalance() {
			return this.cospend.showMyBalance
		},
		myBalance() {
			const cumulativeBalance = Object.values(this.projects)
				.filter(p => p.archived_ts === null)
				.map(p => {
					const me = p.members.find(m => m.userid === this.currentUserId)
					return me ? me.balance : null
				})
				.filter(b => b !== null)
				.reduce((acc, balance) => acc + balance, 0)
			return cumulativeBalance.toFixed(this.cospend.maxPrecision || 2)
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
		pluginNavItems() {
			return getEntryPoints('navItem').map((item) => ({
				plugin: item.plugin,
				ep: item.ep,
				counter: this.pluginNavItemCounter(item.plugin.id, item.ep.id, item.ep.counter),
			}))
		},
		pluginCreateProjectActions() {
			return getEntryPoints('createProjectAction')
		},
		editionAccess() {
			return this.selectedProjectId && this.projects[this.selectedProjectId].myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
	},
	beforeMount() {
	},
	mounted() {
		subscribe('cospend:set-navitem-counter', this.onSetNavItemCounter)
	},
	beforeUnmount() {
		unsubscribe('cospend:set-navitem-counter', this.onSetNavItemCounter)
	},
	methods: {
		pluginNavItemCounterKey(pluginId, entryPointId) {
			return pluginId + '::' + entryPointId
		},
		pluginNavItemCounter(pluginId, entryPointId, fallback) {
			const override = this.pluginNavItemCounters[this.pluginNavItemCounterKey(pluginId, entryPointId)]
			return override === undefined ? fallback : override
		},
		/**
		 * Handle the 'cospend:set-navitem-counter' event: validate the payload and
		 * store the counter override for one of the emitting plugin's own nav items.
		 */
		onSetNavItemCounter(payload) {
			const { pluginId, navItemId, count } = payload ?? {}
			if (typeof pluginId !== 'string' || pluginId === '') {
				console.warn('[cospend] cospend:set-navitem-counter: missing pluginId, ignored')
				return
			}
			const items = getEntryPoints('navItem').filter((item) => item.plugin.id === pluginId)
			if (items.length === 0) {
				console.warn(`[cospend] cospend:set-navitem-counter: plugin ${pluginId} has no visible navItem, ignored`)
				return
			}
			let item
			if (navItemId === undefined || navItemId === null || navItemId === '') {
				if (items.length > 1) {
					console.warn(`[cospend] cospend:set-navitem-counter: plugin ${pluginId} has several navItems, navItemId is required, ignored`)
					return
				}
				item = items[0]
			} else {
				item = items.find((i) => i.ep.id === navItemId)
				if (!item) {
					console.warn(`[cospend] cospend:set-navitem-counter: plugin ${pluginId} has no navItem with id "${navItemId}", ignored`)
					return
				}
			}
			if (count !== null
				&& (typeof count !== 'number' || !Number.isFinite(count))
				&& (typeof count !== 'string' || count === '')) {
				console.warn(`[cospend] cospend:set-navitem-counter: count must be a finite number, a non-empty string or null, ignored (plugin ${pluginId})`)
				return
			}
			const key = this.pluginNavItemCounterKey(item.plugin.id, item.ep.id)
			// null stores an explicit "no badge"; a number/string (re)sets the badge
			this.pluginNavItemCounters[key] = count
		},
		toggleArchivedProjects() {
			this.showArchivedProjects = !this.showArchivedProjects
			emit('cospend:deselect-project')
		},
		showSettings() {
			emit('cospend:show-settings')
		},
		onPluginNavItemClick(item) {
			emit('cospend:plugin-navitem-clicked', {
				pluginId: item.plugin.id,
				entryPointId: item.ep.id,
			})
		},
		onPluginCreateProjectActionClick(action) {
			emit('cospend:plugin-create-project-action', {
				pluginId: action.plugin.id,
				entryPointId: action.ep.id,
			})
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
				emit('cospend:project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingProject = false
			})
		},
		onImportSWClick() {
			importSWProject(() => {
				this.importingProject = true
			}, (data) => {
				emit('cospend:project-imported', data)
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
