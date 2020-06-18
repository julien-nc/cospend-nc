<template>
    <div id="app-navigation" :class="{'icon-loading': loading}">
        <AppNavigationVue>
            <ul>
                <div v-if="!pageIsPublic">
                    <AppNavigationItem v-if="!creating"
                        :title="t('cospend', 'New project')"
                        icon="icon-add"
                        class="buttonItem"
                        @click.prevent.stop="startCreateProject" />
                    <div v-else class="project-create">
                        <form @submit.prevent.stop="createProject">
                            <input :placeholder="t('cospend', 'New project name')" type="text" required>
                            <input type="submit" value="" class="icon-confirm">
                            <Actions><ActionButton icon="icon-close" @click.stop.prevent="cancelCreate" /></Actions>
                        </form>
                    </div>
                </div>
                <AppNavigationItem
                    v-if="editionAccess"
                    :title="t('cospend', 'New bill')"
                    @click="onNewBillClick"
                    icon="icon-edit"
                    class="buttonItem"
                    v-show="selectedProjectId"
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
                    @exportClicked="onExportClicked"
                    @statsClicked="onStatsClicked"
                    @settleClicked="onSettleClicked"
                    @categoryClicked="onCategoryClicked"
                    @currencyClicked="onCurrencyClicked"
                    @detailClicked="onDetailClicked"
                    @newMemberClicked="onNewMemberClicked"
                    @memberEdited="onMemberEdited"
                    @projectEdited="onProjectEdited"
                    />
            </ul>
            <AppNavigationSettings>
                <AppNavigationItem
                    v-if="!pageIsPublic"
                    :title="t('cospend', 'Import csv project')"
                    @click="onImportClick"
                    icon="icon-download"
                    class="buttonItem"
                    v-show="true"
                    />
                <AppNavigationItem
                    v-if="!pageIsPublic"
                    :title="t('cospend', 'Import SplitWise project')"
                    @click="onImportSWClick"
                    icon="icon-download"
                    class="buttonItem"
                    v-show="true"
                    />
                <AppNavigationItem
                    :title="t('cospend', 'Guest access link')"
                    @click="onGuestLinkClick"
                    icon="icon-clippy"
                    class="buttonItem"
                    v-show="true"
                    />
                <div class="output-dir"
                    v-if="!pageIsPublic">
                    <button class="icon-folder" @click="onOutputDirClick">
                        {{ t('cospend', 'Change output directory') }}
                    </button>
                    <input v-model="outputDir" :placeholder="t('cospend', '/Anywhere')" type="text" readonly @click="onOutputDirClick"/>
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
    ActionButton, ActionText, AppNavigation as AppNavigationVue, AppNavigationIconBullet,
    AppNavigationSettings, AppNavigationItem, ActionInput, Actions
} from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import cospend from '../state';
import * as constants from '../constants';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'

export default {
    name: 'AppNavigation',
    components: {
        AppNavigationProjectItem,
        AppNavigationVue,
        AppNavigationItem,
        AppNavigationSettings,
        AppNavigationIconBullet,
        ActionButton, ActionText,
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
            creating: false,
            outputDir: cospend.outputDirectory,
            pageIsPublic: cospend.pageIsPublic
        }
    },
    computed: {
        editionAccess() {
            return this.selectedProjectId && this.projects[this.selectedProjectId].myaccesslevel >= constants.ACCESS.PARTICIPANT;
        }
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
        onImportClick() {
            const that = this;
            OC.dialogs.filepicker(
                t('cospend', 'Choose csv project file'),
                function(targetPath) {
                    that.importProject(targetPath);
                },
                false,
                ['text/csv'],
                true
            );
        },
        onImportSWClick() {
            const that = this;
            OC.dialogs.filepicker(
                t('cospend', 'Choose SplitWise project file'),
                function(targetPath) {
                    that.importProject(targetPath, true);
                },
                false,
                ['text/csv'],
                true
            );
        },
        importProject(targetPath, isSplitWise=false) {
            const that = this;
            const req = {
                path: targetPath
            };
            let url;
            if (isSplitWise) {
                url = generateUrl('/apps/cospend/import-sw-project');
            } else {
                url = generateUrl('/apps/cospend/import-csv-project');
            }
            $.ajax({
                type: 'GET',
                url: url,
                data: req,
                async: true
            }).done(function(response) {
                that.$emit('projectImported', response)
                showSuccess(t('cospend', 'Project imported.'))
            }).always(function() {
            }).fail(function(response) {
                showError(
                    t('cospend', 'Failed to import project file') +
                    ': ' + response.responseJSON.message
                );
            });
        },
        async onGuestLinkClick() {
            try {
                const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/login');
                await this.$copyText(guestLink)
                showSuccess(t('cospend', 'Guest link copied to clipboard.'))
            } catch (error) {
                console.debug(error)
                showError(t('cospend', 'Guest link could not be copied to clipboard.'))
            }
        },
        onOutputDirClick() {
            const that = this;
            OC.dialogs.filepicker(
                t('maps', 'Choose where to write output files (stats, settlement, export)'),
                function(targetPath) {
                    if (targetPath === '') {
                        targetPath = '/';
                    }
                    that.outputDir = targetPath;
                    that.$emit('saveOption', 'outputDirectory', targetPath)
                },
                false,
                'httpd/unix-directory',
                true
            );
        },
        onNewBillClick() {
            this.$emit('newBillClicked');
        },
        onProjectClicked(projectid) {
            this.$emit('projectClicked', projectid);
        },
        onDeleteProject(projectid) {
            this.$emit('deleteProject', projectid);
        },
        onQrcodeClicked(projectid) {
            this.$emit('qrcodeClicked', projectid);
        },
        onExportClicked(projectid) {
            this.$emit('exportClicked', projectid);
        },
        onStatsClicked(projectid) {
            this.$emit('statsClicked', projectid);
        },
        onSettleClicked(projectid) {
            this.$emit('settleClicked', projectid);
        },
        onCategoryClicked(projectid) {
            this.$emit('categoryClicked', projectid);
        },
        onCurrencyClicked(projectid) {
            this.$emit('currencyClicked', projectid);
        },
        onDetailClicked(projectid) {
            this.$emit('detailClicked', projectid);
        },
        onNewMemberClicked(projectid) {
            this.$emit('newMemberClicked', projectid);
        },
        onMemberEdited(projectid, memberid) {
            this.$emit('memberEdited', projectid, memberid);
        },
        onProjectEdited(projectid) {
            this.$emit('projectEdited', projectid);
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
.output-dir {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.output-dir button {
    width: 59% !important;
}
.output-dir input {
    width: 39% !important;
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
.buttonItem {
    border-bottom: solid 1px var(--color-border);
}
</style>