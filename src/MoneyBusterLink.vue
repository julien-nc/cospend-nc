<template>
	<div id="mbLink">
		<h3>
			<span class="icon-phone" />
			<span class="tcontent">{{ t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: project.name}) }}</span>
			<button class="icon icon-info"
				@click="onInfo1Clicked" />
		</h3>
		<div id="qrcode-div-nopass">
			<QRCode render="canvas"
				:link="noPassLink"
				:fgcolor="qrcodeColor"
				:imageUrl="qrcodeImageUrl"
				:rounded="100" />
		</div>
		<label id="mbUrlLabel">{{ noPassLink }}</label>
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
			<div id="qrcode-div-pass">
				<QRCode
					v-if="validPassword"
					render="canvas"
					:link="passLink"
					:fgcolor="qrcodeColor"
					:imageUrl="qrcodeImageUrl"
					:rounded="100" />
			</div>
			<label id="mbPassUrlLabel">{{ passLink }}</label>
		</div>
	</div>
</template>

<script>
import QRCode from './components/QRCode'
import { generateUrl, imagePath } from '@nextcloud/router'
import {
	showError,
} from '@nextcloud/dialogs'
import cospend from './state'
import * as network from './network'

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
			qrcodeImageUrl: imagePath('cospend', 'cospend.png'),
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
		passLink() {
			let url = null
			if (this.validPassword) {
				url = 'https://net.eneiluj.moneybuster.cospend/' + window.location.host
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
				t('cospend', 'Scan this QRCode with an Android phone with MoneyBuster installed and open the link or simply send the link to another Android phone.')
				+ ' '
				+ t('cospend', 'Android will know MoneyBuster can open such a link (based on the \'https://net.eneiluj.moneybuster.cospend\' part) and you will be able to add the project.'),
				t('cospend', 'Info')
			)
		},
		onInfo2Clicked() {
			OC.dialogs.info(
				t('cospend', 'As password is stored hashed (for security), it can\'t be automatically included in the QRCode. If you want to include it in the QRCode and make it easier to add a project in MoneyBuster, you can provide the password again.')
				+ ' '
				+ t('cospend', 'Type the project password and press Enter to generate another QRCode including the password.'),
				t('cospend', 'Info')
			)
		},
	},
}
</script>

<style scoped lang="scss">
#qrcode-div-pass,
#qrcode-div-nopass {
	width: 210px;
	margin: 0 auto;
}
.enterPassword {
	order: 1;
	display: flex;
	margin-left: auto;
	margin-right: auto;
	height: 44px;
	width: 250px;
	form {
		display: flex;
		flex-grow: 1;
		input[type="password"] {
			flex-grow: 1;
		}
	}
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
	display: flex;
	margin-bottom: 20px;

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
