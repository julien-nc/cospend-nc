<!--
	Cross-Project Balance View Component

	This component implements the Cross-project balances feature (GitHub issue #281).
	It displays aggregated balance information showing what the current user owes
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
					<h2>{{ t('cospend', 'Cross-project balances') }}</h2>
					<p class="subtitle">
						{{ t('cospend', 'Overview of your debts and credits across all projects') }}
					</p>
				</div>
				<NcButton type="tertiary"
					:aria-label="t('cospend', 'Close cross-project balances')"
					@click="$emit('close')">
					<template #icon>
						<CloseIcon />
					</template>
				</NcButton>
			</div>
		</div>

		<div v-if="loading" class="loading-container">
			<NcLoadingIcon :size="64" />
			<p>{{ t('cospend', 'Loading cross-project balances...') }}</p>
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
			<!-- Summary Cards -->
			<div class="summary-section">
				<h3>{{ t('cospend', 'Summary') }}</h3>
				<div class="summary-cards">
					<div class="summary-card total-owed">
						<div class="card-icon negative">
							<MinusIcon />
						</div>
						<div class="card-content">
							<h4>{{ t('cospend', 'Total you owe') }}</h4>
							<span class="amount negative">{{ formatCurrency(balanceData.totalOwed) }}</span>
						</div>
					</div>
					<div class="summary-card total-owed-to">
						<div class="card-icon positive">
							<PlusIcon />
						</div>
						<div class="card-content">
							<h4>{{ t('cospend', 'Total owed to you') }}</h4>
							<span class="amount positive">{{ formatCurrency(balanceData.totalOwedTo) }}</span>
						</div>
					</div>
					<div class="summary-card net-balance">
						<div :class="['card-icon', balanceData.netBalance >= 0 ? 'positive' : 'negative']">
							<EqualsIcon />
						</div>
						<div class="card-content">
							<h4>{{ t('cospend', 'Net balance') }}</h4>
							<span :class="['amount', balanceData.netBalance >= 0 ? 'positive' : 'negative']">
								{{ formatCurrency(balanceData.netBalance) }}
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Person-by-person breakdown -->
			<div v-if="balanceData.personBalances && balanceData.personBalances.length > 0" class="person-section">
				<h3>{{ t('cospend', 'Balances by person') }}</h3>
				<div class="person-grid">
					<div v-for="person in sortedPersonBalances"
						:key="person.identifier"
						class="person-card">
						<div class="person-header">
							<div class="person-avatar">
								<ColoredAvatar v-if="person.identifier.startsWith('user:')"
									:user="person.identifier.substring(5)"
									:size="32" />
								<div v-else class="anonymous-avatar">
									{{ person.name.charAt(0).toUpperCase() }}
								</div>
							</div>
							<div class="person-info">
								<h4 class="person-name">
									{{ person.name }}
								</h4>
								<div :class="['person-balance', person.totalBalance < 0 ? 'positive' : 'negative']">
									<span v-if="person.totalBalance < 0">
										{{ t('cospend', 'Owes you: {amount}', { amount: formatCurrency(Math.abs(person.totalBalance)) }) }}
									</span>
									<span v-else>
										{{ t('cospend', 'You owe: {amount}', { amount: formatCurrency(person.totalBalance) }) }}
									</span>
								</div>
							</div>
						</div>

						<!-- Project breakdown for this person (only show if multiple projects) -->
						<div v-if="person.projects && person.projects.length > 1" class="project-breakdown">
							<NcButton type="tertiary"
								:aria-expanded="expandedPersons.includes(person.identifier)"
								@click="togglePersonExpansion(person.identifier)">
								<template #icon>
									<ChevronDownIcon v-if="!expandedPersons.includes(person.identifier)" />
									<ChevronUpIcon v-else />
								</template>
								{{ t('cospend', 'Show {count} projects', { count: person.projects.length }) }}
							</NcButton>

							<!-- Expandable list of individual project balances -->
							<div v-if="expandedPersons.includes(person.identifier)" class="project-list">
								<div v-for="project in person.projects"
									:key="project.projectId"
									class="project-item">
									<span class="project-name">{{ project.projectName }}</span>
									<!-- Project-level balance (positive=we owe, negative=they owe) -->
									<span :class="['project-balance', project.balance >= 0 ? 'positive' : 'negative']">
										{{ formatCurrency(project.balance) }}
									</span>
								</div>
							</div>
						</div>
						<!-- If only one project involved, show project name inline -->
						<div v-else-if="person.projects && person.projects.length === 1" class="single-project">
							<span class="project-name">{{ person.projects[0].projectName }}</span>
						</div>
					</div>
				</div>
			</div>

			<!-- No balances message -->
			<div v-else class="no-balances">
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
		}
	},

	computed: {
		/**
		 * Sort person balances by absolute amount for better UX
		 * Shows largest balances (positive or negative) first
		 */
		sortedPersonBalances() {
			if (!this.balanceData?.personBalances) {
				return []
			}
			// Sort by absolute balance amount (highest first) to prioritize significant balances
			return [...this.balanceData.personBalances].sort((a, b) => {
				return Math.abs(b.totalBalance) - Math.abs(a.totalBalance)
			})
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
				console.error('Failed to load cross-project balances:', error)
				this.error = error.response?.data?.ocs?.meta?.message || t('cospend', 'Failed to load cross-project balances')
				showError(this.error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Format currency amounts for display
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
		 * Toggle expansion of person card to show/hide project details
		 * Maintains state of which cards are expanded
		 * @param {string} personIdentifier Unique identifier for the person
		 */
		togglePersonExpansion(personIdentifier) {
			const index = this.expandedPersons.indexOf(personIdentifier)
			if (index === -1) {
				this.expandedPersons.push(personIdentifier)
			} else {
				this.expandedPersons.splice(index, 1)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cross-project-balances {
	padding: 20px;
	max-width: 1200px;
	margin: 0 auto;
}

.header {
	margin-bottom: 30px;

	.header-content {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 20px;
	}

	.title-section {
		flex: 1;
		text-align: center;
	}

	h2 {
		margin-bottom: 8px;
	}

	.subtitle {
		color: var(--color-text-maxcontrast);
		margin: 0;
	}
}

.loading-container {
	text-align: center;
	padding: 60px 20px;

	p {
		margin-top: 20px;
		color: var(--color-text-maxcontrast);
	}
}

.error-container {
	padding: 40px 20px;
}

.summary-section {
	margin-bottom: 40px;

	h3 {
		margin-bottom: 20px;
	}
}

.summary-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

.summary-card {
	background: var(--color-background-hover);
	border-radius: 12px;
	padding: 20px;
	border: 2px solid var(--color-border);
	display: flex;
	align-items: center;
	gap: 16px;
	transition: all 0.2s ease;

	&:hover {
		border-color: var(--color-primary);
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
	}

	.card-icon {
		width: 48px;
		height: 48px;
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

		h4 {
			margin: 0 0 8px 0;
			font-size: 0.9em;
			color: var(--color-text-maxcontrast);
		}

		.amount {
			font-size: 1.4em;
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

.person-section {
	h3 {
		margin-bottom: 20px;
	}
}

.person-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
	gap: 20px;
}

.person-card {
	background: var(--color-background-hover);
	border-radius: 12px;
	padding: 20px;
	border: 1px solid var(--color-border);
	transition: all 0.2s ease;

	&:hover {
		border-color: var(--color-primary);
		transform: translateY(-1px);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}
}

.person-header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 16px;
}

.person-avatar {
	flex-shrink: 0;
}

.anonymous-avatar {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: var(--color-primary);
	color: white;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: bold;
	font-size: 14px;
}

.person-info {
	flex: 1;
	min-width: 0;
}

.person-name {
	margin: 0 0 4px 0;
	font-size: 1.1em;
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.person-balance {
	font-weight: 500;
	font-size: 0.9em;

	&.positive {
		color: var(--color-success);
	}

	&.negative {
		color: var(--color-error);
	}
}

.project-breakdown {
	margin-top: 12px;

	> .button-vue {
		width: 100%;
		justify-content: space-between;
	}
}

.project-list {
	margin-top: 12px;
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}

.project-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border-dark);

	&:last-child {
		border-bottom: none;
	}
}

.project-name {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	flex: 1;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	margin-right: 12px;
}

.project-balance {
	font-weight: 600;
	font-size: 0.9em;

	&.positive {
		color: var(--color-success);
	}

	&.negative {
		color: var(--color-error);
	}
}

.single-project {
	margin-top: 8px;
	padding-top: 8px;
	border-top: 1px solid var(--color-border);

	.project-name {
		font-size: 0.8em;
		color: var(--color-text-maxcontrast);
	}
}

.no-balances {
	padding: 60px 20px;
}
</style>
