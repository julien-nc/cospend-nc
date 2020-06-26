<template>
    <div id="bill-list" :class="{'app-content-list': true, 'showdetails': shouldShowDetails}">
        <h2 class="icon-loading-small" v-show="loading"></h2>
        <div>
            <AppNavigationItem
                v-if="editionAccess && twoActiveMembers"
                v-show="!loading" icon="icon-add" @click="onAddBillClicked"
                :title="t('cospend', 'New bill')"
            />
        </div>
        <h2 v-if="bills.length === 0" class="nobill">
            {{ t('cospend', 'No bill yet') }}
        </h2>
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

export default {
    name: 'BillList',

    components: {
        BillItem, AppNavigationItem
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
            const that = this;
            const req = {};
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/bills/' + bill.id);
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id);
            }
            $.ajax({
                type: 'DELETE',
                url: url,
                data: req,
                async: true,
            }).done(function() {
                that.$emit('itemDeleted', bill);
                //updateProjectBalances(projectid);
                showSuccess(t('cospend', 'Bill deleted.'));
            }).always(function() {
            }).fail(function(response) {
                showError(
                    t('cospend', 'Failed to delete bill') +
                    ': ' + response.responseJSON
                );
            });
        }
    }
}
</script>

<style scoped lang="scss">

</style>