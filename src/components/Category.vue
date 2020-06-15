<template>
    <div class="one-category">
        <div class="one-category-label" v-show="!editMode">
            <label class="one-category-label-label">{{ category.icon || '' }}</label>
            <label class="one-category-label-label">{{ category.name }}</label>
            <input class="one-category-label-color" type="color" :value="category.color" v-on:click.prevent readonly="readonly"/>
            <input type="submit" value="" class="icon-rename editOneCategory"
                @click="onClickEdit" v-show="editionAccess"/>
            <input type="submit" value="" :class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneCategory'"
                @click="onClickDelete" v-show="editionAccess"/>
            <label class="one-category-label-label" v-if="timerOn">
                <vac :end-time="new Date().getTime() + (7000)">
                    <template v-slot:process="{ timeObj }">
                        <span>{{ `${timeObj.s}` }}</span>
                    </template>
                </vac>
			</label>
        </div>
        <div class="one-category-edit" v-show="editMode">
            <label>{{ t('cospend', 'Icon') }}</label>
            <div class="edit-icon-input-div">
                <input type="text" v-model="category.icon" maxlength="3" class="editCategoryIconInput"
                    ref="editIconInput"/>
                <button class="edit-icon-button" @click="onIconButtonClick" ref="iconButton">🙂</button>
            </div>
            <label>{{ t('cospend', 'Name') }}</label>
            <input type="text" v-model="category.name" maxlength="300" @focus="$event.target.select()"
                    ref="cname" class="editCategoryNameInput" :placeholder="t('cospend', 'Category name')"/>
            <label>{{ t('cospend', 'Color') }}</label>
            <input type="color" v-model="category.color" class="editCategoryColorInput"/>
            <div>
                <button class="editCategoryClose" @click="onClickCancel">
                    <span class="icon-close"></span>
                    <span>{{ t('cospend', 'Cancel') }}</span>
                </button>
                <button class="editCategoryOk" @click="onClickEditOk">
                    <span class="icon-checkmark"></span>
                    <span>{{ t('cospend', 'Save') }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import {Timer} from "../utils";
import EmojiButton from '@joeattardi/emoji-button';
import {vueAwesomeCountdown} from 'vue-awesome-countdown'

export default {
    name: 'Category',

    components: {
        vueAwesomeCountdown
    },

    props: ['category', 'editionAccess'],
    data() {
        return {
            editMode: false,
            timerOn: false,
            timer: null,
            categoryBackup: null,
            picker: new EmojiButton({position: 'auto', zIndex: 9999999})
        };
    },

    computed: {
    },
    mounted() {
        this.picker.on('emoji', emoji => {
            // avoid setting value of input with v-model, prefer modifying v-model target instead
            //this.$refs.editIconInput.value = emoji;
            this.category.icon = emoji;
        });
    },

    methods: {
        onIconButtonClick() {
            this.picker.togglePicker(this.$refs.iconButton);
        },
        onClickEdit() {
            this.editMode = true;
            this.categoryBackup = {
                color: this.category.color,
                name: this.category.name,
                icon: this.category.icon,
            }
            this.$nextTick(() => this.$refs.cname.focus());
        },
        onClickCancel() {
            this.editMode = false;
            this.category.name = this.categoryBackup.name;
            this.category.color = this.categoryBackup.color;
            this.category.icon = this.categoryBackup.icon;
        },
        onClickDelete() {
            if (this.timerOn) {
                this.timerOn = false;
                this.timer.pause();
                delete this.timer;
            } else {
                this.timerOn = true;
                const that = this;
                this.timer = new Timer(function () {
                    that.timerOn = false;
                    that.$emit('delete', that.category);
                }, 7000);
            }
        },
        onClickEditOk() {
            this.$emit('edit', this.category, this.categoryBackup);
            this.editMode = false;
        }
    },
}
</script>

<style scoped lang="scss">
.one-category-edit {
    display: grid;
    grid-template: 1fr / 200px 100px;
    padding: 10px 0px 10px 20px;
    background-color: var(--color-background-dark);
}
.one-category-edit label,
#add-category label,
.one-category-label label {
    line-height: 40px;
}
.one-category-label input[type=submit] {
    border-radius: 0 !important;
    width: 36px !important;
}
.one-category-label {
    display: grid;
    grid-template: 1fr / 30px 150px 150px 37px 37px 37px;
}
.editCategoryOk {
    background-color: #46ba61;
    color: white;
}
.editCategoryClose {
    background-color: #e9322d;
    color: white;
}
.one-category-label-icon {
    font-size: 22px;
}
</style>
