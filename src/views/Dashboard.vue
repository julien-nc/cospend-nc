<template>
	<NcDashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template #empty-content>
			<NcEmptyContent
				:name="t('cospend', 'No recent activity')"
				:title="t('cospend', 'No recent activity')">
				<template #icon>
					<CospendIcon />
				</template>
				<template #action>
					<a :href="cospendUrl">
						<NcButton>
							{{ t('cospend', 'Go to Cospend') }}
						</NcButton>
					</a>
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import CospendIcon from '../components/icons/CospendIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'

import NcDashboardWidget from '@nextcloud/vue/components/NcDashboardWidget'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'Dashboard',

	components: {
		CospendIcon,
		NcDashboardWidget,
		NcEmptyContent,
		NcButton,
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
			darkThemeColor: OCA.Accessibility?.theme === 'dark' ? '181818' : 'ffffff',
			windowVisibility: true,
		}
	},

	computed: {
		items() {
			return this.activities.map((a) => {
				return {
					id: a.activity_id,
					targetUrl: this.getTarget(a),
					// avatarUrl: this.getAvatarUrl(a),
					avatarUsername: this.getUserId(a),
					avatarIsNoUser: !a.user,
					// overlayIconUrl: a.icon,
					mainText: this.getMainText(a),
					subText: this.getSubline(a),
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

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
		launchLoop() {
			this.loadActivity()
			this.loop = setInterval(this.loadActivity, 60000)
		},
		async loadActivity() {
			if (!this.windowVisibility) {
				// Dashboard is not visible, so don't update the activity list
				return
			}
			// eslint-disable-next-line
			const params = new URLSearchParams()
			params.append('format', 'json')
			params.append('limit', 50)

			try {
				const response = await axios.get(generateOcsUrl('apps/activity/api/v2/activity') + '/cospend' + '?' + params)
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
// nothing
</style>
