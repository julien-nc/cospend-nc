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
				<template #icon>
					<InformationOutlineIcon :size="20" />
				</template>
				<div class="infos">
					<h5 class="app-settings-section__hint">
						{{ t('cospend', 'Thanks for using Cospend') + ' ♥' }}
					</h5>
					<label class="app-settings-section__hint">
						{{ t('cospend', 'App version: {version}', { version: cospendVersion }) }}
					</label>
					<label class="app-settings-section__hint">
						{{ t('cospend', 'Bug/issue tracker') + ': ' }}
					</label>
					<a href="https://github.com/julien-nc/cospend-nc/issues"
						target="_blank"
						class="external">
						https://github.com/julien-nc/cospend-nc/issues
						<OpenInNewIcon :size="16" />
					</a>
					<label class="app-settings-section__hint">
						{{ t('cospend', 'Translation') + ': ' }}
					</label>
					<a href="https://crowdin.com/project/moneybuster"
						target="_blank"
						class="external">
						https://crowdin.com/project/moneybuster
						<OpenInNewIcon :size="16" />
					</a>
					<label class="app-settings-section__hint">
						{{ t('cospend', 'User documentation') + ': ' }}
					</label>
					<a href="https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md"
						target="_blank"
						class="external">
						https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md
						<OpenInNewIcon :size="16" />
					</a>
					<label class="app-settings-section__hint">
						{{ t('cospend', 'Admin documentation') + ': ' }}
					</label>
					<a href="https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md"
						target="_blank"
						class="external">
						https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md
						<OpenInNewIcon :size="16" />
					</a>
					<label class="app-settings-section__hint">
						{{ t('cospend', 'Developer documentation') + ': ' }}
					</label>
					<a href="https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md"
						target="_blank"
						class="external">
						https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md
						<OpenInNewIcon :size="16" />
					</a>
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!pageIsPublic"
				id="import"
				:name="t('cospend', 'Import projects')"
				:title="t('cospend', 'Import projects')"
				class="app-settings-section">
				<template #icon>
					<FileImportIcon :size="20" />
				</template>
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
			</NcAppSettingsSection>
			<NcAppSettingsSection v-if="!pageIsPublic"
				id="export"
				:name="t('cospend', 'Export location')"
				:title="t('cospend', 'Export location')"
				class="app-settings-section">
				<template #icon>
					<FolderOutlineIcon :size="20" />
				</template>
				<NcTextField
					:model-value="outputDir"
					:label="t('cospend', 'Export directory')"
					:show-trailing-button="!!outputDir"
					@trailing-button-click="resetOutputDir"
					@click="onOutputDirClick">
					<template #icon>
						<FolderOutlineIcon :size="20" />
					</template>
				</NcTextField>
				<NcButton @click="onOutputDirClick">
					<template #icon>
						<FileImportIcon :size="20" />
					</template>
					{{ t('cospend', 'Select export directory') }}
				</NcButton>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="sort"
				:name="t('cospend', 'Sort criterias')"
				:title="t('cospend', 'Sort criterias')"
				class="app-settings-section">
				<template #icon>
					<SortIcon :size="20" />
				</template>
				<div v-if="!pageIsPublic">
					<NcSelect
						:model-value="selectedSortOrder"
						:input-label="t('cospend', 'Projects order')"
						:options="Object.values(sortOrderOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="sortOrder = $event.value ?? 'name'; onSortOrderChange()" />
				</div>
				<NcSelect
					:model-value="selectedMemberOrder"
					:input-label="t('cospend', 'Members order')"
					:options="Object.values(memberOrderOptions)"
					:no-wrap="true"
					:clearable="false"
					@update:model-value="memberOrder = $event.value ?? 'name'; onMemberOrderChange()" />
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="misc"
				:name="t('cospend', 'Misc')"
				:title="t('cospend', 'Misc')"
				class="app-settings-section">
				<template #icon>
					<CogIcon :size="20" />
				</template>
				<NcInputField
					v-model.number="maxPrecision"
					type="number"
					min="2"
					max="10"
					step="1"
					:label="t('cospend', 'Maximum decimal precision to show in balances')"
					@update:model-value="onMaxPrecisionChange" />
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="useTime"
						@update:model-value="onCheckboxChange($event, 'useTime')">
						{{ t('cospend', 'Use time in dates') }}
					</NcFormBoxSwitch>
					<NcFormBoxSwitch
						v-model="showMyBalance"
						@update:model-value="onCheckboxChange($event, 'showMyBalance')">
						{{ t('cospend', 'Show my cumulated balance') }}
					</NcFormBoxSwitch>
				</NcFormBox>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import SortIcon from 'vue-material-design-icons/Sort.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'

import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { getFilePickerBuilder, FilePickerType, showSuccess } from '@nextcloud/dialogs'
import { importCospendProject, importSWProject } from '../utils.js'

export default {
	name: 'CospendSettingsDialog',

	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcTextField,
		NcLoadingIcon,
		NcSelect,
		NcInputField,
		NcFormBox,
		NcFormBoxSwitch,
		FileImportIcon,
		OpenInNewIcon,
		FolderOutlineIcon,
		InformationOutlineIcon,
		SortIcon,
		CogIcon,
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
			sortOrderOptions: {
				name: {
					value: 'name',
					label: t('cospend', 'Name'),
				},
				change: {
					value: 'change',
					label: t('cospend', 'Last activity'),
				},
			},
			memberOrderOptions: {
				name: {
					value: 'name',
					label: t('cospend', 'Name'),
				},
				balance: {
					value: 'balance',
					label: t('cospend', 'Balance'),
				},
			},
		}
	},

	computed: {
		selectedSortOrder() {
			return this.sortOrderOptions[this.sortOrder]
		},
		selectedMemberOrder() {
			return this.memberOrderOptions[this.memberOrder]
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
		resetOutputDir() {
			this.outputDir = '/'
			emit('save-option', { key: 'outputDirectory', value: '/' })
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

	.infos {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}
}

:deep(.cospend-settings-dialog .modal-container) {
	display: flex !important;
}
</style>
