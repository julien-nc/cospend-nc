/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Cospend UI plugin registry.
 *
 * Plugins are registered from external app scripts (or the demo plugin) through
 * OCA.Cospend.registerPlugin() or the 'cospend:plugin:register' event-bus event.
 * A registration is declarative data only; see docs/plugins.md.
 */
import { reactive } from 'vue'
import { subscribe } from '@nextcloud/event-bus'
import { ACCESS } from '../constants.js'

export const SUPPORTED_API_VERSION = 1
export const ELEMENT_TAG_PREFIX = 'cospend-plugin-'

const KNOWN_ENTRY_POINT_TYPES = [
	'navItem',
	'createProjectAction',
	'projectAction',
	'sidebarTab',
	'memberAction',
	'billAction',
	'billListAction',
	'settingsSection',
]
// entry points that render in the main content area and can declare a listView
const CONTENT_ENTRY_POINT_TYPES = [
	'navItem',
	'createProjectAction',
	'projectAction',
	'memberAction',
	'billAction',
	'billListAction',
]
// entry points that need an id (all but navItem)
const ID_ENTRY_POINT_TYPES = [
	'createProjectAction',
	'projectAction',
	'sidebarTab',
	'memberAction',
	'billAction',
	'billListAction',
	'settingsSection',
]

let initialized = false

/**
 * A validated plugin registration.
 *
 * @typedef {object} CospendPlugin
 * @property {string} id unique plugin id
 * @property {string} appId id of the app providing the plugin
 * @property {number} apiVersion plugin API version
 * @property {string[]} contexts visibility contexts ('user', 'public')
 * @property {Record<string, CospendEntryPoint[]>} entryPoints valid entry points by type
 */

/**
 * A validated plugin entry point.
 *
 * @typedef {object} CospendEntryPoint
 * @property {string} view custom element tag to render
 * @property {string|null} id entry point id (optional for navItem, defaults to its view tag)
 * @property {string} name display name
 * @property {string} icon icon url
 * @property {'content'|'modal'} display how the view is shown
 * @property {string|null} listView optional custom element tag for list views
 * @property {number} order sort order
 * @property {number} minAccessLevel minimal project access level (ACCESS.VIEWER to ACCESS.ADMIN)
 * @property {string[]} contexts visibility contexts ('user', 'public')
 */

/**
 * Adopt early registrations (made by plugin scripts that ran before us),
 * define the public registration API and handle late registrations.
 */
export function initPlugins() {
	if (initialized) {
		return
	}
	initialized = true

	OCA.Cospend = window.OCA.Cospend ?? {}
	const earlyPlugins = Array.isArray(OCA.Cospend.plugins) ? OCA.Cospend.plugins : []
	OCA.Cospend.plugins = reactive([])
	OCA.Cospend.registerPlugin = registerPlugin
	earlyPlugins.forEach((plugin) => {
		registerPlugin(plugin)
	})

	subscribe('cospend:plugin:register', (plugin) => {
		registerPlugin(plugin)
	})
}

/**
 * Validate and register a plugin. Idempotent by id: re-registering replaces.
 *
 * @param {object} plugin plugin registration object
 */
export function registerPlugin(plugin) {
	if (!plugin || typeof plugin !== 'object') {
		console.warn('[cospend] Ignored invalid plugin registration (not an object)')
		return
	}
	if (typeof plugin.id !== 'string' || plugin.id === '') {
		console.warn('[cospend] Ignored invalid plugin registration: missing id')
		return
	}
	if (typeof plugin.appId !== 'string' || plugin.appId === '') {
		console.warn('[cospend] Ignored invalid plugin registration: missing appId', plugin.id)
		return
	}
	const apiVersion = plugin.apiVersion ?? 1
	if (typeof apiVersion !== 'number' || apiVersion > SUPPORTED_API_VERSION) {
		console.warn(
			`[cospend] Plugin ${plugin.id} (from ${plugin.appId}) requires apiVersion ${apiVersion}`
			+ ` but this Cospend only supports up to ${SUPPORTED_API_VERSION}. Not registered.`,
		)
		return
	}

	const entryPoints = {}
	if (plugin.entryPoints && typeof plugin.entryPoints === 'object') {
		for (const [type, value] of Object.entries(plugin.entryPoints)) {
			if (!KNOWN_ENTRY_POINT_TYPES.includes(type)) {
				console.warn(
					`[cospend] Plugin ${plugin.id}: ignoring unsupported entry point type "${type}"`
					+ ` (this Cospend supports: ${KNOWN_ENTRY_POINT_TYPES.join(', ')})`,
				)
				continue
			}
			const valid = []
			for (const ep of Array.isArray(value) ? value : [value]) {
				const normalized = normalizeEntryPoint(type, ep, plugin.id)
				if (normalized !== null) {
					valid.push(normalized)
				}
			}
			if (valid.length > 0) {
				entryPoints[type] = valid
			}
		}
	}
	if (Object.keys(entryPoints).length === 0) {
		console.warn(`[cospend] Plugin ${plugin.id} has no valid entry point, not registered`)
		return
	}

	const registered = {
		id: plugin.id,
		appId: plugin.appId,
		apiVersion,
		contexts: sanitizeContexts(plugin.contexts),
		entryPoints,
	}
	const index = OCA.Cospend.plugins.findIndex((p) => p.id === plugin.id)
	if (index === -1) {
		OCA.Cospend.plugins.push(registered)
	} else {
		OCA.Cospend.plugins.splice(index, 1, registered)
	}
}

/**
 * Validate a single entry point. Returns a sanitized copy or null (with a warning).
 */
function normalizeEntryPoint(type, ep, pluginId) {
	if (!ep || typeof ep !== 'object') {
		console.warn(`[cospend] Plugin ${pluginId}: invalid ${type} entry point (not an object), ignored`)
		return null
	}
	if (typeof ep.view !== 'string' || !ep.view.startsWith(ELEMENT_TAG_PREFIX)) {
		console.warn(
			`[cospend] Plugin ${pluginId}: ${type} entry point "view" must be a custom element tag`
			+ ` starting with "${ELEMENT_TAG_PREFIX}", ignored`,
		)
		return null
	}
	if (ID_ENTRY_POINT_TYPES.includes(type) && (typeof ep.id !== 'string' || ep.id === '')) {
		console.warn(`[cospend] Plugin ${pluginId}: ${type} entry point is missing an id, ignored`)
		return null
	}
	if (typeof ep.name !== 'string' || ep.name === '') {
		console.warn(`[cospend] Plugin ${pluginId}: ${type} entry point is missing a name, ignored`)
		return null
	}
	if (CONTENT_ENTRY_POINT_TYPES.includes(type)
		&& ep.listView !== undefined
		&& (typeof ep.listView !== 'string' || !ep.listView.startsWith(ELEMENT_TAG_PREFIX))
	) {
		console.warn(
			`[cospend] Plugin ${pluginId}: ${type} entry point "listView" must be a custom element tag`
			+ ` starting with "${ELEMENT_TAG_PREFIX}", ignored`,
		)
		return null
	}
	if (ep.display !== undefined && !['content', 'modal'].includes(ep.display)) {
		console.warn(`[cospend] Plugin ${pluginId}: ${type} entry point display must be "content" or "modal", ignored`)
		return null
	}
	if (ep.minAccessLevel !== undefined
		&& (!Number.isInteger(ep.minAccessLevel) || ep.minAccessLevel < ACCESS.VIEWER || ep.minAccessLevel > ACCESS.ADMIN)
	) {
		console.warn(`[cospend] Plugin ${pluginId}: ${type} entry point minAccessLevel must be between ${ACCESS.VIEWER} (viewer) and ${ACCESS.ADMIN} (admin), ignored`)
		return null
	}

	return {
		view: ep.view,
		// navItem has no required id: default it to the view tag so that several
		// navItems of the same plugin can be told apart (a custom element tag
		// is unique per definition)
		id: type === 'navItem' && (typeof ep.id !== 'string' || ep.id === '')
			? ep.view
			: (ep.id ?? null),
		name: ep.name,
		icon: typeof ep.icon === 'string' ? ep.icon : '',
		display: type === 'navItem' ? 'content' : (ep.display ?? 'modal'),
		listView: ep.listView ?? null,
		order: typeof ep.order === 'number' ? ep.order : Infinity,
		minAccessLevel: ep.minAccessLevel ?? ACCESS.VIEWER,
		contexts: sanitizeContexts(ep.contexts),
	}
}

function sanitizeContexts(contexts) {
	if (!Array.isArray(contexts)) {
		return ['user']
	}
	const valid = contexts.filter((c) => ['user', 'public'].includes(c))
	return valid.length > 0 ? valid : ['user']
}

/**
 * Get the entry points of a given type, sorted by order then id.
 * Entry points are filtered by visibility context (hidden on public pages
 * unless they declare the 'public' context).
 *
 * @param {string} type one of the known entry point types
 * @return {Array<{plugin: CospendPlugin, ep: CospendEntryPoint}>}
 */
export function getEntryPoints(type) {
	const pageIsPublic = OCA.Cospend.state?.pageIsPublic ?? false
	const result = []
	for (const plugin of OCA.Cospend.plugins ?? []) {
		for (const ep of plugin.entryPoints[type] ?? []) {
			const contexts = ep.contexts ?? plugin.contexts
			if (pageIsPublic && !contexts.includes('public')) {
				continue
			}
			result.push({ plugin, ep })
		}
	}
	result.sort((a, b) => (a.ep.order - b.ep.order)
		|| String(a.ep.id).localeCompare(String(b.ep.id))
		|| a.plugin.id.localeCompare(b.plugin.id))
	return result
}

/**
 * Find a registered entry point.
 *
 * @param {string} pluginId plugin id
 * @param {string} type entry point type
 * @param {string|null} entryPointId entry point id (null matches the first entry point found)
 * @return {{plugin: CospendPlugin, ep: CospendEntryPoint}|null}
 */
export function findEntryPoint(pluginId, type, entryPointId = null) {
	return getEntryPoints(type).find(({ plugin, ep }) => {
		return plugin.id === pluginId && (entryPointId === null || ep.id === entryPointId)
	}) ?? null
}

/**
 * Whether the given custom element tag is registered in the current document.
 *
 * @param {string} tag custom element tag name
 * @return {boolean}
 */
export function isPluginElementDefined(tag) {
	return typeof customElements !== 'undefined' && customElements.get(tag) !== undefined
}

/**
 * Deep-clone a value from the reactive state into a plain snapshot.
 *
 * @template T
 * @param {T} value value to clone
 * @return {T} plain data clone
 */
export function snapshot(value) {
	return (value === null || value === undefined) ? value : JSON.parse(JSON.stringify(value))
}
