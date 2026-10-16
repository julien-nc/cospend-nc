<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcListItem
		class="billitem"
		:class="{ newBill: bill.id === 0 }"
		:title="billFormattedTitle"
		:name="billFormattedTitle"
		:active="selected"
		:bold="selected"
		:details="billDetails"
		:counter-number="deleteCounter"
		:force-display-actions="true"
		:draggable="true"
		@dragstart="onDragStart"
		@dragend="onDragEnd"
		@click="onItemClick">
		<template #subname>
			<div class="subname">
				<AlertCircleOutlineIcon v-if="bill.payersFallback"
					class="payers-fallback-marker"
					:size="16"
					:title="payersFallbackTitle" />
				<span class="subname-text">
					{{ parseFloat(bill.amount).toFixed(2) }}
					<span v-if="currencyName">
						{{ currencyName }}
					</span>
					<CalendarSyncIcon v-if="bill.repeat !== 'n'"
						:size="16" />
					({{ smartPayerName }} → {{ smartOwerNames }})
				</span>
			</div>
		</template>
		<template #indicator>
			<CursorMoveIcon v-if="isDragged" :size="20" class="icon-move" />
		</template>
		<template #extra>
			<div v-if="editionAccess && selectMode"
				class="icon-selector"
				@click="onItemClick">
				<CheckboxMarkedIcon v-if="selected" class="selected" :size="20" />
				<CheckboxBlankOutlineIcon v-else :size="20" />
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
import CursorMoveIcon from 'vue-material-design-icons/CursorMove.vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'

import MemberAvatar from './avatar/MemberAvatar.vue'

import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { emit } from '@nextcloud/event-bus'
import { reload, Timer, getBillPayerIds, getCategory, getSmartMemberName } from '../utils.js'

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
		CursorMoveIcon,
		AlertCircleOutlineIcon,
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
	emits: ['clicked', 'duplicate-bill', 'move'],
	data() {
		return {
			cospend: OCA.Cospend.state,
			deleteCounter: 0,
			timer: null,
			isDragged: false,
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
			// Any payer missing from the project is enough to block duplication.
			return this.bill.id !== 0 && this.payerIds.some(id => !this.members[id]?.activated)
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
		payersFallbackTitle() {
			// Same sentence as the form, because it reports the same fact: the split is on
			// disk but the balances are ignoring it.
			return t('cospend', 'Payers cover {covered} of {total}. Until it balances, the bill counts as paid entirely by {payer}.', {
				covered: (this.bill.payers ?? []).reduce((sum, payer) => sum + payer.amount, 0).toFixed(2),
				total: parseFloat(this.bill.amount).toFixed(2),
				payer: this.members[this.bill.payer_id]?.name ?? '?',
			})
		},
		payerIds() {
			return getBillPayerIds(this.bill, this.members)
		},
		payerNames() {
			// A payer id with no member behind it renders as a stub instead of throwing.
			return this.payerIds.map(id => (this.members[id] ? getSmartMemberName(this.projectId, id) : '?'))
		},
		smartPayerName() {
			if (this.bill.payer_id === 0) {
				return ''
			}
			const names = this.payerNames
			if (names.length === 1) {
				return names[0]
			}
			// The row has less room than a single name already needs, so the payer side gets
			// the same ladder the ower side has rather than being allowed to grow.
			const nbActivated = Object.values(this.members).filter(m => m.activated).length
			if (names.length >= nbActivated) {
				return t('cospend', 'Everyone')
			}
			if (names.length === 2) {
				return t('cospend', '{member1} and {member2}', { member1: names[0], member2: names[1] })
			}
			return n('cospend', '{member} and {n} other', '{member} and {n} others', names.length - 1,
				{ member: names[0], n: names.length - 1 })
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
		onDragStart(e) {
			e.dataTransfer.setData('projectId', this.projectId)
			e.dataTransfer.setData('billId', this.bill.id)
			e.dataTransfer.setData('payerId', this.bill.payer_id)
			// Every payer has to exist in the target project, not only the primary one.
			e.dataTransfer.setData('payerNames', JSON.stringify(
				this.payerIds.map(id => this.members[id]?.name).filter(name => name !== undefined),
			))
			this.isDragged = true
		},
		onDragEnd(e) {
			this.isDragged = false
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
	min-width: 0;
}

.subname-text {
	// the row overflows even with a single payer name, so it truncates legibly here rather
	// than being clipped mid-character by an ancestor. text-overflow has no effect on a flex
	// container, which is why this wrapper exists at all.
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.payers-fallback-marker {
	// first in the row so it survives the truncation it is warning about
	flex: 0 0 auto;
	color: var(--color-warning-text, var(--color-warning));
}

.icon-move {
	color: var(--color-element-success);
}
</style>
