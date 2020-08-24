<script>
import { Pie, mixins } from 'vue-chartjs'

export default {
	name: 'PieChartJs',
	extends: Pie,
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
