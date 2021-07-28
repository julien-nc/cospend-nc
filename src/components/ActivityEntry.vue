<template>
	<div class="activity-entry">
		<span class="icon-activity-entry"
			:style="{ 'background-image': 'url(\'' + iconUrl + '\')' }" />
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
		<span class="time">
			{{ relativeTime }}
		</span>
	</div>
</template>

<script>

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { getCurrentUser } from '@nextcloud/auth'
import RichText from '@juliushaertl/vue-richtext'

import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'

const isDarkTheme = OCA.Accessibility.theme === 'dark'
const icons = {
	bill_update: generateUrl('/svg/core/actions/rename?color=' + (isDarkTheme ? 'FFFFFF' : '000000')),
	bill_delete: generateUrl('/svg/core/actions/delete?color=E9322D'),
	bill_create: generateUrl('/svg/core/actions/add?color=46BA61'),
	project_share: generateUrl('/svg/core/actions/share?color=' + (isDarkTheme ? 'FFFFFF' : '000000')),
	project_unshare: generateUrl('/svg/core/actions/share?color=' + (isDarkTheme ? 'FFFFFF' : '000000')),
}

export default {
	name: 'ActivityEntry',

	components: {
		RichText,
		// eslint-disable-next-line
		UserBubble,
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
		iconUrl() {
			return icons[this.activity.link] ?? ''
		},
		relativeTime() {
			return moment(this.activity.datetime).fromNow()
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
	margin: 8px 0 8px 0;
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
</style>
