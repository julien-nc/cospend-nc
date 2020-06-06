<template>
    <div ref="qrcodediv">
    </div>
</template>

<script>
import kjua from 'kjua';

export default {
	name: 'QRCode',

    props: ['link', 'color'],
	components: {
    },

    mounted() {
        this.genQRCode();
    },

	data: function() {
		return {
		};
	},

    watch: {
        link: function(val) {
            this.genQRCode();
        }
    },

	methods: {
		genQRCode: function() {
            const that = this;
            const img = new Image();
            // wait for the image to be loaded to generate the QRcode
            img.onload = function() {
                const qr = kjua({
                    text: that.link,
                    crisp: false,
                    render: 'canvas',
                    minVersion: 6,
                    ecLevel: 'H',
                    size: 210,
                    back: '#ffffff',
                    fill: that.color,
                    rounded: 100,
                    quiet: 1,
                    mode: 'image',
                    mSize: 20,
                    mPosX: 50,
                    mPosY: 50,
                    image: img,
                    label: 'no label',
                });
                that.$refs.qrcodediv.innerHTML = '';
                that.$refs.qrcodediv.appendChild(qr);
            };
            img.onerror = function() {
                const qr = kjua({
                    text: that.link,
                    crisp: false,
                    render: 'canvas',
                    minVersion: 6,
                    ecLevel: 'H',
                    size: 210,
                    back: '#ffffff',
                    fill: that.color,
                    rounded: 100,
                    quiet: 1,
                    mode: 'label',
                    mSize: 10,
                    mPosX: 50,
                    mPosY: 50,
                    image: img,
                    label: 'Cospend',
                    fontcolor: '#000000',
                });
                that.$refs.qrcodediv.innerHTML = '';
                that.$refs.qrcodediv.appendChild(qr);
            };

            // dirty trick to get image URL from css url()... Anyone knows better ?
            img.src = $('#dummylogo').css('content').replace('url("', '').replace('")', '');
        }
    }
}
</script>