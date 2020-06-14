<template>
    <div id="billdetail" class="app-content-details">
        <h2 id="mbTitle">
            <span class="icon-phone"></span>
            {{ t('cospend', 'MoneyBuster link/QRCode for project {name}', {name: project.name}) }}
        </h2>
        <div id="qrcode-div-nopass">
            <QRCode
                :link="noPassLink"
                :color="color"
            />
        </div>
        <label id="mbUrlLabel">{{ noPassLink }}</label>
        <br/>
        <label id="mbUrlHintLabel1">{{ t('cospend', 'Scan this QRCode with an Android phone with MoneyBuster installed and open the link or simply send the link to another Android phone.') }}</label>
        <label id="mbUrlHintLabel2">{{ t('cospend', 'Android will know MoneyBuster can open such a link (based on the \'https://net.eneiluj.moneybuster.cospend\' part) and you will be able to add the project.') }}</label>
        <br/><hr/><br/>
        <label id="mbPasswordLabel1">{{ t('cospend', 'As password is stored hashed (for security), it can\'t be automatically included in the QRCode. If you want to include it in the QRCode and make it easier to add a project in MoneyBuster, you can provide the password again.') }}</label>
        <label id="mbPasswordLabel2">{{ t('cospend', 'Type the project password and press Enter to generate another QRCode including the password.') }}</label>
        <input id="mbPasswordInput" ref="passInput" type="text" v-on:keyup.enter="onPasswordPressEnter"
            value="" :placeholder="t('cospend', 'Project password')"/>
        <div id="qrcode-div-pass">
            <QRCode
                v-if="validPassword"
                :link="passLink"
                :color="color"
            />
        </div>
        <label id="mbPassUrlLabel">{{ passLink }}</label>
    </div>
</template>

<script>
import QRCode from './components/QRCode';
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import cospend from './state';

export default {
    name: 'MoneyBusterLink',

    components: {
        QRCode
    },

    props: ['project'],
    data: function() {
        return {
            //project: cospend.projects[cospend.currentProjectId],
            validPassword: null,
            color: cospend.themeColorDark
        };
    },

    computed: {
        noPassLink: function() {
            return 'https://net.eneiluj.moneybuster.cospend/' + window.location.host +
                generateUrl('').replace('/index.php', '') + this.project.id + '/';
        },
        passLink: function() {
            let url = null;
            if (this.validPassword) {
                url = 'https://net.eneiluj.moneybuster.cospend/' + window.location.host +
                    generateUrl('').replace('/index.php', '') + this.project.id + '/' + encodeURIComponent(this.validPassword);
            }
            return url;
        }
    },

    methods: {
        onPasswordPressEnter: function() {
            const that = this;
            const password = this.$refs.passInput.value;
            const url = generateUrl('/apps/cospend/checkpassword/' + this.project.id + '/' + password);
            $.ajax({
                type: 'GET',
                url: url,
                data: null,
                async: true,
            }).done(function(response) {
                if (response) {
                    that.validPassword = password;
                } else {
                    Notification.showTemporary(t('cospend', 'Incorrect project password'));
                }
            });
        }
    }
}
</script>

<style scoped lang="scss">
#mbTitle {
    padding: 20px 0px 20px 0px;
}
#qrcode-div-pass,
#qrcode-div-nopass {
    width: 210px;
    margin: 0 auto;
}
#mbPasswordInput {
    display: block;
    margin-left: auto;
    margin-right: auto;
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

</style>