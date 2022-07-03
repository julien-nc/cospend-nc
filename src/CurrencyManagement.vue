<template>
	<div id="manage-currencies">
		<div id="main-currency-div">
			<label class="title-label">
				<CurrencyUsdIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Main currency') }}
			</label>
			<div v-show="!editMode"
				id="main-currency-label">
				<label id="main-currency-label-label">{{ project.currencyname || t('cospend', 'None') }}</label>
				<Button v-show="project.myaccesslevel >= constants.ACCESS.MAINTENER"
					v-tooltip.top="{ content: t('cospend', 'Set main currency name') }"
					@click="editMode=true; $nextTick(() => $refs.mainCurrencyEdit.focus());">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
				</Button>
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
				<Button
					v-tooltip.top="{ content: t('cospend', 'Cancel') }"
					@click="editMode=false">
					<template #icon>
						<UndoIcon :size="20" />
					</template>
				</Button>
				<Button
					v-tooltip.top="{ content: t('cospend', 'Save') }"
					type="primary"
					@click="onEditMainOkClick">
					<template #icon>
						<CheckIcon :size="20" />
					</template>
				</Button>
			</div>
		</div>
		<hr>
		<div id="currencies-div">
			<div v-show="project.myaccesslevel >= constants.ACCESS.MAINTENER"
				id="add-currency-div">
				<label class="title-label">
					<PlusIcon
						class="icon"
						:size="20" />
					{{ t('cospend', 'Add currency') }}
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
				</div>
				<div class="addCurrencyButtonWrapper">
					<Button
						v-tooltip.top="{ content: t('cospend', 'Add this currency') }"
						type="primary"
						@click="onAddCurrency">
						<template #icon>
							<CheckIcon :size="22" />
						</template>
					</Button>
				</div>
				<hr>
			</div>
			<label class="currencyListLabel">
				<CurrencyIcon class="icon" :size="20" />
				{{ t('cospend', 'Currency list') }}
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
import Button from '@nextcloud/vue/dist/Components/Button'
import PencilIcon from 'vue-material-design-icons/Pencil'
import PlusIcon from 'vue-material-design-icons/Plus'
import CheckIcon from 'vue-material-design-icons/Check'
import UndoIcon from 'vue-material-design-icons/Undo'
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd'
import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

import cospend from './state'
import Currency from './components/Currency'
import * as constants from './constants'
import * as network from './network'
import CurrencyIcon from './components/icons/CurrencyIcon'

export default {
	name: 'CurrencyManagement',

	components: {
		CurrencyIcon,
		Currency,
		CurrencyUsdIcon,
		PlusIcon,
		PencilIcon,
		CheckIcon,
		UndoIcon,
		Button,
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
			const iToDel = this.currencies.findIndex((c) => {
				return parseInt(c.id) === parseInt(currency.id)
			})
			if (iToDel !== -1) {
				this.currencies.splice(iToDel, 1)
			}
		},
		onEditCurrency(currency, name, exchangeRate) {
			if (name === '') {
				showError(t('cospend', 'Currency name should not be empty.'))
				return
			}
			const backupCurrency = {
				name: currency.name,
				exchange_rate: currency.exchange_rate,
			}
			currency.name = name
			currency.exchange_rate = exchangeRate
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
#manage-currencies .icon {
	line-height: 44px;
	padding: 0 12px 0 12px;
}

.editMainCurrencyInput {
	flex-grow: 1;
}

#main-currency-edit {
	display: flex;
	align-items: center;
	> * {
		margin: 0 4px 0 4px;
	}
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

.addCurrencyButtonWrapper {
	width: 100%;
	display: flex;
	> * {
		margin-left: auto;
	}
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

.currencyListLabel {
	display: flex;
	align-items: center;
}

.title-label {
	display: flex;
	.icon {
		padding-left: 12px !important;
	}
}
</style>
