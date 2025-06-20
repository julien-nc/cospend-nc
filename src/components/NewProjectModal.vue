<template>
	<NcModal
		:name="t('cospend', 'Create empty project')"
		@close="$emit('close')">
		<div class="creation-modal-content">
			<h2>{{ t('cospend', 'Create empty project') }}</h2>
			<NcTextField
				ref="input"
				v-model="newProjectName"
				:label="t('cospend', 'Project name')"
				:placeholder="t('cospend', 'My new project')"
				@keyup.enter="createProject" />
			<NcButton class="submit"
				@click="createProject">
				<template #icon>
					<ArrowRightIcon />
				</template>
				{{ t('cospend', 'Create') }}
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { emit } from '@nextcloud/event-bus'

export default {
	name: 'NewProjectModal',
	components: {
		NcButton,
		NcModal,
		NcTextField,
		ArrowRightIcon,
	},
	props: {
	},
	data() {
		return {
			newProjectName: '',
		}
	},
	computed: {
	},
	beforeMount() {
	},
	mounted() {
		this.$refs.input.focus()
	},
	methods: {
		createProject() {
			emit('create-project', this.newProjectName)
			this.newProjectName = ''
			this.$emit('close')
		},
	},
}
</script>
<style scoped lang="scss">
.creation-modal-content {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 16px;

	.submit {
		align-self: end;
	}

	h2 {
		margin-top: 0;
	}
}
</style>
