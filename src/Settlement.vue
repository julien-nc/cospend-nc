<template>
	<AppContentDetails class="settlement-content">
		<h2 id="settlementTitle">
			<span class="icon-reimburse icon" />
			{{ t('cospend', 'Settlement of project {name}', {name: project.name}) }}
			<button class="exportSettlement" @click="onExportClick">
				<span class="icon icon-save" />
				{{ t('cospend', 'Export') }}
			</button>
			<button
				v-if="editionAccess"
				class="autoSettlement"
				@click="onAutoSettleClick">
				<span class="icon-add" />
				{{ t('cospend', 'Add these payments to project') }}
			</button>
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
				<label for="max-date">{{ t('cospend', 'Settlement date') }}</label>
				<button class="icon icon-info"
					@click="onDateInfoClicked" />
				<DatetimePicker
					id="max-date"
					v-model="maxDate"
					class="datetime-picker"
					type="datetime"
					:placeholder="t('cospend', 'When?')"
					:minute-step="5"
					:show-second="false"
					:formatter="format"
					:clearable="true"
					confirm
					@change="onChangeMaxDate" />
				<button
					v-tooltip.bottom="{ content: t('cospend', 'Set to beginning of this day') }"
					class="icon icon-calendar-dark"
					@click="onDayBeginningClicked" />
				<button
					v-tooltip.bottom="{ content: t('cospend', 'Set to beginning of this week') }"
					class="icon icon-calendar-dark"
					@click="onWeekBeginningClicked" />
				<button
					v-tooltip.bottom="{ content: t('cospend', 'Set to beginning of this month') }"
					class="icon icon-calendar-dark"
					@click="onMonthBeginningClicked" />
			</div>
		</div>
		<hr>

		<h3>
			{{ maxTs ? t('cospend', 'Settlement plan on {date}', { date: formattedMaxDate }) : t('cospend', 'Settlement plan') }}
		</h3>
		<v-table v-if="transactions"
			id="settlementTable"
			class="coloredTable avatarTable"
			:data="transactions">
			<thead slot="head">
				<v-th sort-key="from">
					{{ t('cospend', 'Who pays?') }}
				</v-th>
				<v-th sort-key="to">
					{{ t('cospend', 'To whom?') }}
				</v-th>
				<v-th sort-key="amount">
					{{ t('cospend', 'How much?') }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="value in displayData" :key="value.from + ':' + value.to">
					<td :style="'border: 2px solid #' + myGetMemberColor(value.from) + ';'">
						<div :class="'owerAvatar' + myGetAvatarClass(value.from)">
							<div class="disabledMask" /><img :src="myGetMemberAvatar(project.id, value.from)">
						</div>
						{{ myGetSmartMemberName(project.id, value.from) }}
					</td>
					<td :style="'border: 2px solid #' + myGetMemberColor(value.to) + ';'">
						<div :class="'owerAvatar' + myGetAvatarClass(value.to)">
							<div class="disabledMask" /><img :src="myGetMemberAvatar(project.id, value.to)">
						</div>
						{{ myGetSmartMemberName(project.id, value.to) }}
					</td>
					<td>{{ value.amount.toFixed(precision) }}</td>
				</tr>
			</tbody>
		</v-table>
		<EmptyContent v-else
			class="central-empty-content"
			icon="icon-reimburse">
			{{ t('cospend', 'No transactions found') }}
		</EmptyContent>

		<h3>
			{{ maxTs ? t('cospend', 'Balances on {date}', { date: formattedMaxDate }) : t('cospend', 'Balances') }}
		</h3>
		<v-table v-if="balances"
			id="balanceTable"
			class="coloredTable avatarTable"
			:data="balances">
			<thead slot="head">
				<v-th sort-key="member.name">
					{{ t('cospend', 'Member name') }}
				</v-th>
				<v-th sort-key="balance">
					{{ t('cospend', 'Balance') }}
				</v-th>
			</thead>
			<tbody slot="body" slot-scope="{displayData}">
				<tr v-for="value in displayData"
					:key="value.mid">
					<td :style="'border: 2px solid #' + myGetMemberColor(value.mid) + ';'">
						<div :class="'owerAvatar' + myGetAvatarClass(value.mid)">
							<div class="disabledMask" /><img :src="myGetMemberAvatar(project.id, value.mid)">
						</div>{{ myGetSmartMemberName(project.id, value.mid) }}
					</td>
					<td :class="getBalanceClass(value.balance)"
						:style="'border: 2px solid #' + myGetMemberColor(value.mid) +';'">
						{{ value.balance.toFixed(2) }}
					</td>
				</tr>
			</tbody>
			<tfoot />
		</v-table>
		<EmptyContent v-else
			class="central-empty-content"
			icon="icon-cospend">
			{{ t('cospend', 'No balances found') }}
		</EmptyContent>
	</AppContentDetails>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { getSmartMemberName, getMemberAvatar } from './utils'
import cospend from './state'
import * as constants from './constants'
import * as network from './network'

import Vue from 'vue'
import { VTooltip } from 'v-tooltip'
Vue.directive('tooltip', VTooltip)

export default {
	name: 'Settlement',

	components: {
		AppContentDetails, DatetimePicker, EmptyContent,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			transactions: null,
			balancesObject: null,
			centeredOn: 0,
			maxDate: null,
			locale: getLocale(),
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
		}
	},

	computed: {
		project() {
			return cospend.projects[this.projectId]
		},
		members() {
			return cospend.members[this.projectId]
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
					}
				})
				: null
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
		myGetAvatarClass(mid) {
			return this.members[mid].activated ? '' : ' owerAvatarDisabled'
		},
		myGetSmartMemberName(pid, mid) {
			return getSmartMemberName(pid, mid)
		},
		myGetMemberAvatar(pid, mid) {
			return getMemberAvatar(pid, mid)
		},
		myGetMemberColor(mid) {
			return this.members[mid].color
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
			network.getSettlement(this.project.id, centeredOn, this.maxTs, this.getSettlementSuccess, this.getSettlementFail)
		},
		getSettlementSuccess(response) {
			if (Array.isArray(response.transactions) && response.transactions.length > 0) {
				this.transactions = response.transactions
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
		onDateInfoClicked() {
			OC.dialogs.info(
				t('cospend', 'Set a maximum date to only consider bills until then.')
				+ ' '
				+ t('cospend', 'Useful if you want to settle at a precise date and ignore the bills created since then.')
				+ ' '
				+ t('cospend', 'Automatic settlement will create bills one second before the maximum date.'),
				t('cospend', 'Info')
			)
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
	},
}
</script>

<style scoped lang="scss">
#settlement-options {
	.centered-option {
		display: flex;
		justify-content: center;
		align-items: center;

		label {
			margin-right: 5px;
		}

		.icon {
			display: inline-block;
			width: 44px;
			height: 44px;
			border-radius: var(--border-radius-pill);
			opacity: .5;

			&.icon-calendar-dark,
			&.icon-info {
				background-color: transparent;
				border: none;
				margin: 0;
			}

			&:hover,
			&:focus {
				opacity: 1;
				background-color: var(--color-background-hover);
			}
		}
	}
}

.settlement-content {
	margin-left: auto;
	margin-right: auto;

	>h3 {
		text-align: center;
	}
}

::v-deep #settlementTitle {
	text-align: left;
	padding: 20px 0px 20px 0px;

	.icon {
		min-width: 23px !important;
		min-height: 23px !important;
		width: 30px;
		vertical-align: middle;
		display: inline-block;
	}
	button {
		.icon {
			min-height: 16px !important;
			vertical-align: text-bottom;
		}
	}
}

#balanceTable {
	display: table;
	margin: 20px auto 20px auto;
}

#settlementTable {
	display: table;
	margin: 20px auto 20px auto;

	td {
		border: 1px solid var(--color-border-dark);
		padding: 0px 5px 0px 5px;
		text-align: left;
	}

	td:last-child {
		text-align: right;
		padding-right: 2px;
	}
}
</style>
