<template>
	<AppContentDetails class="statistics-content">
		<h2 id="statsTitle">
			<span :class="{ 'icon-loading-small': exporting, 'icon-category-monitoring': !exporting, icon: true }" />
			{{ t('cospend', 'Statistics of project {name}', { name: project.name }, undefined, { escape: false }) }}
			<button v-if="!cospend.pageIsPublic"
				class="exportStats"
				projectid="dum"
				@click="onExportClick">
				<span class="icon-save" />
				{{ t('cospend', 'Export') }}
			</button>
		</h2>
		<div id="stats-filters">
			<label for="date-min-stats">{{ t('cospend', 'Minimum date') }}: </label>
			<input id="date-min-stats"
				ref="dateMinFilter"
				type="date"
				@change="getStats">
			<label for="date-max-stats">{{ t('cospend', 'Maximum date') }}: </label>
			<input id="date-max-stats"
				ref="dateMaxFilter"
				type="date"
				@change="getStats">
			<label for="payment-mode-stats">
				<a class="icon icon-tag" />
				{{ t('cospend', 'Payment mode') }}
			</label>
			<select id="payment-mode-stats"
				ref="paymentModeFilter"
				@change="getStats">
				<option value="n"
					:selected="true">
					{{ t('cospend', 'All') }}
				</option>
				<option v-for="(pm, id) in paymentModes"
					:key="id"
					:value="id">
					{{ pm.icon + ' ' + pm.name }}
				</option>
			</select>
			<label for="category-stats">
				<a class="icon icon-category-app-bundles" />
				{{ t('cospend', 'Category') }}
			</label>
			<select id="category-stats"
				ref="categoryFilter"
				@change="getStats">
				<option value="0">
					{{ t('cospend', 'All') }}
				</option>
				<option value="-100"
					:selected="true">
					{{ t('cospend', 'All except reimbursement') }}
				</option>
				<option v-for="category in sortedCategories"
					:key="category.id"
					:value="category.id">
					{{ category.icon + ' ' + category.name }}
				</option>
				<option v-for="(category, catid) in hardCodedCategories"
					:key="catid"
					:value="catid">
					{{ category.icon + ' ' + category.name }}
				</option>
			</select>
			<label for="amount-min-stats">{{ t('cospend', 'Minimum amount') }}: </label>
			<input id="amount-min-stats"
				ref="amountMinFilter"
				type="number"
				@change="getStats">
			<label for="amount-max-stats">{{ t('cospend', 'Maximum amount') }}: </label>
			<input id="amount-max-stats"
				ref="amountMaxFilter"
				type="number"
				@change="getStats">
			<label for="currency-stats">{{ t('cospend', 'Currency of statistic values') }}: </label>
			<select id="currency-stats"
				ref="currencySelect"
				@change="getStats">
				<option value="0">
					{{ project.currencyname || t('cospend', 'Main project\'s currency') }}
				</option>
				<option v-for="currency in currencies"
					:key="currency.id"
					:value="currency.id">
					{{ currency.name }}
				</option>
			</select>
			<input id="showDisabled"
				ref="showDisabledFilter"
				type="checkbox"
				class="checkbox"
				@change="getStats">
			<label for="showDisabled" class="checkboxlabel">
				{{ t('cospend', 'Show disabled members') }}
			</label>
		</div>
		<br>
		<p v-if="stats"
			class="totalPayedText">
			{{ t('cospend', 'Total paid by all the members: {t}', { t: totalPayed.toFixed(2) }) }}
		</p>
		<br><hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Global stats') }}
		</h2>
		<v-table v-if="stats"
			id="statsTable"
			class="coloredTable avatarTable"
			:data="stats.stats">
			<thead slot="head">
				<v-th sort-key="member.name">
					{{ t('cospend', 'Member name') }}
				</v-th>
				<v-th sort-key="paid">
					{{ t('cospend', 'Paid') }}
				</v-th>
				<v-th sort-key="spent">
					{{ t('cospend', 'Spent') }}
				</v-th>
				<v-th v-if="isFiltered"
					sort-key="filtered_balance">
					{{ t('cospend', 'Filtered balance') }}
				</v-th>
				<v-th sort-key="balance">
					{{ t('cospend', 'Balance') }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="value in displayData"
					:key="value.member.id">
					<td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
						<div class="owerAvatar">
							<ColoredAvatar
								class="itemAvatar"
								:color="getMemberColor(value.member.id)"
								:size="24"
								:disable-menu="true"
								:disable-tooltip="true"
								:show-user-status="false"
								:is-no-user="getMemberUserId(value.member.id) === ''"
								:user="getMemberUserId(value.member.id)"
								:display-name="getMemberName(value.member.id)" />
							<div v-if="isMemberDisabled(value.member.id)" class="disabledMask" />
						</div>{{ myGetSmartMemberName(value.member.id) }}
					</td>
					<td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
						{{ value.paid.toFixed(2) }}
					</td>
					<td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) +';'">
						{{ value.spent.toFixed(2) }}
					</td>
					<td v-if="isFiltered"
						:class="getBalanceClass(value.filtered_balance)"
						:style="'border: 2px solid #' + myGetMemberColor(value.member.id) +';'">
						{{ value.filtered_balance.toFixed(2) }}
					</td>
					<td :class="getBalanceClass(value.balance)"
						:style="'border: 2px solid #' + myGetMemberColor(value.member.id) +';'">
						{{ value.balance.toFixed(2) }}
					</td>
				</tr>
			</tbody>
			<tfoot />
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly paid per member') }}
		</h2>
		<v-table v-if="stats"
			id="memberMonthlyPaidTable"
			class="coloredTable avatarTable"
			:data="memberMonthlyPaidStats">
			<thead slot="head">
				<v-th sort-key="member.name">
					{{ t('cospend', 'Member/Month') }}
				</v-th>
				<v-th v-for="(st, month) in stats.memberMonthlyPaidStats"
					:key="month"
					:sort-key="month">
					{{ month }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="value in displayData"
					:key="value.member.id"
					v-tooltip.left="{ content: value.member.name }"
					:class="{ 'all-members': value.member.id === 0 }">
					<td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
						<div v-if="value.member.id !== 0"
							class="owerAvatar">
							<ColoredAvatar
								class="itemAvatar"
								:color="getMemberColor(value.member.id)"
								:size="24"
								:disable-menu="true"
								:disable-tooltip="true"
								:show-user-status="false"
								:is-no-user="getMemberUserId(value.member.id) === ''"
								:user="getMemberUserId(value.member.id)"
								:display-name="getMemberName(value.member.id)" />
							<div v-if="isMemberDisabled(value.member.id)" class="disabledMask" />
						</div>{{ (value.member.id !== 0) ? myGetSmartMemberName(value.member.id) : value.member.name }}
					</td>
					<td v-for="(st, month) in stats.memberMonthlyPaidStats"
						:key="month"
						:class="{ selected: selectedMemberMonthlyPaidCol === Object.keys(stats.memberMonthlyPaidStats).indexOf(month) }"
						:style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
						{{ value[month].toFixed(2) }}
					</td>
				</tr>
			</tbody>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<div id="memberMonthlyPaidChart"
			@mouseleave="selectedMemberMonthlyPaidCol = null">
			<LineChartJs v-if="stats"
				:chart-data="memberMonthlyPaidChartData"
				:options="memberMonthlyPaidChartOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly spent per member') }}
		</h2>
		<v-table v-if="stats"
			id="memberMonthlySpentTable"
			class="coloredTable avatarTable"
			:data="memberMonthlySpentStats">
			<thead slot="head">
				<v-th sort-key="member.name">
					{{ t('cospend', 'Member/Month') }}
				</v-th>
				<v-th v-for="(st, month) in stats.memberMonthlySpentStats"
					:key="month"
					:sort-key="month">
					{{ month }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="value in displayData"
					:key="value.member.id"
					v-tooltip.left="{ content: value.member.name }"
					:class="{ 'all-members': value.member.id === 0 }">
					<td :style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
						<div v-if="value.member.id !== 0"
							class="owerAvatar">
							<ColoredAvatar
								class="itemAvatar"
								:color="getMemberColor(value.member.id)"
								:size="24"
								:disable-menu="true"
								:disable-tooltip="true"
								:show-user-status="false"
								:is-no-user="getMemberUserId(value.member.id) === ''"
								:user="getMemberUserId(value.member.id)"
								:display-name="getMemberName(value.member.id)" />
							<div v-if="isMemberDisabled(value.member.id)" class="disabledMask" />
						</div>{{ (value.member.id !== 0) ? myGetSmartMemberName(value.member.id) : value.member.name }}
					</td>
					<td v-for="(st, month) in stats.memberMonthlySpentStats"
						:key="month"
						:class="{ selected: selectedMemberMonthlySpentCol === Object.keys(stats.memberMonthlySpentStats).indexOf(month) }"
						:style="'border: 2px solid #' + myGetMemberColor(value.member.id) + ';'">
						{{ value[month].toFixed(2) }}
					</td>
				</tr>
			</tbody>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<div id="memberMonthlySpentChart"
			@mouseleave="selectedMemberMonthlySpentCol = null">
			<LineChartJs v-if="stats"
				:chart-data="memberMonthlySpentChartData"
				:options="memberMonthlySpentChartOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly paid per category') }}
		</h2>
		<v-table v-if="stats"
			id="categoryTable"
			class="coloredTable"
			:data="monthlyCategoryStats">
			<thead slot="head">
				<v-th sort-key="name">
					{{ t('cospend', 'Category/Month') }}
				</v-th>
				<v-th v-for="month in categoryMonths"
					:key="month"
					:sort-key="month">
					{{ month }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="vals in displayData"
					:key="vals.catid"
					v-tooltip.left="{ content: vals.name }">
					<td :style="'border: 2px solid ' + myGetCategory(vals.catid).color + ';'">
						{{ vals.name }}
					</td>
					<td v-for="month in categoryMonths"
						:key="month"
						:class="{ selected: selectedMonthlyCategoryCol === categoryMonths.indexOf(month) }"
						:style="'border: 2px solid ' + myGetCategory(vals.catid).color + ';'">
						{{ (vals[month] || 0).toFixed(2) }}
					</td>
				</tr>
			</tbody>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<div id="categoryMonthlyChart"
			@mouseleave="selectedMonthlyCategoryCol = null">
			<LineChartJs v-if="stats"
				:chart-data="monthlyCategoryChartData"
				:options="monthlyCategoryChartOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly paid per payment mode') }}
		</h2>
		<v-table v-if="stats"
			id="paymentModeTable"
			class="coloredTable"
			:data="monthlyPaymentModeStats">
			<thead slot="head">
				<v-th sort-key="name">
					{{ t('cospend', 'Payment mode/Month') }}
				</v-th>
				<v-th v-for="month in paymentModeMonths"
					:key="month"
					:sort-key="month">
					{{ month }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="vals in displayData"
					:key="vals.pmId"
					v-tooltip.left="{ content: vals.name }">
					<td :style="'border: 2px solid ' + myGetPaymentMode(vals.pmId).color + ';'">
						{{ vals.name }}
					</td>
					<td v-for="month in paymentModeMonths"
						:key="month"
						:class="{ selected: selectedMonthlyPaymentModeCol === paymentModeMonths.indexOf(month) }"
						:style="'border: 2px solid ' + myGetPaymentMode(vals.pmId).color + ';'">
						{{ (vals[month] || 0).toFixed(2) }}
					</td>
				</tr>
			</tbody>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<div id="paymentModeMonthlyChart"
			@mouseleave="selectedMonthlyPaymentModeCol = null">
			<LineChartJs v-if="stats"
				:chart-data="monthlyPaymentModeChartData"
				:options="monthlyPaymentModeChartOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="memberChart">
			<PieChartJs v-if="stats"
				:chart-data="memberPieData"
				:options="memberPieOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="categoryChart">
			<PieChartJs v-if="stats"
				:chart-data="categoryPieData"
				:options="categoryPieOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="paymentModeChart">
			<PieChartJs v-if="stats"
				:chart-data="paymentModePieData"
				:options="paymentModePieOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<select v-if="stats"
			id="categoryMemberSelect"
			ref="categoryMemberSelect"
			@change="onCategoryMemberChange">
			<option v-for="catid in sortedCategoryStatsIds"
				:key="catid"
				:value="catid">
				{{ getCategoryNameIcon(catid) }}
			</option>
		</select>
		<div id="categoryMemberChart">
			<PieChartJs v-if="stats"
				:catid="selectedCategoryId"
				:chart-data="categoryMemberPieData"
				:options="categoryMemberPieOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<select v-if="stats"
			id="memberPolarSelect"
			ref="memberPolarSelect"
			v-model="selectedMemberId">
			<option disabled value="0">
				{{ t('cospend', 'Select a member') }}
			</option>
			<option v-for="mid in stats.memberIds"
				:key="mid"
				:value="mid">
				{{ myGetSmartMemberName(mid) }}
			</option>
		</select>
		<div id="memberPolarChart">
			<PolarChartJs v-if="stats && (selectedMemberId !== 0)"
				:chart-data="memberPolarPieData"
				:options="memberPolarPieOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Who paid for whom?') }}
		</h2>
		<v-table v-if="stats"
			id="paidForTable"
			class="coloredTable avatarTable"
			:data="membersPaidForData">
			<thead slot="head">
				<v-th sort-key="name">
					↓ {{ t('cospend', 'paid for') }} →
				</v-th>
				<v-th v-for="mid in stats.allMemberIds"
					:key="mid"
					:sort-key="mid.toString()"
					class="avatared"
					:style="'border: 2px solid #' + myGetMemberColor(mid) + ';'">
					<div class="owerAvatar">
						<ColoredAvatar
							class="itemAvatar"
							:color="getMemberColor(mid)"
							:size="24"
							:disable-menu="true"
							:disable-tooltip="true"
							:show-user-status="false"
							:is-no-user="getMemberUserId(mid) === ''"
							:user="getMemberUserId(mid)"
							:display-name="getMemberName(mid)" />
						<div v-if="isMemberDisabled(mid)" class="disabledMask" />
					</div>{{ myGetSmartMemberName(mid) }}
				</v-th>
				<v-th sort-key="total">
					{{ t('cospend', 'Total paid') }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="value in displayData"
					:key="value.memberid">
					<td v-if="value.memberid !== 0" :style="'border: 2px solid #' + myGetMemberColor(value.memberid) + ';'">
						<div class="owerAvatar">
							<ColoredAvatar
								class="itemAvatar"
								:color="getMemberColor(value.memberid)"
								:size="24"
								:disable-menu="true"
								:disable-tooltip="true"
								:show-user-status="false"
								:is-no-user="getMemberUserId(value.memberid) === ''"
								:user="getMemberUserId(value.memberid)"
								:display-name="getMemberName(value.memberid)" />
							<div v-if="isMemberDisabled(value.memberid)" class="disabledMask" />
						</div>{{ myGetSmartMemberName(value.memberid) }}
					</td>
					<td v-else style="padding-left: 5px; border: 2px solid lightgrey;">
						{{ t('cospend', 'Total owed') }}
					</td>
					<td v-for="mid in stats.allMemberIds"
						:key="value.memberid + '-' + mid"
						v-tooltip.top="{
							content: value.memberid === 0
								? t('cospend', 'Total owed by {name}', { name: myGetSmartMemberName(mid) })
								: myGetSmartMemberName(value.memberid) + ' → ' + myGetSmartMemberName(mid)
						}"
						:style="'border: 2px solid ' + (value.memberid === 0 ? 'lightgrey' : '#' + myGetMemberColor(value.memberid)) + ';'">
						{{ value[mid].toFixed(2) }}
					</td>
					<td v-if="value.memberid !== 0"
						v-tooltip.top="{ content: t('cospend', 'Total paid by {name}', { name: myGetSmartMemberName(value.memberid) }) }"
						style="border: 2px solid lightgrey;">
						{{ value.total.toFixed(2) }}
					</td>
				</tr>
			</tbody>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
	</AppContentDetails>
</template>

<script>
import moment from '@nextcloud/moment'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import ColoredAvatar from './components/ColoredAvatar'

import { getCategory, getSmartMemberName, strcmp } from './utils'
import { paymentModes } from './constants'
import cospend from './state'
import * as network from './network'
import LineChartJs from './components/LineChartJs'
import PieChartJs from './components/PieChartJs'
import PolarChartJs from './components/PolarChartJs'
import * as constants from './constants'

export default {
	name: 'Statistics',

	components: {
		ColoredAvatar, LineChartJs, PieChartJs, PolarChartJs, AppContentDetails,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			stats: null,
			selectedCategoryId: 0,
			selectedMemberId: 0,
			isFiltered: true,
			cospend,
			exporting: false,
			loadingStats: false,
			selectedMemberMonthlyPaidCol: null,
			selectedMemberMonthlySpentCol: null,
			selectedMonthlyCategoryCol: null,
			selectedMonthlyPaymentModeCol: null,
		}
	},

	computed: {
		project() {
			return cospend.projects[this.projectId]
		},
		members() {
			return cospend.members[this.projectId]
		},
		categories() {
			return cospend.projects[this.projectId].categories
		},
		sortedCategories() {
			if ([
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.MOST_RECENTLY_USED,
			].includes(this.project.categorysort)) {
				return Object.values(this.categories).slice().sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
			} else if (this.project.categorysort === constants.SORT_ORDER.ALPHA) {
				return Object.values(this.categories).slice().sort((a, b) => {
					return strcmp(a.name, b.name)
				})
			}
			return []
		},
		hardCodedCategories() {
			return cospend.hardCodedCategories
		},
		currencies() {
			return cospend.projects[this.projectId].currencies
		},
		paymentModes() {
			return cospend.paymentModes
		},
		membersPaidForData() {
			const rows = []
			const memberIds = this.stats.allMemberIds
			memberIds.forEach((mid) => {
				rows.push({
					memberid: mid,
					name: this.myGetSmartMemberName(mid),
					...this.stats.membersPaidFor[mid],
				})
			})
			rows.push({
				memberid: 0,
				name: 'total',
				...this.stats.membersPaidFor.total,
			})
			return rows
		},
		totalPayed() {
			return this.stats.stats.map(s => s.paid).reduce((acc, curr) => acc + curr)
		},
		memberMonthlyPaidStats() {
			const memberIds = this.stats.memberIds
			const mids = memberIds.slice()
			mids.push('0')
			return mids.map((mid) => {
				const row = {
					member: mid === '0' ? { name: t('cospend', 'All members'), id: 0 } : cospend.members[this.projectId][mid],
				}
				for (const month in this.stats.memberMonthlyPaidStats) {
					row[month] = this.stats.memberMonthlyPaidStats[month][mid]
				}
				return row
			})
		},
		memberMonthlySpentStats() {
			const memberIds = this.stats.memberIds
			const mids = memberIds.slice()
			mids.push('0')
			return mids.map((mid) => {
				const row = {
					member: mid === '0' ? { name: t('cospend', 'All members'), id: 0 } : cospend.members[this.projectId][mid],
				}
				for (const month in this.stats.memberMonthlySpentStats) {
					row[month] = this.stats.memberMonthlySpentStats[month][mid]
				}
				return row
			})
		},
		categoryMonths() {
			const months = []
			for (const catId in this.stats.categoryMonthlyStats) {
				for (const month in this.stats.categoryMonthlyStats[catId]) {
					months.push(month)
				}
			}
			const distinctMonths = [...new Set(months)]
			distinctMonths.sort()
			return distinctMonths
		},
		paymentModeMonths() {
			const months = []
			for (const pmId in this.stats.paymentModeMonthlyStats) {
				for (const month in this.stats.paymentModeMonthlyStats[pmId]) {
					months.push(month)
				}
			}
			const distinctMonths = [...new Set(months)]
			distinctMonths.sort()
			return distinctMonths
		},
		sortedMonthlyCategoryIds() {
			const sortedCategoryIds = this.sortedCategories.filter((cat) => {
				return this.stats.categoryMonthlyStats[cat.id]
			}).map(cat => cat.id)
			const monthlyCatIds = Object.keys(this.stats.categoryMonthlyStats).map(id => parseInt(id))
			const diff = [...monthlyCatIds].filter(cid => !sortedCategoryIds.includes(cid))
			sortedCategoryIds.push(...diff)
			return sortedCategoryIds
		},
		monthlyCategoryStats() {
			const data = []
			let elem

			this.sortedMonthlyCategoryIds.forEach((catid) => {
				elem = {
					catid,
					name: this.getCategoryNameIcon(catid),
				}
				for (const month in this.stats.categoryMonthlyStats[catid]) {
					elem[month] = this.stats.categoryMonthlyStats[catid][month]
				}
				data.push(elem)
			})
			return data
		},
		monthlyPaymentModeStats() {
			const data = []
			let elem
			for (const pmId in this.stats.paymentModeMonthlyStats) {
				elem = {
					pmId,
					name: this.getPaymentModeNameIcon(pmId),
				}
				for (const month in this.stats.paymentModeMonthlyStats[pmId]) {
					elem[month] = this.stats.paymentModeMonthlyStats[pmId][month]
				}
				data.push(elem)
			}
			return data
		},
		memberMonthlyPaidChartData() {
			const memberDatasets = []
			let member
			let index = 0
			const memberDict = {
				...this.members,
				0: {
					name: t('cospend', 'All members'),
					color: this.myGetMemberColor(0),
				},
			}
			for (const mid in memberDict) {
				member = memberDict[mid]
				const paid = []
				for (const month of this.categoryMonths) {
					if (mid in this.stats.memberMonthlyPaidStats[month]) {
						paid.push(this.stats.memberMonthlyPaidStats[month][mid].toFixed(2))
					}
				}
				// check if data is complete (would be better to be sure of member list, like get it from the stats request)
				if (paid.length !== this.categoryMonths.length) {
					continue
				}

				const dataset = {
					label: member.name,
					// FIXME hacky way to change alpha channel:
					backgroundColor: '#' + member.color + '4D',
					pointBackgroundColor: '#' + member.color,
					borderColor: '#' + member.color,
					pointHighlightStroke: '#' + member.color,
					// lineTension: 0.2,
					pointRadius: 0,
					data: paid,
					hidden: parseInt(mid) === 0,
				}
				if (index === 0) {
					// dataset.fill = 'origin'
				}
				index++
				memberDatasets.push(dataset)
			}
			return {
				labels: this.categoryMonths,
				datasets: memberDatasets,
			}
		},
		memberMonthlySpentChartData() {
			const memberDatasets = []
			let member
			let index = 0
			const memberDict = {
				...this.members,
				0: {
					name: t('cospend', 'All members'),
					color: this.myGetMemberColor(0),
				},
			}
			for (const mid in memberDict) {
				member = memberDict[mid]
				const paid = []
				for (const month of this.categoryMonths) {
					if (mid in this.stats.memberMonthlySpentStats[month]) {
						paid.push(this.stats.memberMonthlySpentStats[month][mid].toFixed(2))
					}
				}
				// check if data is complete (would be better to be sure of member list, like get it from the stats request)
				if (paid.length !== this.categoryMonths.length) {
					continue
				}

				const dataset = {
					label: member.name,
					// FIXME hacky way to change alpha channel:
					backgroundColor: '#' + member.color + '4D',
					pointBackgroundColor: '#' + member.color,
					borderColor: '#' + member.color,
					pointHighlightStroke: '#' + member.color,
					// lineTension: 0.2,
					pointRadius: 0,
					data: paid,
					hidden: parseInt(mid) === 0,
				}
				if (index === 0) {
					// dataset.fill = 'origin'
				}
				index++
				memberDatasets.push(dataset)
			}
			return {
				labels: this.categoryMonths,
				datasets: memberDatasets,
			}
		},
		memberMonthlyPaidChartOptions() {
			return {
				elements: {
					line: {
						// by default, fill lines to the previous dataset
						// fill: '-1',
						fill: false,
					},
				},
				scales: {
					yAxes: [{
						// stacked: true,
					}],
				},
				title: {
					display: true,
					text: t('cospend', 'Payments per member per month'),
				},
				responsive: true,
				maintainAspectRatio: false,
				showAllTooltips: false,
				hover: {
					intersect: false,
					mode: 'index',
				},
				tooltips: {
					intersect: false,
					mode: 'index',
				},
				legend: {
					position: 'left',
				},
				onHover: this.onMemberMonthlyPaidChartHover,
			}
		},
		memberMonthlySpentChartOptions() {
			return {
				...this.memberMonthlyPaidChartOptions,
				title: {
					display: true,
					text: t('cospend', 'Spendings per member per month'),
				},
				onHover: this.onMemberMonthlySpentChartHover,
			}
		},
		monthlyCategoryChartData() {
			const categoryDatasets = []
			let category
			let index = 0

			this.sortedMonthlyCategoryIds.forEach((catId) => {
				category = this.myGetCategory(catId)

				// Build time series:
				const paid = []
				for (const month of this.categoryMonths) {
					if (month in this.stats.categoryMonthlyStats[catId]) {
						paid.push(this.stats.categoryMonthlyStats[catId][month].toFixed(2))
					} else {
						paid.push(0)
					}
				}

				const dataset = {
					label: category.icon + ' ' + category.name,
					// FIXME hacky way to change alpha channel:
					backgroundColor: category.color + '4D',
					pointBackgroundColor: category.color,
					borderColor: category.color,
					pointHighlightStroke: category.color,
					// lineTension: 0.2,
					pointRadius: 0,
					data: paid,
				}
				if (index === 0) {
					// dataset.fill = 'origin'
				}
				index++
				categoryDatasets.push(dataset)
			})
			return {
				labels: this.categoryMonths,
				datasets: categoryDatasets,
			}
		},
		monthlyCategoryChartOptions() {
			return {
				...this.memberMonthlyPaidChartOptions,
				title: {
					display: true,
					text: t('cospend', 'Payments per category per month'),
				},
				onHover: this.onMonthlyCategoryChartHover,
			}
		},
		monthlyPaymentModeChartData() {
			const paymentModeDatasets = []
			let paymentMode
			let index = 0
			for (const pmId in this.stats.paymentModeMonthlyStats) {
				paymentMode = this.myGetPaymentMode(pmId)

				// Build time series:
				const paid = []
				for (const month of this.paymentModeMonths) {
					if (month in this.stats.paymentModeMonthlyStats[pmId]) {
						paid.push(this.stats.paymentModeMonthlyStats[pmId][month].toFixed(2))
					} else {
						paid.push(0)
					}
				}

				const dataset = {
					label: paymentMode.icon + ' ' + paymentMode.name,
					// FIXME hacky way to change alpha channel:
					backgroundColor: paymentMode.color + '4D',
					pointBackgroundColor: paymentMode.color,
					borderColor: paymentMode.color,
					pointHighlightStroke: paymentMode.color,
					// lineTension: 0.2,
					pointRadius: 0,
					data: paid,
				}
				if (index === 0) {
					// dataset.fill = 'origin'
				}
				index++
				paymentModeDatasets.push(dataset)
			}
			return {
				labels: this.paymentModeMonths,
				datasets: paymentModeDatasets,
			}
		},
		monthlyPaymentModeChartOptions() {
			return {
				...this.memberMonthlyPaidChartOptions,
				title: {
					display: true,
					text: t('cospend', 'Payments per payment mode per month'),
				},
				onHover: this.onMonthlyPaymentModeChartHover,
			}
		},
		memberPieData() {
			const memberBackgroundColors = this.stats.stats.map((stat) => '#' + this.members[stat.member.id].color)
			return {
				// 2 datasets: paid and spent
				datasets: [{
					data: this.stats.stats.map((stat) => stat.paid.toFixed(2)),
					backgroundColor: memberBackgroundColors,
				}, {
					data: this.stats.stats.map((stat) => stat.spent.toFixed(2)),
					backgroundColor: memberBackgroundColors,
				}],
				labels: this.stats.stats.map((stat) => stat.member.name),
			}
		},
		memberPieOptions() {
			return {
				title: {
					display: true,
					text: t('cospend', 'Who paid (outside circle) and spent (inside pie)?'),
				},
				responsive: true,
				showAllTooltips: false,
				legend: {
					position: 'left',
				},
			}
		},
		sortedCategoryStatsIds() {
			const sortedCategoryIds = this.sortedCategories.filter((cat) => {
				return this.stats.categoryStats[cat.id]
			}).map(cat => cat.id)
			const catIds = Object.keys(this.stats.categoryStats).map(id => parseInt(id))
			const diff = [...catIds].filter(cid => !sortedCategoryIds.includes(cid))
			sortedCategoryIds.push(...diff)
			return sortedCategoryIds
		},
		categoryPieData() {
			const categoryData = {
				datasets: [{
					data: [],
					backgroundColor: [],
				}],
				labels: [],
			}
			let paid, category
			this.sortedCategoryStatsIds.forEach((catId) => {
				paid = this.stats.categoryStats[catId].toFixed(2)
				category = this.myGetCategory(catId)

				categoryData.datasets[0].data.push(paid)
				categoryData.datasets[0].backgroundColor.push(category.color)
				categoryData.labels.push(category.icon + ' ' + category.name)
			})
			return categoryData
		},
		categoryPieOptions() {
			return {
				...this.memberPieOptions,
				title: {
					display: true,
					text: t('cospend', 'What was paid per category?'),
				},
			}
		},
		paymentModePieData() {
			const paymentModeData = {
				datasets: [{
					data: [],
					backgroundColor: [],
				}],
				labels: [],
			}
			let paid, paymentMode
			for (const pmId in this.stats.paymentModeStats) {
				paid = this.stats.paymentModeStats[pmId].toFixed(2)
				paymentMode = this.myGetPaymentMode(pmId)

				paymentModeData.datasets[0].data.push(paid)
				paymentModeData.datasets[0].backgroundColor.push(paymentMode.color)
				paymentModeData.labels.push(paymentMode.icon + ' ' + paymentMode.name)
			}
			return paymentModeData
		},
		paymentModePieOptions() {
			return {
				...this.memberPieOptions,
				title: {
					display: true,
					text: t('cospend', 'What was paid per payment mode?'),
				},
			}
		},
		categoryMemberPieData() {
			const catid = this.selectedCategoryId
			const categoryData = {
				datasets: [{
					data: [],
					backgroundColor: [],
				}],
				labels: [],
			}
			const categoryStats = this.stats.categoryMemberStats[catid]
			let memberName, paid, color
			for (const mid in categoryStats) {
				memberName = this.members[mid].name
				color = '#' + this.members[mid].color
				paid = categoryStats[mid].toFixed(2)
				categoryData.datasets[0].data.push(paid)
				categoryData.datasets[0].backgroundColor.push(color)
				categoryData.labels.push(memberName)
			}
			return categoryData
		},
		// keeping this computed in case vue-chartjs make options reactive...
		categoryMemberPieOptions() {
			return {
				...this.memberPieOptions,
				title: {
					display: true,
					text: t('cospend', 'Who paid for this category?'),
				},
			}
		},
		memberPolarPieData() {
			const memberData = {
				datasets: [{
					data: [],
					backgroundColor: [],
				}],
				labels: [],
			}
			let category, paid
			this.sortedCategoryStatsIds.forEach((catId) => {
				category = this.myGetCategory(catId)
				paid = this.stats.categoryMemberStats[catId][this.selectedMemberId].toFixed(2)
				memberData.datasets[0].data.push(paid)
				memberData.datasets[0].backgroundColor.push(category.color)
				memberData.labels.push(category.icon + ' ' + category.name)
			})
			return memberData
		},
		// keeping this computed in case vue-chartjs make options reactive...
		memberPolarPieOptions() {
			return {
				title: {
					display: true,
					text: t('cospend', 'What kind of member is she/he?'),
				},
				responsive: true,
				showAllTooltips: false,
				legend: {
					position: 'left',
				},
			}
		},
	},

	watch: {
		projectId() {
			this.getStats()
		},
	},

	mounted() {
		this.getStats()
	},

	methods: {
		onMemberMonthlyPaidChartHover(event, data) {
			if (data.length > 0 && data[0]._index !== undefined) {
				this.selectedMemberMonthlyPaidCol = data[0]._index
			}
		},
		onMemberMonthlySpentChartHover(event, data) {
			if (data.length > 0 && data[0]._index !== undefined) {
				this.selectedMemberMonthlySpentCol = data[0]._index
			}
		},
		onMonthlyCategoryChartHover(event, data) {
			if (data.length > 0 && data[0]._index !== undefined) {
				this.selectedMonthlyCategoryCol = data[0]._index
			}
		},
		onMonthlyPaymentModeChartHover(event, data) {
			if (data.length > 0 && data[0]._index !== undefined) {
				this.selectedMonthlyPaymentModeCol = data[0]._index
			}
		},
		myGetPaymentMode(pmId) {
			return paymentModes[pmId] ?? {
				name: t('cospend', 'None'),
				icon: '',
				color: 'black',
			}
		},
		getPaymentModeNameIcon(pmId) {
			const paymentMode = this.myGetPaymentMode(pmId)
			return paymentMode.icon + ' ' + paymentMode.name
		},
		myGetCategory(catid) {
			return getCategory(this.projectId, catid)
		},
		getCategoryNameIcon(catid) {
			const category = this.myGetCategory(catid)
			return category.icon + ' ' + category.name
		},
		onMemberPolarChange() {
			const mid = this.$refs.memberPolarSelect.value
			this.selectedMemberId = mid
		},
		onCategoryMemberChange() {
			const catId = this.$refs.categoryMemberSelect.value
			this.selectedCategoryId = catId
		},
		getBalanceClass(balance) {
			let balanceClass = ''
			if (balance > 0) {
				balanceClass = 'balancePositive'
			} else if (balance < 0) {
				balanceClass = 'balanceNegative'
			}
			return balanceClass
		},
		isMemberDisabled(mid) {
			return !this.members[mid].activated
		},
		getMemberColor(mid) {
			return this.members[mid].color || ''
		},
		getMemberUserId(mid) {
			return this.members[mid].userid || ''
		},
		getMemberName(mid) {
			return this.members[mid].name
		},
		myGetSmartMemberName(mid) {
			let smartName = getSmartMemberName(this.projectId, mid)
			if (smartName === t('cospend', 'You')) {
				smartName += ' (' + this.members[mid].name + ')'
			}
			return smartName
		},
		myGetMemberColor(mid) {
			if (mid === 0) {
				return '999999'
			} else {
				return this.members[mid].color
			}
		},
		onChangeCenterMember(e) {
			this.getSettlement(e.target.value)
		},
		getStats() {
			this.stats = null
			this.loadingStats = true
			const dateMin = this.$refs.dateMinFilter.value
			const dateMax = this.$refs.dateMaxFilter.value
			const tsMin = (dateMin !== '') ? moment(dateMin).unix() : null
			const tsMax = (dateMax !== '') ? moment(dateMax).unix() + (24 * 60 * 60) - 1 : null
			const paymentMode = this.$refs.paymentModeFilter.value
			const category = this.$refs.categoryFilter.value
			const amountMin = this.$refs.amountMinFilter.value || null
			const amountMax = this.$refs.amountMaxFilter.value || null
			const showDisabled = this.$refs.showDisabledFilter.checked
			const currencyId = this.$refs.currencySelect.value
			const req = {
				tsMin,
				tsMax,
				paymentMode,
				category,
				amountMin,
				amountMax,
				showDisabled: showDisabled ? '1' : '0',
				currencyId,
			}
			const isFiltered = (
				   (dateMin !== null && dateMin !== '')
				|| (dateMax !== null && dateMax !== '')
				|| (paymentMode !== null && paymentMode !== 'n')
				|| (category !== null && parseInt(category) !== 0)
				|| (amountMin !== null && amountMin !== '')
				|| (amountMax !== null && amountMax !== '')
			)
			network.getStats(this.projectId, req, isFiltered, this.getStatsSuccess, this.getStatsDone)
		},
		getStatsSuccess(response, isFiltered) {
			this.stats = response
			this.isFiltered = isFiltered
		},
		getStatsDone() {
			this.loadingStats = false
			this.onCategoryMemberChange()
		},
		onExportClick() {
			this.exporting = true
			const dateMin = this.$refs.dateMinFilter.value
			const dateMax = this.$refs.dateMaxFilter.value
			const tsMin = (dateMin !== '') ? moment(dateMin).unix() : null
			const tsMax = (dateMax !== '') ? moment(dateMax).unix() + (24 * 60 * 60) - 1 : null
			const paymentMode = this.$refs.paymentModeFilter.value
			const category = this.$refs.categoryFilter.value
			const amountMin = this.$refs.amountMinFilter.value
			const amountMax = this.$refs.amountMaxFilter.value
			const showDisabled = this.$refs.showDisabledFilter.checked
			const currencyId = this.$refs.currencySelect.value
			const req = {
				tsMin,
				tsMax,
				paymentMode,
				category,
				amountMin,
				amountMax,
				showDisabled: showDisabled ? '1' : '0',
				currencyId,
			}
			network.exportStats(this.projectId, req, this.exportStatsDone)
		},
		exportStatsDone() {
			this.exporting = false
		},
	},
}
</script>

<style scoped lang="scss">
#statsTitle {
	padding: 20px 0px 20px 0px;
}

#stats-filters {
	max-width: 900px;
	margin-left: 20px;
	display: grid;
	grid-template: 1fr / 1fr 1fr 1fr 1fr;
}

#stats-filters select {
	width: 130px;
}

#stats-filters label {
	line-height: 40px;
}

#memberPolarChart,
#categoryMemberChart,
#memberChart,
#paymentModeChart,
#categoryChart {
	max-width: 400px;
	margin: 0 auto 0 auto;
}

#categoryMemberSelect,
#memberPolarSelect {
	display: block;
	margin-left: auto;
	margin-right: auto;
}

.checkboxlabel {
	grid-column: 3 / 5;
}

.statistics-content {
	// flex: 1 1 500px;
	flex-grow: 1;
	width: 500px;
	padding: 0 20px 0 20px;

	#stats-filters {
		margin-left: auto;
		margin-right: auto;
	}

	.totalPayedText {
		text-align: center;
	}
}

::v-deep #statsTitle {
	text-align: center;
	padding: 20px 0px 20px 20px;

	.icon {
		min-width: 23px !important;
		min-height: 23px !important;
		width: 30px;
		vertical-align: middle;
		display: inline-block;
	}
	button {
		.icon {
			min-height: 16px !important;
			vertical-align: text-bottom;
		}
	}
}

.statTableTitle {
	padding: 0px 0px 0px 20px !important;
}

#statsTable {
	display: table;
	margin-left: auto;
	margin-right: auto;
}

.totalPayedText {
	margin: 0px 20px 0px 20px;
}

::v-deep .coloredTable svg {
	margin-bottom: -3px;
}

::v-deep #paidForTable th.avatared .owerAvatar {
	margin-left: 0;
}

#paidForTable,
#memberMonthlyPaidTable,
#memberMonthlySpentTable,
#categoryTable,
#paymentModeTable {
	overflow: scroll;
}

#memberMonthlyPaidTable tr.all-members td:first-child,
#memberMonthlySpentTable tr.all-members td:first-child,
#paymentModeTable td:first-child,
#categoryTable td:first-child {
	padding: 0px 5px 0px 5px;
}

.loading-stats-animation {
	height: 70px;
}

td.selected {
	background-color: var(--color-background-dark);
}
</style>
