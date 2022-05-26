<template>
	<div>
		<br>
		<div v-if="adminAccess" class="renameProject">
			<input
				v-model="newProjectName"
				type="text"
				:placeholder="t('cospend', 'Rename project {n}', { n: project.name }, undefined, { escape: false })"
				@keyup.enter="onRenameProject">
			<Button @click="onRenameProject">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
			</Button>
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
				<CalendarMonthIcon
					class="material-icon"
					:size="20" />
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
			class="exportItem"
			:title="t('cospend', 'Export project')"
			@click="onExportClick">
			<template #icon>
				<ContentSaveIcon
					:size="20" />
			</template>
		</AppNavigationItem>
		<div>
			<br><hr>
			<h3>
				<AccountIcon class="icon" :size="20" />
				<span class="tcontent">
					{{ t('cospend', 'Members') }}
				</span>
				<Button @click="onInfoAddClicked">
					<template #icon>
						<InformationVariantIcon :size="20" />
					</template>
				</Button>
			</h3>
			<h4 v-if="maintenerAccess">
				<PlusIcon class="icon" :size="20" />
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
					<div v-if="option.type === 'u'" class="select-icon">
						<AccountIcon :size="20" />
					</div>
					<div v-else-if="option.type === 's'" class="select-icon">
						<AccountPlusIcon :size="20" />
					</div>
				</template>
				<template #noOptions>
					{{ t('cospend', 'Enter a member name') }}
				</template>
				<!-- this slot is not forwarded yet -->
				<template #noResult>
					{{ t('cospend', 'No result') }}
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
					<AccountIcon class="icon" :size="20" />
					<span class="tcontent">
						{{ t('cospend', 'Associate a project member with a Nextcloud user') }}
					</span>
					<Button @click="onInfoAssociateClicked">
						<template #icon>
							<InformationVariantIcon :size="20" />
						</template>
					</Button>
				</h3>
				<div id="affectDiv">
					<MemberMultiSelect
						id="memberMultiSelect"
						class="affectMemberInput"
						:project-id="project.id"
						:value="selectedMember"
						:placeholder="t('cospend', 'Choose a member')"
						:members="activeMembers"
						@input="affectMemberSelected" />
					<Multiselect
						v-if="maintenerAccess"
						v-model="selectedAffectUser"
						class="affectUserInput"
						label="displayName"
						track-by="multiselectKey"
						:disabled="!selectedMemberId"
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
							<div class="select-icon">
								<AccountIcon :size="20" />
							</div>
						</template>
						<template #noOptions>
							{{ t('cospend', 'Type to search users') }}
						</template>
					</Multiselect>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import InformationVariantIcon from 'vue-material-design-icons/InformationVariant'
import AccountIcon from 'vue-material-design-icons/Account'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus'
import CheckIcon from 'vue-material-design-icons/Check'
import PlusIcon from 'vue-material-design-icons/Plus'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave'
import CalendarMonthIcon from 'vue-material-design-icons/CalendarMonth'
import Button from '@nextcloud/vue/dist/Components/Button'
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
import MemberMultiSelect from './MemberMultiSelect'

export default {
	name: 'SettingsTabSidebar',
	components: {
		Multiselect,
		AppNavigationItem,
		AppNavigationMemberItem,
		Avatar,
		MemberMultiSelect,
		CalendarMonthIcon,
		ContentSaveIcon,
		CheckIcon,
		Button,
		AccountIcon,
		AccountPlusIcon,
		PlusIcon,
		InformationVariantIcon,
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
			selectedMemberId: null,
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
		selectedMember() {
			return this.members[this.selectedMemberId]
		},
		formatedUsersAffect() {
			// avoid simple member here
			const result = this.unallocatedUsersAffect.map(item => {
				return {
					user: item.id,
					name: item.name,
					displayName: item.label,
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
		affectMemberSelected(selectedMember) {
			if (selectedMember !== null) {
				this.selectedMemberId = selectedMember.id
			}
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
			const member = this.members[this.selectedMemberId]
			this.$set(member, 'userid', this.selectedAffectUser.user)
			this.$set(member, 'name', this.selectedAffectUser.name)
			this.$emit('member-edited', this.projectId, this.selectedMemberId)
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
			this.$refs.addUserInput.$el?.focus()
		},
	},
}
</script>
<style scoped lang="scss">
#autoExport {
	width: 100%;
	display: inline-flex;
	align-items: center;

	span.icon {
		display: inline-block;
		min-width: 30px !important;
		min-height: 18px !important;
		width: 41px;
		height: 18px;
		vertical-align: sub;
	}

	label,
	select {
		display: inline-flex;
		width: 49%;

		.material-icon {
			margin: 0 12px 0 12px;
		}
	}
}

.addUserInput {
	width: 100%;
	margin: 0 0 20px 0;
}

#affectDiv {
	display: flex;
}

.affectMemberInput,
.affectUserInput {
	width: 49%;
}

.renameProject {
	display: flex;
	input[type='text'] {
		flex-grow: 1;
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

	> span.icon {
		padding: 12px 12px 12px 12px;
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
