<template>
	<NcAppNavigationItem v-show="memberVisible"
		:class="{ memberItem: true }"
		:name="nameTitle"
		:active="selected"
		:editable="maintenerAccess"
		:edit-label="t('cospend', 'Rename member')"
		:force-menu="false"
		:menu-open="menuOpen"
		@contextmenu.native.stop.prevent="menuOpen = true"
		@update:menuOpen="onUpdateMenuOpen"
		@update:name="onRename"
		@click="onClick">
		<div v-if="maintenerAccess"
			slot="icon">
			<NcColorPicker
				class="app-navigation-entry-bullet-wrapper memberColorPicker"
				:value="`#${member.color}`"
				@input="updateColor">
				<template #default="{ attrs }">
					<CospendTogglableAvatar
						v-bind="attrs"
						ref="avatar"
						:enabled="member.activated"
						:color="member.color"
						:size="24"
						:disable-menu="true"
						:disable-tooltip="true"
						:is-no-user="!isUser"
						:user="member.userid || ''"
						:display-name="member.name" />
				</template>
			</NcColorPicker>
		</div>
		<div v-else
			slot="icon">
			<CospendTogglableAvatar
				:enabled="member.activated"
				:color="member.color"
				:size="24"
				:disable-menu="true"
				:disable-tooltip="true"
				:is-no-user="!isUser"
				:user="member.userid || ''"
				:display-name="member.name" />
		</div>
		<template v-if="inNavigation"
			#counter>
			<NcCounterBubble class="balance">
				<span :class="balanceClass">{{ balanceCounter }}</span>
			</NcCounterBubble>
		</template>
		<template v-if="maintenerAccess"
			#actions>
			<NcActionButton
				@click="onMenuColorClick">
				<template #icon>
					<PaletteIcon :size="20" />
				</template>
				{{ t('cospend', 'Change color') }}
			</NcActionButton>
			<NcActionInput
				ref="weightInput"
				type="number"
				step="0.01"
				:value="''"
				:disabled="false"
				@submit="onWeightSubmit">
				<template #icon>
					<WeightIcon
						class="icon"
						:size="20" />
				</template>
				{{ t('cospend', 'Weight') }} ({{ member.weight }})
			</NcActionInput>
			<NcActionButton
				:close-after-click="true"
				@click="onDeleteMemberClick">
				<template #icon>
					<DeleteIcon v-if="member.activated" :size="20" />
					<UndoIcon v-else :size="20" />
				</template>
				{{ getDeletionText() }}
			</NcActionButton>

			<NcActionSeparator v-if="showShareEdition" />
			<NcActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.NO_ACCESS, access)"
				:checked="!access"
				@change="clickAccessLevel(constants.ACCESS.NO_ACCESS)">
				{{ t('cospend', 'No access') }}
			</NcActionRadio>
			<NcActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.VIEWER, access)"
				:checked="access && access.accesslevel === constants.ACCESS.VIEWER"
				@change="clickAccessLevel(constants.ACCESS.VIEWER)">
				{{ t('cospend', 'Viewer') }}
			</NcActionRadio>
			<NcActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.PARTICIPANT, access)"
				:checked="access && access.accesslevel === constants.ACCESS.PARTICIPANT"
				@change="clickAccessLevel(constants.ACCESS.PARTICIPANT)">
				{{ t('cospend', 'Participant') }}
			</NcActionRadio>
			<NcActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.MAINTENER, access)"
				:checked="access && access.accesslevel === constants.ACCESS.MAINTENER"
				@change="clickAccessLevel(constants.ACCESS.MAINTENER)">
				{{ t('cospend', 'Maintainer') }}
			</NcActionRadio>
			<NcActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.ADMIN, access)"
				:checked="access && access.accesslevel === constants.ACCESS.ADMIN"
				@change="clickAccessLevel(constants.ACCESS.ADMIN)">
				{{ t('cospend', 'Admin') }}
			</NcActionRadio>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import PaletteIcon from 'vue-material-design-icons/Palette.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import WeightIcon from 'vue-material-design-icons/Weight.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionRadio from '@nextcloud/vue/dist/Components/NcActionRadio.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcColorPicker from '@nextcloud/vue/dist/Components/NcColorPicker.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'

import CospendTogglableAvatar from './avatar/CospendTogglableAvatar.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import cospend from '../state.js'
import * as constants from '../constants.js'
import * as network from '../network.js'
import { getSmartMemberName, delay } from '../utils.js'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'AppNavigationMemberItem',
	components: {
		CospendTogglableAvatar,
		NcAppNavigationItem,
		NcActionButton,
		NcActionRadio,
		NcActionSeparator,
		NcActionInput,
		NcColorPicker,
		NcCounterBubble,
		WeightIcon,
		PaletteIcon,
		DeleteIcon,
		UndoIcon,
	},
	props: {
		member: {
			type: Object,
			required: true,
		},
		projectId: {
			type: String,
			required: true,
		},
		selected: {
			type: Boolean,
			default: false,
		},
		inNavigation: {
			type: Boolean,
			required: true,
		},
		precision: {
			type: Number,
			default: 2,
		},
	},
	data() {
		return {
			constants,
			menuOpen: false,
		}
	},
	computed: {
		project() {
			return cospend.projects[this.projectId]
		},
		cMember() {
			return cospend.members[this.projectId][this.member.id]
		},
		myAccessLevel() {
			return this.project.myaccesslevel
		},
		maintenerAccess() {
			return this.projectId && cospend.projects[this.projectId].myaccesslevel >= constants.ACCESS.MAINTENER
		},
		editionAccess() {
			return this.projectId && cospend.projects[this.projectId].myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		showShareEdition() {
			return (this.editionAccess && this.member.userid && getCurrentUser() && !this.isCurrentUser(this.member.userid))
		},
		access() {
			for (let i = 0; i < this.project.shares.length; i++) {
				if (this.project.shares[i].type === constants.SHARE_TYPE.USER && this.project.shares[i].userid === this.member.userid) {
					return this.project.shares[i]
				}
			}
			return null
		},
		nameTitle() {
			return this.member.name + ((this.member.weight !== 1.0) ? (' (x' + this.member.weight + ')') : '')
		},
		balanceCounter() {
			const strVal = this.member.balance.toFixed(this.precision)
			return parseFloat(strVal) === 0.0
				? '0.00'
				: strVal
		},
		color() {
			return '#' + this.member.color
		},
		isUser() {
			if (this.member.userid) {
				return true
			}
			return false
		},
		smartMemberName() {
			return getSmartMemberName(this.projectId, this.member.id)
		},
		balanceClass() {
			return {
				balancePositive: this.member.balance >= 0.01,
				balanceNegative: this.member.balance <= -0.01,
			}
		},
		memberVisible() {
			const balance = this.member.balance
			return (balance >= 0.01 || balance <= -0.01 || this.member.activated)
		},
	},

	methods: {
		onClick(e) {
			if (e.target.tagName !== 'DIV') {
				this.$emit('click')
			}
		},
		getDeletionText() {
			const balance = this.member.balance
			const closeToZero = (balance < 0.01 && balance > -0.01)
			return this.member.activated
				? (closeToZero ? t('cospend', 'Delete') : t('cospend', 'Deactivate'))
				: t('cospend', 'Reactivate')
		},
		canSetAccessLevel(level, access) {
			// i must be able to edit, have at least perms of the access, have at least same perms as what i want to set
			// and i can't edit myself
			return this.editionAccess && (access === null || this.myAccessLevel >= access.accesslevel) && this.myAccessLevel >= level
				&& (access === null || !this.isCurrentUser(access.userid))
		},
		onDeleteMemberClick() {
			this.cMember.activated = !this.cMember.activated
			emit('member-edited', { projectId: this.projectId, memberId: this.member.id })
			// take care of removing access if it was added automatically
			if (this.member.userid) {
				this.deleteAccessOfUser()
			}
		},
		onRename(newName) {
			// check if name already exists
			const members = cospend.projects[this.projectId].members
			for (const mid in members) {
				if (members[mid].name === newName && parseInt(mid) !== this.member.id) {
					showError(t('cospend', 'A member is already named like that'))
					return
				}
			}

			this.cMember.name = newName
			// take care of removing access if it was added automatically
			if (this.member.userid) {
				this.deleteAccessOfUser()
			}
			this.cMember.userid = null
			emit('member-edited', { projectId: this.projectId, memberId: this.member.id })
		},
		onWeightSubmit() {
			const newWeight = this.$refs.weightInput.$el.querySelector('input[type="number"]').value
			this.cMember.weight = parseFloat(newWeight)
			emit('member-edited', { projectId: this.projectId, memberId: this.member.id })
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color)
			}, 2000)()
		},
		applyUpdateColor(color) {
			this.cMember.color = color.replace('#', '')
			emit('member-edited', { projectId: this.projectId, memberId: this.member.id })
		},
		onMenuColorClick() {
			this.menuOpen = false
			this.$refs.avatar.$el.click()
		},
		deleteAccessOfUser() {
			if (this.access !== null && !this.access.manually_added) {
				this.deleteAccess()
			}
		},
		clickAccessLevel(level) {
			if (this.access === null && level !== 0) {
				// add a shared access
				const sh = {
					user: this.member.userid,
					type: constants.SHARE_TYPE.USER,
					accesslevel: level,
					manually_added: true,
				}
				network.createSharedAccess(this.projectId, sh).then((response) => {
					const newShAccess = {
						accesslevel: sh.accesslevel,
						type: sh.type,
						name: response.data.ocs.data.name,
						userid: sh.user,
						id: response.data.ocs.data.id,
						manually_added: sh.manually_added,
					}
					this.project.shares.push(newShAccess)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to add shared access')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			} else if (this.access !== null && level === 0) {
				this.deleteAccess()
			} else if (this.access !== null) {
				// edit shared access
				network.setSharedAccessLevel(this.projectId, this.access, level).then((response) => {
					this.access.accesslevel = level
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to edit shared access level')
						+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
					)
				})
			}
		},
		deleteAccess() {
			const accessId = this.access.id
			network.deleteSharedAccess(this.projectId, this.access).then((response) => {
				this.deleteAccessSuccess(accessId)
			}).catch((error) => {
				console.error(error)
				showError(
					t('cospend', 'Failed to delete shared access')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
			})
		},
		deleteAccessSuccess(accessId) {
			const index = this.project.shares.findIndex(sh => {
				return sh.id === accessId
			})
			this.project.shares.splice(index, 1)
		},
		onUpdateMenuOpen(isOpen) {
			this.menuOpen = isOpen
		},
	},

}
</script>

<style scoped lang="scss">
.nopad {
	left: 8px;
}

.balance {
	max-width: 64px !important;
}
</style>
