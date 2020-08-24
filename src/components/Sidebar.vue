<template>
	<AppSidebar v-show="show"
		:title="title"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="subtitle"
		:active="activeTab"
		@update:active="onActiveChanged"
		@close="$emit('close')">
		<template slot="primary-actions">
		</template>
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
				@projectEdited="onProjectEdited" />
		</AppSidebarTab>
		<AppSidebarTab
			id="categories"
			:name="t('cospend', 'Categories')"
			:icon="'icon-category-app-bundles'"
			:order="3">
			<CategoryManagement
				:projectId="projectId"
				@categoryDeleted="onCategoryDeleted" />
		</AppSidebarTab>
		<AppSidebarTab
			id="currencies"
			:name="t('cospend', 'Currencies')"
			:icon="'icon-currencies'"
			:order="4">
			<CurrencyManagement
				:projectId="projectId"
				@projectEdited="onProjectEdited" />
		</AppSidebarTab>
		<!--AppSidebarTab :id="'comments'" :name="'Comments'" :icon="'icon-comment'"
			:order="3"
			v-if="false"
			>
			this is the comments tab
		</AppSidebarTab-->
		<AppSidebarTab v-if="editionAccess"
			id="settings"
			icon="icon-settings-dark"
			:name="t('cospend', 'Settings')"
			:order="2">
			<SettingsTabSidebar
				:project="project"
				@projectEdited="onProjectEdited"
				@userAdded="onUserAdded"
				@memberEdited="onMemberEdited"
				@newSimpleMember="onNewSimpleMember"
				@exportClicked="onExportClicked" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import {
	ActionButton, AppSidebar, AppSidebarTab, ActionLink
} from '@nextcloud/vue'
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
			backgroundImageUrl: generateUrl('/apps/theming/img/core/filetypes/folder.svg?v=0')
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
			this.$emit('activeChanged', newActive)
		},
		onProjectEdited(projectid, password = null) {
			this.$emit('projectEdited', projectid, password)
		},
		onUserAdded(projectid, name, userid) {
			this.$emit('userAdded', projectid, name, userid)
		},
		onMemberEdited(projectid, memberid) {
			this.$emit('memberEdited', projectid, memberid)
		},
		onNewSimpleMember(projectid, name) {
			this.$emit('newMember', projectid, name)
		},
		onExportClicked(projectid) {
			this.$emit('exportClicked', projectid)
		},
		onCategoryDeleted(catid) {
			this.$emit('categoryDeleted', catid)
		},
	},
}
</script>

<style scoped lang="scss">
</style>
