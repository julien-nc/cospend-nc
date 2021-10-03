<template>
	<div>
		<br>
		<div v-if="adminAccess" class="renameProject">
			<form @submit.prevent.stop="onRenameProject">
				<input
					v-model="newProjectName"
					:placeholder="t('cospend', 'Rename project {n}', { n: project.name }, undefined, { escape: false })"
					type="text">
				<input type="submit" value="" class="icon-confirm">
			</form>
			<br>
		</div>
		<div v-if="adminAccess" class="deletion-disabled-line">
			<input
				id="deletion-disabled"
				class="checkbox"
				type="checkbox"
				:checked="project.deletion_disabled"
				@input="onDisableDeletionChange">
			<label for="deletion-disabled" class="checkboxlabel">
				{{ t('cospend', 'Disable bill deletion') }}
			</label>
		</div>
		<div id="autoExport">
			<label for="autoExportSelect">
				<span class="icon icon-schedule" />
				<span>{{ t('cospend', 'Automatic export') }}</span>
			</label>
			<select id="autoExportSelect"
				:disabled="!adminAccess"
				:value="project.autoexport"
				@input="onAutoExportSet">
				<option :value="constants.FREQUENCY.NO">
					{{ t('cospend', 'No') }}
				</option>
				<option :value="constants.FREQUENCY.DAILY">
					{{ t('cospend', 'Daily') }}
				</option>
				<option :value="constants.FREQUENCY.WEEKLY">
					{{ t('cospend', 'Weekly') }}
				</option>
				<option :value="constants.FREQUENCY.MONTHLY">
					{{ t('cospend', 'Monthly') }}
				</option>
			</select>
		</div>
		<AppNavigationItem v-if="!pageIsPublic"
			icon="icon-save"
			class="exportItem"
			:title="t('cospend', 'Export project')"
			@click="onExportClick" />
		<div>
			<br><hr>
			<h3>
				<span class="icon icon-user" />
				<span class="tcontent">
					{{ t('cospend', 'Members') }}
				</span>
				<button class="icon icon-info" @click="onInfoAddClicked" />
			</h3>
			<h4
				v-if="maintenerAccess">
				<span class="icon icon-add" />
				<span class="tcontent">
					{{ t('cospend', 'Add a member') }}
				</span>
			</h4>
			<Multiselect
				v-if="maintenerAccess"
				ref="addUserInput"
				v-model="selectedAddUser"
				class="addUserInput"
				label="displayName"
				track-by="multiselectKey"
				:placeholder="newMemberPlaceholder"
				:options="formatedUsers"
				:user-select="true"
				:internal-search="true"
				@search-change="asyncFind"
				@input="clickAddUserItem">
				<template #option="{option}">
					<Avatar v-if="option.type === 's'"
						:is-no-user="true"
						:show-user-status="false"
						:user="option.name" />
					<Avatar v-else
						:is-no-user="false"
						:show-user-status="false"
						:user="option.user" />
					<span class="select-display-name">{{ option.displayName }}</span>
					<span :class="option.icon + ' select-icon'" />
				</template>
				<template #noOptions>
					{{ t('cospend', 'No recommendations. Start typing.') }}
				</template>
				<template #noResult>
					{{ t('cospend', 'No result.') }}
				</template>
			</Multiselect>
			<AppNavigationMemberItem
				v-for="member in sortedMembers"
				:key="member.id"
				:member="member"
				:project-id="project.id"
				:in-navigation="false"
				:precision="precision"
				@member-edited="onMemberEdited(member.id)" />
			<div v-if="!pageIsPublic && maintenerAccess">
				<br><hr>
				<h3>
					<span class="icon-user" />
					<span class="tcontent">
						{{ t('cospend', 'Associate a project member with a Nextcloud user') }}
					</span>
					<button class="icon icon-info" @click="onInfoAssociateClicked" />
				</h3>
				<div id="affectDiv">
					<select v-model="selectedMember">
						<option v-for="member in activeMembers"
							:key="member.id"
							:value="member.id">
							{{ member.name }}
						</option>
					</select>
					<Multiselect
						v-if="maintenerAccess"
						v-model="selectedAffectUser"
						class="affectUserInput"
						label="displayName"
						track-by="multiselectKey"
						:disabled="!selectedMember"
						:placeholder="t('cospend', 'Choose a Nextcloud user')"
						:options="formatedUsersAffect"
						:user-select="true"
						:internal-search="true"
						@search-change="asyncFind"
						@input="clickAffectUserItem">
						<template #option="{option}">
							<Avatar
								:is-no-user="false"
								:show-user-status="false"
								:user="option.user" />
							<span class="select-display-name">{{ option.displayName }}</span>
							<span :class="option.icon + ' select-icon'" />
						</template>
					</Multiselect>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'

import cospend from '../state'
import * as constants from '../constants'
import { getSortedMembers } from '../utils'
import AppNavigationMemberItem from './AppNavigationMemberItem'

export default {
	name: 'SettingsTabSidebar',
	components: {
		Multiselect, AppNavigationItem, AppNavigationMemberItem, Avatar,
	},
	props: {
		project: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			constants,
			selectedAddUser: null,
			selectedAffectUser: null,
			users: [],
			selectedMember: null,
			newProjectName: '',
			query: '',
			currentUser: getCurrentUser(),
		}
	},
	computed: {
		maintenerAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.MAINTENER
		},
		editionAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
		adminAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.ADMIN
		},
		myAccessLevel() {
			return this.project.myaccesslevel
		},
		members() {
			return cospend.members[this.projectId]
		},
		memberList() {
			return this.project.members
		},
		sortedMembers() {
			return getSortedMembers(this.memberList, cospend.memberOrder)
		},
		activeMembers() {
			return this.memberList.filter((member) => { return member.activated })
		},
		firstMid() {
			return this.activeMembers[0].id
		},
		projectId() {
			return this.project.id
		},
		precision() {
			return this.project.precision
		},
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		newMemberPlaceholder() {
			return this.pageIsPublic
				? t('cospend', 'New member name')
				: t('cospend', 'New member (or Nextcloud user) name')
		},
		formatedUsersAffect() {
			// avoid simple member here
			const result = this.unallocatedUsersAffect.map(item => {
				return {
					user: item.id,
					name: item.name,
					displayName: item.label,
					icon: 'icon-user',
					type: item.type,
					value: item.value,
					multiselectKey: item.type + ':' + item.id,
				}
			})

			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				// don't add it if it's selected
				if (lowerCurrent.match(lowerQuery)) {
					result.push({
						user: this.currentUser.uid,
						name: this.currentUser.displayName,
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
						type: 'u',
						value: this.currentUser.displayName,
						multiselectKey: 'u:' + this.currentUser.uid,
					})
				}
			}

			return result
		},
		unallocatedUsersAffect() {
			const memberList = Object.values(this.members)
			return this.users.filter((user) => {
				const foundIndex = memberList.findIndex((member) => {
					return member.userid === user.id
				})
				if (foundIndex === -1 && user.type === 'u') {
					return true
				}
				return false
			})
		},
		formatedUsers() {
			const result = this.unallocatedUsers.map(item => {
				return {
					user: item.id,
					name: item.name,
					displayName: item.label,
					icon: item.type === 'u' ? 'icon-user' : 'icon-user-dollar',
					type: item.type,
					value: item.value,
					multiselectKey: item.type + ':' + item.id,
				}
			})

			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				// don't add it if it's selected
				if (lowerCurrent.match(lowerQuery)) {
					result.push({
						user: this.currentUser.uid,
						name: this.currentUser.displayName,
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
						type: 'u',
						value: this.currentUser.displayName,
						multiselectKey: 'u:' + this.currentUser.uid,
					})
				}
			}

			return result
		},
		unallocatedUsers() {
			// prepend simple user
			const result = []
			console.debug('unallocatedUsers query ' + this.query)
			if (this.query) {
				result.push({
					id: '',
					name: this.query,
					label: this.query + ' (' + t('cospend', 'Create simple member') + ')',
					type: 's',
				})
			}

			// those not present as member yet
			const memberList = Object.values(this.members)
			const userNotMembers = this.users.filter((user) => {
				const foundIndex = memberList.findIndex((member) => {
					return member.userid === user.id
				})
				if (foundIndex === -1) {
					return true
				}
				return false
			})
			result.push(...userNotMembers)
			return result
		},
	},

	mounted() {
	},

	methods: {
		onAutoExportSet(e) {
			cospend.projects[this.projectId].autoexport = e.target.value
			this.$emit('project-edited', this.projectId)
		},
		asyncFind(query) {
			this.query = query
			if (!this.pageIsPublic) {
				if (query === '') {
					this.users = []
					return
				}
				const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
				axios.get(url, {
					params: {
						format: 'json',
						search: query,
						itemType: ' ',
						itemId: ' ',
						shareTypes: [0],
					},
				}).then((response) => {
					this.users = response.data.ocs.data.map((s) => {
						return {
							id: s.id,
							name: s.label,
							value: s.id !== s.label ? s.label + ' (' + s.id + ')' : s.label,
							label: s.id !== s.label ? s.label + ' (' + s.id + ')' : s.label,
							type: 'u',
						}
					})
				}).catch((error) => {
					console.error(error)
				})
			}
		},
		clickAddUserItem() {
			if (this.selectedAddUser === null) {
				showError(t('cospend', 'Failed to add member.'))
				return
			}
			if (this.selectedAddUser.type === 'u') {
				this.$emit('user-added', this.projectId, this.selectedAddUser.name, this.selectedAddUser.user)
			} else {
				this.$emit('new-simple-member', this.projectId, this.selectedAddUser.name)
			}
			this.selectedAddUser = null
		},
		clickAffectUserItem() {
			const member = this.members[this.selectedMember]
			this.$set(member, 'userid', this.selectedAffectUser.user)
			this.$set(member, 'name', this.selectedAffectUser.name)
			this.$emit('member-edited', this.projectId, this.selectedMember)
			this.selectedAffectUser = null
		},
		onMemberEdited(memberid) {
			this.$emit('member-edited', this.projectId, memberid)
		},
		onRenameProject() {
			cospend.projects[this.projectId].name = this.newProjectName
			this.$emit('project-edited', this.projectId)
			this.newProjectName = ''
		},
		onDisableDeletionChange(e) {
			cospend.projects[this.projectId].deletion_disabled = e.target.checked
			this.$emit('project-edited', this.projectId)
		},
		onMultiselectEnterPressed(elem) {
			// this is most likely never triggered because of the fake user
			// we add that will make the multiselect catch the event
			const name = elem.value
			this.$emit('new-simple-member', this.projectId, name)
			elem.value = ''
		},
		onExportClick() {
			this.$emit('export-clicked', this.projectId)
		},
		onInfoAddClicked() {
			OC.dialogs.info(
				t('cospend', 'You can add a simple member or a Nextcloud user to the project. You can give Nextcloud users access to the project in the context menu. You can also give access to Nextcloud users that are not members in the Sharing tab.'),
				t('cospend', 'Info')
			)
		},
		onInfoAssociateClicked() {
			OC.dialogs.info(
				t('cospend', 'Choose a project member, then a Nextcloud user to associate with.')
				+ ' ' + t('cospend', 'You can cut the link with a Nextcloud user by renaming the member.'),
				t('cospend', 'Info')
			)
		},
		focusOnAddMember() {
			console.debug(this.$refs.addUserInput)
			this.$refs.addUserInput.$el?.focus()
		},
	},
}
</script>
<style scoped lang="scss">
#autoExport {
	width: 100%;
}

.icon-schedule {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../../img/schedule.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('./../../img/schedule.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	min-width: 44px !important;
	min-height: 44px !important;
}

::v-deep .icon-user-dollar {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('./../../img/icon-user-dollar.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('./../../img/icon-user-dollar.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

#autoExport span.icon {
	display: inline-block;
	min-width: 30px !important;
	min-height: 18px !important;
	width: 41px;
	height: 18px;
	vertical-align: sub;
}

#autoExport label,
#autoExport select {
	display: inline-block;
	width: 49%;
}

.addUserInput {
	width: 100%;
	margin: 0 0 20px 0;
}

#affectDiv {
	display: flex;
}

#affectDiv select {
	margin-top: 0px;
}

#affectDiv select,
.affectUserInput {
	width: 49%;
}

.renameProject,
.newMember {
	order: 1;
	display: flex;
	height: 44px;
	form {
		display: flex;
		flex-grow: 1;
		input[type='text'] {
			flex-grow: 1;
		}
	}
}

.deletion-disabled-line {
	line-height: 44px;
	label {
		margin-left: 10px;
	}
}

.exportItem {
	z-index: 0;
}

h3, h4 {
	display: flex;
	margin-bottom: 20px;

	> .tcontent {
		flex-grow: 1;
		padding-top: 12px;
	}

	> span.icon-user {
		display: inline-block;
		min-width: 40px;
	}

	.icon {
		display: inline-block;
		width: 44px;
		height: 44px;
		border-radius: var(--border-radius-pill);
		opacity: .5;

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

h4 {
	margin: 0;
}

.select-display-name {
	margin-left: 5px;
	margin-right: auto;
}

.select-icon {
	opacity: 0.5;
}

.memberItem {
	height: 44px;
	padding-right: 0 !important;
}
</style>
