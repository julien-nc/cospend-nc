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
        <v-table id="settlementTable" :data="transactions">
            <thead slot="head">
                <v-th sortKey="from">{{ t('cospend', 'Who pays?') }}</v-th>
                <v-th sortKey="to">{{ t('cospend', 'To whom?') }}</v-th>
                <v-th sortKey="amount">{{ t('cospend', 'How much?') }}</v-th>
            </thead>
            <tbody slot="body" slot-scope="{displayData}">
                <tr v-for="value in displayData" :key="value.from + value.to">
                    <td :style="'border: 2px solid #' + myGetMemberColor(value.from) + ';'">
                        <div :class="'owerAvatar' + myGetAvatarClass(value.from)">
                            <div class="disabledMask"></div><img :src="myGetMemberAvatar(project.id, value.from)">
                        </div>
                        {{ myGetSmartMemberName(project.id, value.from) }}
                    </td>
                    <td :style="'border: 2px solid #' + myGetMemberColor(value.to) + ';'">
                        <div :class="'owerAvatar' + myGetAvatarClass(value.to)">
                            <div class="disabledMask"></div><img :src="myGetMemberAvatar(project.id, value.to)">
                        </div>
                        {{ myGetSmartMemberName(project.id, value.to) }}
                    </td>
                    <td>{{ value.amount.toFixed(2) }}</td>
                </tr>
            </tbody>
        </v-table>
    </div>
</template>

<script>
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';
import {getMemberName, getSmartMemberName, getMemberAvatar} from './member';
import cospend from './state';

export default {
    name: 'Settlement',

    components: {
    },

	data: function() {
		return {
            project: cospend.projects[cospend.currentProjectId],
            transactions: []
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
            if (parseInt(centeredOn) === 0) {
                centeredOn = null;
            }
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