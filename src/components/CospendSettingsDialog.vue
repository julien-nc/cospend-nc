<!--
  - @copyright Copyright (c) 2021 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author Julien Veyssier <julien-nc@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div id="settings-container">
		<NcAppSettingsDialog
			class="cospend-settings-dialog"
			:name="t('cospend', 'Cospend settings')"
			:title="t('cospend', 'Cospend settings')"
			:open.sync="showSettings"
			:show-navigation="true"
			container="#settings-container">
			<NcAppSettingsSection
				id="about"
				:name="t('cospend', 'About Cospend')"
				:title="t('cospend', 'About Cospend')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Thanks for using Cospend') + ' ♥' }}
				</h3>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'App version: {version}', { version: cospendVersion }) }}
				</h3>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Bug/issue tracker') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/cospend-nc/issues"
					target="_blank"
					class="external">
					https://github.com/julien-nc/cospend-nc/issues
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Translation') + ': ' }}
				</h3>
				<a href="https://crowdin.com/project/moneybuster"
					target="_blank"
					class="external">
					https://crowdin.com/project/moneybuster
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'User documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Admin documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Developer documentation') + ': ' }}
				</h3>
				<a href="https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md"
					target="_blank"
					class="external">
					https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md
					<OpenInNewIcon :size="16" />
				</a>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!pageIsPublic"
				id="import"
				:name="t('cospend', 'Import projects')"
				:title="t('cospend', 'Import projects')"
				class="app-settings-section">
				<div class="oneLine">
					<NcButton @click="onImportClick">
						<template #icon>
							<NcLoadingIcon v-if="importingProject" />
							<FileImportIcon v-else :size="20" />
						</template>
						{{ t('cospend', 'Import csv project') }}
					</NcButton>
					<NcButton @click="onImportSWClick">
						<template #icon>
							<NcLoadingIcon v-if="importingSWProject" />
							<FileImportIcon v-else :size="20" />
						</template>
						{{ t('cospend', 'Import SplitWise project') }}
					</NcButton>
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!pageIsPublic"
				id="export"
				:name="t('cospend', 'Export location')"
				:title="t('cospend', 'Export location')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Select export directory') }}
				</h3>
				<input
					type="text"
					class="app-settings-section__input"
					:value="outputDir"
					:disabled="false"
					:readonly="true"
					@click="onOutputDirClick">
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="sort"
				:name="t('cospend', 'Sort criteria')"
				:title="t('cospend', 'Sort criteria')"
				class="app-settings-section">
				<div v-if="!pageIsPublic">
					<h3 class="app-settings-section__hint">
						{{ t('cospend', 'How projects are sorted in navigation sidebar') }}
					</h3>
					<label for="sort-select">
						{{ t('cospend', 'Projects order') }}
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
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'How members are sorted') }}
				</h3>
				<label for="sort-member-select">
					{{ t('cospend', 'Members order') }}
				</label>
				<select id="sort-member-select" v-model="memberOrder" @change="onMemberOrderChange">
					<option value="name">
						{{ t('cospend', 'Name') }}
					</option>
					<option value="balance">
						{{ t('cospend', 'Balance') }}
					</option>
				</select>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="cumulated-balance"
				:name="t('cospend', 'Cumulated balances')"
				:title="t('cospend', 'Cumulated balances')"
				class="app-settings-section">
				<NcCheckboxRadioSwitch
					:checked.sync="showMyBalance"
					@update:checked="onCheckboxChange($event, 'showMyBalance')">
					{{ t('cospend', 'Show cumulated balances') }}
				</NcCheckboxRadioSwitch>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Cumulative balances view customization') }}
				</h3>
				<!-- Cross-project balance display order control -->
				<!-- Allows users to choose whether Summary or People section appears first -->
				<label for="display-order-select">
					{{ t('cospend', 'First Section: ') }}
				</label>
				<select id="display-order-select" v-model="displayOrder" @change="onDisplayOrderChange">
					<option value="summary">
						{{ t('cospend', 'Balance Summary') }}
					</option>
					<option value="people">
						{{ t('cospend', 'Balances by People') }}
					</option>
				</select>
				<br>
				<!-- Project details visibility control -->
				<!-- Replaces problematic checkbox with reliable dropdown for hiding/showing project breakdowns -->
				<label for="hide-projects-select">
					{{ t('cospend', 'Project details: ') }}
				</label>
				<select id="hide-projects-select" v-model="hideProjectsVisibility" @change="onHideProjectsChange">
					<option value="show">
						{{ t('cospend', 'Expand by default') }}
					</option>
					<option value="hide">
						{{ t('cospend', 'Collapse by default') }}
					</option>
				</select>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="misc"
				:name="t('cospend', 'Misc')"
				:title="t('cospend', 'Misc')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Maximum decimal precision to show in balances') }}
				</h3>
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
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Do you want to see and choose time in bill dates?') }}
				</h3>
				<NcCheckboxRadioSwitch
					:checked.sync="useTime"
					@update:checked="onCheckboxChange($event, 'useTime')">
					{{ t('cospend', 'Use time in dates') }}
				</NcCheckboxRadioSwitch>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { getFilePickerBuilder, FilePickerType, showSuccess } from '@nextcloud/dialogs'
import cospend from '../state.js'
import { importCospendProject, importSWProject } from '../utils.js'

export default {
	name: 'CospendSettingsDialog',

	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		FileImportIcon,
		OpenInNewIcon,
	},

	data() {
		return {
			showSettings: false,
			outputDir: cospend.outputDirectory || '/',
			pageIsPublic: cospend.pageIsPublic,
			sortOrder: cospend.sortOrder || 'name',
			memberOrder: cospend.memberOrder || 'name',
			maxPrecision: cospend.maxPrecision || 2,
			useTime: cospend.useTime ?? true,
			showMyBalance: cospend.showMyBalance ?? false,
			// Cross-project balance display settings:
			// Convert boolean showSummaryFirst to dropdown-friendly string value
			displayOrder: cospend.showSummaryFirst ? 'summary' : 'people',
			// Store the actual boolean value for direct cospend state updates
			hideProjectsByDefault: cospend.hideProjectsByDefault ?? true,
			// Convert boolean hideProjectsByDefault to dropdown-friendly string value
			// This allows for intuitive dropdown selection (show/hide) instead of boolean checkbox
			hideProjectsVisibility: (cospend.hideProjectsByDefault ?? true) ? 'hide' : 'show',
			importingProject: false,
			importingSWProject: false,
			cospendVersion: OC.getCapabilities()?.cospend?.version || '??',
		}
	},

	computed: {
	},

	watch: {
		// Sync dropdown values with global cospend state changes
		// This ensures UI stays in sync if settings are changed elsewhere
		'cospend.showSummaryFirst'(newValue) {
			this.displayOrder = newValue ? 'summary' : 'people'
		},
		'cospend.hideProjectsByDefault'(newValue) {
			this.hideProjectsVisibility = newValue ? 'hide' : 'show'
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	beforeDestroy() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings() {
			this.showSettings = true
			// Refresh values from cospend state when dialog opens
			this.displayOrder = cospend.showSummaryFirst ? 'summary' : 'people'
			this.hideProjectsVisibility = cospend.hideProjectsByDefault ? 'hide' : 'show'
		},

		onOutputDirClick() {
			const picker = getFilePickerBuilder(t('cospend', 'Choose where to write output files (stats, settlement, export)'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.outputDir)
				.build()
			picker.pick()
				.then(async (path) => {
					if (path === '') {
						path = '/'
					}
					path = path.replace(/^\/+/, '/')
					this.outputDir = path
					emit('save-option', { key: 'outputDirectory', value: path })
				})
		},
		onSortOrderChange() {
			emit('save-option', { key: 'sortOrder', value: this.sortOrder })
			cospend.sortOrder = this.sortOrder
		},
		onMemberOrderChange() {
			emit('save-option', { key: 'memberOrder', value: this.memberOrder })
			cospend.memberOrder = this.memberOrder
		},
		onMaxPrecisionChange() {
			emit('save-option', { key: 'maxPrecision', value: this.maxPrecision })
			cospend.maxPrecision = this.maxPrecision
			this.$emit('update-max-precision')
		},
		onCheckboxChange(checked, key) {
			emit('save-option', { key, value: checked ? '1' : '0' })
			cospend[key] = checked
		},
		onDisplayOrderChange() {
			// Convert dropdown selection to boolean for cospend state
			// 'summary' = true (show summary first), 'people' = false (show people first)
			const showSummaryFirst = this.displayOrder === 'summary'
			emit('save-option', { key: 'showSummaryFirst', value: showSummaryFirst ? '1' : '0' })
			cospend.showSummaryFirst = showSummaryFirst
		},
		onHideProjectsChange() {
			// Convert dropdown selection to boolean for project details visibility
			// 'hide' = true (hide by default), 'show' = false (show by default)
			const hideProjectsByDefault = this.hideProjectsVisibility === 'hide'
			// Update local data property to keep in sync
			this.hideProjectsByDefault = hideProjectsByDefault
			// Persist setting to server (as string '1'/'0' for database storage)
			emit('save-option', { key: 'hideProjectsByDefault', value: hideProjectsByDefault ? '1' : '0' })
			// Update global cospend state immediately for reactive UI updates
			cospend.hideProjectsByDefault = hideProjectsByDefault
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
				this.importingSWProject = true
			}, (data) => {
				emit('project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingSWProject = false
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.success {
	color: var(--color-success);
}

.wrapper {
	overflow-y: scroll;
	padding: 20px;
}

button {
	display: inline-flex;
	align-items: center;
	.label {
		padding-left: 8px;
	}
}

a.external {
	display: flex;
	align-items: center;
	> * {
		margin: 0 2px 0 2px;
	}
}

.app-settings-section {
	margin-bottom: 80px;
	&.last {
		margin-bottom: 0;
	}
	&__title {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	&__hint {
		color: var(--color-text-lighter);
		padding: 8px 0;
	}
	&__input {
		width: 100%;
	}

	.shortcut-description {
		width: calc(100% - 160px);
	}

	.oneLine {
		display: flex;
		align-items: center;
		> * {
			margin: 0 4px 0 4px;
		}
	}
}

::v-deep .cospend-settings-dialog .modal-container {
	display: flex !important;
}
</style>
