<template>
	<a href="#"
		:billid="bill.id"
		:projectid="projectId"
		:class="{ 'app-content-list-item': true, billitem: true, selectedbill: selected, newBill: bill.id === 0}"
		:title="itemTitle"
		@click="onItemClick">
		<div class="app-content-list-item-icon"
			:style="'background-image: url(' + myGetMemberAvatar(bill.payer_id) + ');'">
			<div v-if="payerDisabled" class="billItemDisabledMask disabled" />
			<div v-if="bill.repeat !== 'n'" class="billItemRepeatMask show" />
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
		<div v-show="editionAccess"
			:class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteBillIcon'"
			@click="onDeleteClick">
			<span v-if="timerOn" class="countdown">
				<vac :end-time="new Date().getTime() + (7000)">
					<template #process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
			</span>
		</div>
	</a>
</template>

<script>
import cospend from '../state'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { reload, Timer, getCategory, getSmartMemberName, getMemberAvatar } from '../utils'

export default {
	name: 'BillItem',

	components: {
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
	},
	data() {
		return {
			timerOn: false,
			timer: null,
		}
	},

	computed: {
		undoDeleteBillStyle() {
			return 'opacity:1; background-image: url(' + generateUrl('/svg/core/actions/history?color=2AB4FF') + ');'
		},
		members() {
			return cospend.members[this.projectId]
		},
		payerDisabled() {
			return this.bill.id !== 0 && !this.members[this.bill.payer_id].activated
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
			if (this.bill.paymentmode && this.bill.paymentmode !== 'n') {
				paymentmodeChar = cospend.paymentModes[this.bill.paymentmode].icon + ' '
			}
			return paymentmodeChar + categoryChar + this.bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars
		},
		smartPayerName() {
			let memberName = ''
			if (this.bill.payer_id !== 0) {
				memberName = getSmartMemberName(this.projectId, this.bill.payer_id)
			}
			return memberName
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
		myGetMemberAvatar(mid) {
			return (this.bill.payer_id === 0 || this.bill.id === 0)
				? generateUrl('/apps/cospend/getAvatar?name=' + encodeURIComponent('*'))
				: getMemberAvatar(this.projectId, mid)
		},
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
					const that = this
					this.timer = new Timer(() => {
						that.timerOn = false
						that.$emit('delete', that.bill)
					}, 7000)
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.countdown {
	position: relative;
	left: -30px;
	top: -12px;
}

.newBill {
	font-style: italic;
}
</style>
