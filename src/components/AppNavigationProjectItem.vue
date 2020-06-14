<template>
    <AppNavigationItem v-if="deleting" title="Are you sure you want to delete?" :undo="true"
        @undo="cancelDeletion"
     />
    <AppNavigationItem v-else
        :title="project.name"
        icon="icon-folder"
        :allow-collapse="true"
        :open="selected"
        :class="{'selectedproject': selected}"
        @click="onProjectClick"
        :forceMenu="false"
        >
        <template slot="actions">
            <ActionInput :disabled="false" icon="icon-user" ref="newMemberInput" @submit="onAddMember">
                {{ t('cospend', 'Add member') }}
            </ActionInput>
            <ActionButton icon="icon-category-monitoring" @click="onStatsClick">
                {{ t('cospend', 'Statistics') }}
            </ActionButton>
            <ActionButton icon="icon-phone" @click="onQrcodeClick">
                {{ t('cospend', 'Link/QRCode for MoneyBuster') }}
            </ActionButton>
            <ActionButton icon="icon-delete" @click="onDeleteProjectClick">
                {{ t('cospend', 'Delete') }}
            </ActionButton>
        </template>
        <template>
            <AppNavigationMemberItem
                v-for="member in members"
                :key="member.id"
                :member="member"
                :projectId="project.id"
                @memberEdited="onMemberEdited"
                />
        </template>
    </AppNavigationItem>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import AppNavigationMemberItem from './AppNavigationMemberItem';
import {
    ActionButton, AppNavigation as AppNavigationVue, AppNavigationIconBullet,
    AppNavigationSettings, AppNavigationItem, ActionInput
} from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import cospend from '../state';
import {Timer} from "../utils";
import {getMemberName, getSmartMemberName, getMemberAvatar} from '../member';

export default {
    name: 'AppNavigation',
    components: {
        AppNavigationMemberItem,
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
    props: ['project', 'members', 'selected'],
    data() {
        return {
            deleting: false,
            deletionTimer: null
        }
    },
    computed: {
    },
    beforeMount() {
    },
    methods: {
        onProjectClick: function() {
            this.$emit('projectClicked', this.project.id);
        },
        onDeleteProjectClick: function() {
            this.deleting = true;
            const that = this;
            this.deletionTimer = new Timer(function () {
                //that.deleting = false;
                that.$emit('deleteProject', that.project.id);
            }, 7000);
        },
        cancelDeletion: function() {
            this.deleting = false;
            this.deletionTimer.pause();
            delete this.deletionTimer;
        },
        onQrcodeClick: function() {
            this.$emit('qrcodeClicked', this.project.id);
        },
        onStatsClick: function() {
            this.$emit('statsClicked', this.project.id);
        },
        onAddMember: function() {
            const newName = this.$refs.newMemberInput.$el.querySelector('input[type="text"]').value;
            this.$emit('newMember', this.project.id, newName);
        },
        onMemberEdited: function(projectid, memberid) {
            this.$emit('memberEdited', projectid, memberid);
        },
    },
}
</script>

<style scoped lang="scss">
</style>