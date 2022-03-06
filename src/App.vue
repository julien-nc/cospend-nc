<template>
	<Content app-name="cospend">
		<CospendNavigation
			:projects="projects"
			:selected-project-id="currentProjectId"
			:selected-member-id="selectedMemberId"
			:loading="projectsLoading"
			@project-clicked="onProjectClicked"
			@delete-project="onDeleteProject"
			@stats-clicked="onStatsClicked"
			@settle-clicked="onSettleClicked"
			@detail-clicked="onDetailClicked"
			@share-clicked="onShareClicked"
			@new-member-clicked="onNewMemberClicked"
			@member-edited="onMemberEdited"
			@create-project="onCreateProject"
			@save-option="onSaveOption"
			@member-click="onNavMemberClick" />
		<AppContent
			:list-max-width="showSidebar ? 40 : 50"
			:list-min-width="showSidebar ? 30 : 20"
			:list-size="showSidebar ? 30 : 20"
			:show-details="shouldShowDetailsToggle"
			@update:showDetails="showList">
			<template #list>
				<BillList
					v-if="currentProjectId"
					ref="billList"
					:loading="billsLoading"
					:project-id="currentProjectId"
					:total-bill-number="currentProject.nbBills || 0"
					:bills="currentBills"
					:selected-bill-id="selectedBillId"
					:edition-access="editionAccess"
					:mode="mode"
					:selected-category-id-filter="selectedCategoryFilter"
					:selected-payment-mode-id-filter="selectedPaymentModeFilter"
					@reset-filters="onResetFilters"
					@set-category-filter="onSetCategoryFilter"
					@set-paymentmode-filter="onSetPaymentModeFilter"
					@load-more-bills="loadMoreBills"
					@item-clicked="onBillClicked"
					@item-deleted="onBillDeleted"
					@items-deleted="onBillsDeleted"
					@multi-bill-edit="onMultiBillEdit"
					@reset-selection="onResetSelection"
					@new-bill-clicked="onNewBillClicked" />
			</template>
			<BillForm
				v-if="currentBill !== null && mode === 'edition'"
				:bill="currentBill"
				:members="currentMembers"
				:edition-access="editionAccess"
				@bill-created="onBillCreated"
				@bill-saved="onBillSaved"
				@custom-bills-created="onCustomBillsCreated"
				@perso-bills-created="onPersoBillsCreated"
				@duplicate-bill="onDuplicateBill"
				@repeat-bill-now="onRepeatBillNow" />
			<Statistics
				v-else-if="mode === 'stats'"
				:project-id="currentProjectId" />
			<Settlement
				v-else-if="mode === 'settle'"
				:project-id="currentProjectId"
				@auto-settled="onAutoSettled" />
			<EmptyContent v-else-if="!isMobile"
				class="central-empty-content"
				icon="icon-cospend">
				{{ currentProjectId ? t('cospend', 'Project {name}', { name: currentProjectId }) : t('cospend', 'Select a project') }}
			</EmptyContent>
			<div v-if="!isMobile"
				class="content-buttons">
				<button
					v-tooltip.bottom="{ content: t('cospend', 'Toggle sidebar') }"
					class="icon-menu-sidebar"
					@click="onMainDetailClicked" />
			</div>
		</AppContent>
		<CospendSettingsDialog
			@project-imported="onProjectImported"
			@update-max-precision="onUpdateMaxPrecision"
			@save-option="onSaveOption" />
		<Sidebar
			v-if="currentProjectId"
			ref="sidebar"
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
			@paymentmode-deleted="onPaymentModeDeleted"
			@category-deleted="onCategoryDeleted" />
	</Content>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import {
	showSuccess,
	showError,
	showInfo,
} from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'

import CospendNavigation from './components/CospendNavigation'
import CospendSettingsDialog from './components/CospendSettingsDialog'
import BillForm from './BillForm'
import BillList from './BillList'
import Statistics from './components/statistics/Statistics'
import Settlement from './Settlement'
import Sidebar from './components/Sidebar'

import cospend from './state'
import * as network from './network'
import * as constants from './constants'
import { rgbObjToHex, slugify } from './utils'

export default {
	name: 'App',
	components: {
		CospendNavigation,
		CospendSettingsDialog,
		BillList,
		BillForm,
		Statistics,
		Settlement,
		Sidebar,
		Content,
		AppContent,
		EmptyContent,
	},
	mixins: [isMobile],
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
			selectedCategoryFilter: null,
			selectedPaymentModeFilter: null,
			showSidebar: false,
			activeSidebarTab: 'sharing',
			selectedMemberId: null,
		}
	},
	computed: {
		shouldShowDetailsToggle() {
			return ((this.currentBill && this.currentBill !== null) || this.mode !== 'edition')
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
			if (this.currentProjectId && this.currentProjectId in this.billLists) {
				let result = this.billLists[this.currentProjectId]
				if (this.selectedMemberId) {
					result = result.filter(b => b.payer_id === this.selectedMemberId)
				}
				if (this.selectedCategoryFilter !== null) {
					const filterCatId = this.selectedCategoryFilter
					result = result.filter(b => b.categoryid === filterCatId)
				}
				if (this.selectedPaymentModeFilter !== null) {
					const filterPmId = this.selectedPaymentModeFilter
					result = result.filter(b => b.paymentmodeid === filterPmId)
				}
				if (this.filterQuery) {
					result = this.getFilteredBills(result)
				}
				return result
			}
			return []
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
				for (const mid in members) {
					if (members[mid].activated) {
						payerId = mid
						break
					}
				}
				if (!cospend.pageIsPublic) {
					let member
					for (const mid in members) {
						member = members[mid]
						if (member.userid === getCurrentUser().uid) {
							payerId = member.id
							break
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
		subscribe('nextcloud:unified-search.search', this.filter)
		subscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	beforeDestroy() {
		unsubscribe('nextcloud:unified-search.search', this.filter)
		unsubscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	methods: {
		onResetFilters() {
			this.selectedCategoryFilter = null
			this.selectedPaymentModeFilter = null
			this.onFilterChange()
		},
		onSetCategoryFilter(catId) {
			this.selectedCategoryFilter = catId
			this.onFilterChange()
		},
		onSetPaymentModeFilter(pmId) {
			this.selectedPaymentModeFilter = pmId
			this.onFilterChange()
		},
		onFilterChange() {
			// deselect current bill
			this.currentBill = null
			// we load bills from scratch to make sure we get the correct total number of bills
			// and infinite scroll works fine
			this.getBills(cospend.currentProjectId)
		},
		onNavMemberClick(projectId, memberId) {
			if (this.selectedMemberId === memberId) {
				this.selectedMemberId = null
			} else if (this.currentProjectId === projectId) {
				this.selectedMemberId = memberId
			}
			// deselect current bill
			this.currentBill = null
			// we load bills from scratch to make sure we get the correct total number of bills
			// and infinite scroll works fine
			this.getBills(cospend.currentProjectId)
		},
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
			// Make sure to escape user input before creating regex from it:
			const regex = new RegExp(this.filterQuery.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&'), 'i')
			return billList.filter(bill => {
				return regex.test(bill.what) || regex.test(bill.comment)
			})
		},
		cleanupBills() {
			const i0 = this.billLists[cospend.currentProjectId].findIndex((bill) => { return bill.id === 0 })
			if (i0 !== -1) {
				this.billLists[cospend.currentProjectId].splice(i0, 1)
			}
		},
		onBillCreated(bill, select, mode) {
			this.bills[cospend.currentProjectId][bill.id] = bill
			this.billLists[cospend.currentProjectId].unshift(bill)
			this.currentProject.nbBills++
			this.cleanupBills()
			if (select) {
				this.currentBill = bill
			}
			if (mode === 'normal') {
				this.updateProjectInfo(cospend.currentProjectId)
			}
		},
		onMultiBillEdit(billIds, categoryid, paymentmodeid) {
			if (categoryid !== null) {
				billIds.forEach(id => {
					this.bills[cospend.currentProjectId][id].categoryid = categoryid
				})
			}
			if (paymentmodeid !== null) {
				billIds.forEach(id => {
					this.bills[cospend.currentProjectId][id].paymentmodeid = paymentmodeid
				})
			}
		},
		onRepeatBillNow(billId) {
			network.repeatBillNow(cospend.currentProjectId, billId).then((response) => {
				if (response.data.length > 0) {
					this.getBills(cospend.currentProjectId, billId)
					showSuccess(n('cospend', '{nb} bill was created', '{nb} bills were created', response.data.length, { nb: response.data.length }))
					// this.currentBill = null
				} else {
					showInfo(t('cospend', 'Nothing to repeat'))
				}
			}).catch((error) => {
				console.error(error)
			})
		},
		onDuplicateBill(bill) {
			this.onNewBillClicked(bill)
		},
		onBillSaved(bill, changedBill) {
			Object.assign(bill, changedBill)
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onCustomBillsCreated() {
			this.currentBill = null
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onPersoBillsCreated() {
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onResetSelection() {
			this.currentBill = null
		},
		onBillsDeleted(billIds) {
			const billList = this.billLists[cospend.currentProjectId]
			billIds.forEach(id => {
				const index = billList.findIndex(bill => bill.id === id)
				billList.splice(index, 1)
				this.currentProject.nbBills--
			})
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onBillDeleted(bill) {
			const billList = this.billLists[cospend.currentProjectId]
			billList.splice(billList.indexOf(bill), 1)
			this.currentProject.nbBills--
			if (bill.id === this.selectedBillId) {
				this.currentBill = null
			}
			this.updateProjectInfo(cospend.currentProjectId)
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
			this.$nextTick(() => { this.$refs.sidebar?.focusOnAddMember() })
		},
		onNewMember(projectid, name, userid = null) {
			if (this.getMemberNames(projectid).includes(name)) {
				showError(t('cospend', 'Member {name} already exists', { name }))
			} else {
				this.createMember(projectid, name, userid)
			}
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
			this.selectedMemberId = null
			this.selectedCategoryFilter = null
			this.selectedPaymentModeFilter = null
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
		onNewBillClicked(bill = null) {
			// if a member is selected: deselect member and get full bill list
			// then call onNewBillClicked again
			if (this.selectedMemberId
				|| this.selectedCategoryFilter !== null
				|| this.selectedPaymentModeFilter !== null
			) {
				this.selectedMemberId = null
				this.selectedCategoryFilter = null
				this.selectedPaymentModeFilter = null
				this.$refs.billList?.toggleFilterMode(false, false)
				this.getBills(cospend.currentProjectId, null, () => { this.onNewBillClicked(bill) })
			} else {
				// find potentially existing new bill
				const billList = this.billLists[cospend.currentProjectId]
				const found = billList.findIndex((bill) => {
					return bill.id === 0
				})
				if (found === -1) {
					if (bill) {
						this.currentBill = {
							...bill,
							id: 0,
						}
					} else {
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
							// timestamp: moment().hour(0).minute(0).second(0).unix(),
							timestamp: moment().unix(),
							amount: 0.0,
							payer_id: payerId,
							repeat: 'n',
							owers: [],
							owerIds,
							paymentmode: 'n',
							categoryid: 0,
							paymentmodeid: 0,
							comment: '',
						}
					}
					this.billLists[cospend.currentProjectId].unshift(this.currentBill)
					this.currentProject.nbBills++
				} else {
					this.currentBill = billList[found]
					if (bill) {
						Object.assign(this.currentBill, {
							...bill,
							id: 0,
						})
					}
				}
				// select new bill in case it was not selected yet
				// this.selectedBillId = billid
				this.mode = 'edition'
			}
		},
		onBillClicked(billid) {
			const billList = this.billLists[cospend.currentProjectId]
			if (billid === 0) {
				const found = billList.findIndex((bill) => { return bill.id === 0 })
				if (found !== -1) {
					this.currentBill = billList[found]
				}
			} else {
				this.currentBill = this.bills[cospend.currentProjectId][billid]
			}
			this.mode = 'edition'
		},
		getProjects() {
			this.projectsLoading = true
			network.getProjects().then((response) => {
				if (!cospend.pageIsPublic) {
					response.data.forEach((proj) => { this.addProject(proj) })
					if (cospend.urlProjectId && cospend.urlProjectId in this.projects) {
						this.selectProject(cospend.urlProjectId, false)
					} else if (cospend.restoredCurrentProjectId !== null && cospend.restoredCurrentProjectId in this.projects) {
						this.selectProject(cospend.restoredCurrentProjectId, false)
					}
				} else {
					if (!response.data.myaccesslevel) {
						response.data.myaccesslevel = response.data.guestaccesslevel
					}
					this.addProject(response.data)
					this.selectProject(response.data.id, false)
				}
				this.projectsLoading = false
			}).catch((error) => {
				console.debug(error)
				showError(
					t('cospend', 'Failed to get projects')
					+ ': ' + error.response.request.responseText
				)
			})
		},
		getBills(projectid, selectBillId = null, callback = null) {
			this.billsLoading = true
			const catFilter = this.selectedCategoryFilter
			const pmFilter = this.selectedPaymentModeFilter
			network.getBills(projectid, 0, 50, this.selectedMemberId, catFilter, pmFilter).then((response) => {
				this.currentProject.nbBills = response.data.nb_bills
				this.bills[projectid] = {}
				this.$set(this.billLists, projectid, response.data.bills)
				response.data.bills.forEach((bill) => {
					this.bills[projectid][bill.id] = bill
				})
				this.updateProjectInfo(projectid)
				if (selectBillId !== null && this.bills[projectid][selectBillId]) {
					this.currentBill = this.bills[projectid][selectBillId]
				}
				if (callback) {
					callback()
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to get bills')
					+ ': ' + error.response?.request?.responseText
				)
				console.error(error)
			}).then(() => {
				this.billsLoading = false
			})
		},
		loadMoreBills(projectid, state) {
			const catFilter = this.selectedCategoryFilter
			const pmFilter = this.selectedPaymentModeFilter
			network.getBills(projectid, this.billLists[projectid].length, 20, this.selectedMemberId, catFilter, pmFilter).then((response) => {
				this.currentProject.nbBills = response.data.nb_bills
				if (!response.data.bills || response.data.bills.length === 0) {
					state.complete()
				} else {
					this.$set(this.billLists, projectid, this.billLists[projectid].concat(response.data.bills))
					response.data.bills.forEach((bill) => {
						this.bills[projectid][bill.id] = bill
					})
					state.loaded()
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to get bills')
					+ ': ' + error.response?.request?.responseText
				)
			}).then(() => {
			})
		},
		addProject(proj) {
			cospend.members[proj.id] = {}
			this.$set(this.members, proj.id, cospend.members[proj.id])
			proj.members.forEach((member) => {
				cospend.members[proj.id][member.id] = member
				this.$set(this.members[proj.id], member.id, member)
				this.$set(this.members[proj.id][member.id], 'balance', proj.balance[member.id])
				this.$set(this.members[proj.id][member.id], 'color', rgbObjToHex(member.color).replace('#', ''))
			})

			cospend.bills[proj.id] = {}
			this.$set(this.bills, proj.id, cospend.bills[proj.id])

			cospend.billLists[proj.id] = []
			this.$set(this.billLists, proj.id, cospend.billLists[proj.id])

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
			network.createProject(name, id).then((response) => {
				this.$refs.billList?.toggleFilterMode(false, false)
				this.addProject(response.data)
				this.selectProject(response.data.id)
			}).catch((error) => {
				console.error(error)
				showError(
					t('cospend', 'Failed to create project')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		deleteProject(projectid) {
			network.deleteProject(projectid).then((response) => {
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
				if (this.currentProjectId === projectid) {
					this.deselectProject()
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete project')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		updateProjectInfo(projectid) {
			network.updateProjectInfo(projectid).then((response) => {
				this.projects[projectid].balance = response.data.balance
				let balance
				for (const memberid in response.data.balance) {
					balance = response.data.balance[memberid]
					this.$set(this.members[projectid][memberid], 'balance', balance)
				}
				this.updateProjectPrecision(projectid)

				this.projects[projectid].nb_bills = response.data.nb_bills
				this.projects[projectid].total_spent = response.data.total_spent
				this.projects[projectid].lastchanged = response.data.lastchanged
				// category order
				for (const cid in this.projects[projectid].categories) {
					this.projects[projectid].categories[cid].order = response.data.categories[cid]?.order
				}
				// payment mode order
				for (const pmid in this.projects[projectid].paymentmodes) {
					this.projects[projectid].paymentmodes[pmid].order = response.data.paymentmodes[pmid]?.order
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to update balances')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		onUpdateMaxPrecision() {
			this.updateProjectPrecision(this.currentProjectId)
		},
		updateProjectPrecision(projectid) {
			const balances = this.projects[projectid].balance
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
			network.createMember(projectid, name, userid).then((response) => {
				response.data.balance = 0
				response.data.color = rgbObjToHex(response.data.color).replace('#', '')
				this.$set(this.members[projectid], response.data.id, response.data)
				this.projects[projectid].members.unshift(response.data)
				showSuccess(t('cospend', 'Created member {name}', { name }))
				// add access to this user if it's not there already
				if (response.data.userid) {
					this.addParticipantAccess(projectid, response.data.id, response.data.userid)
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to add member')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		addParticipantAccess(projectid, memberid, userid) {
			const foundIndex = this.projects[projectid].shares.findIndex((access) => {
				return access.userid === userid && access.type === constants.SHARE_TYPE.USER
			})
			if (userid !== this.projects[projectid].userid && foundIndex === -1) {
				const sh = {
					user: userid,
					type: constants.SHARE_TYPE.USER,
					accesslevel: 2,
					manually_added: false,
				}
				network.addSharedAccess(projectid, sh).then((response) => {
					const newShAccess = {
						accesslevel: sh.accesslevel,
						type: sh.type,
						name: response.data.name,
						userid: sh.user,
						id: response.data.id,
						manually_added: sh.manually_added,
					}
					this.projects[projectid].shares.push(newShAccess)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to add shared access')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
					)
				})
			}
		},
		deleteNewBill() {
			const billList = this.billLists[cospend.currentProjectId]
			const newBill = billList.find((bill) => {
				return bill.id === 0
			})
			if (newBill) {
				this.onBillDeleted(newBill)
			}
		},
		onMemberEdited(projectid, memberid) {
			this.deleteNewBill()
			const member = this.members[projectid][memberid]
			network.editMember(projectid, member).then((response) => {
				this.editMemberSuccess(projectid, memberid, response.data)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to save member')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
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
				this.updateProjectInfo(cospend.currentProjectId)
				// add access to this user if it's not there already
				if (member.userid) {
					this.addParticipantAccess(projectid, memberid, member.userid)
				}
			}
		},
		editProject(projectid, password = null) {
			const project = this.projects[projectid]
			network.editProject(project, password).then((response) => {
				if (password && cospend.pageIsPublic) {
					cospend.password = password
				}
				this.updateProjectInfo(cospend.currentProjectId)
				showSuccess(t('cospend', 'Project saved'))
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to edit project')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
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
		onPaymentModeDeleted(pmid) {
			let bill
			for (const bid in this.bills[this.currentProjectId]) {
				bill = this.bills[this.currentProjectId][bid]
				if (bill.paymentmodeid === pmid) {
					bill.paymentmodeid = 0
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
	position: fixed !important;
	top: 56px;
	right: 6px;
	button {
		width: 44px;
		height: 44px;
		margin: 0;
		background-color: transparent;
		border: none;
		&:hover {
			background-color: var(--color-background-dark);
		}
	}
}

#app-content-wrapper {
	display: flex;
}

::v-deep .central-empty-content {
	margin-left: auto;
	margin-right: auto;

	.empty-content__icon {
		mask-size: 64px auto;
		-webkit-mask-size: 64px auto;
	}
}

::v-deep .icon-cospend {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../img/app_black.svg') no-repeat;
	mask-size: 18px auto;
	mask-position: center;
	-webkit-mask: url('./../img/app_black.svg') no-repeat;
	-webkit-mask-size: 18px auto;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
}

::v-deep .icon-currencies {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../img/currency.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center;
	-webkit-mask: url('./../img/currency.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
}

::v-deep .icon-reimburse {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../img/reimburse.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center;
	-webkit-mask: url('./../img/reimburse.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
}

::v-deep .icon-save {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../img/save.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center;
	-webkit-mask: url('./../img/save.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center;
}
</style>

<style>
	#content * {
		box-sizing: border-box;
	}
</style>
