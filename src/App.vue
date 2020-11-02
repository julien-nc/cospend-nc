<template>
	<Content app-name="cospend">
		<CospendNavigation
			:projects="projects"
			:selected-project-id="currentProjectId"
			:loading="projectsLoading"
			@project-clicked="onProjectClicked"
			@delete-project="onDeleteProject"
			@stats-clicked="onStatsClicked"
			@settle-clicked="onSettleClicked"
			@detail-clicked="onDetailClicked"
			@share-clicked="onShareClicked"
			@new-member-clicked="onNewMemberClicked"
			@member-edited="onMemberEdited"
			@project-edited="onProjectEdited"
			@create-project="onCreateProject"
			@project-imported="onProjectImported"
			@save-option="onSaveOption" />
		<AppContent>
			<div v-if="shouldShowDetailsToggle"
				id="app-details-toggle"
				class="icon-confirm"
				tabindex="0"
				@click="showList" />
			<div id="app-content-wrapper">
				<BillList
					v-if="currentProjectId"
					:loading="billsLoading"
					:project-id="currentProjectId"
					:bills="currentBills"
					:selected-bill-id="selectedBillId"
					:edition-access="editionAccess"
					:mode="mode"
					@load-more-bills="loadMoreBills"
					@item-clicked="onBillClicked"
					@item-deleted="onBillDeleted"
					@items-deleted="onBillsDeleted"
					@multi-category-edit="onMultiCategoryEdit"
					@reset-selection="onResetSelection"
					@new-bill-clicked="onNewBillClicked" />
				<BillForm
					v-if="currentBill !== null && mode === 'edition'"
					:bill="currentBill"
					:members="currentMembers"
					:edition-access="editionAccess"
					@bill-created="onBillCreated"
					@bill-saved="onBillSaved"
					@custom-bills-created="onCustomBillsCreated"
					@perso-bills-created="onPersoBillsCreated" />
				<Statistics
					v-if="mode === 'stats'"
					:project-id="currentProjectId" />
				<Settlement
					v-if="mode === 'settle'"
					:project-id="currentProjectId"
					@auto-settled="onAutoSettled" />
			</div>
			<Actions
				class="content-buttons"
				:title="t('cospend', 'Details')">
				<ActionButton
					icon="icon-menu-sidebar"
					@click="onMainDetailClicked" />
			</Actions>
		</AppContent>
		<Sidebar
			v-if="currentProjectId"
			:project-id="currentProjectId"
			:bills="currentBills"
			:members="currentMembers"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			@active-changed="onActiveSidebarTabChanged"
			@close="showSidebar = false"
			@project-edited="onProjectEdited"
			@user-added="onNewMember"
			@member-edited="onMemberEdited"
			@new-member="onNewMember"
			@export-clicked="onExportClicked"
			@category-deleted="onCategoryDeleted" />
	</Content>
</template>

<script>
import CospendNavigation from './components/CospendNavigation'
import BillForm from './BillForm'
import BillList from './BillList'
import Statistics from './Statistics'
import Settlement from './Settlement'
import Sidebar from './components/Sidebar'
import cospend from './state'
import * as network from './network'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import * as constants from './constants'
import { rgbObjToHex, slugify } from './utils'
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'App',
	components: {
		CospendNavigation,
		BillList,
		BillForm,
		Statistics,
		Settlement,
		Sidebar,
		Content,
		AppContent,
		Actions,
		ActionButton,
	},
	provide() {
		return {
		}
	},
	data() {
		return {
			mode: 'edition',
			cospend,
			projects: {},
			bills: {},
			billLists: {},
			members: {},
			projectsLoading: false,
			billsLoading: false,
			currentBill: null,
			filterQuery: null,
			showSidebar: false,
			activeSidebarTab: 'sharing',
		}
	},
	computed: {
		shouldShowDetailsToggle() {
			return (this.currentBill || this.mode !== 'edition')
		},
		currentProjectId() {
			return this.cospend.currentProjectId
		},
		currentProject() {
			return this.projects[this.currentProjectId]
		},
		selectedBillId() {
			return (this.currentBill !== null) ? this.currentBill.id : -1
		},
		currentBills() {
			return (this.currentProjectId && this.currentProjectId in this.billLists)
				? (
					this.filterQuery
						? this.getFilteredBills(this.billLists[this.currentProjectId])
						: this.billLists[this.currentProjectId]
				)
				: []
		},
		currentMembers() {
			return (this.currentProjectId && this.currentProjectId in this.members)
				? this.members[this.currentProjectId]
				: {}
		},
		editionAccess() {
			return this.currentProjectId && this.projects[this.currentProjectId].myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
		defaultPayerId() {
			let payerId = -1
			const members = this.members[this.currentProjectId]
			if (members && Object.keys(members).length > 0) {
				if (cospend.pageIsPublic) {
					payerId = Object.keys(members)[0]
				} else {
					payerId = Object.keys(members)[0]
					let member
					for (const mid in members) {
						member = members[mid]
						if (member.userid === getCurrentUser().uid) {
							payerId = member.id
						}
					}
				}
			}
			return payerId
		},
	},
	created() {
		this.getProjects()
	},
	mounted() {
		subscribe('nextcloud:unified-search:search', this.filter)
		subscribe('nextcloud:unified-search:reset', this.cleanSearch)
	},
	beforeDestroy() {
		unsubscribe('nextcloud:unified-search:search', this.filter)
		unsubscribe('nextcloud:unified-search:reset', this.cleanSearch)
	},
	methods: {
		onActiveSidebarTabChanged(newActive) {
			this.activeSidebarTab = newActive
		},
		onMainDetailClicked() {
			this.showSidebar = !this.showSidebar
			this.activeSidebarTab = 'project-settings'
		},
		onDetailClicked(projectid) {
			const sameProj = cospend.currentProjectId === projectid
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid)
			}
			const sameTab = this.activeSidebarTab === 'project-settings'
			this.showSidebar = (sameProj && sameTab) ? !this.showSidebar : true
			this.activeSidebarTab = 'project-settings'
		},
		onShareClicked(projectid) {
			const sameProj = cospend.currentProjectId === projectid
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid)
			}
			const sameTab = this.activeSidebarTab === 'sharing'
			this.showSidebar = (sameProj && sameTab) ? !this.showSidebar : true
			this.activeSidebarTab = 'sharing'
		},
		filter({ query }) {
			this.filterQuery = query
		},
		cleanSearch() {
			this.filterQuery = null
		},
		getFilteredBills(billList) {
			const filteredBills = []
			// Make sure to escape user input before creating regex from it:
			const regex = new RegExp(this.filterQuery.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&'), 'i')
			let bill
			for (let i = 0; i < billList.length; i++) {
				bill = billList[i]
				if (regex.test(bill.what)) {
					filteredBills.push(bill)
				}
			}
			return filteredBills
		},
		cleanupBills() {
			const billList = this.billLists[cospend.currentProjectId]
			for (let i = 0; i < billList.length; i++) {
				if (billList[i].id === 0) {
					billList.splice(i, 1)
					break
				}
			}
		},
		onBillCreated(bill, select, mode) {
			this.bills[cospend.currentProjectId][bill.id] = bill
			this.billLists[cospend.currentProjectId].push(bill)
			this.cleanupBills()
			if (select) {
				this.currentBill = bill
			}
			if (mode === 'normal') {
				this.updateBalances(cospend.currentProjectId)
			}
		},
		onMultiCategoryEdit(billIds, categoryid) {
			billIds.forEach(id => {
				this.bills[cospend.currentProjectId][id].categoryid = categoryid
			})
		},
		onBillSaved(bill, changedBill) {
			Object.assign(bill, changedBill)
			this.updateBalances(cospend.currentProjectId)
		},
		onCustomBillsCreated() {
			this.currentBill = null
			this.updateBalances(cospend.currentProjectId)
		},
		onPersoBillsCreated() {
			this.updateBalances(cospend.currentProjectId)
		},
		onResetSelection() {
			this.currentBill = null
		},
		onBillsDeleted(billIds) {
			const billList = this.billLists[cospend.currentProjectId]
			billIds.forEach(id => {
				const index = billList.findIndex(bill => bill.id === id)
				billList.splice(index, 1)
			})
			this.updateBalances(cospend.currentProjectId)
		},
		onBillDeleted(bill) {
			const billList = this.billLists[cospend.currentProjectId]
			billList.splice(billList.indexOf(bill), 1)
			if (bill.id === this.selectedBillId) {
				this.currentBill = null
			}
			this.updateBalances(cospend.currentProjectId)
		},
		onProjectClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid)
			}
		},
		onDeleteProject(projectid) {
			this.deleteProject(projectid)
		},
		onExportClicked(projectid) {
			const projectName = this.projects[projectid].name
			const timeStamp = Math.floor(Date.now())
			const dateStr = moment(timeStamp).format('YYYY-MM-DD')
			const filename = projectid + '_' + dateStr + '.csv'
			network.exportProject(filename, projectid, projectName)
		},
		onStatsClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid)
			}
			this.currentBill = null
			this.mode = 'stats'
		},
		onSettleClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid)
			}
			this.currentBill = null
			this.mode = 'settle'
		},
		onNewMemberClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid)
			}
			this.currentBill = null
			this.activeSidebarTab = 'project-settings'
			this.showSidebar = true
		},
		onNewMember(projectid, name, userid = null) {
			if (this.getMemberNames(projectid).includes(name)) {
				showError(t('cospend', 'Member {name} already exists', { name }))
			} else {
				this.createMember(projectid, name, userid)
			}
		},
		onMemberEdited(projectid, memberid) {
			this.editMember(projectid, memberid)
		},
		onProjectEdited(projectid, password = null) {
			this.editProject(projectid, password)
		},
		onSaveOption(key, value) {
			const ov = {}
			ov[key] = value
			network.saveOptionValue(ov)
		},
		getMemberNames(projectid) {
			const res = []
			for (const mid in this.members[projectid]) {
				res.push(this.members[projectid][mid].name)
			}
			return res
		},
		selectProject(projectid, save = true) {
			this.mode = 'edition'
			this.currentBill = null
			this.getBills(projectid)
			if (save) {
				network.saveOptionValue({ selectedProject: projectid })
			}
			cospend.currentProjectId = projectid
		},
		deselectProject() {
			this.mode = 'edition'
			this.currentBill = null
			cospend.currentProjectId = null
		},
		onAutoSettled(projectid) {
			this.getBills(projectid)
		},
		onNewBillClicked() {
			// find potentially existing new bill
			const billList = this.billLists[cospend.currentProjectId]
			let found = -1
			for (let i = 0; i < billList.length; i++) {
				if (billList[i].id === 0) {
					found = i
					break
				}
			}
			if (found === -1) {
				const payerId = this.defaultPayerId
				// select all owers
				const owerIds = []
				for (const mid in this.currentMembers) {
					if (this.currentMembers[mid].activated) {
						owerIds.push(this.currentMembers[mid].id)
					}
				}
				this.currentBill = {
					id: 0,
					what: '',
					timestamp: moment().unix(),
					amount: 0.0,
					payer_id: payerId,
					repeat: 'n',
					owers: [],
					owerIds,
					paymentmode: 'n',
					categoryid: 0,
					comment: '',
				}
				this.billLists[cospend.currentProjectId].push(this.currentBill)
			} else {
				this.currentBill = billList[found]
			}
			// select new bill in case it was not selected yet
			// this.selectedBillId = billid
			this.mode = 'edition'
		},
		onBillClicked(billid) {
			const billList = this.billLists[cospend.currentProjectId]
			if (billid === 0) {
				let found = -1
				for (let i = 0; i < billList.length; i++) {
					if (billList[i].id === 0) {
						found = i
						break
					}
				}
				this.currentBill = billList[found]
			} else {
				this.currentBill = this.bills[cospend.currentProjectId][billid]
			}
			this.mode = 'edition'
		},
		getProjects() {
			this.projectsLoading = true
			network.getProjects(this.getProjectsSuccess)
		},
		getProjectsSuccess(response) {
			if (!cospend.pageIsPublic) {
				let proj
				for (let i = 0; i < response.length; i++) {
					proj = response[i]
					this.addProject(proj)
				}
				if (cospend.restoredCurrentProjectId !== null && cospend.restoredCurrentProjectId in this.projects) {
					this.selectProject(cospend.restoredCurrentProjectId, false)
				}
			} else {
				if (!response.myaccesslevel) {
					response.myaccesslevel = response.guestaccesslevel
				}
				this.addProject(response)
				this.selectProject(response.id, false)
			}
			this.projectsLoading = false
		},
		getBills(projectid) {
			this.billsLoading = true
			network.getBills(projectid, 0, 50, this.getBillsSuccess, this.getBillsDone)
		},
		getBillsSuccess(projectid, response) {
			this.bills[projectid] = {}
			this.$set(this.billLists, projectid, response)
			let bill
			for (let i = 0; i < response.length; i++) {
				bill = response[i]
				this.bills[projectid][bill.id] = bill
			}
			this.updateBalances(projectid)
		},
		getBillsDone() {
			this.billsLoading = false
		},
		loadMoreBills(projectid, state) {
			network.getBills(projectid, this.billLists[projectid].length, 20, this.getMoreBillsSuccess, this.getMoreBillsDone, state)
		},
		getMoreBillsSuccess(projectid, response, state) {
			if (!response || response.length === 0) {
				state.complete()
			} else {
				this.$set(this.billLists, projectid, this.billLists[projectid].concat(response))
				let bill
				for (let i = 0; i < response.length; i++) {
					bill = response[i]
					this.bills[projectid][bill.id] = bill
				}
				state.loaded()
			}
		},
		getMoreBillsDone() {
		},
		addProject(proj) {
			cospend.members[proj.id] = {}
			this.$set(this.members, proj.id, cospend.members[proj.id])
			for (let i = 0; i < proj.members.length; i++) {
				cospend.members[proj.id][proj.members[i].id] = proj.members[i]
				this.$set(this.members[proj.id], proj.members[i].id, proj.members[i])
				// proj.members[i].balance = proj.balance[proj.members[i].id]
				this.$set(this.members[proj.id][proj.members[i].id], 'balance', proj.balance[proj.members[i].id])
				// proj.members[i].color = rgbObjToHex(proj.members[i].color).replace('#', '')
				this.$set(this.members[proj.id][proj.members[i].id], 'color', rgbObjToHex(proj.members[i].color).replace('#', ''))
			}

			cospend.bills[proj.id] = {}
			this.$set(this.bills, proj.id, cospend.bills[proj.id])

			cospend.billLists[proj.id] = []
			this.$set(this.billLists, proj.id, cospend.billLists[proj.id])
			// this.$set(cospend.projects, proj.id, proj)

			cospend.projects[proj.id] = proj
			this.$set(this.projects, proj.id, proj)
		},
		onProjectImported(project) {
			this.addProject(project)
			this.selectProject(project.id)
		},
		onCreateProject(name) {
			if (!name) {
				showError(t('cospend', 'Invalid project name'))
			} else {
				const id = slugify(name)
				this.createProject(name, id)
			}
		},
		createProject(name, id) {
			network.createProject(name, id, this.createProjectSuccess)
		},
		createProjectSuccess(response) {
			this.addProject(response)
			this.selectProject(response.id)
		},
		deleteProject(projectid) {
			network.deleteProject(projectid, this.deleteProjectSuccess)
		},
		deleteProjectSuccess(projectid, response) {
			this.currentBill = null
			this.$delete(this.projects, projectid)
			this.$delete(this.bills, projectid)
			this.$delete(this.billLists, projectid)
			this.$delete(this.members, projectid)

			if (cospend.pageIsPublic) {
				const redirectUrl = generateUrl('/apps/cospend/login')
				window.location.replace(redirectUrl)
			}
			showSuccess(t('cospend', 'Deleted project {id}', { id: projectid }))
			this.deselectProject()
		},
		updateBalances(projectid) {
			network.updateBalances(projectid, this.updateBalancesSuccess)
		},
		updateBalancesSuccess(projectid, response) {
			let balance
			for (const memberid in response.balance) {
				balance = response.balance[memberid]
				this.$set(this.members[projectid][memberid], 'balance', balance)
			}
			this.updateProjectPrecision(projectid, response.balance)
		},
		updateProjectPrecision(projectid, balances) {
			const balanceArray = Object.values(balances)
			let precision = 1
			let sum
			do {
				precision++
				sum = balanceArray.reduce((a, b) => parseFloat(a.toFixed(precision)) + parseFloat(b.toFixed(precision)), 0)
			} while (sum !== 0.0 && precision < cospend.maxPrecision)
			this.$set(this.projects[projectid], 'precision', precision)
		},
		createMember(projectid, name, userid = null) {
			network.createMember(projectid, name, userid, this.createMemberSuccess)
		},
		createMemberSuccess(projectid, name, response) {
			response.balance = 0
			response.color = rgbObjToHex(response.color).replace('#', '')
			this.$set(this.members[projectid], response.id, response)
			this.projects[projectid].members.unshift(response)
			showSuccess(t('cospend', 'Created member {name}', { name }))
			// add access to this user if it's not there already
			if (response.userid) {
				this.addParticipantAccess(projectid, response.id, response.userid)
			}
		},
		addParticipantAccess(projectid, memberid, userid) {
			const foundIndex = this.projects[projectid].shares.findIndex((access) => {
				return access.userid === userid && access.type === 'u'
			})
			if (userid !== this.projects[projectid].userid && foundIndex === -1) {
				const sh = {
					user: userid,
					type: 'u',
					accesslevel: 2,
					manually_added: false,
				}
				network.addSharedAccess(projectid, sh, this.addSharedAccessSuccess)
			}
		},
		addSharedAccessSuccess(response, sh, projectid) {
			const newShAccess = {
				accesslevel: sh.accesslevel,
				type: sh.type,
				name: response.name,
				userid: sh.user,
				id: response.id,
				manually_added: sh.manually_added,
			}
			this.projects[projectid].shares.push(newShAccess)
		},
		editMember(projectid, memberid) {
			const member = this.members[projectid][memberid]
			network.editMember(projectid, member, this.editMemberSuccess)
		},
		editMemberSuccess(projectid, memberid, member) {
			if (!member) {
				// delete member
				this.$delete(this.members[projectid], memberid)
				const i = this.projects[projectid].members.findIndex((m) => m.id === memberid)
				if (i !== -1) {
					this.projects[projectid].members.splice(i, 1)
				}
				showSuccess(t('cospend', 'Member deleted.'))
			} else {
				showSuccess(t('cospend', 'Member saved.'))
				this.updateBalances(cospend.currentProjectId)
				// add access to this user if it's not there already
				if (member.userid) {
					this.addParticipantAccess(projectid, memberid, member.userid)
				}
			}
		},
		editProject(projectid, password = null) {
			const project = this.projects[projectid]
			network.editProject(project, password, this.editProjectSuccess)
		},
		editProjectSuccess(password) {
			if (password && cospend.pageIsPublic) {
				cospend.password = password
			}
			showSuccess(t('cospend', 'Project saved'))
		},
		onCategoryDeleted(catid) {
			let bill
			for (const bid in this.bills[this.currentProjectId]) {
				bill = this.bills[this.currentProjectId][bid]
				if (bill.categoryid === catid) {
					bill.categoryid = 0
				}
			}
		},
		showList() {
			this.currentBill = null
			this.mode = 'edition'
		},
	},
}
</script>

<style lang="scss" scoped>
/*#content {
	#app-content {
		transition: margin-left 100ms ease;
		position: relative;
		overflow-x: hidden;
		align-items: stretch;
	}
	#app-sidebar {
		transition: max-width 100ms ease;
	}
	&.nav-hidden {
		#app-content {
			margin-left: 0;
		}
	}
	&.sidebar-hidden {
		#app-sidebar {
			max-width: 0;
			min-width: 0;
		}
	}
}*/
.content-buttons {
	position: absolute !important;
	top: 0px;
	right: 8px;
}

#app-content-wrapper {
	display: flex;
}
</style>

<style>
	#content * {
		box-sizing: border-box;
	}
</style>
