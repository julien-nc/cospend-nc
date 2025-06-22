<template>
	<div class="manage-elements">
		<div>
			<div v-show="editionAccess">
				<h3>
					<PlusIcon
						class="icon"
						:size="20" />
					{{ addElementLabel }}
				</h3>
				<div class="add-element">
					<NcColorPicker class="app-navigation-entry-bullet-wrapper" :model-value="''" @update:model-value="updateAddColor">
						<NcButton
							:title="t('cospend', 'Color')"
							:aria-label="t('cospend', 'Color')"
							:style="{ backgroundColor: newColor }">
							<template #icon>
								<PaletteIcon :size="20" />
							</template>
						</NcButton>
					</NcColorPicker>
					<NcEmojiPicker :show-preview="true"
						@select="selectEmoji">
						<NcButton
							:title="t('cospend', 'Icon')"
							:aria-label="t('cospend', 'Icon')"
							class="emojiButton">
							{{ newIcon }}
						</NcButton>
					</NcEmojiPicker>
					<input ref="newName"
						type="text"
						value=""
						maxlength="300"
						class="new-name"
						:placeholder="newNamePlaceholder"
						@focus="$event.target.select()"
						@keyup.enter="onAddElement">
					<NcButton
						:title="addTooltip"
						:aria-label="addTooltip"
						variant="primary"
						@click="onAddElement">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
					</NcButton>
				</div>
				<hr>
			</div>
			<div id="order-selection">
				<h3 for="order-select">
					<SortIcon
						class="icon"
						:size="20" />
					<span>{{ sortOrderLabel }}</span>
				</h3>
				<select id="order-select"
					:disabled="!adminAccess"
					:value="sortOrderValue"
					@input="onSortChange">
					<option :value="constants.SORT_ORDER.ALPHA">
						{{ t('cospend', 'Alphabetical') }}
					</option>
					<option :value="constants.SORT_ORDER.MANUAL">
						{{ t('cospend', 'Manual') }}
					</option>
					<option :value="constants.SORT_ORDER.MOST_USED">
						{{ t('cospend', 'Most used') }}
					</option>
					<option :value="constants.SORT_ORDER.RECENTLY_USED">
						{{ t('cospend', 'Most recently used') }}
					</option>
				</select>
			</div>
			<hr>
			<h3>
				<ShapeIcon v-if="type === 'category'"
					class="icon"
					:size="20" />
				<TagIcon v-else
					class="icon"
					:size="20" />
				{{ listLabel }}
			</h3>
			<label v-if="hasElements && editionAccess && sortOrderValue === constants.SORT_ORDER.MANUAL" class="hint">
				<InformationOutlineIcon :size="20" />
				<span>{{ dragText }}</span>
			</label>
			<div v-if="hasElements && editionAccess && sortOrderValue === constants.SORT_ORDER.MANUAL"
				class="element-list">
				<Container @drop="onDrop">
					<Draggable
						v-for="element in sortedElements"
						:key="element.id">
						<CategoryOrPm
							:element="element"
							:edition-access="editionAccess"
							:draggable="true"
							@delete="onDeleteElement"
							@edit="onEditElement" />
					</Draggable>
				</Container>
			</div>
			<div v-else-if="hasElements"
				class="element-list">
				<CategoryOrPm
					v-for="element in sortedElements"
					:key="element.id"
					:element="element"
					:edition-access="editionAccess"
					@delete="onDeleteElement"
					@edit="onEditElement" />
			</div>
			<div v-else>
				<NcEmptyContent
					:name="emptyContentText"
					:title="emptyContentText">
					<template #icon>
						<ShapeIcon v-if="type === 'category'"
							class="icon"
							:size="20" />
						<TagIcon v-else
							class="icon"
							:size="20" />
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import SortIcon from 'vue-material-design-icons/Sort.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ShapeIcon from 'vue-material-design-icons/Shape.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import CategoryOrPm from './components/CategoryOrPm.vue'

import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

import { Container, Draggable } from 'vue3-smooth-dnd'

import * as constants from './constants.js'
import * as network from './network.js'
import { strcmp } from './utils.js'

export default {
	name: 'CategoryOrPmManagement',

	components: {
		CategoryOrPm,
		NcColorPicker,
		NcEmojiPicker,
		Container,
		Draggable,
		NcEmptyContent,
		TagIcon,
		ShapeIcon,
		PlusIcon,
		SortIcon,
		PaletteIcon,
		InformationOutlineIcon,
		NcButton,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
		type: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			cospend: OCA.Cospend.state,
			constants,
			editMode: false,
			newColor: '#000000',
			newIcon: '🙂',
		}
	},

	computed: {
		project() {
			return this.cospend.projects[this.projectId]
		},
		sortOrderLabel() {
			return this.type === 'category' ? t('cospend', 'Category sort method') : t('cospend', 'Payment mode sort method')
		},
		addElementLabel() {
			return this.type === 'category' ? t('cospend', 'Add category') : t('cospend', 'Add payment mode')
		},
		newNamePlaceholder() {
			return this.type === 'category' ? t('cospend', 'Category name') : t('cospend', 'Payment mode name')
		},
		addTooltip() {
			return this.type === 'category' ? t('cospend', 'Add this category') : t('cospend', 'Add this payment mode')
		},
		listLabel() {
			return this.type === 'category' ? t('cospend', 'Category list') : t('cospend', 'Payment mode list')
		},
		dragText() {
			return this.type === 'category' ? t('cospend', 'Drag categories to set manual order') : t('cospend', 'Drag payment modes to set manual order')
		},
		emptyContentText() {
			return this.type === 'category' ? t('cospend', 'No categories') : t('cospend', 'No payment modes')
		},
		sortOrderValue() {
			return this.type === 'category'
				? this.project.categorysort || constants.SORT_ORDER.MANUAL
				: this.project.paymentmodesort || constants.SORT_ORDER.MANUAL
		},
		elements() {
			return this.type === 'category'
				? this.project.categories
				: this.project.paymentmodes
		},
		adminAccess() {
			return (this.project.myaccesslevel >= constants.ACCESS.ADMIN)
		},
		editionAccess() {
			return (this.project.myaccesslevel >= constants.ACCESS.MAINTENER)
		},
		elementList() {
			return Object.values(this.elements)
		},
		hasElements() {
			return this.elementList.length > 0
		},
		sortedElements() {
			if ([
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
			].includes(this.sortOrderValue)) {
				return this.elementList.slice().sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
			} else if (this.sortOrderValue === constants.SORT_ORDER.ALPHA) {
				return this.elementList.slice().sort((a, b) => {
					return strcmp(a.name, b.name)
				})
			}
			return []
		},
	},

	methods: {
		selectEmoji(emoji) {
			this.newIcon = emoji
		},
		updateAddColor(color) {
			this.newColor = color
		},
		onAddElement() {
			const name = this.$refs.newName.value
			const icon = this.newIcon
			const color = this.newColor
			const order = this.elementList.length
			if (name === null || name === '') {
				showError(t('cospend', 'Name should not be empty'))
				return
			}
			const func = this.type === 'category'
				? network.createCategory
				: network.createPaymentMode
			func(this.project.id, name, icon, color, order).then((response) => {
				this.addElementSuccess(response.data.ocs.data, name, icon, color)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to add {name}', { name })
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		addElementSuccess(response, name, icon, color) {
			this.elements[response] = {
				name,
				icon,
				color,
				id: response,
			}
			showSuccess(t('cospend', '{name} was added', { name }))
			this.$refs.newName.value = ''
			this.newColor = '#000000'
			this.newIcon = '🙂'
		},
		onDeleteElement(element) {
			if (this.type === 'category') {
				network.deleteCategory(this.project.id, element.id).then((response) => {
					console.debug('aaaaaaaaa delete element', element)
					this.deleteElementSuccess(element.id)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to delete category')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
					console.error(error)
				})
			} else {
				network.deletePaymentMode(this.project.id, element.id).then((response) => {
					this.deleteElementSuccess(element.id)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to delete payment mode')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		deleteElementSuccess(elementid) {
			delete this.elements[elementid]
			this.$emit('element-deleted', elementid)
		},
		onEditElement(element, name, icon, color) {
			if (name === null || name === '') {
				showError(t('cospend', 'Name should not be empty'))
				return
			}
			const backupElement = {
				name: element.name,
				icon: element.icon,
				color: element.color,
			}
			element.name = name
			element.icon = icon
			element.color = color
			if (this.type === 'category') {
				network.editCategory(this.project.id, element, backupElement).then((response) => {
				}).catch((error) => {
					this.editElementFail(element, backupElement)
					showError(
						t('cospend', 'Failed to edit category')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			} else {
				network.editPaymentMode(this.project.id, element, backupElement, this.editElementFail)
			}
		},
		editElementFail(element, backupElement) {
			// backup
			element.name = backupElement.name
			element.icon = backupElement.icon
			element.color = backupElement.color
		},
		onDrop(e) {
			const index = e.removedIndex
			const newIndex = e.addedIndex
			if (index !== newIndex) {
				const currentList = this.sortedElements
				// initialize order
				for (let i = 0; i < currentList.length; i++) {
					currentList[i].order = i
				}
				// change the one that's been moved
				currentList[index].order = newIndex
				// change others along the way
				if (index > newIndex) {
					for (let i = newIndex; i < index; i++) {
						currentList[i].order++
					}
				} else {
					for (let i = index + 1; i <= newIndex; i++) {
						currentList[i].order--
					}
				}
				this.saveElementsOrder()
			}
		},
		saveElementsOrder() {
			const order = this.elementList.map((e) => { return { id: e.id, order: e.order } })
			const func = this.type === 'category'
				? network.saveCategoryOrder
				: network.savePaymentModeOrder
			func(this.project.id, order).then((response) => {
				showSuccess(t('cospend', 'Order saved'))
			}).catch((error) => {
				console.error(error)
			})
		},
		onSortChange(e) {
			if (this.type === 'category') {
				this.cospend.projects[this.projectId].categorysort = e.target.value
			} else {
				this.cospend.projects[this.projectId].paymentmodesort = e.target.value
			}
			this.$emit('project-edited', this.projectId)
		},
	},
}
</script>

<style scoped lang="scss">
h3 {
	margin-top: 12px;
	display: flex;
	align-items: center;
	gap: 8px;
}

.element-list {
	margin-left: 37px;
}

:deep(.emojiButton *) {
	margin: 0 !important;
	margin-left: 0 !important;
	margin-right: 0 !important;
}

.add-element {
	display: flex;
	align-items: center;
	padding: 10px 10px 10px 20px;
	> * {
		margin: 0 4px 0 4px;
	}
}

.new-name {
	width: 90%;
}

.hint {
	opacity: 0.7;
	display: flex;
	span {
		margin-left: 10px;
	}
}

#order-selection {
	display: flex;
	flex-direction: column;
	align-items: start;
}
</style>
