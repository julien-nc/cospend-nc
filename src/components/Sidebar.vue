<template>
	<AppSidebar v-show="show"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="subtitle"
		:active="activeTab"
		@update:active="onActiveChanged"
		@close="$emit('close')">
		<!--template #description /-->
		<template v-if="false" slot="secondary-actions">
			<ActionButton icon="icon-edit" @click="alert('Edit')">
				Edit
			</ActionButton>
			<ActionButton icon="icon-delete" @click="alert('Delete')">
				Delete
			</ActionButton>
			<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
		</template>
		<AppSidebarTab v-if="!pageIsPublic"
			id="sharing"
			:name="t('cospend', 'Sharing')"
			:order="1">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			<SharingTabSidebar
				:project="project"
				@project-edited="onProjectEdited" />
		</AppSidebarTab>
		<AppSidebarTab
			id="project-settings"
			:name="t('cospend', 'Settings')"
			:order="2">
			<template #icon>
				<CogIcon :size="20" />
			</template>
			<SettingsTabSidebar
				ref="settingsTab"
				:project="project"
				@project-edited="onProjectEdited"
				@user-added="onUserAdded"
				@member-edited="onMemberEdited"
				@new-simple-member="onNewSimpleMember"
				@export-clicked="onExportClicked" />
		</AppSidebarTab>
		<AppSidebarTab v-if="!pageIsPublic && activityEnabled"
			id="activity"
			:name="t('cospend', 'Activity')"
			:order="3">
			<template #icon>
				<LightningBoltIcon :size="20" />
			</template>
			<ActivityTabSidebar
				:project-id="projectId" />
		</AppSidebarTab>
		<AppSidebarTab
			id="categories"
			:name="t('cospend', 'Categories')"
			:order="4">
			<template #icon>
				<ShapeIcon :size="20" />
			</template>
			<CategoryOrPmManagement
				:project-id="projectId"
				type="category"
				@project-edited="onProjectEdited"
				@element-deleted="onCategoryDeleted" />
		</AppSidebarTab>
		<AppSidebarTab
			id="paymentmodes"
			:name="t('cospend', 'Payment modes')"
			:order="5">
			<template #icon>
				<TagIcon :size="20" />
			</template>
			<CategoryOrPmManagement
				:project-id="projectId"
				type="paymentmode"
				@project-edited="onProjectEdited"
				@element-deleted="onPaymentModeDeleted" />
		</AppSidebarTab>
		<AppSidebarTab
			id="currencies"
			:name="t('cospend', 'Currencies')"
			:icon="'icon-tab-currencies'"
			:order="6">
			<CurrencyManagement
				:project-id="projectId"
				@project-edited="onProjectEdited" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import LightningBoltIcon from 'vue-material-design-icons/LightningBolt'
import ShapeIcon from 'vue-material-design-icons/Shape'
import TagIcon from 'vue-material-design-icons/Tag'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant'
import CogIcon from 'vue-material-design-icons/Cog'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'

import { generateUrl } from '@nextcloud/router'
import SharingTabSidebar from './SharingTabSidebar'
import SettingsTabSidebar from './SettingsTabSidebar'
import CategoryOrPmManagement from '../CategoryOrPmManagement'
import CurrencyManagement from '../CurrencyManagement'
import ActivityTabSidebar from './ActivityTabSidebar'
import cospend from '../state'
import * as constants from '../constants'

export default {
	name: 'Sidebar',
	components: {
		ActionButton,
		AppSidebar,
		AppSidebarTab,
		ActionLink,
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
	},
	props: {
		show: {
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
		}
	},
	computed: {
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		activityEnabled() {
			return cospend.activity_enabled
		},
		project() {
			return cospend.projects[this.projectId]
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
		onMemberEdited(projectid, memberid) {
			this.$emit('member-edited', projectid, memberid)
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
	},
}
</script>

<style lang="scss" scoped>
::v-deep .icon-tab-currencies {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/currency.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center 7px;
	-webkit-mask: url('../../img/currency.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center 0;
	min-width: 44px !important;
	min-height: 18px !important;
}
</style>
