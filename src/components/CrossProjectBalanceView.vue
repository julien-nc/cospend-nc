<!--
	Cross-Project Balance View Component

	This component implements the Cross-project balances feature (GitHub issue #281).
	It displays balance information showing what the current user owes
	to and is owed by other users across all projects they participate in.

	Key Features:
	1. Summary cards showing total amounts owed/owed to user and net balance
	2. Per-person breakdown with expandable project details
	3. Proper balance interpretation consistent with settlement views
	4. Loading states and error handling

	The component fetches data from the /api/v1/cross-project-balances endpoint
	and presents it in a user-friendly format that helps users understand their
	overall financial position across all their Cospend projects.

	Balance Logic:
	- Positive amounts = user owes money to that person
	- Negative amounts = that person owes money to user
	- Display labels are adjusted accordingly ("You owe" vs "Owes you")

	@since 1.6.0 Added for cross-project balance aggregation feature
-->
<template>
	<NcAppContentDetails class="cross-project-balances">
		<div class="header">
			<div class="header-content">
				<div class="title-section">
					<h2>{{ t('cospend', 'Cumulated Balances') }}</h2>
					<p class="subtitle">
						{{ t('cospend', 'Overview of your debts and credits across all projects') }}
					</p>
				</div>
				<NcButton type="tertiary"
					:aria-label="t('cospend', 'Close cumulated balances')"
					@click="$emit('close')">
					<template #icon>
						<CloseIcon />
					</template>
				</NcButton>
			</div>
		</div>

		<div v-if="loading" class="loading-container">
			<NcLoadingIcon :size="64" />
			<p>{{ t('cospend', 'Loading cumulated balances...') }}</p>
		</div>

		<div v-else-if="error" class="error-container">
			<NcEmptyContent :name="t('cospend', 'Failed to load balances')">
				<template #icon>
					<AlertCircleIcon />
				</template>
				<template #desc>
					<p>{{ error }}</p>
					<NcButton @click="loadBalances">
						{{ t('cospend', 'Retry') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>

		<div v-else-if="balanceData" class="balance-content">
			<!-- Conditional content ordering -->
			<template v-if="showSummaryFirst">
				<!-- Summary first -->
				<div class="summary-section">
					<h3>{{ t('cospend', 'Summary') }}</h3>
					<div v-if="balanceData.currencyTotals && balanceData.currencyTotals.length > 0" class="currency-summaries">
						<div v-for="currencyTotal in balanceData.currencyTotals"
							:key="currencyTotal.currency"
							class="currency-summary">
							<div class="currency-header">
								{{ currencyTotal.currency }}
							</div>
							<div class="summary-cards-compact">
								<div class="summary-card-compact total-owed">
									<div class="card-icon-compact negative">
										<MinusIcon />
									</div>
									<div class="card-content-compact">
										<span class="label">{{ t('cospend', 'You owe') }}</span>
										<span class="amount negative">{{ formatCurrency(currencyTotal.totalOwed) }}</span>
									</div>
								</div>
								<div class="summary-card-compact total-owed-to">
									<div class="card-icon-compact positive">
										<PlusIcon />
									</div>
									<div class="card-content-compact">
										<span class="label">{{ t('cospend', 'Owed to you') }}</span>
										<span class="amount positive">{{ formatCurrency(currencyTotal.totalOwedTo) }}</span>
									</div>
								</div>
								<div class="summary-card-compact net-balance">
									<div :class="['card-icon-compact', currencyTotal.netBalance >= 0 ? 'positive' : 'negative']">
										<EqualsIcon />
									</div>
									<div class="card-content-compact">
										<span class="label">{{ t('cospend', 'Net balance') }}</span>
										<span :class="['amount', currencyTotal.netBalance >= 0 ? 'positive' : 'negative']">
											{{ formatCurrency(currencyTotal.netBalance) }}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Then person balances -->
				<div v-if="balanceData.personBalances && balanceData.personBalances.length > 0" class="person-section">
					<h3>{{ t('cospend', 'Balances by person') }}</h3>
					<div class="person-list">
						<div v-for="person in sortedPersonBalances"
							:key="getPersonKey(person)"
							class="person-card">
							<div class="person-header">
								<div class="person-avatar">
									<ColoredAvatar v-if="person.member.userid"
										:user="person.member.userid"
										:size="28" />
									<div v-else class="anonymous-avatar">
										{{ person.member.name.charAt(0).toUpperCase() }}
									</div>
								</div>
								<div class="person-info">
									<h4 class="person-name">
										{{ person.member.name }}
									</h4>
									<div class="currency-balances">
										<div v-for="(currencyBalance, currency) in person.currencyBalances"
											:key="currency"
											:class="['currency-balance', currencyBalance.totalBalance < 0 ? 'positive' : 'negative']">
											<span v-if="currencyBalance.totalBalance < 0">
												{{ t('cospend', 'Owes you {currency} {amount}', { amount: formatCurrency(Math.abs(currencyBalance.totalBalance)), currency: currency }) }}
											</span>
											<span v-else>
												{{ t('cospend', 'You owe {currency} {amount}', { amount: formatCurrency(currencyBalance.totalBalance), currency: currency }) }}
											</span>
										</div>
									</div>
								</div>
							</div>
							<div v-if="person.projects && person.projects.length > 1" class="project-breakdown">
								<NcButton type="tertiary"
									size="small"
									:aria-expanded="isPersonExpanded(getPersonKey(person))"
									@click="togglePersonExpansion(getPersonKey(person))">
									<template #icon>
										<ChevronDownIcon v-if="!isPersonExpanded(getPersonKey(person))" />
										<ChevronUpIcon v-else />
									</template>
									{{ t('cospend', 'Show {count} projects', { count: person.projects.length }) }}
								</NcButton>
								<div v-if="isPersonExpanded(getPersonKey(person))" class="project-list">
									<div v-for="project in person.projects"
										:key="project.projectId"
										class="project-item">
										<span class="project-name">{{ project.projectName }}</span>
										<span class="project-currency">{{ project.currency }}</span>
										<span :class="['project-balance', project.balance >= 0 ? 'negative' : 'positive']">
											{{ formatCurrency(Math.abs(project.balance)) }}
										</span>
									</div>
								</div>
							</div>
							<div v-else-if="person.projects && person.projects.length === 1" class="project-breakdown">
								<NcButton
									type="tertiary"
									size="small"
									:aria-expanded="isPersonExpanded(getPersonKey(person))"
									@click="togglePersonExpansion(getPersonKey(person))">
									<template #icon>
										<ChevronDownIcon v-if="!isPersonExpanded(getPersonKey(person))" />
										<ChevronUpIcon v-else />
									</template>
									{{ t('cospend', 'Show {count} project', { count: person.projects.length }) }}
								</NcButton>
								<div v-if="isPersonExpanded(getPersonKey(person))" class="project-list">
									<div v-for="project in person.projects"
										:key="project.projectId"
										class="project-item">
										<span class="project-name">{{ project.projectName }}</span>
										<span class="project-currency">{{ project.currency }}</span>
										<span :class="['project-balance', project.balance >= 0 ? 'negative' : 'positive']">
											{{ formatCurrency(Math.abs(project.balance)) }}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</template>
			<template v-else>
				<!-- Person balances first -->
				<div v-if="balanceData.personBalances && balanceData.personBalances.length > 0" class="person-section">
					<h3>{{ t('cospend', 'Balances by person') }}</h3>
					<div class="person-list">
						<div v-for="person in sortedPersonBalances"
							:key="getPersonKey(person)"
							class="person-card">
							<div class="person-header">
								<div class="person-avatar">
									<ColoredAvatar v-if="person.member.userid"
										:user="person.member.userid"
										:size="28" />
									<div v-else class="anonymous-avatar">
										{{ person.member.name.charAt(0).toUpperCase() }}
									</div>
								</div>
								<div class="person-info">
									<h4 class="person-name">
										{{ person.member.name }}
									</h4>
									<div class="currency-balances">
										<div v-for="(currencyBalance, currency) in person.currencyBalances"
											:key="currency"
											:class="['currency-balance', currencyBalance.totalBalance < 0 ? 'positive' : 'negative']">
											<span v-if="currencyBalance.totalBalance < 0">
												{{ t('cospend', 'Owes you {currency} {amount}', { amount: formatCurrency(Math.abs(currencyBalance.totalBalance)), currency: currency }) }}
											</span>
											<span v-else>
												{{ t('cospend', 'You owe {currency} {amount}', { amount: formatCurrency(currencyBalance.totalBalance), currency: currency }) }}
											</span>
										</div>
									</div>
								</div>
							</div>
							<div v-if="person.projects && person.projects.length > 1" class="project-breakdown">
								<NcButton
									type="tertiary"
									size="small"
									:aria-expanded="isPersonExpanded(getPersonKey(person))"
									@click="togglePersonExpansion(getPersonKey(person))">
									<template #icon>
										<ChevronDownIcon v-if="!isPersonExpanded(getPersonKey(person))" />
										<ChevronUpIcon v-else />
									</template>
									{{ t('cospend', 'Show {count} projects', { count: person.projects.length }) }}
								</NcButton>
								<div v-if="isPersonExpanded(getPersonKey(person))" class="project-list">
									<div v-for="project in person.projects"
										:key="project.projectId"
										class="project-item">
										<span class="project-name">{{ project.projectName }}</span>
										<span class="project-currency">{{ project.currency }}</span>
										<span :class="['project-balance', project.balance >= 0 ? 'negative' : 'positive']">
											{{ formatCurrency(Math.abs(project.balance)) }}
										</span>
									</div>
								</div>
							</div>
							<div v-else-if="person.projects && person.projects.length === 1" class="project-breakdown">
								<NcButton
									type="tertiary"
									size="small"
									:aria-expanded="isPersonExpanded(getPersonKey(person))"
									@click="togglePersonExpansion(getPersonKey(person))">
									<template #icon>
										<ChevronDownIcon v-if="!isPersonExpanded(getPersonKey(person))" />
										<ChevronUpIcon v-else />
									</template>
									{{ t('cospend', 'Show {count} project', { count: person.projects.length }) }}
								</NcButton>
								<div v-if="isPersonExpanded(getPersonKey(person))" class="project-list">
									<div v-for="project in person.projects"
										:key="project.projectId"
										class="project-item">
										<span class="project-name">{{ project.projectName }}</span>
										<span class="project-currency">{{ project.currency }}</span>
										<span :class="['project-balance', project.balance >= 0 ? 'negative' : 'positive']">
											{{ formatCurrency(Math.abs(project.balance)) }}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Then summary -->
				<div class="summary-section">
					<h3>{{ t('cospend', 'Summary') }}</h3>
					<div v-if="balanceData.currencyTotals && balanceData.currencyTotals.length > 0" class="currency-summaries">
						<div v-for="currencyTotal in balanceData.currencyTotals"
							:key="currencyTotal.currency"
							class="currency-summary">
							<div class="currency-header">
								{{ currencyTotal.currency }}
							</div>
							<div class="summary-cards-compact">
								<div class="summary-card-compact total-owed">
									<div class="card-icon-compact negative">
										<MinusIcon />
									</div>
									<div class="card-content-compact">
										<span class="label">{{ t('cospend', 'You owe') }}</span>
										<span class="amount negative">{{ formatCurrency(currencyTotal.totalOwed) }}</span>
									</div>
								</div>
								<div class="summary-card-compact total-owed-to">
									<div class="card-icon-compact positive">
										<PlusIcon />
									</div>
									<div class="card-content-compact">
										<span class="label">{{ t('cospend', 'Owed to you') }}</span>
										<span class="amount positive">{{ formatCurrency(currencyTotal.totalOwedTo) }}</span>
									</div>
								</div>
								<div class="summary-card-compact net-balance">
									<div :class="['card-icon-compact', currencyTotal.netBalance >= 0 ? 'positive' : 'negative']">
										<EqualsIcon />
									</div>
									<div class="card-content-compact">
										<span class="label">{{ t('cospend', 'Net balance') }}</span>
										<span :class="['amount', currencyTotal.netBalance >= 0 ? 'positive' : 'negative']">
											{{ formatCurrency(currencyTotal.netBalance) }}
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</template>

			<!-- No balances message -->
			<div v-if="!balanceData.personBalances || balanceData.personBalances.length === 0" class="no-balances">
				<NcEmptyContent :name="t('cospend', 'All settled up!')">
					<template #icon>
						<CheckIcon />
					</template>
					<template #desc>
						{{ t('cospend', 'You have no outstanding balances across your projects.') }}
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</NcAppContentDetails>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcAppContentDetails from '@nextcloud/vue/dist/Components/NcAppContentDetails.js'

import PlusIcon from 'vue-material-design-icons/Plus.vue'
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import EqualsIcon from 'vue-material-design-icons/Equal.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import ColoredAvatar from './avatar/ColoredAvatar.vue'
import * as network from '../network.js'
import cospend from '../state.js'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'CrossProjectBalanceView',

	components: {
		NcButton,
		NcLoadingIcon,
		NcEmptyContent,
		NcAppContentDetails,
		ColoredAvatar,
		PlusIcon,
		MinusIcon,
		EqualsIcon,
		CheckIcon,
		AlertCircleIcon,
		ChevronDownIcon,
		ChevronUpIcon,
		CloseIcon,
	},

	data() {
		return {
			loading: false, // Loading state for API call
			error: null, // Error message if API call fails
			balanceData: null, // Response data from cross-project balance API
			expandedPersons: [], // Track which person cards are expanded to show project details
			collapsedPersons: [], // Track which person cards have been manually collapsed (when default is show)
		}
	},

	computed: {
		/**
		 * Sort person balances by highest currency balance for better UX
		 * Shows largest balances (positive or negative) first across all currencies
		 */
		sortedPersonBalances() {
			if (!this.balanceData?.personBalances) {
				return []
			}
			// Sort by the largest absolute balance across all currencies
			return [...this.balanceData.personBalances].sort((a, b) => {
				const maxBalanceA = Math.max(...Object.values(a.currencyBalances).map(cb => Math.abs(cb.totalBalance)))
				const maxBalanceB = Math.max(...Object.values(b.currencyBalances).map(cb => Math.abs(cb.totalBalance)))
				return maxBalanceB - maxBalanceA
			})
		},

		/**
		 * Whether to show summary cards first (before person balances)
		 * Based on user setting in Cospend Settings
		 */
		showSummaryFirst() {
			return cospend.showSummaryFirst ?? true
		},

		/**
		 * Whether to hide project breakdown by default
		 * Based on user setting in Cospend Settings -> Cumulated balances
		 *
		 * When enabled, shows "Show X projects" buttons that users can click
		 * to expand and see which specific projects contribute to each person's balance.
		 * When disabled, shows all project details expanded by default.
		 *
		 * This applies to both single-project and multi-project users for consistency.
		 *
		 * @return {boolean} True if project details should be collapsed by default
		 */
		hideProjectsByDefault() {
			return cospend.hideProjectsByDefault ?? true
		},
	},

	watch: {
		/**
		 * Reset expansion state when the hide/show default setting changes
		 * This ensures the new default behavior applies immediately
		 */
		hideProjectsByDefault() {
			// Clear both tracking arrays so default behavior applies
			this.expandedPersons = []
			this.collapsedPersons = []
		},
	},

	async mounted() {
		// Load balance data when component is mounted
		await this.loadBalances()
	},

	methods: {
		/**
		 * Load cross-project balance data from the API
		 * Handles loading states and error cases
		 */
		async loadBalances() {
			this.loading = true
			this.error = null

			try {
				// Call the cross-project balance API endpoint
				const response = await network.getCrossProjectBalances()
				this.balanceData = response.data.ocs.data
			} catch (error) {
				console.error('Failed to load cumulated balances:', error)
				this.error = error.response?.data?.ocs?.meta?.message || t('cospend', 'Failed to load cumulated balances')
				showError(this.error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Format currency amounts for display without currency suffix since it's shown in context
		 * Uses browser locale for proper number formatting
		 * @param {number} amount The numeric amount to format
		 * @return {string} Formatted currency string
		 */
		formatCurrency(amount) {
			return new Intl.NumberFormat(navigator.language, {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(amount)
		},

		/**
		 * Get unique key for a person (for Vue key and tracking)
		 * @param {object} person Person object with member info
		 * @return {string} Unique key for the person
		 */
		getPersonKey(person) {
			return person.member.userid || `name-${person.member.name}`
		},

		/**
		 * Toggle expansion of person card to show/hide project details
		 * Handles both default-hidden and default-shown states properly
		 * @param {string} personKey Unique key for the person
		 */
		togglePersonExpansion(personKey) {
			if (this.hideProjectsByDefault) {
				// Default is hidden, so toggle expanded list
				const index = this.expandedPersons.indexOf(personKey)
				if (index === -1) {
					this.expandedPersons.push(personKey)
				} else {
					this.expandedPersons.splice(index, 1)
				}
			} else {
				// Default is shown, so toggle collapsed list
				const index = this.collapsedPersons.indexOf(personKey)
				if (index === -1) {
					this.collapsedPersons.push(personKey)
				} else {
					this.collapsedPersons.splice(index, 1)
				}
			}
		},

		/**
		 * Check if a person's project details should be expanded
		 * Handles both default states and manual user interactions properly:
		 * - When hideProjectsByDefault=true: Hidden by default, show only if manually expanded
		 * - When hideProjectsByDefault=false: Shown by default, hide only if manually collapsed
		 * This ensures the "Show X projects" button always appears but honors the user's preference for default state
		 * while maintaining full toggle functionality in both modes.
		 * @param {string} personKey Unique key for the person
		 * @return {boolean} True if person's projects should be visible
		 */
		isPersonExpanded(personKey) {
			if (this.hideProjectsByDefault) {
				// Default is hidden, show only if manually expanded
				return this.expandedPersons.includes(personKey)
			} else {
				// Default is shown, hide only if manually collapsed
				return !this.collapsedPersons.includes(personKey)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cross-project-balances {
	padding: 16px;
	max-width: 1200px;
	margin: 0 auto;
}

.header {
	margin-bottom: 24px;

	.header-content {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;
	}

	.title-section {
		flex: 1;
		text-align: center;
	}

	h2 {
		margin-bottom: 6px;
		font-size: 1.4em;
	}

	.subtitle {
		color: var(--color-text-maxcontrast);
		margin: 0;
		font-size: 0.9em;
	}
}

.loading-container {
	text-align: center;
	padding: 40px 16px;

	p {
		margin-top: 16px;
		color: var(--color-text-maxcontrast);
	}
}

.error-container {
	padding: 32px 16px;
}

.summary-section {
	margin-bottom: 32px;

	h3 {
		margin-bottom: 16px;
		font-size: 1.2em;
	}
}

.currency-summaries {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 16px;
}

.currency-summary {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	padding: 12px;
	background: var(--color-background-hover);

	.currency-header {
		margin: 0 0 8px 0;
		font-size: 0.9em;
		font-weight: bold;
		text-align: center;
		color: var(--color-text-light);
		padding: 6px 12px;
		background: var(--color-background-dark);
		border: 1px solid var(--color-border-dark);
		border-radius: 4px;
		display: block;
		width: 100%;
		box-sizing: border-box;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}
}

.summary-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 16px;
	margin-bottom: 0;
}

.summary-card {
	background: var(--color-background-hover);
	border-radius: 8px;
	padding: 16px;
	border: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	gap: 12px;
	transition: all 0.2s ease;

	&:hover {
		border-color: var(--color-primary);
		transform: translateY(-1px);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}

	.card-icon {
		width: 36px;
		height: 36px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;

		&.positive {
			background: rgba(var(--color-success-rgb), 0.1);
			color: var(--color-success);
		}

		&.negative {
			background: rgba(var(--color-error-rgb), 0.1);
			color: var(--color-error);
		}
	}

	.card-content {
		flex: 1;

		h4, h5 {
			margin: 0 0 4px 0;
			font-size: 0.85em;
			color: var(--color-text-maxcontrast);
			font-weight: 500;
		}

		.amount {
			font-size: 1.2em;
			font-weight: bold;

			&.positive {
				color: var(--color-success);
			}

			&.negative {
				color: var(--color-error);
			}
		}
	}
}

/* Compact summary cards styles */
.summary-cards-compact {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
	gap: 6px;
}

.summary-card-compact {
	display: flex;
	align-items: center;
	gap: 4px;
	padding: 4px 6px;
	border-radius: 4px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	min-width: 0;
	transition: all 0.2s ease;

	&:hover {
		border-color: var(--color-primary);
		box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
	}

	.card-icon-compact {
		width: 16px;
		height: 16px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;

		&.positive {
			background: rgba(var(--color-success-rgb), 0.1);
			color: var(--color-success);
		}

		&.negative {
			background: rgba(var(--color-error-rgb), 0.1);
			color: var(--color-error);
		}
	}

	.card-content-compact {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
		flex: 1;

		.label {
			font-size: 9px;
			color: var(--color-text-maxcontrast);
			font-weight: 500;
			line-height: 1.1;
		}

		.amount {
			font-size: 12px;
			font-weight: 600;
			line-height: 1.1;

			&.positive {
				color: var(--color-success);
			}

			&.negative {
				color: var(--color-error);
			}
		}
	}
}

.person-section {
	h3 {
		margin-bottom: 16px;
		font-size: 1.2em;
	}
}

.person-list {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 16px;
}

.person-card {
	background: var(--color-background-hover);
	border-radius: 8px;
	padding: 16px;
	border: 1px solid var(--color-border);
	transition: all 0.2s ease;
	width: 100%;
	box-sizing: border-box;
	align-self: start; /* Prevent cards from stretching when others expand */

	&:hover {
		border-color: var(--color-primary);
		box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
	}
}

.person-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 12px;
}

.person-avatar {
	flex-shrink: 0;
}

.anonymous-avatar {
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: var(--color-primary);
	color: white;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: bold;
	font-size: 12px;
}

.person-info {
	flex: 1;
	min-width: 0;
}

.person-name {
	margin: 0 0 6px 0;
	font-size: 1em;
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.currency-balances {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.currency-balance {
	font-weight: 500;
	font-size: 0.85em;
	display: flex;
	align-items: center;

	&.positive {
		color: var(--color-success);
	}

	&.negative {
		color: var(--color-error);
	}
}

.project-breakdown {
	margin-top: 10px;

	> .button-vue {
		width: 100%;
		justify-content: space-between;
		padding: 8px 12px;
		font-size: 0.85em;
	}
}

.project-list {
	margin-top: 10px;
	border-top: 1px solid var(--color-border);
	padding-top: 10px;
}

.project-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 6px 0;
	border-bottom: 1px solid var(--color-border-dark);
	gap: 8px;
	font-size: 0.85em;

	&:last-child {
		border-bottom: none;
	}
}

.project-name {
	color: var(--color-text-maxcontrast);
	flex: 1;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-currency {
	color: var(--color-text-maxcontrast);
	font-size: 0.75em;
	font-weight: bold;
	background: var(--color-background-dark);
	padding: 2px 4px;
	border-radius: 3px;
	flex-shrink: 0;
}

.project-balance {
	font-weight: 600;
	flex-shrink: 0;

	&.positive {
		color: var(--color-success);
	}

	&.negative {
		color: var(--color-error);
	}
}

.single-project {
	margin-top: 6px;
	padding-top: 6px;
	border-top: 1px solid var(--color-border);
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.8em;

	.project-name {
		color: var(--color-text-maxcontrast);
	}

	.project-currency {
		color: var(--color-text-maxcontrast);
		opacity: 0.8;
		font-size: 0.75em;
	}
}

.no-balances {
	padding: 40px 16px;
}
</style>
