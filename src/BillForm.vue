<template>
	<AppContentDetails class="bill-form-content">
		<h2 class="bill-title">
			<div class="billFormAvatar">
				<ColoredAvatar
					class="itemAvatar"
					:color="payerColor"
					:size="50"
					:disable-menu="true"
					:disable-tooltip="true"
					:show-user-status="false"
					:icon-class="billLoading ? 'icon-loading' : undefined"
					:is-no-user="payerUserId === ''"
					:user="payerUserId"
					:display-name="payerName" />
				<div v-if="payerDisabled" class="disabledMask" />
			</div>
			<span>
				{{ billFormattedTitle }}
			</span>
			<a v-for="link in billLinks"
				:key="link"
				:href="link"
				target="blank">
				[🔗 {{ t('cospend', 'link') }}]
			</a>
			<button
				v-if="isNewBill"
				id="owerValidate"
				style="display: inline-block;"
				:title="t('cospend', 'Press Shift+Enter to validate')"
				@click="onCreateClick">
				<span class="icon-confirm" />
				<span id="owerValidateText">
					{{ createBillButtonText }}
				</span>
			</button>
		</h2>
		<div class="bill-form">
			<div class="bill-left">
				<div class="bill-what">
					<label for="what">
						<a class="icon icon-tag" />{{ t('cospend', 'What?') }}
					</label>
					<input
						id="what"
						v-model="myBill.what"
						type="text"
						maxlength="300"
						class="input-bill-what"
						:readonly="!editionAccess"
						:placeholder="t('cospend', 'What is the bill about?')"
						@input="onBillEdited"
						@focus="$event.target.select()">
				</div>
				<div v-if="!pageIsPublic"
					class="bill-link-button">
					<div />
					<button id="addFileLinkButton" @click="onGeneratePubLinkClick">
						<span class="icon-public" />{{ t('cospend', 'Attach public link to personal file') }}
					</button>
				</div>
				<div class="bill-amount">
					<label for="amount">
						<a class="icon icon-cospend" />{{ t('cospend', 'How much?') }}
					</label>
					<div class="field-with-info">
						<input
							id="amount"
							v-model="uiAmount"
							type="text"
							class="input-bill-amount"
							:disabled="isNewBill && newBillMode === 'custom'"
							:readonly="!editionAccess"
							@input="onAmountChanged"
							@keyup.enter="onAmountEnterPressed"
							@focus="$event.target.select()">
						<button
							v-tooltip.top="{ content: t('cospend', 'More information') }"
							class="icon-info infoButton"
							@click="onAmountInfoClicked" />
					</div>
				</div>
				<div
					v-if="project.currencyname && project.currencies.length > 0 && editionAccess"
					class="bill-currency-convert">
					<label for="bill-currency">
						<a class="icon icon-currencies" />{{ t('cospend', 'Convert to') }}
					</label>
					<div class="field-with-info">
						<select id="bill-currency" ref="currencySelect" @change="onCurrencyConvert">
							<option value="">
								{{ project.currencyname }}
							</option>
							<option v-for="currency in project.currencies"
								:key="currency.id"
								:value="currency.id">
								{{ currency.name }} ⇒ {{ project.currencyname }} (x{{ currency.exchange_rate }})
							</option>
						</select>
						<button
							v-tooltip.top="{ content: t('cospend', 'More information') }"
							class="icon-info infoButton"
							@click="onConvertInfoClicked" />
					</div>
				</div>
				<div class="bill-payer">
					<label for="payer"><a class="icon icon-user" />{{ t('cospend', 'Who payed?') }}</label>
					<select
						id="payer"
						v-model="myBill.payer_id"
						class="input-bill-payer"
						:disabled="!editionAccess || (!isNewBill && !members[myBill.payer_id].activated)"
						@input="onBillEdited">
						<option v-for="member in activatedOrPayer"
							:key="member.id"
							:value="member.id"
							:selected="member.id === myBill.payer_id || (isNewBill && currentUser && member.userid === currentUser.uid)">
							{{ myGetSmartMemberName(member.id) }}
						</option>
					</select>
				</div>
				<div class="bill-date">
					<label for="date"><a class="icon icon-calendar-dark" />{{ t('cospend', 'When?') }}</label>
					<DatetimePicker v-model="billDatetime"
						class="datetime-picker"
						type="datetime"
						:placeholder="t('cospend', 'When?')"
						:minute-step="5"
						:show-second="false"
						:formatter="format"
						:disabled="!editionAccess"
						confirm />
				</div>
				<div class="bill-payment-mode">
					<label for="payment-mode">
						<a class="icon icon-tag" />{{ t('cospend', 'Payment mode') }}
					</label>
					<select
						id="payment-mode"
						v-model="myBill.paymentmode"
						:disabled="!editionAccess"
						@input="onBillEdited">
						<option value="n">
							{{ t('cospend', 'None') }}
						</option>
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
						<a class="icon icon-category-app-bundles" />{{ t('cospend', 'Category') }}
					</label>
					<select
						id="category"
						v-model="myBill.categoryid"
						:disabled="!editionAccess"
						@input="onBillEdited">
						<option value="0">
							{{ t('cospend', 'None') }}
						</option>
						<option
							v-for="category in sortedCategories"
							:key="category.id"
							:value="category.id">
							{{ category.icon + ' ' + category.name }}
						</option>
					</select>
				</div>
				<div class="bill-comment">
					<label for="comment">
						<a class="icon icon-comment" />{{ t('cospend', 'Comment') }}
					</label>
					<textarea
						id="comment"
						v-model="myBill.comment"
						maxlength="300"
						class="input-bill-comment"
						:readonly="!editionAccess"
						:placeholder="t('cospend', 'More details about the bill (300 char. max)')"
						@input="onBillEdited" />
				</div>
				<div class="bill-repeat">
					<label for="repeatbill">
						<a class="icon icon-play-next" />{{ t('cospend', 'Repeat') }}
					</label>
					<div class="field-with-info">
						<select
							id="repeatbill"
							v-model="myBill.repeat"
							:disabled="!editionAccess"
							@input="onBillEdited">
							<option value="n" selected="selected">
								{{ t('cospend', 'No') }}
							</option>
							<option value="d">
								{{ t('cospend', 'Daily') }}
							</option>
							<option value="w">
								{{ t('cospend', 'Weekly') }}
							</option>
							<option value="b">
								{{ t('cospend', 'Bi-weekly (every 2 weeks)') }}
							</option>
							<option value="s">
								{{ t('cospend', 'Semi-monthly (twice a month)') }}
							</option>
							<option value="m">
								{{ t('cospend', 'Monthly') }}
							</option>
							<option value="y">
								{{ t('cospend', 'Yearly') }}
							</option>
						</select>
						<button
							v-tooltip.top="{ content: t('cospend', 'More information') }"
							class="icon-info infoButton"
							@click="onRepeatInfoClicked" />
					</div>
				</div>
				<div v-if="myBill.repeat !== 'n'"
					class="bill-repeat-extra">
					<div class="bill-repeat-include">
						<input
							id="repeatallactive"
							v-model="myBill.repeatallactive"
							class="checkbox"
							type="checkbox"
							:disabled="!editionAccess"
							@input="onBillEdited">
						<label for="repeatallactive" class="checkboxlabel">
							{{ t('cospend', 'Include all active members on repeat') }}
						</label>
						<br>
					</div>
					<div class="bill-repeat-until">
						<label for="repeatuntil">
							<a class="icon icon-pause" />{{ t('cospend', 'Repeat until') }}
						</label>
						<input
							id="repeatuntil"
							v-model="myBill.repeatuntil"
							type="date"
							class="input-bill-repeatuntil"
							:readonly="!editionAccess"
							@input="onBillEdited">
					</div>
					<div v-if="editionAccess && !isNewBill"
						class="bill-repeat-until">
						<label />
						<button
							class="repeat-now"
							@click="$emit('repeat-bill-now', myBill.id)">
							<span class="icon icon-play-next" />
							{{ t('cospend', 'Repeat now') }}
						</button>
					</div>
				</div>
			</div>
			<div class="bill-right">
				<div v-if="isNewBill"
					class="bill-type">
					<label class="bill-owers-label">
						<a class="icon icon-toggle-filelist" /><span>{{ t('cospend', 'Bill type') }}</span>
					</label><br>
					<select
						id="billtype"
						v-model="newBillMode">
						<option value="normal" :selected="true">
							{{ t('cospend', 'Classic, even split') }}
						</option>
						<option value="perso">
							{{ t('cospend', 'Even split with optional personal parts') }}
						</option>
						<option value="custom">
							{{ t('cospend', 'Custom owed amount per member') }}
						</option>
						<option value="customShare">
							{{ t('cospend', 'Custom share per member') }}
						</option>
					</select>
					<button id="modehintbutton" @click="onHintClick">
						<span class="icon-details" />
					</button>
					<div v-if="isNewBill && newBillMode === 'customShare'" class="checkbox-line">
						<input
							id="ignore-weights"
							class="checkbox"
							type="checkbox"
							:checked="ignoreWeights"
							@input="onIgnoreWeightsChange">
						<label for="ignore-weights" class="checkboxlabel">
							{{ t('cospend', 'Ignore member weights') }}
						</label>
					</div>
					<transition name="fade">
						<div v-if="newBillMode === 'normal' && showHint"
							class="modehint">
							{{ t('cospend', 'Classic mode: Choose a payer, enter a bill amount and select who is concerned by the whole spending, the bill is then split equitably between selected members. Real life example: One person pays the whole restaurant bill and everybody agrees to evenly split the cost.') }}
						</div>
						<div v-else-if="newBillMode === 'perso' && showHint"
							class="modehint">
							{{ t('cospend', 'Classic+personal mode: This mode is similar to the classic one. Choose a payer and enter a bill amount corresponding to what was actually payed. Then select who is concerned by the bill and optionally set an amount related to personal stuff for some members. Multiple bills will be created: one for the shared spending and one for each personal part. Real life example: We go shopping, part of what was bought concerns the group but someone also added something personal (like a shirt) which the others don\'t want to collectively pay.') }}
						</div>
						<div v-else-if="newBillMode === 'custom' && showHint"
							class="modehint">
							{{ t('cospend', 'Custom mode, uneven split: Choose a payer, ignore the bill amount (which is disabled) and enter a custom owed amount for each member who is concerned. Then press "Create the bills". Multiple bills will be created. Real life example: One person pays the whole restaurant bill but there are big price differences between what each person ate.') }}
						</div>
						<div v-else-if="newBillMode === 'customShare' && showHint"
							class="modehint">
							{{ t('cospend', 'Custom share mode, uneven split: Choose a payer, set the bill amount and enter a custom share number for each member who is concerned. Then press "Create the bills". Multiple bills will be created. Real life example: Someone pays an electricity bill for the month but one member was only there during half the month. This member should then pay half a share (0.5) while the others pay a full share (1).') }}
						</div>
					</transition>
				</div>
				<div class="bill-owers">
					<label class="bill-owers-label">
						<a class="icon icon-group" /><span>{{ t('cospend', 'For whom?') }}</span>
					</label>
					<div v-if="!['custom', 'customShare'].includes(newBillMode)"
						class="owerAllNoneDiv">
						<div />
						<input
							id="checkAllNone"
							v-model="selectAllNoneOwers"
							type="checkbox"
							class="checkbox"
							:disabled="!editionAccess"
							@input="onBillEdited">
						<label for="checkAllNone" class="checkboxlabel">{{ t('cospend', 'All/None') }}</label>
					</div>
					<div v-if="!isNewBill || newBillMode === 'normal'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<div class="owerAvatar">
								<ColoredAvatar
									class="itemAvatar"
									:color="getMemberColor(ower.id)"
									:size="24"
									:disable-menu="true"
									:disable-tooltip="true"
									:show-user-status="false"
									:is-no-user="getMemberUserId(ower.id) === ''"
									:user="getMemberUserId(ower.id)"
									:display-name="getMemberName(ower.id)" />
								<div v-if="isMemberDisabled(ower.id)" class="disabledMask" />
							</div>
							<input
								:id="'dum' + ower.id"
								v-model="myBill.owerIds"
								:value="ower.id"
								number
								class="checkbox"
								type="checkbox"
								:owerid="ower.id"
								:disabled="!editionAccess || !members[ower.id].activated"
								@input="onBillEdited">
							<label
								class="checkboxlabel"
								:for="'dum' + ower.id">
								{{ ower.name }}
							</label>
							<label v-if="myBill.owerIds.includes(ower.id)"
								class="spentlabel">
								({{ owerAmount[ower.id] || 0 }})
							</label>
						</div>
					</div>
					<div v-else-if="newBillMode === 'perso'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<div class="owerAvatar">
								<ColoredAvatar
									class="itemAvatar"
									:color="getMemberColor(ower.id)"
									:size="24"
									:disable-menu="true"
									:disable-tooltip="true"
									:show-user-status="false"
									:is-no-user="getMemberUserId(ower.id) === ''"
									:user="getMemberUserId(ower.id)"
									:display-name="getMemberName(ower.id)" />
								<div v-if="isMemberDisabled(ower.id)" class="disabledMask" />
							</div>
							<input
								:id="'dum' + ower.id"
								v-model="myBill.owerIds"
								class="checkbox"
								type="checkbox"
								:owerid="ower.id"
								:value="ower.id"
								number>
							<label
								class="checkboxlabel"
								:for="'dum' + ower.id">
								{{ ower.name }}
							</label>
							<input v-show="myBill.owerIds.includes(ower.id)"
								:ref="'amountdum' + ower.id"
								class="amountinput"
								type="text"
								value=""
								:placeholder="t('cospend', 'Personal amount')"
								@keyup.enter="onPersoAmountEnterPressed">
						</div>
					</div>
					<div v-else-if="newBillMode === 'custom'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<div class="owerAvatar">
								<ColoredAvatar
									class="itemAvatar"
									:color="getMemberColor(ower.id)"
									:size="24"
									:disable-menu="true"
									:disable-tooltip="true"
									:show-user-status="false"
									:is-no-user="getMemberUserId(ower.id) === ''"
									:user="getMemberUserId(ower.id)"
									:display-name="getMemberName(ower.id)" />
								<div v-if="isMemberDisabled(ower.id)" class="disabledMask" />
							</div>
							<label
								class="numberlabel"
								:for="'amountdum' + ower.id">
								{{ ower.name }}
							</label>
							<input
								:id="'amountdum' + ower.id"
								:ref="'amountdum' + ower.id"
								class="amountinput"
								type="text"
								value=""
								:placeholder="t('cospend', 'Custom amount')"
								@input="onCustomAmountChange"
								@keyup.enter="onCustomAmountEnterPressed">
						</div>
					</div>
					<div v-else-if="newBillMode === 'customShare'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<div class="owerAvatar">
								<ColoredAvatar
									class="itemAvatar"
									:color="getMemberColor(ower.id)"
									:size="24"
									:disable-menu="true"
									:disable-tooltip="true"
									:show-user-status="false"
									:is-no-user="getMemberUserId(ower.id) === ''"
									:user="getMemberUserId(ower.id)"
									:display-name="getMemberName(ower.id)" />
								<div v-if="isMemberDisabled(ower.id)" class="disabledMask" />
							</div>
							<label
								class="numberlabel"
								:for="'amountdum' + ower.id">
								{{ ower.name }}
							</label>
							<input
								:id="'amountdum' + ower.id"
								:ref="'amountdum' + ower.id"
								class="amountinput"
								type="number"
								step="0.1"
								value=""
								:placeholder="t('cospend', 'Custom share number')"
								@input="onCustomShareAmountChange">
							<label v-if="owerCustomShareAmount[ower.id]"
								class="spentlabel">
								({{ owerCustomShareAmount[ower.id] ? owerCustomShareAmount[ower.id].toFixed(2) : 0 }})
							</label>
						</div>
					</div>
				</div>
				<button
					v-if="isNewBill"
					id="owerValidate2"
					:title="t('cospend', 'Press Shift+Enter to validate')"
					@click="onCreateClick">
					<span class="icon-confirm" />
					<span id="owerValidateText">{{ createBillButtonText }}</span>
				</button>
			</div>
		</div>
	</AppContentDetails>
</template>

<script>
import cospend from './state'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { getLocale } from '@nextcloud/l10n'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import ColoredAvatar from './components/ColoredAvatar'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import {
	delay, getCategory, getSmartMemberName,
} from './utils'
import * as network from './network'

export default {
	name: 'BillForm',

	components: {
		DatetimePicker, AppContentDetails, ColoredAvatar,
	},

	props: {
		bill: {
			type: Object,
			required: true,
		},
		members: {
			type: Object,
			required: true,
		},
		editionAccess: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			projectId: cospend.currentProjectId,
			currentUser: getCurrentUser(),
			newBillMode: 'normal',
			billLoading: false,
			progAmountChange: false,
			showHint: false,
			locale: getLocale(),
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
			currentFormula: null,
			nbBillsLeftToCreate: 0,
			// pseudo deep copy of the bill prop, this myBill is the one we make changes on
			myBill: {
				...this.bill,
				owerIds: [...this.bill.owerIds],
			},
			owerCustomShareAmount: {},
			ignoreWeights: false,
		}
	},

	computed: {
		// amount field proxy to safely manipulate bill.amount
		uiAmount: {
			get() {
				return this.myBill.amount
			},
			set(value) {
				const val = value.replace(/,/g, '.')
				// only change bill amount if we're not typing a formula
				if (val === '') {
					this.myBill.amount = 0
					this.currentFormula = null
				} else if (!val.endsWith('.') && !isNaN(val)) {
					this.myBill.amount = parseFloat(val)
					this.currentFormula = null
				} else {
					this.currentFormula = val
					this.myBill.amount = val
				}
				// update custom share ower amounts
				if (this.isNewBill && this.newBillMode === 'customShare') {
					this.owerCustomShareAmount = this.getOwersCustomShareAmount()
				}
			},
		},
		selectAllNoneOwers: {
			get() {
				return this.activatedOrOwer ? this.myBill.owerIds.length === this.activatedOrOwer.length : false
			},
			set(value) {
				const selected = []

				if (value) {
					// select all members
					this.activatedOrOwer.forEach((member) => {
						selected.push(member.id)
					})
				} else {
					// deselect all members
					// avoid deselecting disabled ones (add those who are not active and were selected)
					this.disabledMembers.forEach((member) => {
						if (this.myBill.owerIds.includes(member.id)) {
							selected.push(member.id)
						}
					})
				}

				this.myBill.owerIds = selected
			},
		},
		// classic mode, get actual amount owed per member
		owerAmount() {
			const result = {}
			const amount = parseFloat(this.myBill.amount)
			const nbOwers = this.myBill.owerIds.length
			let weightSum = 0
			let oneWeight, owerVal
			if (nbOwers > 0
				&& !isNaN(amount)
				&& amount !== 0.0) {
				this.myBill.owerIds.forEach((mid) => {
					weightSum += this.members[mid].weight
				})
				oneWeight = amount / weightSum
				this.myBill.owerIds.forEach((mid) => {
					owerVal = oneWeight * this.members[mid].weight
					result[mid] = owerVal.toFixed(2)
				})
			}
			return result
		},
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		isNewBill() {
			return (this.myBill.id === 0)
		},
		noBill() {
			return (this.myBill && this.myBill.id === -1)
		},
		project() {
			return cospend.projects[this.projectId]
		},
		payerDisabled() {
			return this.myBill.id !== 0 && !this.members[this.myBill.payer_id].activated
		},
		payerUserId() {
			return this.myBill.id !== 0 && this.members[this.myBill.payer_id]
				? this.members[this.myBill.payer_id].userid || ''
				: ''
		},
		payerColor() {
			return (this.myBill.payer_id === 0 || this.myBill.id === 0)
				? ''
				: this.members[this.myBill.payer_id]
					? this.members[this.myBill.payer_id].color
					: ''
		},
		payerName() {
			return (this.myBill.payer_id === 0 || this.myBill.id === 0)
				? '*'
				: this.members[this.myBill.payer_id]
					? this.members[this.myBill.payer_id].name
					: ''
		},
		billLinks() {
			return this.myBill.what.match(/https?:\/\/[^\s]+/gi) || []
		},
		billFormattedTitle() {
			let paymentmodeChar = ''
			let categoryChar = ''
			if (parseInt(this.myBill.categoryid) !== 0) {
				categoryChar = getCategory(this.projectId, this.myBill.categoryid).icon + ' '
			}
			if (this.myBill.paymentmode && this.myBill.paymentmode !== 'n') {
				paymentmodeChar = cospend.paymentModes[this.myBill.paymentmode].icon + ' '
			}
			const whatFormatted = paymentmodeChar + categoryChar + this.myBill.what.replace(/https?:\/\/[^\s]+/gi, '')
			return t('cospend', 'Bill : {what}', { what: whatFormatted })
		},
		billDateObject() {
			return moment.unix(this.myBill.timestamp).toDate()
		},
		billDatetime: {
			get() {
				return this.billDateObject
			},
			set(value) {
				const ts = moment(value).unix()
				if (!isNaN(ts)) {
					this.myBill.timestamp = ts
					this.onBillEdited(null, false)
				}
			},
		},
		activatedMembers() {
			const mList = []
			for (const mid in this.members) {
				if (this.members[mid].activated) {
					mList.push(this.members[mid])
				}
			}
			return mList
		},
		disabledMembers() {
			const mList = []
			for (const mid in this.members) {
				if (!this.members[mid].activated) {
					mList.push(this.members[mid])
				}
			}
			return mList
		},
		activatedOrPayer() {
			const mList = []
			for (const mid in this.members) {
				if (this.members[mid].activated || parseInt(mid) === this.myBill.payer_id) {
					mList.push(this.members[mid])
				}
			}
			return mList
		},
		activatedOrOwer() {
			const mList = []
			for (const mid in this.members) {
				if (this.members[mid].activated || this.myBill.owerIds.indexOf(parseInt(mid)) !== -1) {
					mList.push(this.members[mid])
				}
			}
			return mList
		},
		sortedCategories() {
			const allCategories = Object.values(cospend.projects[this.projectId].categories).concat(Object.values(cospend.hardCodedCategories))
			return this.project.categorysort === 'm'
				? allCategories.sort((a, b) => {
					return a.order > b.order
						? 1
						: a.order < b.order
							? -1
							: 0
				})
				: this.project.categorysort === 'a'
					? allCategories.sort((a, b) => {
						const la = a.name.toLowerCase()
						const lb = b.name.toLowerCase()
						return la > lb
							? 1
							: la < lb
								? -1
								: 0
					})
					: allCategories
		},
		currencies() {
			return cospend.projects[this.projectId].currencies
		},
		paymentModes() {
			return cospend.paymentModes
		},
		createBillButtonText() {
			return this.newBillMode === 'normal' ? t('cospend', 'Create the bill') : t('cospend', 'Create the bills')
		},
	},

	watch: {
		myBill() {
			// reset formula when changing bill
			this.currentFormula = null
			this.newBillMode = 'normal'
		},
		bill() {
			this.myBill = {
				...this.bill,
				owerIds: [...this.bill.owerIds],
			}
		},
	},

	methods: {
		stringify(date) {
			return moment(date).locale(this.locale).format('LLL')
		},
		parse(value) {
			return moment(value, 'LLL', this.locale).toDate()
		},
		myGetSmartMemberName(mid) {
			let smartName = getSmartMemberName(this.projectId, mid)
			if (smartName === t('cospend', 'You')) {
				smartName += ' (' + this.members[mid].name + ')'
			}
			return smartName
		},
		getMemberName(mid) {
			return this.members[mid].name
		},
		getMemberUserId(mid) {
			return this.members[mid].userid || ''
		},
		getMemberColor(mid) {
			return this.members[mid].color || ''
		},
		isMemberDisabled(mid) {
			return !this.members[mid].activated
		},
		myGetMemberColor(mid) {
			if (mid === 0) {
				return '888888'
			} else {
				return this.members[mid].color
			}
		},
		onBillEdited(e, delayed = true) {
			if (!this.isNewBill && !this.noBill) {
				if (delayed) {
					delay(() => {
						this.saveBill()
					}, 2000)()
				} else {
					this.saveBill()
				}
			}
		},
		isBillValidForSaveOrNormal() {
			return this.basicBillValueCheck() && this.myBill.owerIds.length > 0
		},
		basicBillValueCheck() {
			const myBill = this.myBill
			if (myBill.what === null || myBill.what === '') {
				return false
			}
			if (myBill.amount === '' || isNaN(myBill.amount) || isNaN(myBill.payer_id)) {
				return false
			}
			return true
		},
		saveBill() {
			// don't save the bill if we are typing a formula
			if (this.currentFormula !== null) {
				return
			}
			if (!this.isBillValidForSaveOrNormal()) {
				showError(t('cospend', 'Impossible to save bill, invalid values.'))
			} else {
				this.billLoading = true
				network.saveBill(this.projectId, this.myBill, this.saveBillSuccess, this.saveBillDone)
			}
		},
		saveBillSuccess() {
			// to update balances
			this.$emit('bill-saved', this.bill, this.myBill)
			showSuccess(t('cospend', 'Bill saved.'))
		},
		saveBillDone() {
			this.billLoading = false
		},
		onCurrencyConvert() {
			let currencyId = this.$refs.currencySelect.value
			if (currencyId !== '') {
				const userAmount = parseFloat(this.myBill.amount)
				currencyId = parseInt(currencyId)
				const currency = this.currencies.find((c) => { return parseInt(c.id) === currencyId })
				if (!currency) {
					return
				}
				this.progAmountChange = true
				this.myBill.amount = parseFloat(this.myBill.amount) * currency.exchange_rate
				this.myBill.what = this.cleanStringFromCurrency(this.myBill.what) + ' (' + userAmount.toFixed(2) + ' ' + currency.name + ')'
				this.$refs.currencySelect.value = ''
				// convert personal amounts
				if (this.isNewBill) {
					if (this.newBillMode === 'perso') {
						const persoParts = this.getPersonalParts()
						let part
						for (const mid in persoParts) {
							part = persoParts[mid]
							if (part !== 0.0) {
								this.$refs['amountdum' + mid][0].value = part * currency.exchange_rate
							}
						}
					} else if (this.newBillMode === 'custom') {
						const customAmounts = this.getCustomAmounts()
						let am
						for (const mid in customAmounts) {
							am = customAmounts[mid]
							if (am !== 0.0) {
								this.$refs['amountdum' + mid][0].value = am * currency.exchange_rate
							}
						}
					} else if (this.newBillMode === 'customShare') {
						this.owerCustomShareAmount = this.getOwersCustomShareAmount()
					}
				}
				this.onBillEdited(null, false)
			}
		},
		cleanStringFromCurrency(str) {
			this.currencies.forEach((c) => {
				const re = new RegExp(' \\(\\d+\\.?\\d* ' + c.name + '\\)', 'g')
				str = str.replace(re, '')
			})
			return str
		},
		onAmountChanged() {
			this.myBill.what = this.cleanStringFromCurrency(this.myBill.what)
			// here, do nothing if we are typing a formula or if
			if (this.currentFormula === null) {
				this.onBillEdited()
			}
		},
		onAmountEnterPressed() {
			// try to evaluate the current algebric formula
			if (isNaN(this.currentFormula)) {
				let calc = 'a'
				try {
					// eslint-disable-next-line
					calc = parseFloat(eval(this.currentFormula).toFixed(12))
				} catch (err) {
					console.debug(err)
				}
				this.myBill.amount = isNaN(calc) ? 0 : calc
				this.currentFormula = null
				this.onBillEdited(null, false)
			}
		},
		onPersoAmountEnterPressed(e) {
			const val = e.target.value.replace(/,/g, '.')
			if (isNaN(val)) {
				let calc = 'a'
				try {
					// eslint-disable-next-line
					calc = parseFloat(eval(val).toFixed(12))
				} catch (err) {
					console.debug(err)
				}
				if (!isNaN(calc)) {
					e.target.value = calc
				}
			}
		},
		onCustomAmountEnterPressed(e) {
			const val = e.target.value.replace(/,/g, '.')
			if (isNaN(val)) {
				let calc = 'a'
				try {
					// eslint-disable-next-line
					calc = parseFloat(eval(val).toFixed(12))
				} catch (err) {
					console.debug(err)
				}
				if (!isNaN(calc)) {
					e.target.value = calc
					this.onCustomAmountChange()
				}
			}
		},
		onHintClick() {
			this.showHint = !this.showHint
		},
		onCreateClick() {
			if (this.newBillMode === 'normal') {
				this.createNormalBill()
			} else if (this.newBillMode === 'perso') {
				this.createEquiPersoBill()
			} else if (this.newBillMode === 'custom') {
				this.createCustomAmountBill()
			} else if (this.newBillMode === 'customShare') {
				this.createCustomShareBill()
			}
		},
		createNormalBill() {
			if (this.isBillValidForSaveOrNormal()) {
				const myBill = this.myBill
				this.createBill('normal', myBill.what, myBill.amount, myBill.payer_id, myBill.timestamp, myBill.owerIds, myBill.repeat,
					myBill.paymentmode, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.comment)
			} else {
				showError(t('cospend', 'Bill values are not valid.'))
			}
		},
		createEquiPersoBill() {
			if (this.isBillValidForSaveOrNormal()) {
				const myBill = this.myBill
				// check if personal parts are valid
				let tmpAmount = parseFloat(this.myBill.amount)
				const persoParts = this.getPersonalParts()
				let part
				for (const mid in persoParts) {
					part = persoParts[mid]
					if (!isNaN(part) && part > 0.0) {
						tmpAmount -= part
					}
				}
				if (tmpAmount < 0.0) {
					showError(t('cospend', 'Personal parts are bigger than the paid amount.'))
					return
				}

				// count how many bills are going to be created
				let nbBills = 0
				for (const mid in persoParts) {
					part = persoParts[mid]
					if (!isNaN(part) && part !== 0.0) {
						nbBills++
					}
				}
				if (tmpAmount > 0.0) {
					nbBills++
				}
				this.nbBillsLeftToCreate = nbBills

				// create bills for perso parts
				for (const mid in persoParts) {
					part = persoParts[mid]
					if (!isNaN(part) && part !== 0.0) {
						this.createBill('perso', myBill.what, part, myBill.payer_id, myBill.timestamp, [mid], myBill.repeat,
							myBill.paymentmode, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.comment)
					}
				}

				// create main bill
				if (tmpAmount > 0.0) {
					this.createBill('mainPerso', myBill.what, tmpAmount, myBill.payer_id, myBill.timestamp, myBill.owerIds, myBill.repeat,
						myBill.paymentmode, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.comment)
				}
				this.newBillMode = 'normal'
			} else {
				showError(t('cospend', 'Bill values are not valid.'))
			}
		},
		createCustomAmountBill() {
			if (this.basicBillValueCheck()) {
				const myBill = this.myBill
				// check if custom amounts are valid
				const customAmounts = this.getCustomAmounts()
				let total = 0.0
				let nbBills = 0
				for (const mid in customAmounts) {
					total += customAmounts[mid]
					// count how many bills are going to be created
					if (customAmounts[mid] !== 0.0) {
						nbBills++
					}
				}
				if (total === 0.0) {
					showError(t('cospend', 'There is no custom amount.'))
					return
				} else {
					this.nbBillsLeftToCreate = nbBills
					let am
					for (const mid in customAmounts) {
						am = customAmounts[mid]
						if (am !== 0.0) {
							this.createBill('custom', myBill.what, am, myBill.payer_id, myBill.timestamp, [mid], myBill.repeat,
								myBill.paymentmode, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.comment)
						}
					}
				}
				this.newBillMode = 'normal'
			} else {
				showError(t('cospend', 'Bill values are not valid'))
			}
		},
		createCustomShareBill() {
			if (this.basicBillValueCheck()) {
				const myBill = this.myBill
				// make sure we have up-to-date custom amounts
				this.owerCustomShareAmount = this.getOwersCustomShareAmount()
				// check if custom share numbers are valid
				let total = 0.0
				let nbBills = 0
				for (const mid in this.owerCustomShareAmount) {
					total += this.owerCustomShareAmount[mid]
					// count how many bills are going to be created
					if (this.owerCustomShareAmount[mid] !== 0.0) {
						nbBills++
					}
				}
				if (total === 0.0) {
					showError(t('cospend', 'There is no custom share number'))
					return
				} else {
					this.nbBillsLeftToCreate = nbBills
					console.debug('BBBBB')
					for (const mid in this.owerCustomShareAmount) {
						const amount = this.owerCustomShareAmount[mid]
						console.debug(amount)
						if (amount !== 0.0) {
							this.createBill('customShare', myBill.what, amount, myBill.payer_id, myBill.timestamp, [mid], myBill.repeat,
								myBill.paymentmode, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.comment)
						}
					}
				}
				this.newBillMode = 'normal'
				this.owerCustomShareAmount = {}
			} else {
				showError(t('cospend', 'Bill values are not valid'))
			}
		},
		createBill(mode = null, what = null, amount = null, payer_id = null, timestamp = null, owerIds = null, repeat = null,
			paymentmode = null, categoryid = null, repeatallactive = null,
			repeatuntil = null, comment = null) {
			if (mode === null) {
				mode = this.newBillMode
			}
			const billToCreate = {
				what,
				comment,
				timestamp,
				payer_id,
				owerIds,
				amount,
				repeat,
				repeatallactive,
				repeatuntil,
				paymentmode,
				categoryid,
			}
			const req = {
				what,
				comment,
				timestamp,
				payer: payer_id,
				payed_for: owerIds.join(','),
				amount,
				repeat,
				repeatallactive: repeatallactive ? 1 : 0,
				repeatuntil,
				paymentmode,
				categoryid,
			}
			this.billLoading = true
			network.createBill(this.projectId, req).then((response) => {
				this.createBillSuccess(response.data, billToCreate, mode)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to create bill')
					+ ': ' + error.response?.request?.responseText
				)
			}).then(() => {
				this.createBillDone()
			})
		},
		createBillSuccess(response, billToCreate, mode) {
			const billid = response
			billToCreate.id = billid
			// only select the bill if it's a normal one or the main one in perso mode
			const select = (mode === 'normal' || mode === 'mainPerso')
			this.$emit('bill-created', billToCreate, select, mode)
			showSuccess(t('cospend', 'Bill created'))
			// manage multiple creation
			if (mode !== 'normal') {
				this.nbBillsLeftToCreate--
				if (this.nbBillsLeftToCreate === 0) {
					if (['custom', 'customShare'].includes(mode)) {
						this.$emit('custom-bills-created')
					} else {
						this.$emit('perso-bills-created')
					}
				}
			}
		},
		createBillDone() {
			this.billLoading = false
		},
		getPersonalParts() {
			const result = {}
			this.myBill.owerIds.forEach((mid) => {
				const val = this.$refs['amountdum' + mid][0].value.replace(/,/g, '.')
				result[mid] = parseFloat(val) || 0
			})
			return result
		},
		getCustomAmounts() {
			const result = {}
			this.activatedOrOwer.forEach((member) => {
				const val = this.$refs['amountdum' + member.id][0].value.replace(/,/g, '.')
				result[member.id] = parseFloat(val) || 0
			})
			return result
		},
		onCustomAmountChange() {
			const customAmounts = this.getCustomAmounts()
			let am
			let sum = 0
			for (const mid in customAmounts) {
				am = customAmounts[mid]
				sum += am
			}
			this.myBill.amount = sum
		},
		getCustomShareNumbers() {
			const result = {}
			this.activatedOrOwer.forEach((member) => {
				const field = this.$refs['amountdum' + member.id]
				const val = field ? parseFloat(field[0].value) : 0
				if (val) {
					result[member.id] = val
				}
			})
			return result
		},
		// custom share mode, get owed amount (consider member weights AND custom share number)
		getOwersCustomShareAmount() {
			const result = {}
			const amount = parseFloat(this.myBill.amount)
			const shareNumbers = this.getCustomShareNumbers()

			const nbOwers = Object.keys(shareNumbers).length
			let shareWeightSum = 0
			if (nbOwers > 0
				&& !isNaN(amount)
				&& amount !== 0.0) {
				if (this.ignoreWeights) {
					// here we only consider custom share numbers
					Object.keys(shareNumbers).forEach((mid) => {
						shareWeightSum += shareNumbers[mid]
					})
					Object.keys(shareNumbers).forEach((mid) => {
						const myProp = shareNumbers[mid]
						const owerVal = amount * (myProp / shareWeightSum)
						result[mid] = owerVal
					})
				} else {
					// here we combine the effect of member's weight and custom share numbers
					Object.keys(shareNumbers).forEach((mid) => {
						shareWeightSum += this.members[mid].weight * shareNumbers[mid]
					})
					Object.keys(shareNumbers).forEach((mid) => {
						const myProp = this.members[mid].weight * shareNumbers[mid]
						const owerVal = amount * (myProp / shareWeightSum)
						result[mid] = owerVal
					})
				}
			}
			return result
		},
		onCustomShareAmountChange() {
			this.owerCustomShareAmount = this.getOwersCustomShareAmount()
		},
		onIgnoreWeightsChange(e) {
			this.ignoreWeights = e.target.checked
			this.owerCustomShareAmount = this.getOwersCustomShareAmount()
		},
		onGeneratePubLinkClick() {
			OC.dialogs.filepicker(
				t('cospend', 'Choose file'),
				(targetPath) => {
					this.generatePublicLinkToFile(targetPath)
				},
				false, null, true
			)
		},
		generatePublicLinkToFile(targetPath) {
			network.generatePublicLinkToFile(targetPath, this.genSuccess)
		},
		genSuccess(response) {
			const filePublicUrl = window.location.protocol + '//' + window.location.host + generateUrl('/s/' + response.token)

			let what = this.myBill.what
			what = what + ' ' + filePublicUrl
			this.myBill.what = what
			this.onBillEdited()
		},
		onConvertInfoClicked() {
			OC.dialogs.info(
				t('cospend', 'This is just a currency converter. Bill amount can be entered in another currency and then converted to "{maincur}". Value is always stored in "{maincur}".', { maincur: this.project.currencyname }),
				t('cospend', 'Info')
			)
		},
		onAmountInfoClicked() {
			OC.dialogs.info(
				t('cospend', 'You can type simple math operations and validate by pressing Enter key.'),
				t('cospend', 'Info')
			)
		},
		onRepeatInfoClicked() {
			OC.dialogs.info(
				t('cospend', 'Bill repetition process runs once a day as a background job. If your bills are not automatically repeated, ask your Nextcloud administrator to check if "Cron" method is selected in admin settings.')
					+ ' ' + t('cospend', 'You can also manually repeat the current bill with the "Repeat now" button.'),
				t('cospend', 'Info')
			)
		},
	},
}
</script>

<style scoped lang="scss">
.bill-title {
	padding: 20px 0px 20px 0px;
	text-align: center;
	margin-left: 50px;

	.billFormAvatar {
		display: inline-block;
		vertical-align: middle;
		height: 52px;
		.itemAvatar {
			display: block;
			position: relative;
			left: 0px;
			top: 0px;
		}
		.disabledMask {
			width: 52px;
			height: 52px;
			display: block;
			position: relative;
			left: -1px;
			top: -51px;
		}
	}
}

.icon-cospend,
.icon-currencies {
	min-width: 23px !important;
	min-height: 23px !important;
	vertical-align: middle;
}

.bill-left select,
.bill-left textarea,
.bill-left input {
	width: 100%;
}

.bill-form-content {
	display: flex;
	flex-direction: column;
	flex-grow: 1;

	.bill-form {
		height: 100%;
		margin-left: auto;
		margin-right: auto;

		a.icon {
			justify-content: space-between;
			line-height: 44px;
			min-height: 44px;
			padding: 0 12px 0 25px;
		}
	}
}

.bill-left,
.bill-right {
	padding: 0px 15px 0px 15px;
	float: left;
}

.owerAllNoneDiv label,
.owerEntry label {
	margin-left: 5px;
}

.bill-owers input {
	cursor: pointer;
	padding: 5px;
	min-height: 0px;
}

#owerValidate,
#owerValidate2 {
	background-color: #46ba61;
	color: white;
}

.owerAllNoneDiv div {
	display: inline-block;
	width: 24px;
}

.owerAllNoneDiv,
.owerEntry {
	height: 34px;
	margin: 10px 0 10px 26px;
	.owerAvatar {
		width: 24px;
	}
}

.amountinput {
	margin-top: 0px !important;
	margin-bottom: 0px !important;
}

#billtype {
	max-width: 80%;
}

.infoButton {
	height: 34px;
	width: 34px;
	float: right;
	border: none;
	background-color: transparent;
	&:hover {
		background-color: var(--color-background-hover);
	}
}

.field-with-info {
	display: flex;
}

.field-with-info select,
.field-with-info input {
	flex-grow: 100;
}

.datetime-picker {
	width: 100%;
}

.bill-date,
.bill-payment-mode,
.bill-category,
.bill-repeat,
.bill-repeat-until,
.bill-payer,
.bill-amount,
.bill-currency-convert,
.bill-comment,
.bill-link-button,
.bill-what {
	display: grid;
	grid-template: 1fr / 5fr 7fr;
}

.bill-repeat,
.bill-payer,
.bill-amount {
	margin-top: 25px;
}

.bill-amount .icon-cospend,
.bill-currency-convert .icon-currencies {
	display: inline-block;
	padding-left: 34px !important;
}

.bill-repeat-include {
	text-align: left;
	margin-top: 5px;
	margin-bottom: 5px;
	padding-left: 8px;
}

.modehint {
	max-width: 350px;
}

.checkbox-line {
	line-height: 44px;
}
</style>
