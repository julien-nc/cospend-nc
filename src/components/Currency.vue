<template>
	<div class="one-currency">
		<div class="one-currency-label" v-show="!editMode">
			<label class="one-currency-label-label">{{ currency.name }} (x{{ currency.exchange_rate }})</label>
			<input type="submit" value="" class="icon-rename editOneCurrency"
				@click="onClickEdit" v-show="editionAccess"/>
			<input type="submit" value="" :class="(timerOn ? 'icon-history' : 'icon-delete') + ' deleteOneCurrency'"
				@click="onClickDelete" v-show="editionAccess"/>
			<label class="one-currency-label-label" v-if="timerOn">
				<vac :end-time="new Date().getTime() + (7000)">
					<template v-slot:process="{ timeObj }">
						<span>{{ `${timeObj.s}` }}</span>
					</template>
				</vac>
			</label>
		</div>
		<div class="one-currency-edit" v-show="editMode">
			<input type="text" v-model="currency.name" maxlength="64" @focus="$event.target.select()"
					ref="cname" class="editCurrencyNameInput" :placeholder="t('cospend', 'Currency name')"/>
			<input type="number" v-model="currency.exchange_rate"
				   class="editCurrencyRateInput" step="0.0001" min="0"/>
			<button class="editCurrencyClose" @click="onClickCancel">
				<span class="icon-history"></span>
			</button>
			<button class="editCurrencyOk" @click="onClickEditOk">
				<span class="icon-checkmark"></span>
			</button>
		</div>
	</div>
</template>

<script>
import {Timer} from "../utils";
import {vueAwesomeCountdown} from 'vue-awesome-countdown'

export default {
	name: 'Currency',

	components: {
		vueAwesomeCountdown
	},

	props: ['currency', 'editionAccess'],
	data() {
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
		onClickEdit() {
			this.editMode = true;
			this.currencyBackup = {
				exchange_rate: this.currency.exchange_rate,
				name: this.currency.name,
			}
			this.$nextTick(() => this.$refs.cname.focus());
		},
		onClickCancel() {
			this.editMode = false;
			this.currency.name = this.currencyBackup.name;
			this.currency.exchange_rate = this.currencyBackup.exchange_rate;
		},
		onClickDelete() {
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
		onClickEditOk() {
			//this.editCurrency(this.currency, this.currencyBackup);
			this.$emit('edit', this.currency, this.currencyBackup);
			this.editMode = false;
		}
	},
}
</script>

<style scoped lang="scss">
.one-currency-edit {
	display: grid;
	grid-template: 1fr / 1fr 1fr 44px 44px;
	padding: 10px 10px 10px 20px;
	background-color: var(--color-background-dark);
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
	grid-template: 1fr / 1fr 42px 42px 37px;
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
</style>
