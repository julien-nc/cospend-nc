<template>
	<div class="one-category">
		<div v-show="!editMode"
			class="one-category-label">
			<div class="colorDot"
				:style="{ backgroundColor: category.color }" />
			<label class="one-category-label-label">{{ category.icon || '' }}</label>
			<label class="one-category-label-label">{{ category.name }}</label>
			<input v-show="editionAccess"
				type="submit"
				value=""
				class="icon-rename editOneCategory"
				@click="onClickEdit">
			<input v-show="editionAccess"
				type="submit"
				value=""
				:class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneCategory'"
				@click="onClickDelete">
			<label v-if="timerOn"
				class="one-category-label-label">
				<vac :end-time="new Date().getTime() + (7000)">
					<template v-slot:process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
			</label>
		</div>
		<div v-show="editMode"
			class="one-category-edit">
			<ColorPicker ref="col"
				class="app-navigation-entry-bullet-wrapper"
				value=""
				@input="updateColor">
				<div :style="{ backgroundColor: category.color }" class="color0 icon-colorpicker" />
			</ColorPicker>
			<div class="edit-icon-input-div">
				<input ref="editIconInput"
					v-model="category.icon"
					type="text"
					maxlength="3"
					class="editCategoryIconInput">
				<button ref="iconButton"
					class="edit-icon-button"
					@click="onIconButtonClick">
					🙂
				</button>
			</div>
			<input ref="cname"
				v-model="category.name"
				type="text"
				maxlength="300"
				class="editCategoryNameInput"
				:placeholder="t('cospend', 'Category name')"
				@focus="$event.target.select()">
			<button class="editCategoryClose"
				@click="onClickCancel">
				<span class="icon-history" />
			</button>
			<button class="editCategoryOk"
				@click="onClickEditOk">
				<span class="icon-checkmark" />
			</button>
		</div>
	</div>
</template>

<script>
import { Timer } from '../utils'
import EmojiButton from '@joeattardi/emoji-button'
import { ColorPicker } from '@nextcloud/vue'

export default {
	name: 'Category',

	components: {
		ColorPicker,
	},

	props: {
		category: {
			type: Object,
			required: true,
		},
		editionAccess: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			editMode: false,
			timerOn: false,
			timer: null,
			categoryBackup: null,
			picker: new EmojiButton({
				position: 'auto',
				zIndex: 9999999,
				categories: [
					'objects',
					'symbols',
					'flags',
					'smileys',
					'people',
					'animals',
					'food',
					'activities',
					'travel',
				],
			}),
		}
	},

	computed: {
	},

	mounted() {
		this.picker.on('emoji', emoji => {
			// avoid setting value of input with v-model, prefer modifying v-model target instead
			// this.$refs.editIconInput.value = emoji
			this.category.icon = emoji
		})
	},

	methods: {
		updateColor(color) {
			this.category.color = color
		},
		onIconButtonClick() {
			this.picker.togglePicker(this.$refs.iconButton)
		},
		onClickEdit() {
			this.editMode = true
			this.categoryBackup = {
				color: this.category.color,
				name: this.category.name,
				icon: this.category.icon,
			}
			this.$nextTick(() => this.$refs.cname.focus())
		},
		onClickCancel() {
			this.editMode = false
			this.category.name = this.categoryBackup.name
			this.category.color = this.categoryBackup.color
			this.category.icon = this.categoryBackup.icon
		},
		onClickDelete() {
			if (this.timerOn) {
				this.timerOn = false
				this.timer.pause()
				delete this.timer
			} else {
				this.timerOn = true
				const that = this
				this.timer = new Timer(() => {
					that.timerOn = false
					that.$emit('delete', that.category)
				}, 7000)
			}
		},
		onClickEditOk() {
			this.$emit('edit', this.category, this.categoryBackup)
			this.editMode = false
		},
	},
}
</script>

<style scoped lang="scss">
.one-category-edit {
	display: grid;
	grid-template: 1fr / 1fr 2fr 3fr 44px 44px;
	padding: 10px 10px 10px 20px;
	background-color: var(--color-background-dark);
}
.one-category-edit label,
#add-category label,
.one-category-label label {
	line-height: 40px;
}
.one-category-label input[type=submit] {
	border-radius: 50% !important;
	width: 40px !important;
	height: 40px;
	margin-top: 0px;
}
.one-category-label {
	display: grid;
	grid-template: 1fr / 1fr 1fr 6fr 42px 42px 40px;
}
.editCategoryOk,
.editCategoryClose {
	margin-top: 0px;
	height: 40px;
}
.editCategoryOk {
	background-color: #46ba61;
	color: white;
}
.one-category-label-icon {
	font-size: 22px;
}
$clickable-area: 44px;
.color0 {
	width: calc(#{$clickable-area} - 6px);
	height: calc(#{$clickable-area} - 6px);
	background-size: 14px;
	border-radius: 50%;
}
.colorDot {
	width: calc(#{$clickable-area} - 20px);
	height: calc(#{$clickable-area} - 20px);
	border-radius: 50%;
	margin-top: 8px;
}
.edit-icon-button {
	height: 34px;
}
</style>
