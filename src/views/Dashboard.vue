<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template #empty-content>
			<EmptyContent
				icon="icon-cospend">
				<template #desc>
					{{ t('cospend', 'No recent activity') }}
					<div class="empty-content-button">
						<a class="button" :href="cospendUrl">
							{{ t('cospend', 'Go to Cospend') }}
						</a>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			activities: [],
			showMoreUrl: generateUrl('/apps/activity') + '?filter=cospend',
			cospendUrl: generateUrl('/apps/cospend'),
			loop: null,
			state: 'loading',
			darkThemeColor: OCA.Accessibility.theme === 'dark' ? '181818' : 'ffffff',
		}
	},

	computed: {
		items() {
			return this.activities.map((a) => {
				return {
					id: a.activity_id,
					targetUrl: this.getTarget(a),
					// avatarUrl: this.getSimpleAvatarUrl(n),
					avatarUsername: this.getUserId(a),
					mainText: this.getMainText(a),
					subText: this.getSubline(a),
					// overlayIconUrl: a.icon,
				}
			})
		},
		lastDate() {
			const nbItems = this.activities.length
			return (nbItems > 0) ? this.activities[0].datetime : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		lastActivityId() {
			const nbItems = this.activities.length
			return (nbItems > 0) ? this.activities[0].activity_id : 0
		},
	},

	beforeMount() {
		this.loadActivity()
		this.loop = setInterval(() => this.loadActivity(), 60000)
	},

	mounted() {
	},

	methods: {
		async loadActivity() {
			// eslint-disable-next-line
			const params = new URLSearchParams()
			params.append('format', 'json')
			params.append('limit', 50)

			try {
				const response = await axios.get(generateOcsUrl('apps/activity/api/v2/activity') + 'cospend' + '?' + params)
				this.processActivities(response.data.ocs.data)
				this.state = 'ok'
			} catch (error) {
				this.state = 'error'
			}
		},
		processActivities(newActivities) {
			if (this.lastActivityId) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newActivities.length && this.lastActivityId < newActivities[i].activity_id) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filterActivities(newActivities.slice(0, i))
					this.activities = toAdd.concat(this.activities)
				}
			} else {
				// first time we don't check the date
				this.activities = this.filterActivities(newActivities)
			}
		},
		filterActivities(activities) {
			return activities.filter((a) => {
				return a.user !== getCurrentUser().uid
			})
		},
		getUserId(a) {
			return a.user
		},
		getTarget(a) {
			const projectId = a.subject_rich[1].project.id
			return generateUrl('/apps/cospend?project=' + projectId)
		},
		getSubline(a) {
			const projectName = a.subject_rich[1].project.name
			let char
			if (a.link === 'project_share') {
				char = '🔗'
			} else if (a.link === 'project_unshare') {
				char = '🛇'
			} else if (a.link === 'bill_create') {
				char = '➕'
			} else if (a.link === 'bill_delete') {
				char = '🗑️'
			} else if (a.link === 'bill_update') {
				char = '️✏️'
			}
			return char + ' ' + projectName
		},
		getMainText(a) {
			if (a.link === 'project_share') {
				const projectName = a.subject_rich[1].project.name
				const userName = a.subject_rich[1].user.name
				const whoName = a.subject_rich[1].who.name
				return t('cospend', '{user} shared {project} with {who}', { user: userName, project: projectName, who: whoName })
			} else if (a.link === 'project_unshare') {
				const projectName = a.subject_rich[1].project.name
				const userName = a.subject_rich[1].user.name
				const whoName = a.subject_rich[1].who.name
				return t('cospend', '{user} unshared {project} with {who}', { user: userName, project: projectName, who: whoName })
			} else if (['bill_create', 'bill_delete', 'bill_update'].includes(a.link)) {
				const userName = a.subject_rich[1].user.name
				const billName = a.subject_rich[1].bill.name
				if (a.link === 'bill_create') {
					return t('cospend', '{user} created {bill}', { user: userName, bill: billName })
				} else if (a.link === 'bill_delete') {
					return t('cospend', '{user} deleted {bill}', { user: userName, bill: billName })
				} else if (a.link === 'bill_update') {
					return t('cospend', '{user} edited {bill}', { user: userName, bill: billName })
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .empty-content-button {
	margin-top: 10px;
}
</style>
