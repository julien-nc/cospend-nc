<template>
    <div id="bill-list" :class="{'app-content-list': true, 'showdetails': shouldShowDetails}">
        <div>
            <AppNavigationItem
                v-if="editionAccess && twoActiveMembers"
                v-show="!loading"
                class="addBillItem"
                icon="icon-add"
                @click="onAddBillClicked"
                :title="t('cospend', 'New bill')"
            />
        </div>
        <h2 v-if="bills.length === 0" class="nobill">
            {{ t('cospend', 'No bill yet') }}
        </h2>
        <h2 class="icon-loading-small loading-icon" v-show="loading"></h2>
        <slide-x-right-transition group :duration="{enter: 300, leave: 0}">
            <BillItem
                v-for="(bill, index) in reverseBills"
                :key="bill.id"
                :bill="bill"
                :projectId="projectId"
                :index="nbBills - index"
                :nbbills="nbBills"
                :selected="bill.id === selectedBillId"
                :editionAccess="editionAccess"
                v-on:clicked="onItemClicked"
                v-on:delete="onItemDeleted"/>
        </slide-x-right-transition>
    </div>
</template>

<script>
import { AppNavigationItem } from '@nextcloud/vue'
import BillItem from './components/BillItem';
import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import cospend from './state';
import * as constants from './constants';
import * as network from './network';
import { SlideXRightTransition } from 'vue2-transitions'

export default {
    name: 'BillList',

    components: {
        BillItem, AppNavigationItem, SlideXRightTransition
    },

    //TODO
    props: ['projectId', 'bills', 'selectedBillId', 'editionAccess', 'loading', 'mode'],
    data() {
        return {
            cospend: cospend,
        };
    },

    mounted() {
    },

    computed: {
        nbBills() {
            return this.bills.length;
        },
        reverseBills() {
            return this.bills.slice().reverse();
        },
        shouldShowDetails() {
            return (this.mode !== 'edition' || this.selectedBillId !== -1);
        },
        twoActiveMembers() {
            let c = 0;
            const members = this.cospend.projects[this.projectId].members;
            for (const mid in members) {
                if (members[mid].activated) {
                    c++;
                }
            }
            return (c >= 2);
        },
    },

    methods: {
        onAddBillClicked() {
            this.$emit('newBillClicked');
        },
        onItemClicked(bill) {
            this.$emit('itemClicked', bill.id);
        },
        onItemDeleted(bill) {
            if (bill.id === 0) {
                this.$emit('itemDeleted', bill);
            } else {
                this.deleteBill(bill);
            }
        },
        deleteBill(bill) {
            network.deleteBill(this.projectId, bill, this.deleteBillSuccess);
        },
        deleteBillSuccess(bill) {
            this.$emit('itemDeleted', bill);
            //updateProjectBalances(projectid);
            showSuccess(t('cospend', 'Bill deleted.'));
        },
    }
}
</script>

<style scoped lang="scss">
.addBillItem {
    padding-left: 40px;
}
.nobill {
    text-align: center;
    color: var(--color-text-lighter);
    margin-top: 8px;
}
.loading-icon {
    margin-top: 16px;
}
</style>