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
                    placeholder="Plus de détails sur la facture (300 caractères max)">
                </textarea>
            </div>
        </div>
        <!--div class="bill-right">
            <div class="bill-type" style="display: block;">
                <label class="bill-owers-label">
                    <a class="icon icon-toggle-filelist"></a><span>Type de facture</span>
                </label>
                <select id="billtype">
                    <option value="normal" selected="selected">Classique, partage équitable</option>
                    <option value="perso">Partage équitable avec d'éventuelles parties personnelles</option>
                    <option value="custom">Montants personnalisés par membre</option>
                </select>
                <button id="modehintbutton">
                    <span class="icon-details"></span>
                </button>
                <div class="modehint modenormal">Mode classique : Choisissez un payeur, entrez un montant de facture et sélectionnez les membres concernés par la dépense toute entière. La facture est ensuite partagée équitablement entre les membres sélectionnés. Exemple dans la vie réelle : Une personne paye toute l'addition au restaurant et tout le monde est d'accord pour partager équitablement la dépense.</div>
                <div class="modehint modeperso">Mode classique+personnel : Ce mode est similaire au mode classique. Choisissez un payeur et entrez le montant effectivement payé par ce payeur. Ensuite sélectionnez les membres concernés par cette facture et entrez éventuellement le montant dépensé concernant ce membre uniquement. Plusieurs factures vont être créées : une pour la dépense partagée et une pour chaque part personnelle. Exemple dans la vie réelle : Nous allons faire des courses. Une partie de ce qui a été acheté concerne le groupe mais quelqu'un a aussi ajouté un article personnel (comme un vêtement) que les autres ne veulent pas payer collectivement.</div>
                <div class="modehint modecustom">Mode personnalisé, partage non-équitable : Choisissez un payeur, ignorez le montant global de la facture (qui est grisé) et entrez le montant personnalisé dû par chaque membre concerné par la facture. Ensuite appuyez sur "Créer les factures". Plusieurs factures vont être créées. Exemple dans la vie réelle : Une personne paye l'addition au restaurant mais il y a de grosses différences de prix entre ce que chaque personne a consommé.</div>
            </div>
            <div class="bill-owers">
                <label class="bill-owers-label">
                    <a class="icon icon-group"></a><span>Pour qui ?</span>
                </label>
                <div class="owerAllNoneDiv">
                    <button id="owerAll"><span class="icon-group"></span>Tous</button>
                    <button id="owerNone"><span class="icon-disabled-users"></span>Aucun</button>
                </div>
                <div class="owerEntry">
                    <div class="owerAvatar">
                        <div class="disabledMask"></div>
                        <img src="/n19/index.php/apps/cospend/getAvatar?color=93b27b&amp;name=robi">
                    </div>
                    <input id="dum1" owerid="1" class="checkbox" type="checkbox"/>
                    <label for="dum1" class="checkboxlabel">robi</label>
                    <input id="amountdum1" owerid="1" class="amountinput" type="number" value="" step="0.01" min="0">
                    <label for="amountdum1" class="numberlabel">robi</label>
                    <label class="spentlabel"></label>
                </div>
            </div>
        </div-->
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
            currentUser: getCurrentUser()
		};
    },

    computed: {
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
		onDateChanged: function() {
            console.log('dd '+this.bill.what);
        },
		onTimeChanged: function() {
        },
    }
}
</script>

<style scoped lang="scss">

</style>