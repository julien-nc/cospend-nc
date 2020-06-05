<template>
<div id="manage-categories">
	<div id="categories-div">
		<div id="add-category-div" v-show="editionAccess">
			<label>
				<a class="icon icon-add"></a>{{ t('cospend', 'Add category') }}
			</label>
			<div id="add-category">
				<label for="addCategoryIconInput">{{ t('cospend', 'Icon') }}</label>
				<div id="add-icon-input-div">
					<input type="text" value="" maxlength="3" id="addCategoryIconInput" ref="newCategoryIcon"/>
					<button class="add-icon-button" @click="onIconButtonClick" ref="iconButton">🙂</button>
				</div>
				<label for="addCategoryNameInput">{{ t('cospend', 'Name') }}</label>
				<input type="text" value="" maxlength="300" id="addCategoryNameInput"
					v-on:keyup.enter="onAddCategory"
					ref="newCategoryName" :placeholder="t('cospend', 'New category name')"/>
				<label for="addCategoryColorInput">{{ t('cospend', 'Color') }}</label>
				<input type="color" value="" id="addCategoryColorInput" ref="newCategoryColor"/>
				<button class="addCategoryOk" @click="onAddCategory">
					<span class="icon-add"></span>
					<span>{{ t('cospend', 'Add this category') }}</span>
				</button>
			</div>
			<hr>
		</div>
		<br>
		<label>
			<a class="icon icon-category-app-bundles"></a>{{ t('cospend', 'Category list') }}
		</label>
		<div id="category-list" v-if="categories">
			<Category
				:editionAccess="editionAccess"
				v-on:delete="onDeleteCategory"
				v-on:edit="onEditCategory"
				v-for="category in categories"
				:key="category.id"
				v-bind:category="category"/>
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
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import * as constants from './constants';
import {editProject} from './project';
import {getBills} from './bill';
import EmojiButton from '@joeattardi/emoji-button';

export default {
	name: 'CategoryManagement',

	components: {
		Category
	},

	data: function() {
		return {
			categories: cospend.projects[cospend.currentProjectId].categories,
			project: cospend.projects[cospend.currentProjectId],
			constants: constants,
			editMode: false,
			editionAccess: (cospend.projects[cospend.currentProjectId].myaccesslevel >= constants.ACCESS.MAINTENER),
			picker: new EmojiButton({position: 'auto', zIndex: 9999999})
		};
	},
	mounted() {
		this.picker.on('emoji', emoji => {
			this.$refs.newCategoryIcon.value = emoji;
		});
	},
	computed: {
		categoryList() {
			return Object.values(this.categories);
		}
	},

	methods: {
		onIconButtonClick: function() {
			this.picker.togglePicker(this.$refs.iconButton);
		},
		onAddCategory: function() {
			const name = this.$refs.newCategoryName.value;
			const icon = this.$refs.newCategoryIcon.value;
			const color = this.$refs.newCategoryColor.value;
			if (name === null || name === '') {
				Notification.showTemporary(t('cospend', 'Category name should not be empty'));
				return;
			}
			const req = {
				name: name,
				icon: icon,
				color: color
			};
			let url;
			if (!cospend.pageIsPublic) {
				req.projectid = this.project.id;
				url = generateUrl('/apps/cospend/addCategory');
			} else {
				url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category');
			}
			const that = this;
			$.ajax({
				type: 'POST',
				url: url,
				data: req,
				async: true
			}).done(function(response) {
				// make sure to update vue
				that.$set(that.categories, response, {
					name: name,
					icon: icon,
					color: color,
					id: response
				});
				// this works but the vue is not updated
				//that.project.categories[response] = {
				//	name: name,
				//	icon: icon,
				//	color: color,
				//	id: response
				//};
				Notification.showTemporary(t('cospend', 'Category {n} added', {n: name}));
				that.$refs.newCategoryName.value = '';
				that.$refs.newCategoryColor.value = '';
				that.$refs.newCategoryIcon.value = '';
			}).always(function() {
			}).fail(function(response) {
				Notification.showTemporary(
					t('cospend', 'Failed to add category') +
					': ' + (response.responseJSON.message || response.responseText)
				);
			});
		},
		onDeleteCategory: function(category) {
			const that = this;
			const req = {};
			let url, type;
			if (!cospend.pageIsPublic) {
				req.projectid = cospend.currentProjectId;
				req.categoryid = category.id;
				url = generateUrl('/apps/cospend/deleteCategory');
				type = 'POST';
			} else {
				type = 'DELETE';
				url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + category.id);
			}
			$.ajax({
				type: type,
				url: url,
				data: req,
				async: true
			}).done(function() {
				//delete that.categories[category.id];
				that.$delete(that.categories, category.id);
				// reload bill list
				getBills(cospend.currentProjectId);
			}).always(function() {
			}).fail(function(response) {
				Notification.showTemporary(
					t('cospend', 'Failed to delete category') +
					': ' + response.responseJSON.message
				);
			});
		},

		onEditCategory: function(category, backupCategory) {
			if (category.name === null || category.name === '') {
				Notification.showTemporary(t('cospend', 'Category name should not be empty'));
				category.name = backupCategory.name;
				category.icon = backupCategory.icon;
				category.color = backupCategory.color;
				return;
			}
			const req = {
				name: category.name,
				icon: category.icon,
				color:category.color
			};
			let url, type;
			if (!cospend.pageIsPublic) {
				req.projectid = cospend.currentProjectId;
				req.categoryid = category.id;
				url = generateUrl('/apps/cospend/editCategory');
				type = 'POST';
			} else {
				url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + category.id);
				type = 'PUT';
			}
			$.ajax({
				type: type,
				url: url,
				data: req,
				async: true
			}).done(function() {
				// reload bill list
				getBills(cospend.currentProjectId);
			}).always(function() {
			}).fail(function(response) {
				// backup
				category.name = backupCategory.name;
				category.exchange_rate = backupCategory.exchange_rate;
				Notification.showTemporary(
					t('cospend', 'Failed to edit category') +
					'; ' + response.responseJSON.message || response.responseJSON
				);
			});
		},
	},
}
</script>

<style scoped lang="scss">
.editMainCategory {
    width: 36px !important;
}
.editMainCategoryInput {
    width: 96%;
}
#main-category-edit {
	display: grid;
    grid-template: 1fr / 150px 37px 37px;
}
#main-category-edit input[type=submit] {
    margin-left: -5px;
    border-radius: 0;
    width: 36px !important;
}
.addCategoryOk {
    background-color: #46ba61;
    color: white;
}
#main-category-edit,
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
    grid-template: 1fr / 200px 130px;
}
.addCategoryRateHint {
    grid-column: 1/3;
}
#main-category-label-label,
#add-category label {
    line-height: 40px;
}
</style>
