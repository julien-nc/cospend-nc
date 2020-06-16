<template>
    <AppSidebar v-show="show"
        :title="title"
        :subtitle="subtitle"
        @close="$emit('close')">
        <template slot="primary-actions">
            <div id="autoExport">
                <label for="autoExportSelect">
                    <span class="icon icon-schedule"></span>
                    <span>{{ t('cospend', 'Automatic export') }}</span>
                </label>
                <select id="autoExportSelect"
                    :disabled="!adminAccess"
                    :value="project.autoexport" @input="onAutoExportSet">
                    <option value="n">{{ t('cospend', 'No') }}</option>
                    <option value="d">{{ t('cospend', 'Daily') }}</option>
                    <option value="w">{{ t('cospend', 'Weekly') }}</option>
                    <option value="m">{{ t('cospend', 'Monthly') }}</option>
                </select>
            </div>
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
        <AppSidebarTab id="sharing" name="Sharing" icon="icon-shared"
            v-if="!pageIsPublic"
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
import * as constants from '../constants';

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
        adminAccess() {
            return this.project.myaccesslevel >= constants.ACCESS.ADMIN;
        },
    },
    methods: {
        onProjectEdited(projectid, password=null) {
            this.$emit('projectEdited', projectid, password);
        },
        onAutoExportSet(e) {
            this.project.autoexport = e.target.value;
            this.onProjectEdited(this.projectId);
        }
    }
}
</script>

<style scoped lang="scss">
#autoExport {
    width: 100%;
}
#autoExport span.icon {
    display: inline-block;
    min-width: 30px !important;
    min-height: 18px !important;
    width: 30px;
    height: 18px;
    vertical-align: sub;
}
#autoExport label,
#autoExport select {
    display: inline-block;
    width: 49%;
}
</style>