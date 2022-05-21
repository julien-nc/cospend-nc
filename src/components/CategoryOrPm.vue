<template>
	<div class="one-element">
		<span v-if="draggable"
			class="icon icon-move" />
		<div v-show="!editMode"
			class="one-element-label">
			<div class="colorDot"
				:style="{ backgroundColor: element.color }" />
			<label class="one-element-label-label">{{ element.icon || '' }}</label>
			<label class="one-element-label-label label-label">{{ element.name }}</label>
			<Button v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Edit') }"
				@click="onClickEdit">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
			</Button>
			<Button v-show="editionAccess"
				class="deleteItemButton"
				v-tooltip.top="{ content: t('cospend', 'Delete') }"
				@click="onClickDelete">
				<template #icon>
					<UndoIcon v-if="timerOn" :size="20" />
					<DeleteIcon v-else class="deleteItem" :size="20" />
				</template>
			</Button>
			<label v-if="timerOn"
				class="one-element-label-timer">
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
				<Button
					v-tooltip.top="{ content: t('cospend', 'Color') }"
					:style="{ backgroundColor: color }">
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
				</Button>
			</ColorPicker>
			<EmojiPicker :show-preview="true"
				@select="selectEmoji">
				<Button class="emojiButton"
						v-tooltip.top="{ content: t('cospend', 'Icon') }">
					{{ icon }}
				</Button>
			</EmojiPicker>
			<input ref="cname"
				v-model="name"
				type="text"
				maxlength="300"
				class="editElementNameInput"
				:placeholder="t('cospend', 'Name')"
				@focus="$event.target.select()">
			<Button
				v-tooltip.top="{ content: t('cospend', 'Cancel') }"
				@click="onClickCancel">
				<template #icon>
					<UndoIcon :size="20" />
				</template>
			</Button>
			<Button
				type="primary"
				v-tooltip.top="{ content: t('cospend', 'Save') }"
				@click="onClickEditOk">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
			</Button>
		</div>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import PaletteIcon from 'vue-material-design-icons/Palette'
import PencilIcon from 'vue-material-design-icons/Pencil'
import UndoIcon from 'vue-material-design-icons/Undo'
import CheckIcon from 'vue-material-design-icons/Check'
import DeleteIcon from 'vue-material-design-icons/Delete'
import { Timer } from '../utils'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'

export default {
	name: 'CategoryOrPm',

	components: {
		ColorPicker,
		EmojiPicker,
		PaletteIcon,
		PencilIcon,
		DeleteIcon,
		UndoIcon,
		CheckIcon,
		Button,
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
		height: 52px;
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
	display: flex;
	align-items: center;
	padding: 4px 0 4px 0;
	border-radius: 15px;
	background-color: var(--color-background-dark);
	margin-right: 20px;
	.editElementNameInput {
		flex-grow: 1;
	}
	> * {
		margin: 0 4px 0 4px;
	}
}

.one-element-label {
	flex-grow: 1;
	display: flex;
	align-items: center;
	padding: 4px 0 4px 0;
	margin-right: 20px;
	> * {
		margin: 0 4px 0 4px;
	}
	.label-label {
		flex-grow: 1;
	}
	.one-element-label-timer {
		position: absolute;
		right: -5px;
	}
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
}

.edit-icon-button {
	border-radius: var(--border-radius-pill);
	height: 40px;
	width: 40px;
	margin: 0;
	padding: 0;
}

::v-deep .deleteItemButton:hover {
	.delete-icon {
		color: var(--color-error);
	}
}

::v-deep .emojiButton * {
	margin: 0 !important;
	margin-left: 0 !important;
	margin-right: 0 !important;
}
</style>
