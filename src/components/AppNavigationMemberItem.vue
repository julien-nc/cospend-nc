<template>
	<AppNavigationItem
		class="memberItem"
		:title="nameTitle"
		:forceMenu="true"
		v-show="memberVisible"
		>
		<div v-if="maintenerAccess" class="memberItemAvatar" slot="icon">
			<ColorPicker class="app-navigation-entry-bullet-wrapper memberColorPicker" :value="`#${member.color}`" @input="updateColor" ref="col">
				<div :class="{ disabledMask: true, nopad: !inNavigation }" v-show="!member.activated"></div>
				<!--img :src="memberAvatar"/-->
				<Avatar
					class="itemAvatar"
					:size="24"
					:disableMenu="true"
					:disableTooltip="true"
					:user="member.userid || ''"
					:isNoUser="!isUser"
					:url="memberAvatar" />
			</ColorPicker>
		</div>
		<div v-else class="memberItemAvatar" slot="icon">
			<div class="disabledMask" v-show="!member.activated"></div>
			<!--img :src="memberAvatar"/-->
			<Avatar
				class="itemAvatar"
				:size="24"
				:disableMenu="true"
				:disableTooltip="true"
				:user="member.userid"
				:isNoUser="!isUser"
				:url="memberAvatar" />
		</div>
		<template slot="counter" v-if="inNavigation">
			<span :class="balanceClass">{{ balanceCounter }}</span>
		</template>
		<template slot="actions" v-if="maintenerAccess">
			<ActionInput :disabled="false" icon="icon-rename" type="text" :value="member.name"
				ref="nameInput" @submit="onNameSubmit">
			</ActionInput>
			<ActionInput :disabled="false" icon="icon-quota" type="number" step="0.1" :value="''"
				ref="weightInput" @submit="onWeightSubmit">
				{{ t('cospend', 'Weight') }} ({{ member.weight }})
			</ActionInput>
			<ActionButton icon="icon-palette" @click="onMenuColorClick">
				{{ t('cospend', 'Change color') }}
			</ActionButton>
			<ActionButton :icon="member.activated ? 'icon-delete' : 'icon-history'" @click="onDeleteMemberClick">
				{{ getDeletionText() }}
			</ActionButton>

			<ActionSeparator v-if="showShareEdition" />
			<ActionRadio name="accessLevel" v-if="showShareEdition" :disabled="!canSetAccessLevel(0, access)"
				:checked="!access"
				@change="clickAccessLevel(0)">
				{{ t('cospend', 'No access') }}
			</ActionRadio>
			<ActionRadio name="accessLevel" v-if="showShareEdition" :disabled="!canSetAccessLevel(1, access)"
				:checked="access && access.accesslevel === 1"
				@change="clickAccessLevel(1)">
				{{ t('cospend', 'Viewer') }}
			</ActionRadio>
			<ActionRadio name="accessLevel" v-if="showShareEdition" :disabled="!canSetAccessLevel(2, access)"
				:checked="access && access.accesslevel === 2"
				@change="clickAccessLevel(2)">
				{{ t('cospend', 'Participant') }}
			</ActionRadio>
			<ActionRadio name="accessLevel" v-if="showShareEdition" :disabled="!canSetAccessLevel(3, access)"
				:checked="access && access.accesslevel === 3"
				@change="clickAccessLevel(3)">
				{{ t('cospend', 'Maintainer') }}
			</ActionRadio>
			<ActionRadio name="accessLevel" v-if="showShareEdition" :disabled="!canSetAccessLevel(4, access)"
				:checked="access && access.accesslevel === 4"
				@change="clickAccessLevel(4)">
				{{ t('cospend', 'Admin') }}
			</ActionRadio>
		</template>
	</AppNavigationItem>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import {
	ActionButton, AppNavigation as AppNavigationVue, AppNavigationIconBullet,
	AppNavigationSettings, AppNavigationItem, ActionInput, ActionRadio, ActionSeparator,
	ColorPicker, Avatar
} from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import cospend from '../state';
import * as constants from '../constants';
import * as network from '../network';
import { getMemberName, getSmartMemberName, getMemberAvatar, delay } from '../utils';

export default {
	name: 'AppNavigationMemberItem',
	components: {
		AppNavigationVue,
		AppNavigationItem,
		AppNavigationSettings,
		AppNavigationIconBullet,
		ActionButton, ActionRadio, ActionSeparator,
		ActionInput, ColorPicker, Avatar,
	},
	directives: {
		ClickOutside,
	},
	props: ['member', 'projectId', 'inNavigation'],
	data() {
		return {
		}
	},
	computed: {
		project() {
			return cospend.projects[this.projectId];
		},
		myAccessLevel() {
			return this.project.myaccesslevel;
		},
		maintenerAccess() {
			return this.projectId && cospend.projects[this.projectId].myaccesslevel >= constants.ACCESS.MAINTENER;
		},
		editionAccess() {
			return this.projectId && cospend.projects[this.projectId].myaccesslevel >= constants.ACCESS.PARTICIPANT;
		},
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		showShareEdition() {
			return (this.editionAccess && this.member.userid && getCurrentUser() && !this.isCurrentUser(this.member.userid))
		},
		access() {
			for (let i = 0; i < this.project.shares.length; i++) {
				if (this.project.shares[i].type === 'u' && this.project.shares[i].userid === this.member.userid) {
					return this.project.shares[i]
				}
			}
			return null;
		},
		nameTitle() {
			return this.member.name + ((this.member.weight !== 1.0) ? (' (x' + this.member.weight + ')') : '');
		},
		balanceCounter() {
			if (this.member.balance < 0.01 && this.member.balance > -0.01) {
				return '0.00';
			} else {
				return this.member.balance.toFixed(2);
			}
		},
		color() {
			return '#' + this.member.color;
		},
		memberAvatar() {
			return this.isUser
				? undefined
				: getMemberAvatar(this.projectId, this.member.id)
		},
		isUser() {
			if (this.member.userid) {
				return true
			}
			return false
		},
		smartMemberName() {
			return getSmartMemberName(this.projectId, this.member.id);
		},
		balanceClass() {
			let balanceClass = '';
			if (this.member.balance >= 0.01) {
				balanceClass = ' balancePositive';
			} else if (this.member.balance <= -0.01) {
				balanceClass = ' balanceNegative';
			}
			return 'balance ' + balanceClass;
		},
		memberVisible() {
			const balance = this.member.balance;
			return (balance >= 0.01 || balance <= -0.01 || this.member.activated);
		},
	},

	methods: {
		getDeletionText() {
			const balance = this.member.balance;
			const closeToZero = (balance < 0.01 && balance > -0.01)
			return this.member.activated ?
				(closeToZero ? t('cospend', 'Delete') : t('cospend', 'Deactivate'))
				: t('cospend', 'Reactivate')
		},
		canSetAccessLevel(level, access) {
			// i must be able to edit, have at least perms of the access, have at least same perms as what i want to set
			// and i can't edit myself
			return this.editionAccess && (access === null || this.myAccessLevel >= access.accesslevel) && this.myAccessLevel >= level &&
				(access === null || !this.isCurrentUser(access.userid))
		},
		onDeleteMemberClick() {
			this.member.activated = !this.member.activated;
			this.$emit('memberEdited', this.projectId, this.member.id);
		},
		onNameSubmit() {
			const newName = this.$refs.nameInput.$el.querySelector('input[type="text"]').value;
			this.member.name = newName;
			this.member.userid = null;
			this.$emit('memberEdited', this.projectId, this.member.id);
		},
		onWeightSubmit() {
			const newWeight = this.$refs.weightInput.$el.querySelector('input[type="number"]').value;
			this.member.weight = parseFloat(newWeight);
			this.$emit('memberEdited', this.projectId, this.member.id);
		},
		updateColor(color) {
			delay(() => {
				this.applyUpdateColor(color);
			}, 2000)();
		},
		applyUpdateColor(color) {
			this.member.color = color.replace('#', '');
			this.$emit('memberEdited', this.projectId, this.member.id);
		},
		onMenuColorClick() {
			this.$refs.col.$el.querySelector('.trigger').click();
		},
		clickAccessLevel(level) {
			if (this.access === null && level !== 0) {
				// add a shared access
				const sh = {
					user: this.member.userid,
					type: 'u',
					accesslevel: level
				}
				network.addSharedAccess(this.projectId, sh, this.addSharedAccessSuccess);
			} else if (this.access !== null && level === 0) {
				// delete shared access
				network.deleteAccess(this.projectId, this.access, this.deleteAccessSuccess);
			} else if (this.access !== null) {
				// edit shared access
				network.setAccessLevel(this.projectId, this.access, level, this.setAccessLevelSuccess);
			}
		},
		addSharedAccessSuccess(response, sh) {
			const newShAccess = {
				accesslevel: sh.accesslevel,
				type: sh.type,
				name: response.name,
				userid: sh.user,
				id: response.id
			};
			this.project.shares.push(newShAccess);
		},
		setAccessLevelSuccess(access, level) {
			access.accesslevel = level;
		},
		deleteAccessSuccess(access) {
			const index = this.project.shares.indexOf(access);
			this.project.shares.splice(index, 1);
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
::v-deep .action-input__label {
	width: 34px;
	height: 34px;
	padding: 0;
}
</style>