<template>
	<Avatar
		class="avatar"
		:style="cssVars"
		v-bind="$attrs" />
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'

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
	},

	data() {
		return {
		}
	},

	computed: {
		cssVars() {
			return {
				'--member-bg-color': '#' + this.color,
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
