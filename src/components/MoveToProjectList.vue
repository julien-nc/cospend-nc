<template>
	<div class="project-list">
		<h2>
			{{ t('cospend', 'Move bill "{bill}" to a different project:', { bill: bill.what }) }}
		</h2>
		<ul>
			<ListItem v-for="(project) in allProjectsExceptOrigin"
				:key="project.id"
				:title="project.name"
				@click="onProjectClicked(project)" />
		</ul>
		<EmptyContent v-if="cospend.projects.length === 1 && cospend.projects[projectId]">
			{{ t('cospend', 'Only one project available, which this bill already exists in') }}
		</EmptyContent>
		<EmptyContent v-else-if="cospend.projects.length === 0">
			{{ t('cospend', 'No projects found') }}
		</EmptyContent>
	</div>
</template>
<script>
import ListItem from '@nextcloud/vue/dist/Components/ListItem.js'
import cospend from '../state.js'
import * as network from '../network.js'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'MoveToProjectList',
	components: {
		ListItem,
	},
	props: {
		bill: {
			type: Object,
			required: true,
		},
		projectId: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			cospend,
		}
	},
	computed: {
		allProjectsExceptOrigin() {
			const projects = {}
			Object.keys(cospend.projects).forEach(pid => {
				if (pid !== this.projectId) {
					projects[pid] = cospend.projects[pid]
				}
			})
			return projects
		},
	},
	created() {
	},
	methods: {
		onProjectClicked(project) {
			network.moveBill(this.projectId, this.bill.id, project.id).then(res => {
				showSuccess(t('cospend', 'Bill moved to "{project}" successfully', { project: project.name }))
				this.$emit('item-moved', res.data, project.id)
			}).catch(error => {
				console.error(error)
				showError(
					t('cospend', 'Failed to move bill')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
	},
}
</script>

<style scoped lang="scss">
.project-list {
	padding: 12px;
	width: 92%;
}
</style>
