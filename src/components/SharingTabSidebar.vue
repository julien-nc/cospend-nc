<template>
    <div>
        <Multiselect
            v-if="editionAccess"
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
            <li @click="addLink" v-if="editionAccess">
                <div class="avatardiv icon icon-public-white" />
                <span class="username">
                    {{ t('cospend', 'Add public link') }}
                </span>
                <ActionButton class="addLinkButton" icon="icon-add" :ariaLabel="t('cospend', 'Add link')"/>
            </li>
            <li v-for="access in linkShares" :key="access.id">
                <div class="avatardiv icon icon-public-white" />
                <span class="username">
                    <span>{{ t('cospend', 'Public link') }}</span>
                </span>

                <Popover>
                    <ActionButton slot="trigger" class="copyLinkButton"
                        :icon="(linkCopied[access.id]) ? 'icon-checkmark-color' : 'icon-clippy'"
                        :ariaLabel="t('cospend', 'Copy link')" @click="copyLink(access)"/>
                    <template>
                        {{ t('cospend', 'Copied!') }}
                    </template>
                </Popover>

                <Actions :force-menu="true">
                    <ActionRadio name="accessLevel" :disabled="!editionAccess || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 1"
                        @change="clickAccessLevel(access, 1)">
                        {{ t('cospend', 'Viewer') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" :disabled="!editionAccess || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 2"
                        @change="clickAccessLevel(access, 2)">
                        {{ t('cospend', 'Participant') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" :disabled="myAccessLevel < 3 || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 3"
                        @change="clickAccessLevel(access, 3)">
                        {{ t('cospend', 'Maintainer') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" :disabled="myAccessLevel < 4 || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 4"
                        @change="clickAccessLevel(access, 4)">
                        {{ t('cospend', 'Admin') }}
                    </ActionRadio>
                    <ActionButton v-if="editionAccess && myAccessLevel > access.accesslevel"
                        icon="icon-delete" @click="clickDeleteAccess(access)">
                        {{ t('cospend', 'Delete') }}
                    </ActionButton>
                </Actions>
            </li>
            <li>
                <Avatar :disableMenu="true" :disableTooltip="true" :user="project.userid" />
                <span class="has-tooltip username">
                    {{ project.userid }}
                    <span class="project-owner-label">
                        ({{ t('cospend', 'Project owner') }})
                    </span>
                </span>
            </li>
            <li v-for="access in ugcShares" :key="access.id">
                <Avatar
                    v-if="access.type==='u'" :user="access.userid"
                    :disableMenu="true" :disableTooltip="true" />
                <div v-if="access.type==='g'" class="avatardiv icon icon-group" />
                <div v-if="access.type==='c'" class="avatardiv icon share-icon-circle" />
                <span class="username">
                    <span>{{ access.name }}</span>
                    <span v-if="access.type==='g'">({{ t('cospend', 'Group') }})</span>
                    <span v-if="access.type==='c'">({{ t('cospend', 'Circle') }})</span>
                </span>

                <Actions :force-menu="true">
                    <ActionRadio name="accessLevel" :disabled="!editionAccess || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 1"
                        @change="clickAccessLevel(access, 1)">
                        {{ t('cospend', 'Viewer') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" :disabled="!editionAccess || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 2"
                        @change="clickAccessLevel(access, 2)">
                        {{ t('cospend', 'Participant') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" :disabled="myAccessLevel < 3 || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 3"
                        @change="clickAccessLevel(access, 3)">
                        {{ t('cospend', 'Maintainer') }}
                    </ActionRadio>
                    <ActionRadio name="accessLevel" :disabled="myAccessLevel < 4 || isCurrentUser(access.userid)"
                        :checked="access.accesslevel === 4"
                        @change="clickAccessLevel(access, 4)">
                        {{ t('cospend', 'Admin') }}
                    </ActionRadio>
                    <ActionButton v-if="editionAccess && myAccessLevel > access.accesslevel"
                        icon="icon-delete" @click="clickDeleteAccess(access)">
                        {{ t('cospend', 'Delete') }}
                    </ActionButton>
                </Actions>
            </li>
        </ul>
        <hr/><br/>
        <ul
            id="guestList"
            class="shareWithList">
            <li>
                <div class="avatardiv icon icon-password" />
                <span class="username">
                    <span>{{ t('cospend', 'Password protected access') }}</span>
                </span>

                <Popover>
                    <ActionButton slot="trigger" class="copyLinkButton"
                        :icon="guestLinkCopied ? 'icon-checkmark-color' : 'icon-clippy'"
                        :ariaLabel="t('cospend', 'Copy link')" @click="copyPasswordLink"/>
                    <template>
                        {{ t('cospend', 'Copied!') }}
                    </template>
                </Popover>

                <Actions v-if="true" :force-menu="true">
                    <ActionRadio name="guestAccessLevel"
                        :disabled="myAccessLevel < 4"
                        :checked="project.guestaccesslevel === 1" @change="clickGuestAccessLevel(1)">
                        {{ t('cospend', 'Viewer') }}
                    </ActionRadio>
                    <ActionRadio name="guestAccessLevel"
                        :disabled="myAccessLevel < 4"
                        :checked="project.guestaccesslevel === 2" @change="clickGuestAccessLevel(2)">
                        {{ t('cospend', 'Participant') }}
                    </ActionRadio>
                    <ActionRadio name="guestAccessLevel"
                        :disabled="myAccessLevel < 4"
                        :checked="project.guestaccesslevel === 3" @change="clickGuestAccessLevel(3)">
                        {{ t('cospend', 'Maintainer') }}
                    </ActionRadio>
                    <ActionRadio name="guestAccessLevel"
                        :disabled="myAccessLevel < 4"
                        :checked="project.guestaccesslevel === 4" @change="clickGuestAccessLevel(4)">
                        {{ t('cospend', 'Admin') }}
                    </ActionRadio>
                </Actions>
            </li>
        </ul>
        <form id="newPasswordForm" @submit.prevent.stop="setPassword" v-if="myAccessLevel === 4">
            <label for="newPasswordInput">{{ t('cospend', 'New project password') }}</label>
            <div>
                <input id="newPasswordInput" ref="newPasswordInput" value="" type="password"
                        @focus="$event.target.select()"/>
                <input type="submit" value="" class="icon-confirm">
            </div>
        </form>
        <br/><hr/><br/>
        <MoneyBusterLink
            :project="project"
        />
    </div>
</template>

<script>
import {
    Avatar, Multiselect, Actions, ActionButton,
    ActionCheckbox, ActionRadio, ActionSeparator,
    AppNavigationItem, Popover
} from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import MoneyBusterLink from '../MoneyBusterLink';
import cospend from '../state';
import * as constants from '../constants';
import * as network from '../network';
import { Timer } from '../utils';

export default {
    name: 'SharingTabSidebar',
    components: {
        MoneyBusterLink,
        Avatar,
        Actions,
        ActionButton,
        ActionCheckbox, ActionRadio, ActionSeparator,
        Multiselect, AppNavigationItem, Popover
    },
    props: ['project'],
    data() {
        return {
            isLoading: false,
            selectedSharee: null,
            sharees: [],
            guestLinkCopied: false,
            linkCopied: {}
        }
    },
    mounted() {
        this.asyncFind()
    },
    computed: {
        editionAccess() {
            return this.project.myaccesslevel >= constants.ACCESS.PARTICIPANT;
        },
        myAccessLevel() {
            return this.project.myaccesslevel;
        },
        shares() {
            return this.project.shares;
        },
        linkShares() {
            const ls = [];
            for (let i = 0; i < this.shares.length; i++) {
                if (this.shares[i].type === 'l') {
                    ls.push(this.shares[i]);
                }
            }
            return ls;
        },
        ugcShares() {
            const ls = [];
            for (let i = 0; i < this.shares.length; i++) {
                if (this.shares[i].type !== 'l') {
                    ls.push(this.shares[i]);
                }
            }
            return ls;
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
                    sharee.icon = 'share-icon-circle'
                    sharee.isNoUser = true
                }
                return sharee
            })
        },
        // those with which the project is not shared yet
        unallocatedSharees() {
            return this.sharees.filter((sharee) => {
                let foundIndex;
                if (sharee.type === 'u') {
                    foundIndex = this.shares.findIndex((access) => {
                        return access.userid === sharee.id && access.type === 'u'
                    })
                } else if (sharee.type === 'g') {
                    foundIndex = this.shares.findIndex((access) => {
                        return access.groupid === sharee.id && access.type === 'g'
                    })
                } else if (sharee.type === 'c') {
                    foundIndex = this.shares.findIndex((access) => {
                        return access.circleid === sharee.id && access.type === 'c'
                    })
                }
                if (foundIndex === -1) {
                    return true
                }
                return false
            })
        },
    },
    methods: {
        asyncFind() {
            this.isLoading = true
            this.loadSharees();
        },
        loadSharees() {
            network.loadUsers(this.loadShareesSuccess);
        },
        loadShareesSuccess(response) {
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
            this.sharees = data;
        },
        clickShareeItem() {
            this.addSharedAccess(this.selectedSharee);
        },
        addSharedAccess(sh) {
            network.addSharedAccess(this.projectId, sh, this.addSharedAccessSuccess);
        },
        addSharedAccessSuccess(response, sh) {
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
            this.project.shares.push(newShAccess);
        },
        clickAccessLevel(access, level) {
            network.setAccessLevel(this.projectId, access, level, this.setAccessLevelSuccess);
        },
        setAccessLevelSuccess(access, level) {
            access.accesslevel = level;
        },
        clickDeleteAccess(access) {
            network.deleteAccess(this.projectId, access, this.deleteAccessSuccess);
        },
        deleteAccessSuccess(access) {
            const index = this.shares.indexOf(access);
            this.shares.splice(index, 1);
        },
        async copyLink(access) {
            const publicLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/s/' + access.token);
            try {
                await this.$copyText(publicLink)
                this.$set(this.linkCopied, access.id, true);
                const that = this;
                new Timer(function () {
                    that.$set(that.linkCopied, access.id, false);
                }, 5000);
            } catch (error) {
                console.debug(error)
                showError(t('cospend', 'Link could not be copied to clipboard.'))
            }
        },
        addLink() {
            this.addSharedAccess({type: 'l'});
        },
        setPassword() {
            const password = this.$refs.newPasswordInput.value;
            if (password) {
                this.$emit('projectEdited', this.projectId, password);
            } else {
                showError(t('cospend', 'Password should not be empty.'));
            }
        },
        async copyPasswordLink() {
            const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/loginproject/' + this.projectId);
            try {
                await this.$copyText(guestLink)
                this.guestLinkCopied = true;
                const that = this;
                new Timer(function () {
                    that.guestLinkCopied = false;
                }, 5000);
            } catch (error) {
                console.debug(error)
                showError(t('cospend', 'Link could not be copied to clipboard.'))
            }
        },
        clickGuestAccessLevel(level) {
            network.setGuestAccessLevel(this.projectId, level, this.setGuestAccessLevelSuccess);
        },
        setGuestAccessLevelSuccess(level) {
            this.project.guestaccesslevel = level;
            showSuccess(t('cospend', 'Guest access level changed.'))
        },
    },
}
</script>
<style scoped>
    .shareInput {
        width: 100%;
    }
    .shareWithList {
        margin-bottom: 20px;
    }
    .shareWithList li {
        display: flex;
        align-items: center;
    }
    .username {
        padding: 12px 9px;
        flex-grow: 1;
    }
    .project-owner-label {
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
    #newPasswordForm div {
        width: 48%;
        display: inline-block;
    }
    #newPasswordForm label {
        text-align: center;
        display: inline-block;
        width: 48%;
    }
    .avatardiv.icon-public-white {
        background-color: var(--color-primary);
    }
</style>