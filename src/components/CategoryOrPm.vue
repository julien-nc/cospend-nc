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
				:title="t('cospend', 'Edit')"
				:aria-label="t('cospend', 'Edit')"
				@click="onClickEdit">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
			</NcButton>
			<NcButton v-show="editionAccess"
				:title="t('cospend', 'Delete')"
				:aria-label="t('cospend', 'Delete')"
				class="deleteItemButton"
				@click="onClickDelete">
				<template #icon>
					<UndoIcon v-if="timerOn" :size="20" />
					<DeleteIcon v-else class="deleteItem" :size="20" />
				</template>
			</NcButton>
			<label v-if="timerOn"
				class="one-element-label-timer">
				<Countdown :duration="7" />
			</label>
		</div>
		<div v-if="editMode"
			class="one-element-edit">
			<NcColorPicker
				class="app-navigation-entry-bullet-wrapper"
				:model-value="''"
				@update:model-value="updateColor">
				<NcButton
					:title="t('cospend', 'Color')"
					:aria-label="t('cospend', 'Color')"
					:style="{ backgroundColor: color }">
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
				</NcButton>
			</NcColorPicker>
			<NcEmojiPicker :show-preview="true"
				@select="selectEmoji">
				<NcButton
					:title="t('cospend', 'Icon')"
					:aria-label="t('cospend', 'Icon')"
					class="emojiButton">
					{{ icon }}
				</NcButton>
			</NcEmojiPicker>
			<input ref="cname"
				v-model="name"
				type="text"
				maxlength="300"
				class="editElementNameInput"
				:placeholder="t('cospend', 'Name')"
				@focus="$event.target.select()">
			<NcButton
				:title="t('cospend', 'Cancel')"
				:aria-label="t('cospend', 'Cancel')"
				@click="onClickCancel">
				<template #icon>
					<UndoIcon :size="20" />
				</template>
			</NcButton>
			<NcButton
				:title="t('cospend', 'Save')"
				:aria-label="t('cospend', 'Save')"
				variant="primary"
				@click="onClickEditOk">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import CursorMoveIcon from 'vue-material-design-icons/CursorMove.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'

import Countdown from './Countdown.vue'

import { Timer } from '../utils.js'

export default {
	name: 'CategoryOrPm',

	components: {
		Countdown,
		NcColorPicker,
		NcEmojiPicker,
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
	position: relative;
	flex-grow: 1;
	display: flex;
	align-items: center;
	margin-right: 20px;
	padding: 4px 0 4px 0;
	> * {
		margin: 0 4px 0 4px;
	}
	.label-label {
		flex-grow: 1;
	}
	.one-element-label-timer {
		position: absolute;
		right: -20px;
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

:deep(.deleteItemButton:hover) {
	.delete-icon {
		color: var(--color-error);
	}
}

:deep(.emojiButton *) {
	margin: 0 !important;
	margin-left: 0 !important;
	margin-right: 0 !important;
}
</style>
