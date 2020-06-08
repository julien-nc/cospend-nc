<script>
import { PolarArea, mixins } from 'vue-chartjs'

export default {
    name: 'PolarChartJs',
    extends: PolarArea,
    mixins: [mixins.reactiveProp],
    props: ['chartData', 'options'],
    mounted () {
        this.renderChart(this.chartData, this.options)
    }
}
</script>