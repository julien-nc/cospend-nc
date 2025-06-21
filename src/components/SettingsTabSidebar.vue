<template>
	<div class="settings">
		<div class="settings-project">
			<div v-if="adminAccess" class="renameProject">
				<NcTextField
					v-model="newProjectName"
					:label="t('cospend', 'Rename project {n}', { n: project.name }, undefined, { escape: false })"
					placeholder="..."
					trailing-button-icon="arrowRight"
					:show-trailing-button="newProjectName !== ''"
					@trailing-button-click="onRenameProject"
					@keyup.enter="onRenameProject" />
			</div>
			<div v-if="adminAccess" class="deletion-disabled-line">
				<NcCheckboxRadioSwitch
					id="deletion-disabled"
					:model-value="project.deletiondisabled"
					@update:model-value="onDisableDeletionChange">
					{{ t('cospend', 'Disable bill deletion') }}
				</NcCheckboxRadioSwitch>
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
			<NcAppNavigationItem v-if="!pageIsPublic"
				class="exportItem"
				:name="t('cospend', 'Export project')"
				@click="onExportClick">
				<template #icon>
					<ContentSaveIcon
						:size="20" />
				</template>
			</NcAppNavigationItem>
		</div>
		<div>
			<br><hr>
			<h3>
				<AccountIcon class="icon" :size="20" />
				<span class="tcontent">
					{{ t('cospend', 'Members') }}
				</span>
				<NcButton
					:aria-label="t('cospend', 'More information on adding members')"
					@click="showInfoAdd = true">
					<template #icon>
						<InformationOutlineIcon />
					</template>
				</NcButton>
				<NcDialog v-model:open="showInfoAdd"
					:name="t('cospend', 'Info')"
					:message="t('cospend', 'You can add a simple member or a Nextcloud user to the project. You can give Nextcloud users access to the project in the context menu. You can also give access to Nextcloud users that are not members in the Sharing tab.')" />
			</h3>
			<h4 v-if="maintenerAccess">
				<PlusIcon class="icon" :size="20" />
				<span class="tcontent">
					{{ t('cospend', 'Add a member') }}
				</span>
			</h4>
			<NcSelect
				v-if="maintenerAccess"
				ref="addUserInput"
				v-model="selectedAddUser"
				class="addUserInput"
				:aria-label-combobox="t('cospend', 'Add a member')"
				label="displayName"
				track-by="multiselectKey"
				:append-to-body="false"
				:placeholder="newMemberPlaceholder"
				:options="formatedUsers"
				@search="asyncFind"
				@update:model-value="clickAddUserItem">
				<template #option="option">
					<div class="addUserSelectOption">
						<NcAvatar v-if="option.type === 's'"
							:is-no-user="true"
							:hide-status="true"
							:user="option.name" />
						<NcAvatar v-else
							:is-no-user="false"
							:hide-status="true"
							:user="option.user" />
						<span class="select-display-name">{{ option.displayName }}</span>
						<div v-if="option.type === 'u'" class="select-icon">
							<AccountIcon :size="20" />
						</div>
						<div v-else-if="option.type === 's'" class="select-icon">
							<AccountPlusIcon :size="20" />
						</div>
					</div>
				</template>
				<template #noOptions>
					{{ t('cospend', 'Enter a member name') }}
				</template>
			</NcSelect>
			<AppNavigationMemberItem
				v-for="member in sortedMembers"
				:key="member.id"
				:member="member"
				:project-id="project.id"
				:in-navigation="false"
				:precision="precision" />
			<div v-if="!pageIsPublic && maintenerAccess">
				<br><hr>
				<h3>
					<AccountIcon class="icon" :size="20" />
					<span class="tcontent">
						{{ t('cospend', 'Associate a project member with a Nextcloud user') }}
					</span>
					<NcButton
						:aria-label="t('cospend', 'More information on adding Nextcloud users as members')"
						@click="showInfoAssociate = true">
						<template #icon>
							<InformationOutlineIcon />
						</template>
					</NcButton>
					<NcDialog v-model:open="showInfoAssociate"
						:name="t('cospend', 'Info')"
						:message="t('cospend', 'Choose a project member, then a Nextcloud user to associate with.') + ' ' + t('cospend', 'You can cut the link with a Nextcloud user by renaming the member.')" />
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
					<NcSelect
						v-if="maintenerAccess"
						v-model="selectedAffectUser"
						:aria-label-combobox="t('cospend', 'Choose a Nextcloud user')"
						class="affectUserInput"
						label="displayName"
						:disabled="!selectedMemberId"
						:append-to-body="false"
						:placeholder="t('cospend', 'Choose a Nextcloud user')"
						:options="formatedUsersAffect"
						@search="asyncFind"
						@update:model-value="clickAffectUserItem">
						<template #option="option">
							<div class="affectUserSelectOption">
								<NcAvatar
									:is-no-user="false"
									:hide-status="true"
									:user="option.user" />
								<span class="select-display-name">{{ option.displayName }}</span>
								<span :class="option.icon + ' select-icon'" />
								<div class="select-icon">
									<AccountIcon :size="20" />
								</div>
							</div>
						</template>
						<template #noOptions>
							{{ t('cospend', 'Type to search users') }}
						</template>
					</NcSelect>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import CalendarMonthIcon from 'vue-material-design-icons/CalendarMonth.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import AppNavigationMemberItem from './AppNavigationMemberItem.vue'
import MemberMultiSelect from './MemberMultiSelect.vue'

import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import * as constants from '../constants.js'
import { getSortedMembers } from '../utils.js'

export default {
	name: 'SettingsTabSidebar',
	components: {
		NcSelect,
		NcAppNavigationItem,
		AppNavigationMemberItem,
		NcAvatar,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcTextField,
		MemberMultiSelect,
		CalendarMonthIcon,
		ContentSaveIcon,
		NcButton,
		AccountIcon,
		AccountPlusIcon,
		PlusIcon,
		InformationOutlineIcon,
	},
	props: {
		project: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			cospend: OCA.Cospend.state,
			constants,
			selectedAddUser: null,
			selectedAffectUser: null,
			users: [],
			selectedMemberId: null,
			newProjectName: '',
			query: '',
			currentUser: getCurrentUser(),
			showInfoAdd: false,
			showInfoAssociate: false,
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
			return this.cospend.members[this.projectId]
		},
		memberList() {
			return this.project.members
		},
		sortedMembers() {
			return getSortedMembers(this.memberList, this.cospend.memberOrder)
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
			return this.cospend.pageIsPublic
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
					id: item.type + ':' + item.id,
				}
			})

			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query and if it is not the selected project member
			if (this.currentUser && this.currentUser.uid !== this.selectedMember?.userid && this.query) {
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
						id: 'u:' + this.currentUser.uid,
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
					id: item.type + ':' + item.id,
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
						id: 'u:' + this.currentUser.uid,
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
			this.cospend.projects[this.projectId].autoexport = e.target.value
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
				showError(t('cospend', 'Failed to add member'))
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
			member.userid = this.selectedAffectUser.user
			member.name = this.selectedAffectUser.name
			emit('member-edited', { projectId: this.projectId, memberId: this.selectedMemberId })
			this.selectedAffectUser = null
			this.selectedMemberId = null
		},
		onRenameProject() {
			if (this.newProjectName) {
				this.cospend.projects[this.projectId].name = this.newProjectName
				this.$emit('project-edited', this.projectId)
				this.newProjectName = ''
			}
		},
		onDisableDeletionChange(checked) {
			this.cospend.projects[this.projectId].deletiondisabled = checked
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
		focusOnAddMember() {
			this.$refs.addUserInput.$el?.focus()
		},
	},
}
</script>
<style scoped lang="scss">
.settings {
	display: flex;
	flex-direction: column;

	&-project {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
}

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
		margin: 0;

		.material-icon {
			margin: 0 8px 0 6px;
		}
	}
}

.addUserInput {
	width: 100%;
	margin: 0 0 20px 0 !important;

	.addUserSelectOption {
		display: flex;
		align-items: center;
	}
}

#affectDiv {
	display: flex;
	flex-direction: column;
}

.affectMemberInput,
.affectUserInput {
	margin: 4px 0;

	.affectUserSelectOption {
		display: flex;
		align-items: center;
	}
}

.renameProject {
	display: flex;
	align-items: center;
	input[type='text'] {
		flex-grow: 1;
	}
}

.exportItem {
	z-index: 0;
}

h3, h4 {
	display: flex;
	align-items: center;
	gap: 8px;

	> .tcontent {
		flex-grow: 1;
	}
}

h3 {
	margin-top: 12px;
}

.select-display-name {
	margin-left: 5px;
	margin-right: auto;
}

.select-icon {
	opacity: 0.5;
}
</style>
