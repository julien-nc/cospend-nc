<template>
	<AppNavigation>
		<template slot="list">
			<div v-if="!pageIsPublic && !loading">
				<AppNavigationNewItem
					icon="icon-add"
					:title="t('cospend', 'New project')"
					:edit-placeholder="t('cospend', 'New project name')"
					@new-item="$emit('create-project', $event)" />
			</div>
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
				@project-clicked="onProjectClicked"
				@delete-project="onDeleteProject"
				@stats-clicked="onStatsClicked"
				@settle-clicked="onSettleClicked"
				@detail-clicked="onDetailClicked"
				@share-clicked="onShareClicked"
				@new-member-clicked="onNewMemberClicked"
				@member-edited="onMemberEdited" />
		</template>
		<template slot="footer">
			<AppNavigationSettings>
				<AppNavigationItem
					v-if="!pageIsPublic"
					v-show="true"
					:title="t('cospend', 'Import csv project')"
					icon="icon-download"
					class="buttonItem"
					@click="onImportClick" />
				<AppNavigationItem
					v-if="!pageIsPublic"
					v-show="true"
					icon="icon-download"
					class="buttonItem"
					:title="t('cospend', 'Import SplitWise project')"
					@click="onImportSWClick" />
				<AppNavigationItem
					v-show="true"
					icon="icon-clippy"
					class="buttonItem"
					:title="t('cospend', 'Guest access link')"
					@click="onGuestLinkClick" />
				<div v-if="!pageIsPublic"
					class="output-dir">
					<button class="icon-folder"
						@click="onOutputDirClick">
						{{ t('cospend', 'Change output directory') }}
					</button>
					<input v-model="outputDir"
						:placeholder="t('cospend', '/Anywhere')"
						type="text"
						readonly
						@click="onOutputDirClick">
				</div>
				<div id="sort-order">
					<label for="sort-select">
						{{ t('cospend', 'Sort projects by') }}
					</label>
					<select id="sort-select" v-model="sortOrder" @change="onSortOrderChange">
						<option value="name">
							{{ t('cospend', 'Name') }}
						</option>
						<option value="change">
							{{ t('cospend', 'Last activity') }}
						</option>
					</select>
				</div>
				<div id="max-precision">
					<label for="precision">
						{{ t('cospend', 'Maximum precision') }}
					</label>
					<input id="precision"
						v-model.number="maxPrecision"
						type="number"
						min="2"
						max="10"
						step="1"
						@input="onMaxPrecisionChange">
				</div>
				<div id="use-time">
					<input id="use-time-input"
						v-model="useTime"
						class="checkbox"
						type="checkbox"
						@input="onUseTimeChange">
					<label for="use-time-input">
						{{ t('cospend', 'Use time in dates') }}
					</label>
				</div>
			</AppNavigationSettings>
		</template>
	</AppNavigation>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import AppNavigationProjectItem from './AppNavigationProjectItem'

import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import AppNavigationNewItem from '@nextcloud/vue/dist/Components/AppNavigationNewItem'

import { generateUrl } from '@nextcloud/router'
import cospend from '../state'
import * as constants from '../constants'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import * as network from '../network'

export default {
	name: 'CospendNavigation',
	components: {
		AppNavigationProjectItem,
		AppNavigation,
		AppNavigationItem,
		AppNavigationSettings,
		EmptyContent,
		AppNavigationNewItem,
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
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			opened: false,
			creating: false,
			outputDir: cospend.outputDirectory,
			pageIsPublic: cospend.pageIsPublic,
			sortOrder: cospend.sortOrder || 'name',
			maxPrecision: cospend.maxPrecision || 2,
			useTime: cospend.useTime,
		}
	},
	computed: {
		sortedProjectIds() {
			if (this.sortOrder === 'name') {
				return Object.keys(this.projects).sort((a, b) => {
					return this.projects[a].name.toLowerCase() > this.projects[b].name.toLowerCase()
				})
			} else if (this.sortOrder === 'change') {
				return Object.keys(this.projects).sort((a, b) => {
					return this.projects[a].lastchanged < this.projects[b].lastchanged
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
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
		},
		onImportClick() {
			OC.dialogs.filepicker(
				t('cospend', 'Choose csv project file'),
				(targetPath) => {
					this.importProject(targetPath)
				},
				false,
				['text/csv'],
				true
			)
		},
		onImportSWClick() {
			OC.dialogs.filepicker(
				t('cospend', 'Choose SplitWise project file'),
				(targetPath) => {
					this.importProject(targetPath, true)
				},
				false,
				['text/csv'],
				true
			)
		},
		importProject(targetPath, isSplitWise = false) {
			network.importProject(targetPath, isSplitWise, this.importProjectSuccess)
		},
		importProjectSuccess(response) {
			this.$emit('project-imported', response)
			showSuccess(t('cospend', 'Project imported.'))
		},
		async onGuestLinkClick() {
			try {
				const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/login')
				await this.$copyText(guestLink)
				showSuccess(t('cospend', 'Guest link copied to clipboard.'))
			} catch (error) {
				console.debug(error)
				showError(t('cospend', 'Guest link could not be copied to clipboard.'))
			}
		},
		onOutputDirClick() {
			OC.dialogs.filepicker(
				t('maps', 'Choose where to write output files (stats, settlement, export)'),
				(targetPath) => {
					if (targetPath === '') {
						targetPath = '/'
					}
					this.outputDir = targetPath
					this.$emit('save-option', 'outputDirectory', targetPath)
				},
				false,
				'httpd/unix-directory',
				true
			)
		},
		onSortOrderChange() {
			this.$emit('save-option', 'sortOrder', this.sortOrder)
		},
		onMaxPrecisionChange() {
			this.$emit('save-option', 'maxPrecision', this.maxPrecision)
			cospend.maxPrecision = this.maxPrecision
		},
		onUseTimeChange(e) {
			this.$emit('save-option', 'useTime', e.target.checked ? '1' : '0')
			cospend.useTime = e.target.checked
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
	},
}
</script>
<style scoped lang="scss">
#app-settings-content {
	p {
		margin-top: 20px;
		margin-bottom: 20px;
		color: var(--color-text-light);
	}
}

.output-dir {
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.output-dir button {
	width: 59% !important;
}

.output-dir input {
	width: 39% !important;
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

#max-precision label,
#sort-order label {
	line-height: 38px;
	padding-left: 15px;
}

#max-precision,
#sort-order {
	display: grid;
	grid-template: 1fr / 1fr 1fr;
}

.loading-icon {
	margin-top: 16px;
}
</style>
