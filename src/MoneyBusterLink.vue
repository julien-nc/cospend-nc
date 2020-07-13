<template>
    <div id="mbLink">
        <div id="mbTitle">
            <span class="icon-phone"></span>
            {{ t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: project.name}) }}
        </div>
        <button class="icon-info infoButton" @click="onInfo1Clicked"></button>
        <div id="qrcode-div-nopass">
            <QRCode
                :link="noPassLink"
                :fgcolor="qrcodeColor"
                :imageUrl="qrcodeImageUrl"
                render="canvas"
                :rounded="100"
            />
        </div>
        <label id="mbUrlLabel">{{ noPassLink }}</label>
        <br/>
        <div v-if="!pageIsPublic">
            <br/><hr/><br/>
            <label id="mbPasswordLabel">{{ t('cospend', 'Confirm project password to get a QRCode including the password.') }}</label>
            <button class="icon-info infoButton" @click="onInfo2Clicked"></button>
            <div class="enterPassword">
                <form autocomplete="off" @submit.prevent.stop="onPasswordPressEnter">
                    <input
                        v-model="password"
                        :placeholder="t('cospend', 'Project password')"
                        autocomplete="off"
                        @focus="$event.target.select()"
                        type="password"/>
                    <input type="submit" value="" class="icon-confirm"/>
                </form>
            </div>
            <div id="qrcode-div-pass">
                <QRCode
                    v-if="validPassword"
                    :link="passLink"
                    :fgcolor="qrcodeColor"
                    :imageUrl="qrcodeImageUrl"
                    render="canvas"
                    :rounded="100"
                />
            </div>
            <label id="mbPassUrlLabel">{{ passLink }}</label>
        </div>
    </div>
</template>

<script>
import QRCode from './components/QRCode';
import {generateUrl, imagePath} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import cospend from './state';
import * as network from './network';

export default {
    name: 'MoneyBusterLink',

    components: {
        QRCode
    },

    props: ['project'],
    data() {
        return {
            password: '',
            validPassword: null,
            qrcodeColor: cospend.themeColorDark,
            qrcodeImageUrl: imagePath('cospend', 'cospend.png')
        };
    },

    computed: {
        pageIsPublic() {
            return cospend.pageIsPublic;
        },
        noPassLink() {
            return 'https://net.eneiluj.moneybuster.cospend/' + window.location.host +
                generateUrl('').replace('/index.php', '') + this.project.id + '/';
        },
        passLink() {
            let url = null;
            if (this.validPassword) {
                url = 'https://net.eneiluj.moneybuster.cospend/' + window.location.host +
                    generateUrl('').replace('/index.php', '') + this.project.id + '/' + encodeURIComponent(this.validPassword);
            }
            return url;
        }
    },

    methods: {
        onPasswordPressEnter() {
            network.checkPassword(this.project.id, this.password, this.checkPasswordSuccess);
        },
        checkPasswordSuccess(response) {
            if (response) {
                this.validPassword = this.password;
            } else {
                showError(t('cospend', 'Incorrect project password.'));
            }
        },
        onInfo1Clicked() {
            OC.dialogs.alert(
                t('cospend', 'Scan this QRCode with an Android phone with MoneyBuster installed and open the link or simply send the link to another Android phone.') + ' ' +
                    t('cospend', 'Android will know MoneyBuster can open such a link (based on the \'https://net.eneiluj.moneybuster.cospend\' part) and you will be able to add the project.'),
                t('cospend', 'Info')
            );
        },
        onInfo2Clicked() {
            OC.dialogs.alert(
                t('cospend', 'As password is stored hashed (for security), it can\'t be automatically included in the QRCode. If you want to include it in the QRCode and make it easier to add a project in MoneyBuster, you can provide the password again.') + ' ' +
                    t('cospend', 'Type the project password and press Enter to generate another QRCode including the password.'),
                t('cospend', 'Info')
            );
        },
    }
}
</script>

<style scoped lang="scss">
#mbTitle span {
    display: inline-block;
    width: 40px;
}
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
.infoButton {
    width: 30px;
    height: 30px;
}
#mbPasswordLabel {
    text-align: center;
}
#mbPasswordLabel,
.infoButton {
    margin-left: auto;
    margin-right: auto;
    display: block;
}
</style>