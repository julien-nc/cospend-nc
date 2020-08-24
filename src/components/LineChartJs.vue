<script>
import { Line, mixins } from 'vue-chartjs'

export default {
	name: 'LineChartJs',
	extends: Line,
	mixins: [mixins.reactiveProp],
	props: {
		chartData: {
			type: Object,
			required: true,
		},
		options: {
			type: Object,
			required: true,
		},
	},
	mounted() {
		this.renderChart(this.chartData, this.options)
	},
}
</script>
