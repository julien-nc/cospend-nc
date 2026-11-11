/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Demo Cospend UI plugin.
 *
 * It is part of Cospend itself and is injected by the main page controller when the
 * 'enable_demo_plugin' admin setting is on, but it is written exactly like a plugin
 * coming from an external app: it only defines custom elements and pushes a
 * declarative registration object into OCA.Cospend. It never calls Cospend's API,
 * everything goes through the pluginContext prop and event-bus action events.
 *
 * This file is the template to follow to write a plugin in another app:
 * see docs/plugins.INPROGRESS.md.
 */
import { defineCustomElement } from 'vue'
import DemoMainView from './components/DemoMainView.vue'
import DemoMainListView from './components/DemoMainListView.vue'
import DemoProjectActionView from './components/DemoProjectActionView.vue'
import DemoSidebarTabView from './components/DemoSidebarTabView.vue'
import DemoMemberActionView from './components/DemoMemberActionView.vue'
import DemoBillActionView from './components/DemoBillActionView.vue'
import DemoBillListActionView from './components/DemoBillListActionView.vue'
import DemoSettingsView from './components/DemoSettingsView.vue'

// shadowRoot: false so the styles bundled with the app apply (light DOM custom elements);
// configureApp provides the global translation methods to the element's Vue app
function defineDemoElement(tag, component) {
	customElements.define(
		tag,
		defineCustomElement(component, {
			shadowRoot: false,
			props: ['projectId', 'pluginContext'],
			configureApp(app) {
				app.config.globalProperties.t = window.t
				app.config.globalProperties.n = window.n
			},
		}),
	)
}

defineDemoElement('cospend-plugin-demo-main', DemoMainView)
defineDemoElement('cospend-plugin-demo-main-list', DemoMainListView)
defineDemoElement('cospend-plugin-demo-project-action', DemoProjectActionView)
defineDemoElement('cospend-plugin-demo-sidebar', DemoSidebarTabView)
defineDemoElement('cospend-plugin-demo-member', DemoMemberActionView)
defineDemoElement('cospend-plugin-demo-bill', DemoBillActionView)
defineDemoElement('cospend-plugin-demo-billlist', DemoBillListActionView)
defineDemoElement('cospend-plugin-demo-settings', DemoSettingsView)

// self-contained SVG icon (puzzle piece), so the demo does not depend on server files
const demoIcon = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
	"<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'>"
	+ "<path fill='currentColor' d='M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5C13 2.12 11.88 1 10.5 1S8 2.12 8 3.5V5H4c-1.1 0-1.99.9-1.99 2v3.8H3.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7 1.49 0 2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5c1.38 0 2.5-1.12 2.5-2.5S21.88 11 20.5 11z'/>"
	+ '</svg>',
)

const plugin = {
	id: 'demo',
	appId: 'cospend',
	apiVersion: 1,
	contexts: ['user'],
	entryPoints: {
		navItem: {
			name: 'Demo plugin',
			icon: demoIcon,
			order: 100,
			view: 'cospend-plugin-demo-main',
			listView: 'cospend-plugin-demo-main-list',
		},
		projectAction: {
			id: 'demo-project-action',
			name: 'Demo project action',
			icon: demoIcon,
			display: 'content',
			minAccessLevel: 1,
			view: 'cospend-plugin-demo-project-action',
		},
		sidebarTab: {
			id: 'demo-sidebar-tab',
			name: 'Demo tab',
			icon: demoIcon,
			order: 10,
			minAccessLevel: 1,
			view: 'cospend-plugin-demo-sidebar',
		},
		memberAction: {
			id: 'demo-member-action',
			name: 'Demo member action',
			icon: demoIcon,
			display: 'modal',
			minAccessLevel: 1,
			view: 'cospend-plugin-demo-member',
		},
		billAction: {
			id: 'demo-bill-action',
			name: 'Demo bill action',
			icon: demoIcon,
			display: 'modal',
			minAccessLevel: 1,
			view: 'cospend-plugin-demo-bill',
		},
		billListAction: {
			id: 'demo-billlist-action',
			name: 'Demo bill list action',
			icon: demoIcon,
			display: 'content',
			minAccessLevel: 1,
			view: 'cospend-plugin-demo-billlist',
		},
		settingsSection: {
			id: 'demo-settings',
			name: 'Demo plugin',
			icon: demoIcon,
			view: 'cospend-plugin-demo-settings',
		},
	},
}

if (window.OCA?.Cospend?.registerPlugin) {
	window.OCA.Cospend.registerPlugin(plugin)
} else {
	// Cospend is not loaded yet: adopt the namespace and store the registration,
	// Cospend will adopt it on boot.
	window.OCA.Cospend ??= {}
	window.OCA.Cospend.plugins ??= []
	window.OCA.Cospend.plugins.push(plugin)
}
