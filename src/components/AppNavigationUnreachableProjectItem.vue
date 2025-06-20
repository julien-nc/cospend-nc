<template>
	<NcAppNavigationItem
		:name="name"
		:title="title"
		:allow-collapse="false"
		:force-display-actions="true"
		:force-menu="true"
		:menu-open="menuOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen"
		@click="onProjectClick">
		<template #icon>
			<HelpNetworkOutlineIcon
				class="icon"
				:size="20" />
		</template>
		<template #actions>
			<NcActionButton
				:close-after-click="true"
				@click="onLeaveShareClick">
				<template #icon>
					<CloseNetworkIcon :size="20" />
				</template>
				{{ t('cospend', 'Leave share') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import HelpNetworkOutlineIcon from 'vue-material-design-icons/HelpNetworkOutline.vue'
import CloseNetworkIcon from 'vue-material-design-icons/CloseNetwork.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'

import { emit } from '@nextcloud/event-bus'
import * as network from '../network.js'

export default {
	name: 'AppNavigationUnreachableProjectItem',
	components: {
		NcAppNavigationItem,
		NcActionButton,
		CloseNetworkIcon,
		HelpNetworkOutlineIcon,
	},
	props: {
		invite: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			menuOpen: false,
		}
	},
	computed: {
		name() {
			return this.invite.remoteProjectName
		},
		title() {
			return t('cospend', 'Project {projectName} ({projectId}) shared by {displayName} ({cloudId})', {
				projectId: this.invite.remoteProjectId,
				projectName: this.invite.remoteProjectName,
				cloudId: this.invite.inviterCloudId,
				displayName: this.invite.inviterDisplayName,
			})
		},
	},
	beforeMount() {
	},
	methods: {
		onProjectClick() {
		},
		onLeaveShareClick() {
			network.rejectInvitation(this.invite.id)
				.then(response => {
				})
				.catch(error => {
					console.error(error)
				})
				.then(() => {
					this.$nextTick(() => {
						emit('remove-unreachable-project', this.invite.id)
					})
				})
		},
		onUpdateMenuOpen(isOpen) {
			this.menuOpen = isOpen
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
