<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem v-if="!pageIsPublic && !loading"
				class="addProjectItem"
				:name="t('cospend', 'New project')"
				:loading="importingProject"
				:menu-open="importMenuOpen"
				@click="importMenuOpen = true"
				@update:menuOpen="updateImportMenuOpen">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				<template #actions>
					<NcActionButton
						:close-after-click="true"
						@click="showCreationModal = true">
						<template #icon>
							<FolderPlusIcon />
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
				</template>
			</NcAppNavigationItem>
			<NcModal v-if="showCreationModal"
				@close="showCreationModal = false">
				<div class="creation-modal-content">
					<h2>{{ t('cospend', 'Create empty project') }}</h2>
					<NcTextField :value.sync="newProjectName"
						:label="t('cospend', 'Project name')"
						:placeholder="t('cospend', 'My new project')" />
					<NcButton class="submit"
						@click="createProject">
						<template #icon>
							<ArrowRightIcon />
						</template>
						{{ t('cospend', 'Create') }}
					</NcButton>
				</div>
			</NcModal>
			<h2 v-if="loading"
				class="icon-loading-small loading-icon" />
			<NcEmptyContent v-else-if="sortedProjectIds.length === 0"
				:name="t('cospend', 'No projects yet')"
				:title="t('cospend', 'No projects yet')">
				<template #icon>
					<FolderIcon />
				</template>
			</NcEmptyContent>
			<AppNavigationProjectItem
				v-for="id in sortedProjectIds"
				:key="id"
				:project="projects[id]"
				:members="projects[id].members"
				:selected="id === selectedProjectId"
				:selected-member-id="selectedMemberId"
				:member-order="cospend.memberOrder"
				:trashbin-enabled="trashbinEnabled" />
		</template>
		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcAppNavigationItem
						:name="t('cospend', 'Archived projects')"
						@click="showSettings">
						<template #icon>
							<ArchiveLockIcon />
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
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import FolderPlusIcon from 'vue-material-design-icons/FolderPlus.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import ArchiveLockIcon from 'vue-material-design-icons/ArchiveLock.vue'

import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import AppNavigationProjectItem from './AppNavigationProjectItem.vue'

import cospend from '../state.js'
import * as constants from '../constants.js'
import { strcmp, importCospendProject, importSWProject } from '../utils.js'

import ClickOutside from 'vue-click-outside'
import { emit } from '@nextcloud/event-bus'
import { showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'CospendNavigation',
	components: {
		AppNavigationProjectItem,
		NcAppNavigation,
		NcEmptyContent,
		NcAppNavigationItem,
		NcActionButton,
		NcButton,
		NcModal,
		NcTextField,
		CogIcon,
		FileImportIcon,
		PlusIcon,
		FolderIcon,
		FolderPlusIcon,
		ArrowRightIcon,
		ArchiveLockIcon,
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
		trashbinEnabled: {
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
			showCreationModal: false,
			newProjectName: '',
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
		createProject() {
			emit('create-project', this.newProjectName)
			this.showCreationModal = false
			this.newProjectName = ''
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
.addProjectItem {
	position: sticky;
	top: 0;
	z-index: 1000;
	border-bottom: 1px solid var(--color-border);
	::v-deep .app-navigation-entry {
		background-color: var(--color-main-background-blur, var(--color-main-background));
		backdrop-filter: var(--filter-background-blur, none);
		&:hover {
			background-color: var(--color-background-hover);
		}
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

.creation-modal-content {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 16px;

	.submit {
		align-self: end;
	}
}
</style>
