<template>
	<div id="manage-categories">
		<div id="categories-div">
			<div id="add-category-div" v-show="editionAccess">
				<label>
					<a class="icon icon-add"></a>{{ t('cospend', 'Add category') }}
				</label>
				<div class="add-category-2">
					<ColorPicker class="app-navigation-entry-bullet-wrapper" value="" @input="updateAddColor">
						<div :style="{ backgroundColor: newCategoryColor }" class="color0 icon-colorpicker" :title="t('cospend', 'Color')"/>
					</ColorPicker>
					<button class="add-icon-button" :title="t('cospend', 'Icon')"
						@click="onIconButtonClick" ref="iconButton">
						{{ newCategoryIcon }}
					</button>
					<input type="text" value="" maxlength="300" @focus="$event.target.select()"
							v-on:keyup.enter="onAddCategory"
							class="new-category-name"
							ref="newCategoryName" :placeholder="t('cospend', 'Category name')"/>
					<button class="addCategoryOk" @click="onAddCategory">
						<span class="icon-add"></span>
					</button>
				</div>
				<hr>
			</div>
			<br>
			<label>
				<a class="icon icon-category-app-bundles"></a>{{ t('cospend', 'Category list') }}
			</label>
			<div id="category-list" v-if="categories">
				<slide-x-right-transition group>
					<Category
						:editionAccess="editionAccess"
						v-on:delete="onDeleteCategory"
						v-on:edit="onEditCategory"
						v-for="category in categories"
						:key="category.id"
						v-bind:category="category"/>
				</slide-x-right-transition>
			</div>
			<div v-else class="no-categories">
				{{ t('cospend', 'No categories to display') }}
			</div>
		</div>
	</div>
</template>

<script>
import cospend from './state';
import Category from './components/Category';
import { ColorPicker } from '@nextcloud/vue'
import {generateUrl} from '@nextcloud/router';
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import * as constants from './constants';
import EmojiButton from '@joeattardi/emoji-button';
import * as network from './network';
import { SlideXRightTransition } from 'vue2-transitions'

export default {
	name: 'CategoryManagement',

	components: {
		Category, ColorPicker, SlideXRightTransition
	},

	props: ['projectId'],
	data() {
		return {
			constants: constants,
			editMode: false,
			picker: new EmojiButton({position: 'auto', zIndex: 9999999, categories: [
				'objects',
				'symbols',
				'flags',
				'smileys',
				'people',
				'animals',
				'food',
				'activities',
				'travel'
			]}),
			newCategoryColor: '#000000',
			newCategoryIcon: '🙂'
		};
	},
	mounted() {
		this.picker.on('emoji', emoji => {
			this.newCategoryIcon = emoji;
		});
	},
	computed: {
		project() {
			return cospend.projects[this.projectId];
		},
		categories() {
			return this.project.categories;
		},
		editionAccess() {
			return (this.project.myaccesslevel >= constants.ACCESS.MAINTENER);
		},
		categoryList() {
			return Object.values(this.categories);
		}
	},

	methods: {
		updateAddColor(color) {
			this.newCategoryColor = color;
		},
		onIconButtonClick() {
			this.picker.togglePicker(this.$refs.iconButton);
		},
		onAddCategory() {
			const name = this.$refs.newCategoryName.value;
			const icon = this.newCategoryIcon;
			const color = this.newCategoryColor;
			if (name === null || name === '') {
				showError(t('cospend', 'Category name should not be empty.'));
				return;
			}
			network.addCategory(this.project.id, name, icon, color, this.addCategorySuccess);
		},
		addCategorySuccess(response, name, icon, color) {
			// make sure to update vue
			this.$set(this.categories, response, {
				name: name,
				icon: icon,
				color: color,
				id: response
			});
			showSuccess(t('cospend', 'Category {n} added.', {n: name}));
			this.$refs.newCategoryName.value = '';
			this.newCategoryColor = '#000000';
			this.newCategoryIcon = '🙂';
		},
		onDeleteCategory(category) {
			network.deleteCategory(this.project.id, category.id, this.deleteCategorySuccess);
		},
		deleteCategorySuccess(categoryid) {
			this.$delete(this.categories, categoryid);
			this.$emit('categoryDeleted', categoryid);
		},
		onEditCategory(category, backupCategory) {
			if (category.name === null || category.name === '') {
				showError(t('cospend', 'Category name should not be empty.'));
				category.name = backupCategory.name;
				category.icon = backupCategory.icon;
				category.color = backupCategory.color;
				return;
			}
			network.editCategory(this.project.id, category, backupCategory, this.editCategoryFail);
		},
		editCategoryFail(category, backupCategory) {
			// backup
			category.name = backupCategory.name;
			category.exchange_rate = backupCategory.exchange_rate;
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
	grid-template: 1fr / 1fr 1fr 4fr 44px;
	padding: 10px 10px 10px 20px;
}
.add-category-2 label {
	line-height: 40px;
}
.add-icon-button {
	margin-top: 0px;
	border-radius: 50%;
	width: 40px;
}
.new-category-name {
	width: 90%;
}
</style>
