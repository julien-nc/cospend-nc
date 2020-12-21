<template>
	<div class="one-currency">
		<div v-show="!editMode"
			class="one-currency-label">
			<label class="one-currency-label-label">{{ currency.name }}</label>
			<label class="one-currency-label-label">(x{{ currency.exchange_rate }})</label>
			<input v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Edit') }"
				type="submit"
				value=""
				class="icon-rename editOneCurrency icon"
				@click="onClickEdit">
			<input v-show="editionAccess"
				v-tooltip.top="{ content: t('cospend', 'Delete') }"
				type="submit"
				value=""
				:class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneCurrency icon'"
				@click="onClickDelete">
			<label v-if="timerOn"
				class="one-currency-label-label">
				<vac :end-time="new Date().getTime() + (7000)">
					<template #process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
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
			<button
				v-tooltip.top="{ content: t('cospend', 'Cancel') }"
				class="editCurrencyClose icon-history icon"
				@click="onClickCancel" />
			<button
				v-tooltip.top="{ content: t('cospend', 'Save') }"
				class="editCurrencyOk icon-checkmark icon"
				@click="onClickEditOk" />
		</div>
	</div>
</template>

<script>
import { Timer } from '../utils'

export default {
	name: 'Currency',

	components: {},

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
	display: grid;
	grid-template: 1fr / 1fr 1fr 42px 42px;
	height: 40px;
	border-radius: 15px;
	background-color: var(--color-background-dark);
	margin-right: 15px;
}

.one-currency-edit label,
.one-currency-label label {
	line-height: 40px;
}

.one-currency-label input[type=submit] {
	border-radius: 50% !important;
	width: 40px !important;
	height: 40px;
	margin-top: 0px;
}

.one-currency-label {
	display: grid;
	grid-template: 1fr / 1fr 1fr 42px 42px 15px;
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

.icon {
	border-radius: var(--border-radius-pill);
	opacity: .5;

	&.icon-rename,
	&.icon-delete,
	&.icon-history {
		background-color: transparent;
		border: none;
		margin: 0;
	}

	&:hover,
	&:focus {
		opacity: 1;
		background-color: var(--color-background-hover);
	}
}
</style>
