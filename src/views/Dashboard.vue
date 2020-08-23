<template>
	<DashboardWidget :items="items"
		:showMoreUrl="showMoreUrl"
		:showMoreText="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<a :href="showMoreUrl">
				{{ t('cospend', 'There has been no bills in the last 2 weeks.') }}
			</a>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { rgbObjToHex } from '../utils'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			showMoreUrl: generateUrl('/apps/cospend'),
			loop: null,
			state: 'loading',
			darkThemeColor: OCA.Accessibility.theme === 'dark' ? '181818' : 'ffffff',
		}
	},

	computed: {
		items() {
			return this.notifications.map((n) => {
				return {
					id: n.id,
					targetUrl: this.getBillTarget(n),
					avatarUrl: this.getSimpleAvatarUrl(n),
					avatarUsername: this.getUserId(n),
					mainText: n.what,
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbItems = this.notifications.length
            return (nbItems > 0) ? this.notifications[0].timestamp : null
		},
		lastMoment() {
			return moment.unix(this.lastDate)
		},
	},

	beforeMount() {
		this.fetchNotifications()
		this.loop = setInterval(() => this.fetchNotifications(), 60000)
	},

	mounted() {
	},

	methods: {
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/cospend/bill-activity'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				showError(t('cospend', 'Failed to get Cospend activity.'))
				this.state = 'error'
				console.debug(error)
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastDate < newNotifications[i].timestamp) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			return notifications
		},
		getSimpleAvatarUrl(n) {
			if (n.payer.userid) {
				return undefined
			}
			const color = rgbObjToHex(n.payer.color).replace('#', '')
			return generateUrl('/apps/cospend/getAvatar?color=' + color + '&name=' + encodeURIComponent(n.payer.name))
		},
		getUserId(n) {
			if (n.payer.userid) {
				return n.payer.userid
			}
			return undefined
		},
		getBillTarget(n) {
			return generateUrl('/apps/cospend?project=' + n.project_id)
		},
		getSubline(n) {
			return '[' + n.project_name + '] ' + n.payer.name
		},
		getFormattedDate(n) {
			return moment(n.updated_at).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
</style>
