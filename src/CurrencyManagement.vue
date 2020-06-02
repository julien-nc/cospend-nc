<template>
	<CurrencyList
		:currencies="currencies"
		v-on:delete="onDeleteEvent"
		v-on:edit="onEditEvent"
	/>
</template>

<script>
import cospend from './state';
import CurrencyList from './components/CurrencyList';
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';

export default {
	name: 'CurrencyManagement',

	components: {
		CurrencyList
	},

	data: function() {
		return {
			currencies: cospend.projects[cospend.currentProjectId].currencies
		};
	},

	computed: {
	},

	methods: {
		onDeleteEvent: function(currency) {
			const that = this;
			console.log('delete currency '+currency.id);
			const req = {};
			let url, type;
			if (!cospend.pageIsPublic) {
				req.projectid = cospend.currentProjectId;
				req.currencyid = currency.id;
				url = generateUrl('/apps/cospend/deleteCurrency');
				type = 'POST';
			} else {
				type = 'DELETE';
				url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currency.id);
			}
			$.ajax({
				type: type,
				url: url,
				data: req,
				async: true
			}).done(function() {
				let iToDel = null;
				for (let i = 0; i < that.currencies.length; i++) {
					if (parseInt(that.currencies[i].id) === parseInt(currency.id)) {
						iToDel = i;
						break;
					}
				}
				if (iToDel !== null) {
					that.currencies.splice(iToDel, 1);
				}
			}).always(function() {
			}).fail(function(response) {
				Notification.showTemporary(
					t('cospend', 'Failed to delete currency') +
					': ' + response.responseJSON.message
				);
			});
		},

		onEditEvent: function(currency, backupCurrency) {
			if (currency.name === '') {
				Notification.showTemporary(t('cospend', 'Currency name should not be empty'));
				currency.name = backupCurrency.name;
				currency.exchange_rate = backupCurrency.exchange_rate;
				return;
			}
			console.log('edit currency '+currency.name);
			const req = {
				name: currency.name,
				rate: currency.exchange_rate
			};
			let url, type;
			if (!cospend.pageIsPublic) {
				req.projectid = cospend.currentProjectId;
				req.currencyid = currency.id;
				url = generateUrl('/apps/cospend/editCurrency');
				type = 'POST';
			} else {
				url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currency.id);
				type = 'PUT';
			}
			$.ajax({
				type: type,
				url: url,
				data: req,
				async: true
			}).done(function() {
			}).always(function() {
			}).fail(function(response) {
				// backup
				currency.name = backupCurrency.name;
				currency.exchange_rate = backupCurrency.exchange_rate;
				Notification.showTemporary(
					t('cospend', 'Failed to edit currency') +
					'; ' + response.responseJSON.message || response.responseJSON
				);
			});
		},
	},
}
</script>

<style scoped lang="scss">
</style>
