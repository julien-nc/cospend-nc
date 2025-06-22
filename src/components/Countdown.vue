<template>
	<div>
		{{ timer }}
	</div>
</template>

<script>
export default {
	name: 'Countdown',

	components: {
	},

	props: {
		duration: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			timer: this.duration,
		}
	},

	computed: {
	},

	mounted() {
		if (this.timer > 0) {
			this.tick()
		}
	},

	methods: {
		tick() {
			setTimeout(() => {
				this.timer--
				if (this.timer > 0) {
					this.tick()
				}
			}, 1000)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
