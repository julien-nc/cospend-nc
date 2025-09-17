<template>
	<div class="activity-tab">
		<ActivityEntry
			v-for="a in activities"
			:key="a.activity_id"
			:activity="a" />
		<!--InfiniteLoading v-if="activities.length >= 50"
			:identifier="projectId"
			@infinite="infiniteHandler">
			<template #no-results>
				{{ t('cospend', 'No more activity') }}
			</template>
			<template #no-more>
				{{ t('cospend', 'No more activity') }}
			</template>
		</InfiniteLoading-->
	</div>
</template>

<script>
// import InfiniteLoading from 'vue-infinite-loading'

import ActivityEntry from './ActivityEntry.vue'

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ActivityTabSidebar',

	components: {
		// InfiniteLoading,
		ActivityEntry,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			activities: [],
		}
	},

	computed: {
	},

	watch: {
		projectId() {
			this.activities = []
			this.getActivity()
		},
	},

	mounted() {
		this.getActivity()
	},

	methods: {
		getActivity(since = null, state = null) {
			// eslint-disable-next-line
			const params = new URLSearchParams()
			params.append('format', 'json')
			params.append('limit', 50)
			if (since) {
				params.append('since', since)
			}
			const requestProjectId = this.projectId
			axios.get(generateOcsUrl('apps/activity/api/v2/activity') + '/cospend' + '?' + params).then((response) => {
				if (this.projectId !== requestProjectId) {
					console.debug('Stopping activity request processing because project ID has changed during a request')
					return
				}
				const allNewActivities = response.data.ocs.data
				const newFilteredActivities = this.filter(response.data.ocs.data)
				if (state) {
					if (newFilteredActivities.length > 0) {
						state.loaded()
					}
					if (allNewActivities.length === 0) {
						state.complete()
					}
				}
				if (newFilteredActivities.length > 0) {
					this.activities.push(...newFilteredActivities)
				}
				// if we got something but either
				// - nothing related to current project
				// - OR we still don't have 50 good ones
				// then get more entries
				if (allNewActivities.length > 0
					&& (newFilteredActivities.length === 0 || this.activities.length < 50)
				) {
					const newSince = allNewActivities[allNewActivities.length - 1].activity_id
					this.getActivity(newSince, state)
				}
			}).catch((error) => {
				if (state && error.response?.status === 304) {
					state.complete()
				} else if (error.response?.status !== 304) {
					console.error(error)
				}
			})
		},
		filter(activities) {
			return activities.filter((a) => {
				return (a.object_type === 'cospend_project' && a.object_name === this.projectId)
					|| (a.object_type === 'cospend_bill' && a.subject_rich[1]?.project?.id === this.projectId)
			})
		},
		infiniteHandler($state) {
			const since = this.activities.length > 0
				? this.activities[this.activities.length - 1].activity_id
				: null
			this.getActivity(since, $state)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
