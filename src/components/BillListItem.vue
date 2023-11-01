<template>
	<NcListItem
		:class="{ billItem: true, newBill: bill.id === 0, selected}"
		:title="billFormattedTitle"
		:name="billFormattedTitle"
		:active="selected"
		:bold="selected"
		:details="billDetails"
		:counter-number="deleteCounter"
		:force-display-actions="true"
		@click="onItemClick">
		<template #subname>
			{{ parseFloat(bill.amount).toFixed(2) }} ({{ smartPayerName }} → {{ smartOwerNames }})
		</template>
		<template #subtitle>
			{{ parseFloat(bill.amount).toFixed(2) }} ({{ smartPayerName }} → {{ smartOwerNames }})
		</template>
		<template #icon>
			<CospendTogglableAvatar
				:enabled="!payerDisabled"
				:color="payerColor"
				:size="40"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:is-no-user="payerUserId === ''"
				:user="payerUserId"
				:display-name="payerName" />
			<div v-if="bill.repeat !== 'n'" class="billItemRepeatMask show">
				<CalendarSyncIcon :size="16" />
			</div>
		</template>
		<template #actions>
			<NcActionButton v-if="editionAccess && !selectMode && bill.id !== 0 && !payerDisabled && !timerOn"
				:close-after-click="true"
				@click="onDuplicateClick">
				<template #icon>
					<ContentDuplicateIcon
						class="icon"
						:size="20" />
				</template>
				{{ t('cospend', 'Duplicate bill') }}
			</NcActionButton>
			<NcActionButton v-if="editionAccess && !selectMode && (deletionEnabled || bill.id === 0)"
				:close-after-click="true"
				@click="onDeleteClick">
				<template #icon>
					<component :is="deleteIconComponent"
						class="icon"
						:size="20" />
				</template>
				{{ deleteIconTitle }}
			</NcActionButton>
			<NcActionButton v-if="!pageIsPublic && editionAccess && !selectMode && !timerOn && bill.id !== 0"
				:close-after-click="true"
				@click="onMoveClick">
				<template #icon>
					<SwapHorizontalIcon class="icon" :size="20" />
				</template>
				{{ moveIconTitle }}
			</NcActionButton>
		</template>
		<template #extra>
			<div v-if="editionAccess && selectMode" class="icon-selector">
				<CheckboxMarkedIcon v-if="selected" class="selected" :size="20" />
				<CheckboxBlankOutlineIcon v-else :size="20" />
			</div>
		</template>
	</NcListItem>
</template>

<script>
import CalendarSyncIcon from 'vue-material-design-icons/CalendarSync.vue'
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import SwapHorizontalIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import ContentDuplicateIcon from 'vue-material-design-icons/ContentDuplicate.vue'

import CospendTogglableAvatar from './avatar/CospendTogglableAvatar.vue'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import cospend from '../state.js'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { reload, Timer, getCategory, getPaymentMode, getSmartMemberName } from '../utils.js'

export default {
	name: 'BillListItem',

	components: {
		CospendTogglableAvatar,
		NcListItem,
		CalendarSyncIcon,
		UndoIcon,
		DeleteIcon,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		NcActionButton,
		SwapHorizontalIcon,
		ContentDuplicateIcon,
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
		selectMode: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			deleteCounter: 0,
			timer: null,
		}
	},

	computed: {
		timerOn() {
			return this.deleteCounter > 0
		},
		billUrl() {
			return generateUrl('/apps/cospend/p/{projectId}/b/{billId}', { projectId: this.projectId, billId: this.bill.id })
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
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		deletionEnabled() {
			return !cospend.projects[this.projectId].deletiondisabled
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
		billIndexText() {
			return '[' + this.index + '/' + this.nbbills + ']'
		},
		deleteIconComponent() {
			return this.timerOn
				? UndoIcon
				: DeleteIcon
		},
		deleteIconTitle() {
			return this.timerOn
				? t('cospend', 'Cancel')
				: t('cospend', 'Delete this bill')
		},
		moveIconTitle() {
			return t('cospend', 'Move bill')
		},
		billDetails() {
			return this.selected
				? this.billIndexText + ' ' + this.billDate
				: this.billDate
		},
	},

	mounted() {
	},

	methods: {
		onItemClick() {
			this.$emit('clicked', this.bill)
		},
		onMoveClick(e) {
			this.$emit('move')
		},
		onDeleteClick(e) {
			// stop timer
			if (this.timerOn) {
				this.deleteCounter = 0
				if (this.timer) {
					this.timer.pause()
					delete this.timer
				}
			} else {
				if (this.bill.id === 0) {
					this.$emit('delete', this.bill)
				} else {
					// start timer
					this.deleteCounter = 7
					this.timerLoop()
				}
			}
		},
		timerLoop() {
			// on each loop, check if finished or not
			if (this.timerOn) {
				this.timer = new Timer(() => {
					this.deleteCounter--
					this.timerLoop()
				}, 1000)
			} else {
				this.$emit('delete', this.bill)
			}
		},
		onSelectorClick(e) {
			this.$nextTick(() => this.onItemClick())
		},
		onDuplicateClick() {
			const owerIds = this.bill.owerIds.filter((owerId) => {
				return this.members[owerId].activated
			})
			const billWithoutDisabledOwers = {
				...this.bill,
				owerIds,
			}
			this.$emit('duplicate-bill', billWithoutDisabledOwers)
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .newBillAvatar * {
	color: var(--color-main-text) !important;
}

.billItem {
	list-style: none;

	&.selected :deep(.list-item) {
		background-color: var(--color-background-dark);
	}
}

.icon-selector {
	display: flex;
	justify-content: right;
	padding-right: 8px;
	position: absolute;
	right: 14px;
	bottom: 12px;
}

::v-deep .billItemRepeatMask.show {
	display: block;
	color: var(--color-main-text);
	width: 16px;
	height: 16px;
	margin: 33px 0 0 -4px;
	position: absolute;
}
</style>
