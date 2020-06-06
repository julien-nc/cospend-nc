<template>
    <div id="settlement-div">
        <div id="center-settle-div">
            <label for="settle-member-center">{{ t('cospend', 'Center settlement on') }}</label>
            <select id="settle-member-center" @change="onChangeCenterMember">
                <option value="0">{{ t('cospend', 'Nobody (optimal)') }}</option>
                <option
                    v-for="(member, mid) in members"
                    :key="mid"
                    :value="mid">
                    {{ member.name }}
                </option>
            </select>
        </div>
        <table id="settlementTable" class="sortable" v-if="transactions">
            <thead>
                <th>{{ t('cospend', 'Who pays?') }}</th>
                <th>{{ t('cospend', 'To whom?') }}</th>
                <th class="sorttable_numeric">{{ t('cospend', 'How much?') }}</th>
            </thead>
            <tbody>
                <tr
                    v-for="transaction in transactions"
                    :key="transaction.from + transaction.to">
                    <td :style="'border: 2px solid #' + myGetMemberColor(transaction.from) + ';'">
                        <div :class="'owerAvatar' + myGetAvatarClass(transaction.from)">
                            <div class="disabledMask"></div><img :src="myGetMemberAvatar(project.id, transaction.from)">
                        </div>
                        {{ myGetSmartMemberName(project.id, transaction.from) }}
                    </td>
                    <td :style="'border: 2px solid #' + myGetMemberColor(transaction.to) + ';'">
                        <div :class="'owerAvatar' + myGetAvatarClass(transaction.to)">
                            <div class="disabledMask"></div><img :src="myGetMemberAvatar(project.id, transaction.to)">
                        </div>
                        {{ myGetSmartMemberName(project.id, transaction.to) }}
                    </td>
                    <td>{{ transaction.amount.toFixed(2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import {getMemberName, getSmartMemberName, getMemberAvatar} from './member';
import cospend from './state';

export default {
    name: 'Settlement.vue',

    components: {

    },

	data: function() {
		return {
            project: cospend.projects[cospend.currentProjectId],
            transactions: null
		};
    },

	computed: {
		members: function() {
            return cospend.members[this.project.id];
        }
    },

    mounted() {
        this.getSettlement();
    },

    methods: {
        myGetAvatarClass(mid) {
            return this.members[mid].activated ? '' : ' owerAvatarDisabled';
        },
        myGetSmartMemberName: function(pid, mid) {
            return getSmartMemberName(pid, mid);
        },
        myGetMemberAvatar: function(pid, mid) {
            return getMemberAvatar(pid, mid);
        },
        myGetMemberColor: function(mid) {
            return this.members[mid].color;
        },
        onChangeCenterMember: function(e) {
            this.getSettlement(e.target.value);
        },
        getSettlement: function(centeredOn=null) {
            const that = this;
            const req = {
                centeredOn: centeredOn
            };
            let url, type;
            if (!cospend.pageIsPublic) {
                req.projectid = this.project.id;
                type = 'POST';
                url = generateUrl('/apps/cospend/getSettlement');
            } else {
                type = 'GET';
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/settle');
            }
            $.ajax({
                type: type,
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                //if (cospend.currentProjectId !== that.project.id) {
                //    selectProject($('.projectitem[projectid="' + projectid + '"]'));
                //}
                //displaySettlement(projectid, response, centeredOn);
                that.transactions = response;
            }).always(function() {
            }).fail(function() {
                that.transactions = null;
                Notification.showTemporary(t('cospend', 'Failed to get settlement'));
            });
        }
    }
}
</script>

<style scoped lang="scss">

</style>