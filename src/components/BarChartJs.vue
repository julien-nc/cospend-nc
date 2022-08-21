<script>
import { Bar } from 'vue-chartjs/legacy/index.js'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

export default {
	name: 'BarChartJs',
	extends: Bar,
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
