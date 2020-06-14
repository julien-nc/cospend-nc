<template>
	<div id="app-navigation" :class="{'icon-loading': loading}">
		<AppNavigationVue>
			<ul>
				<AppNavigationItem
                    :title="t('cospend', 'New project')"
                    icon="icon-add"
                    />
                <AppNavigationItem
                    :title="t('cospend', 'New bill')"
                    @click="onNewBillClick"
                    icon="icon-edit"
                    />
                <AppNavigationItem
                    v-for="(project, id) in projects"
                    :key="id"
                    :title="project.name"
                    icon="icon-folder"
                    :allow-collapse="true"
                    :open="true"
                    @click="onProjectClick(id)"
                    :forceMenu="true"
                    >
                    <template slot="actions">
						<ActionButton icon="icon-add" @click="onStatsClick(id)">
							{{ t('cospend', 'Statistics') }}
						</ActionButton>
						<ActionButton icon="icon-phone" @click="onQrcodeClick(id)">
							{{ t('cospend', 'Link/QRCode for MoneyBuster') }}
						</ActionButton>
						<ActionButton icon="icon-delete" @click="alert('Delete')">
							Delete
						</ActionButton>
					</template>
                    <template>
						<AppNavigationItem title="AppNavigationItemChild1">
							<AppNavigationIconBullet slot="icon" color="0082c9" />
						</AppNavigationItem>
						<AppNavigationItem title="AppNavigationItemChild2" icon="icon-category-enabled" />
						<AppNavigationItem title="AppNavigationItemChild3" icon="icon-category-enabled" />
						<AppNavigationItem title="AppNavigationItemChild4" icon="icon-category-enabled" />
					</template>
                </AppNavigationItem>
			</ul>
			<AppNavigationSettings>
				<div>
                    SETTINGS !!!<br/>PLOP
				</div>
			</AppNavigationSettings>
		</AppNavigationVue>

		<div
			id="app-settings"
			:class="{open: opened}">
			<div id="app-settings-header">
				<button class="settings-button" @click="toggleMenu">
					{{ t('cospend', 'Settings') }}
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import { ActionButton, AppNavigation as AppNavigationVue, AppNavigationIconBullet, AppNavigationSettings, AppNavigationItem } from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import cospend from '../state';
export default {
	name: 'AppNavigation',
	components: {
		AppNavigationVue,
		AppNavigationItem,
        AppNavigationSettings,
        AppNavigationIconBullet,
        ActionButton
	},
	directives: {
		ClickOutside,
	},
	props: ['projects'],
	data() {
		return {
            opened: false,
            loading: false
		}
	},
	computed: {
	},
	beforeMount() {
	},
	methods: {
		toggleMenu() {
			this.opened = !this.opened
		},
		closeMenu() {
			this.opened = false
        },
        onProjectClick: function(projectid) {
            this.$emit('projectClicked', projectid);
        },
        onNewBillClick: function() {
            this.$emit('newBillClicked');
        },
        onQrcodeClick: function(projectid) {
            this.$emit('qrcodeClicked', projectid);
        },
        onStatsClick: function(projectid) {
            this.$emit('statsClicked', projectid);
        },
	},
}
</script>
<style scoped lang="scss">
	#app-settings-content {
		p {
			margin-top: 20px;
			margin-bottom: 20px;
			color: var(--color-text-light);
		}
	}
</style>