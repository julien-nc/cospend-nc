<template>
	<div class="togglable-avatar"
		:style="cssVars">
		<ColoredAvatar
			:size="size"
			:color="color"
			:is-no-user="isNoUser"
			v-bind="$attrs" />
		<DisabledMaskIcon v-show="!enabled"
			class="disabled-icon"
			:size="size + 2"
			:fill-color="maskColor" />
	</div>
</template>

<script>
import ColoredAvatar from './ColoredAvatar.vue'
import DisabledMaskIcon from '../icons/DisabledMaskIcon.vue'

import { getColorBrightness, hexToRgb } from '../../utils.js'

export default {
	name: 'CospendTogglableAvatar',

	components: {
		DisabledMaskIcon,
		ColoredAvatar,
	},

	props: {
		size: {
			type: Number,
			default: 32,
		},
		color: {
			type: String,
			default: '',
		},
		isNoUser: {
			type: Boolean,
			default: false,
		},
		enabled: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		cssVars() {
			return {
				'--container-size': this.size + 'px',
			}
		},
		maskColor() {
			if (this.color && this.isNoUser) {
				const rgb = hexToRgb(this.color)
				const colorBrightness = getColorBrightness(rgb)
				return colorBrightness > 80
					? 'black'
					: 'white'
			}
			return 'gray'
		},
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.togglable-avatar {
	display: flex;
	position: relative;
	width: var(--container-size);
	height: var(--container-size);

	.disabled-icon {
		position: absolute;
		top: -1px;
		left: -1px;
	}
}
</style>
