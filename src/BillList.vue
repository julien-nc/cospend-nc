<template>
	<AppContentList
		ref="list">
		<div class="list-header">
			<AppNavigationItem
				v-if="editionAccess && oneActiveMember"
				v-show="!loading"
				class="addBillItem"
				icon="icon-add"
				:title="t('cospend', 'New bill')"
				@click="onAddBillClicked">
				<template slot="actions">
					<ActionButton v-show="editionAccess && bills.length > 0"
						:icon="selectMode ? 'icon-close' : 'icon-toggle-filelist'"
						@click="toggleSelectMode">
						{{ multiToggleText }}
					</ActionButton>
				</template>
			</AppNavigationItem>
			<transition name="fade">
				<div v-if="selectMode"
					class="selectionOptions">
					<select v-show="selectedBillIds.length > 0"
						v-model="selectedCategory"
						class="category-select"
						@input="onCategoryChange">
						<option value="placeholder">
							{{ t('cospend', 'Assign category') }}
						</option>
						<option value="0">
							{{ t('cospend', 'None') }}
						</option>
						<option
							v-for="category in sortedCategories"
							:key="category.id"
							:value="category.id">
							{{ category.icon + ' ' + category.name }}
						</option>
						<option
							v-for="(category, catid) in hardCodedCategories"
							:key="catid"
							:value="catid">
							{{ category.icon + ' ' + category.name }}
						</option>
					</select>
					<select v-show="selectedBillIds.length > 0"
						v-model="selectedPaymentMode"
						class="paymentmode-select"
						:disabled="!editionAccess"
						@input="onPaymentModeChange">
						<option value="placeholder">
							{{ t('cospend', 'Assign payment mode') }}
						</option>
						<option value="n">
							{{ t('cospend', 'None') }}
						</option>
						<option
							v-for="(pm, id) in paymentModes"
							:key="id"
							:value="id">
							{{ pm.icon + ' ' + pm.name }}
						</option>
					</select>
					<Actions v-show="selectedBillIds.length > 0 && deletionEnabled">
						<ActionButton
							icon="icon-delete"
							class="multiDelete"
							@click="deleteSelection">
							{{ t('cospend', 'Delete selected bills') }}
						</ActionButton>
					</Actions>
					<p v-if="selectedBillIds.length === 0"
						class="multiSelectHint">
						{{ t('cospend', 'Multi select mode: Select bills to make grouped actions') }}
					</p>
				</div>
			</transition>
		</div>
		<h3 v-if="!oneActiveMember"
			class="nomember">
			{{ t('cospend', 'Add at least 2 members to start creating bills') }}
		</h3>
		<EmptyContent v-else-if="bills.length === 0 && !loading"
			icon="icon-cospend-raw">
			{{ t('cospend', 'No bills yet') }}
		</EmptyContent>
		<h2 v-show="loading"
			class="icon-loading-small loading-icon" />
		<transition-group name="list">
			<BillItem
				v-for="(bill, index) in bills"
				:key="bill.id"
				:bill="bill"
				:project-id="projectId"
				:index="nbBills - index"
				:nbbills="nbBills"
				:selected="isBillSelected(bill)"
				:edition-access="editionAccess"
				:show-delete="!selectMode"
				@clicked="onItemClicked"
				@delete="onItemDeleted" />
		</transition-group>
		<InfiniteLoading v-if="bills.length > 30"
			:identifier="projectId"
			@infinite="infiniteHandler">
			<template #no-results>
				{{ t('cospend', 'No more bills') }}
			</template>
			<template #no-more>
				{{ t('cospend', 'No more bills') }}
			</template>
		</InfiniteLoading>
	</AppContentList>
</template>

<script>
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import BillItem from './components/BillItem'
import InfiniteLoading from 'vue-infinite-loading'
import { showSuccess, showError } from '@nextcloud/dialogs'
import cospend from './state'
import * as network from './network'
import * as constants from './constants'
import { strcmp } from './utils'

export default {
	name: 'BillList',

	components: {
		BillItem, AppContentList, AppNavigationItem, Actions, ActionButton, EmptyContent, InfiniteLoading,
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
	},

	data() {
		return {
			cospend,
			selectMode: false,
			selectedCategory: 'placeholder',
			selectedPaymentMode: 'placeholder',
			selectedBillIds: [],
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
		paymentModes() {
			return cospend.paymentModes
		},
		hardCodedCategories() {
			return cospend.hardCodedCategories
		},
		multiToggleText() {
			return this.selectMode
				? t('cospend', 'Leave multiple selection mode')
				: t('cospend', 'Enter multiple selection mode')
		},
		deletionEnabled() {
			return !cospend.projects[this.projectId].deletion_disabled
		},
	},

	watch: {
		projectId() {
			this.selectMode = false
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
		deleteBill(bill) {
			network.deleteBill(this.projectId, bill).then((response) => {
				this.$emit('item-deleted', bill)
				showSuccess(t('cospend', 'Bill deleted'))
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete bill')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		toggleSelectMode() {
			this.selectMode = !this.selectMode
			if (this.selectMode) {
				this.$emit('reset-selection')
			} else {
				this.selectedBillIds = []
			}
		},
		onCategoryChange(e) {
			const categoryid = e.target.value
			if (this.selectedBillIds.length > 0) {
				network.saveBills(this.projectId, this.selectedBillIds, categoryid, null).then((response) => {
					this.saveBillsSuccess(this.selectedBillIds, categoryid, null)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to save bills')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
					)
				})
			}
		},
		onPaymentModeChange(e) {
			const paymentmode = e.target.value
			if (this.selectedBillIds.length > 0) {
				network.saveBills(this.projectId, this.selectedBillIds, null, paymentmode).then((response) => {
					this.saveBillsSuccess(this.selectedBillIds, null, paymentmode)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to save bills')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
					)
				})
			}
		},
		saveBillsSuccess(billIds, categoryid, paymentmode) {
			this.$emit('multi-bill-edit', billIds, categoryid, paymentmode)
			showSuccess(t('cospend', 'Bills edited'))
			this.selectedCategory = 'placeholder'
			this.selectedPaymentMode = 'placeholder'
		},
		deleteSelection() {
			if (this.selectedBillIds.length > 0) {
				OC.dialogs.confirmDestructive(
					n('cospend',
						'Are you sure you want to delete {nb} bill?',
						'Are you sure you want to delete {nb} bills?',
						this.selectedBillIds.length,
						{ nb: this.selectedBillIds.length }
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
									+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
								)
							})
						}
					},
					true
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
		display: flex;

		select {
			margin-top: 5px;
		}
		.multiDelete {
			margin-left: auto;
		}

		.paymentmode-select,
		.category-select {
			width: 40%;
		}
	}
}

.addBillItem {
	padding-left: 40px;
	padding-right: 44px;
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
	text-align: center;
	width: 100%;
	font-weight: bold;
	min-height: 44px;
	padding-top: 10px;
}

::v-deep .icon-cospend-raw {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../img/app_black.svg') no-repeat;
	mask-size: 64px auto;
	mask-position: center;
	-webkit-mask: url('./../img/app_black.svg') no-repeat;
	-webkit-mask-size: 64px auto;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
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
