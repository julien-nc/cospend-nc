<template>
    <div id="app-navigation" :class="{'icon-loading': loading}">
        <AppNavigationVue>
            <ul>
                <AppNavigationItem
                    :title="t('cospend', 'New project')"
                    icon="icon-add"
                    />
                <AppNavigationItem
                    :title="t('cospend', 'New bill')"
                    @click="onNewBillClick"
                    icon="icon-edit"
                    />
                <AppNavigationProjectItem
                    v-for="(project, id) in projects"
                    :key="id"
                    :project="project"
                    :members="project.members"
                    :selected="id === selectedProjectId"
                    @projectClicked="onProjectClicked"
                    @deleteProjectClicked="onDeleteProjectClicked"
                    @qrcodeClicked="onQrcodeClicked"
                    @statsClicked="onStatsClicked"
                    @newMember="onNewMember"
                    @memberEdited="onMemberEdited"
                    />
            </ul>
            <AppNavigationSettings>
                <div>
                    SETTINGS !!!<br/>PLOP
                </div>
            </AppNavigationSettings>
        </AppNavigationVue>

        <div
            id="app-settings"
            :class="{open: opened}">
            <div id="app-settings-header">
                <button class="settings-button" @click="toggleMenu">
                    {{ t('cospend', 'Settings') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import AppNavigationProjectItem from './AppNavigationProjectItem';
import {
    ActionButton, AppNavigation as AppNavigationVue, AppNavigationIconBullet,
    AppNavigationSettings, AppNavigationItem, ActionInput
} from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import cospend from '../state';
import {getMemberName, getSmartMemberName, getMemberAvatar} from '../member';

export default {
    name: 'AppNavigation',
    components: {
        AppNavigationProjectItem,
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
    props: ['projects', 'selectedProjectId'],
    data() {
        return {
            opened: false,
            loading: false,
        }
    },
    computed: {
    },
    beforeMount() {
    },
    methods: {
        toggleMenu() {
            this.opened = !this.opened
        },
        closeMenu() {
            this.opened = false
        },
        onNewBillClick: function() {
            this.$emit('newBillClicked');
        },
        onProjectClicked: function(projectid) {
            this.$emit('projectClicked', projectid);
        },
        onDeleteProjectClicked: function(projectid) {
            this.$emit('deleteProjectClicked', projectid);
        },
        onQrcodeClicked: function(projectid) {
            this.$emit('qrcodeClicked', projectid);
        },
        onStatsClicked: function(projectid) {
            this.$emit('statsClicked', projectid);
        },
        onNewMember: function(projectid, name) {
            this.$emit('newMember', projectid, name);
        },
        onMemberEdited: function(projectid, memberid) {
            this.$emit('memberEdited', projectid, memberid);
        },
    },
}
</script>
<style scoped lang="scss">
#app-settings-content {
    p {
        margin-top: 20px;
        margin-bottom: 20px;
        color: var(--color-text-light);
    }
}
</style>