<template>
	<AppNavigationItem v-show="memberVisible"
		:class="{ memberItem: true, selectedmember: selected }"
		:title="nameTitle"
		:editable="maintenerAccess"
		:edit-label="t('cospend', 'Rename member')"
		:force-menu="false"
		@update:title="onRename"
		@click="onClick">
		<div v-if="maintenerAccess"
			slot="icon"
			class="memberItemAvatar">
			<ColorPicker ref="col"
				class="app-navigation-entry-bullet-wrapper memberColorPicker"
				:value="`#${member.color}`"
				@input="updateColor">
				<div
					v-show="!member.activated"
					:class="{ disabledMask: true, nopad: !inNavigation }" />
				<ColoredAvatar
					class="itemAvatar"
					:color="member.color"
					:size="24"
					:disable-menu="true"
					:disable-tooltip="true"
					:is-no-user="!isUser"
					:user="member.userid || ''"
					:display-name="member.name" />
			</ColorPicker>
		</div>
		<div v-else
			slot="icon"
			class="memberItemAvatar">
			<div v-show="!member.activated"
				:class="{ disabledMask: true, nopad: !inNavigation }" />
			<ColoredAvatar
				class="itemAvatar"
				:color="member.color"
				:size="24"
				:disable-menu="true"
				:disable-tooltip="true"
				:is-no-user="!isUser"
				:user="member.userid || ''"
				:display-name="member.name" />
		</div>
		<template v-if="inNavigation"
			slot="counter">
			<span :class="balanceClass">{{ balanceCounter }}</span>
		</template>
		<template v-if="maintenerAccess"
			slot="actions">
			<ActionButton
				@click="onMenuColorClick">
				<template #icon>
					<PaletteIcon :size="20" />
				</template>
				{{ t('cospend', 'Change color') }}
			</ActionButton>
			<ActionInput
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
			</ActionInput>
			<ActionButton
				:close-after-click="true"
				@click="onDeleteMemberClick">
				<template #icon>
					<DeleteIcon v-if="member.activated" :size="20" />
					<UndoIcon v-else :size="20" />
				</template>
				{{ getDeletionText() }}
			</ActionButton>

			<ActionSeparator v-if="showShareEdition" />
			<ActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.NO_ACCESS, access)"
				:checked="!access"
				@change="clickAccessLevel(constants.ACCESS.NO_ACCESS)">
				{{ t('cospend', 'No access') }}
			</ActionRadio>
			<ActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.VIEWER, access)"
				:checked="access && access.accesslevel === constants.ACCESS.VIEWER"
				@change="clickAccessLevel(constants.ACCESS.VIEWER)">
				{{ t('cospend', 'Viewer') }}
			</ActionRadio>
			<ActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.PARTICIPANT, access)"
				:checked="access && access.accesslevel === constants.ACCESS.PARTICIPANT"
				@change="clickAccessLevel(constants.ACCESS.PARTICIPANT)">
				{{ t('cospend', 'Participant') }}
			</ActionRadio>
			<ActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.MAINTENER, access)"
				:checked="access && access.accesslevel === constants.ACCESS.MAINTENER"
				@change="clickAccessLevel(constants.ACCESS.MAINTENER)">
				{{ t('cospend', 'Maintainer') }}
			</ActionRadio>
			<ActionRadio
				v-if="showShareEdition"
				name="accessLevel"
				:disabled="!canSetAccessLevel(constants.ACCESS.ADMIN, access)"
				:checked="access && access.accesslevel === constants.ACCESS.ADMIN"
				@change="clickAccessLevel(constants.ACCESS.ADMIN)">
				{{ t('cospend', 'Admin') }}
			</ActionRadio>
		</template>
	</AppNavigationItem>
</template>

<script>
import PaletteIcon from 'vue-material-design-icons/Palette'
import DeleteIcon from 'vue-material-design-icons/Delete'
import UndoIcon from 'vue-material-design-icons/Undo'
import WeightIcon from 'vue-material-design-icons/Weight'
import ClickOutside from 'vue-click-outside'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import ColorPicker from '@nextcloud/vue/dist/Components/ColorPicker'
import ColoredAvatar from './ColoredAvatar'

import { getCurrentUser } from '@nextcloud/auth'
import cospend from '../state'
import * as constants from '../constants'
import * as network from '../network'
import { getSmartMemberName, delay } from '../utils'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'AppNavigationMemberItem',
	components: {
		AppNavigationItem,
		ActionButton,
		ActionRadio,
		ActionSeparator,
		ActionInput,
		ColorPicker,
		ColoredAvatar,
		WeightIcon,
		PaletteIcon,
		DeleteIcon,
		UndoIcon,
	},
	directives: {
		ClickOutside,
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
				balance: true,
				balancePositive: this.member.balance >= 0.01,
				balanceNegative: this.member.balance <= -0.01,
				alone: !this.maintenerAccess,
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
			this.$emit('member-edited', this.projectId, this.member.id)
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
					showError(t('cospend', 'A member is already named like that.'))
					return
				}
			}

			this.cMember.name = newName
			// take care of removing access if it was added automatically
			if (this.member.userid) {
				this.deleteAccessOfUser()
			}
			this.cMember.userid = null
			this.$emit('member-edited', this.projectId, this.member.id)
		},
		onWeightSubmit() {
			const newWeight = this.$refs.weightInput.$el.querySelector('input[type="number"]').value
			this.cMember.weight = parseFloat(newWeight)
			this.$emit('member-edited', this.projectId, this.member.id)
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color)
			}, 2000)()
		},
		applyUpdateColor(color) {
			this.cMember.color = color.replace('#', '')
			this.$emit('member-edited', this.projectId, this.member.id)
		},
		onMenuColorClick() {
			this.$refs.col.$el.querySelector('.trigger').click()
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
				network.addSharedAccess(this.projectId, sh).then((response) => {
					const newShAccess = {
						accesslevel: sh.accesslevel,
						type: sh.type,
						name: response.data.name,
						userid: sh.user,
						id: response.data.id,
						manually_added: sh.manually_added,
					}
					this.project.shares.push(newShAccess)
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to add shared access')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
					)
				})
			} else if (this.access !== null && level === 0) {
				this.deleteAccess()
			} else if (this.access !== null) {
				// edit shared access
				network.setAccessLevel(this.projectId, this.access, level).then((response) => {
					this.access.accesslevel = level
				}).catch((error) => {
					showError(
						t('cospend', 'Failed to edit shared access level')
						+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
					)
				})
			}
		},
		deleteAccess() {
			const accessId = this.access.id
			network.deleteAccess(this.projectId, this.access).then((response) => {
				this.deleteAccessSuccess(accessId)
			}).catch((error) => {
				console.error(error)
				showError(
					t('cospend', 'Failed to delete shared access')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		deleteAccessSuccess(accessId) {
			const index = this.project.shares.findIndex(sh => {
				return sh.id === accessId
			})
			this.project.shares.splice(index, 1)
		},
	},

}
</script>

<style scoped lang="scss">
.nopad {
	left: 8px;
}

.disabledMask {
	z-index: 99;
}

.itemAvatar {
	margin-top: 16px;
	margin-right: 2px;
}

.balance.alone {
	padding-right: 10px;
}

/* first action-input child has margin...
::v-deep .action-input {
	margin-top: 0px !important;
}
*/

::v-deep .app-navigation-entry__title {
	padding: 0 !important;
}
</style>
