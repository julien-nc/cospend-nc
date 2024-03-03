<template>
	<NcAvatar v-if="showMe"
		class="colored-avatar"
		:is-no-user="isNoUser"
		:show-user-status="showUserStatus"
		:display-name="displayName"
		:style="cssVars"
		v-bind="$attrs">
		<template v-if="displayName && isNoUser" #icon>
			<div class="initials-avatar">
				{{ initials }}
			</div>
		</template>
	</NcAvatar>
	<div v-else />
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import { getColorBrightness, hexToRgb } from '../../utils.js'

export default {
	name: 'ColoredAvatar',

	components: {
		NcAvatar,
	},

	props: {
		color: {
			type: String,
			default: '',
		},
		isNoUser: {
			type: Boolean,
			default: false,
		},
		showUserStatus: {
			type: Boolean,
			default: true,
		},
		displayName: {
			type: String,
			default: undefined,
		},
	},

	data() {
		return {
			showMe: true,
		}
	},

	computed: {
		cssVars() {
			return {
				'--member-bg-color': '#' + this.color,
				'--member-text-color': this.textColor,
			}
		},
		textColor() {
			if (this.color && this.isNoUser) {
				const rgb = hexToRgb(this.color)
				const colorBrightness = getColorBrightness(rgb)
				return colorBrightness > 80
					? 'black'
					: 'white'
			}
			return 'gray'
		},
		initials() {
			if (this.displayName) {
				const parts = this.displayName.split(/\s+/)
				const initials = parts.length > 1
					? parts[0][0] + parts[1][0]
					: parts.length > 0
						? parts[0][0]
						: '?'
				return initials.toUpperCase()
			}
			return '?'
		},
	},

	watch: {
		isNoUser(val) {
			// trick to re-render the avatar in case isNoUser changes
			// re-render only if we show the user status (which is what's not rendered correctly)
			if (this.showUserStatus) {
				this.showMe = false
				this.$nextTick(() => {
					this.showMe = true
				})
			}
		},
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.initials-avatar {
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background-color: var(--member-bg-color) !important;
	color: var(--member-text-color) !important;
	position: absolute;
	left: 0;
	width: 100%;
}
</style>
