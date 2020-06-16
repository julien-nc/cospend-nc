<template>
    <div>
        <Multiselect
            v-if="true"
            v-model="selectedSharee"
            class="shareInput"
            :placeholder="t('cospend', 'Share project with a user, group or circle …')"
            :options="formatedSharees"
            :user-select="true"
            label="displayName"
            track-by="multiselectKey"
            :internal-search="true"
            @input="clickShareeItem"
            />

        <ul
            id="shareWithList"
            class="shareWithList">
            <li @click="addLink">
                <div v-if="true" class="avatardiv icon icon-public" />
                <span class="username">
                    {{ t('cospend', 'Add public link') }}
                </span>
                <ActionButton class="addLinkButton" icon="icon-add" :ariaLabel="t('cospend', 'Add link')"/>
            </li>
            <li>
                <Avatar :user="project.userid" />
                <span class="has-tooltip username">
                    {{ project.userid }}
                    <span v-if="!isCurrentUser(project.userid)" class="board-owner-label">
                        {{ t('cospend', 'Project owner') }}
                    </span>
                </span>
            </li>
            <li v-for="access in shares" :key="access.id">
                <Avatar v-if="access.type==='u'" :user="access.userid" />
                <div v-if="access.type==='g'" class="avatardiv icon icon-group" />
                <div v-if="access.type==='c'" class="avatardiv icon icon-circles" />
                <div v-if="access.type==='l'" class="avatardiv icon icon-public" />
                <span class="username">
                    <span v-if="access.type==='l'">{{ t('cospend', 'Public link') }}</span>
                    <span v-else>{{ access.name }}</span>
                    <span v-if="access.type==='g'">{{ t('cospend', '(Group)') }}</span>
                    <span v-if="access.type==='c'">{{ t('cospend', '(Circle)') }}</span>
                </span>

                <ActionButton v-if="access.type==='l'" class="copyLinkButton" icon="icon-clippy"
                    :ariaLabel="t('cospend', 'Copy link')" @click="copyLink(access)"/>

                <!-- TODO if enough permissions -->
                <Actions v-if="true" :force-menu="true">
                    <ActionRadio name="accessLevel" v-if="true" :checked="access.accesslevel === 1" @change="clickAccessLevel(access, 1)">
                        {{ t('cospend', 'Viewer') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" v-if="true" :checked="access.accesslevel === 2" @change="clickAccessLevel(access, 2)">
                        {{ t('cospend', 'Participant') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" v-if="true" :checked="access.accesslevel === 3" @change="clickAccessLevel(access, 3)">
                        {{ t('cospend', 'Maintener') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" v-if="true" :checked="access.accesslevel === 4" @change="clickAccessLevel(access, 4)">
                        {{ t('cospend', 'Admin') }}
                    </ActionRadio>
                    <ActionButton v-if="true" icon="icon-delete" @click="clickDeleteAccess(access)">
                        {{ t('cospend', 'Delete') }}
                    </ActionButton>
                </Actions>
            </li>
        </ul>
    </div>
</template>

<script>
import { Avatar, Multiselect, Actions, ActionButton, ActionCheckbox, ActionRadio } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import cospend from '../state';
import * as constants from '../constants';

export default {
    name: 'SharingTabSidebar',
    components: {
        Avatar,
        Actions,
        ActionButton,
        ActionCheckbox, ActionRadio,
        Multiselect,
    },
    props: ['project'],
    data() {
        return {
            isLoading: false,
            selectedSharee: null,
            sharees: [],
        }
    },
    computed: {
        shares() {
            return this.project.shares;
        },
        projectId() {
            return this.project.id;
        },
        isCurrentUser() {
            return (uid) => uid === getCurrentUser().uid
        },
        formatedSharees() {
            return this.unallocatedSharees.map(item => {
                const sharee = {
                    user: item.id,
                    displayName: item.label,
                    icon: 'icon-user',
                    type: item.type,
                    value: item.value,
                    multiselectKey: item.type + ':' + item.id,
                }
                if (item.type === 'g') {
                    sharee.icon = 'icon-group'
                    sharee.isNoUser = true
                }
                if (item.type === 'c') {
                    sharee.icon = 'icon-circles'
                    sharee.isNoUser = true
                }
                return sharee
            })
        },
        // those with which the project is not shared yet
        unallocatedSharees() {
            return this.sharees.filter((sharee) => {
                return true;
                //const foundIndex = this.board.acl.findIndex((acl) => {
                //    return acl.participant.uid === sharee.value.shareWith && acl.participant.type === sharee.value.shareType
                //})
                //if (foundIndex === -1) {
                //    return true
                //}
                //return false
            })
        },
    },
    mounted() {
        this.asyncFind('')
    },
    methods: {
        asyncFind(query) {
            this.isLoading = true
            this.loadSharees(query);
        },
        loadSharees() {
            const that = this;
            const url = generateUrl('/apps/cospend/user-list');
            $.ajax({
                type: 'GET',
                url: url,
                data: {},
                async: true
            }).done(function(response) {
                cospend.userIdName = response.users;
                cospend.groupIdName = response.groups;
                cospend.circleIdName = response.circles;
                const data = [];
                let d, name, id;
                for (id in response.users) {
                    name = response.users[id];
                    d = {
                        id: id,
                        name: name,
                        type: 'u',
                    };
                    if (id !== name) {
                        d.label = name + ' (' + id + ')';
                        d.value = name + ' (' + id + ')';
                    } else {
                        d.label = name;
                        d.value = name;
                    }
                    data.push(d);
                }
                for (id in response.groups) {
                    name = response.groups[id];
                    d = {
                        id: id,
                        name: name,
                        type: 'g',
                    };
                    if (id !== name) {
                        d.label = name + ' (' + id + ')';
                        d.value = name + ' (' + id + ')';
                    } else {
                        d.label = name;
                        d.value = name;
                    }
                    data.push(d);
                }
                for (id in response.circles) {
                    name = response.circles[id];
                    d = {
                        id: id,
                        name: name,
                        type: 'c',
                    };
                    d.label = name;
                    d.value = name;
                    data.push(d);
                }
                that.sharees = data;
            }).fail(function() {
                showError(t('cospend', 'Failed to get user list.'));
            });
        },
        clickShareeItem() {
            this.addSharedAccess(this.selectedSharee);
        },
        addSharedAccess(sh) {
            const that = this;
            const req = {};
            let url;
            if (sh.type === 'u') {
                req.userid = sh.user;
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/user-share');
            } else if (sh.type === 'g') {
                req.groupid = sh.user;
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/group-share');
            } else if (sh.type === 'c') {
                req.circleid = sh.user;
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/circle-share');
            } else if (sh.type === 'l') {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/public-share');
            }
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function(response) {
                const newShAccess = {
                    accesslevel: constants.ACCESS.PARTICIPANT,
                    type: sh.type
                };
                newShAccess.id = response.id;
                if (sh.type === 'l') {
                    newShAccess.token = response.token;
                } else {
                    newShAccess.name = response.name;
                    if (sh.type === 'u') {
                        newShAccess.userid = sh.user;
                    } else if (sh.type === 'g') {
                        newShAccess.groupid = sh.user;
                    } else if (sh.type === 'c') {
                        newShAccess.circleid = sh.user;
                    }
                }
                that.project.shares.push(newShAccess);
            }).always(function() {
            }).fail(function(response) {
                showError(
                    t('cospend', 'Failed to add shared access') +
                    ': ' + response.responseText
                );
            });
        },
        clickAccessLevel(access, level) {
            const req = {
                accesslevel: level
            };
            const url = generateUrl('/apps/cospend/projects/' + this.projectId + '/share-access-level/' + access.id);
            $.ajax({
                type: 'PUT',
                url: url,
                data: req,
                async: true
            }).done(function() {
                access.accesslevel = level;
            }).always(function() {
            }).fail(function(response) {
                showError(
                    t('cospend', 'Failed to edit shared access level') +
                    ': ' + response.responseText
                );
            });
        },
        clickDeleteAccess(access) {
            const that = this;
            const shid = access.id;
            const req = {};
            let url;
            if (access.type === 'u') {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/user-share/' + shid);
            } else if (access.type === 'g') {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/group-share/' + shid);
            } else if (access.type === 'c') {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/circle-share/' + shid);
            } else if (access.type === 'l') {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/public-share/' + shid);
            }
            $.ajax({
                type: 'DELETE',
                url: url,
                data: req,
                async: true
            }).done(function() {
                const index = that.shares.indexOf(access);
                that.shares.splice(index, 1);
            }).always(function() {
            }).fail(function(response) {
                showError(
                    t('cospend', 'Failed to delete shared access') +
                    ': ' + response.responseJSON.message
                );
            });
        },
        async copyLink(access) {
            const publicLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/s/' + access.token);
            try {
                await this.$copyText(publicLink)
                showSuccess(t('cospend', 'Public link copied to clipboard.'))
            } catch (error) {
                console.debug(error)
                showError(t('cospend', 'Public link could not be copied to clipboard.'))
            }
        },
        addLink() {
            this.addSharedAccess({type: 'l'});
        }
    },
}
</script>
<style scoped>
    .shareInput {
        width: 100%;
    }
    #shareWithList {
        margin-bottom: 20px;
    }
    #shareWithList li {
        display: flex;
        align-items: center;
    }
    .username {
        padding: 12px 9px;
        flex-grow: 1;
    }
    .board-owner-label {
        opacity: .7;
    }
    .avatarLabel {
        padding: 6px
    }
    .avatardiv {
        background-color: #f5f5f5;
        border-radius: 16px;
        width: 32px;
        height: 32px;
    }
</style>