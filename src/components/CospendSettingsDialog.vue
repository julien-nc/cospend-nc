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
			:show-navigation="true"
			:no-version="true"
			container="#settings-container">
			<NcAppSettingsSection v-if="!pageIsPublic"
				id="import"
				:name="t('cospend', 'Import projects')">
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
				:name="t('cospend', 'Export location')">
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
				:name="t('cospend', 'Sort criterias')">
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
				id="cumulative-balance"
				:name="t('cospend', 'Cumulative balances')">
				<template #icon>
					<ReimburseIcon :size="20" />
				</template>
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="showMyBalance"
						@update:model-value="onCheckboxChange($event, 'showMyBalance')">
						{{ t('cospend', 'Show cumulative balances') }}
					</NcFormBoxSwitch>
				</NcFormBox>
				<div class="infos">
					<label>{{ t('cospend', 'Cumulative balances view customisation') }}</label>
				</div>
				<div class="cumulative-settings-fields">
					<NcSelect
						:model-value="selectedDisplayOrder"
						:input-label="t('cospend', 'First section')"
						:options="Object.values(displayOrderOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="displayOrder = $event.value ?? 'summary'; onDisplayOrderChange()" />
					<NcSelect
						:model-value="selectedHideProjectsVisibility"
						:input-label="t('cospend', 'Project details')"
						:options="Object.values(hideProjectsVisibilityOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="hideProjectsVisibility = $event.value ?? 'hide'; onHideProjectsChange()" />
					<div class="infos">
						<label>{{ t('cospend', 'Sort options') }}</label>
					</div>
					<NcSelect
						:model-value="selectedPersonSortBy"
						:input-label="t('cospend', 'Sort balances by people by')"
						:options="Object.values(personSortByOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="personSortBy = $event.value ?? 'balance'; onPersonSortByChange()" />
					<NcSelect
						:model-value="selectedPersonSortOrder"
						:input-label="t('cospend', 'Sort order')"
						:options="Object.values(balanceSortOrderOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="personSortOrder = $event.value ?? 'desc'; onPersonSortOrderChange()" />
					<NcSelect
						:model-value="selectedSummarySortBy"
						:input-label="t('cospend', 'Sort summary by')"
						:options="Object.values(summarySortByOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="summarySortBy = $event.value ?? 'amount'; onSummarySortByChange()" />
					<NcSelect
						:model-value="selectedSummarySortOrder"
						:input-label="t('cospend', 'Sort order')"
						:options="Object.values(balanceSortOrderOptions)"
						:no-wrap="true"
						:clearable="false"
						@update:model-value="summarySortOrder = $event.value ?? 'desc'; onSummarySortOrderChange()" />
				</div>
			</NcAppSettingsSection>
			<NcAppSettingsSection
				id="misc"
				:name="t('cospend', 'Misc')">
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
			<NcAppSettingsSection
				id="about"
				:name="t('cospend', 'About Cospend')">
				<template #icon>
					<InformationOutlineIcon :size="20" />
				</template>
				<div class="about">
					<label>
						{{ '♥ ' + t('cospend', 'Thanks for using Cospend') + ' ♥ (v' + cospendVersion + ')' }}
					</label>
					<NcFormBox>
						<NcFormBoxButton
							:label="t('cospend', 'Bug/issue tracker')"
							description="https://github.com/julien-nc/cospend-nc/issues"
							href="https://github.com/julien-nc/cospend-nc/issues"
							target="_blank" />
						<NcFormBoxButton
							:label="t('cospend', 'Translation')"
							description="https://crowdin.com/project/moneybuster"
							href="https://crowdin.com/project/moneybuster"
							target="_blank" />
						<NcFormBoxButton
							:label="t('cospend', 'User documentation')"
							description="https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md"
							href="https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md"
							target="_blank" />
						<NcFormBoxButton
							:label="t('cospend', 'Admin documentation')"
							description="https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md"
							href="https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md"
							target="_blank" />
						<NcFormBoxButton
							:label="t('cospend', 'Developer documentation')"
							description="https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md"
							href="https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md"
							target="_blank" />
					</NcFormBox>
				</div>
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script lang="ts">
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import SortIcon from 'vue-material-design-icons/Sort.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import ReimburseIcon from './icons/ReimburseIcon.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'

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
		NcFormBoxButton,
		FileImportIcon,
		FolderOutlineIcon,
		InformationOutlineIcon,
		ReimburseIcon,
		SortIcon,
		CogIcon,
	},

	data() {
		return {
			showSettings: false,
			cospend: OCA.Cospend.state,
			outputDir: OCA.Cospend.state.outputDirectory as string || '/',
			pageIsPublic: OCA.Cospend.state.pageIsPublic,
			sortOrder: OCA.Cospend.state.sortOrder || 'name',
			memberOrder: OCA.Cospend.state.memberOrder || 'name',
			maxPrecision: OCA.Cospend.state.maxPrecision as number || 2,
			useTime: OCA.Cospend.state.useTime as boolean ?? true,
			showMyBalance: OCA.Cospend.state.showMyBalance as boolean ?? false,
			displayOrder: OCA.Cospend.state.showSummaryFirst ? 'summary' : 'people',
			hideProjectsVisibility: (OCA.Cospend.state.hideProjectsByDefault ?? true) ? 'hide' : 'show',
			personSortBy: OCA.Cospend.state.personSortBy || 'balance',
			personSortOrder: OCA.Cospend.state.personSortOrder || 'desc',
			summarySortBy: OCA.Cospend.state.summarySortBy || 'amount',
			summarySortOrder: OCA.Cospend.state.summarySortOrder || 'desc',
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
			displayOrderOptions: {
				summary: {
					value: 'summary',
					label: t('cospend', 'Balance summary'),
				},
				people: {
					value: 'people',
					label: t('cospend', 'Balances by people'),
				},
			},
			hideProjectsVisibilityOptions: {
				hide: {
					value: 'hide',
					label: t('cospend', 'Collapse by default'),
				},
				show: {
					value: 'show',
					label: t('cospend', 'Expand by default'),
				},
			},
			personSortByOptions: {
				balance: {
					value: 'balance',
					label: t('cospend', 'Balance amount'),
				},
				name: {
					value: 'name',
					label: t('cospend', 'Name'),
				},
				currency: {
					value: 'currency',
					label: t('cospend', 'Currency'),
				},
			},
			summarySortByOptions: {
				amount: {
					value: 'amount',
					label: t('cospend', 'Amount'),
				},
				currency: {
					value: 'currency',
					label: t('cospend', 'Currency'),
				},
			},
			balanceSortOrderOptions: {
				desc: {
					value: 'desc',
					label: t('cospend', 'Descending'),
				},
				asc: {
					value: 'asc',
					label: t('cospend', 'Ascending'),
				},
			},
		}
	},

	computed: {
		selectedSortOrder(): Object {
			return this.sortOrderOptions[this.sortOrder as keyof typeof this.sortOrderOptions] ?? this.sortOrderOptions.name
		},
		selectedMemberOrder(): Object {
			return this.memberOrderOptions[this.memberOrder as keyof typeof this.memberOrderOptions] ?? this.memberOrderOptions.name
		},
		selectedDisplayOrder(): Object {
			return this.displayOrderOptions[this.displayOrder as keyof typeof this.displayOrderOptions] ?? this.displayOrderOptions.summary
		},
		selectedHideProjectsVisibility(): Object {
			return this.hideProjectsVisibilityOptions[this.hideProjectsVisibility as keyof typeof this.hideProjectsVisibilityOptions] ?? this.hideProjectsVisibilityOptions.hide
		},
		selectedPersonSortBy(): Object {
			return this.personSortByOptions[this.personSortBy as keyof typeof this.personSortByOptions] ?? this.personSortByOptions.balance
		},
		selectedPersonSortOrder(): Object {
			return this.balanceSortOrderOptions[this.personSortOrder as keyof typeof this.balanceSortOrderOptions] ?? this.balanceSortOrderOptions.desc
		},
		selectedSummarySortBy(): Object {
			return this.summarySortByOptions[this.summarySortBy as keyof typeof this.summarySortByOptions] ?? this.summarySortByOptions.amount
		},
		selectedSummarySortOrder(): Object {
			return this.balanceSortOrderOptions[this.summarySortOrder as keyof typeof this.balanceSortOrderOptions] ?? this.balanceSortOrderOptions.desc
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
	},

	beforeDestroy() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings(): void {
			this.showSettings = true
			this.showMyBalance = this.cospend.showMyBalance as boolean ?? false
			this.displayOrder = this.cospend.showSummaryFirst ? 'summary' : 'people'
			this.hideProjectsVisibility = (this.cospend.hideProjectsByDefault ?? true) ? 'hide' : 'show'
			this.personSortBy = this.cospend.personSortBy || 'balance'
			this.personSortOrder = this.cospend.personSortOrder || 'desc'
			this.summarySortBy = this.cospend.summarySortBy || 'amount'
			this.summarySortOrder = this.cospend.summarySortOrder || 'desc'
		},

		onOutputDirClick(): void {
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
		resetOutputDir(): void {
			this.outputDir = '/'
			emit('save-option', { key: 'outputDirectory', value: '/' })
		},
		onSortOrderChange(): void {
			emit('save-option', { key: 'sortOrder', value: this.sortOrder })
			this.cospend.sortOrder = this.sortOrder
		},
		onMemberOrderChange(): void {
			emit('save-option', { key: 'memberOrder', value: this.memberOrder })
			this.cospend.memberOrder = this.memberOrder
		},
		onMaxPrecisionChange(): void {
			emit('save-option', { key: 'maxPrecision', value: this.maxPrecision })
			this.cospend.maxPrecision = this.maxPrecision
			this.$emit('update-max-precision')
		},
		onCheckboxChange(checked: boolean, key: string): void {
			emit('save-option', { key, value: checked ? '1' : '0' })
			this.cospend[key] = checked
		},
		onDisplayOrderChange(): void {
			const showSummaryFirst = this.displayOrder === 'summary'
			emit('save-option', { key: 'showSummaryFirst', value: showSummaryFirst ? '1' : '0' })
			this.cospend.showSummaryFirst = showSummaryFirst
		},
		onHideProjectsChange(): void {
			const hideProjectsByDefault = this.hideProjectsVisibility === 'hide'
			emit('save-option', { key: 'hideProjectsByDefault', value: hideProjectsByDefault ? '1' : '0' })
			this.cospend.hideProjectsByDefault = hideProjectsByDefault
		},
		onPersonSortByChange(): void {
			if (this.personSortBy === 'balance') {
				this.personSortOrder = 'desc'
			} else {
				this.personSortOrder = 'asc'
			}
			this.onPersonSortOrderChange()
		},
		onPersonSortOrderChange(): void {
			emit('save-option', { key: 'personSortBy', value: this.personSortBy })
			emit('save-option', { key: 'personSortOrder', value: this.personSortOrder })
			this.cospend.personSortBy = this.personSortBy
			this.cospend.personSortOrder = this.personSortOrder
		},
		onSummarySortByChange(): void {
			if (this.summarySortBy === 'amount') {
				this.summarySortOrder = 'desc'
			} else {
				this.summarySortOrder = 'asc'
			}
			this.onSummarySortOrderChange()
		},
		onSummarySortOrderChange(): void {
			emit('save-option', { key: 'summarySortBy', value: this.summarySortBy })
			emit('save-option', { key: 'summarySortOrder', value: this.summarySortOrder })
			this.cospend.summarySortBy = this.summarySortBy
			this.cospend.summarySortOrder = this.summarySortOrder
		},
		onImportClick(): void {
			importCospendProject(() => {
				this.importingProject = true
			}, (data: any) => {
				emit('project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingProject = false
			})
		},
		onImportSWClick(): void {
			importSWProject(() => {
				this.importingSWProject = true
			}, (data: any) => {
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
	.about {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.cumulative-settings-fields {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
}

:deep(.cospend-settings-dialog .modal-container) {
	display: flex !important;
}
</style>
