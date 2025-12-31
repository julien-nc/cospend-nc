<template>
	<div class="activity-entry">
		<img v-if="activity.icon"
			:src="activity.icon"
			class="activity-entry-icon"
			:class="{ bw: !hasColoredIcon }">
		<span
			v-if="activity.subject_rich[0]"
			class="subject">
			<NcRichText
				:text="message.subject"
				:arguments="message.parameters" />
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
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import { NcRichText } from '@nextcloud/vue/components/NcRichText'

import ActivityHighlight from './ActivityHighlight.vue'

import moment from '@nextcloud/moment'

export default {
	name: 'ActivityEntry',

	components: {
		NcRichText,
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
		hasColoredIcon() {
			const icon = this.activity.icon
			if (icon) {
				return icon.endsWith('-color.svg')
			}
			return false
		},
		relativeTime() {
			return moment(this.activity.datetime).fromNow()
		},
		formattedTime() {
			return moment(this.activity.datetime).format('LLL')
		},
		message() {
			const subject = this.activity.subject_rich[0]
			const parameters = JSON.parse(JSON.stringify(this.activity.subject_rich[1]))
			if (parameters.after && typeof parameters.after.id === 'string' && parameters.after.id.startsWith('dt:')) {
				const dateTime = parameters.after.id.slice(3)
				parameters.after.name = moment(dateTime).format('L LTS')
			}

			Object.keys(parameters).forEach((key, index) => {
				const { type } = parameters[key]
				switch (type) {
				case 'highlight':
					parameters[key] = {
						component: ActivityHighlight,
						props: {
							href: parameters[key].link,
							name: parameters[key].name,
						},
					}
					break
				case 'user':
					parameters[key] = {
						component: NcUserBubble,
						props: {
							user: parameters[key].id,
							displayName: parameters[key].name,
						},
					}
					break
				default:
					parameters[key] = `{${key}}`
				}

			})

			return {
				subject, parameters,
			}
		},
	},

	mounted() {
	},

	methods: {
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
		&.bw {
			filter: var(--background-invert-if-dark);
		}
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
