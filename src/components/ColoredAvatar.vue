<template>
	<Avatar v-if="showMe"
		class="avatar"
		:is-no-user="isNoUser"
		:show-user-status="showUserStatus"
		:style="cssVars"
		v-bind="$attrs" />
	<div v-else />
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar.js'

export default {
	name: 'ColoredAvatar',

	components: {
		Avatar,
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
			}
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
.avatar {
	background-color: var(--member-bg-color) !important;
}
</style>
