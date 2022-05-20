<template>
	<div class="activity-entry">
		<element :is="icon.component"
			class="icon-activity-entry"
			:style="icon.color ? 'color: ' + icon.color + ';' : ''"
			:size="16" />
		<span
			v-if="subjectRich"
			class="subject">
			<RichText
				:text="subjectRich"
				:arguments="subjectParameters" />
		</span>
		<span v-else>
			{{ activity.subject }}
		</span>
		<span
			v-tooltip.top="{ content: formattedTime }"
			class="time">
			{{ relativeTime }}
		</span>
	</div>
</template>

<script>

import PencilIcon from 'vue-material-design-icons/Pencil'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import PlusIcon from 'vue-material-design-icons/Plus'
import DeleteIcon from 'vue-material-design-icons/Delete'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { getCurrentUser } from '@nextcloud/auth'
import RichText from '@juliushaertl/vue-richtext'

import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'

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
		RichText,
		// eslint-disable-next-line
		UserBubble,
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
				params.user = {
					component: UserBubble,
					props: {
						user: this.activity.subject_rich[1]?.user?.id || undefined,
						displayName: this.userIsMe(this.activity.subject_rich[1]?.user?.id)
							? t('cospend', 'You')
							: this.activity.subject_rich[1]?.user?.name,
					},
				}
			}
			if (['project_share', 'project_unshare'].includes(this.activity.link)) {
				params.who = {
					component: UserBubble,
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

	.icon-activity-entry {
		min-width: 16px;
		min-height: 16px;
		opacity: 0.5;
		display: inline-block;
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

::v-deep .user-bubble__wrapper {
	margin-bottom: -2px;
}
</style>
