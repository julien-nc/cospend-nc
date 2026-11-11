<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--
  Renders a plugin's custom element as its content, passing the context as reactive
  DOM properties. If the element was never defined by the plugin script, renders an
  explicit error content instead of a silently blank area.
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

	render() {
		if (!isPluginElementDefined(this.tag)) {
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
