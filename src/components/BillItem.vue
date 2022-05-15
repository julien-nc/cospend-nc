<template>
	<a :href="billUrl"
		:class="{ 'app-content-list-item': true, billitem: true, selectedbill: selected, newBill: bill.id === 0}"
		:title="itemTitle"
		@click.stop.prevent="onItemClick">
		<div class="app-content-list-item-icon">
			<ColoredAvatar
				class="itemAvatar"
				:color="payerColor"
				:size="40"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:is-no-user="payerUserId === ''"
				:user="payerUserId"
				:display-name="payerName" />
			<div v-if="payerDisabled" class="billItemDisabledMask disabled" />
			<div v-if="bill.repeat !== 'n'" class="billItemRepeatMask show">
				<CalendarSyncIcon :size="16" />
			</div>
		</div>
		<div class="app-content-list-item-line-one">{{ billFormattedTitle }}</div>
		<div class="app-content-list-item-line-two">{{ parseFloat(bill.amount).toFixed(2) }} ({{ smartPayerName }} → {{ smartOwerNames }})</div>
		<span class="app-content-list-item-details">
			<span v-if="selected"
				class="bill-counter">
				{{ counter }}
			</span>
			<span>{{ billDate }}</span>
		</span>
		<div v-if="editionAccess && showDelete && (deletionEnabled || bill.id === 0)"
			:class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteBillIcon'"
			@click.prevent.stop="onDeleteClick">
			<span v-if="timerOn" class="countdown">
				<vac :end-time="new Date().getTime() + (7000)">
					<template #process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
			</span>
		</div>
		<div v-if="editionAccess && !showDelete" class="icon-selector">
			<input type="checkbox"
				:readonly="true"
				:checked="selected">
		</div>
	</a>
</template>

<script>
import CalendarSyncIcon from 'vue-material-design-icons/CalendarSync'
import cospend from '../state'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import ColoredAvatar from './ColoredAvatar'
import { reload, Timer, getCategory, getPaymentMode, getSmartMemberName } from '../utils'

export default {
	name: 'BillItem',

	components: {
		ColoredAvatar,
		CalendarSyncIcon,
	},

	props: {
		bill: {
			type: Object,
			required: true,
		},
		projectId: {
			type: String,
			required: true,
		},
		editionAccess: {
			type: Boolean,
			required: true,
		},
		index: {
			type: Number,
			required: true,
		},
		nbbills: {
			type: Number,
			required: true,
		},
		selected: {
			type: Boolean,
			required: true,
		},
		showDelete: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			timerOn: false,
			timer: null,
		}
	},

	computed: {
		billUrl() {
			return generateUrl('/apps/cospend/p/{projectId}/b/{billId}', { projectId: this.projectId, billId: this.bill.id })
		},
		undoDeleteBillStyle() {
			return 'opacity:1; background-image: url(' + generateUrl('/svg/core/actions/history?color=2AB4FF') + ');'
		},
		members() {
			return cospend.members[this.projectId]
		},
		payerDisabled() {
			return this.bill.id !== 0 && !this.members[this.bill.payer_id].activated
		},
		payerUserId() {
			return this.bill.id !== 0 && this.members[this.bill.payer_id]
				? this.members[this.bill.payer_id].userid || ''
				: ''
		},
		payerColor() {
			return (this.bill.payer_id === 0 || this.bill.id === 0)
				? '000000'
				: this.members[this.bill.payer_id]
					? this.members[this.bill.payer_id].color
					: '000000'
		},
		payerName() {
			return (this.bill.payer_id === 0 || this.bill.id === 0)
				? '*'
				: this.members[this.bill.payer_id]
					? this.members[this.bill.payer_id].name
					: ''
		},
		deletionEnabled() {
			return !cospend.projects[this.projectId].deletion_disabled
		},
		billFormattedTitle() {
			const links = this.bill.what.match(/https?:\/\/[^\s]+/gi) || []
			let linkChars = ''
			for (let i = 0; i < links.length; i++) {
				linkChars = linkChars + '  🔗'
			}
			let paymentmodeChar = ''
			let categoryChar = ''
			if (parseInt(this.bill.categoryid) !== 0) {
				categoryChar = getCategory(this.projectId, this.bill.categoryid).icon + ' '
			}
			if (parseInt(this.bill.paymentmodeid) !== 0) {
				paymentmodeChar = getPaymentMode(this.projectId, this.bill.paymentmodeid).icon + ' '
			}
			return paymentmodeChar + categoryChar + this.bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars
		},
		smartPayerName() {
			return this.bill.payer_id !== 0
				? getSmartMemberName(this.projectId, this.bill.payer_id)
				: ''
		},
		smartOwerNames() {
			const owerIds = this.bill.owerIds
			// get missing members
			let nbMissingEnabledMembers = 0
			const missingEnabledMemberIds = []
			for (const memberid in this.members) {
				if (this.members[memberid].activated
					&& !owerIds.includes(parseInt(memberid))) {
					nbMissingEnabledMembers++
					missingEnabledMemberIds.push(memberid)
				}
			}

			// 4 cases : all, all except 1, all except 2, custom
			if (nbMissingEnabledMembers === 0) {
				return t('cospend', 'Everyone')
			} else if (nbMissingEnabledMembers === 1 && owerIds.length > 2) {
				const mName = getSmartMemberName(this.projectId, missingEnabledMemberIds[0])
				return t('cospend', 'Everyone except {member}', { member: mName })
			} else if (nbMissingEnabledMembers === 2 && owerIds.length > 2) {
				const mName1 = getSmartMemberName(this.projectId, missingEnabledMemberIds[0])
				const mName2 = getSmartMemberName(this.projectId, missingEnabledMemberIds[1])
				const mName = t('cospend', '{member1} and {member2}', { member1: mName1, member2: mName2 })
				return t('cospend', 'Everyone except {member}', { member: mName })
			} else {
				let owerNames = ''
				let mid
				for (let i = 0; i < owerIds.length; i++) {
					mid = owerIds[i]
					if (!(mid in this.members)) {
						reload(t('cospend', 'Member list is not up to date. Reloading in 5 sec.'))
						return
					}
					owerNames = owerNames + getSmartMemberName(this.projectId, mid) + ', '
				}
				owerNames = owerNames.replace(/, $/, '')
				return owerNames
			}
		},
		billDate() {
			const billMom = moment.unix(this.bill.timestamp)
			return billMom.format('L')
		},
		billTime() {
			const billMom = moment.unix(this.bill.timestamp)
			return billMom.format('LT')
		},
		itemTitle() {
			return this.billFormattedTitle + '\n' + parseFloat(this.bill.amount).toFixed(2) + '\n'
				+ this.billDate + ' ' + this.billTime + '\n' + this.smartPayerName + ' → ' + this.smartOwerNames
		},
		counter() {
			return '[' + this.index + '/' + this.nbbills + ']'
		},
	},

	mounted() {
	},

	methods: {
		onItemClick() {
			this.$emit('clicked', this.bill)
		},
		onDeleteClick(e) {
			e.stopPropagation()
			if (this.timerOn) {
				this.timerOn = false
				this.timer.pause()
				delete this.timer
			} else {
				if (this.bill.id === 0) {
					this.$emit('delete', this.bill)
				} else {
					this.timerOn = true
					this.timer = new Timer(() => {
						this.timerOn = false
						this.$emit('delete', this.bill)
					}, 7000)
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.billitem {
	padding: 8px 7px;
}

.countdown {
	position: relative;
	left: -40px;
	top: -12px;
}

.newBill {
	font-style: italic;
	.itemAvatar {
		font-style: normal;
	}
}

.icon-selector {
	opacity: 1 !important;

	input {
		position: relative;
		top: -17px;
		right: 10px;
		cursor: pointer;
	}
}

.app-content-list-item-details {
	max-width: 125px !important;
}

.itemAvatar {
	position: absolute !important;
	left: 0;
}

.deleteBillIcon {
	border-radius: 50%;
	&:hover {
		background-color: var(--color-main-background);
	}
}

.billItemDisabledMask.disabled {
	display: block;
	width: 105%;
	height: 105%;
	background-image: url('../../css/images/forbidden.svg');
	margin: -1px 0 0 -1px;
	position: absolute;
}

.billItemRepeatMask.show {
	display: block;
	color: var(--color-main-text);
	width: 16px;
	height: 16px;
	margin: 33px 0 0 -4px;
	position: absolute;
}
</style>
