<template>
    <AppNavigationItem
        class="memberItem"
        :title="nameTitle"
        :editable="true"
        :forceMenu="true"
        v-show="memberVisible"
        :editLabel="t('cospend', 'Rename')"
        ref="nameInput"
        @update:title="onMemberRename"
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
            <ActionInput :disabled="false" icon="icon-quota" type="number" step="0.1" :value="''"
                ref="weightInput" @submit="onWeightSubmit"
                >
                {{ t('cospend', 'Weight') }} ({{ member.weight }})
            </ActionInput>
            <ActionInput :disabled="false" icon="icon-palette" type="color"
                :value="color" ref="colorInput" @submit="onColorSubmit"
                >
            </ActionInput>
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
        nameTitle: function() {
            return this.member.name + ((this.member.weight !== 1.0) ? (' (x' + this.member.weight + ')') : '');
        },
        balance: function() {
            return this.member.balance;
        },
        color: function() {
            return '#' + this.member.color;
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
        onColorSubmit: function() {
            const newColor = this.$refs.colorInput.$el.querySelector('input[type="color"]').value;
            this.member.color = newColor.replace('#', '');
            this.$emit('memberEdited', this.projectId, this.member.id);
        },
        onDeleteMemberClick: function() {
            this.member.activated = !this.member.activated;
            this.$emit('memberEdited', this.projectId, this.member.id);
        },
        onMemberRename: function() {
            const newName = this.$refs.nameInput.$el.querySelector('input[type="text"]').value;
            this.member.name = newName;
            this.$emit('memberEdited', this.projectId, this.member.id);
        },
        onWeightSubmit: function() {
            const newWeight = this.$refs.weightInput.$el.querySelector('input[type="number"]').value;
            this.member.weight = parseFloat(newWeight);
            this.$emit('memberEdited', this.projectId, this.member.id);
        }
    },

}
</script>

<style scoped lang="scss">

.memberItem {
    padding-left: 30px !important;
}
</style>