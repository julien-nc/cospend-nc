<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--
  Renders a plugin's custom element as its content, passing the context as reactive
  DOM properties. If the element is not defined yet, waits for its definition
  (plugins may define it late, e.g. from a dynamic import) and renders an explicit
  error content if it never happens, instead of a silently blank area.
-->
<script>
import { h, defineComponent } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import { isPluginElementDefined } from './registry.js'

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
			// tags awaited through customElements.whenDefined(), true once resolved;
			// lets the render react to elements defined after this host first rendered
			awaitedTags: {},
		}
	},

	computed: {
		isElementDefined() {
			// awaitedTags[this.tag] flips to true when the whenDefined() promise
			// below resolves, which re-evaluates this computed (and re-renders)
			// for elements defined after this host first rendered
			return this.awaitedTags[this.tag] === true || isPluginElementDefined(this.tag)
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
				customElements.whenDefined(tag).then(() => {
					this.awaitedTags[tag] = true
				})
			},
		},
	},

	render() {
		if (!this.isElementDefined) {
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
		return h(this.tag, {
			projectId: this.projectId,
			pluginContext: this.pluginContext,
		})
	},
})
</script>

<style scoped>
.plugin-error-content {
	margin: 24px auto;
}
</style>
