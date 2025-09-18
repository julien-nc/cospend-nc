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
			v-model:open="showSettings"
			class="cospend-settings-dialog"
			:name="t('cospend', 'Cospend settings')"
			:title="t('cospend', 'Cospend settings')"
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
				:name="t('cospend', 'Sort criterias')"
				:title="t('cospend', 'Sort criterias')"
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
					v-model="useTime"
					@update:model-value="onCheckboxChange($event, 'useTime')">
					{{ t('cospend', 'Use time in dates') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="showMyBalance"
					@update:model-value="onCheckboxChange($event, 'showMyBalance')">
					{{ t('cospend', 'Show my cumulated balance') }}
				</NcCheckboxRadioSwitch>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { getFilePickerBuilder, FilePickerType, showSuccess } from '@nextcloud/dialogs'
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
			cospend: OCA.Cospend.state,
			outputDir: OCA.Cospend.state.outputDirectory || '/',
			pageIsPublic: OCA.Cospend.state.pageIsPublic,
			sortOrder: OCA.Cospend.state.sortOrder || 'name',
			memberOrder: OCA.Cospend.state.memberOrder || 'name',
			maxPrecision: OCA.Cospend.state.maxPrecision || 2,
			useTime: OCA.Cospend.state.useTime ?? true,
			showMyBalance: OCA.Cospend.state.showMyBalance ?? false,
			importingProject: false,
			importingSWProject: false,
			cospendVersion: OC.getCapabilities()?.cospend?.version || '??',
		}
	},

	computed: {
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
		},

		onOutputDirClick() {
			const picker = getFilePickerBuilder(t('cospend', 'Choose where to write output files (stats, settlement, export)'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.outputDir)
				.addButton({
					label: t('cospend', 'Pick current directory'),
					variant: 'primary',
					callback: (nodes) => {
						const node = nodes[0]
						let path = node.path
						if (path === '') {
							path = '/'
						}
						path = path.replace(/^\/+/, '/')
						this.outputDir = path
						emit('save-option', { key: 'outputDirectory', value: path })
					},
				})
				.build()
			picker.pick()
			/*
			.then(async (path) => {
				console.debug('aaaaaaaaaaaaa', path)
				if (path === '') {
					path = '/'
				}
				path = path.replace(/^\/+/, '/')
				this.outputDir = path
				emit('save-option', { key: 'outputDirectory', value: path })
			})
			*/
		},
		onSortOrderChange() {
			emit('save-option', { key: 'sortOrder', value: this.sortOrder })
			this.cospend.sortOrder = this.sortOrder
		},
		onMemberOrderChange() {
			emit('save-option', { key: 'memberOrder', value: this.memberOrder })
			this.cospend.memberOrder = this.memberOrder
		},
		onMaxPrecisionChange() {
			emit('save-option', { key: 'maxPrecision', value: this.maxPrecision })
			this.cospend.maxPrecision = this.maxPrecision
			this.$emit('update-max-precision')
		},
		onCheckboxChange(checked, key) {
			emit('save-option', { key, value: checked ? '1' : '0' })
			this.cospend[key] = checked
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

:deep(.cospend-settings-dialog .modal-container) {
	display: flex !important;
}
</style>
