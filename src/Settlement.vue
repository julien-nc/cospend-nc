<template>
	<NcAppContentDetails class="settlement-content">
		<h2 id="settlementTitle">
			<NcLoadingIcon v-if="loading" />
			<ReimburseIcon v-else :size="20" />
			<span>
				{{ t('cospend', 'Settlement of project {name}', { name: project.name }, undefined, { escape: false }) }}
			</span>
			<NcButton @click="onExportClick">
				<template #icon>
					<ContentSaveIcon :size="20" />
				</template>
				{{ t('cospend', 'Export') }}
			</NcButton>
			<NcButton v-if="editionAccess" @click="onAutoSettleClick">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('cospend', 'Add these payments to the project') }}
			</NcButton>
		</h2>
		<div id="settlement-options">
			<div id="center-settle-div" class="centered-option">
				<label for="settle-member-center">{{ t('cospend', 'Center settlement on') }}</label>
				<select id="settle-member-center" v-model="centeredOn" @change="onChangeCenterMember">
					<option value="0">
						{{ t('cospend', 'Nobody (optimal)') }}
					</option>
					<option
						v-for="(member, mid) in members"
						:key="mid"
						:value="mid">
						{{ member.name }}
					</option>
				</select>
			</div>
			<div id="max-date-settle-div" class="centered-option">
				<label for="max-date">
					{{ t('cospend', 'Settlement date') }}
				</label>
				<NcButton
					:title="t('cospend', 'Information on settlement date')"
					:aria-label="t('cospend', 'Information on settlement date')"
					@click="showDateInfo = true">
					<template #icon>
						<InformationOutlineIcon />
					</template>
				</NcButton>
				<NcDialog v-model:open="showDateInfo"
					:name="t('cospend', 'Info')"
					:message="dateInfoText" />
				<NcDateTimePickerNative
					id="max-date"
					v-model="maxDate"
					class="datetime-picker"
					type="datetime-local"
					:hide-label="true"
					@change="onChangeMaxDate" />
				<NcButton
					:title="t('cospend', 'Set to beginning of this day')"
					:aria-label="t('cospend', 'Set to beginning of this day')"
					@click="onDayBeginningClicked">
					<template #icon>
						<CalendarTodayIcon :size="20" />
					</template>
				</NcButton>
				<NcButton
					:title="t('cospend', 'Set to beginning of this week')"
					:aria-label="t('cospend', 'Set to beginning of this week')"
					@click="onWeekBeginningClicked">
					<template #icon>
						<CalendarWeekIcon :size="20" />
					</template>
				</NcButton>
				<NcButton
					:title="t('cospend', 'Set to beginning of this month')"
					:aria-label="t('cospend', 'Set to beginning of this month')"
					@click="onMonthBeginningClicked">
					<template #icon>
						<CalendarMonthIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<hr>

		<h3>
			{{ maxTs ? t('cospend', 'Settlement plan on {date}', { date: formattedMaxDate }) : t('cospend', 'Settlement plan') }}
		</h3>
		<NcLoadingIcon v-if="loading" :size="24" />
		<v-table v-else-if="transactions"
			id="settlementTable"
			class="coloredTable"
			:data="transactions">
			<template #head>
				<v-th sort-key="fromName">
					{{ t('cospend', 'Who pays?') }}
				</v-th>
				<v-th sort-key="toName">
					{{ t('cospend', 'To whom?') }}
				</v-th>
				<v-th sort-key="amount">
					{{ t('cospend', 'How much?') }}
				</v-th>
			</template>
			<template #body="{ rows }">
				<tr v-for="row in rows" :key="row.from + ':' + row.to">
					<td :style="'border: 2px solid #' + members[row.from].color + ';'">
						<div class="left-aligned-cell-content">
							<MemberAvatar
								:member="members[row.from]"
								:size="24" />
							<span>
								{{ myGetSmartMemberName(project.id, row.from) }}
							</span>
						</div>
					</td>
					<td :style="'border: 2px solid #' + members[row.to].color + ';'">
						<div class="left-aligned-cell-content">
							<MemberAvatar
								:member="members[row.to]"
								:size="24" />
							<span>
								{{ myGetSmartMemberName(project.id, row.to) }}
							</span>
						</div>
					</td>
					<td>
						{{ row.amount.toFixed(precision) }}
						<span v-if="project.currencyname">
							{{ project.currencyname }}
						</span>
					</td>
				</tr>
			</template>
		</v-table>
		<NcEmptyContent v-else
			class="central-empty-content"
			:name="t('cospend', 'No transactions found')"
			:title="t('cospend', 'No transactions found')">
			<template #icon>
				<ReimburseIcon />
			</template>
		</NcEmptyContent>

		<h3>
			{{ maxTs ? t('cospend', 'Balances on {date}', { date: formattedMaxDate }) : t('cospend', 'Global balances') }}
		</h3>
		<NcLoadingIcon v-if="loading" :size="24" />
		<v-table v-else-if="balances"
			id="balanceTable"
			class="coloredTable"
			:data="balances">
			<template #head>
				<v-th sort-key="memberName">
					{{ t('cospend', 'Member name') }}
				</v-th>
				<v-th sort-key="balance">
					{{ t('cospend', 'Balance') }}
				</v-th>
			</template>
			<template #body="{ rows }">
				<tr v-for="row in rows"
					:key="row.mid">
					<td :style="'border: 2px solid #' + members[row.mid].color + ';'">
						<div class="left-aligned-cell-content">
							<MemberAvatar
								:member="members[row.mid]"
								:size="24" />
							<span>
								{{ myGetSmartMemberName(project.id, row.mid) }}
							</span>
						</div>
					</td>
					<td :class="getBalanceClass(row.balance)"
						:style="'border: 2px solid #' + members[row.mid].color +';'">
						{{ row.balance.toFixed(2) }}
						<span v-if="project.currencyname">
							{{ project.currencyname }}
						</span>
					</td>
				</tr>
			</template>
			<tfoot />
		</v-table>
		<NcEmptyContent v-else
			class="central-empty-content"
			:name="t('cospend', 'No balances found')"
			:title="t('cospend', 'No balances found')">
			<template #icon>
				<CospendIcon />
			</template>
		</NcEmptyContent>

		<hr>
		<h2 class="individualTitle">
			<NcButton
				:title="t('cospend', 'Information on individual reimbursement')"
				:aria-label="t('cospend', 'Information on individual reimbursement')"
				@click="showIndividualInfo = true">
				<template #icon>
					<InformationOutlineIcon />
				</template>
			</NcButton>
			<span>{{ t('cospend', 'Individual reimbursement') }}</span>
		</h2>
		<NcDialog v-model:open="showIndividualInfo"
			:name="t('cospend', 'Info')"
			:message="individualInfoText" />
		<div id="individual-form">
			<select id="individual-payer" v-model="individualPayerId" @change="onChangeIndividual">
				<option value="0">
					{{ t('cospend', 'Payer') }}
				</option>
				<option
					v-for="member in membersWithNegativeBalance"
					:key="member.id"
					:value="member.id">
					{{ member.name }}
				</option>
			</select>
			&nbsp;→&nbsp;&nbsp;
			<select id="individual-receiver" v-model="individualReceiverId" @change="onChangeIndividual">
				<option :value="0">
					{{ t('cospend', 'Receiver') }}
				</option>
				<option
					v-for="member in receiverCandidates"
					:key="member.id"
					:value="member.id">
					{{ member.name }}
				</option>
			</select>
			<NcButton v-if="individualPayerId && individualReceiverId"
				@click="createIndividual">
				<span v-if="project.currencyname">
					{{ t('cospend', 'Create bill ({amount} {currencyName})', { amount: (-members[individualPayerId].balance).toFixed(precision), currencyName: project.currencyname }) }}
				</span>
				<span v-else>
					{{ t('cospend', 'Create bill ({amount})', { amount: (-members[individualPayerId].balance).toFixed(precision) }) }}
				</span>
			</NcButton>
		</div>
	</NcAppContentDetails>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CalendarTodayIcon from 'vue-material-design-icons/CalendarToday.vue'
import CalendarWeekIcon from 'vue-material-design-icons/CalendarWeek.vue'
import CalendarMonthIcon from 'vue-material-design-icons/CalendarMonth.vue'

import CospendIcon from './components/icons/CospendIcon.vue'
import ReimburseIcon from './components/icons/ReimburseIcon.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcAppContentDetails from '@nextcloud/vue/components/NcAppContentDetails'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import MemberAvatar from './components/avatar/MemberAvatar.vue'

import { showSuccess, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'
import { getSmartMemberName } from './utils.js'
import * as constants from './constants.js'
import * as network from './network.js'

export default {
	name: 'Settlement',

	components: {
		MemberAvatar,
		ReimburseIcon,
		CospendIcon,
		NcLoadingIcon,
		NcAppContentDetails,
		NcDateTimePickerNative,
		NcEmptyContent,
		NcButton,
		NcDialog,
		ContentSaveIcon,
		PlusIcon,
		InformationOutlineIcon,
		CalendarTodayIcon,
		CalendarWeekIcon,
		CalendarMonthIcon,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			cospend: OCA.Cospend.state,
			loading: false,
			transactions: null,
			balancesObject: null,
			centeredOn: 0,
			maxDate: null,
			locale: getLocale(),
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
			// individual reimbursement
			individualPayerId: 0,
			individualReceiverId: 0,
			showDateInfo: false,
			showIndividualInfo: false,
		}
	},

	computed: {
		project() {
			return this.cospend.projects[this.projectId]
		},
		members() {
			return this.cospend.members[this.projectId]
		},
		membersWithNegativeBalance() {
			return Object.values(this.members).filter((m) => {
				return m.balance <= -0.01
			})
		},
		receiverCandidates() {
			return Object.values(this.members).filter((m) => {
				return m.id !== this.individualPayerId
			})
		},
		editionAccess() {
			return (this.project.myaccesslevel >= constants.ACCESS.PARTICIPANT)
		},
		precision() {
			return this.project.precision || 2
		},
		maxTs() {
			return this.maxDate
				? moment(this.maxDate).unix()
				: null
		},
		formattedMaxDate() {
			return this.stringify(this.maxDate)
		},
		balances() {
			return this.balancesObject
				? Object.keys(this.balancesObject).map((k) => {
					return {
						mid: k,
						balance: this.balancesObject[k],
						memberName: this.getMemberName(k),
					}
				})
				: null
		},
		dateInfoText() {
			return t('cospend', 'Set a maximum date to only consider bills until then.')
				+ ' ' + t('cospend', 'Useful if you want to settle at a precise date and ignore the bills created since then.')
				+ ' ' + t('cospend', 'Automatic settlement will create bills one second before the maximum date.')
		},
		individualInfoText() {
			return t('cospend', 'This feature is useful when a member with a negative balance wants to get out of the project but you don\'t want to make a full settlement plan.')
				+ ' '
				+ t('cospend', 'Select a payer who wants to get a zero balance, then a receiver who will be the only one to get the reimbursement money.')
				+ ' '
				+ t('cospend', 'Make sure the "real" reimbursement has been done between those 2 members in real life. Then press "Create bill" to automatically create the corresponding bill.')
		},
	},

	watch: {
		projectId() {
			this.transactions = []
			this.centeredOn = 0
			this.getSettlement()
		},
	},

	mounted() {
		this.getSettlement()
	},

	methods: {
		myGetSmartMemberName(pid, mid) {
			return getSmartMemberName(pid, mid)
		},
		getMemberName(mid) {
			return this.members[mid].name
		},
		getBalanceClass(balance) {
			let balanceClass = ''
			if (balance > 0) {
				balanceClass = 'balancePositive'
			} else if (balance < 0) {
				balanceClass = 'balanceNegative'
			}
			return balanceClass
		},
		onChangeCenterMember(e) {
			this.getSettlement(e.target.value)
		},
		onChangeMaxDate(e) {
			this.getSettlement(this.centeredOn)
		},
		getSettlement(centeredOn = null) {
			this.loading = true
			network.getSettlement(this.project.id, centeredOn, this.maxTs).then((response) => {
				this.getSettlementSuccess(response.data.ocs.data)
			}).catch((error) => {
				this.getSettlementFail()
				showError(
					t('cospend', 'Failed to get settlement')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			}).then(() => {
				this.loading = false
			})
		},
		getSettlementSuccess(response) {
			if (Array.isArray(response.transactions) && response.transactions.length > 0) {
				this.transactions = response.transactions.map(t => {
					t.fromName = this.getMemberName(t.from)
					t.toName = this.getMemberName(t.to)
					return t
				})
				this.balancesObject = response.balances
			} else {
				this.getSettlementFail()
			}
		},
		getSettlementFail() {
			this.transactions = null
			this.balancesObject = null
		},
		onExportClick() {
			this.exportSettlement()
		},
		onAutoSettleClick() {
			this.autoSettlement()
		},
		autoSettlement() {
			network.autoSettlement(this.projectId, this.centeredOn, this.maxTs, this.precision, this.autoSettlementSuccess)
		},
		autoSettlementSuccess() {
			this.$emit('auto-settled', this.projectId)
			showSuccess(t('cospend', 'Project settlement bills added.'))
			this.getSettlement(this.centeredOn)
		},
		exportSettlement() {
			network.exportSettlement(this.projectId, this.centeredOn, this.maxTs, this.exportSettlementSuccess)
		},
		exportSettlementSuccess(response) {
			showSuccess(t('cospend', 'Project settlement exported in {path}', { path: response.path }))
		},
		// datetime picker formatter
		stringify(date) {
			return moment(date).locale(this.locale).format('LLL')
		},
		parse(value) {
			return moment(value, 'LLL', this.locale).toDate()
		},
		onDayBeginningClicked() {
			const begin = moment()
				.millisecond(0)
				.second(0)
				.minute(0)
				.hour(0)
			this.maxDate = begin.toDate()
			this.getSettlement(this.centeredOn)
		},
		onWeekBeginningClicked() {
			const begin = moment()
				.millisecond(0)
				.second(0)
				.minute(0)
				.hour(0)
				.day(1)
			this.maxDate = begin.toDate()
			this.getSettlement(this.centeredOn)
		},
		onMonthBeginningClicked() {
			const begin = moment()
				.millisecond(0)
				.second(0)
				.minute(0)
				.hour(0)
				.date(1)
			this.maxDate = begin.toDate()
			this.getSettlement(this.centeredOn)
		},
		// individual reimbursement
		onChangeIndividual(e) {
			if (this.individualPayerId === this.individualReceiverId) {
				this.individualReceiverId = 0
			}
		},
		createIndividual() {
			const req = {
				what: t('cospend', 'Reimbursement'),
				timestamp: moment().unix(),
				payer: this.individualPayerId,
				payedFor: '' + this.individualReceiverId,
				amount: -this.members[this.individualPayerId].balance,
				repeat: 'n',
				categoryId: '-11',
			}
			network.createBill(this.projectId, req).then((response) => {
				this.$emit('auto-settled', this.projectId)
				this.individualPayerId = 0
				this.individualReceiverId = 0
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to create bill')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#settlement-options {
	.centered-option {
		display: flex;
		justify-content: center;
		align-items: center;
		margin: 10px 0 10px 0;

		> * {
			margin: 0 5px 0 5px;
		}

		label {
			margin-right: 5px;
		}
	}
}

.settlement-content {
	margin-left: auto;
	margin-right: auto;

	>h2,
	>h3 {
		text-align: center;
	}

	.individualTitle {
		display: flex;
		align-items: center;
		justify-content: center;
		> * {
			margin: 0 5px 0 5px;
		}
	}

	#individual-form {
		display: flex;
		justify-content: center;
		align-items: center;
		margin-bottom: 50px;
	}
}

#settlementTitle {
	margin-top: 0 !important;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	padding: 20px 0px 20px 0px;
	> * {
		margin: 0 10px 0 10px;
	}
}

#balanceTable {
	display: table;
	margin: 20px auto 20px auto;
	td {
		padding: 4px 5px 4px 5px;
	}
}

#settlementTable {
	display: table;
	margin: 20px auto 20px auto;

	td {
		border: 1px solid var(--color-border-dark);
		padding: 4px 5px 4px 5px;
		text-align: left;
	}

	td:last-child {
		text-align: right;
		padding-right: 2px;
	}
}

table td span {
	vertical-align: middle;
}
</style>
