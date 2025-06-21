<template>
	<div>
		<NcSelect
			v-if="editionAccess"
			v-model="selectedSharee"
			class="shareInput"
			:aria-label-combobox="t('cospend', 'Share project with a user, group or circle')"
			:placeholder="t('cospend', 'Share project with a user, group or circle')"
			:options="formatedSharees"
			:filterable="false"
			:clear-search-on-blur="() => false"
			:append-to-body="false"
			label="displayName"
			@search="asyncFind"
			@update:model-value="clickShareeItem">
			<template #option="option">
				<div class="shareSelectOption">
					<NcAvatar v-if="option.type === constants.SHARE_TYPE.USER"
						class="avatar-option"
						:user="option.user"
						:hide-status="true" />
					<NcAvatar v-else-if="[constants.SHARE_TYPE.GROUP, constants.SHARE_TYPE.CIRCLE].includes(option.type)"
						class="avatar-option"
						:display-name="option.name"
						:is-no-user="true"
						:hide-status="true" />
					<div v-else-if="option.type === constants.SHARE_TYPE.FEDERATED"
						class="federated-avatar-wrapper">
						<NcAvatar
							:url="getRemoteAvatarUrl(option.user)"
							:is-no-user="true"
							:hide-status="true"
							:disable-menu="true"
							:disable-tooltip="true" />
						<span
							class="federated-avatar-wrapper__user-status"
							role="img"
							aria-hidden="false"
							:aria-label="t('cospend', 'Federated user')">
							<WebIcon :size="14" />
						</span>
					</div>
					<span class="multiselect-name">
						{{ option.displayName }}
					</span>
					<div v-if="option.type === constants.SHARE_TYPE.USER" class="multiselect-icon">
						<AccountIcon :size="20" />
					</div>
					<div v-else-if="option.type === constants.SHARE_TYPE.GROUP" class="multiselect-icon">
						<AccountGroupIcon :size="20" />
					</div>
					<div v-else-if="option.type === constants.SHARE_TYPE.CIRCLE" class="multiselect-icon">
						<GoogleCirclesCommunitiesIcon :size="20" />
					</div>
					<div v-else-if="option.type === constants.SHARE_TYPE.FEDERATED" class="multiselect-icon">
						<WebIcon :size="20" />
					</div>
				</div>
			</template>
			<template #noOptions>
				{{ t('cospend', 'Start typing to search') }}
			</template>
		</NcSelect>

		<NcModal v-if="shareLinkQrcodeUrl"
			size="small"
			@close="closeQrcodeModal">
			<div class="qrcode-modal-content">
				<div class="qrcode-wrapper">
					<QRCode render="svg"
						:link="shareLinkQrcodeUrl"
						:fgcolor="qrcodeColor"
						:image-url="qrcodeImageUrl"
						:rounded="100" />
				</div>
				<hr>
				<p>
					{{ t('cospend', 'Scan this QRCode with your mobile device to add project "{name}" in MoneyBuster or PayForMe', { name: project.name }) }}
				</p>
				<hr>
				<p>
					{{ t('cospend', 'QRCode content: ') + shareLinkQrcodeUrl }}
				</p>
			</div>
		</NcModal>

		<ul
			id="shareWithList"
			ref="shareWithList"
			class="shareWithList">
			<li v-if="editionAccess && linkShares.length === 0"
				class="add-public-link-line"
				@click="addLink">
				<div :class="'avatardiv link-icon' + (addingPublicLink ? ' loading' : '')">
					<LinkVariantIcon :size="20" />
				</div>
				<span class="username">
					{{ t('cospend', 'Share link') }}
				</span>
				<NcActions>
					<NcActionButton>
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('cospend', 'Create a new share link') }}
					</NcActionButton>
				</NcActions>
			</li>
			<li v-for="access in federatedShares" :key="'fed-' + access.id">
				<div class="federated-avatar-wrapper">
					<NcAvatar
						:display-name="access.userCloudId"
						:url="getRemoteAvatarUrl(access.userCloudId)"
						:is-no-user="true"
						:hide-status="true"
						:disable-menu="true"
						:disable-tooltip="true" />
					<span
						class="federated-avatar-wrapper__user-status"
						role="img"
						aria-hidden="false"
						:aria-label="t('cospend', 'Federated user')">
						<WebIcon :size="14" />
					</span>
				</div>
				<div :title="access.state === 0 ? t('cospend', 'Pending share') : t('cospend', 'Accepted share')">
					<HelpNetworkOutlineIcon v-if="access.state === 0"
						:size="18"
						fill-color="var(--color-warning)" />
					<CheckNetworkOutlineIcon v-else
						:size="18"
						fill-color="var(--color-success)" />
				</div>
				<span class="username">
					<span>{{ access.userCloudId + ( access.label ? ' ( ' + access.label + ' )' : '') }}</span>
				</span>
				<NcActions
					:force-menu="true"
					placement="bottom">
					<NcActionInput
						type="text"
						:model-value="access.label ?? ''"
						:disabled="!editionAccess || myAccessLevel < access.accesslevel"
						@submit="submitLabel(access, $event)">
						<template #icon>
							<TextBoxIcon :size="20" />
						</template>
						{{ t('cospend', 'Label') }}
					</NcActionInput>
					<NcActionSeparator />
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.VIEWER, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.VIEWER"
						@change="clickAccessLevel(access, constants.ACCESS.VIEWER)">
						{{ t('cospend', 'Viewer') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.PARTICIPANT, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.PARTICIPANT"
						@change="clickAccessLevel(access, constants.ACCESS.PARTICIPANT)">
						{{ t('cospend', 'Participant') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.MAINTENER, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.MAINTENER"
						@change="clickAccessLevel(access, constants.ACCESS.MAINTENER)">
						{{ t('cospend', 'Maintainer') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.ADMIN, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.ADMIN"
						@change="clickAccessLevel(access, constants.ACCESS.ADMIN)">
						{{ t('cospend', 'Admin') }}
					</NcActionRadio>
					<NcActionSeparator />
					<NcActionButton v-if="editionAccess && myAccessLevel >= access.accesslevel"
						@click="clickDeleteAccess(access)">
						<template #icon>
							<NcLoadingIcon v-if="access.loading" />
							<DeleteIcon v-else :size="20" />
						</template>
						{{ t('cospend', 'Delete federated share') }}
					</NcActionButton>
				</NcActions>
			</li>
			<li v-for="access in linkShares" :key="access.id">
				<div class="avatardiv link-icon">
					<LinkVariantIcon :size="20" />
				</div>
				<span class="username">
					<span>{{ t('cospend', 'Share link') + (access.label ? ' (' + access.label + ')' : '') }}</span>
				</span>

				<NcActions>
					<NcActionLink
						:href="generatePublicLink(access)"
						target="_blank"
						@click.stop.prevent="copyLink(access)">
						{{ linkCopied[access.id] ? t('cospend', 'Link copied') : t('cospend', 'Copy to clipboard') }}
						<template #icon>
							<ClipboardCheckOutlineIcon v-if="linkCopied[access.id]"
								class="success"
								:size="20" />
							<ContentCopyIcon v-else
								:size="16" />
						</template>
					</NcActionLink>
				</NcActions>

				<NcActions>
					<NcActionLink
						:href="generateCospendLink(access)"
						target="_blank"
						@click.stop.prevent="displayCospendLinkQRCode(access)">
						<template #icon>
							<QrcodeIcon :size="20" />
						</template>
						{{ t('cospend', 'Show QRCode for mobile clients') }}
					</NcActionLink>
				</NcActions>

				<NcActions
					:force-menu="true"
					placement="bottom">
					<NcActionInput
						type="text"
						:model-value="access.label ?? ''"
						:disabled="!editionAccess || myAccessLevel < access.accesslevel"
						@submit="submitLabel(access, $event)">
						<template #icon>
							<TextBoxIcon :size="20" />
						</template>
						{{ t('cospend', 'Label') }}
					</NcActionInput>
					<NcActionCheckbox
						:model-value="access.password !== null"
						:disabled="!editionAccess || myAccessLevel < access.accesslevel"
						@check="onPasswordCheck(access, $event)"
						@uncheck="onPasswordUncheck(access, $event)">
						{{ t('cospend', 'Password protect') }}
					</NcActionCheckbox>
					<NcActionInput
						v-if="access.password !== null"
						type="password"
						:model-value="access.password"
						:disabled="!editionAccess || myAccessLevel < access.accesslevel"
						@submit="submitPassword(access, $event)">
						<template #icon>
							<LockIcon :size="20" />
						</template>
						{{ t('cospend', 'Set link password') }}
					</NcActionInput>
					<NcActionSeparator />
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.VIEWER, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.VIEWER"
						@change="clickAccessLevel(access, constants.ACCESS.VIEWER)">
						{{ t('cospend', 'Viewer') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.PARTICIPANT, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.PARTICIPANT"
						@change="clickAccessLevel(access, constants.ACCESS.PARTICIPANT)">
						{{ t('cospend', 'Participant') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.MAINTENER, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.MAINTENER"
						@change="clickAccessLevel(access, constants.ACCESS.MAINTENER)">
						{{ t('cospend', 'Maintainer') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.ADMIN, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.ADMIN"
						@change="clickAccessLevel(access, constants.ACCESS.ADMIN)">
						{{ t('cospend', 'Admin') }}
					</NcActionRadio>
					<NcActionSeparator />
					<NcActionButton v-if="editionAccess && myAccessLevel >= access.accesslevel"
						@click="clickDeleteAccess(access)">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('cospend', 'Delete link') }}
					</NcActionButton>
					<NcActionButton v-if="editionAccess"
						:close-after-click="true"
						@click="addLink">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('cospend', 'Add another link') }}
					</NcActionButton>
				</NcActions>
			</li>
			<li>
				<NcAvatar :disable-menu="true" :disable-tooltip="true" :user="project.userid" />
				<span class="has-tooltip username">
					{{ project.userid }}
					<span class="project-owner-label">
						({{ t('cospend', 'Project owner') }})
					</span>
				</span>
			</li>
			<li v-for="access in ugcShares" :key="access.id">
				<NcAvatar
					v-if="access.type === constants.SHARE_TYPE.USER"
					:user="access.userid"
					:disable-menu="true"
					:disable-tooltip="true" />
				<div v-if="access.type === constants.SHARE_TYPE.GROUP"
					class="avatardiv link-icon">
					<AccountGroupIcon :size="20" />
				</div>
				<div v-if="access.type === constants.SHARE_TYPE.CIRCLE"
					class="avatardiv link-icon">
					<GoogleCirclesCommunitiesIcon :size="20" />
				</div>
				<span class="username">
					<span>{{ access.name }} ({{ access.userid }})</span>
				</span>

				<NcActions
					:force-menu="true"
					placement="bottom">
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.VIEWER, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.VIEWER"
						@change="clickAccessLevel(access, constants.ACCESS.VIEWER)">
						{{ t('cospend', 'Viewer') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.PARTICIPANT, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.PARTICIPANT"
						@change="clickAccessLevel(access, constants.ACCESS.PARTICIPANT)">
						{{ t('cospend', 'Participant') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.MAINTENER, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.MAINTENER"
						@change="clickAccessLevel(access, constants.ACCESS.MAINTENER)">
						{{ t('cospend', 'Maintainer') }}
					</NcActionRadio>
					<NcActionRadio name="accessLevel"
						:disabled="!canSetAccessLevel(constants.ACCESS.ADMIN, access)"
						:model-value="access.accesslevel"
						:value="constants.ACCESS.ADMIN"
						@change="clickAccessLevel(access, constants.ACCESS.ADMIN)">
						{{ t('cospend', 'Admin') }}
					</NcActionRadio>
					<NcActionButton v-if="editionAccess && myAccessLevel >= access.accesslevel"
						@click="clickDeleteAccess(access)">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
						{{ t('cospend', 'Delete access') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>
	</div>
</template>

<script>
import HelpNetworkOutlineIcon from 'vue-material-design-icons/HelpNetworkOutline.vue'
import CheckNetworkOutlineIcon from 'vue-material-design-icons/CheckNetworkOutline.vue'
import GoogleCirclesCommunitiesIcon from 'vue-material-design-icons/GoogleCirclesCommunities.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TextBoxIcon from 'vue-material-design-icons/TextBox.vue'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import QrcodeIcon from 'vue-material-design-icons/Qrcode.vue'
import WebIcon from 'vue-material-design-icons/Web.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcModal from '@nextcloud/vue/components/NcModal'

import QRCode from './QRCode.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import * as constants from '../constants.js'
import * as network from '../network.js'
import axios from '@nextcloud/axios'

import { Timer, hexToDarkerHex, getComplementaryColor } from '../utils.js'

export default {
	name: 'SharingTabSidebar',

	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
		NcActionRadio,
		NcActionInput,
		NcActionCheckbox,
		NcActionLink,
		NcActionSeparator,
		NcSelect,
		NcModal,
		NcLoadingIcon,
		QRCode,
		QrcodeIcon,
		LockIcon,
		TextBoxIcon,
		DeleteIcon,
		PlusIcon,
		WebIcon,
		ClipboardCheckOutlineIcon,
		LinkVariantIcon,
		AccountIcon,
		AccountGroupIcon,
		GoogleCirclesCommunitiesIcon,
		CheckNetworkOutlineIcon,
		HelpNetworkOutlineIcon,
		ContentCopyIcon,
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
			selectedSharee: null,
			sharees: [],
			linkCopied: {},
			addingPublicLink: false,
			shareLinkQrcodeUrl: null,
			qrcodeColor: OCA.Cospend.state.themeColorDark,
			// the svg api is dead, glory to the svg api
			qrcodeImageUrl: generateUrl(
				'/apps/cospend/svg/cospend_square_bg?color='
					+ hexToDarkerHex(getComplementaryColor(OCA.Cospend.state.themeColorDark)).replace('#', ''),
			),
			federatedUserStatus: {
				status: null,
				message: null,
				icon: '€',
			},
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
			return this.shares.filter(sh => sh.type === constants.SHARE_TYPE.PUBLIC_LINK)
		},
		federatedShares() {
			return this.shares.filter(sh => sh.type === constants.SHARE_TYPE.FEDERATED)
		},
		ugcShares() {
			return this.shares.filter(sh => [constants.SHARE_TYPE.USER, constants.SHARE_TYPE.GROUP, constants.SHARE_TYPE.CIRCLE].includes(sh.type))
		},
		projectId() {
			return this.project.id
		},
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		formatedSharees() {
			const formatedSharees = this.unallocatedSharees.map(item => {
				return {
					user: item.id,
					manually_added: true,
					name: item.name,
					displayName: item.label,
					type: item.type,
					value: item.value,
					id: item.type + ':' + item.id,
				}
			})
			console.debug('[cospend] formatedSharees', formatedSharees)
			return formatedSharees
		},
		// those with which the project is not shared yet
		unallocatedSharees() {
			return this.sharees.filter(sharee => {
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
				} else if (sharee.type === constants.SHARE_TYPE.FEDERATED) {
					foundIndex = this.shares.findIndex((access) => {
						return access.userCloudId === sharee.id && access.type === constants.SHARE_TYPE.FEDERATED
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
		getRemoteAvatarUrl(cloudId) {
			return network.getRemoteAvatarUrl(cloudId)
		},
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
					shareTypes: [0, 1, 6, 7],
				},
			}).then((response) => {
				this.sharees = response.data.ocs.data.map((s) => {
					const displayName = ['circles', 'remotes'].includes(s.source)
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
								: s.source === 'remotes'
									? constants.SHARE_TYPE.FEDERATED
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
			network.createSharedAccess(this.projectId, sh).then((response) => {
				const newShAccess = {
					accesslevel: constants.ACCESS.PARTICIPANT,
					type: sh.type,
					manually_added: sh.manually_added,
					password: sh.password,
				}
				newShAccess.id = response.data.ocs.data.id
				if (sh.type === constants.SHARE_TYPE.PUBLIC_LINK) {
					newShAccess.userid = response.data.ocs.data.userid
					this.copyLink(newShAccess)
				} else if (sh.type === constants.SHARE_TYPE.FEDERATED) {
					newShAccess.userCloudId = response.data.ocs.data.userCloudId
					newShAccess.state = response.data.ocs.data.state
				} else {
					newShAccess.name = response.data.ocs.data.name
					if (sh.type === constants.SHARE_TYPE.USER) {
						newShAccess.userid = sh.user
					} else if (sh.type === constants.SHARE_TYPE.GROUP) {
						newShAccess.groupid = sh.user
					} else if (sh.type === constants.SHARE_TYPE.CIRCLE) {
						newShAccess.circleid = sh.user
					}
				}
				this.cospend.projects[this.projectId].shares.push(newShAccess)
				this.selectedSharee = null
			}).catch((error) => {
				showError(t('cospend', 'Failed to add shared access'))
				console.error(error)
			}).then(() => {
				this.addingPublicLink = false
			})
		},
		clickAccessLevel(access, level) {
			network.setSharedAccessLevel(this.projectId, access, level).then((response) => {
				access.accesslevel = level
			}).catch((error) => {
				showError(t('cospend', 'Failed to edit shared access level'))
				console.error(error)
			})
		},
		onPasswordCheck(access) {
			access.password = ''
		},
		onPasswordUncheck(access) {
			this.savePassword(access, '')
		},
		submitPassword(access, e) {
			const password = e.target[0].value
			this.savePassword(access, password)
		},
		savePassword(access, password) {
			network.editSharedAccess(this.projectId, access, null, password).then((response) => {
				if (password === '') {
					access.password = null
				} else {
					access.password = password
				}
				showSuccess(t('cospend', 'Share link saved'))
			}).catch((error) => {
				showError(
					t('cospend', 'Failed to edit share link')
					+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
				)
				console.error(error)
			})
		},
		submitLabel(access, e) {
			const label = e.target[0].value
			network.editSharedAccess(this.projectId, access, label, null).then((response) => {
				access.label = label
				showSuccess(
					access.type === constants.SHARE_TYPE.FEDERATED
						? t('cospend', 'Federated share saved')
						: t('cospend', 'Share link saved'),
				)
			}).catch((error) => {
				showError(t('cospend', 'Failed to edit share link'))
				console.error(error)
			})
		},
		clickDeleteAccess(access) {
			access.loading = true
			// to make sure the menu disappears
			this.$refs.shareWithList.click()
			network.deleteSharedAccess(this.projectId, access).then((response) => {
				const index = this.shares.indexOf(access)
				this.shares.splice(index, 1)
			}).catch((error) => {
				showError(t('cospend', 'Failed to delete shared access'))
				console.error(error)
				access.loading = false
			})
		},
		generatePublicLink(access) {
			return window.location.protocol + '//' + window.location.host + generateUrl('/apps/cospend/s/' + access.userid)
		},
		async copyLink(access) {
			const publicLink = this.generatePublicLink(access)
			try {
				await navigator.clipboard.writeText(publicLink)
				this.linkCopied[access.id] = true
				// eslint-disable-next-line
				new Timer(() => {
					this.linkCopied[access.id] = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('cospend', 'Link could not be copied to clipboard'))
			}
		},
		generateCospendLink(access) {
			return (window.location.protocol === 'http:' ? 'cospend+http://' : 'cospend://')
				+ window.location.host
				+ generateUrl('').replace('/index.php', '')
				+ access.userid + '/' + encodeURIComponent(access.password || 'no-pass')
		},
		displayCospendLinkQRCode(access) {
			this.shareLinkQrcodeUrl = this.generateCospendLink(access)
		},
		closeQrcodeModal() {
			this.shareLinkQrcodeUrl = null
		},
		addLink() {
			this.addSharedAccess({
				type: constants.SHARE_TYPE.PUBLIC_LINK,
				password: null,
			})
		},
	},
}
</script>
<style scoped lang="scss">
.success {
	color: var(--color-success);
}

.add-public-link-line * {
	cursor: pointer;
}

.qrcode-modal-content {
	margin: 12px;
	.qrcode-wrapper {
		display: flex;
		flex-direction: column;
		align-items: center;
	}
	p {
		max-width: 400px;
		overflow-wrap: anywhere;
		user-select: text;
	}
}

.shareInput {
	width: 100%;

	.shareSelectOption {
		display: flex;
		align-items: center;
	}

	.multiselect-name {
		flex-grow: 1;
		margin-left: 10px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.multiselect-icon {
		opacity: 0.5;
	}
}

.shareWithList {
	margin-bottom: 20px;

	li {
		display: flex;
		align-items: center;
		gap: 8px;
	}
}

.username {
	padding: 12px 0;
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

.enterPassword {
	display: flex;
	align-items: center;
	input {
		flex-grow: 1;
	}
}

.avatardiv.link-icon {
	background-color: var(--color-primary);
	color: white;
	display: flex;
	align-items: center;
	padding: 6px 6px 6px 6px;
}

.passwordAccessSwitch {
	cursor: pointer;
	display: flex;
	margin-bottom: 16px;
	span {
		margin-right: 8px;
	}
}

.federated-avatar-wrapper {
	position: relative;
	width: 32px;
	height: 32px;
	&__user-status {
		position: absolute;
		right: -4px;
		bottom: -4px;
		height: 18px;
		width: 18px;
		border: 2px solid var(--color-main-background);
		background-color: var(--color-main-background);
		border-radius: 50%;
	}
}
</style>
