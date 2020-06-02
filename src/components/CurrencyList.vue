<template>
	<div id="currency-list" v-if="currencies.length">
        <Currency
            v-on:delete="onDeleteEvent"
            v-on:edit="onEditEvent"
            v-for="currency in currencies"
            :key="currency.id"
            v-bind:currency="currency"/>
	</div>
	<div v-else class="no-currencies">
		{{ t('cospend', 'No currencies to display') }}
	</div>
</template>

<script>
import Currency from './Currency';
import {generateUrl} from '@nextcloud/router';
export default {
	name: 'CurrencyList',

	props: ['currencies'],
	components: {
		Currency
    },
    methods: {
        onDeleteEvent: function(currency) {
			this.$emit('delete', currency);
        },
        onEditEvent: function(currency, currencyBackup) {
			this.$emit('edit', currency, currencyBackup);
        }
    }
}
</script>

<style scoped lang="scss">
.no-currencies {
    padding: 2em;
    text-align: center;
    color: var(--color-text-light);
}
</style>