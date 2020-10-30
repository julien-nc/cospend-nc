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
		</div>
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
			:selected="bill.id === selectedBillId"
			:edition-access="editionAccess"
			@clicked="onItemClicked"
			@delete="onItemDeleted" />
	</div>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import BillItem from './components/BillItem'
import { showSuccess } from '@nextcloud/dialogs'
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
	},

	methods: {
		onAddBillClicked() {
			this.$emit('new-bill-clicked')
		},
		onItemClicked(bill) {
			this.$emit('item-clicked', bill.id)
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
			// updateProjectBalances(projectid)
			showSuccess(t('cospend', 'Bill deleted.'))
		},
	},
}
</script>

<style scoped lang="scss">
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
