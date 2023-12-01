<template>
	<NcContent app-name="cospend">
		<CospendNavigation
			:projects="projects"
			:selected-project-id="currentProjectId"
			:selected-member-id="selectedMemberId"
			:trashbin-enabled="trashbinEnabled"
			:loading="projectsLoading" />
		<NcAppContent
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
					:total-bill-number="totalBillNumber"
					:bills="currentBills"
					:selected-bill-id="selectedBillId"
					:edition-access="editionAccess"
					:trashbin-enabled="trashbinEnabled"
					:selected-category-id-filter="selectedCategoryFilter"
					:selected-payment-mode-id-filter="selectedPaymentModeFilter"
					@reset-filters="onResetFilters"
					@set-category-filter="onSetCategoryFilter"
					@set-paymentmode-filter="onSetPaymentModeFilter"
					@load-more-bills="loadMoreBills"
					@item-clicked="onBillClicked"
					@items-deleted="onBillsDeleted"
					@multi-bill-edit="onMultiBillEdit"
					@reset-selection="onResetSelection"
					@duplicate-bill="onDuplicateBill"
					@new-bill-clicked="onNewBillClicked"
					@move-bill-clicked="onMoveBillClicked" />
			</template>
			<div class="content-details-wrapper">
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
				<NcModal v-if="showMoveModal"
					size="normal"
					@close="showMoveModal = false">
					<MoveToProjectList
						:bill="billToMove"
						:project-id="currentProjectId"
						@item-moved="onBillMoved" />
				</NcModal>
				<Statistics
					v-else-if="mode === 'stats'"
					:project-id="currentProjectId" />
				<Settlement
					v-else-if="mode === 'settle'"
					:project-id="currentProjectId"
					@auto-settled="onAutoSettled" />
				<NcEmptyContent v-show="mode === 'normal' && currentProjectId"
					class="central-empty-content"
					:name="t('cospend', 'What do you want to do?')"
					:title="t('cospend', 'What do you want to do?')"
					:description="t('cospend', 'These actions are also available in the sidebar project context menu.')">
					<template #icon>
						<CospendIcon />
					</template>
				</NcEmptyContent>
				<div v-show="mode === 'normal' && currentProjectId"
					class="project-actions">
					<NcButton
						@click="onNewBillClicked(null)">
						<template #icon>
							<PlusIcon />
						</template>
						{{ t('cospend', 'Create a bill') }}
					</NcButton>
					<NcButton
						@click="onDetailClicked(currentProjectId)">
						<template #icon>
							<CogIcon />
						</template>
						{{ t('cospend', 'Show project settings') }}
					</NcButton>
					<NcButton
						@click="onShareClicked(currentProjectId)">
						<template #icon>
							<ShareVariantIcon />
						</template>
						{{ t('cospend', 'Share the project') }}
					</NcButton>
					<NcButton
						@click="onTrashbinClicked(currentProjectId)">
						<template #icon>
							<DeleteVariantIcon />
						</template>
						{{ trashbinEnabled ? t('cospend', 'Close the trashbin') : t('cospend', 'Show the trashbin') }}
					</NcButton>
					<NcButton
						@click="onStatsClicked(currentProjectId)">
						<template #icon>
							<ChartLineIcon />
						</template>
						{{ t('cospend', 'Show project statistics') }}
					</NcButton>
					<NcButton
						@click="onSettleClicked(currentProjectId)">
						<template #icon>
							<ReimburseIcon />
						</template>
						{{ t('cospend', 'Show project settlement plan') }}
					</NcButton>
				</div>
				<NcEmptyContent v-show="mode === 'normal' && !currentProjectId"
					class="central-empty-content"
					:name="t('cospend', 'Select a project')"
					:title="t('cospend', 'Select a project')">
					<template #icon>
						<CospendIcon />
					</template>
				</NcEmptyContent>
				<div v-if="!isMobile"
					class="content-buttons">
					<NcButton
						:title="t('cospend', 'Toggle sidebar')"
						:aria-label="t('cospend', 'Toggle sidebar')"
						class="icon-menu"
						@click="onMainDetailClicked">
						<template #icon>
							<MenuIcon />
						</template>
					</NcButton>
				</div>
				<div @click="() => {}" />
			</div>
		</NcAppContent>
		<CospendSettingsDialog
			@update-max-precision="onUpdateMaxPrecision" />
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
			@new-member="onNewMember"
			@export-clicked="onExportClicked"
			@paymentmode-deleted="onPaymentModeDeleted"
			@category-deleted="onCategoryDeleted" />
	</NcContent>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import {
	showSuccess,
	showError,
	showInfo,
} from '@nextcloud/dialogs'

import cospend from './state.js'
import * as network from './network.js'
import * as constants from './constants.js'
import { rgbObjToHex, slugify } from './utils.js'

import DeleteVariantIcon from 'vue-material-design-icons/DeleteVariant.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

import ReimburseIcon from './components/icons/ReimburseIcon.vue'
import CospendIcon from './components/icons/CospendIcon.vue'

import Statistics from './components/statistics/Statistics.vue'
import Settlement from './Settlement.vue'
import CospendNavigation from './components/CospendNavigation.vue'
import CospendSettingsDialog from './components/CospendSettingsDialog.vue'
import BillForm from './BillForm.vue'
import BillList from './BillList.vue'
import Sidebar from './components/Sidebar.vue'
import MoveToProjectList from './components/MoveToProjectList.vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

export default {
	name: 'App',
	components: {
		ReimburseIcon,
		CospendIcon,
		CospendNavigation,
		CospendSettingsDialog,
		BillList,
		BillForm,
		Statistics,
		Settlement,
		Sidebar,
		NcContent,
		NcAppContent,
		NcEmptyContent,
		NcButton,
		MoveToProjectList,
		NcModal,
		PlusIcon,
		MenuIcon,
		CogIcon,
		ShareVariantIcon,
		ChartLineIcon,
		DeleteVariantIcon,
	},
	mixins: [isMobile],
	provide() {
		return {
		}
	},
	data() {
		return {
			mode: 'normal',
			trashbinEnabled: false,
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
			showMoveModal: false,
			billToMove: null,
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
				const result = this.billLists[this.currentProjectId]
				// not necessary anymore because we only get what we want from the server
				// by including filters in the query
				/*
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
				*/
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
		totalBillNumber() {
			return this.trashbinEnabled
				? this.currentProject.nb_trashbin_bills || 0
				: this.currentProject.nb_bills || 0
		},
	},
	created() {
		this.getProjects()
	},
	mounted() {
		subscribe('nextcloud:unified-search.search', this.filter)
		subscribe('nextcloud:unified-search.reset', this.cleanSearch)

		subscribe('project-clicked', this.onProjectClicked)
		subscribe('delete-project', this.onDeleteProject)
		subscribe('archive-project', this.onArchiveProject)
		subscribe('project-imported', this.onProjectImported)
		subscribe('stats-clicked', this.onStatsClicked)
		subscribe('settle-clicked', this.onSettleClicked)
		subscribe('detail-clicked', this.onDetailClicked)
		subscribe('share-clicked', this.onShareClicked)
		subscribe('new-member-clicked', this.onNewMemberClicked)
		subscribe('member-edited', this.onMemberEdited)
		subscribe('create-project', this.onCreateProject)
		subscribe('save-option', this.onSaveOption)
		subscribe('member-click', this.onNavMemberClick)
		subscribe('restore-bill', this.onRestoreBill)
		subscribe('restore-bills', this.onRestoreBills)
		subscribe('delete-bill', this.onDeleteBill)

		subscribe('trashbin-clicked', this.onTrashbinClicked)
		subscribe('close-trashbin', this.onCloseTrashbinClicked)
		subscribe('clear-trashbin-clicked', this.onClearTrashbinClicked)
	},
	beforeDestroy() {
		unsubscribe('nextcloud:unified-search.search', this.filter)
		unsubscribe('nextcloud:unified-search.reset', this.cleanSearch)

		unsubscribe('project-clicked', this.onProjectClicked)
		unsubscribe('delete-project', this.onDeleteProject)
		unsubscribe('archive-project', this.onArchiveProject)
		unsubscribe('project-imported', this.onProjectImported)
		unsubscribe('stats-clicked', this.onStatsClicked)
		unsubscribe('settle-clicked', this.onSettleClicked)
		unsubscribe('detail-clicked', this.onDetailClicked)
		unsubscribe('share-clicked', this.onShareClicked)
		unsubscribe('new-member-clicked', this.onNewMemberClicked)
		unsubscribe('member-edited', this.onMemberEdited)
		unsubscribe('create-project', this.onCreateProject)
		unsubscribe('save-option', this.onSaveOption)
		unsubscribe('member-click', this.onNavMemberClick)
		unsubscribe('restore-bill', this.onRestoreBill)
		unsubscribe('restore-bills', this.onRestoreBills)
		unsubscribe('delete-bill', this.onDeleteBill)

		unsubscribe('trashbin-clicked', this.onTrashbinClicked)
		unsubscribe('close-trashbin', this.onCloseTrashbinClicked)
		unsubscribe('clear-trashbin-clicked', this.onClearTrashbinClicked)
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
			this.getBills(cospend.currentProjectId, null, null, null, this.trashbinEnabled)
		},
		onNavMemberClick({ projectId, memberId }) {
			if (this.selectedMemberId === memberId) {
				this.selectedMemberId = null
			} else if (this.currentProjectId === projectId) {
				this.selectedMemberId = memberId
			}
			// deselect current bill
			this.currentBill = null
			// we load bills from scratch to make sure we get the correct total number of bills
			// and infinite scroll works fine
			this.getBills(cospend.currentProjectId, null, null, null, this.trashbinEnabled)
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
				this.selectProject(projectid, true, true)
			}
			const sameTab = this.activeSidebarTab === 'project-settings'
			this.showSidebar = (sameProj && sameTab) ? !this.showSidebar : true
			this.activeSidebarTab = 'project-settings'
		},
		onShareClicked(projectid) {
			const sameProj = cospend.currentProjectId === projectid
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid, true, true)
			}
			const sameTab = this.activeSidebarTab === 'sharing'
			this.showSidebar = (sameProj && sameTab) ? !this.showSidebar : true
			this.activeSidebarTab = 'sharing'
		},
		filter({ query }) {
			this.filterQuery = query
			this.onFilterChange()
		},
		cleanSearch() {
			this.filterQuery = null
			this.onFilterChange()
		},
		getFilteredBills(billList) {
			// Make sure to escape user input before creating regex from it:
			const cleanQuery = this.filterQuery.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&')
			const regex = new RegExp(cleanQuery, 'i')
			if (isNaN(this.filterQuery)) {
				return billList.filter(bill => {
					return regex.test(bill.what) || regex.test(bill.comment)
				})
			} else {
				const queryNumber = parseFloat(this.filterQuery)
				const amountMin = queryNumber - 1.0
				const amountMax = queryNumber + 1.0
				return billList.filter(bill => {
					return regex.test(bill.what) || regex.test(bill.comment)
						|| (bill.amount >= amountMin && bill.amount <= amountMax)
				})
			}
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
			this.currentProject.nb_bills++
			this.cleanupBills()
			if (select) {
				this.currentBill = bill
			}
			if (mode === 'normal') {
				this.updateProjectInfo(cospend.currentProjectId)
				if (!select) {
					this.currentBill = null
				}
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
		onBillSaved(bill, changedBill, updateProjectInfo = true) {
			Object.assign(bill, changedBill)
			// this avoids having both bill object sharing the same owerIds array (pseudo deep copy)
			bill.owerIds = [...changedBill.owerIds]
			if (updateProjectInfo) {
				this.updateProjectInfo(cospend.currentProjectId)
			}
		},
		onCustomBillsCreated() {
			this.currentBill = null
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onPersoBillsCreated() {
			this.currentBill = null
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onResetSelection() {
			this.currentBill = null
		},
		onDeleteBill(bill) {
			if (bill.id === 0) {
				this.onBillDeleted(bill)
			} else {
				network.deleteBill(cospend.currentProjectId, bill).then((response) => {
					this.onBillDeleted(bill)
					showSuccess(t('cospend', 'Bill deleted'))
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to delete bill')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		onBillsDeleted(billIds) {
			const billList = this.billLists[cospend.currentProjectId]
			billIds.forEach(id => {
				const index = billList.findIndex(bill => bill.id === id)
				billList.splice(index, 1)
			})
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onBillDeleted(bill) {
			const billList = this.billLists[cospend.currentProjectId]
			const billIndex = billList.findIndex(b => bill.id === b.id)
			if (billIndex !== -1) {
				billList.splice(billIndex, 1)
			}
			if (bill.id === this.selectedBillId) {
				this.currentBill = null
			}
			this.updateProjectInfo(cospend.currentProjectId)
		},
		onRestoreBill(bill) {
			network.restoreBill(cospend.currentProjectId, bill).then((response) => {
				showSuccess(t('cospend', 'Bill restored'))
				const billList = this.billLists[cospend.currentProjectId]
				billList.splice(billList.indexOf(bill), 1)
				if (bill.id === this.selectedBillId) {
					this.currentBill = null
				}
				this.updateProjectInfo(cospend.currentProjectId)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to restore bill')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		onRestoreBills(billIds) {
			network.restoreBills(cospend.currentProjectId, billIds).then((response) => {
				showSuccess(t('cospend', 'Bills restored'))
				const billList = this.billLists[cospend.currentProjectId]
				billIds.forEach(id => {
					const index = billList.findIndex(bill => bill.id === id)
					billList.splice(index, 1)
				})
				this.updateProjectInfo(cospend.currentProjectId)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete bills')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		onProjectClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.trashbinEnabled = false
				this.selectProject(projectid, true, true)
			} else if (this.selectedMemberId !== null) {
				// click on current selected project: deselect member
				this.selectedMemberId = null
				// deselect current bill
				this.currentBill = null
				// we load bills from scratch to make sure we get the correct total number of bills
				// and infinite scroll works fine
				this.getBills(cospend.currentProjectId)
			}
		},
		onDeleteProject(projectid) {
			this.deleteProject(projectid)
		},
		onArchiveProject(projectId) {
			this.archiveProject(projectId)
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
				this.selectProject(projectid, true, true)
			}
			this.currentBill = null
			this.mode = 'stats'
		},
		onSettleClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid, true, true)
			}
			this.currentBill = null
			this.mode = 'settle'
		},
		onTrashbinClicked(projectid) {
			if (cospend.currentProjectId === projectid && this.trashbinEnabled) {
				this.trashbinEnabled = false
			} else {
				this.trashbinEnabled = true
			}
			this.selectProject(projectid, true, true, false, true)
		},
		onCloseTrashbinClicked(projectid) {
			this.trashbinEnabled = false
			this.selectProject(projectid, true, true, false, false)
		},
		onClearTrashbinClicked(projectId) {
			network.clearTrashbin(projectId)
				.then(() => {
					showSuccess(t('cospend', 'Trashbin has been cleared'))
				})
			this.onCloseTrashbinClicked(projectId)
		},
		onNewMemberClicked(projectid) {
			if (cospend.currentProjectId !== projectid) {
				this.selectProject(projectid, true, true)
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
		selectProject(projectid, save = true, pushState = false, restoreSelectedBill = false, getBills = true) {
			this.mode = 'normal'
			this.currentBill = null
			this.selectedMemberId = null
			this.selectedCategoryFilter = null
			this.selectedPaymentModeFilter = null
			if (restoreSelectedBill) {
				this.getBills(projectid, cospend.restoredCurrentBillId, null, false, this.trashbinEnabled)
			} else {
				this.getBills(projectid, null, null, false, this.trashbinEnabled)
			}
			if (save) {
				network.saveOptionValue({ selectedProject: projectid })
			}
			cospend.currentProjectId = projectid
			if (pushState) {
				window.history.pushState(
					null,
					null,
					generateUrl('/apps/cospend/p/{projectId}', { projectId: cospend.currentProjectId }),
				)
			}
		},
		deselectProject() {
			this.mode = 'normal'
			this.currentBill = null
			cospend.currentProjectId = null
		},
		onAutoSettled(projectid) {
			this.getBills(projectid)
		},
		onMoveBillClicked(bill) {
			if (this.isBillMovable(bill)) {
				this.showMoveModal = true
				this.billToMove = bill
			} else {
				showError(t('cospend', 'Impossible to move bill. No candidate project found.'))
			}
		},
		isBillMovable(bill) {
			// find a project with the same payer name
			const payerName = this.currentMembers[bill.payer_id].name
			let found = false
			// every() stops if lambda returns false
			Object.values(this.projects).every(p => {
				if (p.id !== this.currentProjectId) {
					if (this.projectHasMemberNamed(p.id, payerName)) {
						found = true
						return false
					}
				}
				return true
			})
			return found
		},
		projectHasMemberNamed(projectId, nameQuery) {
			const foundMember = Object.values(this.members[projectId]).find(m => {
				return m.name === nameQuery
			})
			return !!foundMember
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
					this.currentProject.nb_bills++
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
				window.history.pushState(
					null,
					null,
					generateUrl('/apps/cospend/p/{projectId}/b/0', { projectId: cospend.currentProjectId }),
				)
			}
		},
		onBillClicked(billId) {
			const billList = this.billLists[cospend.currentProjectId]
			if (billId === 0) {
				const found = billList.findIndex((bill) => { return bill.id === 0 })
				if (found !== -1) {
					this.currentBill = billList[found]
				}
			} else {
				this.currentBill = this.bills[cospend.currentProjectId][billId]
			}
			this.mode = 'edition'
			if (!cospend.pageIsPublic) {
				window.history.pushState(
					null,
					null,
					generateUrl('/apps/cospend/p/{projectId}/b/{billId}', {
						projectId: cospend.currentProjectId,
						billId,
					}),
				)
			}
		},
		onBillMoved(newBillId, newProjectId) {
			// close the modal
			this.showMoveModal = false
			// set the selected bill id
			cospend.restoredCurrentBillId = newBillId
			// select the project
			this.selectProject(newProjectId, false, true, true)
		},
		getProjects() {
			this.projectsLoading = true
			network.getProjects().then((response) => {
				if (!cospend.pageIsPublic) {
					response.data.forEach((proj) => { this.addProject(proj) })
					if (cospend.restoredCurrentProjectId !== null && cospend.restoredCurrentProjectId in this.projects) {
						this.selectProject(cospend.restoredCurrentProjectId, false, false, true)
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
					+ ': ' + error.response.request.responseText,
				)
			})
		},
		getBills(projectid, selectBillId = null, callback = null, pushState = true, deleted = false) {
			this.billsLoading = true
			const catFilter = this.selectedCategoryFilter
			const pmFilter = this.selectedPaymentModeFilter
			const searchTerm = this.filterQuery
			network.getBills(
				projectid, 0, 50, this.selectedMemberId,
				catFilter, pmFilter, selectBillId, searchTerm, deleted,
			).then((response) => {
				this.updateProjectInfo(projectid).then(() => {
					// update number of filtered bills after project info has been updated
					if (this.trashbinEnabled) {
						this.currentProject.nb_trashbin_bills = response.data.nb_bills
					} else {
						this.currentProject.nb_bills = response.data.nb_bills
					}
				})
				this.bills[projectid] = {}
				this.$set(this.billLists, projectid, response.data.bills)
				response.data.bills.forEach((bill) => {
					this.bills[projectid][bill.id] = bill
				})
				if (selectBillId !== null && this.bills[projectid][selectBillId]) {
					this.currentBill = this.bills[projectid][selectBillId]
					this.mode = 'edition'
					if (pushState) {
						window.history.pushState(
							null,
							null,
							generateUrl('/apps/cospend/p/{projectId}/b/{billId}', {
								projectId: cospend.currentProjectId,
								billId: selectBillId,
							}),
						)
					}
				}
				if (callback) {
					callback()
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to get bills')
					+ ': ' + error.response?.request?.responseText,
				)
				console.error(error)
			}).then(() => {
				this.billsLoading = false
			})
		},
		loadMoreBills(projectid, state, deleted = false) {
			const catFilter = this.selectedCategoryFilter
			const pmFilter = this.selectedPaymentModeFilter
			const searchTerm = this.filterQuery
			network.getBills(
				projectid, this.billLists[projectid].length, 20, this.selectedMemberId,
				catFilter, pmFilter, null, searchTerm, deleted,
			).then((response) => {
				// update number of filtered bills
				if (this.trashbinEnabled) {
					this.currentProject.nb_trashbin_bills = response.data.nb_bills
				} else {
					this.currentProject.nb_bills = response.data.nb_bills
				}
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
					+ ': ' + error.response?.request?.responseText,
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
			this.selectProject(project.id, true, true)
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
				this.selectProject(response.data.id, true, true)
			}).catch((error) => {
				console.error(error)
				showError(
					t('cospend', 'Failed to create project')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		archiveProject(projectId, password = null) {
			this.$set(this.projects[projectId], 'archived', this.projects[projectId].archived ? null : 'currentDateTime')
			this.editProject(projectId, password, true)
		},
		updateProjectInfo(projectid) {
			return network.updateProjectInfo(projectid).then((response) => {
				this.projects[projectid].balance = response.data.balance
				let balance
				for (const memberid in response.data.balance) {
					balance = response.data.balance[memberid]
					this.$set(this.members[projectid][memberid], 'balance', balance)
				}
				this.updateProjectPrecision(projectid)

				this.projects[projectid].nb_bills = response.data.nb_bills
				this.projects[projectid].nb_trashbin_bills = response.data.nb_trashbin_bills
				this.projects[projectid].total_spent = response.data.total_spent
				this.projects[projectid].lastchanged = response.data.lastchanged
				this.projects[projectid].categories = response.data.categories
				this.projects[projectid].paymentmodes = response.data.paymentmodes
				this.projects[projectid].archived = response.data.archived
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to update balances')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
		onMemberEdited({ projectId, memberId }) {
			this.deleteNewBill()
			const member = this.members[projectId][memberId]
			network.editMember(projectId, member).then((response) => {
				this.editMemberSuccess(projectId, memberId, response.data)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to save member')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
		editProject(projectId, password = null, deselectProject = false) {
			const project = this.projects[projectId]
			network.editProject(project, password).then((response) => {
				if (password && cospend.pageIsPublic) {
					cospend.password = password
				}
				this.updateProjectInfo(projectId).then(() => {
					showSuccess(t('cospend', 'Project saved'))
					if (deselectProject) {
						this.deselectProject()
					}
				})
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to edit project')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
	right: 14px;
}

#app-content-wrapper {
	display: flex;
}

::v-deep .central-empty-content {
	margin: 24px auto 24px auto;
}

.project-actions {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.iconButton {
	padding: 0;
}
</style>

<style>
	#content * {
		box-sizing: border-box;
	}
</style>
