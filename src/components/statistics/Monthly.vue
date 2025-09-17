<template>
	<div>
		<!-- mouseout does not work -->
		<div class="tableWrapper"
			@mouseleave="selectedDataset = null ; hoveredTableMonth = null">
			<v-table
				class="monthlyTable coloredTable"
				:data="tableData">
				<template #head>
					<v-th sort-key="name">
						{{ firstColumnTitle }}
					</v-th>
					<v-th v-for="month in distinctMonths"
						:key="month"
						:sort-key="month"
						:class="{ selected: selectedMonthlyCol === distinctMonths.indexOf(month) }">
						{{ month }}
					</v-th>
				</template>
				<template #body="{ rows }">
					<tr v-for="row in rows"
						:key="row.id"
						@mouseenter="selectedDataset = row.id">
						<td :style="'border: 2px solid ' + row.color + ';'">
							{{ row.name }}
						</td>
						<td v-for="month in distinctMonths"
							:key="month"
							:class="{ selected: selectedMonthlyCol === distinctMonths.indexOf(month) }"
							:style="'border: 2px solid ' + row.color + ';'"
							@mouseenter="hoveredTableMonth = month">
							{{ (row[month] || 0).toFixed(2) }}
							{{ currencyName }}
						</td>
					</tr>
				</template>
			</v-table>
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
import LineChartJs from '../LineChartJs.vue'

export default {
	name: 'Monthly',

	components: {
		LineChartJs,
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
	}
	th:first-child,
	td:first-child {
		position: sticky;
		left: 0;
		z-index: 10;
		background-color: var(--color-main-background);
	}
	th:last-child,
	td:last-child {
		position: sticky;
		right: 0;
		z-index: 10;
		background-color: var(--color-main-background);
	}

	th.selected,
	td.selected {
		background-color: var(--color-primary-light);
		font-weight: bold;
	}
	td:first-child {
		padding: 0px 5px 0px 5px;
	}
}

.categoryMonthlyChart {
	height: 400px;
}
</style>
