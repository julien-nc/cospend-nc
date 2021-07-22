<template>
	<div id="mbLink">
		<h3>
			<span class="icon-phone" />
			<span class="tcontent">{{ t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: project.name}, undefined, { escape: false }) }}</span>
			<button class="icon icon-info"
				@click="onInfo1Clicked" />
		</h3>
		<h3 class="qrcode-title">
			{{ t('cospend', 'Old link') }}, MoneyBuster &lt;= 0.1.7
		</h3>
		<div class="qrcode-div-nopass">
			<QRCode render="svg"
				:link="noPassLink"
				:fgcolor="qrcodeColor"
				:image-url="qrcodeImageUrl"
				:rounded="100" />
		</div>
		<label id="mbUrlLabel">{{ noPassLink }}</label>
		<br>
		<h3 class="qrcode-title">
			{{ t('cospend', 'New link') }}, MoneyBuster &gt;= 0.1.8, PayForMe &gt;= 0.0.2
		</h3>
		<div class="qrcode-div-nopass">
			<QRCode render="svg"
				:link="noPassProtocolLink"
				:fgcolor="qrcodeColor"
				:image-url="qrcodeImageUrl"
				:rounded="100" />
		</div>
		<label id="mbUrlLabel">{{ noPassProtocolLink }}</label>
		<br>
		<div v-if="!pageIsPublic">
			<br><hr><br>
			<h3>
				<span class="icon-phone" />
				<span class="tcontent">
					{{ t('cospend', 'Confirm project password to get a QRCode including the password.') }}
				</span>
				<button class="icon icon-info"
					@click="onInfo2Clicked" />
			</h3>
			<div class="enterPassword">
				<form autocomplete="off" @submit.prevent.stop="onPasswordPressEnter">
					<input v-model="password"
						type="password"
						autocomplete="off"
						:placeholder="t('cospend', 'Project password')"
						:readonly="readonly"
						@focus="readonly = false; $event.target.select()">
					<input type="submit" value="" class="icon-confirm">
				</form>
			</div>
			<h3 v-if="validPassword" class="qrcode-title">
				{{ t('cospend', 'Old link') }}, MoneyBuster &lt;= 0.1.7
			</h3>
			<div class="qrcode-div-pass">
				<QRCode
					v-if="validPassword"
					render="svg"
					:link="passLink"
					:fgcolor="qrcodeColor"
					:image-url="qrcodeImageUrl"
					:rounded="100" />
			</div>
			<label id="mbPassUrlLabel">{{ passLink }}</label>
			<h3 v-if="validPassword" class="qrcode-title">
				{{ t('cospend', 'New link') }}, MoneyBuster &gt;= 0.1.8, PayForMe &gt;= 0.0.2
			</h3>
			<div class="qrcode-div-pass">
				<QRCode
					v-if="validPassword"
					render="svg"
					:link="passProtocolLink"
					:fgcolor="qrcodeColor"
					:image-url="qrcodeImageUrl"
					:rounded="100" />
			</div>
			<label id="mbPassUrlLabel">{{ passProtocolLink }}</label>
		</div>
	</div>
</template>

<script>
import QRCode from './components/QRCode'
import { generateUrl } from '@nextcloud/router'
import {
	showError,
} from '@nextcloud/dialogs'
import cospend from './state'
import * as network from './network'
import { hexToDarkerHex, getComplementaryColor } from './utils'

export default {
	name: 'MoneyBusterLink',

	components: {
		QRCode,
	},

	props: {
		project: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			password: '',
			validPassword: null,
			qrcodeColor: cospend.themeColorDark,
			qrcodeImageUrl: generateUrl('/svg/cospend/cospend_square_bg?color=' + hexToDarkerHex(getComplementaryColor(cospend.themeColorDark)).replace('#', '')),
			readonly: true,
		}
	},

	computed: {
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		noPassLink() {
			return 'https://net.eneiluj.moneybuster.cospend/' + window.location.host
				+ generateUrl('').replace('/index.php', '') + this.project.id + '/'
		},
		noPassProtocolLink() {
			return 'cospend://' + window.location.host
				+ generateUrl('').replace('/index.php', '') + this.project.id + '/'
		},
		passLink() {
			let url = null
			if (this.validPassword) {
				url = 'https://net.eneiluj.moneybuster.cospend/' + window.location.host
					+ generateUrl('').replace('/index.php', '') + this.project.id + '/' + encodeURIComponent(this.validPassword)
			}
			return url
		},
		passProtocolLink() {
			let url = null
			if (this.validPassword) {
				url = 'cospend://' + window.location.host
					+ generateUrl('').replace('/index.php', '') + this.project.id + '/' + encodeURIComponent(this.validPassword)
			}
			return url
		},
	},

	methods: {
		onPasswordPressEnter() {
			network.checkPassword(this.project.id, this.password, this.checkPasswordSuccess)
		},
		checkPasswordSuccess(response) {
			if (response) {
				this.validPassword = this.password
			} else {
				showError(t('cospend', 'Incorrect project password.'))
			}
		},
		onInfo1Clicked() {
			OC.dialogs.info(
				t('cospend', 'Scan this QRCode with a smartphone which has MoneyBuster or PayForMe installed or simply send the link itself.'),
				t('cospend', 'Info')
			)
		},
		onInfo2Clicked() {
			OC.dialogs.info(
				t('cospend', 'As the password is stored hashed (for security), it can\'t be automatically included in the QRCode. If you want to include it in the QRCode and make it easier to add a project in mobile apps, you must provide the password again.')
				+ ' '
				+ t('cospend', 'Type the project password and press "Enter" to generate another QRCode that includes the password.'),
				t('cospend', 'Info')
			)
		},
	},
}
</script>

<style scoped lang="scss">
.qrcode-div-pass,
.qrcode-div-nopass {
	width: 210px;
	margin: 0 auto;
}

#mbPasswordLabel1,
#mbPasswordLabel2,
#mbUrlHintLabel1,
#mbUrlHintLabel2,
#mbUrlPasswordLabel,
#mbPassUrlLabel,
#mbUrlLabel {
	display: block;
	text-align: center;
}

h3 {
	&:not(.qrcode-title) {
		display: flex;
		margin-bottom: 20px;
	}

	&.qrcode-title {
		text-align: center;
	}

	> .tcontent {
		flex-grow: 1;
		padding-top: 12px;
	}

	> span.icon-phone {
		display: inline-block;
		min-width: 40px;
	}

	.icon {
		display: inline-block;
		width: 44px;
		height: 44px;
		border-radius: var(--border-radius-pill);
		opacity: .5;

		&.icon-info {
			background-color: transparent;
			border: none;
			margin: 0;
		}

		&:hover,
		&:focus {
			opacity: 1;
			background-color: var(--color-background-hover);
		}
	}
}
</style>
