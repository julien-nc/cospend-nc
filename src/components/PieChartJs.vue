<template>
	<Pie
		:options="chartOptions"
		:data="chartData" />
</template>
<script>
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, CategoryScale, LinearScale, ArcElement } from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, CategoryScale, LinearScale, ArcElement)

export default {
	name: 'PieChartJs',
	components: {
		Pie,
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
