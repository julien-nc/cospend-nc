<template>
    <div id="billdetail" class="app-content-details">
        <h2 class="bill-title" :style="'background-color: #' + myGetMemberColor(bill.payer_id) + ';'">
            <span v-show="billLoading" class="loading-bill icon-loading-small"></span>
            <span class="icon-edit-white"></span>
            <span>{{ billFormattedTitle }}</span>
            <a v-for="link in billLinks" :key="link" :href="link" target="blank">[🔗 {{ t('cospend', 'link') }}]</a>
            <button id="owerValidate" v-if="isNewBill" @click="onCreateClick"
                :title="t('cospend', 'Press Shift+Enter to validate')"
                style="display: inline-block;">
                <span class="icon-confirm"></span>
                <span id="owerValidateText">{{ createBillButtonText }}</span>
            </button>
        </h2>
        <div class="bill-form">
            <div class="bill-left">
                <div class="bill-what">
                    <label for="what">
                        <a class="icon icon-tag"></a>{{ t('cospend', 'What?') }}
                    </label>
                    <input type="text" id="what" maxlength="300" class="input-bill-what"
                        v-model="bill.what"
                        @input="onBillEdited"
                        :placeholder="t('cospend', 'What is the bill about?')"/>
                </div>
                <button id="addFileLinkButton">
                    <span class="icon-public"></span>{{ t('cospend', 'Attach public link to personal file') }}
                </button>
                <div class="bill-amount">
                    <label for="amount">
                        <a class="icon icon-cospend"></a>{{ t('cospend', 'How much?') }}
                    </label>
                    <input type="number" id="amount" class="input-bill-amount" step="any"
                        :disabled="isNewBill && newBillMode === 'custom'"
                        @input="onBillEdited"
                        ref="amountInput"
                        v-model="bill.amount"/>
                </div>
                <div class="bill-currency-convert" v-if="project.currencyname && project.currencies.length > 0">
                    <label for="bill-currency">
                        <a class="icon icon-currencies"></a>{{ t('cospend', 'Convert to') }}
                    </label>
                    <select id="bill-currency" ref="currencySelect" @change="onCurrencyConvert">
                        <option value="">{{ project.currencyname }}</option>
                        <option v-for="currency in project.currencies" :key="currency.id" :value="currency.id">
                            {{ currency.name }} ⇒ {{ project.currencyname }} (x{{ currency.exchange_rate }})
                        </option>
                    </select>
                </div>
                <div class="bill-payer">
                    <label for="payer"><a class="icon icon-user"></a>{{ t('cospend', 'Who payed?') }}</label>
                    <select id="payer" class="input-bill-payer"
                        v-model="bill.payer_id"
                        @input="onBillEdited"
                        :disabled="!isNewBill && !members[bill.payer_id].activated">
                        <option v-for="member in activatedOrPayer" :key="member.id" :value="member.id"
                            :selected="member.id === bill.payer_id || (isNewBill && currentUser && member.userid === currentUser.uid)">
                            {{ myGetSmartMemberName(member.id) }}
                        </option>
                    </select>
                </div>
                <div class="bill-date">
                    <label for="date"><a class="icon icon-calendar-dark"></a>{{ t('cospend', 'When?') }}</label>
                    <input type="date" id="date" class="input-bill-date" :value="billDate" ref="dateInput" @input="onDateChanged"/>
                </div>
                <div class="bill-time">
                    <label for="time"><a class="icon icon-time"></a>{{ t('cospend', 'What time?') }}</label>
                    <input type="time" id="time" class="input-bill-time" :value="billTime" ref="timeInput" @input="onTimeChanged"/>
                </div>
                <div class="bill-repeat">
                    <label for="repeatbill">
                        <a class="icon icon-play-next"></a>{{ t('cospend', 'Repeat') }}
                    </label>
                    <select id="repeatbill"
                        @input="onBillEdited"
                        v-model="bill.repeat">
                        <option value="n" selected="selected">{{ t('cospend', 'No') }}</option>
                        <option value="d">{{ t('cospend', 'Daily') }}</option>
                        <option value="w">{{ t('cospend', 'Weekly') }}</option>
                        <option value="m">{{ t('cospend', 'Monthly') }}</option>
                        <option value="y">{{ t('cospend', 'Yearly') }}</option>
                    </select>
                </div>
                <div class="bill-repeat-extra" v-if="bill.repeat !== 'n'">
                    <div class="bill-repeat-include">
                        <input id="repeatallactive"
                            @input="onBillEdited"
                            v-model="bill.repeatallactive" class="checkbox" type="checkbox"/>
                        <label for="repeatallactive" class="checkboxlabel">{{ t('cospend', 'Include all active members on repeat') }}</label>
                        <br>
                    </div>
                    <div class="bill-repeat-until">
                        <label for="repeatuntil">
                            <a class="icon icon-pause"></a>{{ t('cospend', 'Repeat until') }}
                        </label>
                        <input type="date" id="repeatuntil"
                            @input="onBillEdited"
                            v-model="bill.repeatuntil" class="input-bill-repeatuntil">
                    </div>
                </div>
                <div class="bill-payment-mode">
                    <label for="payment-mode">
                        <a class="icon icon-tag"></a>{{ t('cospend', 'Payment mode') }}
                    </label>
                    <select id="payment-mode"
                        @input="onBillEdited"
                        v-model="bill.paymentmode">
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
                    <select id="category"
                        @input="onBillEdited"
                        v-model="bill.categoryid">
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
                        @input="onBillEdited"
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
                    <button id="modehintbutton" @click="onHintClick">
                        <span class="icon-details"></span>
                    </button>
                    <div class="modehint modenormal" v-if="newBillMode === 'normal' && showHint">
                        {{ t('cospend', 'Classic mode: Choose a payer, enter a bill amount and select who is concerned by the whole spending, the bill is then split equitably between selected members. Real life example: One person pays the whole restaurant bill and everybody agrees to evenly split the cost.') }}
                    </div>
                    <div class="modehint modeperso" v-else-if="newBillMode === 'perso' && showHint">
                        {{ t('cospend', 'Classic+personal mode: This mode is similar to the classic one. Choose a payer and enter a bill amount corresponding to what was actually payed. Then select who is concerned by the bill and optionally set an amount related to personal stuff for some members. Multiple bills will be created: one for the shared spending and one for each personal part. Real life example: We go shopping, part of what was bought concerns the group but someone also added something personal (like a shirt) which the others don\'t want to collectively pay.') }}
                    </div>
                    <div class="modehint modecustom" v-else-if="newBillMode === 'custom' && showHint">
                        {{ t('cospend', 'Custom mode, uneven split: Choose a payer, ignore the bill amount (which is disabled) and enter a custom owed amount for each member who is concerned. Then press "Create the bills". Multiple bills will be created. Real life example: One person pays the whole restaurant bill but there are big price differences between what each person ate.') }}
                    </div>
                </div>
                <div class="bill-owers">
                    <label class="bill-owers-label">
                        <a class="icon icon-group"></a><span>{{ t('cospend', 'For whom?') }}</span>
                    </label>
                    <div class="owerAllNoneDiv" v-if="newBillMode !== 'custom'">
                        <div class="icon-group"></div>
                        <input id="checkAllNone" type="checkbox" class="checkbox" v-model="selectAllNoneOwers">
                        <label for="checkAllNone" class="checkboxlabel">{{ t('cospend', 'All/None') }}</label>
                    </div>
                    <div v-if="newBillMode === 'normal'">
                        <div v-for="ower in activatedOrOwer" :key="ower.id" class="owerEntry">
                            <div :class="'owerAvatar' + myGetAvatarClass(ower.id)">
                                <div class="disabledMask"></div><img :src="myGetMemberAvatar(ower.id)">
                            </div>
                            <input :id="'dum' + ower.id" :owerid="ower.id"
                                class="checkbox" type="checkbox"
                                :disabled="!members[ower.id].activated"
                                @input="onBillEdited"
                                v-model="bill.owerIds" :value="ower.id" number/>
                            <label :for="'dum' + ower.id" class="checkboxlabel">{{ ower.name }}</label>
                            <label class="spentlabel"
                                v-if="bill.owerIds.includes(ower.id)">
                                ({{ owerAmount[ower.id] }})
                            </label>
                        </div>
                    </div>
                    <div v-else-if="newBillMode === 'perso'">
                        <div v-for="ower in activatedOrOwer" :key="ower.id" class="owerEntry">
                            <div :class="'owerAvatar' + myGetAvatarClass(ower.id)">
                                <div class="disabledMask"></div><img :src="myGetMemberAvatar(ower.id)">
                            </div>
                            <input :id="'dum' + ower.id" :owerid="ower.id"
                                class="checkbox" type="checkbox"
                                v-model="bill.owerIds" :value="ower.id" number/>
                            <label :for="'dum' + ower.id" class="checkboxlabel">{{ ower.name }}</label>
                            <input v-show="bill.owerIds.includes(ower.id)"
                                :ref="'amountdum' + ower.id"
                                class="amountinput" type="number" value="" step="0.01" min="0"/>
                        </div>
                    </div>
                    <div v-else>
                        <div v-for="ower in activatedOrOwer" :key="ower.id" class="owerEntry">
                            <div :class="'owerAvatar' + myGetAvatarClass(ower.id)">
                                <div class="disabledMask"></div><img :src="myGetMemberAvatar(ower.id)">
                            </div>
                            <label :for="'amountdum' + ower.id" class="numberlabel">{{ ower.name }}</label>
                            <input :id="'amountdum' + ower.id"
                                :ref="'amountdum' + ower.id"
                                @change="onCustomAmountChange"
                                class="amountinput" type="number" value="" step="0.01" min="0"/>
                        </div>
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
import {getCategory} from './category';
import {getBills} from './bill';
import {updateProjectBalances} from './project';
import {
    delay,
    generatePublicLinkToFile,
} from './utils';

export default {
    name: 'BillForm',

    components: {
    },

    props: ['bill'],
    data() {
        return {
            projectId: cospend.currentProjectId,
            //bill: cospend.currentBill,
            currentUser: getCurrentUser(),
            newBillMode: 'normal',
            billLoading: false,
            progAmountChange: false,
            showHint: false
        };
    },

    watch: {
        //'bill.amount'(val, oldVal) {
        //    if (!this.progAmountChange) {
        //        this.onAmountChanged();
        //    }
        //    this.progAmountChange = false;
        //},
        //bill: {
        //    handler(val) {
        //        if (!this.isNewBill && !this.noBill) {
        //            this.onBillChanged();
        //        }
        //    },
        //    deep: true
        //}
    },

    computed: {
        selectAllNoneOwers: {
            get() {
                return this.activatedOrOwer ? this.bill.owerIds.length === this.activatedOrOwer.length : false;
            },
            set(value) {
                const that = this;
                const selected = [];

                if (value) {
                    // select all members
                    this.activatedOrOwer.forEach(function (member) {
                        selected.push(member.id);
                    });
                } else {
                    // deselect all members
                    // avoid deselecting disabled ones (add those who are not active and were selected)
                    this.disabledMembers.forEach(function (member) {
                        if (that.bill.owerIds.includes(member.id)) {
                            selected.push(member.id);
                        }
                    });
                }

                this.bill.owerIds = selected;
            }
        },
        owerAmount() {
            const result = {};
            const that = this;
            const amount = parseFloat(this.bill.amount);
            const nbOwers = this.bill.owerIds.length;
            let weightSum = 0;
            let oneWeight, owerVal;
            if (nbOwers > 0 &&
                !isNaN(amount) &&
                amount !== 0.0) {
                this.bill.owerIds.forEach(function(mid) {
                    weightSum += that.members[mid].weight;
                });
                oneWeight = amount / weightSum;
                this.bill.owerIds.forEach(function(mid) {
                    owerVal = oneWeight * that.members[mid].weight;
                    result[mid] = owerVal.toFixed(2);
                });
            }
            return result;
        },
        isNewBill() {
            return (this.bill.id === 0);
        },
        noBill() {
            return (this.bill && this.bill.id === -1);
        },
        project() {
            return cospend.projects[this.projectId];
        },
        billLinks() {
            return this.bill.what.match(/https?:\/\/[^\s]+/gi) || [];
        },
        billFormattedTitle() {
            let paymentmodeChar = '';
            let categoryChar = '';
            if (parseInt(this.bill.categoryid) !== 0) {
                categoryChar = getCategory(this.projectId, this.bill.categoryid).icon + ' ';
            }
            if (this.bill.paymentmode !== 'n') {
                paymentmodeChar = cospend.paymentModes[this.bill.paymentmode].icon + ' ';
            }
            const whatFormatted = paymentmodeChar + categoryChar + this.bill.what.replace(/https?:\/\/[^\s]+/gi, '');
            return t('cospend', 'Bill : {what}', {what: whatFormatted});
        },
        billDate() {
            const billMom = moment.unix(this.bill.timestamp);
            return billMom.format('YYYY-MM-DD');
        },
        billTime() {
            const billMom = moment.unix(this.bill.timestamp);
            return billMom.format('HH:mm');
        },
        members() {
            return cospend.members[this.projectId];
        },
        activatedMembers() {
            const mList = [];
            for (const mid in this.members) {
                if (this.members[mid].activated) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        disabledMembers() {
            const mList = [];
            for (const mid in this.members) {
                if (!this.members[mid].activated) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        activatedOrPayer() {
            const mList = [];
            for (const mid in this.members) {
                if (this.members[mid].activated || parseInt(mid) === this.bill.payer_id) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        activatedOrOwer() {
            const mList = [];
            for (const mid in this.members) {
                if (this.members[mid].activated || this.bill.owerIds.indexOf(parseInt(mid)) !== -1) {
                    mList.push(this.members[mid]);
                }
            }
            return mList;
        },
        categories() {
            return cospend.projects[this.projectId].categories;
        },
        hardCodedCategories() {
            return cospend.hardCodedCategories;
        },
        currencies() {
            return cospend.projects[this.projectId].currencies;
        },
        paymentModes() {
            return cospend.paymentModes;
        },
        createBillButtonText() {
            return this.newBillMode === 'normal' ? t('cospend', 'Create the bill') : t('cospend', 'Create the bills');
        },
    },

    methods: {
        myGetSmartMemberName(mid) {
            let smartName = getSmartMemberName(this.projectId, mid);
            if (smartName === t('cospend', 'You')) {
                smartName += ' (' + this.members[mid].name + ')';
            }
            return smartName;
        },
        myGetAvatarClass(mid) {
            return this.members[mid].activated ? '' : ' owerAvatarDisabled';
        },
        myGetMemberAvatar(mid) {
            return getMemberAvatar(this.projectId, mid);
        },
        myGetMemberColor(mid) {
            if (mid === 0) {
                return '888888';
            } else {
                return this.members[mid].color;
            }
        },
        onDateChanged() {
            this.updateTimestamp();
        },
        onTimeChanged() {
            this.updateTimestamp();
        },
        updateTimestamp() {
            const date = this.$refs.dateInput.value;
            let time = this.$refs.timeInput.value;
            if (!time || time === '') {
                time = '00:00';
            }
            const timestamp = moment(date + ' ' + time).unix();
            this.bill.timestamp = timestamp;
            this.onBillEdited();
        },
        onBillEdited(e) {
            if (e && e.target === this.$refs.amountInput) {
                this.onAmountChanged();
            }
            const that = this;
            if (!this.isNewBill && !this.noBill) {
                delay(function() {
                    that.saveBill();
                }, 2000)();
            }
        },
        isBillValidForSaveOrNormal() {
            return this.basicBillValueCheck() && this.bill.owerIds.length > 0;
        },
        basicBillValueCheck() {
            let valid = true;
            const bill = this.bill;
            if (bill.what === null || bill.what === '') {
                return false;
            }
            const date = this.$refs.dateInput.value;
            if (date === null || date === '' || date.match(/^\d\d\d\d-\d\d-\d\d$/g) === null) {
                return false;
            }
            const time = this.$refs.timeInput.value;
            if (time === null || time === '' || time.match(/^\d\d:\d\d$/g) === null) {
                return false;
            }
            if (bill.amount === '' || isNaN(bill.amount) || isNaN(bill.payer_id)) {
                return false;
            }
            return true;
        },
        saveBill() {
            const that = this;
            if (!this.isBillValidForSaveOrNormal()) {
                Notification.showTemporary(t('cospend', 'Impossible to save bill, invalid values'));
            } else {
                this.billLoading = true;
                const bill = this.bill;
                const req = {
                    what: bill.what,
                    comment: bill.comment,
                    timestamp: bill.timestamp,
                    payer: bill.payer_id,
                    payed_for: bill.owerIds.join(','),
                    amount: bill.amount,
                    repeat: bill.repeat,
                    repeatallactive: bill.repeatallactive ? 1 : 0,
                    repeatuntil: bill.repeatuntil,
                    paymentmode: bill.paymentmode,
                    categoryid: bill.categoryid
                };
                let url;
                if (!cospend.pageIsPublic) {
                    url = generateUrl('/apps/cospend/projects/' + this.projectId +'/bills/' + bill.id);
                } else {
                    url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id);
                }
                $.ajax({
                    type: 'PUT',
                    url: url,
                    data: req,
                    async: true,
                }).done(function() {
                    updateProjectBalances(that.projectId);
                    // to update balances
                    that.$emit('billSaved', that.bill);
                    Notification.showTemporary(t('cospend', 'Bill saved'));
                }).always(function() {
                    that.billLoading = false;
                }).fail(function(response) {
                    Notification.showTemporary(
                        t('cospend', 'Failed to save bill') +
                        ' ' + (response.responseJSON)
                    );
                });
            }
        },
        onCurrencyConvert() {
            let currencyId = this.$refs.currencySelect.value;
            if (currencyId !== '') {
                const userAmount = parseFloat(this.bill.amount);
                currencyId = parseInt(currencyId);
                let currency = null;
                for (let i = 0; i < this.currencies.length; i++) {
                    if (parseInt(this.currencies[i].id) === currencyId) {
                        currency = this.currencies[i];
                        break;
                    }
                }
                this.progAmountChange = true;
                this.bill.amount = parseFloat(this.bill.amount) * currency.exchange_rate;
                this.bill.what = this.cleanStringFromCurrency(this.bill.what) + ' (' + userAmount.toFixed(2) + ' ' + currency.name + ')';
                this.$refs.currencySelect.value = '';
                // convert personal amounts
                if (this.isNewBill && this.newBillMode === 'perso') {
                    const persoParts = this.getPersonalParts();
                    let part;
                    for (const mid in persoParts) {
                        part = persoParts[mid];
                        if (part !== 0.0) {
                            this.$refs['amountdum' + mid][0].value = part * currency.exchange_rate;
                        }
                    }
                }
                if (this.isNewBill && this.newBillMode === 'custom') {
                    const customAmounts = this.getCustomAmounts();
                    let am;
                    for (const mid in customAmounts) {
                        am = customAmounts[mid];
                        if (am !== 0.0) {
                            this.$refs['amountdum' + mid][0].value = am * currency.exchange_rate;
                        }
                    }
                }
                this.onBillEdited();
            }
        },
        cleanStringFromCurrency(str) {
            let currency, re;
            for (let i = 0; i < this.currencies.length; i++) {
                currency = this.currencies[i];
                re = new RegExp(' \\(\\d+\\.?\\d* ' + currency.name + '\\)', 'g');
                str = str.replace(re, '');
            }
            return str;
        },
        onAmountChanged() {
            this.bill.what = this.cleanStringFromCurrency(this.bill.what);
        },
        onHintClick() {
            this.showHint = !this.showHint;
        },
        onCreateClick() {
            if (this.newBillMode === 'normal') {
                this.createNormalBill();
            } else if (this.newBillMode === 'perso') {
                this.createEquiPersoBill();
            } else if (this.newBillMode === 'custom') {
                this.createCustomAmountBill();
            }
        },
        createNormalBill() {
            if (this.isBillValidForSaveOrNormal()) {
                const bill = this.bill;
                this.createBill('normal', bill.what, bill.amount, bill.payer_id, bill.timestamp, bill.owerIds, bill.repeat,
                    bill.paymentmode, bill.categoryid, bill.repeatallactive, bill.repeatuntil, bill.comment);
            } else {
                Notification.showTemporary(t('cospend', 'Bill values are not valid'));
            }
        },
        createEquiPersoBill() {
            if (this.isBillValidForSaveOrNormal()) {
                const bill = this.bill;
                // check if personal parts are valid
                let tmpAmount = parseFloat(this.bill.amount);
                const persoParts = this.getPersonalParts();
                let part;
                for (const mid in persoParts) {
                    part = persoParts[mid];
                    if (!isNaN(part) && part > 0.0) {
                        tmpAmount -= part;
                    }
                }
                if (tmpAmount < 0.0) {
                    Notification.showTemporary(t('cospend', 'Personal parts are bigger than the paid amount'));
                    return;
                }

                // create bills for perso parts
                for (const mid in persoParts) {
                    part = persoParts[mid];
                    if (!isNaN(part) && part !== 0.0) {
                        this.createBill('perso', bill.what, part, bill.payer_id, bill.timestamp, [mid], bill.repeat,
                            bill.paymentmode, bill.categoryid, bill.repeatallactive, bill.repeatuntil, bill.comment);
                    }
                }

                // create main bill
                if (tmpAmount > 0.0) {
                    this.createBill('mainPerso', bill.what, tmpAmount, bill.payer_id, bill.timestamp, bill.owerIds, bill.repeat,
                        bill.paymentmode, bill.categoryid, bill.repeatallactive, bill.repeatuntil, bill.comment);
                }
                this.newBillMode = 'normal';
            } else {
                Notification.showTemporary(t('cospend', 'Bill values are not valid'));
            }
        },
        createCustomAmountBill() {
            if (this.basicBillValueCheck()) {
                const bill = this.bill;
                // check if custom amounts are valid
                const customAmounts = this.getCustomAmounts();
                let total = 0.0;
                for (const mid in customAmounts) {
                    total += customAmounts[mid];
                }
                if (total === 0.0) {
                    Notification.showTemporary(t('cospend', 'There is no custom amount'));
                    return;
                } else {
                    let am;
                    for (const mid in customAmounts) {
                        am = customAmounts[mid];
                        if (am !== 0.0) {
                            this.createBill('custom', bill.what, am, bill.payer_id, bill.timestamp, [mid], bill.repeat,
                                bill.paymentmode, bill.categoryid, bill.repeatallactive, bill.repeatuntil, bill.comment);
                        }
                    }
                }
                this.newBillMode = 'normal';
            } else {
                Notification.showTemporary(t('cospend', 'Bill values are not valid'));
            }
        },
        createBill(mode=null, what=null, amount=null, payer_id=null, timestamp=null, owerIds=null, repeat=null,
                            paymentmode=null, categoryid=null, repeatallactive=null,
                            repeatuntil=null, comment=null) {
            const that = this;
            if (mode === null) {
                mode = that.newBillMode;
            }
            const bill = this.bill;
            const billToCreate = {
                what: what,
                comment: comment,
                timestamp: timestamp,
                payer_id: payer_id,
                owerIds: owerIds,
                amount: amount,
                repeat: repeat,
                repeatallactive: repeatallactive,
                repeatuntil: repeatuntil,
                paymentmode: paymentmode,
                categoryid: categoryid
            };
            const req = {
                what: what,
                comment: comment,
                timestamp: timestamp,
                payer: payer_id,
                payed_for: owerIds.join(','),
                amount: amount,
                repeat: repeat,
                repeatallactive: repeatallactive ? 1 : 0,
                repeatuntil: repeatuntil,
                paymentmode: paymentmode,
                categoryid: categoryid
            };
            this.billLoading = true;
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + this.projectId + '/bills');
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
            }
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                const billid = response;
                // update dict
                // TODO use $set
                //that.$set(cospend.bills[this.projectId], billid, {

                billToCreate.id = billid;
                that.$emit('billCreated', billToCreate, (mode === 'normal' || mode === 'mainPerso'));
                //updateProjectBalances(that.projectId);
                Notification.showTemporary(t('cospend', 'Bill created'));
            }).always(function() {
                that.billLoading = false;
            }).fail(function(response) {
                Notification.showTemporary(
                    t('cospend', 'Failed to create bill') +
                    ': ' + (response.responseJSON.message || response.responseText)
                );
            });
        },
        getPersonalParts() {
            const result = {};
            const that = this;
            let oneWeight, owerVal;
            this.bill.owerIds.forEach(function(mid) {
                result[mid] = parseFloat(that.$refs['amountdum' + mid][0].value) || 0;
            });
            return result;
        },
        getCustomAmounts() {
            const result = {};
            const that = this;
            let oneWeight, owerVal;
            this.activatedOrOwer.forEach(function(member) {
                result[member.id] = parseFloat(that.$refs['amountdum' + member.id][0].value) || 0;
            });
            return result;
        },
        onCustomAmountChange() {
            const customAmounts = this.getCustomAmounts();
            let am;
            let sum = 0;
            for (const mid in customAmounts) {
                am = customAmounts[mid];
                sum += am;
            }
            this.bill.amount = sum;
        },
    }
}
</script>

<style scoped lang="scss">
.bill-left select,
.bill-left textarea,
.bill-left input {
    width: 100%;
}
.bill-form a.icon {
    justify-content: space-between;
    line-height: 44px;
    min-height: 44px;
    padding: 0 12px 0 25px;
}
.bill-left {
    padding: 0px 10px 0px 15px;
    float: left;
    width: 49%;
}
.bill-right {
    float: right;
    width: 49%;
}
.bill-owers label {
    margin-left: 5px;
}
.bill-owers input {
    cursor: pointer;
    padding: 5px;
    min-height: 0px;
}
#owerValidate {
    background-color: #46ba61;
    color: white;
}
.owerAllNoneDiv div {
    display: inline-block;
    width: 24px;
}
.owerAllNoneDiv,
.owerEntry {
    margin-left: 26px;
    margin-right: 00px;
    margin-top: 15px;
    margin-bottom: 15px;
}
.amountinput {
    margin-top: 0px !important;
    margin-bottom: 0px !important;
}
.bill-title {
    text-align: center;
    color: white;
}
</style>