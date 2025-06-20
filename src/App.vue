<template>
	<NcContent app-name="cospend">
		<CospendNavigation
			:projects="projects"
			:selected-project-id="currentProjectId"
			:selected-member-id="selectedMemberId"
			:trashbin-enabled="trashbinEnabled"
			:pending-invitations="pendingInvitations"
			:unreachable-projects="unreachableFederatedProject"
			:loading="projectsLoading" />
		<NcAppContent
			:list-max-width="isSidebarOpen ? 40 : 50"
			:list-min-width="isSidebarOpen ? 30 : 20"
			:list-size="isSidebarOpen ? 30 : 20"
			:show-details="shouldShowDetails"
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
				<NcEmptyContent v-show="showProjectEmptyContent"
					class="central-empty-content"
					:name="t('cospend', 'What do you want to do?')"
					:title="t('cospend', 'What do you want to do?')"
					:description="t('cospend', 'These actions are also available in the sidebar project context menu.')">
					<template #icon>
						<CospendIcon />
					</template>
				</NcEmptyContent>
				<div v-show="showProjectEmptyContent"
					class="project-actions">
					<NcButton v-if="currentProjectHasOneMember"
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
					<NcButton v-if="!cospend.pageIsPublic && currentProjectId && !isCurrentProjectFederated"
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
						{{ trashbinEnabled ? t('cospend', 'Close the trash bin') : t('cospend', 'Show the trash bin') }}
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
			:open="currentProjectId && isSidebarOpen"
			:active-tab="activeSidebarTab"
			@update:open="onSidebarUpdateOpen"
			@active-changed="onActiveSidebarTabChanged"
			@close="isSidebarOpen = false"
			@project-edited="onProjectEdited"
			@user-added="onNewMember"
			@new-member="onNewMember"
			@export-clicked="onExportClicked"
			@paymentmode-deleted="onPaymentModeDeleted"
			@category-deleted="onCategoryDeleted" />
	</NcContent>
</template>

<script>
import isMobile from './mixins/isMobile.js'

import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import {
	showSuccess,
	showError,
	showWarning,
	showInfo,
} from '@nextcloud/dialogs'

import * as network from './network.js'
import * as constants from './constants.js'
import { rgbObjToHex, slugify } from './utils.js'

import DeleteVariantIcon from 'vue-material-design-icons/DeleteVariant.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

import ReimburseIcon from './components/icons/ReimburseIcon.vue'
import CospendIcon from './components/icons/CospendIcon.vue'

import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import Statistics from './components/statistics/Statistics.vue'
import Settlement from './Settlement.vue'
import CospendNavigation from './components/CospendNavigation.vue'
import CospendSettingsDialog from './components/CospendSettingsDialog.vue'
import BillForm from './BillForm.vue'
import BillList from './BillList.vue'
import Sidebar from './components/Sidebar.vue'
import MoveToProjectList from './components/MoveToProjectList.vue'
// const Statistics = () => import('./components/statistics/Statistics.vue')
// const Settlement = () => import('./Settlement.vue')
// const CospendNavigation = () => import('./components/CospendNavigation.vue')
// const CospendSettingsDialog = () => import('./components/CospendSettingsDialog.vue')
// const BillForm = () => import('./BillForm.vue')
// const BillList = () => import('./BillList.vue')
// const Sidebar = () => import('./components/Sidebar.vue')
// const MoveToProjectList = () => import('./components/MoveToProjectList.vue')

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
		CogIcon,
		ShareVariantIcon,
		ChartLineIcon,
		DeleteVariantIcon,
	},
	mixins: [isMobile],
	provide() {
		return {
			isCurrentProjectFederated: () => this.isCurrentProjectFederated,
		}
	},
	data() {
		return {
			mode: 'normal',
			trashbinEnabled: false,
			cospend: OCA.Cospend.state,
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
			isSidebarOpen: false,
			activeSidebarTab: 'sharing',
			selectedMemberId: null,
			showMoveModal: false,
			billToMove: null,
			pendingInvitations: [],
			unreachableFederatedProject: [],
		}
	},
	computed: {
		shouldShowDetails() {
			return (this.currentBill && this.currentBill !== null) || !['edition', 'normal'].includes(this.mode)
		},
		showProjectEmptyContent() {
			return this.currentProjectId
				&& (this.mode === 'normal'
					|| (this.mode === 'edition' && this.currentBill === null))
		},
		currentProjectId() {
			console.debug('aaaaaaaaaaaaa COMPUTED currentProjectId', this.cospend.currentProjectId)
			return this.cospend.currentProjectId
		},
		currentProject() {
			console.debug('aaaaaaaaaaaaa COMPUTED currentProject', this.cospend.currentProjectId, this.currentProjectId)
			return this.projects[this.cospend.currentProjectId]
		},
		isCurrentProjectFederated() {
			return this.currentProject?.federated === true
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
		currentProjectHasOneMember() {
			return Object.keys(this.currentMembers).length > 0
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
				if (!this.cospend.pageIsPublic) {
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
		subscribe('nextcloud:unified-search:search', this.filter)
		subscribe('bill-search', this.filter)
		subscribe('nextcloud:unified-search:reset', this.cleanSearch)

		subscribe('project-clicked', this.onProjectClicked)
		subscribe('delete-project', this.onDeleteProject)
		subscribe('archive-project', this.archiveProject)
		subscribe('deselect-project', this.deselectProject)
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
		subscribe('clear-trashbin-clicked', this.onClearTrashBinClicked)

		subscribe('add-project', this.addProject)
		subscribe('remove-pending-invitation', this.removePendingInvitation)
		subscribe('remove-project', this.removeProject)
		subscribe('remove-unreachable-project', this.removeUnreachableProject)
	},
	beforeDestroy() {
		unsubscribe('nextcloud:unified-search:search', this.filter)
		unsubscribe('bill-search', this.filter)
		unsubscribe('nextcloud:unified-search:reset', this.cleanSearch)

		unsubscribe('project-clicked', this.onProjectClicked)
		unsubscribe('delete-project', this.onDeleteProject)
		unsubscribe('archive-project', this.archiveProject)
		unsubscribe('deselect-project', this.deselectProject)
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
		unsubscribe('clear-trashbin-clicked', this.onClearTrashBinClicked)

		unsubscribe('add-project', this.addProject)
		unsubscribe('remove-pending-invitation', this.removePendingInvitation)
		unsubscribe('remove-project', this.removeProject)
		unsubscribe('remove-unreachable-project', this.removeUnreachableProject)
	},
	methods: {
		removeUnreachableProject(invitationId) {
			const index = this.unreachableFederatedProject.findIndex(i => i.id === invitationId)
			if (index !== -1) {
				this.unreachableFederatedProject.splice(index, 1)
			}
		},
		removePendingInvitation(invitationId) {
			const index = this.pendingInvitations.findIndex(i => i.id === invitationId)
			if (index !== -1) {
				this.pendingInvitations.splice(index, 1)
			}
		},
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
			this.getBills(this.cospend.currentProjectId, null, null, null, this.trashbinEnabled)
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
			this.getBills(this.cospend.currentProjectId, null, null, null, this.trashbinEnabled)
		},
		onActiveSidebarTabChanged(newActive) {
			this.activeSidebarTab = newActive
		},
		onSidebarUpdateOpen(open) {
			this.isSidebarOpen = open
			this.activeSidebarTab = 'project-settings'
		},
		onDetailClicked(projectid) {
			const sameProj = this.cospend.currentProjectId === projectid
			if (this.cospend.currentProjectId !== projectid) {
				this.selectProject(projectid, true, true)
			}
			const sameTab = this.activeSidebarTab === 'project-settings'
			this.isSidebarOpen = (sameProj && sameTab) ? !this.isSidebarOpen : true
			this.activeSidebarTab = 'project-settings'
		},
		onShareClicked(projectid) {
			const sameProj = this.cospend.currentProjectId === projectid
			if (this.cospend.currentProjectId !== projectid) {
				this.selectProject(projectid, true, true)
			}
			const sameTab = this.activeSidebarTab === 'sharing'
			this.isSidebarOpen = (sameProj && sameTab) ? !this.isSidebarOpen : true
			this.activeSidebarTab = 'sharing'
		},
		filter({ query }) {
			this.filterQuery = query === '' ? null : query
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
			const i0 = this.billLists[this.cospend.currentProjectId].findIndex((bill) => { return bill.id === 0 })
			if (i0 !== -1) {
				this.billLists[this.cospend.currentProjectId].splice(i0, 1)
			}
		},
		onBillCreated(bill, select, mode) {
			this.bills[this.cospend.currentProjectId][bill.id] = bill
			this.billLists[this.cospend.currentProjectId].unshift(bill)
			this.currentProject.nb_bills++
			this.cleanupBills()
			if (select) {
				this.currentBill = bill
			}
			if (mode === 'normal') {
				this.updateProjectInfo(this.cospend.currentProjectId)
				if (!select) {
					this.currentBill = null
				}
			}
		},
		onMultiBillEdit(billIds, categoryid, paymentmodeid) {
			if (categoryid !== null) {
				billIds.forEach(id => {
					this.bills[this.cospend.currentProjectId][id].categoryid = categoryid
				})
			}
			if (paymentmodeid !== null) {
				billIds.forEach(id => {
					this.bills[this.cospend.currentProjectId][id].paymentmodeid = paymentmodeid
				})
			}
		},
		onRepeatBillNow(billId) {
			network.repeatBill(this.cospend.currentProjectId, billId).then((response) => {
				if (response.data.ocs.data.length > 0) {
					this.getBills(this.cospend.currentProjectId, billId)
					showSuccess(n('cospend', '{nb} bill was created', '{nb} bills were created', response.data.ocs.data.length, { nb: response.data.ocs.data.length }))
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
				this.updateProjectInfo(this.cospend.currentProjectId)
			}
		},
		onCustomBillsCreated() {
			this.currentBill = null
			this.updateProjectInfo(this.cospend.currentProjectId)
		},
		onPersoBillsCreated() {
			this.currentBill = null
			this.updateProjectInfo(this.cospend.currentProjectId)
		},
		onResetSelection() {
			this.currentBill = null
		},
		onDeleteBill(bill) {
			if (bill.id === 0) {
				this.onBillDeleted(bill)
			} else {
				network.deleteBill(this.cospend.currentProjectId, bill).then((response) => {
					this.onBillDeleted(bill)
					showSuccess(t('cospend', 'Bill deleted'))
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to delete bill')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		onBillsDeleted(billIds) {
			const billList = this.billLists[this.cospend.currentProjectId]
			billIds.forEach(id => {
				const index = billList.findIndex(bill => bill.id === id)
				billList.splice(index, 1)
			})
			this.updateProjectInfo(this.cospend.currentProjectId)
		},
		onBillDeleted(bill) {
			const billList = this.billLists[this.cospend.currentProjectId]
			const billIndex = billList.findIndex(b => bill.id === b.id)
			if (billIndex !== -1) {
				billList.splice(billIndex, 1)
			}
			if (bill.id === this.selectedBillId) {
				this.currentBill = null
			}
			this.updateProjectInfo(this.cospend.currentProjectId)
		},
		onRestoreBill(bill) {
			network.restoreBill(this.cospend.currentProjectId, bill).then((response) => {
				showSuccess(t('cospend', 'Bill restored'))
				const billList = this.billLists[this.cospend.currentProjectId]
				billList.splice(billList.indexOf(bill), 1)
				if (bill.id === this.selectedBillId) {
					this.currentBill = null
				}
				this.updateProjectInfo(this.cospend.currentProjectId)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to restore bill')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		onRestoreBills(billIds) {
			network.restoreBills(this.cospend.currentProjectId, billIds).then((response) => {
				showSuccess(t('cospend', 'Bills restored'))
				const billList = this.billLists[this.cospend.currentProjectId]
				billIds.forEach(id => {
					const index = billList.findIndex(bill => bill.id === id)
					billList.splice(index, 1)
				})
				this.updateProjectInfo(this.cospend.currentProjectId)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete bills')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		onProjectClicked(projectId) {
			if (this.cospend.currentProjectId !== projectId) {
				this.trashbinEnabled = false
				this.selectProject(projectId, true, true)
			} else if (this.selectedMemberId !== null) {
				// click on current selected project: deselect member
				this.selectedMemberId = null
				// deselect current bill
				this.currentBill = null
				// we load bills from scratch to make sure we get the correct total number of bills
				// and infinite scroll works fine
				this.getBills(this.cospend.currentProjectId)
			}
		},
		onDeleteProject(projectId) {
			this.deleteProject(projectId)
		},
		onExportClicked(projectId) {
			const projectName = this.projects[projectId].name
			const timeStamp = Math.floor(Date.now())
			const dateStr = moment(timeStamp).format('YYYY-MM-DD')
			const filename = projectId + '_' + dateStr + '.csv'
			network.exportProject(filename, projectId, projectName)
		},
		onStatsClicked(projectId) {
			if (this.cospend.currentProjectId !== projectId) {
				this.selectProject(projectId, true, true)
			}
			this.currentBill = null
			this.mode = 'stats'
		},
		onSettleClicked(projectId) {
			if (this.cospend.currentProjectId !== projectId) {
				this.selectProject(projectId, true, true)
			}
			this.currentBill = null
			this.mode = 'settle'
		},
		onTrashbinClicked(projectId) {
			if (this.cospend.currentProjectId === projectId && this.trashbinEnabled) {
				this.trashbinEnabled = false
			} else {
				this.trashbinEnabled = true
			}
			this.selectProject(projectId, true, true, false, true)
			// to show bill list instead of details view in mobile view
			this.mode = 'edition'
		},
		onCloseTrashbinClicked(projectId) {
			this.trashbinEnabled = false
			this.selectProject(projectId, true, true, false, false)
			// to show bill list instead of details view in mobile view
			this.mode = 'edition'
		},
		onClearTrashBinClicked(projectId) {
			network.clearTrashBin(projectId)
				.then(() => {
					showSuccess(t('cospend', 'Trashbin has been cleared'))
				})
			this.onCloseTrashbinClicked(projectId)
		},
		onNewMemberClicked(projectId) {
			if (this.cospend.currentProjectId !== projectId) {
				this.selectProject(projectId, true, true)
			}
			this.currentBill = null
			this.activeSidebarTab = 'project-settings'
			this.isSidebarOpen = true
			this.$nextTick(() => { this.$refs.sidebar?.focusOnAddMember() })
		},
		onNewMember(projectId, name, userid = null) {
			if (this.getMemberNames(projectId).includes(name)) {
				showError(t('cospend', 'Member {name} already exists', { name }))
			} else {
				this.createMember(projectId, name, userid)
			}
		},
		onProjectEdited(projectId, password = null) {
			this.editProject(projectId, password)
		},
		onSaveOption({ key, value }) {
			const ov = {}
			ov[key] = value
			network.saveOptionValues(ov)
		},
		getMemberNames(projectId) {
			const res = []
			for (const mid in this.members[projectId]) {
				res.push(this.members[projectId][mid].name)
			}
			return res
		},
		selectProject(projectId, save = true, pushState = false, restoreSelectedBill = false, getBills = true) {
			this.mode = 'normal'
			this.currentBill = null
			this.selectedMemberId = null
			this.selectedCategoryFilter = null
			this.selectedPaymentModeFilter = null
			if (restoreSelectedBill) {
				this.getBills(projectId, this.cospend.restoredCurrentBillId, null, false, this.trashbinEnabled)
			} else {
				this.getBills(projectId, null, null, false, this.trashbinEnabled)
			}
			if (save) {
				network.saveOptionValues({ selectedProject: projectId })
			}
			this.cospend.currentProjectId = projectId
			if (pushState) {
				window.history.pushState(
					null,
					null,
					generateUrl('/apps/cospend/p/{projectId}', { projectId: this.cospend.currentProjectId }),
				)
			}
		},
		deselectProject() {
			this.mode = 'normal'
			this.currentBill = null
			this.cospend.currentProjectId = null
		},
		onAutoSettled(projectId) {
			this.getBills(projectId)
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
				this.getBills(this.cospend.currentProjectId, null, () => { this.onNewBillClicked(bill) })
			} else {
				// find potentially existing new bill
				const billList = this.billLists[this.cospend.currentProjectId]
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
							repeatuntil: null,
							repeatallactive: 0,
							repeatfreq: 1,
							owers: [],
							owerIds,
							paymentmode: 'n',
							categoryid: 0,
							paymentmodeid: 0,
							comment: '',
						}
					}
					this.billLists[this.cospend.currentProjectId].unshift(this.currentBill)
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
					generateUrl('/apps/cospend/p/{projectId}/b/0', { projectId: this.cospend.currentProjectId }),
				)
			}
		},
		onBillClicked(billId) {
			const billList = this.billLists[this.cospend.currentProjectId]
			if (billId === 0) {
				const found = billList.findIndex((bill) => { return bill.id === 0 })
				if (found !== -1) {
					this.currentBill = billList[found]
				}
			} else {
				this.currentBill = this.bills[this.cospend.currentProjectId][billId]
			}
			this.mode = 'edition'
			if (!this.cospend.pageIsPublic) {
				window.history.pushState(
					null,
					null,
					generateUrl('/apps/cospend/p/{projectId}/b/{billId}', {
						projectId: this.cospend.currentProjectId,
						billId,
					}),
				)
			}
		},
		onBillMoved(newBillId, newProjectId) {
			// close the modal
			this.showMoveModal = false
			// set the selected bill id
			this.cospend.restoredCurrentBillId = newBillId
			// select the project
			this.selectProject(newProjectId, false, true, true)
		},
		getProjects() {
			this.projectsLoading = true
			network.getLocalProjects().then((response) => {
				const responseData = response.data.ocs.data
				if (!this.cospend.pageIsPublic) {
					responseData.forEach(project => { this.addProject(project) })
					if (this.cospend.restoredCurrentProjectId !== null && this.cospend.restoredCurrentProjectId in this.projects) {
						this.selectProject(this.cospend.restoredCurrentProjectId, false, false, true)
					}
				} else {
					this.addProject(responseData)
					this.selectProject(responseData.id, false)
				}
				this.projectsLoading = false
			}).catch((error) => {
				console.debug(error)
				showError(t('cospend', 'Failed to get projects'))
			})

			if (!this.cospend.pageIsPublic) {
				this.initFederationData()
			}
		},
		initFederationData() {
			network.getFederatedProjectIds().then(response => {
				console.debug('[cospend] federated projects', response.data.ocs.data)
				response.data.ocs.data.forEach(invite => {
					const federatedProjectId = invite.remoteProjectId + '@' + invite.remoteServerUrl
					network.getProjectInfo(federatedProjectId).then(response => {
						console.debug('---------- FEDERATED PROJECT', response.data.ocs.data)
						const project = response.data.ocs.data
						this.addProject(project)
						if (this.cospend.restoredCurrentProjectId !== null && this.cospend.restoredCurrentProjectId === project.id) {
							this.selectProject(this.cospend.restoredCurrentProjectId, false, false, true)
						}
					}).catch((error) => {
						console.error(error)
						showWarning(t('cospend', 'Failed to get federated project'))
						this.unreachableFederatedProject.push(invite)
					})
				})
			})

			network.getPendingInvitations().then(response => {
				console.debug('[cospend] pending shares', response.data.ocs.data)
				this.pendingInvitations = response.data.ocs.data
			}).catch((error) => {
				console.error(error)
				showError(t('cospend', 'Failed to get pending remote invitations'))
			})
		},
		getBills(projectId, selectBillId = null, callback = null, pushState = true, deleted = false) {
			this.billsLoading = true
			const catFilter = this.selectedCategoryFilter
			const pmFilter = this.selectedPaymentModeFilter
			const searchTerm = this.filterQuery
			network.getBills(
				projectId, 0, 50, this.selectedMemberId,
				catFilter, pmFilter, selectBillId, searchTerm, deleted,
			).then((response) => {
				const responseData = response.data.ocs.data
				this.updateProjectInfo(projectId).then(() => {
					// update number of filtered bills after project info has been updated
					if (this.trashbinEnabled) {
						this.currentProject.nb_trashbin_bills = responseData.nb_bills
					} else {
						this.currentProject.nb_bills = responseData.nb_bills
					}
				})
				this.bills[projectId] = {}
				this.billLists[projectId] = responseData.bills
				responseData.bills.forEach((bill) => {
					this.bills[projectId][bill.id] = bill
				})
				if (selectBillId !== null && this.bills[projectId][selectBillId]) {
					this.currentBill = this.bills[projectId][selectBillId]
					this.mode = 'edition'
					if (pushState) {
						window.history.pushState(
							null,
							null,
							generateUrl('/apps/cospend/p/{projectId}/b/{billId}', {
								projectId: this.cospend.currentProjectId,
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
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
				console.error(error)
			}).then(() => {
				this.billsLoading = false
			})
		},
		loadMoreBills(projectId, state, deleted = false) {
			const catFilter = this.selectedCategoryFilter
			const pmFilter = this.selectedPaymentModeFilter
			const searchTerm = this.filterQuery
			network.getBills(
				projectId, this.billLists[projectId].length, 20, this.selectedMemberId,
				catFilter, pmFilter, null, searchTerm, deleted,
			).then((response) => {
				const responseData = response.data.ocs.data
				// update number of filtered bills
				if (this.trashbinEnabled) {
					this.currentProject.nb_trashbin_bills = responseData.nb_bills
				} else {
					this.currentProject.nb_bills = responseData.nb_bills
				}
				if (!responseData.bills || responseData.bills.length === 0) {
					state.complete()
				} else {
					this.billLists[projectId] = this.billLists[projectId].concat(responseData.bills)
					responseData.bills.forEach((bill) => {
						this.bills[projectId][bill.id] = bill
					})
					state.loaded()
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to get bills')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			}).then(() => {
			})
		},
		addProject(proj) {
			this.cospend.members[proj.id] = {}
			this.members[proj.id] = this.cospend.members[proj.id]
			proj.members.forEach((member) => {
				this.cospend.members[proj.id][member.id] = member
				this.members[proj.id][member.id] = member
				this.members[proj.id][member.id].balance = proj.balance[member.id]
				this.members[proj.id][member.id].color = rgbObjToHex(member.color).replace('#', '')
			})

			this.cospend.bills[proj.id] = {}
			this.bills[proj.id] = this.cospend.bills[proj.id]

			this.cospend.billLists[proj.id] = []
			this.billLists[proj.id] = this.cospend.billLists[proj.id]

			this.cospend.projects[proj.id] = proj
			this.projects[proj.id] = proj
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
				this.addProject(response.data.ocs.data)
				this.selectProject(response.data.ocs.data.id, true, true)
			}).catch((error) => {
				console.error(error)
				showError(
					t('cospend', 'Failed to create project')
					+ ': ' + error.response?.data?.ocs?.data,
				)
			})
		},
		removeProject(projectId) {
			this.currentBill = null
			this.$delete(this.projects, projectId)
			this.$delete(this.bills, projectId)
			this.$delete(this.billLists, projectId)
			this.$delete(this.members, projectId)
			if (this.currentProjectId === projectId) {
				this.deselectProject()
			}
		},
		deleteProject(projectId) {
			network.deleteProject(projectId).then((response) => {
				this.currentBill = null
				this.$delete(this.projects, projectId)
				this.$delete(this.bills, projectId)
				this.$delete(this.billLists, projectId)
				this.$delete(this.members, projectId)

				if (this.cospend.pageIsPublic) {
					const redirectUrl = generateUrl('/apps/cospend/login')
					window.location.replace(redirectUrl)
				}
				showSuccess(t('cospend', 'Deleted project {id}', { id: projectId }))
				if (this.currentProjectId === projectId) {
					this.deselectProject()
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete project')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		archiveProject(projectId) {
			this.projects[projectId].archived_ts = this.projects[projectId].archived_ts ? constants.PROJECT_ARCHIVED_TS_UNSET : constants.PROJECT_ARCHIVED_TS_NOW
			this.editProject(projectId, null, true)
		},
		updateProjectInfo(projectId) {
			return network.getProjectInfo(projectId).then((response) => {
				const responseData = response.data.ocs.data
				this.projects[projectId].balance = responseData.balance
				let balance
				for (const memberid in responseData.balance) {
					balance = responseData.balance[memberid]
					this.members[projectId][memberid].balance = balance
				}
				this.updateProjectPrecision(projectId)

				this.projects[projectId].nb_bills = responseData.nb_bills
				this.projects[projectId].nb_trashbin_bills = responseData.nb_trashbin_bills
				this.projects[projectId].total_spent = responseData.total_spent
				this.projects[projectId].lastchanged = responseData.lastchanged
				this.projects[projectId].categories = responseData.categories
				this.projects[projectId].paymentmodes = responseData.paymentmodes
				this.projects[projectId].archived_ts = responseData.archived_ts
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to update balances')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		onUpdateMaxPrecision() {
			this.updateProjectPrecision(this.currentProjectId)
		},
		updateProjectPrecision(projectId) {
			const balances = this.projects[projectId].balance
			const balanceArray = Object.values(balances)
			let precision = 1
			let sum
			do {
				precision++
				sum = balanceArray.reduce((a, b) => parseFloat(a.toFixed(precision)) + parseFloat(b.toFixed(precision)), 0)
			} while (sum !== 0.0 && precision < this.cospend.maxPrecision)
			this.projects[projectId].precision = precision
		},
		createMember(projectId, name, userid = null) {
			const isProjectFederated = this.projects[projectId].federated === true
			// avoid adding local users as members in federated project, the user won't exist in the remote instance
			const newMemberUserId = isProjectFederated ? null : userid
			network.createMember(projectId, name, newMemberUserId).then((response) => {
				const responseData = response.data.ocs.data
				responseData.balance = 0
				responseData.color = rgbObjToHex(responseData.color).replace('#', '')
				this.members[projectId][responseData.id] = responseData
				this.projects[projectId].members.unshift(responseData)
				showSuccess(t('cospend', 'Created member {name}', { name }))
				// add access to this user if it's not there already
				if (responseData.userid) {
					this.addParticipantAccess(projectId, responseData.id, responseData.userid)
				}
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to add member')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		addParticipantAccess(projectId, memberid, userid) {
			const foundIndex = this.projects[projectId].shares.findIndex((access) => {
				return access.userid === userid && access.type === constants.SHARE_TYPE.USER
			})
			if (userid !== this.projects[projectId].userid && foundIndex === -1) {
				const sh = {
					user: userid,
					type: constants.SHARE_TYPE.USER,
					accesslevel: 2,
					manually_added: false,
				}
				network.createSharedAccess(projectId, sh).then((response) => {
					const newShAccess = {
						accesslevel: sh.accesslevel,
						type: sh.type,
						name: response.data.ocs.data.name,
						userid: sh.user,
						id: response.data.ocs.data.id,
						manually_added: sh.manually_added,
					}
					this.projects[projectId].shares.push(newShAccess)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to add shared access')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		deleteNewBill() {
			const billList = this.billLists[this.cospend.currentProjectId]
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
				this.editMemberSuccess(projectId, memberId, response.data.ocs.data)
			}).catch((error) => {
				console.error(error)
				showError(
					t('cospend', 'Failed to save member')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		editMemberSuccess(projectId, memberid, member) {
			if (!member) {
				// delete member
				this.$delete(this.members[projectId], memberid)
				const i = this.projects[projectId].members.findIndex((m) => m.id === memberid)
				if (i !== -1) {
					this.projects[projectId].members.splice(i, 1)
				}
				showSuccess(t('cospend', 'Member deleted'))
			} else {
				showSuccess(t('cospend', 'Member saved'))
				this.updateProjectInfo(this.cospend.currentProjectId)
				// add access to this user if it's not there already
				if (member.userid) {
					this.addParticipantAccess(projectId, memberid, member.userid)
				}
			}
		},
		editProject(projectId, password = null, deselectProject = false) {
			const project = this.projects[projectId]
			network.editProject(project, password).then((response) => {
				if (password && this.cospend.pageIsPublic) {
					this.cospend.password = password
				}
				this.updateProjectInfo(projectId).then(() => {
					showSuccess(t('cospend', 'Project saved'))
					if (deselectProject) {
						this.deselectProject()
					}
				})
			}).catch((error) => {
				showError(t('cospend', 'Failed to edit project'))
				console.error(error)
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
.content-buttons {
	position: fixed !important;
	top: 56px;
	right: 14px;
}

#app-content-wrapper {
	display: flex;
}

:deep(.central-empty-content) {
	margin: 24px auto 24px auto;
}

.project-actions {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.iconButton {
	padding: 0px;
}
</style>

<style>
	#content * {
		box-sizing: border-box;
	}
</style>
