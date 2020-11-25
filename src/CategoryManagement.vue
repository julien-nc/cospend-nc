<template>
	<div id="manage-categories">
		<div id="categories-div">
			<div v-show="editionAccess"
				id="add-category-div">
				<label>
					<a class="icon icon-add" />{{ t('cospend', 'Add category') }}
				</label>
				<div class="add-category-2">
					<ColorPicker class="app-navigation-entry-bullet-wrapper" value="" @input="updateAddColor">
						<div class="color0 icon-colorpicker"
							:style="{ backgroundColor: newCategoryColor }"
							:title="t('cospend', 'Color')" />
					</ColorPicker>
					<EmojiPicker :show-preview="true"
						@select="selectEmoji">
						<button class="add-icon-button"
							:title="t('cospend', 'Icon')">
							{{ newCategoryIcon }}
						</button>
					</EmojiPicker>
					<input ref="newCategoryName"
						type="text"
						value=""
						maxlength="300"
						class="new-category-name"
						:placeholder="t('cospend', 'Category name')"
						@focus="$event.target.select()"
						@keyup.enter="onAddCategory">
					<button
						v-tooltip.left="{ content: t('cospend', 'Add this category') }"
						class="icon icon-add-white addCategoryOk"
						@click="onAddCategory" />
				</div>
				<hr>
			</div>
			<br>
			<label>
				<a class="icon icon-category-app-bundles" />{{ t('cospend', 'Category list') }}
			</label>
			<div v-if="categories"
				id="category-list">
				<Category
					v-for="category in categories"
					:key="category.id"
					:category="category"
					:edition-access="editionAccess"
					@delete="onDeleteCategory"
					@edit="onEditCategory" />
			</div>
			<div v-else class="no-categories">
				{{ t('cospend', 'No categories to display') }}
			</div>
		</div>
	</div>
</template>

<script>
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

import cospend from './state'
import Category from './components/Category'
import * as constants from './constants'
import * as network from './network'

export default {
	name: 'CategoryManagement',

	components: {
		Category, ColorPicker, EmojiPicker,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			constants,
			editMode: false,
			newCategoryColor: '#000000',
			newCategoryIcon: '🙂',
		}
	},

	computed: {
		project() {
			return cospend.projects[this.projectId]
		},
		categories() {
			return this.project.categories
		},
		editionAccess() {
			return (this.project.myaccesslevel >= constants.ACCESS.MAINTENER)
		},
		categoryList() {
			return Object.values(this.categories)
		},
	},

	methods: {
		selectEmoji(emoji) {
			this.newCategoryIcon = emoji
		},
		updateAddColor(color) {
			this.newCategoryColor = color
		},
		onAddCategory() {
			const name = this.$refs.newCategoryName.value
			const icon = this.newCategoryIcon
			const color = this.newCategoryColor
			if (name === null || name === '') {
				showError(t('cospend', 'Category name should not be empty.'))
				return
			}
			network.addCategory(this.project.id, name, icon, color, this.addCategorySuccess)
		},
		addCategorySuccess(response, name, icon, color) {
			// make sure to update vue
			this.$set(this.categories, response, {
				name,
				icon,
				color,
				id: response,
			})
			showSuccess(t('cospend', 'Category {n} added.', { n: name }))
			this.$refs.newCategoryName.value = ''
			this.newCategoryColor = '#000000'
			this.newCategoryIcon = '🙂'
		},
		onDeleteCategory(category) {
			network.deleteCategory(this.project.id, category.id, this.deleteCategorySuccess)
		},
		deleteCategorySuccess(categoryid) {
			this.$delete(this.categories, categoryid)
			this.$emit('category-deleted', categoryid)
		},
		onEditCategory(category, name, icon, color) {
			if (name === null || name === '') {
				showError(t('cospend', 'Category name should not be empty.'))
				return
			}
			const backupCategory = {
				name: category.name,
				icon: category.icon,
				color: category.color,
			}
			category.name = name
			category.icon = icon
			category.color = color
			network.editCategory(this.project.id, category, backupCategory, this.editCategoryFail)
		},
		editCategoryFail(category, backupCategory) {
			// backup
			category.name = backupCategory.name
			category.icon = backupCategory.icon
			category.color = backupCategory.color
		},
	},
}
</script>

<style scoped lang="scss">
#manage-categories {
	margin-left: 20px;
}

#manage-categories .icon {
	line-height: 44px;
	padding: 0 12px 0 25px;
}

.addCategoryOk {
	margin-top: 0px;

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

#add-category,
#category-list,
#main-category-label {
	margin-left: 37px;
}

#main-category-label {
	width: 160px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
}

#add-category {
	display: grid;
	grid-template: 1fr / 1fr 1fr;
}

.addCategoryRateHint {
	grid-column: 1/3;
}

#main-category-label-label,
#add-category label {
	line-height: 40px;
}

#addCategoryNameInput {
	width: 100%;
}

$clickable-area: 44px;

.color0 {
	width: calc(#{$clickable-area} - 6px);
	height: calc(#{$clickable-area} - 6px);
	background-size: 14px;
	border-radius: 50%;
}

.add-category-2 {
	display: grid;
	grid-template: 1fr / 44px 44px 4fr 44px;
	padding: 10px 10px 10px 20px;
}

.add-category-2 label {
	line-height: 40px;
}

.add-icon-button {
	margin-top: 0px;
	border-radius: 50%;
	width: 40px;
	height: 40px;
}

.new-category-name {
	width: 90%;
}
</style>
