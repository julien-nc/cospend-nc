<template>
    <div>
        <br/>
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
            <p class="label">
                <span class="labelIcon icon-user"></span>
                {{ t('cospend', 'Choose a Nextcloud user to create a project member.')}}
            </p>
            <Multiselect
                v-if="maintenerAccess"
                v-model="selectedAddUser"
                class="addUserInput"
                :placeholder="t('cospend', 'Add a Nextcloud user as a member')"
                :options="formatedUsers"
                :user-select="true"
                label="displayName"
                track-by="multiselectKey"
                :internal-search="true"
                @input="clickAddUserItem"
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
                    :options="formatedUsers"
                    :user-select="true"
                    label="displayName"
                    track-by="multiselectKey"
                    :internal-search="true"
                    @input="clickAffectUserItem"
                    />
            </div>
        </div>
    </div>
</template>

<script>
import { Multiselect} from '@nextcloud/vue'
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
        Multiselect,
    },
    props: ['project'],
    data() {
        return {
            selectedAddUser: null,
            selectedAffectUser: null,
            users: [],
            selectedMember: null
        }
    },
    mounted() {
        if (!this.pageIsPublic) {
            this.asyncFind()
        }
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
        formatedUsers() {
            return this.unallocatedUsers.map(item => {
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
                that.users = data;
            }).fail(function() {
                showError(t('cospend', 'Failed to get user list.'));
            });
        },
        clickAddUserItem() {
            this.$emit('userAdded', this.projectId, this.selectedAddUser.name, this.selectedAddUser.user);
        },
        clickAffectUserItem() {
            const member = this.members[this.selectedMember];
            this.$set(member, 'userid', this.selectedAffectUser.user);
            this.$set(member, 'name', this.selectedAffectUser.name);
            this.$emit('memberEdited', this.projectId, this.selectedMember);
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
</style>