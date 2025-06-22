<template>
	<div class="one-currency">
		<div v-show="!editMode"
			class="one-currency-label">
			<label class="one-currency-label-label">{{ currency.name }}</label>
			<label class="one-currency-label-label">(x{{ currency.exchange_rate }})</label>
			<NcButton v-show="editionAccess"
				:title="t('cospend', 'Edit')"
				:aria-label="t('cospend', 'Edit')"
				@click="onClickEdit">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
			</NcButton>
			<NcButton v-show="editionAccess"
				:title="t('cospend', 'Delete')"
				:aria-label="t('cospend', 'Delete')"
				class="deleteCurrencyButton"
				@click="onClickDelete">
				<template #icon>
					<UndoIcon v-if="timerOn" :size="20" />
					<DeleteIcon v-else class="delete-icon" :size="20" />
				</template>
			</NcButton>
			<label v-if="timerOn"
				class="one-currency-label-timer">
				<Countdown :duration="7" />
			</label>
		</div>
		<div v-if="editMode"
			class="one-currency-edit">
			<input
				ref="cname"
				v-model="name"
				type="text"
				maxlength="64"
				class="editCurrencyNameInput"
				:placeholder="t('cospend', 'Currency name')"
				@focus="$event.target.select()">
			<input v-model="exchange_rate"
				type="number"
				class="editCurrencyRateInput"
				step="0.0001"
				min="0">
			<NcButton
				:title="t('cospend', 'Cancel')"
				:aria-label="t('cospend', 'Cancel')"
				@click="onClickCancel">
				<template #icon>
					<UndoIcon :size="20" />
				</template>
			</NcButton>
			<NcButton
				:title="t('cospend', 'Save')"
				:aria-label="t('cospend', 'Save')"
				variant="primary"
				@click="onClickEditOk">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

import Countdown from './Countdown.vue'

import { Timer } from '../utils.js'

export default {
	name: 'Currency',

	components: {
		Countdown,
		PencilIcon,
		DeleteIcon,
		UndoIcon,
		CheckIcon,
		NcButton,
	},

	props: {
		currency: {
			type: Object,
			required: true,
		},
		editionAccess: {
			type: Boolean,
			required: true,
		},
	},
	data() {
		return {
			editMode: false,
			timerOn: false,
			timer: null,
			// initial values
			name: this.currency.name,
			exchange_rate: this.currency.exchange_rate,
		}
	},

	computed: {
	},

	methods: {
		onClickEdit() {
			this.editMode = true
			this.$nextTick(() => this.$refs.cname.focus())
		},
		onClickCancel() {
			this.editMode = false
			this.name = this.currency.name
			this.exchange_rate = this.currency.exchange_rate
		},
		onClickDelete() {
			if (this.timerOn) {
				this.timerOn = false
				this.timer.pause()
				delete this.timer
			} else {
				this.timerOn = true
				this.timer = new Timer(() => {
					this.timerOn = false
					this.$emit('delete', this.currency)
				}, 7000)
			}
		},
		onClickEditOk() {
			this.$emit('edit', this.currency, this.name, this.exchange_rate)
			this.editMode = false
		},
	},
}
</script>

<style scoped lang="scss">
.one-currency-edit {
	display: flex;
	align-items: center;
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-dark);
	margin-right: 20px;
	padding: 4px 0 4px 0;
	> * {
		margin: 0 4px 0 4px;
	}
	.editCurrencyNameInput,
	.editCurrencyRateInput {
		flex-grow: 1;
	}
}

.one-currency-edit label,
.one-currency-label label {
	line-height: 40px;
}

.one-currency-label {
	position: relative;
	flex-grow: 1;
	display: flex;
	align-items: center;
	margin-right: 20px;
	padding: 4px 0 4px 0;
	> * {
		margin: 0 4px 0 4px;
	}
	.one-currency-label-label {
		width: 50%;
	}
	.one-currency-label-timer {
		position: absolute;
		right: -20px;
	}
}

.editCurrencyOk,
.editCurrencyClose {
	width: 40px !important;
	height: 40px;
	margin-top: 0px;
}

.editCurrencyOk {
	background-color: #46ba61;
	color: white;
}

:deep(.deleteCurrencyButton:hover) {
	.delete-icon {
		color: var(--color-error);
	}
}
</style>
