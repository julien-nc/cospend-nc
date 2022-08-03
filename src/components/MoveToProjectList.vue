<template>
	<div>
		<h2>
			{{ t('cospend', 'Move bill "{bill}" to a different project:', { bill: bill.what }) }}
		</h2>
		<ul>
			<ListItem v-for="(project) in cospend.projects"
				v-show="project.id !== projectId"
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
import ListItem from '@nextcloud/vue/dist/Components/ListItem'
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import cospend from '../state'
import * as network from '../network'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'MoveToProjectList',
	components: {
		ListItem,
		AppContentList,
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
