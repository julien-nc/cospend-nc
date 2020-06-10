<template>
    <div class="bill-form">
        <div class="bill-left">
            <div class="bill-what">
                <label for="what">
                    <a class="icon icon-tag"></a>{{ t('cospend', 'What?') }}
                </label>
                <input type="text" id="what" maxlength="300" class="input-bill-what"
                    v-model="bill.what"
                    :placeholder="t('cospend', 'What is the bill about?')"/>
            </div>
            <button id="addFileLinkButton">
                <span class="icon-public"></span>{{ t('cospend', 'Attach public link to personal file') }}
            </button>
            <div class="bill-amount">
                <label for="amount">
                    <a class="icon icon-cospend"></a>{{ t('cospend', 'How much?') }}
                </label>
                <input type="number" id="amount" class="input-bill-amount" v-model="bill.amount" step="any"/>
            </div>
            <div class="bill-currency-convert" v-if="project.currencyname && project.currencies.length > 0">
                <label for="bill-currency">
                    <a class="icon icon-currencies"></a>{{ t('cospend', 'Convert to') }}
                </label>
                <select id="bill-currency">
                    <option value="">{{ project.currencyname }}</option>
                    <option v-for="currency in project.currencies" :key="currency.id" :value="currency.id">
                        {{ currency.name }} ⇒ {{ project.currencyname }} (x{{ currency.exchange_rate }})
                    </option>
                </select>
            </div>
            <div class="bill-payer">
                <label for="payer"><a class="icon icon-user"></a>{{ t('cospend', 'Who payed?') }}</label>
                <select id="payer" class="input-bill-payer"
                    :disabled="!isNewBill && !members[bill.payer_id].activated">
                    <option v-for="member in activatedOrPayer" :key="member.id" :value="member.id"
                        :selected="member.id === bill.payer_id || (isNewBill && currentUser && member.userid === currentUser.uid)">
                        {{ myGetSmartMemberName(member.id) }}
                    </option>
                </select>
            </div>
            <div class="bill-date">
                <label for="date"><a class="icon icon-calendar-dark"></a>{{ t('cospend', 'When?') }}</label>
                <input type="date" id="date" class="input-bill-date" :value="billDate" @change="onDateChanged"/>
            </div>
            <div class="bill-time">
                <label for="time"><a class="icon icon-time"></a>{{ t('cospend', 'What time?') }}</label>
                <input type="time" id="time" class="input-bill-time" :value="billTime" @change="onTimeChanged"/>
            </div>
            <div class="bill-repeat">
                <label for="repeatbill">
                    <a class="icon icon-play-next"></a>{{ t('cospend', 'Repeat') }}
                </label>
                <select id="repeatbill" v-model="bill.repeat">
                    <option value="n" selected="selected">{{ t('cospend', 'No') }}</option>
                    <option value="d">{{ t('cospend', 'Daily') }}</option>
                    <option value="w">{{ t('cospend', 'Weekly') }}</option>
                    <option value="m">{{ t('cospend', 'Monthly') }}</option>
                    <option value="y">{{ t('cospend', 'Yearly') }}</option>
                </select>
            </div>
            <div class="bill-repeat-extra" v-if="bill.repeat !== 'n'">
                <div class="bill-repeat-include">
                    <input id="repeatallactive" class="checkbox" type="checkbox"/>
                    <label for="repeatallactive" class="checkboxlabel">{{ t('cospend', 'Include all active members on repeat') }}</label>
                    <br>
                </div>
                <div class="bill-repeat-until">
                    <label for="repeatuntil">
                        <a class="icon icon-pause"></a>{{ t('cospend', 'Repeat until') }}
                    </label>
                    <input type="date" id="repeatuntil" class="input-bill-repeatuntil">
                </div>
            </div>
            <div class="bill-payment-mode">
                <label for="payment-mode">
                    <a class="icon icon-tag"></a>{{ t('cospend', 'Payment mode') }}
                </label>
                <select id="payment-mode" v-model="bill.paymentmode">
                    <option value="n">{{ t('cospend', 'None') }}</option>
                    <option
                        v-for="(pm, id) in paymentModes"
                        :key="id"
                        :value="id">
                        {{ pm.icon + ' ' + pm.name }}
                    </option>
                </select>
            </div>
            <div class="bill-category">
                <label for="category">
                    <a class="icon icon-category-app-bundles"></a>{{ t('cospend', 'Category') }}
                </label>
                <select id="category" v-model="bill.categoryid">
                    <option value="0">{{ t('cospend', 'All') }}</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id">
                        {{ category.icon + ' ' + category.name }}
                    </option>
                    <option
                        v-for="(category, catid) in hardCodedCategories"
                        :key="catid"
                        :value="catid">
                        {{ category.icon + ' ' + category.name }}
                    </option>
                </select>
            </div>
            <div class="bill-comment">
                <label for="comment">
                    <a class="icon icon-comment"></a>{{ t('cospend', 'Comment') }}
                </label>
                <textarea id="comment" maxlength="300" class="input-bill-comment" v-model="bill.comment"
                    :placeholder="t('cospend', 'More details about the bill (300 char. max)')">
                </textarea>
            </div>
        </div>
        <div class="bill-right">
            <div class="bill-type" v-if="isNewBill">
                <label class="bill-owers-label">
                    <a class="icon icon-toggle-filelist"></a><span>{{ t('cospend', 'Bill type') }}</span>
                </label>
                <select id="billtype" v-model="newBillMode">
                    <option value="normal" :selected="true">{{ t('cospend', 'Classic, even split') }}</option>
                    <option value="perso">{{ t('cospend', 'Even split with optional personal parts') }}</option>
                    <option value="custom">{{ t('cospend', 'Custom owed amount per member') }}</option>
                </select>
                <button id="modehintbutton">
                    <span class="icon-details"></span>
                </button>
                <div class="modehint modenormal">{{ t('cospend', 'Classic mode: Choose a payer, enter a bill amount and select who is concerned by the whole spending, the bill is then split equitably between selected members. Real life example: One person pays the whole restaurant bill and everybody agrees to evenly split the cost.') }}</div>
                <div class="modehint modeperso">{{ t('cospend', 'Classic+personal mode: This mode is similar to the classic one. Choose a payer and enter a bill amount corresponding to what was actually payed. Then select who is concerned by the bill and optionally set an amount related to personal stuff for some members. Multiple bills will be created: one for the shared spending and one for each personal part. Real life example: We go shopping, part of what was bought concerns the group but someone also added something personal (like a shirt) which the others don\'t want to collectively pay.') }}</div>
                <div class="modehint modecustom">{{ t('cospend', 'Custom mode, uneven split: Choose a payer, ignore the bill amount (which is disabled) and enter a custom owed amount for each member who is concerned. Then press "Create the bills". Multiple bills will be created. Real life example: One person pays the whole restaurant bill but there are big price differences between what each person ate.') }}</div>
            </div>
            <div class="bill-owers">
                <label class="bill-owers-label">
                    <a class="icon icon-group"></a><span>{{ t('cospend', 'For whom?') }}</span>
                </label>
                <div class="owerAllNoneDiv" v-if="newBillMode !== 'custom'">
                    <button id="owerAll"><span class="icon-group"></span> {{ t('cospend', 'All') }}</button>
                    <button id="owerNone"><span class="icon-disabled-users"></span> {{ t('cospend', 'None') }}</button>
                </div>
                <div v-if="newBillMode === 'normal'">
                    <div v-for="ower in activatedOrOwer" :key="ower.id" class="owerEntry">
                        <div :class="'owerAvatar' + myGetAvatarClass(ower.id)">
                            <div class="disabledMask"></div>
                            <img :src="myGetMemberAvatar(ower.id)">
                        </div>
                        <input :id="'dum' + ower.id" :owerid="ower.id"
                            @click="onNormalOwerCheck($event, ower.id)"
                            :checked="checkedOwers[ower.id]"
                            class="checkbox" type="checkbox"/>
                        <label :for="'dum' + ower.id" class="checkboxlabel">{{ ower.name }}</label>
                        <label class="spentlabel"></label>
                    </div>
                </div>
                <div v-else-if="newBillMode === 'perso'">
                    <div v-for="ower in activatedOrOwer" :key="ower.id" class="owerEntry">
                        <div :class="'owerAvatar' + myGetAvatarClass(ower.id)">
                            <div class="disabledMask"></div>
                            <img :src="myGetMemberAvatar(ower.id)">
                        </div>
                        <input :id="'dum' + ower.id" :owerid="ower.id"
                            @click="onPersoOwerCheck($event, ower.id)"
                            :checked="checkedOwers[ower.id]"
                            class="checkbox" type="checkbox"/>
                        <label :for="'dum' + ower.id" class="checkboxlabel">{{ ower.name }}</label>
                        <input v-show="checkedOwers[ower.id]"
                            :ref="'amountdum' + ower.id"
                            :id="'amountdum' + ower.id"
                            :owerid="ower.id"
                            class="amountinput" type="number" value="" step="0.01" min="0"/>
                    </div>
                </div>
                <div v-else>
                    <div v-for="ower in activatedOrOwer" :key="ower.id" class="owerEntry">
                        <div :class="'owerAvatar' + myGetAvatarClass(ower.id)">
                            <div class="disabledMask"></div>
                            <img :src="myGetMemberAvatar(ower.id)">
                        </div>
                        <label :for="'amountdum' + ower.id" class="numberlabel">{{ ower.name }}</label>
                        <input :id="'amountdum' + ower.id" :owerid="ower.id" class="amountinput" type="number" value="" step="0.01" min="0"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import cospend from './state';
import {generateUrl} from '@nextcloud/router';
import {getCurrentUser} from '@nextcloud/auth';
import * as Notification from './notification';
import * as constants from './constants';
import {getMemberName, getSmartMemberName, getMemberAvatar} from './member';

export default {
    name: 'BillForm',

    components: {
    },

    data: function() {
        return {
            projectId: cospend.currentProjectId,
            billId: cospend.currentBillId,
            currentUser: getCurrentUser(),
            newBillMode: 'normal',
        };
    },

    computed: {
        checkedOwers: function() {
            return {};
        },
        isNewBill: function() {
            return (this.billId === 0);
        },
        project: function() {
            return cospend.projects[this.projectId];
        },
        bill: function() {
            if (this.isNewBill) {
                return {
                    id: 0,
                    what: '',
                    timestamp: moment().unix(),
                    amount: 0.0,
                    payer_id: 0,
                    repeat: 'n',
                    owers: [],
                    paymentmode: 'n',
                    categoryid: 0,
                    comment: ''
                };
            } else {
                return cospend.bills[this.projectId][this.billId];
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
        members: function() {
            return cospend.members[this.projectId];
        },
        activatedMembers: function() {
            const mList = [];
            for (const mid in this.members) {
                if (this.members[mid].activated) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        activatedOrPayer: function() {
            const mList = [];
            for (const mid in this.members) {
                if (this.members[mid].activated || parseInt(mid) === this.bill.payer_id) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        activatedOrOwer: function() {
            const mList = [];
            for (const mid in this.members) {
                if (this.members[mid].activated || this.owerIds.indexOf(parseInt(mid)) !== -1) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        owerIds: function() {
            const owerIds = [];
            for (const i = 0; i < this.bill.owers.length; i++) {
                owerIds.push(this.bill.owers[i].id);
            }
            return owerIds;
        },
        categories: function() {
            return cospend.projects[this.projectId].categories;
        },
        hardCodedCategories: function() {
            return cospend.hardCodedCategories;
        },
        currencies: function() {
            return cospend.projects[this.projectId].currencies;
        },
        paymentModes: function() {
            return cospend.paymentModes;
        },
    },

    methods: {
        myGetSmartMemberName: function(mid) {
            let smartName = getSmartMemberName(this.projectId, mid);
            if (smartName === t('cospend', 'You')) {
                smartName += ' (' + this.members[mid].name + ')';
            }
            return smartName;
        },
        myGetAvatarClass: function(mid) {
            return this.members[mid].activated ? '' : ' owerAvatarDisabled';
        },
        myGetMemberAvatar: function(mid) {
            return getMemberAvatar(this.projectId, mid);
        },
        onDateChanged: function() {
            console.log('dd '+this.bill.what);
            // TODO set prop bill date
        },
        onTimeChanged: function() {
            // TODO set prop bill date
        },
        onNormalOwerCheck: function(e, owerId) {
            this.checkedOwers[owerId] = e.target.checked;
        },
        onPersoOwerCheck: function(e, owerId) {
            this.checkedOwers[owerId] = e.target.checked;
            if (e.target.checked) {
                this.$refs['amountdum' + owerId][0].style.removeProperty('display');
            } else {
                this.$refs['amountdum' + owerId][0].style.display = 'none';
            }
        },
    }
}
</script>

<style scoped lang="scss">

</style>