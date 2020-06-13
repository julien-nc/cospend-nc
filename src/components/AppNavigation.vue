<template>
	<div id="app-navigation" :class="{'icon-loading': loading}">
		<AppNavigationVue>
			<ul>
				<AppNavigationItem
                    :title="t('cospend', 'New project')"
		            icon="icon-add"
                    />
                <AppNavigationItem
                    v-for="(project, id) in projects"
                    :key="id"
                    :title="project.name"
                    icon="icon-folder"
                    :allow-collapse="true"
                    :open="true"
                    @click="onProjectClick(id)"
                    >
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
import { AppNavigation as AppNavigationVue, AppNavigationSettings, AppNavigationItem } from '@nextcloud/vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import cospend from '../state';
export default {
	name: 'AppNavigation',
	components: {
		AppNavigationVue,
		AppNavigationItem,
		AppNavigationSettings,
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
        }
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