<template>
	<NcModal
		:name="t('cospend', 'Pending remote invitations')"
		size="large"
		@close="$emit('close')">
		<div class="pending-modal-content">
			<h2>{{ t('cospend', 'Pending remote invitations') }}</h2>
			<div v-for="invite in invitations"
				:key="invite.id"
				:title="getTitle(invite)"
				class="invite">
				<span>
					{{ getLabel(invite) }}
				</span>
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import { emit } from '@nextcloud/event-bus'

import * as network from '../network.js'

export default {
	name: 'PendingInvitationsModal',
	components: {
		NcButton,
		NcModal,
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
			network.acceptPendingInvitation(invite.id).then(response => {
				emit('add-project', response.data.ocs.data)
			})
		},
		reject(invite) {
			network.rejectPendingInvitation(invite.id).then(response => {
				emit('delete-invitation', invite.id)
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
