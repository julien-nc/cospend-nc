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
		<h2 v-else-if="bills.length === 0"
			class="nobill">
			{{ t('cospend', 'No bill yet') }}
		</h2>
		<h2 v-show="loading"
			class="icon-loading-small loading-icon" />
		<SlideXRightTransition group :duration="{ enter: 300, leave: 0 }">
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
		</SlideXRightTransition>
	</div>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import BillItem from './components/BillItem'
import { showSuccess } from '@nextcloud/dialogs'
import cospend from './state'
import * as network from './network'
import { SlideXRightTransition } from 'vue2-transitions'

export default {
	name: 'BillList',

	components: {
		BillItem, AppNavigationItem, SlideXRightTransition,
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
			this.$emit('newBillClicked')
		},
		onItemClicked(bill) {
			this.$emit('itemClicked', bill.id)
		},
		onItemDeleted(bill) {
			if (bill.id === 0) {
				this.$emit('itemDeleted', bill)
			} else {
				this.deleteBill(bill)
			}
		},
		deleteBill(bill) {
			network.deleteBill(this.projectId, bill, this.deleteBillSuccess)
		},
		deleteBillSuccess(bill) {
			this.$emit('itemDeleted', bill)
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
</style>
