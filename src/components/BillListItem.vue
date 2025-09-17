<template>
	<NcListItem
		:class="{ billItem: true, newBill: bill.id === 0}"
		:title="billFormattedTitle"
		:name="billFormattedTitle"
		:active="selected"
		:bold="selected"
		:details="billDetails"
		:counter-number="deleteCounter"
		:force-display-actions="true"
		@click="onItemClick">
		<template #subname>
			<div class="subname">
				{{ parseFloat(bill.amount).toFixed(2) }}
				<span v-if="currencyName">
					{{ currencyName }}
				</span>
				<CalendarSyncIcon v-if="bill.repeat !== 'n'"
					:size="16" />
				({{ smartPayerName }} → {{ smartOwerNames }})
			</div>
		</template>
		<template #icon>
			<MemberAvatar
				:member="billItemPayer"
				:hide-status="true"
				:size="40" />
		</template>
		<template #actions>
			<NcActionButton v-if="editionAccess && !selectMode && bill.id !== 0 && bill.deleted === 1 && !timerOn"
				:close-after-click="true"
				@click="onRestoreClick">
				<template #icon>
					<RestoreIcon />
				</template>
				{{ t('cospend', 'Restore') }}
			</NcActionButton>
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
			<NcActionButton v-if="!pageIsPublic && !isFederatedProject && editionAccess && !selectMode && !timerOn && bill.id !== 0"
				:close-after-click="true"
				@click="onMoveClick">
				<template #icon>
					<SwapHorizontalIcon class="icon" :size="20" />
				</template>
				{{ moveIconTitle }}
			</NcActionButton>
		</template>
		<template #extra>
			<div v-if="editionAccess && selectMode"
				class="icon-selector"
				@click="onItemClick">
				<CheckboxMarkedIcon v-if="selected" class="selected" :size="20" />
				<CheckboxBlankOutlineIcon v-else :size="20" />
			</div>
		</template>
	</NcListItem>
</template>

<script>
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import CalendarSyncIcon from 'vue-material-design-icons/CalendarSync.vue'
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import SwapHorizontalIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import ContentDuplicateIcon from 'vue-material-design-icons/ContentDuplicate.vue'

import MemberAvatar from './avatar/MemberAvatar.vue'

import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'
import { reload, Timer, getCategory, getSmartMemberName } from '../utils.js'

export default {
	name: 'BillListItem',

	components: {
		MemberAvatar,
		NcListItem,
		CalendarSyncIcon,
		UndoIcon,
		DeleteIcon,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		NcActionButton,
		SwapHorizontalIcon,
		ContentDuplicateIcon,
		RestoreIcon,
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
		nbBills: {
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
			cospend: OCA.Cospend.state,
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
			return this.cospend.members[this.projectId]
		},
		payer() {
			return this.members[this.bill.payer_id]
		},
		billItemPayer() {
			return this.bill.id === 0
				? {
					name: '*',
					color: '000000',
				}
				: this.payer
		},
		payerDisabled() {
			return this.bill.id !== 0 && !this.members[this.bill.payer_id].activated
		},
		pageIsPublic() {
			return this.cospend.pageIsPublic
		},
		deletionEnabled() {
			return !this.cospend.projects[this.projectId].deletiondisabled
		},
		currencyName() {
			return this.cospend.projects[this.projectId].currencyname
		},
		isFederatedProject() {
			return this.cospend.projects[this.projectId].federated
		},
		billFormattedTitle() {
			const links = this.bill.what.match(/https?:\/\/[^\s]+/gi) || []
			let linkChars = ''
			for (let i = 0; i < links.length; i++) {
				linkChars = linkChars + '  🔗'
			}
			const categoryChar = (parseInt(this.bill.categoryid) === 0)
				? ''
				: getCategory(this.projectId, this.bill.categoryid).icon + ' '
			return categoryChar + this.bill.what.replace(/https?:\/\/[^\s]+/gi, '') + linkChars
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
			return '[' + this.index + '/' + this.nbBills + ']'
		},
		deleteIconComponent() {
			return this.timerOn
				? UndoIcon
				: DeleteIcon
		},
		deleteIconTitle() {
			return this.timerOn
				? t('cospend', 'Cancel')
				: this.bill.deleted
					? t('cospend', 'Delete')
					: t('cospend', 'Move to trash')
		},
		moveIconTitle() {
			return t('cospend', 'Move to other project')
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
		onRestoreClick() {
			emit('restore-bill', this.bill)
		},
		onDeleteClick(e) {
			// delay deletion only in trashbin
			if (this.bill.deleted) {
				this.delayedDelete()
			} else {
				emit('delete-bill', this.bill)
			}
		},
		delayedDelete() {
			// stop timer
			if (this.timerOn) {
				this.deleteCounter = 0
				if (this.timer) {
					this.timer.pause()
					delete this.timer
				}
			} else {
				if (this.bill.id === 0) {
					emit('delete-bill', this.bill)
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
				emit('delete-bill', this.bill)
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
:deep(.newBillAvatar *) {
	color: var(--color-main-text) !important;
}

.icon-selector {
	cursor: pointer !important;
	display: flex;
	justify-content: right;
	padding-right: 0px;
	position: absolute;
	right: 0px;
	bottom: 15px;

	> * {
		cursor: pointer !important;
	}
}

.subname {
	display: flex;
	gap: 4px;
}
</style>
