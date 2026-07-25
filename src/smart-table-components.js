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
