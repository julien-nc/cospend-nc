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
	<AppSettingsDialog
		:open.sync="showSettings"
		:show-navigation="true"
		container="#content-vue">
		<AppSettingsSection
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
				<span class="icon icon-external" />
			</a>
			<h3 class="app-settings-section__hint">
				{{ t('cospend', 'Translation') + ': ' }}
			</h3>
			<a href="https://crowdin.com/project/moneybuster"
				target="_blank"
				class="external">
				https://crowdin.com/project/moneybuster
				<span class="icon icon-external" />
			</a>
			<h3 class="app-settings-section__hint">
				{{ t('cospend', 'User documentation') + ': ' }}
			</h3>
			<a href="https://github.com/eneiluj/cospend-nc/blob/master/docs/user.md"
				target="_blank"
				class="external">
				https://github.com/eneiluj/cospend-nc/blob/master/docs/user.md
				<span class="icon icon-external" />
			</a>
			<h3 class="app-settings-section__hint">
				{{ t('cospend', 'Admin documentation') + ': ' }}
			</h3>
			<a href="https://github.com/eneiluj/cospend-nc/blob/master/docs/admin.md"
				target="_blank"
				class="external">
				https://github.com/eneiluj/cospend-nc/blob/master/docs/admin.md
				<span class="icon icon-external" />
			</a>
			<h3 class="app-settings-section__hint">
				{{ t('cospend', 'Developer documentation') + ': ' }}
			</h3>
			<a href="https://github.com/eneiluj/cospend-nc/blob/master/docs/dev.md"
				target="_blank"
				class="external">
				https://github.com/eneiluj/cospend-nc/blob/master/docs/dev.md
				<span class="icon icon-external" />
			</a>
		</AppSettingsSection>
		<AppSettingsSection v-if="!pageIsPublic"
			:title="t('cospend', 'Import projects')"
			class="app-settings-section">
			<button @click="onImportClick">
				<span :class="{ icon: true, 'icon-download': !importingProject, 'icon-loading-small': importingProject }" />
				{{ t('cospend', 'Import csv project') }}
			</button>
			<button @click="onImportSWClick">
				<span :class="{ icon: true, 'icon-download': !importingSWProject, 'icon-loading-small': importingSWProject }" />
				{{ t('cospend', 'Import SplitWise project') }}
			</button>
		</AppSettingsSection>
		<AppSettingsSection v-if="!pageIsPublic"
			:title="t('cospend', 'Guest access')"
			class="app-settings-section">
			<a :href="guestLink" @click.prevent.stop="onGuestLinkClick">
				<button>
					<span class="icon icon-clippy" />
					{{ t('cospend', 'Copy guest access link') }}
				</button>
			</a>
		</AppSettingsSection>
		<AppSettingsSection v-if="!pageIsPublic"
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
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import cospend from '../state'
import * as network from '../network'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CospendSettingsDialog',

	components: {
		AppSettingsDialog,
		AppSettingsSection,
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
			if (isSplitWise) {
				this.importingSWProject = true
			} else {
				this.importingProject = true
			}
			network.importProject(targetPath, isSplitWise).then((response) => {
				this.$emit('project-imported', response.data)
				showSuccess(t('cospend', 'Project imported'))
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to import project file')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			}).then(() => {
				if (isSplitWise) {
					this.importingSWProject = false
				} else {
					this.importingProject = false
				}
			})
		},
		async onGuestLinkClick() {
			try {
				await this.$copyText(this.guestLink)
				showSuccess(t('cospend', 'Guest link copied to clipboard.'))
			} catch (error) {
				console.debug(error)
				showError(t('cospend', 'Guest link could not be copied to clipboard.'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
a span.icon {
	display: inline-block;
	margin-bottom: -3px;
}

.wrapper {
	overflow-y: scroll;
	padding: 20px;
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
}

</style>
