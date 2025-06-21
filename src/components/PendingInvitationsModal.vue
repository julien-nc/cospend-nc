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
					@click="reject(invite)">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('cospend', 'Reject') }}
				</NcButton>
				<NcButton type="success"
					@click="accept(invite)">
					<template #icon>
						<CheckIcon />
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
import NcModal from '@nextcloud/vue/components/NcModal'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import { emit } from '@nextcloud/event-bus'

import * as network from '../network.js'

export default {
	name: 'PendingInvitationsModal',
	components: {
		NcButton,
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
			console.debug('[cospend] get invite label', invite)
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
			network.acceptPendingInvitation(invite.id).then(response => {
				emit('add-project', response.data.ocs.data)
				this.$emit('close')
				this.$nextTick(() => {
					emit('remove-pending-invitation', invite.id)
					emit('project-clicked', response.data.ocs.data.id)
				})
			})
		},
		reject(invite) {
			network.rejectInvitation(invite.id).then(response => {
				this.$emit('close')
				this.$nextTick(() => {
					emit('remove-pending-invitation', invite.id)
				})
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
