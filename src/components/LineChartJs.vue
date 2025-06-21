<template>
	<LineChart
		:options="chartOptions"
		:data="chartData" />
</template>
<script>
import { Line as LineChart } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler } from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler)

export default {
	name: 'LineChartJs',
	components: {
		LineChart,
	},
	props: {
		chartData: {
			type: Object,
			required: true,
		},
		chartOptions: {
			type: Object,
			required: true,
		},
	},
}
</script>
