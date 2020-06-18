<template>
    <AppSidebar v-show="show"
        :title="title"
        :subtitle="subtitle"
        :active="activeTab"
        @update:active="onActiveChanged"
        @close="$emit('close')">
        <template slot="primary-actions">
        </template>
        <template v-if="false" slot="secondary-actions">
            <ActionButton icon="icon-edit" @click="alert('Edit')">
                Edit
            </ActionButton>
            <ActionButton icon="icon-delete" @click="alert('Delete')">
                Delete
            </ActionButton>
            <ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
        </template>
        <AppSidebarTab id="sharing" :name="t('cospend', 'Sharing')" icon="icon-shared"
            v-if="!pageIsPublic"
            :order="1"
            >
            <SharingTabSidebar :project="project"
                @projectEdited="onProjectEdited"
                @mbLinkClicked="onMBLinkClicked"
                />
        </AppSidebarTab>
        <!--AppSidebarTab :id="'activity'" :name="'Activity'" :icon="'icon-calendar-dark'"
            :order="2"
            >
            this is the activity tabbbaaaa
        </AppSidebarTab>
        <AppSidebarTab :id="'comments'" :name="'Comments'" :icon="'icon-comment'"
            :order="3"
            v-if="false"
            >
            this is the comments tab
        </AppSidebarTab-->
        <AppSidebarTab id="settings" :name="t('cospend', 'Settings')" icon="icon-settings-dark"
            :order="2"
            v-if="editionAccess"
            >
            <SettingsTabSidebar :project="project"
                @projectEdited="onProjectEdited"
                @userAdded="onUserAdded"
                @memberEdited="onMemberEdited"
                @newSimpleMember="onNewSimpleMember"
                />
        </AppSidebarTab>
    </AppSidebar>
</template>

<script>
import {
    ActionButton, AppSidebar, AppSidebarTab, ActionLink
} from '@nextcloud/vue'
import SharingTabSidebar from './SharingTabSidebar'
import SettingsTabSidebar from './SettingsTabSidebar'
import cospend from '../state';
import * as constants from '../constants';

export default {
    name: 'Sidebar',
    components: {
        ActionButton, AppSidebar, AppSidebarTab, ActionLink, SharingTabSidebar, SettingsTabSidebar
    },
    props: ['show', 'activeTab', 'projectId', 'bills'],
    data() {
        return {
        };
    },
    computed: {
        pageIsPublic() {
            return cospend.pageIsPublic;
        },
        project() {
            return cospend.projects[this.projectId];
        },
        title() {
            return t('cospend', 'Project {name}', {name: this.project.name});
        },
        members() {
            return (this.bills.length > 0) ? cospend.members[this.projectId] : [];
        },
        subtitle() {
            const nbBills = this.bills.length;
            let spent = 0;
            this.bills.forEach(function(bill) {
                spent += bill.amount;
            });
            let nbActiveMembers = 0;
            let member;
            for (const mid in this.members) {
                member = this.members[mid];
                if (member.activated) {
                    nbActiveMembers++;
                }
            }
            return t('cospend', '{nb} bills, {nm} active members, {ns} spent', {nb: nbBills, nm: nbActiveMembers, ns: spent.toFixed(2)})
        },
        editionAccess() {
            return this.project.myaccesslevel >= constants.ACCESS.MAINTENER;
        },
    },
    methods: {
        onActiveChanged(newActive) {
            this.$emit('activeChanged', newActive);
        },
        onProjectEdited(projectid, password=null) {
            this.$emit('projectEdited', projectid, password);
        },
        onUserAdded(projectid, name, userid) {
            this.$emit('userAdded', projectid, name, userid);
        },
        onMemberEdited(projectid, memberid, userid, name) {
            this.$emit('memberEdited', projectid, memberid, userid, name);
        },
        onMBLinkClicked() {
            this.$emit('mbLinkClicked');
        },
        onNewSimpleMember(projectid, name) {
            this.$emit('newMember', projectid, name);
        },
    }
}
</script>

<style scoped lang="scss">
</style>