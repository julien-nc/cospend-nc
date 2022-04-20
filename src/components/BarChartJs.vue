<script>
import { Bar, mixins } from 'vue-chartjs'

export default {
	name: 'BarChartJs',
	extends: Bar,
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
