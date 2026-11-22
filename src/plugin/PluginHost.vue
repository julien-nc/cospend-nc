<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--
  Renders a plugin's custom element as its content, passing the context as reactive
  DOM properties. If the element is not defined yet, waits for its definition
  (plugins may define it late, e.g. from a dynamic import) and shows a loading
  state in the meantime; if it is still undefined after a grace period, renders an
  explicit error content instead of a silently blank or endlessly loading area.
-->
<script>
import { h, defineComponent } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { isPluginElementDefined } from './registry.js'

// time given to a plugin to define its element (e.g. through a slow dynamic
// import) before its view is considered broken
const DEFINITION_GRACE_PERIOD = 10 * 1000

export default defineComponent({
	name: 'PluginHost',

	props: {
		tag: {
			type: String,
			required: true,
		},
		pluginId: {
			type: String,
			default: '',
		},
		projectId: {
			type: String,
			default: null,
		},
		pluginContext: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			// tags awaited through customElements.whenDefined(): true once
			// resolved, false while the definition is still pending; lets the
			// render react to elements defined after this host first rendered
			awaitedTags: {},
			// tags whose awaited definition did not happen within the grace period
			timedOutTags: {},
			// grace period timers per awaited tag; not meant to be reactive, but
			// it must exist before the immediate "tag" watcher below runs
			awaitTimeouts: {},
		}
	},

	computed: {
		isElementDefined() {
			// awaitedTags[this.tag] flips to true when the whenDefined() promise
			// below resolves, which re-evaluates this computed (and re-renders)
			// for elements defined after this host first rendered
			return this.awaitedTags[this.tag] === true || isPluginElementDefined(this.tag)
		},
		isElementMissing() {
			return !this.isElementDefined
				&& (typeof customElements === 'undefined' || this.timedOutTags[this.tag] === true)
		},
	},

	watch: {
		tag: {
			immediate: true,
			handler(tag) {
				if (typeof customElements === 'undefined' || this.awaitedTags[tag] !== undefined) {
					return
				}
				this.awaitedTags[tag] = false
				this.awaitTimeouts[tag] = setTimeout(() => {
					// a definition landing after the grace period is still rendered
					// (whenDefined() keeps waiting), the host just stops loading
					if (!isPluginElementDefined(tag)) {
						this.timedOutTags[tag] = true
					}
				}, DEFINITION_GRACE_PERIOD)
				customElements.whenDefined(tag).then(() => {
					clearTimeout(this.awaitTimeouts[tag])
					delete this.awaitTimeouts[tag]
					this.awaitedTags[tag] = true
				})
			},
		},
	},

	beforeUnmount() {
		Object.values(this.awaitTimeouts).forEach((timeout) => clearTimeout(timeout))
	},

	render() {
		if (this.isElementDefined) {
			return h(this.tag, {
				projectId: this.projectId,
				pluginContext: this.pluginContext,
			})
		}
		if (this.isElementMissing) {
			return h(NcEmptyContent, {
				class: 'plugin-error-content',
				name: t('cospend', 'Broken Cospend plugin'),
				title: t('cospend', 'Broken Cospend plugin'),
				description: t('cospend', 'The custom element {tag} was never registered by plugin {plugin}.', {
					tag: this.tag,
					plugin: this.pluginId,
				}),
			})
		}
		return h(NcEmptyContent, {
			class: 'plugin-loading-content',
			name: t('cospend', 'Loading plugin view…'),
			title: t('cospend', 'Loading plugin view…'),
			description: t('cospend', 'Waiting for the custom element {tag} of plugin {plugin} to be defined.', {
				tag: this.tag,
				plugin: this.pluginId,
			}),
		}, {
			icon: () => h(NcLoadingIcon, { size: 44 }),
		})
	},
})
</script>

<style scoped>
.plugin-error-content,
.plugin-loading-content {
	margin: 24px auto;
}
</style>
