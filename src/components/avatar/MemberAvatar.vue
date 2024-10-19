<template>
	<CospendTogglableAvatar
		v-bind="$attrs"
		:enabled="member.activated"
		:color="member.color || ''"
		:is-no-user="isNoUser"
		:user="member.userid || ''"
		:display-name="member.name || ''" />
</template>

<script>
import CospendTogglableAvatar from './CospendTogglableAvatar.vue'

export default {
	name: 'MemberAvatar',

	components: {
		CospendTogglableAvatar,
	},

	inject: [
		'isCurrentProjectFederated',
	],

	props: {
		member: {
			type: Object,
			required: true,
		},
		/**
		 * For the special case of member nav item that is displayed even if its project is not the selected one
		 * So we need to avoid treating the member as a user independently of the current selected project
		 * The alternative would be to pass the project to all MemberAvatar but it would be heavy
		 */
		forceIsNoUser: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		isNoUser() {
			return this.forceIsNoUser || this.isCurrentProjectFederated() || !!this.member.userid === false
		},
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
