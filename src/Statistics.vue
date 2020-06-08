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
        <p class="totalPayedText" v-if="stats">
            {{ t('cospend', 'Total payed by all the members: {t}', {t: totalPayed.toFixed(2)}) }}
        </p>
        <br/><hr/>
        <h2 class="statTableTitle">{{ t('cospend', 'Global stats') }}</h2>
        <v-table id="statsTable" :data="stats.stats" v-if="stats">
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
                        </div>{{ myGetSmartMemberName(value.member.id) }}
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
        <hr/>
        <h2 class="statTableTitle">{{ t('cospend', 'Monthly stats per member') }}</h2>
        <v-table id="monthlyTable" :data="monthlyMemberStats" v-if="stats">
            <thead slot="head">
                <v-th sortKey="member.name">{{ t('cospend', 'Member/Month') }}</v-th>
                <v-th v-for="(st, month) in stats.monthlyStats" :key="month" :sortKey="month">{{ month }}</v-th>
            </thead>
            <tbody slot="body" slot-scope="{displayData}">
                <tr v-for="value in displayData" :key="value.member.id">
                    <td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
                        <div v-if="value.member.id !== 0" :class="'owerAvatar' + myGetAvatarClass(value.member.id)">
                            <div class="disabledMask"></div><img :src="myGetMemberAvatar(projectId, value.member.id)">
                        </div>{{ (value.member.id !== 0) ? myGetSmartMemberName(value.member.id) : value.member.name }}
                    </td>
                    <td v-for="(st, month) in stats.monthlyStats"
                        :key="month"
                        :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
                        {{ value[month].toFixed(2) }}
                    </td>
                </tr>
            </tbody>
        </v-table>
        <div id="memberMonthlyChart">
            <LineChartJs
                v-if="stats"
                :chartData="monthlyMemberChartData"
                :options="monthlyMemberChartOptions"
            />
        </div>
        <hr/>
        <h2 class="statTableTitle">{{ t('cospend', 'Monthly stats per category') }}</h2>
        <v-table id="categoryTable" :data="monthlyCategoryStats" v-if="stats">
            <thead slot="head">
                <v-th sortKey="name">{{ t('cospend', 'Category/Month') }}</v-th>
                <v-th v-for="month in categoryMonths" :key="month" :sortKey="month">{{ month }}</v-th>
                <th></th>
            </thead>
            <tbody slot="body" slot-scope="{displayData}">
                <tr v-for="vals in displayData" :key="vals.catid">
                    <td :style="'border: 2px solid ' + getCategory(vals.catid).color + ';'">
                        {{ getCategory(vals.catid).name }}
                    </td>
                    <td v-for="month in categoryMonths" :key="month"
                        :style="'border: 2px solid ' + getCategory(vals.catid).color + ';'">
                        {{ (vals[month] || 0).toFixed(2) }}
                    </td>
                </tr>
            </tbody>
        </v-table>
        <div id="categoryMonthlyChart">
            <LineChartJs
                v-if="stats"
                :chartData="monthlyCategoryChartData"
                :options="monthlyCategoryChartOptions"
            />
        </div>
        <hr/>
        <div id="memberChart">
            <PieChartJs
                v-if="stats"
                :chartData="memberPieData"
                :options="memberPieOptions"
            />
        </div>
        <hr/>
        <div id="categoryChart">
            <PieChartJs
                v-if="stats"
                :chartData="categoryPieData"
                :options="categoryPieOptions"
            />
        </div>
        <hr/>
        <select v-if="stats" id="categoryMemberSelect" ref="categoryMemberSelect" @change="onCategoryMemberChange">
            <option v-for="(val, catid) in stats.categoryMemberStats" :key="catid" :value="catid">{{ getCategory(catid).name }}</option>
        </select>
        <div id="categoryMemberChart">
            <PieChartJs
                v-if="stats"
                :catid="selectedCategoryId"
                :chartData="categoryMemberPieData"
                :options="categoryMemberPieOptions"
            />
        </div>
        <hr/>
        <select v-if="stats" id="memberPolarSelect" ref="memberPolarSelect" v-model="selectedMemberId">
            <option disabled value="0">{{ t('cospend', 'Select a member') }}</option>
            <option v-for="mid in stats.memberIds" :key="mid" :value="mid">{{ myGetSmartMemberName(mid) }}</option>
        </select>
        <div id="memberPolarChart">
            <PolarChartJs
                v-if="stats && (selectedMemberId !== 0)"
                :chartData="memberPolarPieData"
                :options="memberPolarPieOptions"
            />
        </div>
    </div>
</template>

<script>
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import {getMemberName, getSmartMemberName, getMemberAvatar} from './member';
import cospend from './state';
import LineChartJs from './components/LineChartJs';
import PieChartJs from './components/PieChartJs';
import PolarChartJs from './components/PolarChartJs';

export default {
    name: 'Statistics',

    components: {
        LineChartJs, PieChartJs, PolarChartJs
    },

	data: function() {
		return {
            projectId: cospend.currentProjectId,
            stats: null,
            selectedCategoryId: 0,
            selectedMemberId: 0
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
            for (let i = 0; i < this.stats.stats.length; i++) {
                totalPayed += this.stats.stats[i].paid;
            }
            return totalPayed;
        },
        isFiltered: function() {
            return false;
        },
        monthlyMemberStats: function() {
            const rows = [];
            const memberIds = this.stats.memberIds;
            const mids = memberIds.slice();
            mids.push('0');
            let mid, row;
            for (let i = 0; i < mids.length; i++) {
                mid = mids[i];
                row = {};
                if (mid === '0') {
                    row.member = {name: t('cospend', 'All members'), id: 0}
                } else {
                    row.member = cospend.members[this.projectId][mid];
                }
                for (const month in this.stats.monthlyStats) {
                    row[month] = this.stats.monthlyStats[month][mid];
                }
                rows.push(row);
            }
            return rows;
        },
        categoryMonths: function() {
            let months = [];
            for (const catId in this.stats.categoryMonthlyStats) {
                for (const month in this.stats.categoryMonthlyStats[catId]) {
                    months.push(month);
                }
            }
            const distinctMonths = [...new Set(months)];
            distinctMonths.sort();
            return distinctMonths;
        },
        monthlyCategoryStats: function() {
            const data = [];
            let elem;
            for (const catid in this.stats.categoryMonthlyStats) {
                elem = {
                    catid: catid,
                    name: this.getCategoryPureName(catid)
                };
                for (const month in this.stats.categoryMonthlyStats[catid]) {
                    elem[month] = this.stats.categoryMonthlyStats[catid][month];
                }
                data.push(elem);
            }
            return data;
        },
        monthlyMemberChartData: function() {
            const memberDatasets = [];
            let member;
            for (const mid in this.members) {
                member = this.members[mid];
                let paid = [];
                for (const month of this.categoryMonths) {
                    paid.push(this.stats.monthlyStats[month][mid]);
                }

                memberDatasets.push({
                    label: member.name,
                    // FIXME hacky way to change alpha channel:
                    backgroundColor: "#" + member.color + "4D",
                    pointBackgroundColor: "#" + member.color,
                    borderColor: "#" + member.color,
                    pointHighlightStroke: "#" + member.color,
                    fill: '-1',
                    lineTension: 0,
                    data: paid,
                })
            }
            return {
                labels: this.categoryMonths,
                datasets: memberDatasets
            };
        },
        monthlyMemberChartOptions: function() {
            return {
                scales: {
                    yAxes: [{
                        stacked: true
                    }]
                },
                title: {
                    display: true,
                    text: t('cospend', 'Payments per member per month')
                },
                responsive: true,
                maintainAspectRatio: false,
                showAllTooltips: false,
                hover: {
                    intersect: false,
                    mode: 'index'
                },
                tooltips: {
                    intersect: false,
                    mode: 'nearest'
                },
                legend: {
                    position: 'left'
                }
            };
        },
        monthlyCategoryChartData: function() {
            let categoryDatasets = [];
            let catIdInt, category;
            for (const catId in this.stats.categoryMonthlyStats) {
                catIdInt = parseInt(catId);
                category = this.getCategory(catId);

                // Build time series:
                const paid = [];
                for (const month of this.categoryMonths) {
                    if (this.stats.categoryMonthlyStats[catId].hasOwnProperty(month)) {
                        paid.push(this.stats.categoryMonthlyStats[catId][month]);
                    } else {
                        paid.push(0);
                    }
                }

                categoryDatasets.push({
                    label: category.name,
                    // FIXME hacky way to change alpha channel:
                    backgroundColor: category.color + '4D',
                    pointBackgroundColor: category.color,
                    borderColor: category.color,
                    pointHighlightStroke: category.color,
                    fill: '-1',
                    lineTension: 0,
                    data: paid,
                })
            }
            return {
                labels: this.categoryMonths,
                datasets: categoryDatasets
            };
        },
        monthlyCategoryChartOptions: function() {
            return {
                ...this.monthlyMemberChartOptions,
                title: {
                    display: true,
                    text: t('cospend', 'Payments per category per month')
                },
            };
        },
        memberPieData: function() {
            const memberBackgroundColors = [];
            const memberData = {
                // 2 datasets: paid and spent
                datasets: [{
                    data: [],
                    backgroundColor: []
                }, {
                    data: [],
                    backgroundColor: []
                }],
                labels: []
            };
            let sumPaid = 0;
            let sumSpent = 0;
            let paid, spent, name, color;
            for (let i = 0; i < this.stats.stats.length; i++) {
                paid = this.stats.stats[i].paid.toFixed(2);
                spent = this.stats.stats[i].spent.toFixed(2);
                sumPaid += parseFloat(paid);
                sumSpent += parseFloat(spent);
                name = this.stats.stats[i].member.name;
                color = '#' + this.members[this.stats.stats[i].member.id].color;
                memberData.datasets[0].data.push(paid);
                memberData.datasets[1].data.push(spent);

                memberBackgroundColors.push(color);

                memberData.labels.push(name);
            }
            memberData.datasets[0].backgroundColor = memberBackgroundColors;
            memberData.datasets[1].backgroundColor = memberBackgroundColors;
            return memberData;
        },
        memberPieOptions: function() {
            return {
                title: {
                    display: true,
                    text: t('cospend', 'Who paid (outside circle) and spent (inside pie)?')
                },
                responsive: true,
                showAllTooltips: false,
                legend: {
                    position: 'left'
                }
            };
        },
        categoryPieData: function() {
            const categoryData = {
                datasets: [{
                    data: [],
                    backgroundColor: []
                }],
                labels: []
            };
            let paid, catIdInt, category;
            for (const catId in this.stats.categoryStats) {
                paid = this.stats.categoryStats[catId].toFixed(2);
                catIdInt = parseInt(catId);
                category = this.getCategory(catId);

                categoryData.datasets[0].data.push(paid);
                categoryData.datasets[0].backgroundColor.push(category.color);
                categoryData.labels.push(category.name);
            }
            return categoryData;
        },
        categoryPieOptions: function() {
            return {
                ...this.memberPieOptions,
                title: {
                    display: true,
                    text: t('cospend', 'What was paid per category?')
                }
            }
        },
        categoryMemberPieData: function() {
            const catid = this.selectedCategoryId;
            const categoryData = {
                datasets: [{
                    data: [],
                    backgroundColor: []
                }],
                labels: []
            };
            const categoryStats = this.stats.categoryMemberStats[catid];
            let memberName, paid, color;
            for (const mid in categoryStats) {
                memberName = this.members[mid].name;
                color = '#' + this.members[mid].color;
                paid = categoryStats[mid].toFixed(2);
                categoryData.datasets[0].data.push(paid);
                categoryData.datasets[0].backgroundColor.push(color);
                categoryData.labels.push(memberName);
            }
            return categoryData;
        },
        // keeping this computed in case vue-chartjs make options reactive...
        categoryMemberPieOptions: function() {
            return {
                ...this.memberPieOptions,
                title: {
                    display: true,
                    text: t('cospend', 'Who paid for this category?')
                }
            };
        },
        memberPolarPieData: function() {
            const memberData = {
                datasets: [{
                    data: [],
                    backgroundColor: []
                }],
                labels: []
            };
            let category, paid;
            for (const catId in this.stats.categoryMemberStats) {
                category = this.getCategory(catId);
                paid = this.stats.categoryMemberStats[catId][this.selectedMemberId].toFixed(2);
                memberData.datasets[0].data.push(paid);
                memberData.datasets[0].backgroundColor.push(category.color);
                memberData.labels.push(category.name);
            }
            return memberData;
        },
        // keeping this computed in case vue-chartjs make options reactive...
        memberPolarPieOptions: function() {
            return {
                title: {
                    display: true,
                    text: t('cospend', 'What kind of member is she/he?')
                },
                responsive: true,
                showAllTooltips: false,
                legend: {
                    position: 'left'
                }
            };
        }
    },

    mounted() {
        this.getStats();
    },

    methods: {
        onMemberPolarChange: function() {
            const mid = this.$refs.memberPolarSelect.value;
            this.selectedMemberId = mid;
        },
        onCategoryMemberChange: function() {
            const catId = this.$refs.categoryMemberSelect.value;
            this.selectedCategoryId = catId;
        },
        getCategory: function(catId) {
            const projectid = this.projectId;
            let catName, catColor;
            if (cospend.hardCodedCategories.hasOwnProperty(catId)) {
                catName = cospend.hardCodedCategories[catId].icon + ' ' + cospend.hardCodedCategories[catId].name;
                catColor = cospend.hardCodedCategories[catId].color;
            } else if (cospend.projects[projectid].categories.hasOwnProperty(catId)) {
                catName = (cospend.projects[projectid].categories[catId].icon || '') +
                    ' ' + cospend.projects[projectid].categories[catId].name;
                catColor = cospend.projects[projectid].categories[catId].color || 'red';
            } else {
                catName = t('cospend', 'No category');
                catColor = '#000000';
            }

            return {
                name: catName,
                color: catColor,
            }
        },
        getCategoryPureName: function(catId) {
            const projectid = this.projectId;
            if (cospend.hardCodedCategories.hasOwnProperty(catId)) {
                return cospend.hardCodedCategories[catId].name;
            } else if (cospend.projects[projectid].categories.hasOwnProperty(catId)) {
                return cospend.projects[projectid].categories[catId].name;
            } else {
                return t('cospend', 'No category');
            }
        },
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
        myGetSmartMemberName: function(mid) {
            let smartName = getSmartMemberName(this.projectId, mid);
            if (smartName === t('cospend', 'You')) {
                smartName += ' (' + this.members[mid].name + ')';
            }
            return smartName;
        },
        myGetMemberAvatar: function(pid, mid) {
            return getMemberAvatar(pid, mid);
        },
        myGetMemberColor: function(mid) {
            if (mid === 0) {
                return '999999';
            } else {
                return this.members[mid].color;
            }
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
                that.stats = response;
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