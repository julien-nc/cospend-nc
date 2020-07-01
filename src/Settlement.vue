<template>
    <div id="billdetail" class="app-content-details">
        <h2 id="settlementTitle">
            <span class="icon-reimburse"></span>
            {{ t('cospend', 'Settlement of project {name}', {name: project.name}) }}
            <button class="exportSettlement" @click="onExportClick">
                <span class="icon-save"></span>
                {{ t('cospend', 'Export') }}
            </button>
            <button class="autoSettlement" v-if="editionAccess" @click="onAutoSettleClick">
                <span class="icon-add"></span>
                {{ t('cospend', 'Add these payments to project') }}
            </button>
        </h2>
        <div id="center-settle-div">
            <label for="settle-member-center">{{ t('cospend', 'Center settlement on') }}</label>
            <select id="settle-member-center" v-model="centeredOn" @change="onChangeCenterMember">
                <option value="0">{{ t('cospend', 'Nobody (optimal)') }}</option>
                <option
                    v-for="(member, mid) in members"
                    :key="mid"
                    :value="mid">
                    {{ member.name }}
                </option>
            </select>
        </div>
        <v-table id="settlementTable" v-if="transactions"
            :data="transactions">
            <thead slot="head">
                <v-th sortKey="from">{{ t('cospend', 'Who pays?') }}</v-th>
                <v-th sortKey="to">{{ t('cospend', 'To whom?') }}</v-th>
                <v-th sortKey="amount">{{ t('cospend', 'How much?') }}</v-th>
            </thead>
            <tbody slot="body" slot-scope="{displayData}">
                <tr v-for="value in displayData" :key="value.from + ':' + value.to">
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
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import {getMemberName, getSmartMemberName, getMemberAvatar} from './utils';
import cospend from './state';
import * as constants from './constants';
import * as network from './network';

export default {
    name: 'Settlement',

    components: {
    },

    props: ['projectId'],
    data() {
        return {
            transactions: [],
            centeredOn: 0
        };
    },

    computed: {
        project() {
            return cospend.projects[this.projectId];
        },
        members() {
            return cospend.members[this.projectId];
        },
        editionAccess() {
            return (this.project.myaccesslevel >= constants.ACCESS.PARTICIPANT);
        },
    },

    mounted() {
        this.getSettlement();
    },

    watch: {
        projectId() {
            this.transactions = [];
            this.centeredOn = 0;
            this.getSettlement();
        }
    },

    methods: {
        myGetAvatarClass(mid) {
            return this.members[mid].activated ? '' : ' owerAvatarDisabled';
        },
        myGetSmartMemberName(pid, mid) {
            return getSmartMemberName(pid, mid);
        },
        myGetMemberAvatar(pid, mid) {
            return getMemberAvatar(pid, mid);
        },
        myGetMemberColor(mid) {
            return this.members[mid].color;
        },
        onChangeCenterMember(e) {
            this.getSettlement(e.target.value);
        },
        getSettlement(centeredOn=null) {
            network.getSettlement(this.project.id, centeredOn, this.getSettlementSuccess, this.getSettlementFail);
        },
        getSettlementSuccess(response) {
            if (Array.isArray(response) && response.length === 0) {
                response = null;
            }
            this.transactions = response;
        },
        getSettlementFail() {
            this.transactions = null;
        },
        onExportClick() {
            this.exportSettlement();
        },
        onAutoSettleClick() {
            this.autoSettlement();
        },
        autoSettlement() {
            network.autoSettlement(this.projectId, this.centeredOn, this.autoSettlementSuccess);
        },
        autoSettlementSuccess() {
            this.$emit('autoSettled', this.projectId)
            showSuccess(t('cospend', 'Project settlement bills added.'));
            this.transactions = [];
            this.centeredOn = 0;
        },
        exportSettlement() {
            network.exportSettlement(this.projectId, this.centeredOn, this.exportSettlementSuccess);
        },
        exportSettlementSuccess(response) {
            showSuccess(t('cospend', 'Project settlement exported in {path}', {path: response.path}));
        },
    }
}
</script>

<style scoped lang="scss">
#center-settle-div {
    display: flex;
    justify-content: center;
    align-items: center;
}
#center-settle-div label {
    margin-right: 5px;
}
</style>