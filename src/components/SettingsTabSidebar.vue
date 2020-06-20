<template>
    <div>
        <br/>
        <div class="renameProject">
            <form v-if="adminAccess" @submit.prevent.stop="onRenameProject">
                <input
                    v-model="newProjectName"
                    :placeholder="t('cospend', 'Rename project {n}', {n: project.name})"
                    type="text"/>
                <input type="submit" value="" class="icon-confirm"/>
            </form>
            <br/>
        </div>
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
        <div v-if="!pageIsPublic">
            <br/><hr/><br/>
            <!-- let's keep this just in case the combined one is not efficient... -->
            <!--div class="newMember">
                <form v-if="maintenerAccess" @submit.prevent.stop="onAddMember">
                    <input v-model="newMemberName" :placeholder="t('cospend', 'Add a simple member')" type="text">
                    <input type="submit" value="" class="icon-confirm">
                </form>
            </div><br/-->
            <p class="label">
                <span class="labelIcon icon-user"></span>
                {{ t('cospend', 'Add a project member.')}}
            </p>
            <Multiselect
                v-if="maintenerAccess"
                v-model="selectedAddUser"
                class="addUserInput"
                :placeholder="t('cospend', 'New member (or Nextcloud user) name')"
                :options="formatedUsers"
                :user-select="true"
                label="displayName"
                track-by="multiselectKey"
                :internal-search="true"
                @input="clickAddUserItem"
                ref="userMultiselect"
                />
            <br/><br/><hr/><br/>
            <p class="label">
                <span class="labelIcon icon-user"></span>
                {{ t('cospend', 'Choose a project member, then a Nextcloud user to associate with.')}}
            </p>
            <div id="affectDiv">
                <select v-model="selectedMember">
                    <option v-for="member in activatedMembers" :key="member.id"
                        :value="member.id">
                        {{ member.name }}
                    </option>
                </select>
                <Multiselect
                    :disabled="!selectedMember"
                    v-if="maintenerAccess"
                    v-model="selectedAffectUser"
                    class="affectUserInput"
                    :placeholder="t('cospend', 'Choose a Nextcloud user')"
                    :options="formatedUsersAffect"
                    :user-select="true"
                    label="displayName"
                    track-by="multiselectKey"
                    :internal-search="true"
                    @input="clickAffectUserItem"
                    />
            </div>
            <p>
                <span class="labelIcon icon-details"></span>
                {{ t('cospend', 'You can cut the link with a Nextcloud user by renaming the member.') }}
            </p>
        </div>
    </div>
</template>

<script>
import { Multiselect, ActionInput } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import cospend from '../state';
import * as constants from '../constants';
import { Timer } from '../utils';

export default {
    name: 'SharingTabSidebar',
    components: {
        Multiselect, ActionInput
    },
    props: ['project'],
    data() {
        return {
            selectedAddUser: null,
            selectedAffectUser: null,
            users: [],
            selectedMember: null,
            newMemberName: '',
            newProjectName: '',
        }
    },
    mounted() {
        if (!this.pageIsPublic) {
            this.asyncFind()
        }
        const input = this.$refs.userMultiselect.$el.querySelector('input');
        input.addEventListener('keyup', e => {
            if (e.key === 'Enter') {
                // trick to add member when pressing enter on NC user multiselect
                this.onMultiselectEnterPressed(e.target);
            } else {
                // add a simple user entry in multiselect when typing
                this.updateSimpleUser(e.target.value);
            }
        });
        // remove simple user when loosing focus
        input.addEventListener('blur', e => {
            this.updateSimpleUser(null);
        });
    },
    computed: {
        maintenerAccess() {
            return this.project.myaccesslevel >= constants.ACCESS.MAINTENER;
        },
        editionAccess() {
            return this.project.myaccesslevel >= constants.ACCESS.PARTICIPANT;
        },
        adminAccess() {
            return this.project.myaccesslevel >= constants.ACCESS.ADMIN;
        },
        myAccessLevel() {
            return this.project.myaccesslevel;
        },
        members() {
            return cospend.members[this.projectId];
        },
        memberList() {
            return this.project.members;
        },
        activatedMembers() {
            const mList = this.memberList;
            const actList = [];
            for (let i = 0; i < mList.length; i++) {
                if (mList[i].activated) {
                    actList.push(mList[i]);
                }
            }
            return actList;
        },
        firstMid() {
            return this.activatedMembers[0].id;
        },
        projectId() {
            return this.project.id;
        },
        isCurrentUser() {
            return (uid) => uid === getCurrentUser().uid
        },
        pageIsPublic() {
            return cospend.pageIsPublic;
        },
        formatedUsersAffect() {
            // avoid simple member here
            return this.unallocatedUsersAffect.map(item => {
                return {
                    user: item.id,
                    name: item.name,
                    displayName: item.label,
                    icon: 'icon-user',
                    type: item.type,
                    value: item.value,
                    multiselectKey: item.type + ':' + item.id,
                };
            })
        },
        unallocatedUsersAffect() {
            const memberList = Object.values(this.members);
            return this.users.filter((user) => {
                let foundIndex;
                foundIndex = memberList.findIndex((member) => {
                    return member.userid === user.id
                })
                if (foundIndex === -1 && user.type === 'u') {
                    return true
                }
                return false
            })
        },
        formatedUsers() {
            return this.unallocatedUsers.map(item => {
                return {
                    user: item.id,
                    name: item.name,
                    displayName: item.label,
                    icon: item.type === 'u' ? 'icon-user' : '',
                    type: item.type,
                    value: item.value,
                    multiselectKey: item.type + ':' + item.id,
                };
            })
        },
        // those not present as member yet
        unallocatedUsers() {
            const memberList = Object.values(this.members);
            return this.users.filter((user) => {
                let foundIndex;
                foundIndex = memberList.findIndex((member) => {
                    return member.userid === user.id
                })
                if (foundIndex === -1) {
                    return true
                }
                return false
            })
        },
    },
    methods: {
        onAutoExportSet(e) {
            this.project.autoexport = e.target.value;
            this.$emit('projectEdited', this.projectId);
        },
        asyncFind() {
            this.isLoading = true
            this.loadUsers();
        },
        loadUsers() {
            const that = this;
            const url = generateUrl('/apps/cospend/user-list');
            $.ajax({
                type: 'GET',
                url: url,
                data: {},
                async: true
            }).done(function(response) {
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
                // add current user
                const cu = getCurrentUser();
                data.push({
                    id: cu.uid,
                    name: cu.displayName,
                    label: (cu.uid !== cu.displayName) ? (cu.displayName + ' (' + cu.uid + ')') : cu.uid,
                    type: 'u',
                });
                that.users = data;
            }).fail(function() {
                showError(t('cospend', 'Failed to get user list.'));
            });
        },
        clickAddUserItem() {
            if (this.selectedAddUser === null) {
                showError(t('cospend', 'Failed to add member.'))
                return;
            }
            if (this.selectedAddUser.type === 'u') {
                this.$emit('userAdded', this.projectId, this.selectedAddUser.name, this.selectedAddUser.user);
            } else {
                this.$emit('newSimpleMember', this.projectId, this.selectedAddUser.name);
            }
            this.asyncFind();
        },
        clickAffectUserItem() {
            const member = this.members[this.selectedMember];
            this.$set(member, 'userid', this.selectedAffectUser.user);
            this.$set(member, 'name', this.selectedAffectUser.name);
            this.$emit('memberEdited', this.projectId, this.selectedMember);
            this.asyncFind();
        },
        onAddMember() {
            this.$emit('newSimpleMember', this.projectId, this.newMemberName);
            this.newMemberName = '';
        },
        onRenameProject() {
            this.project.name = this.newProjectName;
            this.$emit('projectEdited', this.projectId);
            this.newProjectName = '';
        },
        onMultiselectEnterPressed(elem) {
            // this is most likely never triggered because of the fake user
            // we add that will make the multiselect catch the event
            const name = elem.value;
            this.$emit('newSimpleMember', this.projectId, name);
            elem.value = '';
        },
        updateSimpleUser(name) {
            // delete existing simple user
            for (let i = 0; i < this.users.length; i++) {
                if (this.users[i].type === 's') {
                    this.users.splice(i, 1);
                    break;
                }
            }
            // add one
            if (name) {
                this.users.unshift({
                    id: '',
                    name: name,
                    label: name + ' (' + t('cospend', 'Simple member') + ')',
                    type: 's'
                });
            }
        },
    },
}
</script>
<style scoped lang="scss">
#autoExport {
    width: 100%;
}
.labelIcon,
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
.addUserInput {
    width: 100%;
}
#affectDiv {
    display: flex;
}
#affectDiv select {
    margin-top: 0px;
}
#affectDiv select,
.affectUserInput {
    width: 49%;
}
.label {
    margin-bottom: 10px;
}
.renameProject,
.newMember {
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