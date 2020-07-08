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
                        ref="newCategoryName" :placeholder="t('cospend', 'Category name')"/>
                    <label for="addCategoryColorInput">{{ t('cospend', 'Color') }}</label>
                    <ColorPicker class="app-navigation-entry-bullet-wrapper" value="" @input="updateAddColor" ref="addcol">
                        <input type="color" value="" v-on:click.prevent :readonly="true" id="addCategoryColorInput" ref="newCategoryColor"/>
                    </ColorPicker>
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
import { ColorPicker } from '@nextcloud/vue'
import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import * as constants from './constants';
import EmojiButton from '@joeattardi/emoji-button';
import * as network from './network';

export default {
    name: 'CategoryManagement',

    components: {
        Category, ColorPicker
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
            ]})
        };
    },
    mounted() {
        this.picker.on('emoji', emoji => {
            this.$refs.newCategoryIcon.value = emoji;
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
            this.$refs.newCategoryColor.value = color;
        },
        onIconButtonClick() {
            this.picker.togglePicker(this.$refs.iconButton);
        },
        onAddCategory() {
            const name = this.$refs.newCategoryName.value;
            const icon = this.$refs.newCategoryIcon.value;
            const color = this.$refs.newCategoryColor.value;
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
            this.$refs.newCategoryColor.value = '';
            this.$refs.newCategoryIcon.value = '';
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
</style>
