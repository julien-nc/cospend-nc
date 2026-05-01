<template>
	<NcModal
		:name="t('cospend', 'Pending invitations for federated shares')"
		size="large"
		@close="$emit('close')">
		<div class="pending-modal-content">
			<h2>{{ t('cospend', 'Pending invitations for federated shares') }}</h2>
			<div v-for="invite in invitations"
				:key="invite.id"
				:title="getTitle(invite)"
				class="invite">
				<span>
					{{ getLabel(invite) }}
				</span>
				<NcAvatar
					:url="getRemoteAvatarUrl(invite.inviterCloudId)"
					:is-no-user="true"
					:hide-status="true"
					:disable-menu="true"
					:disable-tooltip="true" />
				<div class="spacer" />
				<NcButton type="error"
					:disabled="!!loadingId"
					@click="reject(invite)">
					<template #icon>
						<NcLoadingIcon v-if="loadingId === 'reject-' + invite.id" />
						<CloseIcon v-else />
					</template>
					{{ t('cospend', 'Reject') }}
				</NcButton>
				<NcButton type="success"
					:disabled="!!loadingId"
					@click="accept(invite)">
					<template #icon>
						<NcLoadingIcon v-if="loadingId === 'accept-' + invite.id" />
						<CheckIcon v-else />
					</template>
					{{ t('cospend', 'Accept') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'

import * as network from '../network.js'

export default {
	name: 'PendingInvitationsModal',
	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcAvatar,
		CheckIcon,
		CloseIcon,
	},
	props: {
		invitations: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			loadingId: null,
		}
	},
	computed: {
	},
	beforeMount() {
	},
	mounted() {
	},
	methods: {
		getRemoteAvatarUrl(cloudId) {
			return network.getRemoteAvatarUrl(cloudId)
		},
		getLabel(invite) {
			return t('cospend', 'Project {projectName} shared by {inviterName} ({inviterCloudId})', {
				projectName: invite.remoteProjectName,
				inviterName: invite.inviterDisplayName,
				inviterCloudId: invite.inviterCloudId,
			})
		},
		getTitle(invite) {
			return invite.remoteProjectId + '@' + invite.remoteServerUrl
		},
		accept(invite) {
			this.loadingId = 'accept-' + invite.id
			network.acceptPendingInvitation(invite.id).then(response => {
				emit('add-project', response.data.ocs.data)
				this.$emit('close')
				this.$nextTick(() => {
					emit('remove-pending-invitation', invite.id)
					emit('project-clicked', response.data.ocs.data.id)
				})
			}).catch(() => {
				showError(t('cospend', 'Failed to accept federated share invitation'))
			}).then(() => {
				this.loadingId = null
			})
		},
		reject(invite) {
			this.loadingId = 'reject-' + invite.id
			network.rejectInvitation(invite.id).then(() => {
				this.$emit('close')
				this.$nextTick(() => {
					emit('remove-pending-invitation', invite.id)
				})
			}).catch(() => {
				showError(t('cospend', 'Failed to reject federated share invitation'))
			}).then(() => {
				this.loadingId = null
			})
		},
	},
}
</script>
<style scoped lang="scss">
.pending-modal-content {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 16px;

	h2 {
		margin-top: 0;
	}
	.invite {
		display: flex;
		gap: 8px;
		align-items: center;
		padding: 4px;
		&:hover {
			background-color: var(--color-background-hover);
		}
		.spacer {
			flex-grow: 1;
		}
	}
}
</style>
