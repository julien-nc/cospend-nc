<template>
    <AppSidebar v-show="show"
        :title="title"
        subtitle="4,3 MB, last edited 41 days ago"
        @close="$emit('close')">
        <template #primary-actions>
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
        </template>
        <AppSidebarTab id="vueexample" name="Vueexample" icon="icon-vueexample">
            this is the vueexample tab
        </AppSidebarTab>
        <AppSidebarTab id="activity" name="Activity" icon="icon-activity">
            this is the activity tab
        </AppSidebarTab>
        <AppSidebarTab id="comments" name="Comments" icon="icon-comment">
            this is the comments tab
        </AppSidebarTab>
        <AppSidebarTab id="sharing" name="Sharing" icon="icon-shared">
            this is the sharing tab
        </AppSidebarTab>
        <AppSidebarTab id="versions" name="Versions" icon="icon-history">
            this is the versions tab
        </AppSidebarTab>
    </AppSidebar>
</template>

<script>
import {
    ActionButton, AppSidebar, AppSidebarTab, ActionLink
} from '@nextcloud/vue'
import cospend from '../state';

export default {
    name: 'Sidebar',
    components: {
        ActionButton, AppSidebar, AppSidebarTab, ActionLink
    },
    props: ['show', 'projectId'],
    data() {
        return {
        };
    },
    computed: {
        project() {
            return cospend.projects[this.projectId];
        },
        title() {
            return this.project.name;
        }
    }
}
</script>

<style scoped lang="scss">

</style>