<template>
	<div class="activity-entry">
		<component
			:is="icon.component"
			v-if="icon"
			class="activity-entry-icon"
			:style="icon.color ? 'color: ' + icon.color + ';' : ''"
			:size="16" />
		<span
			v-if="subjectRich"
			class="subject">
			<NcRichText
				:text="subjectRich"
				:arguments="subjectParameters" />
		</span>
		<span v-else>
			{{ activity.subject }}
		</span>
		<span
			:title="formattedTime"
			class="time">
			{{ relativeTime }}
		</span>
	</div>
</template>

<script>

import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import { NcRichText } from '@nextcloud/vue/components/NcRichText'

import moment from '@nextcloud/moment'
import { getCurrentUser } from '@nextcloud/auth'

const icons = {
	bill_update: {
		component: PencilIcon,
	},
	bill_delete: {
		component: DeleteIcon,
		color: '#E9322D',
	},
	bill_create: {
		component: PlusIcon,
		color: '#46BA61',
	},
	project_share: {
		component: ShareVariantIcon,
	},
	project_unshare: {
		component: ShareVariantIcon,
	},
}

export default {
	name: 'ActivityEntry',

	components: {
		NcRichText,
		ShareVariantIcon,
		PencilIcon,
		PlusIcon,
		DeleteIcon,
	},

	props: {
		activity: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
		}
	},

	computed: {
		icon() {
			return icons[this.activity.link]
		},
		relativeTime() {
			return moment(this.activity.datetime).fromNow()
		},
		formattedTime() {
			return moment(this.activity.datetime).format('LLL')
		},
		subjectRich() {
			const actionUserIsMe = this.userIsMe(this.activity.subject_rich[1]?.user?.id)
			if (this.activity.link === 'bill_update') {
				return actionUserIsMe
					? t('cospend', '{user} have updated the bill {billName}', { billName: this.activity.subject_rich[1]?.bill?.name })
					: t('cospend', '{user} has updated the bill {billName}', { billName: this.activity.subject_rich[1]?.bill?.name })
			} else if (this.activity.link === 'bill_create') {
				return actionUserIsMe
					? t('cospend', '{user} have created a new bill {billName}', { billName: this.activity.subject_rich[1]?.bill?.name })
					: t('cospend', '{user} has created a new bill {billName}', { billName: this.activity.subject_rich[1]?.bill?.name })
			} else if (this.activity.link === 'bill_delete') {
				return actionUserIsMe
					? t('cospend', '{user} have deleted the bill {billName}', { billName: this.activity.subject_rich[1]?.bill?.name })
					: t('cospend', '{user} has deleted the bill {billName}', { billName: this.activity.subject_rich[1]?.bill?.name })
			} else if (this.activity.link === 'project_share') {
				return actionUserIsMe
					? t('cospend', '{user} have shared the project with {who}')
					: t('cospend', '{user} has shared the project with {who}')
			} else if (this.activity.link === 'project_unshare') {
				return actionUserIsMe
					? t('cospend', '{user} have removed {who} from the project')
					: t('cospend', '{user} has removed {who} from the project')
			}
			return null
		},
		subjectParameters() {
			const params = {}
			if (['bill_update', 'bill_create', 'bill_delete', 'project_share', 'project_unshare'].includes(this.activity.link)) {
				const userId = this.activity.subject_rich[1]?.user?.id === '0'
					? undefined
					: this.activity.subject_rich[1]?.user?.id
				params.user = {
					component: NcUserBubble,
					props: {
						user: userId,
						displayName: this.userIsMe(this.activity.subject_rich[1]?.user?.id)
							? t('cospend', 'You')
							: this.activity.subject_rich[1]?.user?.name,
					},
				}
			}
			if (['project_share', 'project_unshare'].includes(this.activity.link)) {
				params.who = {
					component: NcUserBubble,
					props: {
						user: this.activity.subject_rich[1]?.who?.id || undefined,
						displayName: this.userIsMe(this.activity.subject_rich[1]?.who?.id)
							? t('cospend', 'you')
							: this.activity.subject_rich[1]?.who?.name,
					},
				}
			}
			return params
		},
	},

	mounted() {
	},

	methods: {
		userIsMe(userId) {
			return getCurrentUser().uid === userId
		},
	},
}
</script>

<style scoped lang="scss">
.activity-entry {
	display: flex;
	align-items: center;
	margin: 10px 0 10px 0;
	&:hover {
		background-color: var(--color-background-hover);
	}

	.activity-entry-icon {
		display: flex !important;
		min-width: 16px;
		min-height: 16px;
		opacity: 0.5;
		margin-right: 10px;
	}
	.subject {
		flex-grow: 1;
		width: -webkit-fill-available;
		width: -moz-available;
	}
	.time {
		width: 130px;
		color: var(--color-text-maxcontrast);
		font-size: 0.8em;
		text-align: right;
	}
}

:deep(.user-bubble__wrapper) {
	margin-bottom: -2px;
}
</style>
