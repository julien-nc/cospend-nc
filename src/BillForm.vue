<template>
	<NcAppContentDetails class="bill-form-content">
		<h2 class="bill-title">
			<div class="billFormAvatar">
				<NcLoadingIcon v-if="billLoading"
					:size="44" />
				<MemberAvatar v-else
					:member="billItemPayer"
					:size="44" />
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
			<NcButton
				v-if="isNewBill"
				:title="t('cospend', 'Press Shift+Enter to validate')"
				variant="primary"
				@click="onCreateClick">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
				{{ createBillButtonText }}
			</NcButton>
			<NcButton v-else
				:title="payerDisabled ? t('cospend', 'Impossible to duplicate a bill with a disabled payer') : t('cospend', 'Duplicate bill')"
				:aria-label="payerDisabled ? t('cospend', 'Impossible to duplicate a bill with a disabled payer') : t('cospend', 'Duplicate bill')"
				:disabled="payerDisabled"
				@click="onDuplicate">
				<template #icon>
					<ContentDuplicateIcon :size="20" />
				</template>
			</NcButton>
			<NcButton
				v-if="!isNewBill && !project.deletiondisabled"
				:title="deleteBillLabel"
				variant="secondary"
				@click="onDeleteClick">
				<template #icon>
					<DeleteIcon />
				</template>
			</NcButton>
		</h2>
		<div class="bill-form">
			<div class="bill-left">
				<div class="bill-field bill-what">
					<TextLongIcon
						class="icon"
						:size="20" />
					<NcTextField
						ref="what"
						v-model="myBill.what"
						:label="t('cospend', 'What?')"
						:placeholder="t('cospend', 'What is the bill about?')"
						:show-trailing-button="editionAccess && !!myBill.what && myBill.what.trim() !== ''"
						maxlength="300"
						:readonly="!editionAccess"
						@trailing-button-click="myBill.what = ''; onBillEdited(null, false)"
						@update:model-value="onBillEdited"
						@keyup.enter="onBillEdited(null, false)"
						@focus="$refs.what.select()" />
				</div>
				<div v-if="!pageIsPublic"
					class="bill-link-button">
					<NcButton
						:title="t('cospend', 'Attach share link to personal file')"
						@click="onGeneratePubLinkClick">
						<template #icon>
							<LinkVariantIcon />
						</template>
						{{ t('cospend', 'Attach share link to personal file') }}
					</NcButton>
				</div>
				<div class="bill-field bill-amount">
					<CospendIcon
						class="icon"
						:size="20" />
					<div class="field-with-info">
						<NcTextField
							v-model="uiAmount"
							:label="t('cospend', 'How much?') + (project.currencyname ? (' (' + project.currencyname + ')') : '')"
							placeholder="..."
							:disabled="isNewBill && newBillMode === 'custom'"
							:readonly="!editionAccess"
							:show-trailing-button="editionAccess && !!uiAmount && uiAmount !== 0"
							@trailing-button-click="uiAmount = '0'; onBillEdited(null, false)"
							@update:model-value="onAmountChanged"
							@keyup.enter="onAmountEnterPressed"
							@focus="$event.target.select()" />
						<NcButton
							class="more-info"
							:title="t('cospend', 'More information')"
							:aria-label="t('cospend', 'More information on amount input field')"
							@click="showAmountInfo = true">
							<template #icon>
								<InformationOutlineIcon />
							</template>
						</NcButton>
						<NcDialog v-model:open="showAmountInfo"
							:name="t('cospend', 'Info')"
							:message="t('cospend', 'You can type simple math operations and validate by pressing Enter key.')" />
					</div>
				</div>
				<div
					v-if="project.currencyname && project.currencies.length > 0 && editionAccess"
					class="bill-field bill-currency-convert">
					<CurrencyIcon class="icon"
						:size="20" />
					<div class="input">
						<label for="bill-currency">
							{{ t('cospend', 'Convert to') }}
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
							<NcButton
								:title="t('cospend', 'More information')"
								:aria-label="t('cospend', 'More information on currency conversion')"
								@click="showConvertInfo = true">
								<template #icon>
									<InformationOutlineIcon />
								</template>
							</NcButton>
							<NcDialog v-model:open="showConvertInfo"
								:name="t('cospend', 'Info')"
								:message="convertInfoText" />
						</div>
					</div>
				</div>
				<div class="bill-field bill-payer">
					<AccountIcon
						class="icon"
						:size="20" />
					<MemberMultiSelect
						class="member-select select"
						:project-id="projectId"
						:value="selectedMember"
						:disabled="!editionAccess || (!isNewBill && !members[myBill.payer_id].activated)"
						:input-label="t('cospend', 'Who paid?')"
						:placeholder="t('cospend', 'Choose a member')"
						:members="activatedOrPayer"
						@input="memberSelected" />
				</div>
				<div class="bill-field bill-date">
					<CalendarIcon
						class="icon"
						:size="20" />
					<div class="input">
						<label for="dateInput">
							{{ t('cospend', 'When?') }}
						</label>
						<NcDateTimePickerNative v-if="showDatePicker"
							id="dateInput"
							v-model="billDatetime"
							class="datetime-picker"
							:type="useTime ? 'datetime-local' : 'date'"
							:hide-label="true"
							:disabled="!editionAccess" />
					</div>
				</div>
				<div class="bill-field bill-payment-mode">
					<TagIcon
						class="icon"
						:size="20" />
					<NcSelect
						:model-value="selectedPaymentModeItem"
						class="select"
						:placeholder="t('cospend', 'Choose a payment mode')"
						:input-label="t('cospend', 'Payment mode')"
						:options="formattedPaymentModes"
						:no-wrap="true"
						label="name"
						:disabled="!editionAccess"
						:clearable="false"
						@search="pmQueryChanged"
						@update:model-value="paymentModeSelected" />
				</div>
				<div class="bill-field bill-category">
					<ShapeIcon
						class="icon"
						:size="20" />
					<NcSelect
						:model-value="selectedCategoryItem"
						class="select"
						:placeholder="t('cospend', 'Choose or add a category')"
						:input-label="t('cospend', 'Category')"
						:options="formattedCategories"
						:no-wrap="true"
						label="name"
						:disabled="!editionAccess"
						:clearable="false"
						@search="categoryQueryChanged"
						@update:model-value="categorySelected" />
				</div>
				<div class="bill-field bill-comment">
					<CommentTextIcon
						class="icon"
						:size="20" />
					<div class="input">
						<label for="comment">
							{{ t('cospend', 'Comment') }}
						</label>
						<NcRichContenteditable
							v-model="myBill.comment"
							class="input-bill-comment"
							:maxlength="300"
							:multiline="true"
							:contenteditable="editionAccess"
							:placeholder="t('cospend', 'More details about the bill') + '\n' + t('cospend', '({n} characters max)', { n: 300 })"
							@update:model-value="onBillEdited" />
					</div>
				</div>
				<div class="bill-field bill-repeat">
					<CalendarSyncIcon
						class="icon"
						:size="20" />
					<div class="input">
						<label for="repeatbill">
							{{ t('cospend', 'Repeat') }}
						</label>
						<div class="field-with-info">
							<select
								id="repeatbill"
								:value="myBill.repeat"
								:disabled="!editionAccess"
								@input="onRepeatChanged">
								<option :value="constants.FREQUENCY.NO" selected="selected">
									{{ t('cospend', 'No') }}
								</option>
								<option :value="constants.FREQUENCY.DAILY">
									{{ t('cospend', 'Daily') }}
								</option>
								<option :value="constants.FREQUENCY.WEEKLY">
									{{ t('cospend', 'Weekly') }}
								</option>
								<option :value="constants.FREQUENCY.BI_WEEKLY">
									{{ t('cospend', 'Bi-weekly (every 2 weeks)') }}
								</option>
								<option :value="constants.FREQUENCY.SEMI_MONTHLY">
									{{ t('cospend', 'Semi-monthly (twice a month)') }}
								</option>
								<option :value="constants.FREQUENCY.MONTHLY">
									{{ t('cospend', 'Monthly') }}
								</option>
								<option :value="constants.FREQUENCY.YEARLY">
									{{ t('cospend', 'Yearly') }}
								</option>
							</select>
							<NcButton
								:title="t('cospend', 'More information')"
								:aria-label="t('cospend', 'More information on bill repetition')"
								@click="showRepeatInfo = true">
								<template #icon>
									<InformationOutlineIcon />
								</template>
							</NcButton>
							<NcDialog v-model:open="showRepeatInfo"
								:name="t('cospend', 'Info')"
								:message="repeatInfoText" />
						</div>
					</div>
				</div>
				<div v-if="myBill.repeat !== 'n'"
					class="bill-repeat-extra">
					<div v-if="[constants.FREQUENCY.DAILY, constants.FREQUENCY.WEEKLY, constants.FREQUENCY.MONTHLY, constants.FREQUENCY.YEARLY].includes(myBill.repeat)"
						class="bill-field bill-repeat-freq">
						<CounterIcon
							class="icon"
							:size="20" />
						<div class="input">
							<label for="repeat-freq">
								{{ t('cospend', 'Frequency') }}
							</label>
							<div class="field-with-info">
								<input
									id="repeat-freq"
									:value="myBill.repeatfreq"
									type="number"
									class="input-repeat-freq"
									min="1"
									step="1"
									:placeholder="t('cospend', 'Leave empty for maximum frequency')"
									:readonly="!editionAccess"
									@input="onRepeatFreqChanged">
							</div>
						</div>
					</div>
					<div class="bill-repeat-include">
						<NcCheckboxRadioSwitch
							:model-value="myBill.repeatallactive === 1"
							:disabled="!editionAccess"
							class="nc-checkbox"
							@update:model-value="onRepeatAllActiveChanged">
							{{ t('cospend', 'Include all active members on repeat') }}
						</NcCheckboxRadioSwitch>
					</div>
					<div class="bill-field bill-repeat-until">
						<CalendarEndIcon
							class="icon"
							:size="20" />
						<div class="input">
							<label>
								{{ t('cospend', 'Repeat until') }}
								{{ billStringRepeatUntil ? '' : ' (' + t('cospend', 'No limit') + ')' }}
							</label>
							<NcDateTimePickerNative v-if="showDatePicker"
								v-model="billStringRepeatUntil"
								class="datetime-picker"
								type="date"
								:min="billDateObject"
								:hide-label="true"
								:readonly="!editionAccess" />
						</div>
					</div>
					<div v-if="editionAccess && !isNewBill"
						class="bill-repeat-now">
						<label>&nbsp;</label>
						<div class="repeat-now">
							<NcButton
								@click="$emit('repeat-bill-now', myBill.id)">
								<template #icon>
									<RepeatIcon
										class="icon"
										:size="20" />
								</template>
								{{ t('cospend', 'Repeat now') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
			<div class="bill-right">
				<div v-if="isNewBill"
					class="bill-type">
					<label class="bill-owers-label">
						<FormatListBulletedTypeIcon
							class="icon"
							:size="20" />
						{{ t('cospend', 'Bill type') }}
					</label>
					<br>
					<div id="billTypeLine">
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
						<NcButton
							:title="t('cospend', 'More information')"
							:aria-label="t('cospend', 'More information on bill mode')"
							@click="onHintClick">
							<template #icon>
								<InformationOutlineIcon />
							</template>
						</NcButton>
					</div>
					<div v-if="isNewBill && newBillMode === 'customShare'" class="checkbox-line">
						<NcCheckboxRadioSwitch
							v-model="ignoreWeights"
							class="nc-checkbox"
							@update:model-value="onIgnoreWeightsChange">
							{{ t('cospend', 'Ignore member weights') }}
						</NcCheckboxRadioSwitch>
					</div>
					<transition name="fade">
						<div v-if="newBillMode === 'normal' && showHint"
							class="modehint">
							{{ t('cospend', 'Classic mode: Choose a payer, enter a bill amount and select who is concerned by the whole spending, the bill is then split equitably between selected members. Real life example: One person pays the whole restaurant bill and everybody agrees to evenly split the cost.') }}
						</div>
						<div v-else-if="newBillMode === 'perso' && showHint"
							class="modehint">
							{{ t('cospend', 'Classic+personal mode: This mode is similar to the classic one. Choose a payer and enter a bill amount corresponding to what was actually paid. Then select who is concerned by the bill and optionally set an amount related to personal stuff for some members. Multiple bills will be created: one for the shared spending and one for each personal part. Real life example: We go shopping, part of what was bought concerns the group but someone also added something personal (like a shirt) which the others don\'t want to collectively pay.') }}
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
						<AccountGroupIcon
							class="icon"
							:size="20" />
						<span>
							{{ t('cospend', 'For whom?') }}
						</span>
					</label>
					<div v-if="!['custom', 'customShare'].includes(newBillMode)"
						class="owerAllNoneDiv">
						<NcCheckboxRadioSwitch
							v-model="selectAllNoneOwers"
							:disabled="!editionAccess"
							class="nc-checkbox"
							@update:model-value="onBillEdited(null, false)">
							{{ t('cospend', 'All/None') }}
						</NcCheckboxRadioSwitch>
					</div>
					<div v-if="!isNewBill || newBillMode === 'normal'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<NcCheckboxRadioSwitch
								:model-value="myBill.owerIds.includes(ower.id)"
								:disabled="!editionAccess || !members[ower.id].activated"
								class="nc-checkbox"
								@update:model-value="onOwerChecked2($event, ower.id)">
								<div class="nc-checkbox-content">
									<MemberAvatar
										:member="members[ower.id]"
										:size="24"
										:hide-status="true" />
									<span>{{ ower.name }}</span>
									<span v-if="myBill.owerIds.includes(ower.id)"
										class="spentlabel">
										&nbsp;({{ (owerAmount[ower.id] || 0) + (project.currencyname ? ' ' + project.currencyname : '') }})
									</span>
								</div>
							</NcCheckboxRadioSwitch>
						</div>
					</div>
					<div v-else-if="newBillMode === 'perso'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<NcCheckboxRadioSwitch
								:model-value="myBill.owerIds.includes(ower.id)"
								class="nc-checkbox"
								@update:model-value="onOwerChecked2($event, ower.id)">
								<div class="nc-checkbox-content">
									<MemberAvatar
										:member="members[ower.id]"
										:size="24" />
									<span>{{ ower.name }}</span>
								</div>
							</NcCheckboxRadioSwitch>
							<input v-show="myBill.owerIds.includes(ower.id)"
								:ref="'amountdum' + ower.id"
								class="amountinput"
								type="text"
								value=""
								:placeholder="t('cospend', 'Personal amount')"
								@input="onPersoAmountInput"
								@keyup.enter="onPersoAmountEnterPressed">
						</div>
					</div>
					<div v-else-if="newBillMode === 'custom'">
						<div v-for="ower in activatedOrOwer"
							:key="ower.id"
							class="owerEntry">
							<MemberAvatar
								:member="members[ower.id]"
								:size="24" />
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
							<MemberAvatar
								:member="members[ower.id]"
								:size="24" />
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
								({{
									(owerCustomShareAmount[ower.id] ? owerCustomShareAmount[ower.id].toFixed(2) : 0)
										+ (project.currencyname ? ' ' + project.currencyname : '')
								}})
							</label>
						</div>
					</div>
				</div>
				<NcButton
					v-if="isNewBill"
					:title="t('cospend', 'Press Shift+Enter to validate')"
					variant="primary"
					@click="onCreateClick">
					<template #icon>
						<CheckIcon :size="20" />
					</template>
					{{ createBillButtonText }}
				</NcButton>
			</div>
		</div>
	</NcAppContentDetails>
</template>

<script>
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FormatListBulletedTypeIcon from 'vue-material-design-icons/FormatListBulletedType.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import CalendarEndIcon from 'vue-material-design-icons/CalendarEnd.vue'
import CounterIcon from 'vue-material-design-icons/Counter.vue'
import TextLongIcon from 'vue-material-design-icons/TextLong.vue'
import ShapeIcon from 'vue-material-design-icons/Shape.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import CommentTextIcon from 'vue-material-design-icons/CommentText.vue'
import RepeatIcon from 'vue-material-design-icons/Repeat.vue'
import ContentDuplicateIcon from 'vue-material-design-icons/ContentDuplicate.vue'
import CalendarSyncIcon from 'vue-material-design-icons/CalendarSync.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import CospendIcon from './components/icons/CospendIcon.vue'
import CurrencyIcon from './components/icons/CurrencyIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcAppContentDetails from '@nextcloud/vue/components/NcAppContentDetails'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import MemberAvatar from './components/avatar/MemberAvatar.vue'
import MemberMultiSelect from './components/MemberMultiSelect.vue'

import { emit } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { getLocale } from '@nextcloud/l10n'
import {
	showSuccess,
	showError,
	getFilePickerBuilder,
	FilePickerType,
} from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import {
	delay, getCategory, getPaymentMode, strcmp, evalAlgebricFormula,
} from './utils.js'
import * as network from './network.js'
import * as constants from './constants.js'

export default {
	name: 'BillForm',

	components: {
		MemberAvatar,
		CurrencyIcon,
		CospendIcon,
		NcDateTimePickerNative,
		NcAppContentDetails,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcTextField,
		NcLoadingIcon,
		MemberMultiSelect,
		NcButton,
		NcRichContenteditable,
		NcDialog,
		AccountIcon,
		AccountGroupIcon,
		TagIcon,
		CalendarIcon,
		CalendarEndIcon,
		CalendarSyncIcon,
		CounterIcon,
		TextLongIcon,
		ShapeIcon,
		CommentTextIcon,
		RepeatIcon,
		LinkVariantIcon,
		CheckIcon,
		ContentDuplicateIcon,
		InformationOutlineIcon,
		FormatListBulletedTypeIcon,
		DeleteIcon,
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
			constants,
			cospend: OCA.Cospend.state,
			projectId: OCA.Cospend.state.currentProjectId,
			currentUser: getCurrentUser(),
			newBillMode: 'normal',
			billLoading: false,
			progAmountChange: false,
			showHint: false,
			locale: getLocale(),
			formatWhen: {
				stringify: this.stringify,
				parse: this.parse,
			},
			formatRepeatUntil: {
				stringify: this.stringifyRepeatUntil,
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
			showDatePicker: true,
			categoryQuery: '',
			pmQuery: '',
			showConvertInfo: false,
			showAmountInfo: false,
			showRepeatInfo: false,
		}
	},

	computed: {
		deleteBillLabel() {
			return this.myBill.deleted === 1
				? t('cospend', 'Delete this bill')
				: t('cospend', 'Move this bill to the trashbin')
		},
		maintenerAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.MAINTENER
		},
		selectedMember() {
			return this.members[this.myBill.payer_id]
		},
		selectedPaymentModeItem() {
			if (this.myBill.paymentmodeid === 0) {
				return {
					id: 0,
					name: t('cospend', 'None'),
				}
			} else {
				const paymentmode = getPaymentMode(this.projectId, this.myBill.paymentmodeid)
				return {
					id: paymentmode.id,
					name: paymentmode.icon + ' ' + paymentmode.name,
				}
			}
		},
		formattedPaymentModes() {
			const pmItems = [{
				name: t('cospend', 'None'),
				id: 0,
			}]
			pmItems.push(...this.sortedPaymentModes.map((pm) => {
				return {
					name: pm.icon + ' ' + pm.name,
					id: pm.id,
				}
			}))
			if (this.maintenerAccess
				&& this.pmQuery
				&& !this.sortedPaymentModes.find((pm) => { return strcmp(pm.name, this.pmQuery) === 0 })
			) {
				pmItems.push({
					isNewPm: true,
					name: '➕ ' + t('cospend', 'Add payment mode "{name}"', { name: this.pmQuery }),
					id: -999,
				})
			}
			return pmItems
		},
		selectedCategoryItem() {
			if (this.myBill.categoryid === 0) {
				return {
					id: 0,
					name: t('cospend', 'None'),
				}
			} else {
				const category = getCategory(this.projectId, this.myBill.categoryid)
				return {
					id: category.id,
					name: category.icon + ' ' + category.name,
				}
			}
		},
		formattedCategories() {
			const categoryItems = [{
				name: t('cospend', 'None'),
				id: 0,
			}]
			categoryItems.push(...this.sortedCategories.map((c) => {
				return {
					name: c.icon + ' ' + c.name,
					id: c.id,
				}
			}))
			categoryItems.push(...Object.values(this.hardCodedCategories).map((c) => {
				return {
					name: c.icon + ' ' + c.name,
					id: c.id,
				}
			}))
			if (this.maintenerAccess
				&& this.categoryQuery
				&& !this.sortedCategories.find((c) => { return strcmp(c.name, this.categoryQuery) === 0 })
			) {
				categoryItems.push({
					isNewCategory: true,
					name: '➕ ' + t('cospend', 'Add category "{name}"', { name: this.categoryQuery }),
					id: -1,
				})
			}
			return categoryItems
		},
		useTime() {
			return this.cospend.useTime
		},
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
			return this.cospend.pageIsPublic
		},
		isNewBill() {
			return (this.myBill.id === 0)
		},
		noBill() {
			return (this.myBill && this.myBill.id === -1)
		},
		project() {
			return this.cospend.projects[this.projectId]
		},
		payer() {
			return this.members[this.myBill.payer_id]
		},
		billItemPayer() {
			return this.myBill.id === 0
				? {
					name: '*',
					color: '000000',
				}
				: this.payer
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
			if (parseInt(this.myBill.paymentmodeid) !== 0) {
				paymentmodeChar = getPaymentMode(this.projectId, this.myBill.paymentmodeid).icon + ' '
			}
			const whatFormatted = paymentmodeChar + categoryChar + this.myBill.what.replace(/https?:\/\/[^\s]+/gi, '')
			return t('cospend', 'Bill : {what}', { what: whatFormatted }, undefined, { escape: false })
		},
		billDateMoment() {
			return moment.unix(this.myBill.timestamp)
		},
		billDateObject() {
			return this.billDateMoment.toDate()
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
		billStringRepeatUntil: {
			get() {
				return this.myBill.repeatuntil
					? moment(this.myBill.repeatuntil).toDate()
					: null
			},
			set(value) {
				if (value === null) {
					this.myBill.repeatuntil = ''
				} else {
					const mom = moment(value)
					this.myBill.repeatuntil = mom.format('YYYY-MM-DD')
				}
				this.onBillEdited(null, false)
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
		sortedMembers() {
			return Object.keys(this.members).sort((a, b) => {
				const ma = this.members[a]
				const mb = this.members[b]
				return strcmp(ma.name, mb.name)
			}).map(mid => this.members[mid])
		},
		activatedOrPayer() {
			return this.sortedMembers.filter(m => (this.members[m.id].activated || parseInt(m.id) === this.myBill.payer_id))
		},
		activatedOrOwer() {
			return this.sortedMembers.filter(m => (this.members[m.id].activated || this.myBill.owerIds.includes(m.id)))
		},
		sortedPaymentModes() {
			const allPaymentModes = Object.values(this.cospend.projects[this.projectId].paymentmodes)
			return [
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
			].includes(this.project.paymentmodesort)
				? allPaymentModes.sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
				: this.project.paymentmodesort === constants.SORT_ORDER.ALPHA
					? allPaymentModes.sort((a, b) => {
						return strcmp(a.name, b.name)
					})
					: allPaymentModes
		},
		sortedCategories() {
			const allCategories = Object.values(this.cospend.projects[this.projectId].categories)
			return [
				constants.SORT_ORDER.MANUAL,
				constants.SORT_ORDER.MOST_USED,
				constants.SORT_ORDER.RECENTLY_USED,
			].includes(this.project.categorysort)
				? allCategories.sort((a, b) => {
					return a.order === b.order
						? strcmp(a.name, b.name)
						: a.order > b.order
							? 1
							: a.order < b.order
								? -1
								: 0
				})
				: this.project.categorysort === constants.SORT_ORDER.ALPHA
					? allCategories.sort((a, b) => {
						return strcmp(a.name, b.name)
					})
					: allCategories
		},
		hardCodedCategories() {
			return this.cospend.hardCodedCategories
		},
		currencies() {
			return this.cospend.projects[this.projectId].currencies
		},
		createBillButtonText() {
			return this.newBillMode === 'normal' ? t('cospend', 'Create the bill') : t('cospend', 'Create the bills')
		},
		convertInfoText() {
			return t('cospend', 'This is just a currency converter. Bill amount can be entered in another currency and then converted to "{maincur}". Value is always stored in "{maincur}".', { maincur: this.project.currencyname })
		},
		repeatInfoText() {
			return t('cospend', 'Bill repetition process runs once a day as a background job. If your bills are not automatically repeated, ask your Nextcloud administrator to check if "Cron" method is selected in admin settings.')
				+ ' ' + t('cospend', 'You can also manually repeat the current bill with the "Repeat now" button.')
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
			this.$refs.what.focus()
		},
		useTime() {
			// re-render date picker after type change
			this.showDatePicker = false
			this.$nextTick(() => { this.showDatePicker = true })
		},
	},

	mounted() {
		this.$refs.what.focus()
	},

	methods: {
		isRepeatUntilDateDisabled(date) {
			return moment(date).isBefore(this.billDateMoment)
		},
		memberSelected(selected) {
			if (selected) {
				this.myBill.payer_id = selected.id
				this.onBillEdited(null, false)
			}
		},
		onRepeatChanged(e) {
			this.myBill.repeat = e.target.value
			this.onBillEdited(null, false)
		},
		onRepeatFreqChanged(e) {
			this.myBill.repeatfreq = parseInt(e.target.value)
			this.onBillEdited(null, false)
		},
		onRepeatAllActiveChanged(checked) {
			this.myBill.repeatallactive = checked ? 1 : 0
			this.onBillEdited(null, false)
		},
		paymentModeSelected(selected) {
			if (!selected.isNewPm) {
				this.myBill.paymentmodeid = selected.id
				this.onBillEdited(null, false)
			} else {
				// add a pm
				const name = this.pmQuery
				const icon = '🏷'
				const color = '#000000'
				const order = this.sortedPaymentModes.length
				network.createPaymentMode(this.project.id, name, icon, color, order).then((response) => {
					const newPmId = response.data.ocs.data
					this.cospend.projects[this.projectId].paymentmodes[newPmId] = {
						name,
						icon,
						color,
						id: newPmId,
					}
					this.myBill.paymentmodeid = newPmId
					this.onBillEdited(null, false)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to add payment mode')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
					console.error(error)
				})
			}
			this.pmQuery = ''
		},
		pmQueryChanged(query) {
			this.pmQuery = query
		},
		categoryQueryChanged(query) {
			this.categoryQuery = query
		},
		categorySelected(selected) {
			if (!selected.isNewCategory) {
				this.myBill.categoryid = selected.id
				this.onBillEdited(null, false)
			} else {
				// add a category
				const name = this.categoryQuery
				const icon = '✨'
				const color = '#000000'
				const order = this.sortedCategories.length
				network.createCategory(this.project.id, name, icon, color, order).then((response) => {
					const newCategoryId = response.data.ocs.data
					this.cospend.projects[this.projectId].categories[newCategoryId] = {
						name,
						icon,
						color,
						id: newCategoryId,
					}
					this.myBill.categoryid = newCategoryId
					this.onBillEdited(null, false)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to add category')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
					console.error(error)
				})
			}
			this.categoryQuery = ''
		},
		stringifyRepeatUntil(date) {
			return moment(date).locale(this.locale).format('LL')
		},
		stringify(date) {
			return this.useTime
				? moment(date).locale(this.locale).format('LLL')
				: moment(date).locale(this.locale).format('LL')
		},
		parse(value) {
			// in case we detect another format
			if (value.match(/^\d\d.\d\d.\d\d\d\d$/)) {
				return moment(value, 'DD.MM.YYYY').toDate()
			} else if (value.match(/^\d\d\/\d\d\/\d\d\d\d$/)) {
				return moment(value, 'DD/MM/YYYY').toDate()
			} else if (value.match(/^\d\d\d\d-\d\d-\d\d$/)) {
				return moment(value, 'YYYY-MM-DD').toDate()
			} else if (value.match(/^\d.\d.\d\d$/)) {
				return moment(value, 'D.M.YY').toDate()
			} else if (value.match(/^\d\/\d\/\d\d$/)) {
				return moment(value, 'D/M/YY').toDate()
			} else if (value.match(/^\d\d-\d-\d$/)) {
				return moment(value, 'YY-M-D').toDate()
			}
			return this.useTime
				? moment(value, 'LLL', this.locale).toDate()
				: moment(value, 'LL', this.locale).toDate()
		},
		onOwerChecked2(checked, value) {
			console.debug('ccccccccccccc', checked, value)
			if (checked) {
				if (!this.myBill.owerIds.includes(value)) {
					this.myBill.owerIds.push(value)
				}
			} else {
				if (this.myBill.owerIds.includes(value)) {
					this.myBill.owerIds.splice(this.myBill.owerIds.indexOf(value), 1)
				}
			}
			this.onBillEdited(null, false)
		},
		onOwerChecked(e) {
			const value = parseInt(e.target.value)
			if (e.target.checked) {
				if (!this.myBill.owerIds.includes(value)) {
					this.myBill.owerIds.push(value)
				}
			} else {
				if (this.myBill.owerIds.includes(value)) {
					this.myBill.owerIds.splice(this.myBill.owerIds.indexOf(value), 1)
				}
			}
			this.onBillEdited(null, false)
		},
		onBillEdited(e, delayed = true) {
			console.debug('onBillEdited', this.myBill)
			this.computeAmountFormula()
			if (!this.isNewBill && !this.noBill) {
				if (delayed) {
					delay(() => {
						this.saveBill()
					}, 2000)()
				} else {
					this.saveBill()
				}
			} else if (this.isNewBill) {
				// apply new bill changes immediately (to display them in bill list item)
				this.$emit('bill-saved', this.bill, this.myBill, false)
			}
		},
		isBillValidForSaveOrNormal() {
			return this.basicBillValueCheck() && this.myBill.owerIds.length > 0
		},
		basicBillValueCheck() {
			const myBill = this.myBill
			if (myBill.amount === '' || isNaN(myBill.amount) || isNaN(myBill.payer_id)) {
				return false
			}
			return true
		},
		onDeleteClick() {
			emit('delete-bill', this.myBill)
		},
		saveBill() {
			// don't save the bill if we are typing a formula
			if (this.currentFormula !== null) {
				return
			}
			if (!this.isBillValidForSaveOrNormal()) {
				showError(t('cospend', 'Impossible to save bill, invalid values'))
			} else {
				this.billLoading = true
				network.editBill(this.projectId, this.myBill).then((response) => {
					// to update balances
					this.$emit('bill-saved', this.bill, this.myBill)
					showSuccess(t('cospend', 'Bill saved'))
				}).catch((error) => {
					console.debug(error)
					showError(
						t('cospend', 'Failed to save bill')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				}).then(() => {
					this.billLoading = false
				})
			}
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
				// update custom share ower amounts
				if (this.isNewBill && this.newBillMode === 'customShare') {
					this.owerCustomShareAmount = this.getOwersCustomShareAmount()
				}
			}
		},
		onAmountEnterPressed() {
			if (this.computeAmountFormula()) {
				this.onBillEdited(null, false)
			}
		},
		/**
		 * @return {boolean} true if a formula has really been computed
		 */
		computeAmountFormula() {
			// try to evaluate the current algebric formula
			if (isNaN(this.currentFormula)) {
				const calc = evalAlgebricFormula(this.currentFormula)
				this.myBill.amount = isNaN(calc) ? 0 : calc
				this.currentFormula = null
				// update custom share ower amounts
				if (this.isNewBill && this.newBillMode === 'customShare') {
					this.owerCustomShareAmount = this.getOwersCustomShareAmount()
				}
				return true
			}
			return false
		},
		onPersoAmountInput(e) {
			if (e.data === ',') {
				e.target.value = e.target.value.replace(/,/g, '.')
			}
		},
		onPersoAmountEnterPressed(e) {
			const val = e.target.value.replace(/,/g, '.')
			if (isNaN(val)) {
				const calc = evalAlgebricFormula(val)
				if (!isNaN(calc)) {
					e.target.value = calc
				}
			}
		},
		onCustomAmountEnterPressed(e) {
			const val = e.target.value.replace(/,/g, '.')
			if (isNaN(val)) {
				const calc = evalAlgebricFormula(val)
				if (!isNaN(calc)) {
					e.target.value = calc
					this.onCustomAmountChange(e)
				}
			}
		},
		onHintClick() {
			this.showHint = !this.showHint
		},
		onCreateClick() {
			this.computeAmountFormula()
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
					myBill.paymentmodeid, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.repeatfreq, myBill.comment)
			} else {
				showError(t('cospend', 'Bill values are not valid'))
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
					showError(t('cospend', 'Personal parts are bigger than the paid amount'))
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
							myBill.paymentmodeid, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.repeatfreq, myBill.comment)
					}
				}

				// create main bill
				if (tmpAmount > 0.0) {
					this.createBill('mainPerso', myBill.what, tmpAmount, myBill.payer_id, myBill.timestamp, myBill.owerIds, myBill.repeat,
						myBill.paymentmodeid, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.repeatfreq, myBill.comment)
				}
				this.newBillMode = 'normal'
			} else {
				showError(t('cospend', 'Bill values are not valid'))
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
					showError(t('cospend', 'There is no custom amount'))
					return
				} else {
					this.nbBillsLeftToCreate = nbBills
					let am
					for (const mid in customAmounts) {
						am = customAmounts[mid]
						if (am !== 0.0) {
							this.createBill('custom', myBill.what, am, myBill.payer_id, myBill.timestamp, [mid], myBill.repeat,
								myBill.paymentmodeid, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.repeatfreq, myBill.comment)
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
					for (const mid in this.owerCustomShareAmount) {
						const amount = this.owerCustomShareAmount[mid]
						if (amount !== 0.0) {
							this.createBill('customShare', myBill.what, amount, myBill.payer_id, myBill.timestamp, [mid], myBill.repeat,
								myBill.paymentmodeid, myBill.categoryid, myBill.repeatallactive, myBill.repeatuntil, myBill.repeatfreq, myBill.comment)
						}
					}
				}
				this.newBillMode = 'normal'
				this.owerCustomShareAmount = {}
			} else {
				showError(t('cospend', 'Bill values are not valid'))
			}
		},
		createBill(mode = null, what = null, amount = null, payerId = null, timestamp = null, owerIds = null, repeat = null,
			paymentmodeid = null, categoryid = null, repeatallactive = null,
			repeatuntil = null, repeatfreq = null, comment = null) {
			if (mode === null) {
				mode = this.newBillMode
			}
			const billToCreate = {
				what,
				comment,
				timestamp,
				payer_id: payerId,
				owerIds,
				amount,
				repeat,
				repeatallactive,
				repeatuntil,
				repeatfreq: repeatfreq ? parseInt(repeatfreq) : 1,
				paymentmodeid,
				categoryid,
			}
			const req = {
				what,
				comment,
				timestamp,
				payer: payerId,
				payedFor: owerIds.join(','),
				amount,
				repeat,
				repeatAllActive: repeatallactive ? 1 : 0,
				repeatUntil: repeatuntil,
				repeatFreq: repeatfreq ? parseInt(repeatfreq) : 1,
				paymentModeId: paymentmodeid,
				categoryId: categoryid,
			}
			this.billLoading = true
			network.createBill(this.projectId, req).then((response) => {
				this.createBillSuccess(response.data.ocs.data, billToCreate, mode)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to create bill')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			}).then(() => {
				this.createBillDone()
			})
		},
		createBillSuccess(response, billToCreate, mode) {
			const billid = response
			billToCreate.id = billid
			// only select the bill if it's a normal one or the main one in perso mode
			// const select = (mode === 'normal' || mode === 'mainPerso')
			this.$emit('bill-created', billToCreate, false, mode)
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
				const field = this.$refs['amountdum' + member.id][0]
				const val = field.value.replace(/,/g, '.')
				if (val.search('\\+|-|/|\\*') !== -1) {
					const calc = evalAlgebricFormula(val)
					result[member.id] = isNaN(calc) ? 0 : calc
				} else {
					result[member.id] = parseFloat(val) || 0
				}
			})
			return result
		},
		onCustomAmountChange(e) {
			if (e.data === ',') {
				e.target.value = e.target.value.replace(/,/g, '.')
			}

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
		onIgnoreWeightsChange(checked) {
			this.owerCustomShareAmount = this.getOwersCustomShareAmount()
		},
		onGeneratePubLinkClick() {
			const picker = getFilePickerBuilder(t('cospend', 'Choose file'))
				.setMultiSelect(false)
				.setType(FilePickerType.Choose)
				// .addMimeTypeFilter('text/csv')
				// .allowDirectories()
				// .startAt(this.outputDir)
				.addButton({
					label: t('cospend', 'Choose'),
					variant: 'primary',
					callback: (nodes) => {
						const node = nodes[0]
						const path = node.path
						this.generatePublicLinkToFile(path)
					},
				})
				.build()
			picker.pick()
			/*
				.then(async (path) => {
					this.generatePublicLinkToFile(path)
				})
			*/
		},
		generatePublicLinkToFile(targetPath) {
			network.generatePublicLinkToFile(targetPath).then((response) => {
				const filePublicUrl = window.location.protocol + '//' + window.location.host + generateUrl('/s/' + response.data.ocs.data.token)

				let what = this.myBill.what
				what = what + ' ' + filePublicUrl
				this.myBill.what = what
				this.onBillEdited()
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to generate share link to file')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		onDuplicate() {
			const owerIds = this.myBill.owerIds.filter((owerId) => {
				return this.members[owerId].activated
			})
			const billWithoutDisabledOwers = {
				...this.myBill,
				owerIds,
			}
			this.$emit('duplicate-bill', billWithoutDisabledOwers)
		},
	},
}
</script>

<style scoped lang="scss">
.bill-title {
	margin-top: 0 !important;
	padding: 20px 0px 20px 0px;
	text-align: center;
	display: flex;
	align-items: center;
	justify-content: center;

	> * {
		margin: 0 10px 0 10px;
	}

	.billFormAvatar {
		display: flex;
	}
	.duplicate-bill {
		width: 44px;
		height: 44px;
		padding: 12px;
	}
}

button {
	display: flex;
	align-items: center;
	span.icon {
		width: 22px;
	}
}

.bill-form-content {
	display: flex;
	flex-direction: column;
	flex-grow: 1;

	.bill-form {
		height: 100%;
		display: flex;
		gap: 32px;
		flex-wrap: wrap;
		margin: 0 32px 0 32px;
		padding-bottom: 32px;

		.bill-left,
		.bill-right {
			flex-grow: 1;
		}

		.bill-left {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}

		label:not(.checkboxlabel):not(.spentlabel) {
			display: flex;
			align-items: center;

			span.icon {
				margin-right: 8px;
			}
		}
	}
}

.bill-owers {
	margin-top: 12px;
	input {
		cursor: pointer;
		padding: 5px;
		min-height: 0px;
	}
}

.owerAllNoneDiv div {
	display: inline-block;
	width: 24px;
}

.owerAllNoneDiv,
.owerEntry {
	display: flex;
	align-items: center;
	gap: 4px;
	height: 34px;
	margin: 10px 0 10px 26px;
	.nc-checkbox-content {
		display: flex;
		gap: 4px;
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
	width: 100%;
	display: flex;
	gap: 4px;
	align-items: center;
	flex-grow: 1;
	input,
	select {
		flex-grow: 1;
		margin: 0;
	}
}

.bill-link-button {
	display: flex;
	justify-content: end;
	margin: 8px 0 8px 0;
}

.bill-field {
	display: flex;
	align-items: center;
	gap: 8px;

	.more-info {
		align-self: end;
	}
	.icon {
		margin-top: 24px;
	}
	&.bill-what,
	&.bill-amount {
		.icon {
			margin-top: 6px;
		}
	}
	.select {
		flex-grow: 1;
	}
	.input {
		flex-grow: 1;
		display: flex;
		flex-direction: column;
		align-items: start;
		gap: 0;

		.input-bill-comment,
		.datetime-picker {
			width: 100%;
		}
	}
}

.bill-repeat-now,
.bill-link-button {
	label {
		height: 0px;
	}
}

.repeat-now,
.link-button {
	display: flex;
	flex-direction: column;
	align-items: end;
	margin-top: 10px;
	> * {
		width: 100%;
	}
}

.bill-currency-convert {
	margin-top: 10px;
}

.bill-repeat-include {
	text-align: left;
	margin: 5px 0 5px 20px;
}

.modehint {
	max-width: 350px;
}

.checkbox-line {
	line-height: 44px;
}

#billTypeLine {
	display: flex;
}
</style>
