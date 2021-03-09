<template>
	<div class="one-category">
		<span v-if="draggable"
			class="icon icon-move" />
		<div v-show="!editMode"
			class="one-category-label">
			<div class="colorDot"
				:style="{ backgroundColor: category.color }" />
			<label class="one-category-label-label">{{ category.icon || '' }}</label>
			<label class="one-category-label-label">{{ category.name }}</label>
			<input v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Edit') }"
				type="submit"
				value=""
				class="icon-rename editOneCategory icon"
				@click="onClickEdit">
			<input v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Delete') }"
				type="submit"
				value=""
				:class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneCategory icon'"
				@click="onClickDelete">
			<label v-if="timerOn"
				class="one-category-label-label">
				<vac :end-time="new Date().getTime() + (7000)">
					<template #process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
			</label>
		</div>
		<div v-if="editMode"
			class="one-category-edit">
			<ColorPicker ref="col"
				class="app-navigation-entry-bullet-wrapper"
				value=""
				@input="updateColor">
				<div
					v-tooltip.top="{ content: t('cospend', 'Color') }"
					:style="{ backgroundColor: color }"
					class="color0 icon-colorpicker" />
			</ColorPicker>
			<EmojiPicker :show-preview="true"
				@select="selectEmoji">
				<button
					v-tooltip.top="{ content: t('cospend', 'Icon') }"
					class="edit-icon-button"
					:title="t('cospend', 'Icon')">
					{{ icon }}
				</button>
			</EmojiPicker>
			<input ref="cname"
				v-model="name"
				type="text"
				maxlength="300"
				class="editCategoryNameInput"
				:placeholder="t('cospend', 'Category name')"
				@focus="$event.target.select()">
			<button
				v-tooltip.top="{ content: t('cospend', 'Cancel') }"
				class="editCategoryClose icon-history icon"
				@click="onClickCancel" />
			<button
				v-tooltip.top="{ content: t('cospend', 'Save') }"
				class="editCategoryOk icon-checkmark icon"
				@click="onClickEditOk" />
		</div>
	</div>
</template>

<script>
import { Timer } from '../utils'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'

export default {
	name: 'Category',

	components: {
		ColorPicker, EmojiPicker,
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
		draggable: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			editMode: false,
			timerOn: false,
			timer: null,
			// initial data
			color: this.category.color,
			name: this.category.name,
			icon: this.category.icon,
		}
	},

	computed: {
	},

	methods: {
		selectEmoji(emoji) {
			this.icon = emoji
		},
		updateColor(color) {
			this.color = color
		},
		onClickEdit() {
			this.editMode = true
			this.$nextTick(() => this.$refs.cname.focus())
		},
		onClickCancel() {
			this.editMode = false
			this.name = this.category.name
			this.color = this.category.color
			this.icon = this.category.icon
		},
		onClickDelete() {
			if (this.timerOn) {
				this.timerOn = false
				this.timer.pause()
				delete this.timer
			} else {
				this.timerOn = true
				this.timer = new Timer(() => {
					this.timerOn = false
					this.$emit('delete', this.category)
				}, 7000)
			}
		},
		onClickEditOk() {
			this.$emit('edit', this.category, this.name, this.icon, this.color)
			this.editMode = false
		},
	},
}
</script>

<style scoped lang="scss">
.one-category {
	display: flex;

	.icon-move {
		cursor: grab;
		width: 44px;
		height: 44px;
		border-radius: 50%;
		text-align: center;
		line-height: 44px;
		font-size: 25px;
		&:hover {
			background-color: black;
		}
		background-color: var(--color-main-text);
		padding: 0 !important;
		mask: url('./../../img/move.svg') no-repeat;
		mask-size: 18px 18px;
		mask-position: center;
		-webkit-mask: url('./../../img/move.svg') no-repeat;
		-webkit-mask-size: 18px 18px;
		-webkit-mask-position: center;
		min-width: 44px !important;
		min-height: 44px !important;
	}
}

.one-category-edit {
	flex-grow: 1;
	display: grid;
	grid-template: 1fr / 44px 44px 3fr 42px 42px;
	height: 40px;
	border-radius: 15px;
	background-color: var(--color-background-dark);
	margin-right: 15px;
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
	flex-grow: 1;
	display: grid;
	grid-template: 1fr / 1fr 1fr 6fr 42px 42px 15px;
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
	border-radius: var(--border-radius-pill);
	height: 40px;
	width: 40px;
	margin: 0;
	padding: 0;
}

.icon {
	border-radius: var(--border-radius-pill);
	opacity: .5;

	&.icon-delete,
	&.icon-rename,
	&.icon-history {
		background-color: transparent;
		border: none;
		margin: 0;
	}

	&:hover,
	&:focus {
		opacity: 1;
		background-color: var(--color-background-hover);
	}
}
</style>
