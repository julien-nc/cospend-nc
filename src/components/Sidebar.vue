<template>
	<AppSidebar v-show="show"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="subtitle"
		:active="activeTab"
		@update:active="onActiveChanged"
		@close="$emit('close')">
		<!--template slot="primary-actions" /-->
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
			icon="icon-shared"
			:name="t('cospend', 'Sharing')"
			:order="1">
			<SharingTabSidebar
				:project="project"
				@project-edited="onProjectEdited" />
		</AppSidebarTab>
		<AppSidebarTab
			id="project-settings"
			icon="icon-settings-dark"
			:name="t('cospend', 'Settings')"
			:order="2">
			<SettingsTabSidebar
				:project="project"
				@project-edited="onProjectEdited"
				@user-added="onUserAdded"
				@member-edited="onMemberEdited"
				@new-simple-member="onNewSimpleMember"
				@export-clicked="onExportClicked" />
		</AppSidebarTab>
		<AppSidebarTab
			id="categories"
			:name="t('cospend', 'Categories')"
			:icon="'icon-category-app-bundles'"
			:order="3">
			<CategoryManagement
				:project-id="projectId"
				@category-deleted="onCategoryDeleted" />
		</AppSidebarTab>
		<AppSidebarTab
			id="currencies"
			:name="t('cospend', 'Currencies')"
			:icon="'icon-currencies'"
			:order="4">
			<CurrencyManagement
				:project-id="projectId"
				@project-edited="onProjectEdited" />
		</AppSidebarTab>
		<!--AppSidebarTab :id="'comments'" :name="'Comments'" :icon="'icon-comment'"
			:order="3"
			v-if="false"
			>
			this is the comments tab
		</AppSidebarTab-->
	</AppSidebar>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'

import { generateUrl } from '@nextcloud/router'
import SharingTabSidebar from './SharingTabSidebar'
import SettingsTabSidebar from './SettingsTabSidebar'
import CategoryManagement from '../CategoryManagement'
import CurrencyManagement from '../CurrencyManagement'
import cospend from '../state'
import * as constants from '../constants'

export default {
	name: 'Sidebar',
	components: {
		ActionButton, AppSidebar, AppSidebarTab, ActionLink, SharingTabSidebar, SettingsTabSidebar, CategoryManagement, CurrencyManagement,
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
			backgroundImageUrl: generateUrl('/apps/theming/img/core/filetypes/folder.svg?v=' + window.OCA.Theming.cacheBuster),
		}
	},
	computed: {
		pageIsPublic() {
			return cospend.pageIsPublic
		},
		project() {
			return cospend.projects[this.projectId]
		},
		title() {
			return this.project.name
		},
		subtitle() {
			const nbBills = this.bills.length
			let spent = 0
			this.bills.forEach(function(bill) {
				spent += bill.amount
			})
			let nbActiveMembers = 0
			let member
			for (const mid in this.members) {
				member = this.members[mid]
				if (member.activated) {
					nbActiveMembers++
				}
			}
			return t('cospend', '{nb} bills, {nm} active members, {ns} spent', { nb: nbBills, nm: nbActiveMembers, ns: spent.toFixed(2) })
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
	},
}
</script>
