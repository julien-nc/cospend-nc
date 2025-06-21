<template>
	<NcAppContentDetails class="statistics-content">
		<h2 id="statsTitle">
			<ChartLineIcon
				:size="20" />
			{{ t('cospend', 'Statistics of project {name}', { name: project.name }, undefined, { escape: false }) }}
			<NcButton v-if="!cospend.pageIsPublic"
				class="exportStats"
				projectid="dum"
				@click="onExportClick">
				<template #icon>
					<NcLoadingIcon v-if="exporting" />
					<ContentSaveIcon v-else :size="20" />
				</template>
				{{ t('cospend', 'Export') }}
			</NcButton>
		</h2>
		<div id="stats-filters">
			<label for="date-min-stats">
				<CalendarStartIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Minimum date') }}
			</label>
			<input id="date-min-stats"
				ref="dateMinFilter"
				type="date"
				@change="getStats">
			<label for="date-max-stats">
				<CalendarEndIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Maximum date') }}
			</label>
			<input id="date-max-stats"
				ref="dateMaxFilter"
				type="date"
				@change="getStats">
			<label for="payment-mode-stats">
				<TagIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Payment mode') }}
			</label>
			<PaymentModeMultiSelect
				id="payment-mode-stats"
				:value="selectedFilterPm"
				:payment-modes="sortedFilterPms"
				:placeholder="t('cospend', 'Select a payment mode')"
				@input="paymentModeFilterSelected" />
			<label for="category-stats">
				<ShapeIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Category') }}
			</label>
			<CategoryMultiSelect
				id="category-stats"
				:value="selectedFilterCategory"
				:categories="sortedFilterCategories"
				:placeholder="t('cospend', 'Select a category')"
				@input="categoryFilterSelected" />
			<label for="amount-min-stats">
				<CurrencyUsdIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Minimum amount') }}
			</label>
			<input id="amount-min-stats"
				ref="amountMinFilter"
				type="number"
				@change="getStats">
			<label for="amount-max-stats">
				<CurrencyUsdIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Maximum amount') }}
			</label>
			<input id="amount-max-stats"
				ref="amountMaxFilter"
				type="number"
				@change="getStats">
			<label for="currency-stats">
				<CurrencyIcon class="icon" :size="20" />
				{{ t('cospend', 'Convert in currency') }}
			</label>
			<select id="currency-stats"
				ref="currencySelect"
				v-model="selectedCurrencyId"
				@change="getStats">
				<option :value="0">
					{{ project.currencyname || t('cospend', 'Main project\'s currency') }}
				</option>
				<option v-for="currency in currencies"
					:key="currency.id"
					:value="currency.id">
					{{ currency.name }}
				</option>
			</select>
			<label for="payer-stats">
				<AccountIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Payer') }}
			</label>
			<MemberMultiSelect
				:project-id="project.id"
				:value="selectedFilterPayer"
				:placeholder="t('cospend', 'Choose a member')"
				:members="filterMembers"
				@input="payerFilterSelected" />
			<div />
			<div />
			<NcCheckboxRadioSwitch
				v-model="showDisabled"
				class="checkFilter"
				@update:model-value="getStats">
				{{ t('cospend', 'Show disabled members') }}
			</NcCheckboxRadioSwitch>
			<label for="prefChartType">
				<ChartBarIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Chart type') }}
			</label>
			<select
				id="prefChartType"
				v-model="preferredChartType">
				<option value="pie">
					{{ t('cospend', 'Pie') }}
				</option>
				<option value="bar">
					{{ t('cospend', 'Bar') }}
				</option>
			</select>
		</div>
		<br>
		<p v-if="stats"
			class="totalPayedText">
			<span v-if="selectedCurrencyName">
				{{ t('cospend', 'Total paid by all the members: {t} {currencyName}', { t: totalPayed.toFixed(2), currencyName: selectedCurrencyName }) }}
			</span>
			<span v-else>
				{{ t('cospend', 'Total paid by all the members: {t}', { t: totalPayed.toFixed(2) }) }}
			</span>
		</p>
		<br><hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Global stats') }}
		</h2>
		<v-table v-if="stats"
			id="statsTable"
			class="coloredTable"
			:data="stats.stats">
			<template #head>
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
				<v-th sort-key="balance"
					:title="t('cospend', 'This balance is computed from the complete bill list')">
					{{ t('cospend', 'Global Balance') }}
				</v-th>
			</template>
			<template #body="{ rows }">
				<tr v-for="row in rows"
					:key="row.member.id">
					<td :style="'border: 2px solid #' + myGetMemberColor(row.member.id) + ';'">
						<div class="left-aligned-cell-content">
							<MemberAvatar
								:member="members[row.member.id]"
								:size="24" />
							<span>
								{{ myGetSmartMemberName(row.member.id) }}
							</span>
						</div>
					</td>
					<td :style="'border: 2px solid #' + myGetMemberColor(row.member.id) + ';'">
						{{ row.paid.toFixed(2) }}
						{{ selectedCurrencyName }}
					</td>
					<td :style="'border: 2px solid #' + myGetMemberColor(row.member.id) +';'">
						{{ row.spent.toFixed(2) }}
						{{ selectedCurrencyName }}
					</td>
					<td v-if="isFiltered"
						:class="getBalanceClass(row.filtered_balance)"
						:style="'border: 2px solid #' + myGetMemberColor(row.member.id) +';'">
						{{ row.filtered_balance.toFixed(2) }}
						{{ selectedCurrencyName }}
					</td>
					<td :class="getBalanceClass(row.balance)"
						:style="'border: 2px solid #' + myGetMemberColor(row.member.id) +';'"
						:title="t('cospend', 'This balance is computed from the complete bill list')">
						{{ row.balance.toFixed(2) }}
						{{ selectedCurrencyName }}
					</td>
				</tr>
			</template>
			<tfoot />
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly paid per member') }}
		</h2>
		<MemberMonthly v-if="stats"
			:stats="stats.memberMonthlyPaidStats || {}"
			:project-id="projectId"
			:member-ids="stats.memberIds"
			:real-months="stats.realMonths"
			:chart-title="t('cospend', 'Payments per member per month')"
			:base-line-chart-options="baseLineChartOptions"
			:currency-name="selectedCurrencyName" />
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly spent per member') }}
		</h2>
		<MemberMonthly v-if="stats"
			:stats="stats.memberMonthlySpentStats || {}"
			:project-id="projectId"
			:member-ids="stats.memberIds"
			:real-months="stats.realMonths"
			:chart-title="t('cospend', 'Spendings per member per month')"
			:base-line-chart-options="baseLineChartOptions"
			:currency-name="selectedCurrencyName" />
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly paid per category') }}
		</h2>
		<Monthly v-if="stats"
			:table-data="monthlyCategoryStats"
			:chart-data="monthlyCategoryChartData"
			:distinct-months="distinctMonths"
			:chart-title="t('cospend', 'Payments per category per month')"
			:first-column-title="t('cospend', 'Category/Month')"
			:base-line-chart-options="baseLineChartOptions"
			:currency-name="selectedCurrencyName" />
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Monthly paid per payment mode') }}
		</h2>
		<Monthly v-if="stats"
			:table-data="monthlyPaymentModeStats"
			:chart-data="monthlyPaymentModeChartData"
			:distinct-months="distinctMonths"
			:chart-title="t('cospend', 'Payments per payment mode per month')"
			:first-column-title="t('cospend', 'Payment mode/Month')"
			:base-line-chart-options="baseLineChartOptions"
			:currency-name="selectedCurrencyName" />
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<hr>
		<div id="memberChart">
			<PieChartJs v-if="stats && preferredChartType === 'pie'"
				:chart-data="memberPieData"
				:chart-options="memberPieOptions" />
			<BarChartJs v-else-if="stats && preferredChartType === 'bar'"
				:chart-data="memberPieData"
				:chart-options="memberBarOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="categoryChart">
			<PieChartJs v-if="stats && preferredChartType === 'pie'"
				:chart-data="categoryPieData"
				:chart-options="categoryPieOptions" />
			<BarChartJs v-else-if="stats && preferredChartType === 'bar'"
				:chart-data="categoryPieData"
				:chart-options="categoryBarOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="paymentModeChart">
			<PieChartJs v-if="stats && preferredChartType === 'pie'"
				:chart-data="paymentModePieData"
				:chart-options="paymentModePieOptions" />
			<BarChartJs v-if="stats && preferredChartType === 'bar'"
				:chart-data="paymentModePieData"
				:chart-options="paymentModeBarOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="categoryMemberTitle">
			<label for="categoryMemberSelect">
				{{ t('cospend', 'Who paid for this category?') }}
			</label>
			<CategoryMultiSelect v-if="stats"
				id="categoryMemberSelect"
				:value="selectedCategory"
				:categories="sortedCategoryStats"
				:placeholder="t('cospend', 'Select a category')"
				@input="categorySelected" />
		</div>
		<div id="categoryMemberChart">
			<PieChartJs v-if="stats && (selectedCategoryId !== -1) && preferredChartType === 'pie'"
				:chart-data="categoryMemberPieData"
				:chart-options="categoryMemberPieOptions" />
			<BarChartJs v-else-if="stats && (selectedCategoryId !== -1) && preferredChartType === 'bar'"
				:chart-data="categoryMemberPieData"
				:chart-options="categoryMemberBarOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<div id="memberPerCategoryTitle">
			<label for="memberPerCategorySelect">
				{{ t('cospend', 'What did they pay for?') }}
			</label>
			<MemberMultiSelect v-if="stats"
				id="memberPerCategoryMultiSelect"
				:project-id="projectId"
				:value="selectedMember"
				:placeholder="t('cospend', 'Select a member')"
				:members="membersWithStatsArray"
				@input="memberSelected" />
		</div>
		<div id="memberPerCategoryChart">
			<PieChartJs v-if="stats && (selectedMemberId !== -1) && preferredChartType === 'pie'"
				:chart-data="memberPerCategoryPieData"
				:chart-options="memberPerCategoryPieOptions" />
			<BarChartJs v-else-if="stats && (selectedMemberId !== -1) && preferredChartType === 'bar'"
				:chart-data="memberPerCategoryPieData"
				:chart-options="memberPerCategoryBarOptions" />
			<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		</div>
		<hr>
		<h2 class="statTableTitle">
			{{ t('cospend', 'Who paid for whom?') }}
		</h2>
		<v-table v-if="stats"
			id="paidForTable"
			class="coloredTable"
			:data="membersPaidForData">
			<template #head>
				<v-th sort-key="name">
					↓ {{ t('cospend', 'paid for') }} →
				</v-th>
				<v-th v-for="mid in stats.allMemberIds"
					:key="mid"
					:sort-key="mid.toString()"
					class="avatared centered-cell"
					:style="'border: 2px solid #' + myGetMemberColor(mid) + ';'">
					<div class="left-aligned-cell-content">
						<MemberAvatar
							:member="members[mid]"
							:size="24" />
						<span>{{ myGetSmartMemberName(mid) }}</span>
					</div>
				</v-th>
				<v-th sort-key="total">
					{{ t('cospend', 'Total paid') }}
				</v-th>
			</template>
			<template #body="{ rows }">
				<tr v-for="row in rows"
					:key="row.memberid">
					<td v-if="row.memberid !== 0"
						class="centered-cell"
						:style="'border: 2px solid #' + myGetMemberColor(row.memberid) + ';'">
						<div class="left-aligned-cell-content">
							<MemberAvatar
								:member="members[row.memberid]"
								:size="24" />
							<span>{{ myGetSmartMemberName(row.memberid) }}</span>
						</div>
					</td>
					<td v-else style="padding-left: 5px; border: 2px solid lightgrey;">
						{{ t('cospend', 'Total owed') }}
					</td>
					<td v-for="mid in stats.allMemberIds"
						:key="row.memberid + '-' + mid"
						:title="row.memberid === 0
							? t('cospend', 'Total owed by {name}', { name: myGetSmartMemberName(mid) })
							: myGetSmartMemberName(row.memberid) + ' → ' + myGetSmartMemberName(mid)"
						:style="'border: 2px solid ' + (row.memberid === 0 ? 'lightgrey' : '#' + myGetMemberColor(row.memberid)) + ';'">
						{{ row[mid].toFixed(2) }}
						{{ selectedCurrencyName }}
					</td>
					<td v-if="row.memberid !== 0"
						:title="t('cospend', 'Total paid by {name}', { name: myGetSmartMemberName(row.memberid) })"
						style="border: 2px solid lightgrey;">
						{{ row.total.toFixed(2) }}
						{{ selectedCurrencyName }}
					</td>
				</tr>
			</template>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
	</NcAppContentDetails>
</template>

<script>
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import CalendarEndIcon from 'vue-material-design-icons/CalendarEnd.vue'
import CalendarStartIcon from 'vue-material-design-icons/CalendarStart.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import ShapeIcon from 'vue-material-design-icons/Shape.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd.vue'

import CurrencyIcon from '../icons/CurrencyIcon.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcAppContentDetails from '@nextcloud/vue/components/NcAppContentDetails'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import MemberAvatar from '../avatar/MemberAvatar.vue'
import MemberMultiSelect from '../MemberMultiSelect.vue'
import CategoryMultiSelect from '../CategoryMultiSelect.vue'
import PaymentModeMultiSelect from '../PaymentModeMultiSelect.vue'
import MemberMonthly from './MemberMonthly.vue'
import Monthly from './Monthly.vue'
import PieChartJs from '../PieChartJs.vue'
import BarChartJs from '../BarChartJs.vue'

import moment from '@nextcloud/moment'
import { getCategory, getPaymentMode, getSmartMemberName, strcmp } from '../../utils.js'
import * as network from '../../network.js'
import * as constants from '../../constants.js'

export default {
	name: 'Statistics',

	components: {
		MemberAvatar,
		CurrencyIcon,
		MemberMultiSelect,
		PieChartJs,
		BarChartJs,
		MemberMonthly,
		Monthly,
		NcAppContentDetails,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		CategoryMultiSelect,
		PaymentModeMultiSelect,
		CalendarStartIcon,
		CalendarEndIcon,
		ShapeIcon,
		TagIcon,
		AccountIcon,
		ChartBarIcon,
		ChartLineIcon,
		CurrencyUsdIcon,
		ContentSaveIcon,
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
			selectedFilterCategory: {
				id: -100,
				icon: '',
				name: t('cospend', 'All except reimbursement'),
			},
			selectedFilterPm: {
				id: null,
				icon: '',
				name: t('cospend', 'All'),
			},
			selectedFilterPayer: {
				id: null,
				name: t('cospend', 'All members'),
			},
			selectedCategoryId: -1,
			selectedMemberId: -1,
			selectedCurrencyId: 0,
			isFiltered: true,
			cospend: OCA.Cospend.state,
			exporting: false,
			loadingStats: false,
			preferredChartType: 'pie',
			showDisabled: false,
		}
	},

	computed: {
		project() {
			return this.cospend.projects[this.projectId]
		},
		members() {
			return this.cospend.members[this.projectId]
		},
		membersArray() {
			return Object.values(this.members)
		},
		membersWithStatsArray() {
			return this.stats
				? this.membersArray.filter((member) => {
					return this.stats.memberIds.includes(member.id)
				})
				: this.membersArray
		},
		selectedCurrencyName() {
			if (this.selectedCurrencyId === 0) {
				return this.project.currencyname || ''
			}
			const selectedCurrency = this.currencies.find(c => c.id === this.selectedCurrencyId)
			if (selectedCurrency) {
				return selectedCurrency.name
			}
			return ''
		},
		filterMembers() {
			return [
				{
					id: null,
					name: t('cospend', 'All members'),
				},
				...this.membersArray,
			]
		},
		sortedFilterCategories() {
			return [
				{
					id: null,
					icon: '',
					name: t('cospend', 'All'),
				},
				{
					id: 0,
					icon: '',
					name: t('cospend', 'No category'),
				},
				{
					id: -100,
					icon: '',
					name: t('cospend', 'All except reimbursement'),
				},
				...this.sortedCategories,
				...Object.values(this.hardCodedCategories),
			]
		},
		sortedFilterPms() {
			return [
				{
					id: null,
					icon: '',
					name: t('cospend', 'All'),
				},
				{
					id: 0,
					icon: '',
					name: t('cospend', 'No payment mode'),
				},
				...this.sortedPaymentModes,
			]
		},
		selectedMember() {
			if (this.selectedMemberId === -1) {
				return null
			}
			return this.members[this.selectedMemberId]
		},
		selectedCategory() {
			if (this.selectedCategoryId === -1) {
				return null
			}
			return this.cospend.projects[this.projectId].categories[this.selectedCategoryId]
				?? this.hardCodedCategories[this.selectedCategoryId]
		},
		paymentmodes() {
			return this.cospend.projects[this.projectId].paymentmodes
		},
		sortedPaymentModes() {
			if ([
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
			].includes(this.project.paymentmodesort)) {
				return Object.values(this.paymentmodes).slice().sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
			} else if (this.project.paymentmodesort === constants.SORT_ORDER.ALPHA) {
				return Object.values(this.paymentmodes).slice().sort((a, b) => {
					return strcmp(a.name, b.name)
				})
			}
			return []
		},
		categories() {
			return this.cospend.projects[this.projectId].categories
		},
		sortedCategories() {
			if ([
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
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
			return this.cospend.hardCodedCategories
		},
		currencies() {
			return this.cospend.projects[this.projectId].currencies
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
		distinctMonths() {
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
			return this.sortedMonthlyCategoryIds.map((catid) => {
				const elem = {
					id: catid,
					name: this.getCategoryNameIcon(catid),
					color: this.myGetCategory(catid).color,
				}
				for (const month in this.stats.categoryMonthlyStats[catid]) {
					elem[month] = this.stats.categoryMonthlyStats[catid][month]
				}
				return elem
			})
		},
		sortedMonthlyPaymentModeIds() {
			const sortedPaymentModeIds = this.sortedPaymentModes.filter((pm) => {
				return this.stats.paymentModeMonthlyStats[pm.id]
			}).map(pm => pm.id)
			const monthlyPmIds = Object.keys(this.stats.paymentModeMonthlyStats).map(id => parseInt(id))
			const diff = [...monthlyPmIds].filter(pmid => !sortedPaymentModeIds.includes(pmid))
			sortedPaymentModeIds.push(...diff)
			return sortedPaymentModeIds
		},
		monthlyPaymentModeStats() {
			return this.sortedMonthlyPaymentModeIds.map((pmid) => {
				const elem = {
					id: pmid,
					name: this.getPaymentModeNameIcon(pmid),
					color: this.myGetPaymentMode(pmid).color,
				}
				for (const month in this.stats.paymentModeMonthlyStats[pmid]) {
					elem[month] = this.stats.paymentModeMonthlyStats[pmid][month]
				}
				return elem
			})
		},
		baseLineChartOptions() {
			return {
				elements: {
					line: {
						// by default, fill lines to the previous dataset
						// fill: '-1',
						// fill: 'origin',
						cubicInterpolationMode: 'monotone',
					},
				},
				scales: {
					y: {
						// stacked: true,
					},
				},
				plugins: {
					legend: {
						position: 'left',
					},
					tooltip: {
						intersect: false,
						mode: 'index',
					},
				},
				responsive: true,
				maintainAspectRatio: false,
				showAllTooltips: false,
				hover: {
					intersect: false,
					mode: 'index',
				},
			}
		},
		monthlyCategoryChartData() {
			const categoryDatasets = []
			let category

			this.sortedMonthlyCategoryIds.forEach((catId) => {
				category = this.myGetCategory(catId)

				// Build time series:
				const paid = []
				for (const month of this.stats.realMonths) {
					if (month in this.stats.categoryMonthlyStats[catId]) {
						paid.push(this.stats.categoryMonthlyStats[catId][month].toFixed(2))
					} else {
						paid.push(0)
					}
				}

				const dataset = {
					id: parseInt(catId),
					label: category.icon + ' ' + category.name,
					// FIXME hacky way to change alpha channel:
					backgroundColor: category.color + '4D',
					pointBackgroundColor: category.color,
					borderColor: category.color,
					pointHighlightStroke: category.color,
					// lineTension: 0.2,
					data: paid,
					hidden: parseInt(catId) === 0,
					pointRadius: Array(this.stats.realMonths.length).fill(0),
					fill: false,
					order: 0,
					borderWidth: 3,
				}
				categoryDatasets.push(dataset)
			})
			return {
				labels: this.stats.realMonths,
				datasets: categoryDatasets,
			}
		},
		monthlyPaymentModeChartData() {
			const paymentModeDatasets = []
			let pm

			this.sortedMonthlyPaymentModeIds.forEach((pmId) => {
				pm = this.myGetPaymentMode(pmId)

				// Build time series:
				const paid = []
				for (const month of this.stats.realMonths) {
					if (month in this.stats.paymentModeMonthlyStats[pmId]) {
						paid.push(this.stats.paymentModeMonthlyStats[pmId][month].toFixed(2))
					} else {
						paid.push(0)
					}
				}

				const dataset = {
					id: parseInt(pmId),
					label: pm.icon + ' ' + pm.name,
					// FIXME hacky way to change alpha channel:
					backgroundColor: pm.color + '4D',
					pointBackgroundColor: pm.color,
					borderColor: pm.color,
					pointHighlightStroke: pm.color,
					// lineTension: 0.2,
					data: paid,
					hidden: parseInt(pmId) === 0,
					pointRadius: Array(this.stats.realMonths.length).fill(0),
					fill: false,
					order: 0,
					borderWidth: 3,
				}
				paymentModeDatasets.push(dataset)
			})
			return {
				labels: this.stats.realMonths,
				datasets: paymentModeDatasets,
			}
		},
		memberPieData() {
			const memberBackgroundColors = this.stats.stats.map((stat) => '#' + this.members[stat.member.id].color)
			return {
				// 2 datasets: paid and spent
				datasets: [{
					data: this.stats.stats.map((stat) => stat.paid.toFixed(2)),
					backgroundColor: memberBackgroundColors,
					label: t('cospend', 'Payed'),
				}, {
					data: this.stats.stats.map((stat) => stat.spent.toFixed(2)),
					backgroundColor: memberBackgroundColors,
					label: t('cospend', 'Spent'),
				}],
				labels: this.stats.stats.map((stat) => stat.member.name),
			}
		},
		memberPieOptions() {
			return {
				responsive: true,
				showAllTooltips: false,
				plugins: {
					legend: {
						position: 'left',
					},
					title: {
						display: true,
						text: t('cospend', 'Who paid (outside circle) and spent (inside pie)?'),
					},
				},
			}
		},
		memberBarOptions() {
			return {
				...this.memberPieOptions,
				...this.barOptions,
				plugins: {
					...this.memberPieOptions.plugins,
					...this.barOptions.plugins,
					title: {
						display: true,
						text: t('cospend', 'Who paid and spent?'),
					},
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
		sortedCategoryStats() {
			const cats = this.sortedCategories.filter((cat) => {
				return this.sortedCategoryStatsIds.includes(cat.id)
			})
			const hardcodedCats = Object.values(this.hardCodedCategories).filter((cat) => {
				return this.sortedCategoryStatsIds.includes(cat.id)
			})
			cats.push(...hardcodedCats)
			return cats
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
				plugins: {
					...this.memberPieOptions.plugins,
					title: {
						display: true,
						text: t('cospend', 'How much was paid per category?'),
					},
				},
			}
		},
		categoryBarOptions() {
			return {
				...this.categoryPieOptions,
				...this.barOptions,
				plugins: {
					...this.categoryPieOptions.plugins,
					...this.barOptions.plugins,
				},
			}
		},
		sortedPaymentModeStatsIds() {
			const sortedPaymentModeIds = this.sortedPaymentModes.filter((pm) => {
				return this.stats.paymentModeStats[pm.id]
			}).map(pm => pm.id)
			const pmIds = Object.keys(this.stats.paymentModeStats).map(id => parseInt(id))
			const diff = [...pmIds].filter(pmid => !sortedPaymentModeIds.includes(pmid))
			sortedPaymentModeIds.push(...diff)
			return sortedPaymentModeIds
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
			this.sortedPaymentModeStatsIds.forEach((pmId) => {
				paid = this.stats.paymentModeStats[pmId].toFixed(2)
				paymentMode = this.myGetPaymentMode(pmId)

				paymentModeData.datasets[0].data.push(paid)
				paymentModeData.datasets[0].backgroundColor.push(paymentMode.color)
				paymentModeData.labels.push(paymentMode.icon + ' ' + paymentMode.name)
			})
			return paymentModeData
		},
		paymentModePieOptions() {
			return {
				...this.memberPieOptions,
				plugins: {
					...this.memberPieOptions.plugins,
					title: {
						display: true,
						text: t('cospend', 'How much was paid per payment mode?'),
					},
				},
			}
		},
		paymentModeBarOptions() {
			return {
				...this.paymentModePieOptions,
				...this.barOptions,
				plugins: {
					...this.paymentModePieOptions.plugins,
					...this.barOptions.plugins,
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
				plugins: {
					...this.memberPieOptions.plugins,
					title: {
						display: false,
					},
				},
			}
		},
		categoryMemberBarOptions() {
			return {
				...this.categoryMemberPieOptions,
				...this.barOptions,
				plugins: {
					...this.categoryMemberPieOptions.plugins,
					...this.barOptions.plugins,
				},
			}
		},
		memberPerCategoryPieData() {
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
		memberPerCategoryPieOptions() {
			return {
				...this.memberPieOptions,
				plugins: {
					...this.memberPieOptions.plugins,
					title: {
						display: false,
					},
				},
			}
		},
		memberPerCategoryBarOptions() {
			return {
				...this.memberPerCategoryPieOptions,
				...this.barOptions,
				plugins: {
					...this.memberPerCategoryPieOptions.plugins,
					...this.barOptions.plugins,
				},
			}
		},
		barOptions() {
			return {
				scales: {
					y: {
						display: true,
						beginAtZero: true,
					},
				},
				plugins: {
					legend: {
						display: false,
					},
				},
			}
		},
	},

	watch: {
		projectId() {
			this.getStats()
			this.selectedMemberId = -1
			this.selectedCategoryId = -1
		},
	},

	mounted() {
		this.getStats()
	},

	methods: {
		memberSelected(selected) {
			if (selected?.id) {
				this.selectedMemberId = selected.id
			}
		},
		payerFilterSelected(selected) {
			if (selected !== null) {
				this.selectedFilterPayer = selected
				this.getStats()
			}
		},
		categoryFilterSelected(selected) {
			if (selected !== null) {
				this.selectedFilterCategory = selected
				this.getStats()
			}
		},
		paymentModeFilterSelected(selected) {
			if (selected !== null) {
				this.selectedFilterPm = selected
				this.getStats()
			}
		},
		categorySelected(selected) {
			if (selected?.id) {
				this.selectedCategoryId = selected.id
			}
		},
		myGetPaymentMode(pmId) {
			return getPaymentMode(this.projectId, pmId)
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
		getBalanceClass(balance) {
			let balanceClass = ''
			if (balance > 0) {
				balanceClass = 'balancePositive'
			} else if (balance < 0) {
				balanceClass = 'balanceNegative'
			}
			return balanceClass
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
		getStats() {
			this.stats = null
			this.loadingStats = true
			const dateMin = this.$refs.dateMinFilter.value
			const dateMax = this.$refs.dateMaxFilter.value
			const tsMin = (dateMin !== '') ? moment(dateMin).unix() : null
			const tsMax = (dateMax !== '') ? moment(dateMax).unix() + (24 * 60 * 60) - 1 : null
			const paymentModeId = this.selectedFilterPm.id
			const categoryId = this.selectedFilterCategory.id
			const amountMin = this.$refs.amountMinFilter.value || null
			const amountMax = this.$refs.amountMaxFilter.value || null
			const currencyId = this.selectedCurrencyId
			const payerId = this.selectedFilterPayer.id
			const req = {
				tsMin,
				tsMax,
				paymentModeId,
				categoryId,
				amountMin,
				amountMax,
				showDisabled: this.showDisabled ? '1' : '0',
				currencyId,
				payerId,
			}
			const isFiltered = (
				   (dateMin !== null && dateMin !== '')
				|| (dateMax !== null && dateMax !== '')
				|| (paymentModeId !== null)
				|| (categoryId !== null)
				|| (payerId !== null)
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
		},
		onExportClick() {
			this.exporting = true
			const dateMin = this.$refs.dateMinFilter.value
			const dateMax = this.$refs.dateMaxFilter.value
			const tsMin = (dateMin !== '') ? moment(dateMin).unix() : null
			const tsMax = (dateMax !== '') ? moment(dateMax).unix() + (24 * 60 * 60) - 1 : null
			const paymentModeId = this.selectedFilterPm.id
			const category = this.selectedFilterCategory.id
			const amountMin = this.$refs.amountMinFilter.value
			const amountMax = this.$refs.amountMaxFilter.value
			const currencyId = this.$refs.currencySelect.value
			const req = {
				tsMin,
				tsMax,
				paymentModeId,
				category,
				amountMin: amountMin === '' ? undefined : amountMin,
				amountMax: amountMax === '' ? undefined : amountMax,
				showDisabled: this.showDisabled ? '1' : '0',
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
	display: flex;
	align-items: center;
	justify-content: center;
	margin-top: 12px;
	> * {
		margin: 0 8px 0 8px;
	}
}

#stats-filters {
	max-width: 900px;
	margin-left: 20px;
	display: grid;
	grid-template: 1fr / 1fr 1fr 1fr 1fr;

	select,
	#amount-min-stats,
	#amount-max-stats,
	#currency-stats,
	#date-min-stats,
	#date-max-stats {
		width: 200px;
	}

	.checkFilter {
		grid-column: 3/5;
		margin: 8px 0 0 10px;
	}
}

#stats-filters label {
	line-height: 40px;
	display: flex;
	.icon {
		margin: 0 10px 0 10px;
	}
}

#memberPerCategoryChart,
#categoryMemberChart,
#memberChart,
#paymentModeChart,
#categoryChart {
	max-width: 400px;
	margin: 0 auto 0 auto;
}

#categoryMemberTitle,
#memberPerCategoryTitle {
	display: table;
	margin-left: auto;
	margin-right: auto;
}

.checkboxlabel {
	display: block !important;
	grid-column: 3 / 5;
	&::before {
		margin: 0 12px 0 12px !important;
	}
}

.statistics-content {
	// flex: 1 1 500px;
	flex-grow: 1;
	//width: 500px;
	padding: 0 20px 0 20px;

	#stats-filters {
		margin-left: auto;
		margin-right: auto;
	}

	.totalPayedText {
		text-align: center;
	}
}

.statTableTitle {
	padding: 0px 0px 0px 20px !important;
}

#statsTable {
	max-height: 500px;
	overflow: scroll;

	th {
		position: sticky;
		top: 0;
		z-index: 9;
		background-color: var(--color-main-background);
		&:first-child {
			left: 0;
			z-index: 10 !important;
		}
	}
	th:first-child,
	td:first-child {
		position: sticky;
		left: 0;
		z-index: 8;
		background-color: var(--color-main-background);
	}

	th.selected,
	td.selected {
		background-color: var(--color-background-dark);
	}
	td:first-child {
		padding: 4px 5px 4px 5px;
	}
}

.totalPayedText {
	margin: 0px 20px 0px 20px;
}

:deep(.coloredTable svg) {
	margin-bottom: -3px;
}

#paidForTable {
	overflow: scroll;
	max-height: 500px;
	th {
		position: sticky;
		top: 0;
		z-index: 9;
		background-color: var(--color-main-background);
		&:first-child {
			left: 0;
			z-index: 10 !important;
		}
	}
	th:first-child,
	td:first-child {
		position: sticky;
		left: 0;
		z-index: 8;
		background-color: var(--color-main-background);
	}
	td:first-child {
		padding: 4px 5px 4px 5px;
	}
}

.loading-stats-animation {
	height: 70px;
}

table td span {
	vertical-align: middle;
}

:deep(#memberPerCategoryMultiSelect input) {
	padding: 0 0 0 5px !important;
}

#memberPerCategoryMultiSelect {
	width: 250px;
}
</style>
