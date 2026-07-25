/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import SmartTable from 'vuejs-smart-table'

const components = {}
const fakeApp = {
	component(name, component) {
		components[name] = component
	},
}
SmartTable.install(fakeApp)

export const VTable = components.VTable
export const VTh = components.VTh
