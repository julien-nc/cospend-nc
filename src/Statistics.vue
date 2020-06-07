<template>
    <div id="stats-div">
        <div id="stats-filters">
            <label for="date-min-stats">{{ t('cospend', 'Minimum date') }}: </label>
            <input id="date-min-stats" type="date"/>
            <label for="date-max-stats">{{ t('cospend', 'Maximum date') }}: </label>
            <input id="date-max-stats" type="date"/>
            <label for="payment-mode-stats">
                <a class="icon icon-tag"></a>
                {{ t('cospend', 'Payment mode') }}
            </label>
            <select id="payment-mode-stats">
                <option value="n" selected="selected">{{ t('cospend', 'All') }}</option>
                <option
                    v-for="(pm, id) in paymentModes"
                    :key="id"
                    :value="id">
                    {{ pm.icon + ' ' + pm.name }}
                </option>
            </select>
            <label for="category-stats">
                <a class="icon icon-category-app-bundles"></a>
                {{ t('cospend', 'Category') }}
            </label>
            <select id="category-stats">
                <option value="0">{{ t('cospend', 'All') }}</option>
                <option value="-100" selected="selected">{{ t('cospend', 'All except reimbursement') }}</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id">
                    {{ category.icon + ' ' + category.name }}
                </option>
                <option
                    v-for="category in hardCodedCategories"
                    :key="category.id"
                    :value="category.id">
                    {{ category.icon + ' ' + category.name }}
                </option>
            </select>
            <label for="amount-min-stats">{{ t('cospend', 'Minimum amount') }}: </label>
            <input id="amount-min-stats" type="number"/>
            <label for="amount-max-stats">{{ t('cospend', 'Maximum amount') }}: </label>
            <input id="amount-max-stats" type="number"/>
            <label for="currency-stats">{{ t('cospend', 'Currency of statistic values') }}: </label>
            <select id="currency-stats">
                <option value="0">{{ project.currencyname || t('cospend', 'Main project\'s currency') }}</option>
                <option
                    v-for="currency in currencies"
                    :key="currency.id"
                    :value="currency.id">
                    {{ currency.name }}
                </option>
            </select>
            <input id="showDisabled" type="checkbox" class="checkbox"/>
            <label for="showDisabled" class="checkboxlabel">{{ t('cospend', 'Show disabled members') }}</label>
        </div>
        <br/>
        <p class="totalPayedText" v-if="globalStats">
            {{ t('cospend', 'Total payed by all the members: {t}', {t: totalPayed.toFixed(2)}) }}
        </p>
        <br/><hr/>
        <h2 class="statTableTitle">{{ t('cospend', 'Global stats') }}</h2>
        <v-table id="statsTable" :data="globalStats" v-if="globalStats">
            <thead slot="head">
                <v-th sortKey="member.name">{{ t('cospend', 'Member name') }}</v-th>
                <v-th sortKey="paid">{{ t('cospend', 'Paid') }}</v-th>
                <v-th sortKey="spent">{{ t('cospend', 'Spent') }}</v-th>
                <v-th sortKey="filtered_balance" v-if="isFiltered">{{ t('cospend', 'Filtered balance') }}</v-th>
                <v-th sortKey="balance">{{ t('cospend', 'Balance') }}</v-th>
            </thead>
            <tbody slot="body" slot-scope="{displayData}">
                <tr v-for="value in displayData" :key="value.member.id">
                    <td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
                        <div :class="'owerAvatar' + myGetAvatarClass(value.member.id)">
                            <div class="disabledMask"></div><img :src="myGetMemberAvatar(projectId, value.member.id)">
                        </div>{{ myGetSmartMemberName(projectId, value.member.id) }}
                    </td>
                    <td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">{{ value.paid.toFixed(2) }}</td>
                    <td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) +';'">{{ value.spent.toFixed(2) }}</td>
                    <td v-if="isFiltered" :class="getBalanceClass(value.filtered_balance)"
                        :style="'border: 2px solid #' + myGetMemberColor(value.member.id) +';'">{{ value.filtered_balance.toFixed(2) }}</td>
                    <td :class="getBalanceClass(value.balance)"
                        :style="'border: 2px solid #' + myGetMemberColor(value.member.id) +';'">{{ value.balance.toFixed(2) }}</td>
                </tr>
            </tbody>
            <tfoot></tfoot>
        </v-table>
    </div>
</template>

<script>
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import {getMemberName, getSmartMemberName, getMemberAvatar} from './member';
import cospend from './state';

export default {
    name: 'Statistics',

    components: {
    },

	data: function() {
		return {
            projectId: cospend.currentProjectId,
            globalStats: null,
            monthlyMemberStats: null,
            monthlyCategoryStats: null
		};
    },

	computed: {
        project: function() {
            return cospend.projects[this.projectId];
        },
		members: function() {
            return cospend.members[this.projectId];
        },
        categories: function() {
            return cospend.projects[this.projectId].categories;
        },
        hardCodedCategories: function() {
            return cospend.hardCodedCategories;
        },
        currencies: function() {
            return cospend.projects[this.projectId].currencies;
        },
        paymentModes: function() {
            return cospend.paymentModes;
        },
        totalPayed: function() {
            let totalPayed = 0.0;
            for (let i = 0; i < this.globalStats.length; i++) {
                totalPayed += this.globalStats[i].paid;
            }
            return totalPayed;
        },
        isFiltered: function() {
            return false;
        }
    },

    mounted() {
        this.getStats();
    },

    methods: {
        getBalanceClass: function(balance) {
            let balanceClass = '';
            if (balance > 0) {
                balanceClass = 'balancePositive';
            } else if (balance < 0) {
                balanceClass = 'balanceNegative';
            }
            return balanceClass;
        },
        myGetAvatarClass: function(mid) {
            return this.members[mid].activated ? '' : ' owerAvatarDisabled';
        },
        myGetSmartMemberName: function(pid, mid) {
            let smartName = getSmartMemberName(pid, mid);
            if (smartName === t('cospend', 'You')) {
                smartName += ' (' + this.members[mid].name + ')';
            }
            return smartName;
        },
        myGetMemberAvatar: function(pid, mid) {
            return getMemberAvatar(pid, mid);
        },
        myGetMemberColor: function(mid) {
            return this.members[mid].color;
        },
        onChangeCenterMember: function(e) {
            this.getSettlement(e.target.value);
        },
        getStats: function() {
            const that = this;
            const req = {};
            //const req = {
            //    tsMin: tsMin,
            //    tsMax: tsMax,
            //    paymentMode: paymentMode,
            //    category: category,
            //    amountMin: amountMin,
            //    amountMax: amountMax,
            //    showDisabled: showDisabled ? '1' : '0',
            //    currencyId: currencyId
            //};
            let url, type;
            if (!cospend.pageIsPublic) {
                req.projectid = this.projectId;
                type = 'POST';
                url = generateUrl('/apps/cospend/getStatistics');
            } else {
                type = 'GET';
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/statistics');
            }
            $.ajax({
                type: type,
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                that.globalStats = response.stats;
            }).always(function() {
            }).fail(function() {
                Notification.showTemporary(t('cospend', 'Failed to get statistics'));
            });
        }
    }
}
</script>

<style scoped lang="scss">

</style>