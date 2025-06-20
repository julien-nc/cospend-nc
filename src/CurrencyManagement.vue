<template>
	<div id="manage-currencies">
		<div id="main-currency-div">
			<h3 class="title-label">
				<CurrencyUsdIcon
					class="icon"
					:size="20" />
				{{ t('cospend', 'Main currency') }}
			</h3>
			<div v-show="!editMode"
				id="main-currency-label">
				<label id="main-currency-label-label">{{ project.currencyname || t('cospend', 'None') }}</label>
				<NcButton v-show="project.myaccesslevel >= constants.ACCESS.MAINTENER"
					:title="t('cospend', 'Set main currency name')"
					:aria-label="t('cospend', 'Set main currency name')"
					@click="editMode=true; $nextTick(() => $refs.mainCurrencyEdit.focus());">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
				</NcButton>
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
				<NcButton
					:title="t('cospend', 'Cancel')"
					:aria-label="t('cospend', 'Cancel currency edition')"
					@click="editMode=false">
					<template #icon>
						<UndoIcon :size="20" />
					</template>
				</NcButton>
				<NcButton
					:title="t('cospend', 'Save')"
					:aria-label="t('cospend', 'Save currency')"
					variant="primary"
					@click="onEditMainOkClick">
					<template #icon>
						<CheckIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<hr>
		<div id="currencies-div">
			<div v-show="project.myaccesslevel >= constants.ACCESS.MAINTENER"
				id="add-currency-div">
				<h3 class="title-label">
					<PlusIcon
						class="icon"
						:size="20" />
					{{ t('cospend', 'Add currency') }}
				</h3>
				<div id="add-currency">
					<NcTextField
						v-model="newCurrencyName"
						:label="t('cospend', 'Currency name')"
						placeholder="..."
						@keyup.enter="onAddCurrency" />
					<NcInputField
						v-model="newCurrencyRate"
						type="number"
						:label="t('cospend', 'Exchange rate to main currency')"
						placeholder="..." />
					<label class="addCurrencyRateHint">
						{{ t('cospend', '(1 of this currency = X of main currency)') }}
					</label>
				</div>
				<div class="addCurrencyButtonWrapper">
					<NcButton
						:title="t('cospend', 'Add this currency')"
						:aria-label="t('cospend', 'Add this currency')"
						variant="primary"
						@click="onAddCurrency">
						<template #icon>
							<CheckIcon :size="22" />
						</template>
					</NcButton>
				</div>
				<hr>
			</div>
			<h3 class="title-label">
				<CurrencyIcon class="icon" :size="20" />
				{{ t('cospend', 'Currency list') }}
			</h3>
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
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import UndoIcon from 'vue-material-design-icons/Undo.vue'
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd.vue'

import CurrencyIcon from './components/icons/CurrencyIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import Currency from './components/Currency.vue'

import {
	showSuccess,
	showError,
} from '@nextcloud/dialogs'

import * as constants from './constants.js'
import * as network from './network.js'

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
		NcButton,
		NcInputField,
		NcTextField,
	},

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			cospend: OCA.Cospend.state,
			constants,
			editMode: false,
			newCurrencyName: '',
			newCurrencyRate: 1,
		}
	},

	computed: {
		currencies() {
			return this.cospend.projects[this.cospend.currentProjectId].currencies
		},
		project() {
			return this.cospend.projects[this.cospend.currentProjectId]
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
			const name = this.newCurrencyName
			const rate = parseFloat(this.newCurrencyRate)
			if (name === null || name === '') {
				showError(t('cospend', 'Currency name should not be empty'))
				return
			}
			if (isNaN(rate)) {
				showError(t('cospend', 'Exchange rate should be a number'))
				return
			}
			network.createCurrency(this.project.id, name, rate, this.addCurrencySuccess)
		},
		addCurrencySuccess(currencyId, name, rate) {
			this.project.currencies.push({
				name,
				exchange_rate: rate,
				id: currencyId,
			})
			showSuccess(t('cospend', 'Currency {n} added', { n: name }))
			this.newCurrencyName = ''
			this.newCurrencyRate = 1
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
				showError(t('cospend', 'Currency name should not be empty'))
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

#add-currency {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

#main-currency-label {
	display: grid;
	grid-template: 1fr / 90% 1fr;
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

.title-label {
	margin-top: 12px;
	display: flex;
	align-items: center;
	gap: 12px;
}
</style>
