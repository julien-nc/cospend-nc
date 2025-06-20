<template>
	<NcAppSidebar
		:open="open"
		:name="title"
		:name-editable="nameEditable"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subname="subtitle"
		:subtitle="subtitle"
		:active="activeTab"
		@update:active="onActiveChanged"
		@update:open="$emit('update:open', $event)"
		@update:name="tmpName = $event"
		@submit-name="onNameSubmit"
		@close="$emit('close')">
		<!--template #description /-->
		<template #secondary-actions>
			<NcActionButton @click="onRenameClick">
				<template #icon>
					<PencilIcon />
				</template>
				{{ t('cospend', 'Rename') }}
			</NcActionButton>
		</template>
		<NcAppSidebarTab v-if="!pageIsPublic && !project.federated"
			id="sharing"
			:name="t('cospend', 'Sharing')"
			:order="1">
			<template #icon>
				<ShareVariantIcon
					:title="t('cospend', 'Sharing')"
					:size="20" />
			</template>
			<SharingTabSidebar
				:project="project"
				@project-edited="onProjectEdited" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="project-settings"
			:name="t('cospend', 'Settings')"
			:order="2">
			<template #icon>
				<CogIcon
					:title="t('cospend', 'Settings')"
					:size="20" />
			</template>
			<SettingsTabSidebar
				ref="settingsTab"
				:project="project"
				@project-edited="onProjectEdited"
				@user-added="onUserAdded"
				@new-simple-member="onNewSimpleMember"
				@export-clicked="onExportClicked" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="!pageIsPublic && activityEnabled"
			id="activity"
			:name="t('cospend', 'Activity')"
			:order="3">
			<template #icon>
				<LightningBoltIcon
					:title="t('cospend', 'Activity')"
					:size="20" />
			</template>
			<ActivityTabSidebar
				:project-id="projectId" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="categories"
			:name="t('cospend', 'Categories')"
			:order="4">
			<template #icon>
				<ShapeIcon
					:title="t('cospend', 'Categories')"
					:size="20" />
			</template>
			<CategoryOrPmManagement
				:project-id="projectId"
				type="category"
				@project-edited="onProjectEdited"
				@element-deleted="onCategoryDeleted" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="paymentmodes"
			:name="t('cospend', 'Payment modes')"
			:order="5">
			<template #icon>
				<TagIcon
					:title="t('cospend', 'Payment modes')"
					:size="20" />
			</template>
			<CategoryOrPmManagement
				:project-id="projectId"
				type="paymentmode"
				@project-edited="onProjectEdited"
				@element-deleted="onPaymentModeDeleted" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="currencies"
			:name="t('cospend', 'Currencies')"
			:order="6">
			<template #icon>
				<CurrencyIcon
					:title="t('cospend', 'Currencies')"
					:size="20" />
			</template>
			<CurrencyManagement
				:project-id="projectId"
				@project-edited="onProjectEdited" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import LightningBoltIcon from 'vue-material-design-icons/LightningBolt.vue'
import ShapeIcon from 'vue-material-design-icons/Shape.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

import CurrencyIcon from './icons/CurrencyIcon.vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'

import SharingTabSidebar from './SharingTabSidebar.vue'
import SettingsTabSidebar from './SettingsTabSidebar.vue'
import CategoryOrPmManagement from '../CategoryOrPmManagement.vue'
import CurrencyManagement from '../CurrencyManagement.vue'
import ActivityTabSidebar from './ActivityTabSidebar.vue'

import { generateUrl } from '@nextcloud/router'
import * as constants from '../constants.js'

export default {
	name: 'Sidebar',
	components: {
		CurrencyIcon,
		NcActionButton,
		NcAppSidebar,
		NcAppSidebarTab,
		SharingTabSidebar,
		SettingsTabSidebar,
		CategoryOrPmManagement,
		CurrencyManagement,
		ActivityTabSidebar,
		ShapeIcon,
		LightningBoltIcon,
		CogIcon,
		ShareVariantIcon,
		TagIcon,
		PencilIcon,
	},
	props: {
		open: {
			type: Boolean,
			required: true,
		},
		activeTab: {
			type: String,
			required: true,
		},
		projectId: {
			type: String,
			required: true,
		},
		bills: {
			type: Array,
			required: true,
		},
		members: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			backgroundImageUrl: generateUrl('/apps/theming/img/core/filetypes/folder.svg?v=' + (window.OCA?.Theming?.cacheBuster || 0)),
			nameEditable: false,
			tmpName: '',
			pageIsPublic: OCA.Cospend.state.pageIsPublic,
			activityEnabled: OCA.Cospend.state.activity_enabled,
		}
	},
	computed: {
		project() {
			return OCA.Cospend.state.projects[this.projectId]
		},
		title() {
			return this.project.name
		},
		subtitle() {
			const nbBills = this.project.nb_bills
			const spent = this.project.total_spent
			let nbActiveMembers = 0
			let member
			for (const mid in this.members) {
				member = this.members[mid]
				if (member.activated) {
					nbActiveMembers++
				}
			}
			if (this.project.currencyname) {
				return t('cospend', '{nb} bills, {nbMembers} active members, {spentAmount} {currency} spent', {
					nb: nbBills,
					nbMembers: nbActiveMembers,
					currency: this.project.currencyname,
					spentAmount: spent.toFixed(2),
				})
			} else {
				return t('cospend', '{nb} bills, {nbMembers} active members, {spentAmount} spent', {
					nb: nbBills,
					nbMembers: nbActiveMembers,
					spentAmount: spent.toFixed(2),
				})
			}
		},
		editionAccess() {
			return this.project.myaccesslevel >= constants.ACCESS.MAINTENER
		},
	},
	methods: {
		onActiveChanged(newActive) {
			this.$emit('active-changed', newActive)
		},
		onProjectEdited(projectid, password = null) {
			this.$emit('project-edited', projectid, password)
		},
		onUserAdded(projectid, name, userid) {
			this.$emit('user-added', projectid, name, userid)
		},
		onNewSimpleMember(projectid, name) {
			this.$emit('new-member', projectid, name)
		},
		onExportClicked(projectid) {
			this.$emit('export-clicked', projectid)
		},
		onCategoryDeleted(catid) {
			this.$emit('category-deleted', catid)
		},
		onPaymentModeDeleted(catid) {
			this.$emit('paymentmode-deleted', catid)
		},
		focusOnAddMember() {
			this.$refs.settingsTab.focusOnAddMember()
		},
		onRenameClick() {
			this.nameEditable = true
			this.tmpName = this.project.name
		},
		onNameSubmit() {
			this.nameEditable = false
			if (this.project.name !== this.tmpName) {
				OCA.Cospend.state.projects[this.projectId].name = this.tmpName
				this.$emit('project-edited', this.projectId)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
