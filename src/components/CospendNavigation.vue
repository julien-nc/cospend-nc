<template>
	<AppNavigation>
		<template #list>
			<AppNavigationItem v-if="!pageIsPublic && !loading"
				class="addProjectItem"
				icon="icon-add"
				:editable="true"
				:title="t('cospend', 'New project')"
				:edit-placeholder="t('cospend', 'New project name')"
				:edit-label="t('cospend', 'New empty project')"
				:loading="importingProject"
				:menu-open="importMenuOpen"
				@click="importMenuOpen = true"
				@update:title="$emit('create-project', $event)"
				@update:menuOpen="updateImportMenuOpen">
				<template #actions>
					<ActionButton
						icon="icon-download"
						:close-after-click="true"
						@click="onImportClick">
						{{ t('cospend', 'Import csv project') }}
					</ActionButton>
					<ActionButton
						icon="icon-download"
						:close-after-click="true"
						@click="onImportSWClick">
						{{ t('cospend', 'Import SplitWise project') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<h2 v-if="loading"
				class="icon-loading-small loading-icon" />
			<EmptyContent v-else-if="sortedProjectIds.length === 0"
				icon="icon-folder">
				{{ t('cospend', 'No projects yet') }}
			</EmptyContent>
			<AppNavigationProjectItem
				v-for="id in sortedProjectIds"
				:key="id"
				:project="projects[id]"
				:members="projects[id].members"
				:selected="id === selectedProjectId"
				:selected-member-id="selectedMemberId"
				:member-order="cospend.memberOrder"
				@project-clicked="onProjectClicked"
				@delete-project="onDeleteProject"
				@stats-clicked="onStatsClicked"
				@settle-clicked="onSettleClicked"
				@detail-clicked="onDetailClicked"
				@share-clicked="onShareClicked"
				@new-member-clicked="onNewMemberClicked"
				@member-edited="onMemberEdited"
				@member-click="$emit('member-click', id, $event)" />
		</template>
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<button class="settings-button" @click="showSettings">
						{{ t('cospend', 'Cospend settings') }}
					</button>
				</div>
			</div>
		</template>
	</AppNavigation>
</template>

<script>
import AppNavigationProjectItem from './AppNavigationProjectItem'

import cospend from '../state'
import * as constants from '../constants'
import { strcmp, importCospendProject, importSWProject } from '../utils'

import ClickOutside from 'vue-click-outside'

import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

import { emit } from '@nextcloud/event-bus'
import { showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'CospendNavigation',
	components: {
		AppNavigationProjectItem,
		AppNavigation,
		EmptyContent,
		AppNavigationItem,
		ActionButton,
	},
	directives: {
		ClickOutside,
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
	},
	data() {
		return {
			opened: false,
			creating: false,
			cospend,
			pageIsPublic: cospend.pageIsPublic,
			importMenuOpen: false,
			importingProject: false,
		}
	},
	computed: {
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
		showSettings() {
			emit('show-settings')
		},
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
		},
		onProjectClicked(projectid) {
			this.$emit('project-clicked', projectid)
		},
		onDeleteProject(projectid) {
			this.$emit('delete-project', projectid)
		},
		onStatsClicked(projectid) {
			this.$emit('stats-clicked', projectid)
		},
		onSettleClicked(projectid) {
			this.$emit('settle-clicked', projectid)
		},
		onDetailClicked(projectid) {
			this.$emit('detail-clicked', projectid)
		},
		onShareClicked(projectid) {
			this.$emit('share-clicked', projectid)
		},
		onNewMemberClicked(projectid) {
			this.$emit('new-member-clicked', projectid)
		},
		onMemberEdited(projectid, memberid) {
			this.$emit('member-edited', projectid, memberid)
		},
		onImportClick() {
			importCospendProject(() => {
				this.importingProject = true
			}, (data) => {
				this.$emit('project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingProject = false
			})
		},
		onImportSWClick() {
			importSWProject(() => {
				this.importingProject = true
			}, (data) => {
				this.$emit('project-imported', data)
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
.addProjectItem {
	position: sticky;
	top: 0;
	z-index: 1000;
	border-bottom: 1px solid var(--color-border);
	background-color: var(--color-main-background);
	&:hover {
		background-color: var(--color-background-hover);
	}
}

::v-deep .selectedproject,
::v-deep .selectedmember {
	> a,
	> div {
		background: var(--color-background-dark, lightgrey);
	}

	> a {
		font-weight: bold;
	}
}

#app-settings-content {
	p {
		margin-top: 20px;
		margin-bottom: 20px;
		color: var(--color-text-light);
	}
}

.project-create {
	order: 1;
	display: flex;
	height: 44px;
	form {
		display: flex;
		flex-grow: 1;
		input[type='text'] {
			flex-grow: 1;
		}
	}
}

.buttonItem {
	border-bottom: solid 1px var(--color-border);
}

.loading-icon {
	margin-top: 16px;
}
</style>
