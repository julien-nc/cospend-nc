<template>
	<NcAppContentList
		ref="list">
		<div class="list-header">
			<div class="list-header-header">
				<NcButton v-if="trashbinEnabled"
					variant="tertiary"
					:aria-label="t('cospend', 'Trash bin')">
					<template #icon>
						<DeleteVariantIcon class="header-trashbin-icon" />
					</template>
				</NcButton>
				<NcTextField
					v-model="billFilterQuery"
					:placeholder="t('cospend', 'Search bills')"
					:show-trailing-button="!['', null].includes(billFilterQuery)"
					@trailing-button-click="updateBillFilterQuery(''); billFilterQuery = ''"
					@input="onUpdateBillFilterQueryInput">
					<template #icon>
						<MagnifyIcon :size="20" />
					</template>
				</NcTextField>
				<NcActions :inline="1">
					<NcActionButton v-if="!trashbinEnabled && editionAccess && oneActiveMember"
						:close-after-click="true"
						@click="onAddBillClicked">
						<template #icon>
							<NotePlusIcon />
						</template>
						{{ t('cospend', 'Create a bill') }}
					</NcActionButton>
					<NcActionButton v-if="oneActiveMember && trashbinEnabled"
						:close-after-click="true"
						@click="onCloseTrashbinClicked">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('cospend', 'Close trashbin') }}
					</NcActionButton>
					<NcActionButton v-if="oneActiveMember && !trashbinEnabled"
						:close-after-click="true"
						@click="showTrashbin">
						<template #icon>
							<DeleteVariantIcon />
						</template>
						{{ t('cospend', 'Show the trash bin') }}
					</NcActionButton>
					<NcActionButton v-if="trashbinEnabled && editionAccess && bills.length > 0"
						:close-after-click="true"
						@click="showClearTrashBinConfirmation = true">
						<template #icon>
							<DeleteEmptyIcon />
						</template>
						{{ t('cospend', 'Clear trashbin') }}
					</NcActionButton>
					<NcActionButton v-if="bills.length > 0 || filterMode"
						:close-after-click="true"
						@click="toggleFilterMode">
						<template #icon>
							<CloseIcon v-if="filterMode" />
							<FilterIcon v-else />
						</template>
						{{ filterToggleText }}
					</NcActionButton>
					<NcActionButton v-if="(editionAccess && bills.length > 0) || selectMode"
						:close-after-click="true"
						@click="toggleSelectMode">
						<template #icon>
							<CloseIcon v-if="selectMode" />
							<FormatListCheckboxIcon v-else />
						</template>
						{{ multiToggleText }}
					</NcActionButton>
				</NcActions>
			</div>
			<Transition name="fade">
				<div v-if="filterMode"
					class="filterOptions">
					<div class="header">
						<FilterIcon class="icon" :size="20" />
						<span>{{ t('cospend', 'Filters') }}</span>
						<NcActions>
							<NcActionButton
								class="rightCloseButton"
								@click="toggleFilterMode(true, false)">
								<template #icon>
									<CloseIcon :size="20" />
								</template>
								{{ t('cospend', 'Close filters') }}
							</NcActionButton>
						</NcActions>
					</div>
					<div class="multiselect-container">
						<CategoryMultiSelect
							:value="selectedCategoryFilter"
							:categories="sortedFilterCategories"
							:placeholder="t('cospend', 'Select a category')"
							@input="onFilterCategoryChange" />
						<PaymentModeMultiSelect
							:value="selectedPaymentModeFilter"
							:payment-modes="sortedFilterPms"
							:placeholder="t('cospend', 'Select a payment mode')"
							@input="onFilterPaymentModeChange" />
					</div>
				</div>
			</Transition>
			<Transition name="fade">
				<div v-if="selectMode"
					class="selectionOptions">
					<div>
						<div class="header">
							<FormatListCheckboxIcon class="icon" :size="20" />
							<span>{{ t('cospend', 'Multi select actions') }}</span>
							<NcActions>
								<NcActionButton
									class="rightCloseButton"
									@click="toggleSelectMode">
									<template #icon>
										<CloseIcon :size="20" />
									</template>
									{{ t('cospend', 'Leave multiple select mode') }}
								</NcActionButton>
							</NcActions>
						</div>
						<div class="multiselect-container">
							<CategoryMultiSelect
								:disabled="selectedBillIds.length === 0 || !editionAccess"
								:value="selectedCategoryMultiAction"
								:categories="sortedMultiActionCategories"
								:placeholder="t('cospend', 'Assign a category')"
								@input="onMultiActionCategoryChange" />
							<PaymentModeMultiSelect
								:disabled="selectedBillIds.length === 0 || !editionAccess"
								:value="selectedPaymentModeMultiAction"
								:payment-modes="sortedMultiActionPms"
								:placeholder="t('cospend', 'Assign a payment mode')"
								@input="onMultiActionPaymentModeChange" />
							<div class="multiSelectFooter">
								<span v-show="selectedBillIds.length === 0">
									<InformationOutlineIcon :size="20" />
									&nbsp;
									{{ t('cospend', 'Select bills to make grouped actions') }}
								</span>
								<NcActions v-show="deletionEnabled || trashbinEnabled"
									class="multi-actions"
									:inline="2">
									<NcActionButton v-if="trashbinEnabled && selectedBillIds.length > 0"
										class="multiRestore"
										@click="showRestorationConfirmation = true">
										<template #icon>
											<RestoreIcon />
										</template>
										{{ t('cospend', 'Restore selected bills') }}
									</NcActionButton>
									<NcActionButton v-if="selectedBillIds.length > 0"
										class="multiDelete"
										@click="showDeletionConfirmation = true">
										<template #icon>
											<DeleteIcon />
										</template>
										{{ multiDeleteLabel }}
									</NcActionButton>
								</NcActions>
							</div>
						</div>
					</div>
				</div>
			</Transition>
		</div>
		<NcEmptyContent v-if="!oneActiveMember"
			:name="t('cospend', 'No member')"
			:description="t('cospend', 'Add at least one member to start creating bills')">
			<template #icon>
				<AccountIcon />
			</template>
			<template #action>
				<NcButton @click="onAddMemberClicked">
					<template #icon>
						<AccountPlusIcon />
					</template>
					{{ t('cospend', 'Add a member') }}
				</NcButton>
			</template>
		</NcEmptyContent>
		<NcEmptyContent v-else-if="bills.length === 0 && !loading"
			:name="t('cospend', 'No bills to show')">
			<template #icon>
				<CospendIcon />
			</template>
			<template v-if="!trashbinEnabled && editionAccess && oneActiveMember" #action>
				<NcButton @click="onAddBillClicked">
					<template #icon>
						<NotePlusIcon />
					</template>
					{{ t('cospend', 'Create a bill') }}
				</NcButton>
			</template>
		</NcEmptyContent>
		<NcLoadingIcon v-if="loading" :size="24" />
		<BillListItem
			v-for="(bill, index) in bills"
			v-else
			:key="bill.id"
			:bill="bill"
			:project-id="projectId"
			:index="nbBills - index"
			:nb-bills="nbBills"
			:selected="isBillSelected(bill)"
			:edition-access="editionAccess"
			:select-mode="selectMode"
			@clicked="onItemClicked"
			@move="onItemMove(bill)"
			@duplicate-bill="$emit('duplicate-bill', $event)" />
		<InfiniteLoading v-if="!loading && bills.length > 30"
			:identifier="projectId"
			@infinite="infiniteHandler">
			<template #no-results>
				{{ t('cospend', 'No more bills') }}
			</template>
			<template #no-more>
				{{ t('cospend', 'No more bills') }}
			</template>
		</InfiniteLoading>
		<NcDialog v-model:open="showDeletionConfirmation"
			:name="t('cospend', 'Confirm deletion')"
			:message="deletionConfirmationMessage">
			<template #actions>
				<NcButton
					@click="showDeletionConfirmation = false">
					{{ t('cospend', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="warning"
					@click="confirmedDeleteSelection">
					<template #icon>
						<DeleteIcon />
					</template>
					{{ t('cospend', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-model:open="showRestorationConfirmation"
			:name="t('cospend', 'Confirm restoration')"
			:message="restorationConfirmationMessage">
			<template #actions>
				<NcButton
					@click="showRestorationConfirmation = false">
					{{ t('cospend', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="warning"
					@click="confirmedRestoreSelection">
					<template #icon>
						<RestoreIcon />
					</template>
					{{ t('cospend', 'Restore') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-model:open="showClearTrashBinConfirmation"
			:name="t('cospend', 'Confirm clear trash bin')"
			:message="clearTrashBinConfirmationMessage">
			<template #actions>
				<NcButton
					@click="showClearTrashBinConfirmation = false">
					{{ t('cospend', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="error"
					@click="clearTrashBin">
					<template #icon>
						<DeleteIcon />
					</template>
					{{ t('cospend', 'Clear') }}
				</NcButton>
			</template>
		</NcDialog>
	</NcAppContentList>
</template>

<script>
import DeleteEmptyIcon from 'vue-material-design-icons/DeleteEmpty.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DeleteVariantIcon from 'vue-material-design-icons/DeleteVariant.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FormatListCheckboxIcon from 'vue-material-design-icons/FormatListCheckbox.vue'
import NotePlusIcon from 'vue-material-design-icons/NotePlus.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'

import CospendIcon from './components/icons/CospendIcon.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAppContentList from '@nextcloud/vue/components/NcAppContentList'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import PaymentModeMultiSelect from './components/PaymentModeMultiSelect.vue'
import CategoryMultiSelect from './components/CategoryMultiSelect.vue'
import BillListItem from './components/BillListItem.vue'

import InfiniteLoading from '@codog/vue3-infinite-loading'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import debounce from 'debounce'
import * as network from './network.js'
import * as constants from './constants.js'
import { strcmp } from './utils.js'

export default {
	name: 'BillList',

	components: {
		BillListItem,
		CospendIcon,
		NcAppContentList,
		NcActions,
		NcActionButton,
		NcEmptyContent,
		NcDialog,
		NcButton,
		NcTextField,
		NcLoadingIcon,
		InfiniteLoading,
		PaymentModeMultiSelect,
		CategoryMultiSelect,
		NotePlusIcon,
		CloseIcon,
		DeleteIcon,
		FilterIcon,
		FormatListCheckboxIcon,
		InformationOutlineIcon,
		DeleteVariantIcon,
		RestoreIcon,
		DeleteEmptyIcon,
		MagnifyIcon,
		AccountPlusIcon,
		AccountIcon,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
		totalBillNumber: {
			type: Number,
			required: true,
		},
		bills: {
			type: Array,
			required: true,
		},
		selectedBillId: {
			type: Number,
			required: true,
		},
		editionAccess: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
		trashbinEnabled: {
			type: Boolean,
			default: false,
		},
		selectedCategoryIdFilter: {
			type: Number,
			default: null,
		},
		selectedPaymentModeIdFilter: {
			type: Number,
			default: null,
		},
	},

	data() {
		return {
			cospend: OCA.Cospend.state,
			selectMode: false,
			selectedCategoryMultiAction: null,
			selectedPaymentModeMultiAction: null,
			selectedBillIds: [],
			filterMode: false,
			showDeletionConfirmation: false,
			showRestorationConfirmation: false,
			showClearTrashBinConfirmation: false,
			billFilterQuery: '',
		}
	},

	computed: {
		project() {
			return this.cospend.projects[this.projectId]
		},
		nbBills() {
			return this.totalBillNumber
		},
		reverseBills() {
			return this.bills.slice().reverse()
		},
		oneActiveMember() {
			let c = 0
			const members = this.cospend.projects[this.projectId].members
			for (const mid in members) {
				if (members[mid].activated) {
					c++
				}
			}
			return (c >= 1)
		},
		categories() {
			return this.cospend.projects[this.projectId].categories
		},
		sortedPaymentModes() {
			const allPaymentModes = Object.values(this.cospend.projects[this.projectId].paymentmodes)
			// TODO use specific sort order for pm instead of category one
			return [
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
			].includes(this.project.paymentmodesort)
				? allPaymentModes.sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
				: this.project.paymentmodesort === constants.SORT_ORDER.ALPHA
					? allPaymentModes.sort((a, b) => {
						return strcmp(a.name, b.name)
					})
					: allPaymentModes
		},
		sortedFilterPms() {
			return [
				{
					id: null,
					icon: '',
					name: t('cospend', 'All payment modes'),
				},
				{
					id: 0,
					icon: '',
					name: t('cospend', 'No payment mode'),
				},
				...this.sortedPaymentModes,
			]
		},
		selectedPaymentModeFilter() {
			return this.sortedFilterPms.find(pm => {
				return pm.id === this.selectedPaymentModeIdFilter
			})
		},
		sortedMultiActionPms() {
			return [
				{
					id: 0,
					icon: '',
					name: t('cospend', 'No payment mode'),
				},
				...this.sortedPaymentModes,
			]
		},
		sortedCategories() {
			const allCategories = Object.values(this.cospend.projects[this.projectId].categories)
			return [
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
			].includes(this.project.categorysort)
				? allCategories.sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
				: this.project.categorysort === constants.SORT_ORDER.ALPHA
					? allCategories.sort((a, b) => {
						return strcmp(a.name, b.name)
					})
					: allCategories
		},
		sortedFilterCategories() {
			return [
				{
					id: null,
					icon: '',
					name: t('cospend', 'All categories'),
				},
				{
					id: 0,
					icon: '',
					name: t('cospend', 'No category'),
				},
				...this.sortedCategories,
				...Object.values(this.hardCodedCategories),
			]
		},
		selectedCategoryFilter() {
			return this.sortedFilterCategories.find(c => {
				return c.id === this.selectedCategoryIdFilter
			})
		},
		sortedMultiActionCategories() {
			return [
				{
					id: 0,
					icon: '',
					name: t('cospend', 'No category'),
				},
				...this.sortedCategories,
				...Object.values(this.hardCodedCategories),
			]
		},
		hardCodedCategories() {
			return this.cospend.hardCodedCategories
		},
		multiToggleText() {
			return this.selectMode
				? t('cospend', 'Leave multiple selection mode')
				: t('cospend', 'Enter multiple selection mode')
		},
		filterToggleText() {
			return this.filterMode
				? t('cospend', 'Close filters')
				: t('cospend', 'Open filters')
		},
		deletionEnabled() {
			return !this.cospend.projects[this.projectId].deletiondisabled
		},
		multiDeleteLabel() {
			return this.trashbinEnabled
				? t('cospend', 'Move selected bills to trash')
				: t('cospend', 'Delete selected bills')
		},
		deletionConfirmationMessage() {
			return this.trashbinEnabled
				? n('cospend',
					'Are you sure you want to delete {nb} bill?',
					'Are you sure you want to delete {nb} bills?',
					this.selectedBillIds.length,
					{ nb: this.selectedBillIds.length },
				)
				: n('cospend',
					'Are you sure you want to move {nb} bill to the trash bin?',
					'Are you sure you want to move {nb} bills to the trash bin?',
					this.selectedBillIds.length,
					{ nb: this.selectedBillIds.length },
				)
		},
		restorationConfirmationMessage() {
			return n('cospend',
				'Are you sure you want to restore {nb} bill?',
				'Are you sure you want to restore {nb} bills?',
				this.selectedBillIds.length,
				{ nb: this.selectedBillIds.length },
			)
		},
		clearTrashBinConfirmationMessage() {
			return n('cospend',
				'Are you sure you want to clear the trash bin? ({nb} bill)',
				'Are you sure you want to clear the trash bin? ({nb} bills)',
				this.bills.length,
				{ nb: this.bills.length },
			)
		},
	},

	watch: {
		projectId() {
			this.selectMode = false
			this.filterMode = false
			this.selectedBillIds = []
			this.$refs.list?.$el.scrollTo(0, 0)
		},
	},

	methods: {
		infiniteHandler($state) {
			this.$emit('load-more-bills', this.projectId, $state, this.trashbinEnabled)
		},
		isBillSelected(bill) {
			if (this.selectMode) {
				return this.selectedBillIds.includes(bill.id)
			} else {
				return bill.id === this.selectedBillId
			}
		},
		onUpdateBillFilterQueryInput: debounce(function(e) {
			this.updateBillFilterQuery(e.target.value)
		}, 2000),
		updateBillFilterQuery(query) {
			emit('bill-search', { query })
		},
		onCloseTrashbinClicked() {
			this.selectedBillIds = []
			emit('close-trashbin', this.projectId)
		},
		onAddMemberClicked() {
			emit('new-member-clicked', this.projectId)
		},
		onAddBillClicked() {
			if (!this.editionAccess || !this.oneActiveMember) {
				return
			}
			this.$refs.list?.$el.scrollTo(0, 0)
			this.$emit('new-bill-clicked')
		},
		onItemClicked(bill) {
			if (this.selectMode) {
				if (this.isBillSelected(bill)) {
					const i = this.selectedBillIds.findIndex((id) => id === bill.id)
					this.selectedBillIds.splice(i, 1)
				} else {
					this.selectedBillIds.push(bill.id)
				}
			} else {
				this.$emit('item-clicked', bill.id)
			}
		},
		onItemMove(bill) {
			this.$emit('move-bill-clicked', bill)
		},
		showTrashbin() {
			this.selectedBillIds = []
			emit('trashbin-clicked', this.projectId)
		},
		clearTrashBin() {
			this.showClearTrashBinConfirmation = false
			this.selectedBillIds = []
			emit('clear-trashbin-clicked', this.projectId)
		},
		toggleFilterMode(emit = true, enabled = null) {
			if (enabled === null) {
				this.filterMode = !this.filterMode
			} else {
				this.filterMode = enabled
			}
			if (emit && !this.filterMode) {
				this.$emit('reset-filters')
			}
		},
		onFilterCategoryChange(selected) {
			if (selected !== null) {
				this.$emit('set-category-filter', selected.id)
			}
		},
		onFilterPaymentModeChange(selected) {
			if (selected !== null) {
				this.$emit('set-paymentmode-filter', selected.id)
			}
		},
		toggleSelectMode() {
			this.selectMode = !this.selectMode
			if (this.selectMode) {
				this.$emit('reset-selection')
			} else {
				this.selectedBillIds = []
			}
		},
		onMultiActionCategoryChange(selected) {
			if (selected === null) {
				return
			}
			const categoryid = selected.id
			if (this.selectedBillIds.length > 0) {
				network.editBills(this.projectId, this.selectedBillIds, categoryid, null).then((response) => {
					this.saveBillsSuccess(this.selectedBillIds, categoryid, null)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to save bills')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		onMultiActionPaymentModeChange(selected) {
			if (selected === null) {
				return
			}
			const paymentmodeid = selected.id
			if (this.selectedBillIds.length > 0) {
				network.editBills(this.projectId, this.selectedBillIds, null, paymentmodeid).then((response) => {
					this.saveBillsSuccess(this.selectedBillIds, null, paymentmodeid)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to save bills')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		saveBillsSuccess(billIds, categoryid, paymentmodeid) {
			this.$emit('multi-bill-edit', billIds, categoryid, paymentmodeid)
			showSuccess(t('cospend', 'Bills edited'))
		},
		confirmedDeleteSelection() {
			this.showDeletionConfirmation = false
			network.deleteBills(this.projectId, this.selectedBillIds).then((response) => {
				this.$emit('items-deleted', this.selectedBillIds)
				showSuccess(t('cospend', 'Bills deleted'))
				this.selectedBillIds = []
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete bills')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		confirmedRestoreSelection() {
			this.showRestorationConfirmation = false
			emit('restore-bills', this.selectedBillIds)
			this.selectedBillIds = []
		},
	},
}
</script>

<style scoped lang="scss">
.list-header {
	position: sticky;
	top: 0;
	z-index: 1000;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	.list-header-header {
		display: flex;
		gap: 4px;
		padding: var(--app-navigation-padding);
		padding-left: calc(var(--default-clickable-area) + 12px);

		.header-trashbin-icon {
			color: var(--color-error);
		}
	}

	.selectionOptions {
		select {
			margin-top: 5px;
		}
		> div {
			width: 100%;
			display: flex;
			flex-direction: column;
		}
		:deep(.multiDelete) {
			&:hover {
				color: var(--color-error);
			}
		}
		:deep(.multiRestore) {
			&:hover {
				color: var(--color-success);
			}
		}
		.multiSelectFooter {
			display: flex;
			align-items: center;
			span {
				display: flex;
				align-items: center;
			}
			.multi-actions {
				margin-left: auto;
			}
		}
	}

	.selectionOptions,
	.filterOptions {
		width: 100%;
		display: flex;
		flex-direction: column;
		align-items: center;
		border-top: 1px solid var(--color-border);
		padding: 0 0 10px 0;
		.header {
			display: flex;
			align-items: center;
			width: 100%;
			padding-right: 10px;
		}
		span.icon {
			width: 44px;
			height: 44px;
		}
		.rightCloseButton {
			margin-left: auto;
		}
		.multiselect-container {
			width: 100%;
			display: flex;
			flex-direction: column;
			padding: 0 10px 0 10px;
			gap: 12px;
		}
	}
}

.multiSelectHint {
	display: flex;
	width: 100%;
	min-height: 44px;
	padding: 10px 0 0 10px;
}

.fade-enter-active,
.fade-leave-active {
	transition: all var(--animation-slow);
}

.fade-enter-from,
.fade-leave-to {
	opacity: 0;
	height: 0px;
	transform: scaleY(0);
}
</style>
