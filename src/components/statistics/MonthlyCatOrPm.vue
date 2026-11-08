<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<!-- mouseout does not work -->
		<div class="tableWrapper"
			@mouseleave="selectedDataset = null ; hoveredTableMonth = null">
			<VTable
				class="monthlyTable coloredTable"
				:class="{ withTotal: hasAverage }"
				:data="tableData">
				<template #head>
					<VTh sort-key="name">
						{{ firstColumnTitle }}
					</VTh>
					<VTh v-for="(month, index) in distinctMonths"
						:key="month"
						:sort-key="month"
						:class="{ selected: selectedMonthlyCol === index }">
						{{ month }}{{ currencySuffix }}
					</VTh>
					<VTh v-if="hasAverage"
						sort-key="total">
						{{ t('cospend', 'Total') }}{{ currencySuffix }}
					</VTh>
					<VTh v-if="hasAverage"
						:sort-key="averageKey">
						{{ averageKey }}{{ currencySuffix }}
					</VTh>
				</template>
				<template #body="{ rows }">
					<tr v-for="row in rows"
						:key="row.id"
						@mouseenter="selectedDataset = row.id">
						<td :style="'border-left: 4px solid ' + row.color + ';' + myGetRowTintStyle(row.color)">
							{{ row.name }}
						</td>
						<td v-for="(month, index) in distinctMonths"
							:key="month"
							:class="{ selected: selectedMonthlyCol === index }"
							:style="myGetRowTintStyle(row.color)"
							@mouseenter="hoveredTableMonth = month">
							{{ (row[month] || 0).toFixed(2) }}
						</td>
						<td v-if="hasAverage"
							:style="myGetRowTintStyle(row.color)">
							{{ (row.total || 0).toFixed(2) }}
						</td>
						<td v-if="hasAverage"
							:style="myGetRowTintStyle(row.color)">
							{{ (row[averageKey] || 0).toFixed(2) }}
						</td>
					</tr>
				</template>
			</VTable>
		</div>
		<div class="categoryMonthlyChart"
			@mouseleave="selectedMonthlyCol = null">
			<LineChartJs
				:chart-data="myChartData"
				:chart-options="chartOptions" />
		</div>
	</div>
</template>

<script>
import LineChartJs from '../chart.js/LineChartJs.vue'
import { VTable, VTh } from '../../smart-table-components.js'

export default {
	name: 'MonthlyCatOrPm',

	components: {
		LineChartJs,
		VTable,
		VTh,
	},

	props: {
		tableData: {
			type: Array,
			required: true,
		},
		chartData: {
			type: Object,
			required: true,
		},
		distinctMonths: {
			type: Array,
			required: true,
		},
		chartTitle: {
			type: String,
			required: true,
		},
		firstColumnTitle: {
			type: String,
			required: true,
		},
		baseLineChartOptions: {
			type: Object,
			required: true,
		},
		currencyName: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			loadingStats: false,
			selectedMonthlyCol: null,
			selectedDataset: null,
			hoveredTableMonth: null,
		}
	},

	computed: {
		averageKey() {
			return t('cospend', 'Average per month')
		},
		hasAverage() {
			return this.tableData.length > 0 && Object.keys(this.tableData[0]).includes(this.averageKey)
		},
		currencySuffix() {
			return this.currencyName ? ' (' + this.currencyName + ')' : ''
		},
		chartOptions() {
			return {
				...this.baseLineChartOptions,
				plugins: {
					...this.baseLineChartOptions.plugins,
					title: {
						display: true,
						text: this.chartTitle,
					},
				},
				onHover: this.onMonthlyChartHover,
			}
		},
		myChartData() {
			if (this.selectedDataset) {
				// row index
				const selectedDatasetIndex = this.chartData.datasets.findIndex((ds) => {
					return ds.id === this.selectedDataset
				})
				// column index
				const hoveredTableMonthIndex = this.chartData.labels.indexOf(this.hoveredTableMonth)
				const selectedDatasetPointRadius = Array(this.chartData.labels.length).fill(0)
				selectedDatasetPointRadius[hoveredTableMonthIndex] = 10

				return {
					labels: this.chartData.labels,
					datasets: [
						...this.chartData.datasets.slice(0, selectedDatasetIndex),
						{
							...this.chartData.datasets[selectedDatasetIndex],
							pointRadius: selectedDatasetPointRadius,
							order: -1,
							borderWidth: 5,
							fill: 'origin',
						},
						...this.chartData.datasets.slice(selectedDatasetIndex + 1, this.chartData.datasets.length),
					],
				}
			} else {
				return {
					labels: this.chartData.labels,
					datasets: this.chartData.datasets,
				}
			}
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onMonthlyChartHover(event, data) {
			if (data.length > 0 && data[0].index !== undefined) {
				this.selectedMonthlyCol = data[0].index
			}
		},
		myGetRowTintStyle(color) {
			const tint = color + '26'
			return 'background-image: linear-gradient(' + tint + ', ' + tint + ');'
		},
	},
}
</script>

<style scoped lang="scss">
.monthlyTable {
	overflow: scroll;
	max-height: 500px;
	th {
		position: sticky;
		top: 0;
		z-index: 9;
		background-color: var(--color-main-background);
		border-radius: 0;
	}
	td {
		border-bottom: 1px solid var(--color-border);
		border-right: 1px solid var(--color-border);
		border-radius: 0;
		padding: 6px;
	}
	th:first-child,
	td:first-child {
		position: sticky;
		left: 0;
		z-index: 10;
		background-color: var(--color-main-background);
		box-shadow: 2px 0 4px -2px rgba(0, 0, 0, 0.15);
	}
	&.withTotal {
		th:last-child,
		td:last-child,
		th:nth-last-child(2),
		td:nth-last-child(2) {
			position: sticky;
			z-index: 10;
			background-color: var(--color-main-background);
		}
		th:last-child,
		td:last-child {
			right: 0;
			width: 8em;
			min-width: 8em;
			max-width: 8em;
		}
		th:nth-last-child(2),
		td:nth-last-child(2) {
			right: 8em;
			box-shadow: -2px 0 4px -2px rgba(0, 0, 0, 0.15);
		}
	}

	th.selected,
	td.selected {
		background-color: var(--color-primary-light);
		font-weight: bold;
	}
	td:first-child {
		padding: 6px 5px;
	}
}

.categoryMonthlyChart {
	height: 400px;
}
</style>
