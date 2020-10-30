<template>
	<div>
		<br>
		<div v-if="adminAccess" class="renameProject">
			<form @submit.prevent.stop="onRenameProject">
				<input
					v-model="newProjectName"
					:placeholder="t('cospend', 'Rename project {n}', { n: project.name })"
					type="text">
				<input type="submit" value="" class="icon-confirm">
			</form>
			<br>
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
				<option value="n">
					{{ t('cospend', 'No') }}
				</option>
				<option value="d">
					{{ t('cospend', 'Daily') }}
				</option>
				<option value="w">
					{{ t('cospend', 'Weekly') }}
				</option>
				<option value="m">
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
				<span class="icon-user" />
				<span class="tcontent">
					{{ t('cospend', 'Member list') }}
				</span>
				<button class="icon icon-info" @click="onInfoAddClicked" />
			</h3>
			<Multiselect
				v-if="maintenerAccess"
				ref="userMultiselect"
				v-model="selectedAddUser"
				class="addUserInput"
				label="displayName"
				track-by="multiselectKey"
				:placeholder="newMemberPlaceholder"
				:options="formatedUsers"
				:user-select="true"
				:internal-search="true"
				@input="clickAddUserItem" />
			<AppNavigationMemberItem
				v-for="member in project.members"
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
						<option v-for="member in activatedMembers"
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
						@input="clickAffectUserItem" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import cospend from '../state'
import * as constants from '../constants'
import * as network from '../network'
import AppNavigationMemberItem from './AppNavigationMemberItem'

export default {
	name: 'SettingsTabSidebar',
	components: {
		Multiselect, AppNavigationItem, AppNavigationMemberItem,
	},
	props: {
		project: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			selectedAddUser: null,
			selectedAffectUser: null,
			users: [],
			selectedMember: null,
			newProjectName: '',
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
		activatedMembers() {
			const mList = this.memberList
			const actList = []
			for (let i = 0; i < mList.length; i++) {
				if (mList[i].activated) {
					actList.push(mList[i])
				}
			}
			return actList
		},
		firstMid() {
			return this.activatedMembers[0].id
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
			return this.unallocatedUsersAffect.map(item => {
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
			return this.unallocatedUsers.map(item => {
				return {
					user: item.id,
					name: item.name,
					displayName: item.label,
					icon: item.type === 'u' ? 'icon-user' : '',
					type: item.type,
					value: item.value,
					multiselectKey: item.type + ':' + item.id,
				}
			})
		},
		// those not present as member yet
		unallocatedUsers() {
			const memberList = Object.values(this.members)
			return this.users.filter((user) => {
				const foundIndex = memberList.findIndex((member) => {
					return member.userid === user.id
				})
				if (foundIndex === -1) {
					return true
				}
				return false
			})
		},
	},

	mounted() {
		if (this.maintenerAccess) {
			this.asyncFind()

			const input = this.$refs.userMultiselect.$el.querySelector('input')
			input.addEventListener('keyup', e => {
				if (e.key === 'Enter') {
					// trick to add member when pressing enter on NC user multiselect
					// this.onMultiselectEnterPressed(e.target)
				} else {
					// add a simple user entry in multiselect when typing
					this.updateSimpleUser(e.target.value)
				}
			})
			// remove simple user when loosing focus
			input.addEventListener('blur', e => {
				this.updateSimpleUser(null)
			})
		}
	},

	methods: {
		onAutoExportSet(e) {
			this.project.autoexport = e.target.value
			this.$emit('project-edited', this.projectId)
		},
		asyncFind() {
			if (!this.pageIsPublic) {
				this.isLoading = true
				this.loadUsers()
			}
		},
		loadUsers() {
			network.loadUsers(this.loadUsersSuccess)
		},
		loadUsersSuccess(response) {
			const data = []
			let d, name, id
			for (id in response.users) {
				name = response.users[id]
				d = {
					id,
					name,
					type: 'u',
				}
				if (id !== name) {
					d.label = name + ' (' + id + ')'
					d.value = name + ' (' + id + ')'
				} else {
					d.label = name
					d.value = name
				}
				data.push(d)
			}
			// add current user
			const cu = getCurrentUser()
			data.push({
				id: cu.uid,
				name: cu.displayName,
				label: (cu.uid !== cu.displayName) ? (cu.displayName + ' (' + cu.uid + ')') : cu.uid,
				type: 'u',
			})
			this.users = data
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
			this.asyncFind()
		},
		clickAffectUserItem() {
			const member = this.members[this.selectedMember]
			this.$set(member, 'userid', this.selectedAffectUser.user)
			this.$set(member, 'name', this.selectedAffectUser.name)
			this.$emit('member-edited', this.projectId, this.selectedMember)
			this.selectedAffectUser = null
			this.asyncFind()
		},
		onMemberEdited(memberid) {
			this.$emit('member-edited', this.projectId, memberid)
		},
		onRenameProject() {
			this.project.name = this.newProjectName
			this.$emit('project-edited', this.projectId)
			this.newProjectName = ''
		},
		onMultiselectEnterPressed(elem) {
			// this is most likely never triggered because of the fake user
			// we add that will make the multiselect catch the event
			const name = elem.value
			this.$emit('new-simple-member', this.projectId, name)
			elem.value = ''
		},
		updateSimpleUser(name) {
			// delete existing simple user
			for (let i = 0; i < this.users.length; i++) {
				if (this.users[i].type === 's') {
					this.users.splice(i, 1)
					break
				}
			}
			// without this, simple member creation works once every two tries
			this.selectedAddUser = null
			// add one
			if (name !== null && name !== '') {
				this.users.unshift({
					id: '',
					name,
					label: name + ' (' + t('cospend', 'Simple member') + ')',
					type: 's',
				})
			}
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
	},
}
</script>
<style scoped lang="scss">
#autoExport {
	width: 100%;
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

.exportItem {
	z-index: 0;
}

h3 {
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
</style>
