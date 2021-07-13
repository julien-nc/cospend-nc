<template>
	<div>
		<v-table v-if="tableData"
			class="monthlyTable coloredTable"
			:data="tableData">
			<thead slot="head">
				<v-th sort-key="name">
					{{ firstColumnTitle }}
				</v-th>
				<v-th v-for="month in distinctMonths"
					:key="month"
					:sort-key="month">
					{{ month }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="vals in displayData"
					:key="vals.id"
					v-tooltip.left="{ content: vals.name }">
					<td :style="'border: 2px solid ' + vals.color + ';'">
						{{ vals.name }}
					</td>
					<td v-for="month in distinctMonths"
						:key="month"
						:class="{ selected: selectedMonthlyCol === distinctMonths.indexOf(month) }"
						:style="'border: 2px solid ' + vals.color + ';'">
						{{ (vals[month] || 0).toFixed(2) }}
					</td>
				</tr>
			</tbody>
		</v-table>
		<div v-else-if="loadingStats" class="loading loading-stats-animation" />
		<div id="categoryMonthlyChart"
			@mouseleave="selectedMonthlyCol = null">
			<LineChartJs
				:chart-data="chartData"
				:options="chartOptions" />
		</div>
	</div>
</template>

<script>
import cospend from '../../state'
import LineChartJs from '../LineChartJs'

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
	},

	data() {
		return {
			cospend,
			loadingStats: false,
			selectedMonthlyCol: null,
		}
	},

	computed: {
		chartOptions() {
			return {
				...this.baseLineChartOptions,
				title: {
					display: true,
					text: this.chartTitle,
				},
				onHover: this.onMonthlyChartHover,
			}
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onMonthlyChartHover(event, data) {
			if (data.length > 0 && data[0]._index !== undefined) {
				this.selectedMonthlyCol = data[0]._index
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

	td.selected {
		background-color: var(--color-background-dark);
	}
	td:first-child {
		padding: 0px 5px 0px 5px;
	}
}

.loading-stats-animation {
	height: 70px;
}
</style>
