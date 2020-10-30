<template>
	<div id="manage-currencies">
		<div id="main-currency-div">
			<label>
				<a class="icon icon-tag" />{{ t('cospend', 'Main currency') }}
			</label>
			<div v-show="!editMode"
				id="main-currency-label">
				<label id="main-currency-label-label">{{ project.currencyname || t('cospend', 'None') }}</label>
				<input v-show="project.myaccesslevel >= constants.ACCESS.MAINTENER"
					type="submit"
					value=""
					class="icon icon-rename editMainCurrency"
					@click="editMode=true; $nextTick(() => $refs.mainCurrencyEdit.focus());">
			</div>
			<div v-show="editMode"
				id="main-currency-edit">
				<input ref="mainCurrencyEdit"
					type="text"
					maxlength="64"
					class="editMainCurrencyInput"
					:placeholder="t('cospend', 'Main currency name')"
					:value="project.currencyname || ''"
					@keyup.enter="onEditMainOkClick"
					@focus="$event.target.select()">
				<input
					type="submit"
					value=""
					class="icon icon-close editMainCurrencyClose"
					@click="editMode=false">
				<input
					type="submit"
					value=""
					class="icon icon-checkmark editMainCurrencyOk"
					@click="onEditMainOkClick">
			</div>
		</div>
		<hr>
		<div id="currencies-div">
			<div v-show="project.myaccesslevel >= constants.ACCESS.MAINTENER"
				id="add-currency-div">
				<label>
					<a class="icon icon-add" />{{ t('cospend', 'Add currency') }}
				</label>
				<div id="add-currency">
					<label for="addCurrencyNameInput">
						{{ t('cospend', 'Name') }}
					</label>
					<input
						id="addCurrencyNameInput"
						ref="newCurrencyName"
						type="text"
						value=""
						maxlength="64"
						:placeholder="t('cospend', 'Currency name')"
						@keyup.enter="onAddCurrency">
					<label for="addCurrencyRateInput">
						{{ t('cospend', 'Exchange rate to main currency') }}
					</label>
					<input
						id="addCurrencyRateInput"
						ref="newCurrencyRate"
						type="number"
						value="1"
						step="0.0001"
						min="0"
						@keyup.enter="onAddCurrency">
					<label class="addCurrencyRateHint">
						{{ t('cospend', '(1 of this currency = X of main currency)') }}
					</label>
					<button class="addCurrencyOk" @click="onAddCurrency">
						<span class="icon-add" />
						<span>
							{{ t('cospend', 'Add this currency') }}
						</span>
					</button>
				</div>
				<hr>
			</div>
			<br>
			<label>
				<a class="icon icon-currencies" />{{ t('cospend', 'Currency list') }}
			</label>
			<div v-if="currencies.length"
				id="currency-list">
				<Currency
					v-for="currency in currencies"
					:key="currency.id"
					:currency="currency"
					:edition-access="project.myaccesslevel >= constants.ACCESS.MAINTENER"
					@delete="onDeleteCurrency"
					@edit="onEditCurrency" />
			</div>
			<div v-else class="no-currencies">
				{{ t('cospend', 'No currencies to display') }}
			</div>
		</div>
	</div>
</template>

<script>
import cospend from './state'
import Currency from './components/Currency'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'
import * as constants from './constants'
import * as network from './network'

export default {
	name: 'CurrencyManagement',

	components: {
		Currency,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			constants,
			editMode: false,
		}
	},

	computed: {
		currencies() {
			return cospend.projects[cospend.currentProjectId].currencies
		},
		project() {
			return cospend.projects[cospend.currentProjectId]
		},
	},

	methods: {
		onEditMainOkClick() {
			const newVal = this.$refs.mainCurrencyEdit.value
			this.project.currencyname = newVal
			this.$emit('project-edited', this.project.id)
			this.editMode = false
		},
		onAddCurrency() {
			const name = this.$refs.newCurrencyName.value
			const rate = parseFloat(this.$refs.newCurrencyRate.value)
			if (name === null || name === '') {
				showError(t('cospend', 'Currency name should not be empty.'))
				return
			}
			if (isNaN(rate)) {
				showError(t('cospend', 'Exchange rate should be a number.'))
				return
			}
			network.addCurrency(this.project.id, name, rate, this.addCurrencySuccess)
		},
		addCurrencySuccess(response, name, rate) {
			this.project.currencies.push({
				name,
				exchange_rate: rate,
				id: response,
			})
			showSuccess(t('cospend', 'Currency {n} added.', { n: name }))
			this.$refs.newCurrencyName.value = ''
			this.$refs.newCurrencyRate.value = 1
		},
		onDeleteCurrency(currency) {
			network.deleteCurrency(this.project.id, currency, this.deleteCurrencySuccess)
		},
		deleteCurrencySuccess(currency) {
			let iToDel = null
			for (let i = 0; i < this.currencies.length; i++) {
				if (parseInt(this.currencies[i].id) === parseInt(currency.id)) {
					iToDel = i
					break
				}
			}
			if (iToDel !== null) {
				this.currencies.splice(iToDel, 1)
			}
		},
		onEditCurrency(currency, backupCurrency) {
			if (currency.name === '') {
				showError(t('cospend', 'Currency name should not be empty.'))
				currency.name = backupCurrency.name
				currency.exchange_rate = backupCurrency.exchange_rate
				return
			}
			network.editCurrency(this.project.id, currency, backupCurrency, this.editCurrencyFail)
		},
		editCurrencyFail(currency, backupCurrency) {
			// backup
			currency.name = backupCurrency.name
			currency.exchange_rate = backupCurrency.exchange_rate
		},
	},
}
</script>

<style scoped lang="scss">
#manage-currencies {
	margin-left: 20px;
}

#manage-currencies .icon {
	line-height: 44px;
	padding: 0 12px 0 25px;
}

#manage-currencies .icon-currencies {
	min-height: 18px !important;
	display: inline-block;
	padding: 0 12px 0 25px !important;
}

.editMainCurrencyOk,
.editMainCurrencyClose,
.editMainCurrency {
	width: 40px !important;
	height: 40px;
	margin-top: 0px;
	border-radius: var(--border-radius-pill);
	opacity: .5;

	&.icon-rename {
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

.editMainCurrencyInput {
	width: 96%;
}

#main-currency-edit {
	display: grid;
	grid-template: 1fr / 80% 1fr 1fr;
}

.addCurrencyOk {
	background-color: #46ba61;
	color: white;
}

#main-currency-edit,
#add-currency,
#main-currency-label {
	margin-left: 37px;
}

#main-currency-label {
	display: grid;
	grid-template: 1fr / 90% 1fr;
}

#add-currency {
	display: grid;
	grid-template: 1fr / 2fr 1fr;
}

.addCurrencyRateHint {
	grid-column: 1/3;
}

#main-currency-label-label,
#add-currency label {
	line-height: 40px;
}

.no-currencies {
	padding: 2em;
	text-align: center;
	color: var(--color-text-light);
}

#currency-list {
	margin-left: 37px;
}
</style>
