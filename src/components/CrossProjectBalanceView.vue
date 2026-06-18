<!--
	Cross-Project Balance View Component - Central Hub for Multi-Project Financial Overview

	This component implements the Cross-project balances feature (GitHub issue #281).
	It represents a major architectural enhancement that aggregates balance information
	across all projects a user participates in, providing a comprehensive financial overview.

	CORE FUNCTIONALITY:
	1. **Balance Aggregation**: Calculates net balances across all active projects
	2. **Currency Management**: Handles multiple currencies with proper aggregation
	3. **Person Consolidation**: Merges balances for the same person across projects
	4. **Settlement Integration**: Direct access to cross-project settlement features

	TECHNICAL ARCHITECTURE:
	- **Lazy Loading**: Component is dynamically imported to optimize bundle size
	- **API Integration**: Uses /api/v1/cross-project-balances endpoint
	- **Memoization**: Expensive calculations are cached for performance
	- **Reactive State**: Real-time updates when settlements are created

	USER INTERFACE DESIGN:
	- **Summary Cards**: High-level overview of financial position per currency
	- **Person Breakdown**: Detailed view of relationships with each person
	- **Project Details**: Expandable sections showing project-level contributions
	- **Settlement Actions**: Direct access to settlement functionality

	BALANCE INTERPRETATION (Critical for Consistency):
	- **Positive amounts**: Current user owes money to that person
	- **Negative amounts**: That person owes money to current user
	- **Display labels**: Automatically adjusted ("You owe" vs "Owes you")

	This interpretation maintains consistency with individual project settlement views
	and ensures users have a coherent understanding across the application.

	PERFORMANCE CONSIDERATIONS:
	- **Filtering**: Only shows people with settleable balances (> 0.01)
	- **Sorting**: Smart sorting by balance amount, name, or currency
	- **Caching**: Balance calculations and formatting are memoized
	- **Responsive**: Optimized layouts for different screen sizes

	@since 3.0.12 Major feature addition for cross-project balance management
	@see CrossProjectSettlement.vue for settlement functionality
	@see CospendNavigation.vue for navigation integration
-->
<template>
	<div class="cross-project-balances">
		<div class="header">
			<div class="header-content">
				<div class="title-section">
					<h2>{{ t('cospend', 'Cumulative Balances') }}</h2>
					<p class="subtitle">
						{{ t('cospend', 'Overview of your debts and credits across all projects') }}
					</p>
				</div>
			</div>
		</div>

		<div v-if="loading" class="loading-container">
			<NcLoadingIcon :size="64" />
			<p>{{ t('cospend', 'Loading cumulative balances...') }}</p>
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
			<!-- Balance Information -->
			<div class="balances-area">
				<!-- Content Sections with Dynamic Ordering
					The order of Summary vs People sections can be controlled via settings.
					Both sections use sorted data based on user preferences.
				-->
				<template v-if="showSummaryFirst">
					<!-- Summary Section (Currency Totals)
						Shows total amounts owed/owing by currency across all projects
						Uses sortedCurrencyTotals computed property for custom ordering
					-->
					<div class="summary-section">
						<div class="section-header">
							<h3>{{ t('cospend', 'Summary') }}</h3>
						</div>
						<div v-if="sortedCurrencyTotals && sortedCurrencyTotals.length > 0" class="currency-summaries">
							<div v-for="currencyTotal in sortedCurrencyTotals"
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
					<!-- People Section (Individual Balances)
						Shows balances with each individual person across all projects
						Uses sortedPersonBalances computed property for custom ordering
						Includes settlement functionality for each person
					-->
					<div v-if="balanceData.personBalances && balanceData.personBalances.length > 0" class="person-section">
						<div class="section-header">
							<h3>{{ t('cospend', 'Balances by person') }}</h3>
						</div>
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
											<template v-for="(currencyBalance, currency) in person.currencyBalances">
												<div v-if="Math.abs(currencyBalance.totalBalance) > 0.01"
													:key="currency"
													:class="['currency-balance', currencyBalance.totalBalance < 0 ? 'positive' : 'negative']">
													<span v-if="currencyBalance.totalBalance < 0">
														{{ t('cospend', 'Owes you {currency} {amount}', { amount: formatCurrency(Math.abs(currencyBalance.totalBalance)), currency: currency }) }}
													</span>
													<span v-else>
														{{ t('cospend', 'You owe {currency} {amount}', { amount: formatCurrency(currencyBalance.totalBalance), currency: currency }) }}
													</span>
												</div>
											</template>
										</div>
									</div>
									<!-- Settlement button in header -->
									<div class="person-actions">
										<NcButton v-if="hasSettleableBalances(person)"
											type="secondary"
											@click="startSettlement(person)">
											<template #icon>
												<ReimburseIcon />
											</template>
											{{ t('cospend', 'Settle') }}
										</NcButton>
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
						<div class="section-header">
							<h3>{{ t('cospend', 'Balances by person') }}</h3>
						</div>
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
											<template v-for="(currencyBalance, currency) in person.currencyBalances">
												<div v-if="Math.abs(currencyBalance.totalBalance) > 0.01"
													:key="currency"
													:class="['currency-balance', currencyBalance.totalBalance < 0 ? 'positive' : 'negative']">
													<span v-if="currencyBalance.totalBalance < 0">
														{{ t('cospend', 'Owes you {currency} {amount}', { amount: formatCurrency(Math.abs(currencyBalance.totalBalance)), currency: currency }) }}
													</span>
													<span v-else>
														{{ t('cospend', 'You owe {currency} {amount}', { amount: formatCurrency(currencyBalance.totalBalance), currency: currency }) }}
													</span>
												</div>
											</template>
										</div>
									</div>
									<!-- Settlement Actions in header -->
									<div class="header-actions">
										<NcButton v-if="hasSettleableBalances(person)"
											type="secondary"
											@click="startSettlement(person)">
											<template #icon>
												<ReimburseIcon />
											</template>
											{{ t('cospend', 'Settle') }}
										</NcButton>
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
						<div class="section-header">
							<h3>{{ t('cospend', 'Summary') }}</h3>
						</div>
						<div v-if="sortedCurrencyTotals && sortedCurrencyTotals.length > 0" class="currency-summaries">
							<div v-for="currencyTotal in sortedCurrencyTotals"
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
			</div> <!-- End balances-area -->
		</div> <!-- End balance-content -->
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcButton from '@nextcloud/vue/components/NcButton'

import PlusIcon from 'vue-material-design-icons/Plus.vue'
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import EqualsIcon from 'vue-material-design-icons/Equal.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import ReimburseIcon from './icons/ReimburseIcon.vue'

import ColoredAvatar from './avatar/ColoredAvatar.vue'
import * as network from '../network.js'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { emit } from '@nextcloud/event-bus'

const cospend = OCA.Cospend.state

export default {
	name: 'CrossProjectBalanceView',

	components: {
		NcLoadingIcon,
		NcEmptyContent,
		NcButton,
		ColoredAvatar,
		PlusIcon,
		MinusIcon,
		EqualsIcon,
		CheckIcon,
		AlertCircleIcon,
		ChevronDownIcon,
		ChevronUpIcon,
		ReimburseIcon,
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
		// ============================================
		// Sorting Preferences (from Global State)
		// ============================================

		/**
		 * Get person sorting preferences from global cospend state
		 * These are set via CospendSettingsDialog and persist across sessions
		 */
		personSortBy() {
			return cospend.personSortBy || 'balance'
		},
		personSortOrder() {
			return cospend.personSortOrder || 'desc'
		},
		summarySortBy() {
			return cospend.summarySortBy || 'amount'
		},
		summarySortOrder() {
			return cospend.summarySortOrder || 'desc'
		},

		// ============================================
		// Sorted Data Arrays
		// ============================================

		/**
		 * Sort person balances based on user preferences
		 * Supports sorting by name, balance amount, or currency
		 * Only includes people with settleable balances (> 0.01)
		 */
		sortedPersonBalances() {
			if (!this.balanceData?.personBalances) {
				return []
			}

			// Filter out people with no settleable balances
			const peopleWithBalances = this.balanceData.personBalances.filter(person =>
				this.hasSettleableBalances(person),
			)

			const sortedPersons = [...peopleWithBalances]

			sortedPersons.sort((a, b) => {
				let compareValue = 0

				switch (this.personSortBy) {
				case 'name':
					compareValue = a.member.name.localeCompare(b.member.name)
					break
				case 'currency': {
					const currenciesA = Object.keys(a.currencyBalances).sort().join(',')
					const currenciesB = Object.keys(b.currencyBalances).sort().join(',')
					compareValue = currenciesA.localeCompare(currenciesB)
					break
				}
				case 'balance':
				default: {
					const maxBalanceA = Math.max(...Object.values(a.currencyBalances).map(cb => Math.abs(cb.totalBalance)))
					const maxBalanceB = Math.max(...Object.values(b.currencyBalances).map(cb => Math.abs(cb.totalBalance)))
					compareValue = maxBalanceA - maxBalanceB
					break
				}
				}

				return this.personSortOrder === 'asc' ? compareValue : -compareValue
			})

			return sortedPersons
		},

		/**
		 * Sort currency totals (summary section) based on user preferences
		 * Supports sorting by amount or currency name
		 */
		sortedCurrencyTotals() {
			if (!this.balanceData?.currencyTotals) {
				return []
			}

			const sortedTotals = [...this.balanceData.currencyTotals]

			sortedTotals.sort((a, b) => {
				let compareValue = 0

				switch (this.summarySortBy) {
				case 'currency':
					compareValue = a.currency.localeCompare(b.currency)
					break
				case 'amount':
				default: {
					const amountA = Math.abs(a.netBalance)
					const amountB = Math.abs(b.netBalance)
					compareValue = amountA - amountB
					break
				}
				}

				return this.summarySortOrder === 'asc' ? compareValue : -compareValue
			})

			return sortedTotals
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
		 * Based on user setting in Cospend Settings -> Cumulative balances
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

		/**
		 * Check if the settlement is valid for execution
		 * @return {boolean} True if settlement can be executed
		 */
		isSettlementValid() {
			if (!this.settlementCurrency || !this.settlementAmount) {
				return false
			}
			if (this.showPartialSettlement && this.partialSettlementConfirmed) {
				return this.validateProjectBreakdownAmounts()
			}
			return true
		},

		/**
		 * Check if partial settlement parameters are valid
		 * @return {boolean} True if partial settlement can proceed
		 */
		isPartialSettlementValid() {
			if (!this.showPartialSettlement) {
				return true
			}
			return this.partialSettlementAmount > 0
				   && this.partialSettlementAmount <= Math.abs(this.settlementAmount)
		},

		/**
		 * Get validation message for partial settlement amount
		 * @return {string|null} Validation message or null if valid
		 */
		partialSettlementValidationMessage() {
			if (!this.showPartialSettlement || !this.partialSettlementAmount) {
				return null
			}

			const maxAmount = Math.abs(this.settlementAmount)
			if (this.partialSettlementAmount > maxAmount) {
				const over = this.partialSettlementAmount - maxAmount
				return t('cospend', 'Amount is {amount} {currency} over the maximum', {
					amount: this.formatCurrency(over),
					currency: this.settlementCurrency,
				})
			}

			return null
		},

		/**
		 * Get validation message for project breakdown amounts
		 * @return {string|null} Validation message or null if valid
		 */
		projectBreakdownValidationMessage() {
			if (!this.showPartialSettlement || !this.partialSettlementConfirmed || !this.settlementProjectBreakdown) {
				return null
			}

			const totalCustomAmount = this.settlementProjectBreakdown.reduce((sum, project) => {
				const amount = project.customAmount !== null ? project.customAmount : project.billAmount
				return sum + (amount || 0)
			}, 0)

			const difference = totalCustomAmount - this.partialSettlementAmount
			const tolerance = 0.01

			if (Math.abs(difference) <= tolerance) {
				return null
			}

			if (difference > tolerance) {
				return t('cospend', 'Total is {amount} {currency} over the settlement amount', {
					amount: this.formatCurrency(difference),
					currency: this.settlementCurrency,
				})
			} else {
				return t('cospend', 'Total is {amount} {currency} under the settlement amount', {
					amount: this.formatCurrency(Math.abs(difference)),
					currency: this.settlementCurrency,
				})
			}
		},

		/**
		 * Check if any project has overpayment
		 * @return {object|null} Overpayment info or null
		 */
		overpaymentWarning() {
			if (!this.showPartialSettlement || !this.partialSettlementConfirmed || !this.settlementProjectBreakdown || !this.settlementPerson) {
				return null
			}

			const currency = this.settlementCurrency
			const projects = this.settlementPerson.projects.filter(p => p.currency === currency)

			for (const project of this.settlementProjectBreakdown) {
				const originalProject = projects.find(p => p.projectId === project.projectId)
				if (!originalProject) continue

				const actualAmount = project.customAmount !== null ? project.customAmount : project.billAmount
				const originalBalance = Math.abs(originalProject.balance)

				if (actualAmount > originalBalance + 0.01) {
					const overpayment = actualAmount - originalBalance
					return {
						projectName: project.projectName,
						overpayment,
						currency,
					}
				}
			}

			return null
		},

		/**
		 * Options for summary sort by dropdown
		 * @return {Array<object>} Array of option objects for NcSelect
		 */
		summarySortByOptions() {
			return [
				{ id: 'amount', label: t('cospend', 'Amount') },
				{ id: 'currency', label: t('cospend', 'Currency') },
			]
		},

		/**
		 * Options for summary sort order dropdown
		 * @return {Array<object>} Array of option objects for NcSelect
		 */
		summarySortOrderOptions() {
			return [
				{ id: 'desc', label: t('cospend', 'Descending') },
				{ id: 'asc', label: t('cospend', 'Ascending') },
			]
		},

		/**
		 * Options for person sort by dropdown
		 * @return {Array<object>} Array of option objects for NcSelect
		 */
		personSortByOptions() {
			return [
				{ id: 'balance', label: t('cospend', 'Balance') },
				{ id: 'name', label: t('cospend', 'Name') },
				{ id: 'currency', label: t('cospend', 'Currency') },
			]
		},

		/**
		 * Options for person sort order dropdown
		 * @return {Array<object>} Array of option objects for NcSelect
		 */
		personSortOrderOptions() {
			return [
				{ id: 'desc', label: t('cospend', 'Descending') },
				{ id: 'asc', label: t('cospend', 'Ascending') },
			]
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

		/**
		 * Update project breakdown when partial settlement amount changes
		 */
		partialSettlementAmount() {
			if (this.showPartialSettlement && this.settlementPerson && this.settlementCurrency) {
				this.settlementProjectBreakdown = this.calculateProjectBreakdown(
					this.settlementPerson,
					this.settlementCurrency,
					this.partialSettlementAmount,
				)
			}
		},

		/**
		 * Update sort order when person sort type changes
		 * @param {string} newSortBy - The new sort criteria ('balance', 'name', etc.)
		 */
		personSortBy(newSortBy) {
			if (newSortBy === 'balance') {
				this.personSortOrder = 'desc' // High to Low for balance
			} else {
				this.personSortOrder = 'asc' // A to Z for name/currency
			}
		},

		/**
		 * Update sort order when summary sort type changes
		 * @param {string} newSortBy - The new sort criteria ('amount', 'currency', etc.)
		 */
		summarySortBy(newSortBy) {
			if (newSortBy === 'amount') {
				this.summarySortOrder = 'desc' // High to Low for amount
			} else {
				this.summarySortOrder = 'asc' // A to Z for currency
			}
		},
	},
	async mounted() {
		// Load balance data when component is mounted
		await this.loadBalances()
	},

	beforeDestroy() {
		// Clean up any pending API calls or timers
		if (this.loadBalancesRequest) {
			this.loadBalancesRequest.abort()
		}
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
				this.$emit('balances-loaded')
			} catch (error) {
				console.error('Failed to load cumulative balances:', error)
				this.error = error.response?.data?.ocs?.meta?.message || t('cospend', 'Failed to load cumulative balances')
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
			if (person?.personKey) {
				return person.personKey
			}
			if (person?.member?.userid) {
				return `user=${person.member.userid}`
			}
			return `name=${(person?.member?.name || '').trim().toLowerCase().replace(/\s+/g, '-')}`
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

		/**
		 * Start settlement for a specific person - emit event to parent
		 * @param {object} person Person to settle with
		 */
		startSettlement(person) {
			// Emit event to parent (App.vue) to handle settlement in the right panel
			this.$emit('settlement-person-selected', person)
			// Fallback path for slotted rendering contexts where component emits can be swallowed.
			emit('cross-project-settlement-person-selected', person)
		},

		/**
		 * Get available currencies for a person
		 * @param {object} person Person object
		 * @return {Array} Available currencies
		 */
		getAvailableCurrencies(person) {
			if (!person?.currencyBalances) {
				return []
			}
			return Object.keys(person.currencyBalances)
				.filter(currency => Math.abs(person.currencyBalances[currency].totalBalance) > 0.01)
		},

		/**
		 * Update remaining amount when partial amount input changes
		 */
		updateRemainingAmount() {
			// Recalculate project breakdown when partial amount changes
			if (this.currentSettlementPerson && this.settlementCurrency && this.showPartialSettlement) {
				this.settlementProjectBreakdown = this.calculateProjectBreakdown(
					this.currentSettlementPerson,
					this.settlementCurrency,
					this.partialSettlementAmount,
				)
			}
		},

		/**
		 * Handle currency change in settlement modal
		 */
		onCurrencyChange() {
			if (this.currentSettlementPerson && this.settlementCurrency) {
				this.settlementAmount = this.currentSettlementPerson.currencyBalances[this.settlementCurrency].totalBalance
				this.partialSettlementAmount = Math.abs(this.settlementAmount)
				this.showPartialSettlement = false
				this.partialSettlementConfirmed = false
				this.settlementProjectBreakdown = this.calculateProjectBreakdown(
					this.currentSettlementPerson,
					this.settlementCurrency,
					Math.abs(this.settlementAmount),
				)
			}
		},

		/**
		 * Handle currency selection in settlement modal
		 * @param {string} currency Selected currency
		 */
		onCurrencySelect(currency) {
			this.settlementCurrency = currency
			this.onCurrencyChange()
		},

		/**
		 * Check if a person has any settleable balances
		 * @param {object} person Person object
		 * @return {boolean}
		 */
		hasSettleableBalances(person) {
			if (!person?.currencyBalances) {
				return false
			}
			return Object.values(person.currencyBalances)
				.some(currencyBalance => Math.abs(currencyBalance.totalBalance) > 0.01)
		},

		/**
		 * Enable partial settlement mode
		 */
		enablePartialSettlement() {
			this.showPartialSettlement = true
			this.partialSettlementAmount = Math.abs(this.settlementAmount) / 2 // Start with half
		},

		/**
		 * Disable partial settlement mode
		 */
		disablePartialSettlement() {
			this.showPartialSettlement = false
			this.partialSettlementConfirmed = false
			this.partialSettlementAmount = 0

			// Reset to original settlement amount and recalculate breakdown
			if (this.settlementPerson && this.settlementCurrency) {
				this.settlementAmount = this.settlementPerson.currencyBalances[this.settlementCurrency].totalBalance
				this.settlementProjectBreakdown = this.calculateProjectBreakdown(
					this.settlementPerson,
					this.settlementCurrency,
					Math.abs(this.settlementAmount),
				)
			}
		},

		/**
		 * Confirm partial settlement configuration
		 */
		confirmPartialSettlement() {
			this.partialSettlementConfirmed = true
			// Update the settlement amount to the partial amount
			this.settlementAmount = this.settlementAmount > 0 ? this.partialSettlementAmount : -this.partialSettlementAmount
			// Recalculate project breakdown with the new amount
			this.settlementProjectBreakdown = this.calculateProjectBreakdown(
				this.settlementPerson,
				this.settlementCurrency,
				this.partialSettlementAmount,
			)
		},

		/**
		 * Calculate how settlement amount should be distributed across projects
		 * @param {object} person Person being settled
		 * @param {string} currency Currency being settled
		 * @param {number} totalAmount Total amount to settle
		 * @return {Array} Array of project breakdown objects
		 */
		calculateProjectBreakdown(person, currency, totalAmount) {
			const projects = person.projects.filter(p => p.currency === currency && Math.abs(p.balance) > 0.01)
			if (projects.length === 0) return []

			// If totalAmount equals the sum of all project balances, use exact amounts
			const totalProjectBalance = projects.reduce((sum, p) => sum + Math.abs(p.balance), 0)

			if (Math.abs(totalAmount - totalProjectBalance) < 0.01) {
				// Full settlement: use exact project balances
				return projects.map(project => ({
					projectId: project.projectId,
					projectName: project.projectName,
					billAmount: Math.abs(project.balance),
					customAmount: null,
				}))
			}

			// Partial settlement: distribute proportionally but prefer whole numbers
			const breakdown = projects.map(project => {
				const proportion = Math.abs(project.balance) / totalProjectBalance
				const proportionalAmount = proportion * totalAmount
				return {
					projectId: project.projectId,
					projectName: project.projectName,
					originalBalance: Math.abs(project.balance),
					proportionalAmount,
					billAmount: proportionalAmount,
					customAmount: null,
				}
			})

			// Try to round to whole numbers while preserving total
			const roundedBreakdown = breakdown.map(item => ({
				...item,
				billAmount: Math.round(item.proportionalAmount),
			}))

			// Check if rounding preserves the total
			const roundedTotal = roundedBreakdown.reduce((sum, item) => sum + item.billAmount, 0)
			const difference = totalAmount - roundedTotal

			// If the difference is small, adjust the largest amount to match exactly
			if (Math.abs(difference) < projects.length && Math.abs(difference) >= 0.01) {
				// Find the project with the largest amount to adjust
				const largestIndex = roundedBreakdown.reduce((maxIndex, item, index, arr) =>
					item.billAmount > arr[maxIndex].billAmount ? index : maxIndex, 0)
				roundedBreakdown[largestIndex].billAmount += difference
			}

			// If rounding works and all amounts are reasonable, use rounded amounts
			const allAmountsReasonable = roundedBreakdown.every(item =>
				item.billAmount > 0 && item.billAmount <= item.originalBalance * 1.5)

			if (allAmountsReasonable && Math.abs(roundedBreakdown.reduce((sum, item) => sum + item.billAmount, 0) - totalAmount) < 0.01) {
				return roundedBreakdown.map(item => ({
					projectId: item.projectId,
					projectName: item.projectName,
					billAmount: item.billAmount,
					customAmount: null,
				}))
			}

			// Fall back to proportional amounts if rounding doesn't work well
			return breakdown.map(item => ({
				projectId: item.projectId,
				projectName: item.projectName,
				billAmount: item.proportionalAmount,
				customAmount: null,
			}))
		},

		/**
		 * Validate that custom amounts in project breakdown add up to settlement amount
		 * @return {boolean} True if amounts are valid
		 */
		validateProjectBreakdownAmounts() {
			if (!this.showPartialSettlement || !this.partialSettlementConfirmed || !this.settlementProjectBreakdown) {
				return true
			}

			const totalCustomAmount = this.settlementProjectBreakdown.reduce((sum, project) => {
				const amount = project.customAmount !== null ? project.customAmount : project.billAmount
				return sum + (amount || 0)
			}, 0)

			const tolerance = 0.01 // Allow small rounding differences
			return Math.abs(totalCustomAmount - this.partialSettlementAmount) <= tolerance
		},

		/**
		 * Get the actual amount to use for a project (custom or calculated)
		 * @param {object} project Project breakdown object
		 * @return {number} Amount to use for this project
		 */
		getProjectAmount(project) {
			return project.customAmount !== null ? project.customAmount : project.billAmount
		},
	},
}
</script>

<style lang="scss" scoped>
// Global rule: All currency amounts use tabular numbers for consistent digit alignment
.amount,
.amount-value,
.amount-input,
.currency-label,
.amount-display,
.summary-card .amount,
.summary-card-compact .amount,
.currency-summary .amount {
	font-family: var(--font-face) !important;
	font-variant-numeric: tabular-nums !important;
	font-weight: 600;
}

.cross-project-balances {
	--cp-positive-text: var(--color-text-success, var(--color-success));
	--cp-negative-text: var(--color-text-error, var(--color-error));
	--cp-warning-bg: rgba(var(--color-warning-rgb, 252, 176, 64), 0.14);
	--cp-warning-border: var(--color-warning, var(--color-primary-element));
	--cp-warning-text: var(--color-main-text);

	padding: 16px;
	max-width: 1200px;
	margin: 0 auto;

	@media (max-width: 768px) {
		padding: 8px;
		max-width: none;
		margin: 0;
	}
}

// Main two-column layout
.main-layout {
	display: flex;
	gap: 24px;
	min-height: 600px;

	@media (max-width: 1024px) {
		flex-direction: column;
		gap: 16px;
		min-height: auto;
	}

	@media (max-width: 768px) {
		gap: 12px;
		min-height: auto;
	}
}

.settlement-area {
	flex: 0 0 400px;

	@media (max-width: 1024px) {
		flex: none;
	}
}

.balances-area {
	flex: 1;
	min-width: 0; // Prevents flex item from overflowing
	overflow-y: auto; // Add vertical scrolling
	max-height: calc(100vh - 150px); // Limit height to viewport minus header
}

// Settlement placeholder when no settlement is active
.settlement-placeholder {
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	background-color: var(--color-background-hover);
	border: 2px dashed var(--color-border);
	border-radius: var(--border-radius-large);
	min-height: 300px;

	.placeholder-content {
		text-align: center;
		color: var(--color-text-maxcontrast);

		h3 {
			margin: 16px 0 8px 0;
			font-size: 1.1em;
		}

		p {
			margin: 0;
			font-size: 0.9em;
		}
	}
}

.header {
	margin-bottom: 24px;

	@media (max-width: 768px) {
		margin-bottom: 16px;
	}

	.header-content {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;

		@media (max-width: 768px) {
			flex-direction: column;
			gap: 12px;
			align-items: center;
		}
	}

	.title-section {
		flex: 1;
		text-align: center;
	}

	h2 {
		margin-top: 0px;
		margin-bottom: 8px;
		font-size: 1.4em;

		@media (max-width: 768px) {
			font-size: 1.2em;
		}
	}

	.subtitle {
		color: var(--color-text-maxcontrast);
		margin: 0;
		font-size: 0.9em;

		@media (max-width: 768px) {
			font-size: 0.8em;
		}
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
	margin-top: 24px;
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
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	margin-bottom: 0;

	@media (max-width: 768px) {
		flex-direction: column;
		gap: 12px;
	}
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
	white-space: nowrap;
	flex-shrink: 0;

	@media (max-width: 768px) {
		padding: 12px;
		gap: 10px;
		white-space: normal;
	}

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
			color: var(--cp-positive-text);
		}

		&.negative {
			background: rgba(var(--color-error-rgb), 0.1);
			color: var(--cp-negative-text);
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
			font-family: var(--font-face);
			font-size: 1.2em;
			font-weight: bold;
			font-variant-numeric: tabular-nums;

			&.positive {
				color: var(--cp-positive-text);
			}

			&.negative {
				color: var(--cp-negative-text);
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
			color: var(--cp-positive-text);
		}

		&.negative {
			background: rgba(var(--color-error-rgb), 0.1);
			color: var(--cp-negative-text);
		}
	}

	.card-content-compact {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
		flex: 1;
		align-items: flex-end;

		.label {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
			font-weight: 500;
			line-height: 1.1;
		}

		.amount {
			font-size: 14px;
			font-weight: 600;
			line-height: 1.1;
			font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Code', 'Fira Mono', 'Roboto Mono', 'Consolas', monospace;
			font-variant-numeric: tabular-nums;

			&.positive {
				color: var(--cp-positive-text);
			}

			&.negative {
				color: var(--cp-negative-text);
			}
		}
	}
}

// Section header styles with sorting controls
.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px; /* Reduced from 16px to match summary spacing better */

	h3 {
		margin: 0;
		font-size: 1.2em;
	}

	.sort-controls {
		display: flex;
		align-items: center;
		gap: 8px;

		label {
			font-size: 0.9em;
			color: var(--color-text-maxcontrast);
			margin: 0;
		}

		.sort-dropdown {
			padding: 6px 8px;
			border: 1px solid var(--color-border);
			border-radius: var(--border-radius);
			background: var(--color-main-background);
			color: var(--color-main-text);
			font-size: 0.85em;
			min-width: 100px;

			&.order-dropdown {
				min-width: 120px;
			}

			&:focus {
				outline: none;
				border-color: var(--color-primary-element);
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
	grid-template-columns: repeat(auto-fit, minmax(max(260px, 25%), 1fr));
	gap: 16px;

	@media (max-width: 768px) {
		grid-template-columns: 1fr;
	}
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

	@media (max-width: 768px) {
		padding: 12px;
		border-radius: 6px;
	}

	&:hover {
		border-color: var(--color-primary);
		box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
	}
}

.person-header {
	display: flex;
	align-items: flex-start; /* Changed from center to flex-start to align with name */
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
	color: var(--color-primary-element-text, #fff);
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

.header-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-shrink: 0;

	.button-vue {
		flex-shrink: 0;
	}
}

.currency-balance {
	font-weight: 500;
	font-size: 0.85em;
	display: flex;
	align-items: center;
	white-space: nowrap; // Prevent text from wrapping

	&.positive {
		color: var(--cp-positive-text);
	}

	&.negative {
		color: var(--cp-negative-text);
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
		color: var(--cp-positive-text);
	}

	&.negative {
		color: var(--cp-negative-text);
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

// Settlement window styles (left panel)
.settlement-window {
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 20px;
	height: fit-content;

	.settlement-header {
		margin-bottom: 20px;
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 12px;

		h3 {
			margin: 0 0 4px 0;
			color: var(--color-main-text);
			font-size: 1.2em;
		}

		h4 {
			margin: 0;
			color: var(--color-text-maxcontrast);
			font-size: 1em;
			font-weight: normal;
		}
	}

	.currency-selection {
		margin-bottom: 20px;
		display: flex;
		align-items: center;
		gap: 12px;

		label {
			font-weight: 500;
			color: var(--color-main-text);
			min-width: 80px;
		}

		.currency-dropdown {
			flex: 1;
			padding: 8px 12px;
			border: 1px solid var(--color-border);
			border-radius: var(--border-radius);
			background-color: var(--color-main-background);
			color: var(--color-main-text);
			font-size: 14px;

			&:focus {
				outline: none;
				border-color: var(--color-primary-element);
			}
		}
	}

	.settlement-controls {
		margin-bottom: 20px;

		.amount-section {
			margin-bottom: 12px;

			label {
				display: block;
				font-weight: 500;
				color: var(--color-main-text);
				margin-bottom: 8px;
			}

			.amount-display {
				.partial-amount {
					display: flex;
					align-items: center;
					gap: 8px;

					.amount-input {
						width: 120px;
						padding: 8px 12px;
						border: 1px solid var(--color-border);
						border-radius: var(--border-radius);
						background-color: var(--color-main-background);
						color: var(--color-main-text);
						font-size: 14px;
						text-align: center;

						&:focus {
							outline: none;
							border-color: var(--color-primary-element);
						}
					}

					.currency-label {
						color: var(--color-text-maxcontrast);
						font-weight: 500;
						font-size: 14px;
					}
				}

				.full-amount {
					display: flex;
					align-items: center;
					gap: 8px;

					.amount-value {
						font-weight: 600;
						font-size: 16px;
						color: var(--color-primary);
					}

					.currency-label {
						color: var(--color-text-maxcontrast);
						font-weight: 500;
						font-size: 14px;
					}
				}
			}
		}

		.settlement-type-buttons {
			display: flex;
			justify-content: flex-end;
		}
	}

	.partial-hint {
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
		margin-bottom: 16px;
		font-style: italic;
	}

	// Project breakdown preview
	.project-breakdown-preview {
		background-color: var(--color-background-hover);
		padding: 16px;
		border-radius: var(--border-radius-large);
		margin-bottom: 20px;

		h5 {
			margin: 0 0 12px 0;
			font-size: 0.9em;
			color: var(--color-text-maxcontrast);
		}

		.project-list {
			.project-preview {
				margin-bottom: 12px;
				border-bottom: 1px solid var(--color-border);

				&:last-child {
					border-bottom: none;
					margin-bottom: 0;
				}

				.project-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 12px;
					padding: 8px 0;

					.project-name {
						color: var(--color-main-text);
						font-weight: 500;
						flex: 1;
						min-width: 0;
						overflow: hidden;
						text-overflow: ellipsis;
					}

					.bill-amount {
						color: var(--color-primary);
						font-weight: 600;
						white-space: nowrap;
					}

					.project-amount-input {
						width: 80px;
						padding: 4px 8px;
						border: 1px solid var(--color-border);
						border-radius: var(--border-radius);
						background-color: var(--color-main-background);
						color: var(--color-main-text);
						font-size: 12px;
						text-align: center;

						&:focus {
							outline: none;
							border-color: var(--color-primary-element);
						}
					}
				}

				.project-overpayment {
					margin: 4px 0 0 0;
					padding: 6px 8px;
					font-size: 0.75em;
				}
			}
		}

		.validation-error {
			margin-top: 12px;
			padding: 12px;
			background-color: rgba(var(--color-error-rgb), 0.1);
			border: 1px solid var(--color-error);
			border-radius: var(--border-radius);
			color: var(--cp-negative-text);
			font-size: 0.85em;
			font-weight: 500;
		}

		.validation-warning {
			margin-top: 12px;
			padding: 12px;
			background-color: rgba(var(--color-warning-rgb), 0.1);
			border: 1px solid var(--color-warning);
			border-radius: var(--border-radius);
			color: var(--color-warning);
			font-size: 0.85em;
			font-weight: 500;

			&.overpayment-notice {
				background-color: var(--cp-warning-bg);
				border-color: var(--cp-warning-border);
				color: var(--cp-warning-text);

				strong {
					font-weight: 600;
				}
			}
		}
	}

	.settlement-actions {
		display: flex;
		justify-content: flex-end;
		gap: 12px;
		padding-top: 16px;
		border-top: 1px solid var(--color-border);
	}
}

.person-actions {
	display: flex;
	align-items: flex-start; /* Align with the top of person-info (name level) */
	gap: 8px;
	padding-top: 2px; /* Small offset to align better with name text */

	.button-vue {
		flex-shrink: 0;
		padding: 4px 12px !important;
		font-size: 0.9em;
	}

	:deep(.button-vue__wrapper) {
		gap: 4px !important;
	}

	:deep(.button-vue__icon) {
		width: 16px !important;
		height: 16px !important;
		min-width: 16px !important;
	}
}

	.settlement-controls {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;
		margin-bottom: 12px;

		.amount-section {
			flex: 1;

			label {
				display: block;
				font-weight: 500;
				color: var(--color-main-text);
				margin-bottom: 6px;
				font-size: 0.9em;
			}

			.amount-display {
				.full-amount, .partial-amount {
					display: flex;
					align-items: center;
					gap: 8px;

					.amount-value {
						font-weight: 600;
						font-size: 16px;
						color: var(--color-primary);
					}

					.amount-input {
						width: 100px;
						padding: 6px 8px;
						border: 1px solid var(--color-border);
						border-radius: var(--border-radius);
						background-color: var(--color-main-background);
						color: var(--color-main-text);
						font-size: 14px;
						text-align: center;

						&:focus {
							outline: none;
							border-color: var(--color-primary-element);
						}
					}

					.currency-label {
						color: var(--color-text-maxcontrast);
						font-weight: 500;
						font-size: 14px;
					}
				}
			}
		}

		.settlement-type-buttons {
			flex-shrink: 0;
		}
	}

	.partial-hint {
		font-size: 0.8em;
		color: var(--color-text-maxcontrast);
		margin-bottom: 12px;
		font-style: italic;
	}

	.project-breakdown-preview {
		background-color: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		padding: 12px;
		margin-bottom: 12px;

		h5 {
			margin: 0 0 8px 0;
			font-size: 0.9em;
			color: var(--color-text-maxcontrast);
		}

		.project-list {
			.project-preview {
				margin-bottom: 8px;

				&:last-child {
					margin-bottom: 0;
				}

				.project-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 8px;
					padding: 6px 0;

					.project-name {
						color: var(--color-main-text);
						font-weight: 500;
						flex: 1;
						min-width: 0;
						overflow: hidden;
						text-overflow: ellipsis;
					}

					.bill-amount {
						color: var(--color-primary);
						font-weight: 600;
						white-space: nowrap;
					}

					.project-amount-input {
						width: 80px;
						padding: 4px 6px;
						border: 1px solid var(--color-border);
						border-radius: var(--border-radius);
						background-color: var(--color-main-background);
						color: var(--color-main-text);
						font-size: 12px;
						text-align: center;

						&:focus {
							outline: none;
							border-color: var(--color-primary-element);
						}
					}
				}

				.overpayment-notice {
					margin: 4px 0 0 0;
					padding: 6px 8px;
					font-size: 0.75em;
					background-color: var(--cp-warning-bg);
					border-color: var(--cp-warning-border);
					color: var(--cp-warning-text);

					strong {
						font-weight: 600;
					}
				}
			}
		}

		.validation-error {
			margin-top: 12px;
			padding: 12px;
			background-color: rgba(var(--color-error-rgb), 0.1);
			border: 1px solid var(--color-error);
			border-radius: var(--border-radius);
			color: var(--cp-negative-text);
			font-size: 0.85em;
			font-weight: 500;
		}

		.validation-warning {
			margin-top: 12px;
			padding: 12px;
			background-color: rgba(var(--color-warning-rgb), 0.1);
			border: 1px solid var(--color-warning);
			border-radius: var(--border-radius);
			color: var(--color-warning);
			font-size: 0.85em;
			font-weight: 500;

			&.overpayment-notice {
				background-color: var(--cp-warning-bg);
				border-color: var(--cp-warning-border);
				color: var(--cp-warning-text);

				strong {
					font-weight: 600;
				}
			}
		}
	}

	.settlement-actions {
		display: flex;
		justify-content: flex-end;
		gap: 12px;
		padding-top: 16px;
		border-top: 1px solid var(--color-border);
	}
</style>
