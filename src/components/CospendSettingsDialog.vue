<!--
  - @copyright Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author Julien Veyssier <eneiluj@posteo.net>
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
		<AppSettingsDialog
			class="cospend-settings-dialog"
			:open.sync="showSettings"
			:show-navigation="true"
			container="#settings-container">
			<AppSettingsSection
				id="about"
				:title="t('cospend', 'About Cospend')"
				class="app-settings-section">
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Thanks for using Cospend') + ' ♥' }}
				</h3>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Bug/issue tracker') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/cospend-nc/issues"
					target="_blank"
					class="external">
					https://github.com/eneiluj/cospend-nc/issues
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
				<a href="https://github.com/eneiluj/cospend-nc/blob/master/docs/user.md"
					target="_blank"
					class="external">
					https://github.com/eneiluj/cospend-nc/blob/master/docs/user.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Admin documentation') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/cospend-nc/blob/master/docs/admin.md"
					target="_blank"
					class="external">
					https://github.com/eneiluj/cospend-nc/blob/master/docs/admin.md
					<OpenInNewIcon :size="16" />
				</a>
				<h3 class="app-settings-section__hint">
					{{ t('cospend', 'Developer documentation') + ': ' }}
				</h3>
				<a href="https://github.com/eneiluj/cospend-nc/blob/master/docs/dev.md"
					target="_blank"
					class="external">
					https://github.com/eneiluj/cospend-nc/blob/master/docs/dev.md
					<OpenInNewIcon :size="16" />
				</a>
			</AppSettingsSection>
			<AppSettingsSection v-if="!pageIsPublic"
				id="import"
				:title="t('cospend', 'Import projects')"
				class="app-settings-section">
				<div class="oneLine">
					<NcButton @click="onImportClick">
						<template #icon>
							<FileImportIcon
								:class="{ 'icon-loading': importingProject }"
								:size="20" />
						</template>
						{{ t('cospend', 'Import csv project') }}
					</NcButton>
					<NcButton @click="onImportSWClick">
						<template #icon>
							<FileImportIcon
								:class="{ 'icon-loading': importingSWProject }"
								:size="20" />
						</template>
						{{ t('cospend', 'Import SplitWise project') }}
					</NcButton>
				</div>
			</AppSettingsSection>
			<AppSettingsSection v-if="!pageIsPublic"
				id="guest-access"
				:title="t('cospend', 'Guest access')"
				class="app-settings-section">
				<a :href="guestLink" @click.prevent.stop="onGuestLinkClick">
					<NcButton>
						<template #icon>
							<ClipboardCheckOutlineIcon v-if="guestLinkCopied"
								class="success"
								:size="20" />
							<ClippyIcon v-else
								:size="16" />
						</template>
						{{ t('cospend', 'Copy guest access link') }}
					</NcButton>
				</a>
			</AppSettingsSection>
			<AppSettingsSection v-if="!pageIsPublic"
				id="export"
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
			</AppSettingsSection>
			<AppSettingsSection
				id="sort"
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
			</AppSettingsSection>
			<AppSettingsSection
				id="misc"
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
				<input id="use-time-cb"
					v-model="useTime"
					class="checkbox"
					type="checkbox"
					@input="onUseTimeChange">
				<label for="use-time-cb">
					{{ t('cospend', 'Use time in dates') }}
				</label>
			</AppSettingsSection>
		</AppSettingsDialog>
	</div>
</template>

<script>
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import FileImportIcon from 'vue-material-design-icons/FileImport.vue'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog.js'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection.js'
import cospend from '../state.js'
import { generateUrl } from '@nextcloud/router'
import { importCospendProject, importSWProject, Timer } from '../utils.js'
import ClippyIcon from './icons/ClippyIcon.vue'

export default {
	name: 'CospendSettingsDialog',

	components: {
		ClippyIcon,
		AppSettingsDialog,
		AppSettingsSection,
		NcButton,
		FileImportIcon,
		ClipboardCheckOutlineIcon,
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
			guestLink: window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/login'),
			importingProject: false,
			importingSWProject: false,
			guestLinkCopied: false,
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
				.setModal(true)
				.setType(1)
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
					this.$emit('save-option', 'outputDirectory', path)
				})
		},
		onSortOrderChange() {
			this.$emit('save-option', 'sortOrder', this.sortOrder)
			cospend.sortOrder = this.sortOrder
		},
		onMemberOrderChange() {
			this.$emit('save-option', 'memberOrder', this.memberOrder)
			cospend.memberOrder = this.memberOrder
		},
		onMaxPrecisionChange() {
			this.$emit('save-option', 'maxPrecision', this.maxPrecision)
			cospend.maxPrecision = this.maxPrecision
			this.$emit('update-max-precision')
		},
		onUseTimeChange(e) {
			this.$emit('save-option', 'useTime', e.target.checked ? '1' : '0')
			cospend.useTime = e.target.checked
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
				this.importingSWProject = true
			}, (data) => {
				this.$emit('project-imported', data)
				showSuccess(t('cospend', 'Project imported'))
			}, () => {
				this.importingSWProject = false
			})
		},
		async onGuestLinkClick() {
			try {
				await this.$copyText(this.guestLink)
				showSuccess(t('cospend', 'Guest link copied to clipboard.'))
				this.guestLinkCopied = true
				// eslint-disable-next-line
				new Timer(() => {
					this.guestLinkCopied = false
				}, 5000)
			} catch (error) {
				console.debug(error)
				showError(t('cospend', 'Guest link could not be copied to clipboard.'))
			}
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
