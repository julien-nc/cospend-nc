<template>
    <div id="app-navigation" :class="{'icon-loading': loading}">
        <AppNavigationVue>
            <ul>
                <AppNavigationItem v-if="!creating"
                    :title="t('deck', 'New project')"
                    icon="icon-add"
                    @click.prevent.stop="startCreateProject" />
                <div v-else class="project-create">
                    <form @submit.prevent.stop="createProject">
                        <input :placeholder="t('cospend', 'New project name')" type="text" required>
                        <input type="submit" value="" class="icon-confirm">
                        <Actions><ActionButton icon="icon-close" @click.stop.prevent="cancelCreate" /></Actions>
                    </form>
                </div>
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
                    @deleteProject="onDeleteProject"
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
    AppNavigationSettings, AppNavigationItem, ActionInput, Actions
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
        ActionInput,
        Actions
    },
    directives: {
        ClickOutside,
    },
    props: ['projects', 'selectedProjectId'],
    data() {
        return {
            opened: false,
            loading: false,
            creating: false
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
        onDeleteProject: function(projectid) {
            this.$emit('deleteProject', projectid);
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
        startCreateProject(e) {
            this.creating = true;
        },
        createProject(e) {
            const name = e.currentTarget.childNodes[0].value;
            this.$emit('createProject', name);
            this.creating = false;
        },
        cancelCreate(e) {
            this.creating = false;
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
.project-create {
    order: 1;
    display: flex;
    height: 44px;
    form {
        display: flex;
        flex-grow: 1;
        input[type="text"] {
            flex-grow: 1;
        }
    }
}
</style>