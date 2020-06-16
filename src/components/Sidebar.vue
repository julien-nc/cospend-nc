<template>
    <AppSidebar v-show="show"
        :title="title"
        :subtitle="subtitle"
        @close="$emit('close')">
        <!--template #primary-actions>
            <button class="primary">
                Button 1
            </button>
            <input id="link-checkbox"
                name="link-checkbox"
                class="checkbox link-checkbox"
                type="checkbox">
            <label for="link-checkbox" class="link-checkbox-label">Do something</label>
        </template>
        <template #secondary-actions>
            <ActionButton icon="icon-edit" @click="alert('Edit')">
                Edit
            </ActionButton>
            <ActionButton icon="icon-delete" @click="alert('Delete')">
                Delete
            </ActionButton>
            <ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
        </template-->
        <AppSidebarTab id="sharing" name="Sharing" icon="icon-shared"
            :order="1"
            >
            <SharingTabSidebar :project="project"
                @projectEdited="onProjectEdited"
                />
        </AppSidebarTab>
        <AppSidebarTab :id="'activity'" :name="'Activity'" :icon="'icon-calendar-dark'"
            :order="2"
            >
            this is the activity tabbbaaaa
        </AppSidebarTab>
        <AppSidebarTab :id="'comments'" :name="'Comments'" :icon="'icon-comment'"
            :order="3"
            v-if="false"
            >
            this is the comments tab
        </AppSidebarTab>
        <AppSidebarTab id="versions" name="Versions" icon="icon-history"
            :order="3"
            v-if="false"
            >
            this is the versions tab
        </AppSidebarTab>
    </AppSidebar>
</template>

<script>
import {
    ActionButton, AppSidebar, AppSidebarTab, ActionLink
} from '@nextcloud/vue'
import SharingTabSidebar from './SharingTabSidebar'
import cospend from '../state';

export default {
    name: 'Sidebar',
    components: {
        ActionButton, AppSidebar, AppSidebarTab, ActionLink, SharingTabSidebar
    },
    props: ['show', 'projectId', 'bills'],
    data() {
        return {
        };
    },
    computed: {
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
        }
    },
    methods: {
        onProjectEdited(projectid, password=null) {
            this.$emit('projectEdited', projectid, password);
        }
    }
}
</script>

<style scoped lang="scss">
</style>