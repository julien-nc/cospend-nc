<template>
	<div class="activity-entry">
		<span class="icon-activity-entry"
			:style="{ 'background-image': 'url(\'' + iconUrl + '\')' }" />
		<span class="subject">
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
	},

	mounted() {
		console.debug(this.activity.icon)
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.activity-entry {
	display: flex;
	align-items: center;
	margin: 5px 0 5px 0;
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
	}
	.time {
		width: 130px;
		color: var(--color-text-maxcontrast);
		font-size: 0.8em;
		text-align: right;
	}
}
</style>
