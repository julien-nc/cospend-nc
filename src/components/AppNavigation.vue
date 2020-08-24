<template>
	<AppNavigationVue>
		<template slot="list">
			<div v-if="!pageIsPublic">
				<AppNavigationItem v-if="!creating"
					class="buttonItem"
					icon="icon-add"
					:title="t('cospend', 'New project')"
					@click.prevent.stop="startCreateProject" />
				<div v-else
					class="project-create">
					<form @submit.prevent.stop="createProject">
						<input type="text"
							:placeholder="t('cospend', 'New project name')"
							required>
						<input type="submit"
							value=""
							class="icon-confirm">
						<Actions>
							<ActionButton icon="icon-close"
								@click.stop.prevent="cancelCreate" />
						</Actions>
					</form>
				</div>
			</div>
			<AppNavigationProjectItem
				v-for="id in sortedProjectIds"
				:key="id"
				:project="projects[id]"
				:members="projects[id].members"
				:selected="id === selectedProjectId"
				@projectClicked="onProjectClicked"
				@deleteProject="onDeleteProject"
				@statsClicked="onStatsClicked"
				@settleClicked="onSettleClicked"
				@detailClicked="onDetailClicked"
				@shareClicked="onShareClicked"
				@newMemberClicked="onNewMemberClicked"
				@memberEdited="onMemberEdited" />
		</template>
		<template slot="footer">
			<AppNavigationSettings>
				<AppNavigationItem
					v-if="!pageIsPublic"
					v-show="true"
					:title="t('cospend', 'Import csv project')"
					icon="icon-download"
					class="buttonItem"
					@click="onImportClick" />
				<AppNavigationItem
					v-if="!pageIsPublic"
					v-show="true"
					icon="icon-download"
					class="buttonItem"
					:title="t('cospend', 'Import SplitWise project')"
					@click="onImportSWClick" />
				<AppNavigationItem
					v-show="true"
					icon="icon-clippy"
					class="buttonItem"
					:title="t('cospend', 'Guest access link')"
					@click="onGuestLinkClick" />
				<div v-if="!pageIsPublic"
					class="output-dir">
					<button class="icon-folder"
						@click="onOutputDirClick">
						{{ t('cospend', 'Change output directory') }}
					</button>
					<input v-model="outputDir"
						:placeholder="t('cospend', '/Anywhere')"
						type="text"
						readonly
						@click="onOutputDirClick">
				</div>
			</AppNavigationSettings>
		</template>
	</AppNavigationVue>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import AppNavigationProjectItem from './AppNavigationProjectItem'
import {
	ActionButton, AppNavigation as AppNavigationVue,
	AppNavigationSettings, AppNavigationItem, Actions,
} from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import cospend from '../state'
import * as constants from '../constants'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import * as network from '../network'

export default {
	name: 'AppNavigation',
	components: {
		AppNavigationProjectItem,
		AppNavigationVue,
		AppNavigationItem,
		AppNavigationSettings,
		ActionButton,
		Actions,
	},
	directives: {
		ClickOutside,
	},
	props: {
		projects: {
			type: Object,
			required: true,
		},
		selectedProjectId: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			opened: false,
			loading: false,
			creating: false,
			outputDir: cospend.outputDirectory,
			pageIsPublic: cospend.pageIsPublic,
		}
	},
	computed: {
		sortedProjectIds() {
			return Object.keys(this.projects).sort((a, b) => {
				return this.projects[a].name.toLowerCase() > this.projects[b].name.toLowerCase()
			})
		},
		editionAccess() {
			return this.selectedProjectId && this.projects[this.selectedProjectId].myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
	},
	beforeMount() {
	},
	methods: {
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
		},
		onImportClick() {
			const that = this
			OC.dialogs.filepicker(
				t('cospend', 'Choose csv project file'),
				function(targetPath) {
					that.importProject(targetPath)
				},
				false,
				['text/csv'],
				true
			)
		},
		onImportSWClick() {
			const that = this
			OC.dialogs.filepicker(
				t('cospend', 'Choose SplitWise project file'),
				function(targetPath) {
					that.importProject(targetPath, true)
				},
				false,
				['text/csv'],
				true
			)
		},
		importProject(targetPath, isSplitWise = false) {
			network.importProject(targetPath, isSplitWise, this.importProjectSuccess)
		},
		importProjectSuccess(response) {
			this.$emit('projectImported', response)
			showSuccess(t('cospend', 'Project imported.'))
		},
		async onGuestLinkClick() {
			try {
				const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/login')
				await this.$copyText(guestLink)
				showSuccess(t('cospend', 'Guest link copied to clipboard.'))
			} catch (error) {
				console.debug(error)
				showError(t('cospend', 'Guest link could not be copied to clipboard.'))
			}
		},
		onOutputDirClick() {
			const that = this
			OC.dialogs.filepicker(
				t('maps', 'Choose where to write output files (stats, settlement, export)'),
				function(targetPath) {
					if (targetPath === '') {
						targetPath = '/'
					}
					that.outputDir = targetPath
					that.$emit('saveOption', 'outputDirectory', targetPath)
				},
				false,
				'httpd/unix-directory',
				true
			)
		},
		onProjectClicked(projectid) {
			this.$emit('projectClicked', projectid)
		},
		onDeleteProject(projectid) {
			this.$emit('deleteProject', projectid)
		},
		onStatsClicked(projectid) {
			this.$emit('statsClicked', projectid)
		},
		onSettleClicked(projectid) {
			this.$emit('settleClicked', projectid)
		},
		onDetailClicked(projectid) {
			this.$emit('detailClicked', projectid)
		},
		onShareClicked(projectid) {
			this.$emit('shareClicked', projectid)
		},
		onNewMemberClicked(projectid) {
			this.$emit('newMemberClicked', projectid)
		},
		onMemberEdited(projectid, memberid) {
			this.$emit('memberEdited', projectid, memberid)
		},
		startCreateProject(e) {
			this.creating = true
		},
		createProject(e) {
			const name = e.currentTarget.childNodes[0].value
			this.$emit('createProject', name)
			this.creating = false
		},
		cancelCreate(e) {
			this.creating = false
		},
	},
}
</script>
<style scoped lang="scss">
#app-settings-content {
	p {
		margin-top: 20px;
		margin-bottom: 20px;
		color: var(--color-text-light);
	}
}
.output-dir {
	display: inline-flex;
	align-items: center;
	justify-content: center;
}
.output-dir button {
	width: 59% !important;
}
.output-dir input {
	width: 39% !important;
}
.project-create {
	order: 1;
	display: flex;
	height: 44px;
	form {
		display: flex;
		flex-grow: 1;
		input[type="text"] {
			flex-grow: 1;
		}
	}
}
.buttonItem {
	border-bottom: solid 1px var(--color-border);
}
</style>
