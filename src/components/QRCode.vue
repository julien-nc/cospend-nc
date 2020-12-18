<template>
	<div ref="qrcodediv" />
</template>

<script>
import kjua from 'kjua-svg'

export default {
	name: 'QRCode',

	components: {
	},
	props: {
		link: {
			type: String,
			required: true,
		},
		render: {
			type: String,
			default: 'image',
			validator(value) {
				return ['image', 'canvas', 'svg'].indexOf(value) !== -1
			},
		},
		fgcolor: {
			type: String,
			default: 'black',
		},
		bgcolor: {
			type: String,
			default: 'white',
		},
		rounded: {
			type: Number,
			default: 0,
			validator(value) {
				return (value >= 0 && value <= 100)
			},
		},
		size: {
			type: Number,
			default: 200,
		},
		imageUrl: {
			type: String,
			default: null,
		},
		label: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
		}
	},

	watch: {
		link(val) {
			this.genQRCode()
		},
	},

	mounted() {
		this.genQRCode()
	},

	methods: {
		genQRCode() {
			if (this.imageUrl) {
				this.genQRCodeWithImage()
			} else {
				this.genPlainQRCode()
			}
		},
		genPlainQRCode() {
			const qr = kjua({
				text: this.link,
				crisp: false,
				render: this.render,
				minVersion: 6,
				ecLevel: 'H',
				size: this.size,
				back: this.bgcolor,
				fill: this.fgcolor,
				rounded: this.rounded,
				quiet: 1,
				mode: this.label ? 'label' : 'plain',
				mSize: 20,
				mPosX: 50,
				mPosY: 50,
				label: this.label,
			})
			this.$refs.qrcodediv.innerHTML = ''
			this.$refs.qrcodediv.appendChild(qr)
		},
		genQRCodeWithImage() {
			const img = new Image()
			// wait for the image to be loaded to generate the QRcode
			img.onload = () => {
				const qr = kjua({
					text: this.link,
					crisp: false,
					render: this.render,
					minVersion: 6,
					ecLevel: 'H',
					size: this.size,
					back: this.bgcolor,
					fill: this.fgcolor,
					rounded: this.rounded,
					quiet: 1,
					mode: 'image',
					mSize: 20,
					mPosX: 50,
					mPosY: 50,
					image: img,
					label: '',
				})
				this.$refs.qrcodediv.innerHTML = ''
				this.$refs.qrcodediv.appendChild(qr)
			}
			img.onerror = () => {
				const qr = kjua({
					text: this.link,
					crisp: false,
					render: this.render,
					minVersion: 6,
					ecLevel: 'H',
					size: this.size,
					back: this.bgcolor,
					fill: this.fgcolor,
					rounded: this.rounded,
					quiet: 1,
					mode: 'label',
					mSize: 10,
					mPosX: 50,
					mPosY: 50,
					image: null,
					label: 'Cospend',
					fontcolor: this.fgcolor,
				})
				this.$refs.qrcodediv.innerHTML = ''
				this.$refs.qrcodediv.appendChild(qr)
			}

			img.src = this.imageUrl
		},
	},
}
</script>
