<template>
	<div class="manage-elements">
		<div>
			<div id="order-selection">
				<label for="order-select">
					<span class="icon icon-settings-dark" />
					<span>{{ sortOrderLabel }}</span>
				</label>
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
					<option :value="constants.SORT_ORDER.MOST_RECENTLY_USED">
						{{ t('cospend', 'Most recently used') }}
					</option>
				</select>
			</div>
			<hr>
			<div v-show="editionAccess">
				<h3>
					<a class="icon icon-add" />{{ addElementLabel }}
				</h3>
				<div class="add-element">
					<ColorPicker class="app-navigation-entry-bullet-wrapper" value="" @input="updateAddColor">
						<div
							v-tooltip.top="{ content: t('cospend', 'Color') }"
							class="color0 icon-colorpicker clickable"
							:style="{ backgroundColor: newColor }" />
					</ColorPicker>
					<EmojiPicker :show-preview="true"
						@select="selectEmoji">
						<button
							v-tooltip.top="{ content: t('cospend', 'Icon') }"
							class="add-icon-button">
							{{ newIcon }}
						</button>
					</EmojiPicker>
					<input ref="newName"
						type="text"
						value=""
						maxlength="300"
						class="new-name"
						:placeholder="newNamePlaceholder"
						@focus="$event.target.select()"
						@keyup.enter="onAddElement">
					<button
						v-tooltip.top="{ content: addTooltip }"
						class="icon icon-add-white addElementOk"
						@click="onAddElement" />
				</div>
				<hr>
			</div>
			<h3>
				<a :class="{ icon: true, [icon]: true }" />{{ listLabel }}
			</h3>
			<label v-if="hasElements && editionAccess && sortOrderValue === constants.SORT_ORDER.MANUAL" class="hint">
				<span class="icon icon-info" />{{ dragText }}
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
				<EmptyContent
					:icon="icon">
					<template #desc>
						{{ emptyContentText }}
					</template>
				</EmptyContent>
			</div>
		</div>
	</div>
</template>

<script>
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

import { Container, Draggable } from 'vue-smooth-dnd'

import cospend from './state'
import CategoryOrPm from './components/CategoryOrPm'
import * as constants from './constants'
import * as network from './network'
import { strcmp } from './utils'

export default {
	name: 'CategoryOrPmManagement',

	components: {
		CategoryOrPm, ColorPicker, EmojiPicker, Container, Draggable, EmptyContent,
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
			constants,
			editMode: false,
			newColor: '#000000',
			newIcon: '🙂',
		}
	},

	computed: {
		project() {
			return cospend.projects[this.projectId]
		},
		icon() {
			return this.type === 'category' ? 'icon-category-app-bundles' : 'icon-tag'
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
				constants.SORT_ORDER.MOST_RECENTLY_USED,
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
				? network.addCategory
				: network.addPaymentMode
			func(this.project.id, name, icon, color, order).then((response) => {
				this.addElementSuccess(response.data, name, icon, color)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to add {name}', { name })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},
		addElementSuccess(response, name, icon, color) {
			// make sure to update vue
			this.$set(this.elements, response, {
				name,
				icon,
				color,
				id: response,
			})
			showSuccess(t('cospend', '{name} was added', { name }))
			this.$refs.newName.value = ''
			this.newColor = '#000000'
			this.newIcon = '🙂'
		},
		onDeleteElement(element) {
			if (this.type === 'category') {
				network.deleteCategory(this.project.id, element.id).then((response) => {
					this.deleteElementSuccess(element.id)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to delete category')
						+ ': ' + error.response.request.responseText
					)
				})
			} else {
				network.deletePaymentMode(this.project.id, element.id).then((response) => {
					this.deleteElementSuccess(element.id)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to delete payment mode')
						+ ': ' + error.response.request.responseText
					)
				})
			}
		},
		deleteElementSuccess(elementid) {
			this.$delete(this.elements, elementid)
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
						+ ': ' + error.response.request.responseText
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
				cospend.projects[this.projectId].categorysort = e.target.value
			} else {
				cospend.projects[this.projectId].paymentmodesort = e.target.value
			}
			this.$emit('project-edited', this.projectId)
		},
	},
}
</script>

<style scoped lang="scss">
.manage-elements {
	.icon {
		line-height: 44px;
		padding: 0 12px 0 25px;
	}
}

.addElementOk {
	margin-top: 0px;
	width: 40px;
	height: 40px;
	border-radius: var(--border-radius-pill);
	opacity: .5;

	&.icon {
		background-color: var(--color-success);
		border: none;
		margin: 0;
	}

	&:hover,
	&:focus {
		opacity: 1;
	}
}

.element-list {
	margin-left: 37px;
}

$clickable-area: 44px;

.color0 {
	width: calc(#{$clickable-area} - 6px);
	height: calc(#{$clickable-area} - 6px);
	background-size: 14px;
	border-radius: 50%;
}

.add-element {
	display: grid;
	grid-template: 1fr / 44px 44px 4fr 44px;
	padding: 10px 10px 10px 20px;
	label {
		line-height: 40px;
	}
}

.add-icon-button {
	margin-top: 0px;
	padding: 0;
	border-radius: 50%;
	width: 40px;
	height: 40px;
}

.new-name {
	width: 90%;
}

.hint {
	opacity: 0.7;
}

.clickable {
	cursor: pointer;
}

#order-selection label,
#order-selection select {
	display: inline-block;
	width: 49%;
}
</style>
