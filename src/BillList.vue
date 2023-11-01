<template>
	<NcAppContentList
		ref="list">
		<div class="list-header">
			<NcAppNavigationItem
				v-show="!loading"
				class="addBillItem"
				:name="(editionAccess && oneActiveMember) ? t('cospend', 'New bill') : ''"
				:force-display-actions="editionAccess && oneActiveMember"
				@click="onAddBillClicked">
				<template #icon>
					<PlusIcon v-show="editionAccess && oneActiveMember" :size="20" />
				</template>
				<template #actions>
					<NcActionButton v-show="bills.length > 0 || filterMode"
						:close-after-click="true"
						@click="toggleFilterMode">
						<template #icon>
							<CloseIcon v-if="filterMode" :size="20" />
							<FilterIcon v-else :size="20" />
						</template>
						{{ filterToggleText }}
					</NcActionButton>
					<NcActionButton v-show="(editionAccess && bills.length > 0) || selectMode"
						:close-after-click="true"
						@click="toggleSelectMode">
						<template #icon>
							<CloseIcon v-if="selectMode" :size="20" />
							<FormatListCheckboxIcon v-else :size="20" />
						</template>
						{{ multiToggleText }}
					</NcActionButton>
				</template>
			</NcAppNavigationItem>
			<transition name="fade">
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
			</transition>
			<transition name="fade">
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
									<InformationVariantIcon :size="20" />
									{{ t('cospend', 'Select bills to make grouped actions') }}
								</span>
								<NcActions v-show="deletionEnabled">
									<NcActionButton
										class="multiDelete"
										@click="deleteSelection">
										<template #icon>
											<DeleteIcon :size="20" />
										</template>
										{{ t('cospend', 'Delete selected bills') }}
									</NcActionButton>
								</NcActions>
							</div>
						</div>
					</div>
				</div>
			</transition>
		</div>
		<h3 v-if="!oneActiveMember"
			class="nomember">
			{{ t('cospend', 'Add at least 2 members to start creating bills') }}
		</h3>
		<NcEmptyContent v-else-if="bills.length === 0 && !loading"
			:name="t('cospend', 'No bills to show')"
			:title="t('cospend', 'No bills to show')">
			<template #icon>
				<CospendIcon />
			</template>
		</NcEmptyContent>
		<h2 v-show="loading"
			class="icon-loading-small loading-icon" />
		<transition-group v-if="!loading" name="list">
			<BillListItem
				v-for="(bill, index) in bills"
				:key="bill.id"
				:bill="bill"
				:project-id="projectId"
				:index="nbBills - index"
				:nbbills="nbBills"
				:selected="isBillSelected(bill)"
				:edition-access="editionAccess"
				:select-mode="selectMode"
				@clicked="onItemClicked"
				@delete="onItemDeleted"
				@move="onItemMove(bill)"
				@duplicate-bill="$emit('duplicate-bill', $event)" />
		</transition-group>
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
	</NcAppContentList>
</template>

<script>
import InformationVariantIcon from 'vue-material-design-icons/InformationVariant.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FormatListCheckboxIcon from 'vue-material-design-icons/FormatListCheckbox.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import CospendIcon from './components/icons/CospendIcon.vue'

import NcAppContentList from '@nextcloud/vue/dist/Components/NcAppContentList.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import PaymentModeMultiSelect from './components/PaymentModeMultiSelect.vue'
import CategoryMultiSelect from './components/CategoryMultiSelect.vue'
import BillListItem from './components/BillListItem.vue'

import InfiniteLoading from 'vue-infinite-loading'
import { showSuccess, showError } from '@nextcloud/dialogs'
import cospend from './state.js'
import * as network from './network.js'
import * as constants from './constants.js'
import { strcmp } from './utils.js'

export default {
	name: 'BillList',

	components: {
		BillListItem,
		CospendIcon,
		NcAppContentList,
		NcAppNavigationItem,
		NcActions,
		NcActionButton,
		NcEmptyContent,
		InfiniteLoading,
		PaymentModeMultiSelect,
		CategoryMultiSelect,
		PlusIcon,
		CloseIcon,
		DeleteIcon,
		FilterIcon,
		FormatListCheckboxIcon,
		InformationVariantIcon,
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
		mode: {
			type: String,
			required: true,
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
			cospend,
			selectMode: false,
			selectedCategoryMultiAction: null,
			selectedPaymentModeMultiAction: null,
			selectedBillIds: [],
			filterMode: false,
		}
	},

	computed: {
		project() {
			return cospend.projects[this.projectId]
		},
		nbBills() {
			return this.totalBillNumber
		},
		reverseBills() {
			return this.bills.slice().reverse()
		},
		shouldShowDetails() {
			return (this.mode !== 'edition' || this.selectedBillId !== -1)
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
			return cospend.projects[this.projectId].categories
		},
		sortedPaymentModes() {
			const allPaymentModes = Object.values(cospend.projects[this.projectId].paymentmodes)
			// TODO use specific sort order for pm instead of category one
			return [
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.MOST_RECENTLY_USED,
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
			const allCategories = Object.values(cospend.projects[this.projectId].categories)
			return [
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.MOST_RECENTLY_USED,
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
			return cospend.hardCodedCategories
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
			return !cospend.projects[this.projectId].deletiondisabled
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
			this.$emit('load-more-bills', this.projectId, $state)
		},
		isBillSelected(bill) {
			if (this.selectMode) {
				return this.selectedBillIds.includes(bill.id)
			} else {
				return bill.id === this.selectedBillId
			}
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
		onItemDeleted(bill) {
			if (bill.id === 0) {
				this.$emit('item-deleted', bill)
			} else {
				this.deleteBill(bill)
			}
		},
		onItemMove(bill) {
			this.$emit('move-bill-clicked', bill)
		},
		deleteBill(bill) {
			network.deleteBill(this.projectId, bill).then((response) => {
				this.$emit('item-deleted', bill)
				showSuccess(t('cospend', 'Bill deleted'))
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete bill')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
				)
			})
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
				network.saveBills(this.projectId, this.selectedBillIds, categoryid, null).then((response) => {
					this.saveBillsSuccess(this.selectedBillIds, categoryid, null)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to save bills')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
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
				network.saveBills(this.projectId, this.selectedBillIds, null, paymentmodeid).then((response) => {
					this.saveBillsSuccess(this.selectedBillIds, null, paymentmodeid)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to save bills')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		saveBillsSuccess(billIds, categoryid, paymentmodeid) {
			this.$emit('multi-bill-edit', billIds, categoryid, paymentmodeid)
			showSuccess(t('cospend', 'Bills edited'))
		},
		deleteSelection() {
			if (this.selectedBillIds.length > 0) {
				OC.dialogs.confirmDestructive(
					n('cospend',
						'Are you sure you want to delete {nb} bill?',
						'Are you sure you want to delete {nb} bills?',
						this.selectedBillIds.length,
						{ nb: this.selectedBillIds.length },
					),
					t('cospend', 'Confirm deletion'),
					{
						type: OC.dialogs.YES_NO_BUTTONS,
						confirm: t('cospend', 'Delete'),
						confirmClasses: 'error',
						cancel: t('cospend', 'Cancel'),
					},
					(result) => {
						if (result) {
							network.deleteBills(this.projectId, this.selectedBillIds).then((response) => {
								this.$emit('items-deleted', this.selectedBillIds)
								showSuccess(t('cospend', 'Bills deleted'))
								this.selectedBillIds = []
							}).catch((error) => {
								showError(
									t('cospend', 'Failed to delete bills')
									+ ': ' + (error.response?.data?.message || error.response?.request?.responseText),
								)
							})
						}
					},
					true,
				)
			}
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

	.selectionOptions {
		select {
			margin-top: 5px;
		}
		.multiDelete {
			margin-left: auto;
			&:hover {
				color: var(--color-error);
			}
		}
		> div {
			width: 100%;
			display: flex;
			flex-direction: column;
		}
		.multiSelectFooter {
			display: flex;
			align-items: center;
			span {
				display: flex;
				align-items: center;
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

.addBillItem {
	padding-left: 40px;
}

.nobill, .nomember {
	text-align: center;
	color: var(--color-text-lighter);
	margin-top: 8px;
	margin-left: 40px;
}

.nomember {
	margin-top: 12px;
}

.loading-icon {
	margin-top: 16px;
}

.multiSelectHint {
	display: flex;
	width: 100%;
	min-height: 44px;
	padding: 10px 0 0 10px;
}

.list-enter-active,
.list-leave-active {
	transition: all var(--animation-slow);
}

.list-enter,
.list-leave-to {
	opacity: 0;
	height: 0px;
	transform: scaleY(0);
}
</style>
