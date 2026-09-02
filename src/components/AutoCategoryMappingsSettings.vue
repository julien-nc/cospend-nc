<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="auto-category-mappings-settings">
		<h3>
			<ShapeIcon class="icon" :size="20" />
			<span class="tcontent">
				{{ t('cospend', 'Auto-category mappings') }}
			</span>
			<NcButton
				:title="t('cospend', 'How it works')"
				:aria-label="t('cospend', 'How it works')"
				@click="showHelp = !showHelp">
				<template #icon>
					<InformationOutlineIcon :size="16" />
				</template>
			</NcButton>
			<NcActions v-if="maintainerAccess">
				<NcActionButton :close-after-click="true" @click="openProjectDialog('copy-all')">
					<template #icon>
						<ContentCopyIcon :size="16" />
					</template>
					{{ t('cospend', 'Copy all mappings to another project') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="openProjectDialog('import')">
					<template #icon>
						<ImportIcon :size="16" />
					</template>
					{{ t('cospend', 'Import mappings from another project') }}
				</NcActionButton>
			</NcActions>
		</h3>
		<div v-if="showHelp" class="help-card">
			<p>{{ t('cospend', 'When you create or edit a bill, the title is checked against these mappings. If a match is found, the category is assigned automatically.') }}</p>
		</div>
		<div v-if="maintainerAccess" class="add-mapping">
			<NcTextField
				v-model="newMappingTitle"
				class="title-input"
				:aria-label="t('cospend', 'Bill title')"
				:placeholder="t('cospend', 'Bill title')"
				:disabled="adding"
				@keyup.enter="addMapping" />
			<NcSelect
				v-model="newMappingCategory"
				class="category-select"
				:placeholder="t('cospend', 'Category')"
				:aria-label-combobox="t('cospend', 'Category')"
				:options="categoryOptions"
				label="name"
				:no-wrap="true"
				:clearable="false"
				:disabled="adding" />
			<NcButton
				:title="t('cospend', 'Add mapping')"
				:aria-label="t('cospend', 'Add mapping')"
				:disabled="!canAddMapping || adding"
				@click="addMapping">
				<template #icon>
					<NcLoadingIcon v-if="adding" />
					<PlusIcon v-else :size="20" />
				</template>
			</NcButton>
		</div>
		<div v-if="duplicateWarning" class="duplicate-warning">
			{{ t('cospend', 'A mapping with this title already exists') }}
		</div>
		<div v-if="loadingMappings" class="loading-mappings">
			<NcLoadingIcon :size="20" />
		</div>
		<div v-else-if="mappings.length === 0" class="no-mappings">
			{{ t('cospend', 'No mappings yet') }}
			<NcButton v-if="maintainerAccess" class="focus-add-btn" @click="focusAddMapping">
				<template #icon>
					<PlusIcon :size="16" />
				</template>
				{{ t('cospend', 'Add mapping') }}
			</NcButton>
		</div>
		<div v-if="mappings.length >= searchThreshold" class="sort-bar">
			<NcTextField
				v-model="searchQuery"
				:aria-label="t('cospend', 'Search')"
				:placeholder="t('cospend', 'Search')"
				class="search-input" />
			<NcSelect
				v-model="sortMode"
				:title="t('cospend', 'Sort')"
				:aria-label-combobox="t('cospend', 'Sort')"
				:options="sortOptions"
				label="label"
				:clearable="false"
				:no-wrap="true"
				class="sort-select" />
		</div>
		<div v-for="mapping in paginatedMappings"
			:key="mapping.id"
			class="mapping-item"
			:class="{ editing: editingId === mapping.id }">
			<div v-if="mapping.category_id === null" class="mapping-invalid-badge" :title="t('cospend', 'Category was deleted')">
				<AlertIcon :size="16" />
			</div>
			<template v-if="editingId !== mapping.id">
				<span
					class="mapping-title"
					:class="{ 'mapping-title-invalid': mapping.category_id === null }"
					:title="titleDisplay(mapping.bill_title)">{{ titleDisplay(mapping.bill_title) }}</span>
				<span class="mapping-arrow">→</span>
				<span class="mapping-category" :class="{ 'mapping-category-invalid': mapping.category_id === null }">{{ categoryDisplay(mapping.category_id) }}</span>
				<NcActions v-if="maintainerAccess" class="mapping-actions">
					<NcActionButton :close-after-click="true" @click="startEdit(mapping)">
						<template #icon>
							<PencilIcon :size="16" />
						</template>
						{{ t('cospend', 'Edit') }}
					</NcActionButton>
					<NcActionButton :close-after-click="true" @click="openProjectDialog('copy-single', mapping)">
						<template #icon>
							<ContentCopyIcon :size="16" />
						</template>
						{{ t('cospend', 'Copy to project…') }}
					</NcActionButton>
					<NcActionButton :close-after-click="true" @click="confirmDelete(mapping.id)">
						<template #icon>
							<DeleteIcon :size="16" />
						</template>
						{{ t('cospend', 'Delete') }}
					</NcActionButton>
				</NcActions>
			</template>
			<template v-else>
				<NcTextField
					v-model="editTitle"
					class="title-input"
					:aria-label="t('cospend', 'Title')"
					:placeholder="t('cospend', 'Title')"
					:disabled="saving"
					@keyup.enter="saveEdit(mapping)"
					@keyup.escape="cancelEdit" />
				<NcSelect
					v-model="editCategory"
					class="category-select"
					:placeholder="t('cospend', 'Category')"
					:aria-label-combobox="t('cospend', 'Category')"
					:options="categoryOptions"
					label="name"
					:no-wrap="true"
					:clearable="false"
					:disabled="saving" />
				<div class="edit-buttons">
					<NcButton
						:title="t('cospend', 'Save')"
						:aria-label="t('cospend', 'Save')"
						:disabled="saving"
						@click="saveEdit(mapping)">
						<template #icon>
							<NcLoadingIcon v-if="saving" />
							<CheckIcon v-else :size="16" />
						</template>
					</NcButton>
					<NcButton
						:title="t('cospend', 'Cancel')"
						:aria-label="t('cospend', 'Cancel')"
						:disabled="saving"
						@click="cancelEdit">
						<template #icon>
							<CloseIcon :size="16" />
						</template>
					</NcButton>
				</div>
			</template>
		</div>
		<div v-if="filteredMappings.length > minPageSize" class="mappings-footer">
			<span class="mapping-count">{{ filteredMappings.length }} {{ t('cospend', 'mapping(s)') }}</span>
			<div class="pagination-bar">
				<NcSelect
					v-model="pageSize"
					:title="t('cospend', 'Mappings per page')"
					:aria-label-combobox="t('cospend', 'Mappings per page')"
					:options="pageSizeOptions"
					label="label"
					:clearable="false"
					:no-wrap="true"
					class="page-size-select" />
				<span class="page-info">{{ currentPage }} / {{ totalPages }}</span>
				<NcButton
					:disabled="currentPage <= 1"
					:aria-label="t('cospend', 'Previous page')"
					@click="prevPage">
					<template #icon>
						<ChevronLeftIcon :size="16" />
					</template>
				</NcButton>
				<NcButton
					:disabled="currentPage >= totalPages"
					:aria-label="t('cospend', 'Next page')"
					@click="nextPage">
					<template #icon>
						<ChevronRightIcon :size="16" />
					</template>
				</NcButton>
			</div>
		</div>
		<div v-if="maintainerAccess && mappings.length > 0" class="auto-categorize-actions">
			<NcButton
				:disabled="autoCategorizing"
				@click="autoCategorizeNow">
				<template #icon>
					<NcLoadingIcon v-if="autoCategorizing" />
					<AutoFixIcon v-else :size="20" />
				</template>
				{{ t('cospend', 'Apply to existing bills') }}
			</NcButton>
		</div>
		<NcDialog v-model:open="showDeleteDialog"
			:name="t('cospend', 'Delete mapping')"
			:message="t('cospend', 'Are you sure you want to delete this mapping?')">
			<NcCheckboxRadioSwitch v-model="skipDeleteConfirm">
				{{ t('cospend', "Don't ask me again") }}
			</NcCheckboxRadioSwitch>
			<template #actions>
				<NcButton @click="showDeleteDialog = false">
					{{ t('cospend', 'Cancel') }}
				</NcButton>
				<NcButton variant="warning" @click="doDeleteMapping(deleteTargetId)">
					<template #icon>
						<DeleteIcon :size="16" />
					</template>
					{{ t('cospend', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-model:open="projectDialogOpen"
			:name="projectDialogTitle">
			<div class="project-dialog-content">
				<p class="project-dialog-description">
					{{ projectDialogDescription }}
				</p>
				<NcSelect
					v-model="projectDialogTarget"
					class="project-select"
					:placeholder="projectDialogSelectLabel"
					:aria-label-combobox="projectDialogSelectLabel"
					:options="otherProjects"
					label="name"
					:no-wrap="true"
					:clearable="false"
					:disabled="projectDialogLoading" />
				<div v-if="projectDialogResult" class="copy-result">
					{{ projectDialogResult }}
					<div v-if="projectDialogErrors.length > 0" class="copy-errors">
						<div v-for="(err, i) in projectDialogErrors" :key="i" class="copy-error-item">
							{{ errorDisplay(err) }}
						</div>
					</div>
				</div>
			</div>
			<template #actions>
				<NcButton :disabled="projectDialogLoading" @click="projectDialogOpen = false">
					{{ projectDialogResult ? t('cospend', 'Close') : t('cospend', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="!projectDialogTarget || projectDialogLoading"
					@click="doProjectDialogAction">
					<template #icon>
						<NcLoadingIcon v-if="projectDialogLoading" />
						<ImportIcon v-else-if="projectDialogMode === 'import'" :size="16" />
						<ContentCopyIcon v-else :size="16" />
					</template>
					{{ projectDialogActionLabel }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import ShapeIcon from 'vue-material-design-icons/Shape.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AutoFixIcon from 'vue-material-design-icons/AutoFix.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ImportIcon from 'vue-material-design-icons/Import.vue'
import AlertIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { getCategory, getErrorMessage, decodeHtmlEntities } from '../utils.js'
import * as network from '../network.js'
import * as constants from '../constants.js'

export default {
	name: 'AutoCategoryMappingsSettings',

	components: {
		ShapeIcon,
		PlusIcon,
		PencilIcon,
		DeleteIcon,
		CheckIcon,
		CloseIcon,
		AutoFixIcon,
		ContentCopyIcon,
		ImportIcon,
		AlertIcon,
		ChevronLeftIcon,
		ChevronRightIcon,
		InformationOutlineIcon,
		NcActions,
		NcActionButton,
		NcButton,
		NcTextField,
		NcSelect,
		NcLoadingIcon,
		NcDialog,
		NcCheckboxRadioSwitch,
	},

	props: {
		project: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			cospend: OCA.Cospend.state,
			mappings: [],
			newMappingTitle: '',
			newMappingCategory: null,
			editingId: null,
			editTitle: '',
			editCategory: null,
			loadingMappings: false,
			adding: false,
			saving: false,
			autoCategorizing: false,
			// search/sort and pagination only appear for larger lists
			searchThreshold: 8,
			minPageSize: 5,
			projectDialogOpen: false,
			projectDialogMode: null,
			projectDialogMapping: null,
			projectDialogTarget: null,
			projectDialogLoading: false,
			projectDialogResult: '',
			projectDialogErrors: [],
			sortMode: { id: 'alpha-asc', label: 'A→Z' },
			searchQuery: '',
			showDeleteDialog: false,
			deleteTargetId: null,
			skipDeleteConfirm: false,
			pageSize: { id: 10, label: '10' },
			currentPage: 1,
			showHelp: false,
		}
	},

	computed: {
		projectId() {
			return this.project.id
		},
		maintainerAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.MAINTENER
		},
		categoryOptions() {
			const items = [{
				name: t('cospend', 'None'),
				id: 0,
			}]
			const allCategories = Object.values(this.cospend.projects[this.projectId].categories || {})
			allCategories.sort((a, b) => a.name.localeCompare(b.name))
			items.push(...allCategories.map((c) => ({
				name: (c.icon || '') + ' ' + c.name,
				id: c.id,
			})))
			return items
		},
		canAddMapping() {
			return this.newMappingTitle.trim() !== '' && this.newMappingCategory !== null
		},
		sortModeValue() {
			return this.sortMode.id
		},
		sortOptions() {
			return [
				{ id: 'alpha-asc', label: 'A→Z' },
				{ id: 'alpha-desc', label: 'Z→A' },
				{ id: 'date-desc', label: t('cospend', 'Newest first') },
				{ id: 'date-asc', label: t('cospend', 'Oldest first') },
			]
		},
		pageSizeOptions() {
			return [
				{ id: 5, label: '5' },
				{ id: 10, label: '10' },
				{ id: 25, label: '25' },
				{ id: 0, label: t('cospend', 'All') },
			]
		},
		sortedMappings() {
			const sorted = [...this.mappings]
			const mode = this.sortModeValue
			switch (mode) {
				case 'alpha-asc':
					sorted.sort((a, b) => a.bill_title.localeCompare(b.bill_title))
					break
				case 'alpha-desc':
					sorted.sort((a, b) => b.bill_title.localeCompare(a.bill_title))
					break
				case 'date-asc':
					sorted.sort((a, b) => (a.created_at || a.last_changed) - (b.created_at || b.last_changed))
					break
				case 'date-desc':
					sorted.sort((a, b) => (b.created_at || b.last_changed) - (a.created_at || a.last_changed))
					break
			}
			return sorted
		},
		filteredMappings() {
			const q = this.searchQuery.trim().toLowerCase()
			if (!q) {
				return this.sortedMappings
			}
			return this.sortedMappings.filter((m) => m.bill_title.toLowerCase().includes(q)
				|| this.categoryDisplay(m.category_id).toLowerCase().includes(q),
			)
		},
		totalPages() {
			const size = this.pageSize.id
			if (size === 0) {
				return 1
			}
			return Math.ceil(this.filteredMappings.length / size) || 1
		},
		paginatedMappings() {
			const size = this.pageSize.id
			if (size === 0) {
				return this.filteredMappings
			}
			const start = (this.currentPage - 1) * size
			return this.filteredMappings.slice(start, start + size)
		},
		duplicateWarning() {
			const title = this.newMappingTitle.trim().toLowerCase()
			if (!title) {
				return false
			}
			return this.mappings.some((m) => m.bill_title.toLowerCase() === title)
		},
		otherProjects() {
			const allProjects = Object.values(this.cospend.projects || {})
			return allProjects
				.filter((p) => p.id !== this.projectId)
				.sort((a, b) => a.name.localeCompare(b.name))
				.map((p) => ({
					id: p.id,
					name: p.name,
				}))
		},
		projectDialogTitle() {
			if (this.projectDialogMode === 'import') {
				return t('cospend', 'Import mappings')
			}
			if (this.projectDialogMode === 'copy-single') {
				return t('cospend', 'Copy mapping')
			}
			return t('cospend', 'Copy all mappings')
		},
		projectDialogDescription() {
			if (this.projectDialogMode === 'import') {
				return t('cospend', 'Mappings of the selected project are imported into this one. Categories are matched by name.')
			}
			if (this.projectDialogMode === 'copy-single') {
				return t('cospend', 'The mapping "{title}" is copied to the selected project. Categories are matched by name.', { title: this.titleDisplay(this.projectDialogMapping?.bill_title ?? '') })
			}
			return t('cospend', 'All mappings are copied to the selected project. Categories are matched by name.')
		},
		projectDialogSelectLabel() {
			return this.projectDialogMode === 'import'
				? t('cospend', 'Source project')
				: t('cospend', 'Target project')
		},
		projectDialogActionLabel() {
			return this.projectDialogMode === 'import'
				? t('cospend', 'Import')
				: t('cospend', 'Copy')
		},
	},

	watch: {
		projectId() {
			this.fetchMappings()
		},
		mappings() {
			this.currentPage = 1
		},
		searchQuery() {
			this.currentPage = 1
		},
		'sortMode.id': function() {
			this.currentPage = 1
		},
	},

	mounted() {
		this.fetchMappings()
		// re-fetch when a mapping is saved from the bill form checkbox
		this._onMappingsChanged = () => {
			this.fetchMappings()
		}
		subscribe('auto-category-mappings-changed', this._onMappingsChanged)
	},

	beforeUnmount() {
		unsubscribe('auto-category-mappings-changed', this._onMappingsChanged)
	},

	methods: {
		fetchMappings() {
			this.loadingMappings = true
			network.getAutoCategoryMappings(this.projectId).then((response) => {
				this.mappings = response.data.ocs.data
			}).catch((error) => {
				console.error('Failed to fetch auto-category mappings', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to fetch mappings')))
			}).then(() => {
				this.loadingMappings = false
			})
		},
		categoryDisplay(categoryId) {
			if (categoryId === null) {
				return t('cospend', 'Deleted category')
			}
			const cat = getCategory(this.projectId, categoryId)
			return (cat.icon || '') + ' ' + cat.name
		},
		titleDisplay(title) {
			return decodeHtmlEntities(title)
		},
		errorDisplay(err) {
			return decodeHtmlEntities(err)
		},
		addMapping() {
			if (!this.canAddMapping || this.adding) {
				return
			}
			this.adding = true
			const title = this.newMappingTitle.trim()
			const catId = this.newMappingCategory.id
			network.createAutoCategoryMapping(this.projectId, title, catId).then((response) => {
				const newId = response.data.ocs.data
				this.mappings.push({
					id: newId,
					project_id: this.projectId,
					bill_title: title,
					category_id: catId,
				})
				this.newMappingTitle = ''
				this.newMappingCategory = null
				showSuccess(t('cospend', 'Mapping added'))
				emit('auto-category-mappings-changed')
			}).catch((error) => {
				console.error('Failed to create mapping', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to add mapping')))
			}).then(() => {
				this.adding = false
			})
		},
		startEdit(mapping) {
			this.editingId = mapping.id
			this.editTitle = mapping.bill_title
			const cat = getCategory(this.projectId, mapping.category_id)
			this.editCategory = { id: cat.id, name: cat.icon + ' ' + cat.name }
		},
		cancelEdit() {
			this.editingId = null
			this.editTitle = ''
			this.editCategory = null
		},
		saveEdit(mapping) {
			if (this.saving) {
				return
			}
			this.saving = true
			const title = this.editTitle.trim()
			const catId = this.editCategory.id
			network.editAutoCategoryMapping(this.projectId, mapping.id, title, catId).then(() => {
				mapping.bill_title = title
				mapping.category_id = catId
				this.cancelEdit()
				showSuccess(t('cospend', 'Mapping updated'))
				emit('auto-category-mappings-changed')
			}).catch((error) => {
				console.error('Failed to update mapping', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to update mapping')))
			}).then(() => {
				this.saving = false
			})
		},
		confirmDelete(mappingId) {
			this.deleteTargetId = mappingId
			if (this.skipDeleteConfirm) {
				this.doDeleteMapping()
				return
			}
			this.showDeleteDialog = true
		},
		doDeleteMapping(mappingId) {
			const id = mappingId ?? this.deleteTargetId
			if (!id) { return }
			network.deleteAutoCategoryMapping(this.projectId, id).then(() => {
				this.mappings = this.mappings.filter((m) => m.id !== id)
				showSuccess(t('cospend', 'Mapping deleted'))
				emit('auto-category-mappings-changed')
			}).catch((error) => {
				console.error('Failed to delete mapping', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to delete mapping')))
			}).then(() => {
				this.showDeleteDialog = false
				this.deleteTargetId = null
			})
		},
		focusAddMapping() {
			const input = this.$el.querySelector('.add-mapping input')
			if (input) { input.focus() }
		},
		prevPage() {
			if (this.currentPage > 1) {
				this.currentPage--
			}
		},
		nextPage() {
			if (this.currentPage < this.totalPages) {
				this.currentPage++
			}
		},
		formatCopyResult(result) {
			const imported = result.imported
			const skipped = result.skipped
			let msg = t('cospend', '{count} mapping(s) imported', { count: imported })
			if (skipped > 0) {
				msg += ', ' + t('cospend', '{count} skipped', { count: skipped })
			}
			return msg
		},
		openProjectDialog(mode, mapping = null) {
			this.projectDialogMode = mode
			this.projectDialogMapping = mapping
			this.projectDialogTarget = null
			this.projectDialogResult = ''
			this.projectDialogErrors = []
			this.projectDialogOpen = true
		},
		doProjectDialogAction() {
			if (!this.projectDialogTarget || this.projectDialogLoading) {
				return
			}
			this.projectDialogLoading = true
			this.projectDialogResult = ''
			this.projectDialogErrors = []
			const otherProjectId = this.projectDialogTarget.id
			let request
			if (this.projectDialogMode === 'import') {
				request = network.importAutoCategoryMappings(this.projectId, otherProjectId)
			} else if (this.projectDialogMode === 'copy-single') {
				request = network.copyAutoCategoryMappings(this.projectId, otherProjectId, this.projectDialogMapping.id)
			} else {
				request = network.copyAutoCategoryMappings(this.projectId, otherProjectId)
			}
			request.then((response) => {
				const result = response.data.ocs.data
				this.projectDialogResult = this.formatCopyResult(result)
				this.projectDialogErrors = result.errors || []
				if (this.projectDialogMode === 'import' && result.imported > 0) {
					emit('auto-category-mappings-changed')
				}
			}).catch((error) => {
				console.error('Failed to copy/import mappings', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to copy mappings')))
			}).then(() => {
				this.projectDialogLoading = false
			})
		},
		autoCategorizeNow() {
			if (this.autoCategorizing) {
				return
			}
			this.autoCategorizing = true
			network.autoCategorizeProject(this.projectId).then((response) => {
				const count = response.data.ocs.data
				showSuccess(t('cospend', '{count} bill(s) categorised', { count }))
				if (count > 0) {
					emit('reload-bills')
				}
			}).catch((error) => {
				console.error('Failed to auto-categorize', error)
				showError(getErrorMessage(error, t('cospend', 'Failed to auto-categorize')))
			}).then(() => {
				this.autoCategorizing = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
.auto-category-mappings-settings {
	// keep selects vertically centered in their flex rows
	:deep(.v-select.select) {
		margin: 0;
	}

	h3 {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-top: 12px;

		.tcontent {
			flex-grow: 1;
		}
	}

	.add-mapping {
		display: flex;
		align-items: center;
		gap: 4px;
		margin: 8px 0;
	}

	.title-input {
		flex: 1 1 auto;
		min-width: 100px;
	}

	.category-select {
		flex: 1 1 auto;
		min-width: 130px;
	}

	.duplicate-warning {
		color: var(--color-main-text);
		font-size: 0.9em;
		font-weight: 600;
		padding: 6px 8px;
		margin: 4px 0;
		background: var(--color-background-dark);
		border-radius: var(--border-radius);
		border-left: 4px solid var(--color-error);
	}

	.loading-mappings {
		display: flex;
		justify-content: center;
		padding: 8px;
	}

	.no-mappings {
		color: var(--color-text-lighter);
		padding: 4px 0;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.help-card {
		background: var(--color-background-dark);
		border-radius: var(--border-radius);
		padding: 8px;
		margin: 4px 0;
		font-size: 0.9em;
		color: var(--color-text-lighter);
	}

	.sort-bar {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 4px 0;
	}

	.search-input {
		flex: 1;
		min-width: 100px;
	}

	.sort-select {
		min-width: 120px;
	}

	.mapping-count {
		color: var(--color-text-lighter);
		font-size: 0.85em;
		white-space: nowrap;
	}

	.mappings-footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		padding: 8px 0;
	}

	.pagination-bar {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.page-size-select {
		min-width: 70px;
	}

	.page-info {
		color: var(--color-text-lighter);
		font-size: 0.85em;
	}

	.mapping-item {
		display: flex;
		align-items: center;
		gap: 4px;
		padding: 4px 0;

		// the edit form needs more room than a display row
		&.editing {
			flex-wrap: wrap;
		}

		.mapping-invalid-badge {
			color: var(--color-warning);
			display: flex;
			align-items: center;
		}

		.mapping-title {
			font-weight: bold;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			min-width: 60px;
		}

		.mapping-title-invalid {
			text-decoration: line-through;
			color: var(--color-text-lighter);
		}

		.mapping-arrow {
			color: var(--color-text-lighter);
		}

		.mapping-category {
			flex-grow: 1;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.mapping-category-invalid {
			text-decoration: line-through;
			color: var(--color-warning);
		}

		.edit-buttons {
			display: flex;
			align-items: center;
			gap: 2px;
			white-space: nowrap;
		}
	}

	.auto-categorize-actions {
		margin-top: 8px;
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}
}

.project-dialog-content {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 0 4px 8px 4px;

	.project-dialog-description {
		color: var(--color-text-lighter);
	}

	.project-select {
		width: 100%;
	}

	.copy-result {
		color: var(--color-main-text);
		font-size: 0.95em;
		font-weight: 600;
		padding: 8px 10px;
		background: var(--color-main-background);
		border-radius: var(--border-radius);
		border: 1px solid var(--color-success);
		border-left: 4px solid var(--color-success);
	}

	.copy-errors {
		margin-top: 4px;
	}

	.copy-error-item {
		color: var(--color-main-text);
		font-size: 0.9em;
		font-weight: 600;
		padding: 4px 8px;
		margin: 2px 0;
		background: var(--color-background-dark);
		border-radius: var(--border-radius);
		border-left: 3px solid var(--color-error);
	}
}
</style>
