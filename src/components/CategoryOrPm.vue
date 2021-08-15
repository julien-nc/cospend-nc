<template>
	<div class="one-element">
		<span v-if="draggable"
			class="icon icon-move" />
		<div v-show="!editMode"
			class="one-element-label">
			<div class="colorDot"
				:style="{ backgroundColor: element.color }" />
			<label class="one-element-label-label">{{ element.icon || '' }}</label>
			<label class="one-element-label-label">{{ element.name }}</label>
			<input v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Edit') }"
				type="submit"
				value=""
				class="icon-rename editOneElement icon"
				@click="onClickEdit">
			<input v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Delete') }"
				type="submit"
				value=""
				:class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneElement icon'"
				@click="onClickDelete">
			<label v-if="timerOn"
				class="one-element-label-label">
				<vac :end-time="new Date().getTime() + (7000)">
					<template #process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
			</label>
		</div>
		<div v-if="editMode"
			class="one-element-edit">
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
				class="editElementNameInput"
				:placeholder="t('cospend', 'Name')"
				@focus="$event.target.select()">
			<button
				v-tooltip.top="{ content: t('cospend', 'Cancel') }"
				class="editElementClose icon-history icon"
				@click="onClickCancel" />
			<button
				v-tooltip.top="{ content: t('cospend', 'Save') }"
				class="editElementOk icon-checkmark icon"
				@click="onClickEditOk" />
		</div>
	</div>
</template>

<script>
import { Timer } from '../utils'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'

export default {
	name: 'CategoryOrPm',

	components: {
		ColorPicker, EmojiPicker,
	},

	props: {
		element: {
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
			color: this.element.color,
			name: this.element.name,
			icon: this.element.icon,
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
			this.name = this.element.name
			this.color = this.element.color
			this.icon = this.element.icon
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
					this.$emit('delete', this.element)
				}, 7000)
			}
		},
		onClickEditOk() {
			this.$emit('edit', this.element, this.name, this.icon, this.color)
			this.editMode = false
		},
	},
}
</script>

<style scoped lang="scss">
.one-element {
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

.one-element-edit {
	flex-grow: 1;
	display: grid;
	grid-template: 1fr / 1fr 1fr 6fr 42px 42px;
	height: 40px;
	border-radius: 15px;
	background-color: var(--color-background-dark);
	margin-right: 20px;
}

.one-element-edit label,
.one-element-label label {
	line-height: 40px;
}

.one-element-label input[type=submit] {
	border-radius: 50% !important;
	width: 40px !important;
	height: 40px;
	margin-top: 0px;
}

.one-element-label {
	flex-grow: 1;
	display: grid;
	grid-template: 1fr / 1fr 1fr 6fr 42px 42px 20px;
}

.editElementOk,
.editElementClose {
	margin-top: 0px;
	height: 40px;
}

.editElementOk {
	background-color: #46ba61;
	color: white;
}

.one-element-label-icon {
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
