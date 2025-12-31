<template>
	<a
		:href="href"
		target="_blank"
		rel="noopener noreferrer">
		{{ name }}
	</a>
</template>

<script>

export default {
	name: 'ActivityHighlight',

	components: {
	},

	props: {
		href: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
a {
	font-weight: bold;
	&:hover {
		text-decoration: underline;
	}
}
</style>
