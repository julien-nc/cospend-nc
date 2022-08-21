<template>
	<div class="one-element">
		<CursorMoveIcon v-if="draggable"
			class="icon move-icon"
			:size="20" />
		<div v-show="!editMode"
			class="one-element-label">
			<div class="colorDot"
				:style="{ backgroundColor: element.color }" />
			<label class="one-element-label-label">{{ element.icon || '' }}</label>
			<label class="one-element-label-label label-label">{{ element.name }}</label>
			<NcButton v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Edit') }"
				@click="onClickEdit">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
			</NcButton>
			<NcButton v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Delete') }"
				class="deleteItemButton"
				@click="onClickDelete">
				<template #icon>
					<UndoIcon v-if="timerOn" :size="20" />
					<DeleteIcon v-else class="deleteItem" :size="20" />
				</template>
			</NcButton>
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
				<NcButton
					v-tooltip.top="{ content: t('cospend', 'Color') }"
					:style="{ backgroundColor: color }">
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
				</NcButton>
			</ColorPicker>
			<EmojiPicker :show-preview="true"
				@select="selectEmoji">
				<NcButton
					v-tooltip.top="{ content: t('cospend', 'Icon') }"
					class="emojiButton">
					{{ icon }}
				</NcButton>
			</EmojiPicker>
			<input ref="cname"
				v-model="name"
				type="text"
				maxlength="300"
				class="editElementNameInput"
				:placeholder="t('cospend', 'Name')"
				@focus="$event.target.select()">
			<NcButton
				v-tooltip.top="{ content: t('cospend', 'Cancel') }"
				@click="onClickCancel">
				<template #icon>
					<UndoIcon :size="20" />
				</template>
			</NcButton>
			<NcButton
				v-tooltip.top="{ content: t('cospend', 'Save') }"
				type="primary"
				@click="onClickEditOk">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import CursorMoveIcon from 'vue-material-design-icons/CursorMove.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import { Timer } from '../utils.js'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker.js'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker.js'

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
		CursorMoveIcon,
		NcButton,
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

	.move-icon {
		margin-right: 12px;
		cursor: grab;
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
