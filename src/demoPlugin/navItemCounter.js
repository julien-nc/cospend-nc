/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Demo nav item counter state.
 *
 * It lives in this module rather than in the custom elements because the elements
 * are unmounted as soon as their view is closed, while the badge of the
 * 'demo-late' nav item must survive. The value is pushed to Cospend through the
 * 'cospend:set-navitem-counter' event (see docs/plugins.md).
 */
import { emit } from '@nextcloud/event-bus'

let demoLateCounter = 0

/**
 * Add a delta to the demo nav item counter and update the badge.
 *
 * @param {number} delta the value to add (can be negative)
 */
export function changeDemoLateCounter(delta) {
	demoLateCounter = Math.max(0, demoLateCounter + delta)
	emit('cospend:set-navitem-counter', {
		pluginId: 'demo',
		navItemId: 'demo-late',
		// 0 hides the badge, a positive value displays it
		count: demoLateCounter === 0 ? null : demoLateCounter,
	})
}
