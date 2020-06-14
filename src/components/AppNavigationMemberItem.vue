<template>
    <AppNavigationItem
        class="memberItem"
        :title="member.name"
        :editable="true"
        :forceMenu="true"
        v-show="memberVisible"
        :editLabel="t('cospend', 'Rename')"
        >
        <div class="memberAvatar" slot="icon">
            <div class="disabledMask" v-show="!member.activated"></div>
            <img :src="memberAvatar"/>
        </div>
        <!--AppNavigationIconBullet slot="icon" color="0082c9" /-->
        <template slot="counter">
            <span :class="balanceClass">{{ member.balance.toFixed(2) }}</span>
        </template>
        <template slot="actions">
            <ActionInput :disabled="false" icon="icon-quota">
                {{ t('cospend', 'Weight') }}
            </ActionInput>
            <ActionButton icon="icon-palette" @click="onChangeColorClick">
                {{ t('cospend', 'Change color') }}
            </ActionButton>
            <ActionButton :icon="member.activated ? 'icon-delete' : 'icon-history'" @click="onDeleteMemberClick">
                {{ member.activated ? t('cospend', 'Deactivate') : t('cospend', 'Reactivate') }}
            </ActionButton>
        </template>
    </AppNavigationItem>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import {
    ActionButton, AppNavigation as AppNavigationVue, AppNavigationIconBullet,
    AppNavigationSettings, AppNavigationItem, ActionInput
} from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import cospend from '../state';
import {getMemberName, getSmartMemberName, getMemberAvatar} from '../member';

export default {
    name: 'AppNavigationMemberItem',
    components: {
        AppNavigationVue,
        AppNavigationItem,
        AppNavigationSettings,
        AppNavigationIconBullet,
        ActionButton,
        ActionInput
    },
    directives: {
        ClickOutside,
    },
    props: ['member', 'projectId'],
    data() {
        return {
        }
    },
    computed: {
        balance: function() {
            return this.member.balance;
        },
        memberAvatar: function() {
            return getMemberAvatar(this.projectId, this.member.id);
        },
        smartMemberName: function() {
            return getSmartMemberName(this.projectId, this.member.id);
        },
        balanceClass: function() {
            let balanceClass = '';
            if (this.member.balance >= 0.01) {
                balanceClass = ' balancePositive';
            } else if (this.member.balance <= -0.01) {
                balanceClass = ' balanceNegative';
            }
            return 'balance ' + balanceClass;
        },
        memberVisible: function() {
            const balance = this.member.balance;
            return (balance >= 0.01 || balance <= -0.01 || this.member.activated);
        },
    },

    methods: {
        onChangeColorClick: function() {
            console.log(this.member.balance)
            console.log(this.balance)

        },
        onDeleteMemberClick: function() {

        }
    },

}
</script>

<style scoped lang="scss">

.memberItem {
    padding-left: 30px !important;
}
</style>