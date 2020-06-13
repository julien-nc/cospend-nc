<template>
	<div id="content" :class="{ 'nav-hidden': !navShown, 'sidebar-hidden': !sidebarRouterView }">
		<AppNavigation />
		<div id="app-content">
            <div id="app-content-wrapper">
                <BillList
                    :projectId="cospend.currentProjectId"
                />
                <BillForm
                    v-if="cospend.currentBill !== null && mode === 'edition'"
                />
                <div id="billdetail">
                </div>
            </div>
		</div>
		<router-view name="sidebar" />
	</div>
</template>

<script>
import AppNavigation from './components/AppNavigation'
import cospend from './state';
import {generateUrl} from '@nextcloud/router';
import {getCurrentUser} from '@nextcloud/auth';
import * as Notification from './notification';
import * as constants from './constants';

export default {
	name: 'App',
	components: {
		AppNavigation,
	},
	data: function() {
		return {
			addButton: {
				icon: 'icon-add',
				classes: [],
				text: t('deck', 'Create new board'),
				edit: {
					text: t('deck', 'new board'),
					action: () => {
					},
					reset: () => {
					},
				},
				action: () => {
					this.addButton.classes.push('editing')
				},
			},
		}
	},
	computed: {
		// TODO: properly handle sidebar showing for route subview and board sidebar
		sidebarRouterView() {
			// console.log(this.$route)
			return this.$route.name === 'card' || this.$route.name === 'board.details'
		},
		sidebarShown() {
			return this.sidebarRouterView || this.sidebarShownState
		},
	},
	provide: function() {
		return {
			boardApi: boardApi,
		}
	},
	created: function() {
        // TODO load preferences, when finished =>
        // restore selected (set currentmachin)
        // get projects
	},
}
</script>

<style lang="scss" scoped>
	#content {
		#app-content {
			transition: margin-left 100ms ease;
			position: relative;
			overflow-x: hidden;
			align-items: stretch;
		}
		#app-sidebar {
			transition: max-width 100ms ease;
		}
		&.nav-hidden {
			#app-content {
				margin-left: 0;
			}
		}
		&.sidebar-hidden {
			#app-sidebar {
				max-width: 0;
				min-width: 0;
			}
		}
	}
</style>

<style>
	#content * {
		box-sizing: border-box;
	}
	.multiselect {
		width: 100%;
	}
</style>