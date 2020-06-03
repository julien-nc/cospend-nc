<template>
	<div class="one-currency">
		<div class="one-currency-label" v-show="!editMode">
			<label class="one-currency-label-label">{{ currency.name }} (x{{ currency.exchange_rate }})</label>
			<input type="submit" value="" class="icon-rename editOneCurrency"
				@click="onClickEdit" v-show="editionAccess"/>
			<input type="submit" value="" :class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneCurrency'"
				@click="onClickDelete" v-show="editionAccess"/>
		</div>
		<div class="one-currency-edit" v-show="editMode">
			<label>{{ t('cospend', 'Name') }}</label>
			<input type="text" v-model="currency.name" maxlength="64" @focus="$event.target.select()"
					ref="cname" class="editCurrencyNameInput" :placeholder="t('cospend', 'Currency name')"/>
			<label>
				{{ t('cospend', 'Exchange rate to main currency') }}
				<br/>
				{{ t('cospend', '(1 of this currency = X of main currency)') }}
			</label>
			<input type="number" v-model="currency.exchange_rate"
			       class="editCurrencyRateInput" step="0.0001" min="0"/>
			<div>
				<button class="editCurrencyClose" @click="onClickCancel">
					<span class="icon-close"></span>
					<span>{{ t('cospend', 'Cancel') }}</span>
				</button>
				<button class="editCurrencyOk" @click="onClickEditOk">
					<span class="icon-checkmark"></span>
					<span>{{ t('cospend', 'Save') }}</span>
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import {Timer} from "../utils";

export default {
	name: 'Currency',

	components: {
	},

	props: ['currency', 'editionAccess'],
	data: function() {
		return {
			editMode: false,
			timerOn: false,
			timer: null,
			currencyBackup: null
		};
	},

	computed: {
	},

	methods: {
		onClickEdit: function() {
			this.editMode = true;
			this.currencyBackup = {
				exchange_rate: this.currency.exchange_rate,
				name: this.currency.name,
			}
			this.$nextTick(() => this.$refs.cname.focus());
		},
		onClickCancel: function() {
			this.editMode = false;
			this.currency.name = this.currencyBackup.name;
			this.currency.exchange_rate = this.currencyBackup.exchange_rate;
		},
		onClickDelete: function() {
			if (this.timerOn) {
				this.timerOn = false;
				this.timer.pause();
				delete this.timer;
			} else {
				this.timerOn = true;
				const that = this;
				this.timer = new Timer(function () {
					//that.deleteCurrency(that.currency);
					that.timerOn = false;
					that.$emit('delete', that.currency);
				}, 7000);
			}
		},
		onClickEditOk: function() {
			//this.editCurrency(this.currency, this.currencyBackup);
			this.$emit('edit', this.currency, this.currencyBackup);
			this.editMode = false;
		}
	},
}
</script>

<style scoped lang="scss">
.one-currency-edit {
    grid-template: 1fr / 300px 100px;
    padding: 10px 0px 10px 20px;
    background-color: var(--color-background-dark);
}
.one-currency-edit label,
.one-currency-label label {
    line-height: 40px;
}
.one-currency-label input[type=submit] {
    border-radius: 0 !important;
    width: 36px !important;
}
.one-currency-label {
    display: grid;
    grid-template: 1fr / 150px 37px 37px;
}
.editCurrencyOk {
    background-color: #46ba61;
    color: white;
}
.editCurrencyClose {
    background-color: #e9322d;
    color: white;
}
</style>
