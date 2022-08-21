<script>
import { Line } from 'vue-chartjs/legacy/index.js'
import { Chart as ChartJS, Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler } from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, PointElement, CategoryScale, LinearScale, LineElement, Filler)

export default {
	name: 'LineChartJs',
	extends: Line,
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
