import cospend from './state.js'

export const deselectProjectMixin = {
	methods: {
		deselectProject() {
			this.mode = 'normal'
			this.currentBill = null
			cospend.currentProjectId = null
		},
	},
}
