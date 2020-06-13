<template>
    <a href="#" :billid="bill.id" :projectid="projectId"
        :class="'app-content-list-item billitem' + (selected ? ' selectedbill' : '')"
        @click="onItemClick"
        :title="itemTitle">
        <div class="app-content-list-item-icon"
            :style="'background-image: url(' + myGetMemberAvatar(bill.payer_id) + ');'">
            <div class="billItemDisabledMask disabled" v-if="payerDisabled"></div>
            <div class="billItemRepeatMask show" v-if="bill.repeat !== 'n'"></div>
        </div>
        <div class="app-content-list-item-line-one">{{ billFormattedTitle }}</div>
        <div class="app-content-list-item-line-two">{{ parseFloat(bill.amount).toFixed(2) }} ({{ smartPayerName }} → {{ smartOwerNames }})</div>
        <span class="app-content-list-item-details">
            <span class="bill-counter" v-if="selected">{{ counter }}</span>
            <span> {{ billDate }}</span>
        </span>
        <div :class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteBillIcon'"
            v-show="editionAccess" @click="onDeleteClick"></div>
    </a>
</template>

<script>
import cospend from '../state';
import {generateUrl} from '@nextcloud/router';
import {getCategory} from '../category';
import {getSmartOwerNames} from '../bill';
import {getSmartMemberName, getMemberAvatar} from '../member';
import {reload, Timer} from '../utils';

export default {
    name: 'BillItem',

    components: {
    },

    props: ['bill', 'projectId', 'editionAccess', 'index', 'nbbills', 'selected'],
	data: function() {
		return {
			timerOn: false,
			timer: null,
		};
    },

	computed: {
        undoDeleteBillStyle: function() {
            return 'opacity:1; background-image: url(' + generateUrl('/svg/core/actions/history?color=2AB4FF') + ');';
        },
		members: function() {
            return cospend.members[this.projectId];
        },
        payerDisabled: function() {
            return !this.bill.id === 0 && !this.members[this.bill.payer_id].activated;
        },
        billFormattedTitle: function() {
            const links = this.bill.what.match(/https?:\/\/[^\s]+/gi) || [];
            let linkChars = '';
            for (let i = 0; i < links.length; i++) {
                linkChars = linkChars + '  🔗';
            }
            let paymentmodeChar = '';
            let categoryChar = '';
            if (parseInt(this.bill.categoryid) !== 0) {
                categoryChar = getCategory(this.projectId, this.bill.categoryid).icon + ' ';
            }
            if (this.bill.paymentmode && this.bill.paymentmode !== 'n') {
                paymentmodeChar = cospend.paymentModes[this.bill.paymentmode].icon + ' ';
            }
            return paymentmodeChar + categoryChar + this.bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars;
        },
        smartPayerName: function() {
            let memberName = '';
            if (this.bill.payer_id !== 0) {
                memberName = getSmartMemberName(this.projectId, this.bill.payer_id);
            }
            return memberName;
        },
        smartOwerNames: function() {
            const owerIds = this.bill.owerIds;
            // get missing members
            let nbMissingEnabledMembers = 0;
            const missingEnabledMemberIds = [];
            for (const memberid in this.members) {
                if (this.members[memberid].activated &&
                    !owerIds.includes(parseInt(memberid))) {
                    nbMissingEnabledMembers++;
                    missingEnabledMemberIds.push(memberid);
                }
            }

            // 4 cases : all, all except 1, all except 2, custom
            if (nbMissingEnabledMembers === 0) {
                return t('cospend', 'Everyone');
            } else if (nbMissingEnabledMembers === 1 && owerIds.length > 2) {
                const mName = getSmartMemberName(this.projectId, missingEnabledMemberIds[0]);
                return t('cospend', 'Everyone except {member}', {member: mName});
            } else if (nbMissingEnabledMembers === 2 && owerIds.length > 2) {
                const mName1 = getSmartMemberName(this.projectId, missingEnabledMemberIds[0]);
                const mName2 = getSmartMemberName(this.projectId, missingEnabledMemberIds[1]);
                const mName = t('cospend', '{member1} and {member2}', {member1: mName1, member2: mName2})
                return t('cospend', 'Everyone except {member}', {member: mName});
            } else {
                let owerNames = '';
                let mid;
                for (let i = 0; i < owerIds.length; i++) {
                    mid = owerIds[i];
                    if (!this.members.hasOwnProperty(mid)) {
                        reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'));
                        return;
                    }
                    owerNames = owerNames + getSmartMemberName(this.projectId, mid) + ', ';
                }
                owerNames = owerNames.replace(/, $/, '');
                return owerNames;
            }
        },
        billDate: function() {
            const billMom = moment.unix(this.bill.timestamp);
            return billMom.format('YYYY-MM-DD');
        },
        billTime: function() {
            const billMom = moment.unix(this.bill.timestamp);
            return billMom.format('HH:mm');
        },
        itemTitle: function() {
            return this.billFormattedTitle + '\n' + parseFloat(this.bill.amount).toFixed(2) + '\n' +
                this.billDate + ' ' + this.billTime + '\n' + this.smartPayerName + ' → ' + this.smartOwerNames;
        },
        counter: function() {
            return '[' + this.index + '/' + this.nbbills + ']';
        },
    },

    mounted() {
    },

    methods: {
        myGetMemberAvatar: function(mid) {
            return (this.bill.payer_id === 0) ?
                generateUrl('/apps/cospend/getAvatar?name=' + encodeURIComponent(' '))
                : getMemberAvatar(this.projectId, mid);
        },
        onItemClick: function() {
            this.$emit('clicked', this.bill);
        },
		onDeleteClick: function(e) {
            e.stopPropagation();
			if (this.timerOn) {
				this.timerOn = false;
				this.timer.pause();
				delete this.timer;
			} else {
                if (this.bill.id === 0) {
                    this.$emit('delete', this.bill);
                } else {
                    this.timerOn = true;
                    const that = this;
                    this.timer = new Timer(function () {
                        that.timerOn = false;
                        that.$emit('delete', that.bill);
                    }, 3000);
                }
			}
		},
    }
}
</script>

<style scoped lang="scss">

</style>