<template>
	<div>
		<Multiselect
			v-if="editionAccess"
			v-model="selectedSharee"
			class="shareInput"
			:placeholder="t('cospend', 'Share project with a user, group or circle …')"
			:options="formatedSharees"
			:user-select="true"
			label="displayName"
			track-by="multiselectKey"
			:internal-search="true"
			@search-change="asyncFind"
			@input="clickShareeItem">
			<template #option="{option}">
				<Avatar v-if="option.type === constants.SHARE_TYPE.USER"
					class="avatar-option"
					:user="option.user"
					:show-user-status="false" />
				<Avatar v-else-if="[constants.SHARE_TYPE.GROUP, constants.SHARE_TYPE.CIRCLE].includes(option.type)"
					class="avatar-option"
					:display-name="option.name"
					:is-no-user="true"
					:show-user-status="false" />
				<span class="multiselect-name">
					{{ option.displayName }}
				</span>
				<span v-if="option.icon && option.type !== constants.SHARE_TYPE.CIRCLE"
					:class="{ icon: true, [option.icon]: true, 'multiselect-icon': true }" />
				<span v-else-if="option.icon && option.type === constants.SHARE_TYPE.CIRCLE"
					:class="{ icon: true, [option.icon]: true, 'multiselect-icon': true }"
					:style="'background-image: url(' + circleMultiselectIconUrl + ')'" />
			</template>
		</Multiselect>

		<ul
			id="shareWithList"
			ref="shareWithList"
			class="shareWithList">
			<li v-if="editionAccess"
				class="add-public-link-line"
				@click="addLink">
				<div :class="'avatardiv icon icon-public-white' + (addingPublicLink ? ' loading' : '')" />
				<span class="username">
					{{ t('cospend', 'Add public link') }}
				</span>
				<ActionButton class="addLinkButton"
					icon="icon-add"
					:aria-label="t('cospend', 'Add link')" />
			</li>
			<li v-for="access in linkShares" :key="access.id">
				<div class="avatardiv icon icon-public-white" />
				<span class="username">
					<span>{{ t('cospend', 'Public link') }}</span>
				</span>

				<ActionButton
					v-tooltip.bottom="{ content: t('cospend', 'Copied!'), show: linkCopied[access.id], trigger: 'manual' }"
					class="copyLinkButton"
					:icon="(linkCopied[access.id]) ? 'icon-checkmark-color' : 'icon-clippy'"
					:aria-label="t('cospend', 'Copy link')"
					@click="copyLink(access)" />

				<Actions
					:force-menu="true"
					placement="bottom">
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(1, access)"
						:checked="access.accesslevel === 1"
						@change="clickAccessLevel(access, 1)">
						{{ t('cospend', 'Viewer') }}
					</ActionRadio>
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(2, access)"
						:checked="access.accesslevel === 2"
						@change="clickAccessLevel(access, 2)">
						{{ t('cospend', 'Participant') }}
					</ActionRadio>
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(3, access)"
						:checked="access.accesslevel === 3"
						@change="clickAccessLevel(access, 3)">
						{{ t('cospend', 'Maintainer') }}
					</ActionRadio>
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(4, access)"
						:checked="access.accesslevel === 4"
						@change="clickAccessLevel(access, 4)">
						{{ t('cospend', 'Admin') }}
					</ActionRadio>
					<ActionButton v-if="editionAccess && myAccessLevel > access.accesslevel"
						icon="icon-delete"
						@click="clickDeleteAccess(access)">
						{{ t('cospend', 'Delete link') }}
					</ActionButton>
				</Actions>
			</li>
			<li>
				<Avatar :disable-menu="true" :disable-tooltip="true" :user="project.userid" />
				<span class="has-tooltip username">
					{{ project.userid }}
					<span class="project-owner-label">
						({{ t('cospend', 'Project owner') }})
					</span>
				</span>
			</li>
			<li v-for="access in ugcShares" :key="access.id">
				<Avatar
					v-if="access.type === constants.SHARE_TYPE.USER"
					:user="access.userid"
					:disable-menu="true"
					:disable-tooltip="true" />
				<div v-if="access.type === constants.SHARE_TYPE.GROUP"
					class="avatardiv icon icon-group"
					:style="'background-image: url(' + groupIconUrl + ')'" />
				<div v-if="access.type === constants.SHARE_TYPE.CIRCLE"
					class="avatardiv icon fixed-icon"
					:style="'background-image: url(' + circleIconUrl + ')'" />
				<span class="username">
					<span>{{ access.name }}</span>
				</span>

				<Actions
					:force-menu="true"
					placement="bottom">
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(1, access)"
						:checked="access.accesslevel === 1"
						@change="clickAccessLevel(access, 1)">
						{{ t('cospend', 'Viewer') }}
					</ActionRadio>
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(2, access)"
						:checked="access.accesslevel === 2"
						@change="clickAccessLevel(access, 2)">
						{{ t('cospend', 'Participant') }}
					</ActionRadio>
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(3, access)"
						:checked="access.accesslevel === 3"
						@change="clickAccessLevel(access, 3)">
						{{ t('cospend', 'Maintainer') }}
					</ActionRadio>
					<ActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(4, access)"
						:checked="access.accesslevel === 4"
						@change="clickAccessLevel(access, 4)">
						{{ t('cospend', 'Admin') }}
					</ActionRadio>
					<ActionButton v-if="editionAccess && myAccessLevel > access.accesslevel"
						icon="icon-delete"
						@click="clickDeleteAccess(access)">
						{{ t('cospend', 'Delete access') }}
					</ActionButton>
				</Actions>
			</li>
		</ul>
		<hr><br>
		<ul
			id="guestList"
			class="shareWithList">
			<li>
				<div class="avatardiv icon icon-password fixed-icon"
					:style="'background-image: url(' + passwordIconUrl + ')'" />
				<span class="username">
					<span>{{ t('cospend', 'Password protected access') }}</span>
				</span>

				<ActionButton
					v-tooltip.bottom="{ content: t('cospend', 'Copied!'), show: guestLinkCopied, trigger: 'manual' }"
					class="copyLinkButton"
					:icon="guestLinkCopied ? 'icon-checkmark-color' : 'icon-clippy'"
					:aria-label="t('cospend', 'Copy link')"
					@click="copyPasswordLink" />

				<Actions
					:force-menu="true"
					placement="bottom">
					<ActionRadio name="guestAccessLevel"
						:disabled="myAccessLevel < 4"
						:checked="project.guestaccesslevel === 1"
						@change="clickGuestAccessLevel(1)">
						{{ t('cospend', 'Viewer') }}
					</ActionRadio>
					<ActionRadio name="guestAccessLevel"
						:disabled="myAccessLevel < 4"
						:checked="project.guestaccesslevel === 2"
						@change="clickGuestAccessLevel(2)">
						{{ t('cospend', 'Participant') }}
					</ActionRadio>
					<ActionRadio name="guestAccessLevel"
						:disabled="myAccessLevel < 4"
						:checked="project.guestaccesslevel === 3"
						@change="clickGuestAccessLevel(3)">
						{{ t('cospend', 'Maintainer') }}
					</ActionRadio>
					<ActionRadio name="guestAccessLevel"
						:disabled="myAccessLevel < 4"
						:checked="project.guestaccesslevel === 4"
						@change="clickGuestAccessLevel(4)">
						{{ t('cospend', 'Admin') }}
					</ActionRadio>
				</Actions>
			</li>
		</ul>
		<form v-if="myAccessLevel === 4"
			id="newPasswordForm"
			@submit.prevent.stop="setPassword">
			<label for="newPasswordInput">{{ t('cospend', 'New project password') }}</label>
			<div>
				<input id="newPasswordInput"
					ref="newPasswordInput"
					value=""
					type="password"
					autocomplete="off"
					:readonly="newPasswordReadonly"
					@focus="newPasswordReadonly = false; $event.target.select()">
				<input type="submit" value="" class="icon-confirm">
			</div>
		</form>
		<br><hr><br>
		<MoneyBusterLink
			:project="project" />
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio'

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import MoneyBusterLink from '../MoneyBusterLink'
import cospend from '../state'
import * as constants from '../constants'
import * as network from '../network'
import axios from '@nextcloud/axios'
import { Timer } from '../utils'

export default {
	name: 'SharingTabSidebar',

	components: {
		MoneyBusterLink,
		Avatar,
		Actions,
		ActionButton,
		ActionRadio,
		Multiselect,
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
			selectedSharee: null,
			sharees: [],
			guestLinkCopied: false,
			linkCopied: {},
			newPasswordReadonly: true,
			addingPublicLink: false,
			groupIconUrl: generateUrl('/svg/core/actions/group?color=000000'),
			passwordIconUrl: generateUrl('/svg/core/actions/password?color=000000'),
			circleIconUrl: generateUrl('/svg/circles/circles?color=000000'),
			circleMultiselectIconUrl: OCA.Accessibility.theme === 'dark'
				? generateUrl('/svg/circles/circles?color=ffffff')
				: generateUrl('/svg/circles/circles?color=000000'),
		}
	},

	computed: {
		editionAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.PARTICIPANT
		},
		myAccessLevel() {
			return this.project.myaccesslevel
		},
		shares() {
			return this.project.shares
		},
		linkShares() {
			return this.shares.filter((sh) => { return sh.type === constants.SHARE_TYPE.PUBLIC_LINK })
		},
		ugcShares() {
			return this.shares.filter((sh) => { return sh.type !== constants.SHARE_TYPE.PUBLIC_LINK })
		},
		projectId() {
			return this.project.id
		},
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		formatedSharees() {
			return this.unallocatedSharees.map(item => {
				const sharee = {
					user: item.id,
					manually_added: true,
					name: item.name,
					displayName: item.label,
					icon: 'icon-user',
					type: item.type,
					value: item.value,
					multiselectKey: item.type + ':' + item.id,
				}
				if (item.type === constants.SHARE_TYPE.GROUP) {
					sharee.icon = 'icon-group'
					sharee.isNoUser = true
				}
				if (item.type === constants.SHARE_TYPE.CIRCLE) {
					sharee.icon = 'icon-circle'
					sharee.isNoUser = true
				}
				return sharee
			})
		},
		// those with which the project is not shared yet
		unallocatedSharees() {
			return this.sharees.filter((sharee) => {
				let foundIndex
				if (sharee.type === constants.SHARE_TYPE.USER) {
					foundIndex = this.shares.findIndex((access) => {
						return access.userid === sharee.id && access.type === constants.SHARE_TYPE.USER
					})
				} else if (sharee.type === constants.SHARE_TYPE.GROUP) {
					foundIndex = this.shares.findIndex((access) => {
						return access.groupid === sharee.id && access.type === constants.SHARE_TYPE.GROUP
					})
				} else if (sharee.type === constants.SHARE_TYPE.CIRCLE) {
					foundIndex = this.shares.findIndex((access) => {
						return access.circleid === sharee.id && access.type === constants.SHARE_TYPE.CIRCLE
					})
				}
				if (foundIndex === -1) {
					return true
				}
				return false
			})
		},
	},

	mounted() {
	},

	methods: {
		canSetAccessLevel(level, access) {
			// i must be able to edit, have at least perms of the access, have at least same perms as what i want to set
			// and i can't edit myself
			return this.editionAccess && this.myAccessLevel >= access.accesslevel && this.myAccessLevel >= level
				&& (access.type !== constants.SHARE_TYPE.USER || !this.isCurrentUser(access.userid))
		},
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.sharees = []
				return
			}
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					shareTypes: [0, 1, 7],
				},
			}).then((response) => {
				this.sharees = response.data.ocs.data.map((s) => {
					const displayName = s.source === 'circles'
						? s.label
						: s.id !== s.label ? s.label + ' (' + s.id + ')' : s.label
					return {
						id: s.id,
						name: s.label,
						value: displayName,
						label: displayName,
						type: s.source === 'users'
							? constants.SHARE_TYPE.USER
							: s.source === 'groups'
								? constants.SHARE_TYPE.GROUP
								: constants.SHARE_TYPE.CIRCLE,
					}
				})
			}).catch((error) => {
				console.error(error)
			})
		},
		clickShareeItem() {
			this.addSharedAccess(this.selectedSharee)
		},
		addSharedAccess(sh) {
			this.addingPublicLink = true
			network.addSharedAccess(this.projectId, sh).then((response) => {
				const newShAccess = {
					accesslevel: constants.ACCESS.PARTICIPANT,
					type: sh.type,
					manually_added: sh.manually_added,
				}
				newShAccess.id = response.data.id
				if (sh.type === constants.SHARE_TYPE.PUBLIC_LINK) {
					newShAccess.token = response.data.token
				} else {
					newShAccess.name = response.data.name
					if (sh.type === constants.SHARE_TYPE.USER) {
						newShAccess.userid = sh.user
					} else if (sh.type === constants.SHARE_TYPE.GROUP) {
						newShAccess.groupid = sh.user
					} else if (sh.type === constants.SHARE_TYPE.CIRCLE) {
						newShAccess.circleid = sh.user
					}
				}
				cospend.projects[this.projectId].shares.push(newShAccess)
				this.selectedSharee = null
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to add shared access')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			}).then(() => {
				this.addingPublicLink = false
			})
		},
		clickAccessLevel(access, level) {
			network.setAccessLevel(this.projectId, access, level).then((response) => {
				access.accesslevel = level
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to edit shared access level')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		clickDeleteAccess(access) {
			// to make sure the menu disappears
			this.$refs.shareWithList.click()
			network.deleteAccess(this.projectId, access).then((response) => {
				const index = this.shares.indexOf(access)
				this.shares.splice(index, 1)
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to delete shared access')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
		async copyLink(access) {
			const publicLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/s/' + access.token)
			try {
				await this.$copyText(publicLink)
				this.$set(this.linkCopied, access.id, true)
				// eslint-disable-next-line
				new Timer(() => {
					this.$set(this.linkCopied, access.id, false)
				}, 5000)
			} catch (error) {
				console.debug(error)
				showError(t('cospend', 'Link could not be copied to clipboard.'))
			}
		},
		addLink() {
			this.addSharedAccess({ type: constants.SHARE_TYPE.PUBLIC_LINK })
		},
		setPassword() {
			const password = this.$refs.newPasswordInput.value
			if (password) {
				this.$emit('project-edited', this.projectId, password)
			} else {
				showError(t('cospend', 'Password should not be empty.'))
			}
		},
		async copyPasswordLink() {
			const guestLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/loginproject/' + this.projectId)
			try {
				await this.$copyText(guestLink)
				this.guestLinkCopied = true
				// eslint-disable-next-line
				new Timer(() => {
					this.guestLinkCopied = false
				}, 5000)
			} catch (error) {
				console.debug(error)
				showError(t('cospend', 'Link could not be copied to clipboard.'))
			}
		},
		clickGuestAccessLevel(level) {
			network.setGuestAccessLevel(this.projectId, level).then((response) => {
				cospend.projects[this.projectId].guestaccesslevel = level
				showSuccess(t('cospend', 'Guest access level changed.'))
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to edit guest access level')
					+ ': ' + (error.response?.data?.message || error.response?.request?.responseText)
				)
			})
		},
	},
}
</script>
<style scoped lang="scss">
.add-public-link-line * {
	cursor: pointer;
}

.shareInput {
	width: 100%;

	.multiselect-name {
		flex-grow: 1;
		margin-left: 10px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.multiselect-icon {
		opacity: 0.5;
	}
	.icon-circle {
		background-image: var(--icon-circles-circles-000);
		background-size: 100% 100%;
		background-repeat: no-repeat;
		background-position: center;
	}
}

.shareWithList {
	margin-bottom: 20px;
}

.shareWithList li {
	display: flex;
	align-items: center;
}

.username {
	padding: 12px 9px;
	flex-grow: 1;
}

.project-owner-label {
	opacity: .7;
}

.avatarLabel {
	padding: 6px
}

.avatardiv {
	background-color: #f5f5f5;
	border-radius: 16px;
	width: 32px;
	height: 32px;
}

#newPasswordForm div {
	width: 48%;
	display: inline-block;
}

#newPasswordForm label {
	text-align: center;
	display: inline-block;
	width: 48%;
}

.avatardiv.icon-public-white {
	background-color: var(--color-primary);
}
</style>
