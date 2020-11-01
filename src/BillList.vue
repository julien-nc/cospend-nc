<template>
	<div id="bill-list"
		:class="{ 'app-content-list': true, 'showdetails': shouldShowDetails }">
		<div>
			<AppNavigationItem
				v-if="editionAccess && twoActiveMembers"
				v-show="!loading"
				class="addBillItem"
				icon="icon-add"
				:title="t('cospend', 'New bill')"
				@click="onAddBillClicked" />
			<button v-if="editionAccess"
				:class="{ icon: true, 'icon-toggle-filelist': !selectMode, 'icon-close': selectMode, 'top-right-icon': true }"
				@click="toggleSelectMode" />
		</div>
		<transition name="fade">
			<div v-if="selectMode"
				class="selectionOptions">
				<select v-show="selectedBillIds.length > 0"
					v-model="selectedCategory"
					@input="onCategoryChange">
					<option value="0">
						{{ t('cospend', 'Affect a category') }}
					</option>
					<option
						v-for="category in categories"
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
				<button v-if="selectedBillIds.length > 0"
					class="icon icon-delete"
					@click="deleteSelection" />
				<p v-else
					class="multiSelectHint">
					{{ t('cospend', 'Multi select mode: Select bills to make grouped actions') }}
				</p>
			</div>
		</transition>
		<h3 v-if="!twoActiveMembers"
			class="nomember">
			{{ t('cospend', 'Add at least 2 members to start creating bills') }}
		</h3>
		<EmptyContent v-else-if="bills.length === 0 && !loading"
			icon="icon-cospend-raw">
			{{ t('cospend', 'No bills yet') }}
		</EmptyContent>
		<h2 v-show="loading"
			class="icon-loading-small loading-icon" />
		<BillItem
			v-for="(bill, index) in reverseBills"
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
	</div>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import BillItem from './components/BillItem'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import cospend from './state'
import * as network from './network'

export default {
	name: 'BillList',

	components: {
		BillItem, AppNavigationItem, EmptyContent,
	},

	props: {
		projectId: {
			type: String,
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
			selectedCategory: 0,
			selectedBillIds: [],
		}
	},

	computed: {
		nbBills() {
			return this.bills.length
		},
		reverseBills() {
			return this.bills.slice().reverse()
		},
		shouldShowDetails() {
			return (this.mode !== 'edition' || this.selectedBillId !== -1)
		},
		twoActiveMembers() {
			let c = 0
			const members = this.cospend.projects[this.projectId].members
			for (const mid in members) {
				if (members[mid].activated) {
					c++
				}
			}
			return (c >= 2)
		},
		categories() {
			return cospend.projects[this.projectId].categories
		},
		hardCodedCategories() {
			return cospend.hardCodedCategories
		},
	},

	watch: {
		projectId() {
			this.selectMode = false
			this.selectedBillIds = []
		},
	},

	methods: {
		isBillSelected(bill) {
			if (this.selectMode) {
				return this.selectedBillIds.includes(bill.id)
			} else {
				return bill.id === this.selectedbillid
			}
		},
		onAddBillClicked() {
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
			network.deleteBill(this.projectId, bill, this.deleteBillSuccess)
		},
		deleteBillSuccess(bill) {
			this.$emit('item-deleted', bill)
			showSuccess(t('cospend', 'Bill deleted'))
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
				network.saveBills(this.projectId, this.selectedBillIds, categoryid, this.saveBillsSuccess)
			}
		},
		saveBillsSuccess(billIds, categoryid) {
			this.$emit('multi-category-edit', billIds, categoryid)
			showSuccess(t('cospend', 'Bills edited'))
			this.selectedCategory = 0
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
							network.deleteBills(this.projectId, this.selectedBillIds, this.deleteBillsSuccess)
						}
					},
					true
				)
			}
		},
		deleteBillsSuccess(billIds) {
			this.$emit('items-deleted', billIds)
			showSuccess(t('cospend', 'Bills deleted'))
			this.selectedBillIds = []
		},
	},
}
</script>

<style scoped lang="scss">
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

.icon {
	width: 44px;
	height: 44px;
	border-radius: var(--border-radius-pill);
	opacity: .5;

	&.top-right-icon {
		position: absolute;
		top: 2px;
		right: 0;
	}
	&.icon-delete,
	&.icon-close,
	&.icon-toggle-filelist {
		background-color: transparent;
		border: none;
		margin: 0;
	}
	&:hover,
	&:focus {
		opacity: 1;
		background-color: var(--color-background-hover);
	}
}

.selectionOptions {
	display: flex;

	select {
		margin-top: 5px;
	}
	.icon-delete {
		margin-left: auto;
	}
}

.multiSelectHint {
	text-align: center;
	width: 100%;
	font-weight: bold;
	min-height: 44px;
}

::v-deep .icon-cospend-raw {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../css/images/app_black.svg') no-repeat;
	mask-size: 64px auto;
	mask-position: center;
	-webkit-mask: url('./../css/images/app_black.svg') no-repeat;
	-webkit-mask-size: 64px auto;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
}
</style>
