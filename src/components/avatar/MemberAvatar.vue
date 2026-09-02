<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
