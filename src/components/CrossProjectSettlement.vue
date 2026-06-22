<!--
	Cross-Project Settlement Component

	This component handles creating settlements between the current user and another person
	across multiple projects. It was cleaned up to remove an incomplete sidebar-based
	settlement feature in favor of a direct modal-like approach.

	Settlement Flow:
	1. User selects currency and settlement type (full or partial)
	2. For partial settlements, user can set custom amounts per project
	3. performSettlement() shows confirmation dialog with breakdown
	4. executeSettlement() makes API call to create settlement bills

	Key Features:
	- Full settlement: Settles entire outstanding balance
	- Partial settlement: Custom amounts with per-project breakdown
	- Visual feedback showing remaining debt after settlement
	- Responsive design with mobile-optimized navigation
-->
<template>
	<div class="cross-project-settlement">
		<!-- Main Settlement Interface
			Only shows when a person is selected for settlement.
			This component handles creating settlements between the current user
			and another person across multiple projects.
		-->
		<div v-if="currentSettlementPerson" class="settlement-content">
			<div class="settlement-header">
				<div class="header-content">
					<h2 class="settlement-title">
						{{ t('cospend', 'Settlement with {name}', { name: currentSettlementPerson.member.name }) }}
					</h2>
					<p class="settlement-subtitle">
						{{ t('cospend', 'Select currency and amount to settle') }}
					</p>
				</div>
				<!-- Close button - hidden on mobile where back button is used instead -->
				<NcButton
					type="tertiary"
					class="desktop-only-close"
					:aria-label="t('cospend', 'Cancel settlement')"
					@click="cancelSettlement">
					<template #icon>
						<CloseIcon />
					</template>
				</NcButton>
			</div>

			<!-- Collapsed Summary
				Shows a compact view of the settlement details after configuration is complete.
				This allows users to see key info without taking up too much space.
				Only visible when configurationCollapsed is true.
			-->
			<div v-if="configurationCollapsed" class="configuration-summary">
				<div class="summary-content">
					<h4>{{ t('cospend', 'Settlement Configuration') }}</h4>
					<div class="summary-details">
						<span><strong>{{ t('cospend', 'Currency:') }}</strong> {{ selectedCurrencyCode }}</span>
						<span><strong>{{ t('cospend', 'Type:') }}</strong> {{ settlementTypeOption?.label }}</span>
						<span><strong>{{ t('cospend', 'Amount:') }}</strong>
							<span class="payment-direction" :class="{ 'payment-label': settlementAmount > 0, 'receive-label': settlementAmount < 0 }">
								{{ memoizedFormatCurrencyWithDirection(isPartialSettlement ? (partialSettlementConfirmed ? totalCustomAmount : partialAmount) : Math.abs(settlementAmount), selectedCurrencyCode, settlementAmount > 0) }}
							</span>
						</span>
						<div v-if="showConfirmationDialog && confirmationBreakdown.length > 0" class="project-summary">
							<span><strong>{{ t('cospend', 'Projects:') }}</strong></span>
							<div class="project-list-summary">
								<div v-for="project in confirmationBreakdown" :key="project.id" class="project-item-summary">
									<div class="project-item-header">
										<span class="project-name">{{ project.name }}</span>
										<span class="project-amount" :class="{ 'payment-amount': settlementAmount > 0, 'receive-amount': settlementAmount < 0 }">
											{{ memoizedFormatCurrencyWithDirection(project.billAmount, selectedCurrencyCode, settlementAmount > 0) }}
										</span>
									</div>
									<div v-if="project.datetime || project.paymentMode || project.comment" class="project-optional-fields">
										<div v-if="project.datetime" class="optional-field-display">
											<CalendarIcon :size="16" />
											<span>{{ formatDateTime(project.datetime) }}</span>
										</div>
										<div v-if="project.paymentMode" class="optional-field-display">
											<TagIcon :size="16" />
											<span>{{ project.paymentMode.label }}</span>
										</div>
										<div v-if="project.comment" class="optional-field-display">
											<span class="comment-text">"{{ project.comment }}"</span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Configuration Sections
				Contains all the settlement setup controls (currency, type, amounts).
				Hidden when configurationCollapsed is true to save space after setup.
			-->
			<div v-show="!configurationCollapsed" class="all-configuration-sections">
				<!-- Settlement Configuration Controls -->
				<div class="settlement-configuration">
					<!-- Currency Selection
						Let users pick which currency to settle in from available currencies
						where there's an actual balance to settle.
					-->
					<div class="config-section">
						<label for="settlement-currency">{{ t('cospend', 'Settlement currency') }}</label>
						<NcSelect
							id="settlement-currency"
							v-model="selectedCurrency"
							:options="currencyOptions"
							label="label"
							track-by="id"
							:label-outside="true"
							:aria-label="t('cospend', 'Choose currency for settlement')"
							@input="onCurrencyChange" />
					</div>

					<!-- Settlement Type Selection
						Full settlement: Settle the entire outstanding balance
						Partial settlement: Allow user to specify custom amounts per project
					-->
					<div class="config-section">
						<label for="settlement-type">{{ t('cospend', 'Settlement type') }}</label>
						<NcSelect
							id="settlement-type"
							v-model="settlementTypeOption"
							:options="settlementTypeOptions"
							label="label"
							track-by="id"
							:label-outside="true"
							:aria-label="t('cospend', 'Choose settlement type')"
							@input="onSettlementTypeChange" />
					</div>
				</div>

				<!-- Amount Configuration Section
					Shows different UI based on settlement type:
					- Full settlement: Display total amount (read-only)
					- Partial settlement: Input field for custom total amount
				-->
				<div class="amount-section">
					<!-- Payment Direction Labels
						Clearly indicate whether the user owes money (pay) or is owed money (receive)
						Based on the sign of settlementAmount
					-->
					<label v-if="isPartialSettlement"
						for="partial-amount-input"
						:class="{ 'payment-label': settlementAmount > 0, 'receive-label': settlementAmount < 0 }">
						{{ settlementAmount > 0 ? t('cospend', 'You need to pay:') : t('cospend', 'You need to receive:') }}
					</label>
					<label v-else
						:class="{ 'payment-label': settlementAmount > 0, 'receive-label': settlementAmount < 0 }">
						{{ settlementAmount > 0 ? t('cospend', 'You need to pay:') : t('cospend', 'You need to receive:') }}
					</label>

					<!-- Amount Input/Display
						For partial settlements: Editable input with validation
						For full settlements: Read-only display of total amount
					-->
					<div class="config-section">
						<input
							v-if="isPartialSettlement"
							id="partial-amount-input"
							v-model.number="partialAmount"
							type="number"
							:step="0.01"
							:min="0"
							:max="Math.abs(settlementAmount)"
							class="amount-input"
							:disabled="partialSettlementConfirmed"
							:aria-label="t('cospend', 'Amount to settle partially')"
							:placeholder="t('cospend', 'Enter amount')"
							@input="validatePartialAmount">
						<div v-else class="amount-display">
							<span class="amount-value" :class="{ 'payment': settlementAmount > 0, 'receive': settlementAmount < 0 }">
								{{ memoizedFormatCurrency(Math.abs(settlementAmount), selectedCurrencyCode) }}
							</span>
						</div>
					</div>

					<!-- Currency Label -->
					<div class="config-section">
						<span v-if="isPartialSettlement" class="currency-label">{{ selectedCurrencyCode }}</span>
					</div>

					<!-- Action Buttons for Partial Settlement Flow
						"Set custom amounts" - confirms the partial amount and moves to per-project breakdown
						"Reset" - allows changing the partial amount after confirmation
					-->
					<div class="config-section">
						<NcButton
							v-if="isPartialSettlement && !partialSettlementConfirmed && canConfirmPartial"
							type="primary"
							:aria-label="t('cospend', 'Confirm partial amount and proceed to project distribution')"
							@click="confirmPartialSettlement">
							{{ t('cospend', 'Set custom amounts') }}
						</NcButton>
						<NcButton
							v-if="isPartialSettlement && partialSettlementConfirmed"
							type="secondary"
							:aria-label="t('cospend', 'Change partial settlement amount')"
							@click="resetPartialSettlement">
							{{ t('cospend', 'Reset') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- Project Breakdown Preview
				Shows how the settlement amount will be distributed across projects.
				Hidden during final confirmation dialog to avoid clutter.
				For partial settlements, shows custom amount inputs for each project.
			-->
			<div v-if="projectBreakdown.length > 0 && !showConfirmationDialog" class="project-breakdown-preview">
				<div class="project-list">
					<div v-for="project in projectBreakdown" :key="project.id" class="project-preview">
						<div class="project-header">
							<span class="project-name">{{ project.name }}</span>
							<!-- Show debt flow info in header -->
							<div v-if="partialSettlementConfirmed" class="debt-flow-header">
								<span class="current-debt-header" :class="{ 'payment-debt': settlementAmount > 0, 'receive-debt': settlementAmount < 0 }">
									{{ memoizedFormatCurrency(project.originalBalance, selectedCurrencyCode) }}
								</span>
								<span class="arrow-header" :aria-label="t('cospend', 'becomes')">→</span>
								<span :id="`debt-info-${project.id}`"
									class="remaining-debt-header"
									:class="{
										'settled': remainingDebt(project) <= 0,
										'reduced': remainingDebt(project) > 0,
										'payment-remaining': settlementAmount > 0,
										'receive-remaining': settlementAmount < 0
									}"
									:aria-label="remainingDebt(project) <= 0 ? t('cospend', 'Debt will be settled') : t('cospend', 'Remaining debt: {amount}', { amount: memoizedFormatCurrency(remainingDebt(project), selectedCurrencyCode) })">
									{{ remainingDebt(project) <= 0 ? t('cospend', 'Settled') : memoizedFormatCurrency(remainingDebt(project), selectedCurrencyCode) }}
								</span>
							</div>
						</div>
						<div class="project-details">
							<div class="amount-input-row">
								<span v-if="!partialSettlementConfirmed"
									class="settlement-amount"
									:class="{ 'payment-amount': settlementAmount > 0, 'receive-amount': settlementAmount < 0 }">
									{{ memoizedFormatCurrency(project.amount, selectedCurrencyCode) }}
								</span>
								<!-- Custom amount input when partial settlement is confirmed -->
								<div v-else class="project-input-container">
									<label :for="`project-amount-${project.id}`"
										:class="{ 'payment-label': settlementAmount > 0, 'receive-label': settlementAmount < 0 }">
										{{ settlementAmount > 0 ? t('cospend', 'You Pay:') : t('cospend', 'You Receive:') }}
									</label>
									<div class="input-and-debt-container">
										<div class="project-input-controls">
											<input
												:id="`project-amount-${project.id}`"
												:value="projectCustomAmounts[project.id] || 0"
												type="number"
												:step="0.01"
												:min="0"
												:max="getProjectInputMax(project)"
												:placeholder="t('cospend', 'Enter amount')"
												:aria-label="t('cospend', 'Settlement amount for {projectName}', { projectName: project.name })"
												:aria-describedby="`debt-info-${project.id}`"
												class="project-amount-input"
												@input="updateCustomAmount(project.id, $event.target.value)">
											<span class="currency-label">{{ selectedCurrencyCode }}</span>
										</div>
										<!-- Inline overpayment warning -->
										<span v-if="partialSettlementConfirmed && projectCustomAmounts[project.id] && projectCustomAmounts[project.id] > project.originalBalance"
											class="overpayment-notice-inline">
											<strong>{{ t('cospend', 'Note:') }}</strong>
											{{ t('cospend', 'Exceeds original debt of {amount}', {
												amount: memoizedFormatCurrency(project.originalBalance, selectedCurrencyCode)
											}) }}
										</span>
									</div>
								</div>
							</div>

							<!-- Additional Details Section for this project -->
							<div class="project-optional-fields">
								<NcButton
									type="tertiary"
									class="optional-fields-toggle"
									:aria-expanded="getProjectOptionalField(project.id, 'showOptionalFields')"
									@click="toggleProjectOptionalFields(project.id)">
									<template #icon>
										<ChevronDownIcon v-if="!getProjectOptionalField(project.id, 'showOptionalFields')" :size="20" />
										<ChevronUpIcon v-else :size="20" />
									</template>
									{{ t('cospend', 'Additional details') }}
								</NcButton>

								<div v-if="getProjectOptionalField(project.id, 'showOptionalFields')" class="optional-fields-content">
									<!-- When? and Payment Mode in a row -->
									<div class="optional-fields-row">
										<!-- When? - Date/Time Picker -->
										<div class="optional-field">
											<label :for="`settlement-date-${project.id}`">
												<CalendarIcon :size="20" />
												{{ t('cospend', 'When?') }}
											</label>
											<NcDateTimePicker
												:id="`settlement-date-${project.id}`"
												:value="getProjectOptionalField(project.id, 'datetime')"
												:label="t('cospend', 'Settlement date and time')"
												type="datetime"
												format="yyyy-MM-dd HH:mm"
												@input="setProjectOptionalField(project.id, 'datetime', $event)" />
										</div>

										<!-- Payment Mode Selection -->
										<div class="optional-field">
											<label :for="`settlement-paymentmode-${project.id}`">
												<TagIcon :size="20" />
												{{ t('cospend', 'Payment mode') }}
											</label>
											<NcSelect
												:id="`settlement-paymentmode-${project.id}`"
												:value="getProjectOptionalField(project.id, 'paymentMode')"
												:options="getPaymentModeOptionsForProject(project.id)"
												:placeholder="t('cospend', 'None')"
												:clearable="true"
												label="label"
												track-by="id"
												:label-outside="true"
												:aria-label="t('cospend', 'Choose payment mode')"
												@input="setProjectOptionalField(project.id, 'paymentMode', $event)" />
										</div>
									</div>

									<!-- Comment Field -->
									<div class="optional-field">
										<label :for="`settlement-comment-${project.id}`">{{ t('cospend', 'Comment') }}</label>
										<textarea
											:id="`settlement-comment-${project.id}`"
											:value="getProjectOptionalField(project.id, 'comment')"
											:placeholder="t('cospend', 'More details about the bill (300 characters max)')"
											rows="3"
											maxlength="300"
											class="comment-textarea"
											@input="setProjectOptionalField(project.id, 'comment', $event.target.value)" />
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Validation summary for partial settlements -->
				<div v-if="partialSettlementConfirmed" class="validation-section">
					<div class="amount-summary">
						<div class="summary-item">
							<span class="label">{{ t('cospend', 'Target total:') }}</span>
							<span class="amount" :class="{ 'payment-amount': settlementAmount > 0, 'receive-amount': settlementAmount < 0 }">{{ memoizedFormatCurrency(partialAmount, selectedCurrencyCode) }}</span>
						</div>
						<div class="summary-item">
							<span class="label">{{ t('cospend', 'Current total:') }}</span>
							<span class="amount"
								:class="{
									'valid': hasValidCustomAmounts && totalCustomAmount > 0,
									'zero': totalCustomAmount === 0,
									'invalid': totalCustomAmount > 0 && !hasValidCustomAmounts,
									'payment-amount': settlementAmount > 0 && hasValidCustomAmounts && totalCustomAmount > 0,
									'receive-amount': settlementAmount < 0 && hasValidCustomAmounts && totalCustomAmount > 0
								}">
								{{ memoizedFormatCurrency(totalCustomAmount, selectedCurrencyCode) }}
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Settlement Actions -->
			<div class="settlement-actions">
				<!-- Primary Settlement Creation Button -->
				<NcButton
					v-if="!showConfirmationDialog"
					type="primary"
					:disabled="!canSettle"
					:loading="isCreatingSettlement"
					@click="performSettlement">
					<template #icon>
						<ReimburseIcon />
					</template>
					{{ t('cospend', 'Create settlement') }}
				</NcButton>

				<!-- Final Confirmation Buttons
					Only shown when user has reviewed settlement breakdown
					and is ready to commit the settlement
				-->
				<div v-if="showConfirmationDialog" class="simple-confirmation">
					<NcButton
						type="tertiary"
						:disabled="isCreatingSettlement"
						:aria-label="t('cospend', 'Cancel settlement creation')"
						@click="cancelConfirmation">
						{{ t('cospend', 'Cancel') }}
					</NcButton>
					<NcButton
						type="primary"
						:disabled="isCreatingSettlement"
						:loading="isCreatingSettlement"
						:aria-label="t('cospend', 'Create the settlement as shown')"
						@click="executeSettlement">
						<template #icon>
							<CheckIcon />
						</template>
						{{ isCreatingSettlement ? t('cospend', 'Creating...') : t('cospend', 'Confirm settlement') }}
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
// Component imports - Nextcloud Vue components for consistent UI
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'

// Material Design icons for buttons
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ReimburseIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'

// Nextcloud utilities
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

// API function to create the actual settlement
import { createCrossProjectSettlement } from '../network.js'

const cospend = OCA.Cospend.state

export default {
	name: 'CrossProjectSettlement',

	components: {
		NcButton,
		NcSelect,
		NcDateTimePicker,
		CloseIcon,
		ReimburseIcon,
		CheckIcon,
		CalendarIcon,
		TagIcon,
		ChevronDownIcon,
		ChevronUpIcon,
	},

	props: {
		/**
		 * Person object containing member info and currency balances
		 * Structure:
		 * {
		 *   member: { name: string, id: string },
		 *   currencyBalances: {
		 *     [currency]: { totalBalance: number }
		 *   },
		 *   projects: [{
		 *     id: string,
		 *     name: string,
		 *     currency: string,
		 *     balance: number
		 *   }]
		 * }
		 */
		currentSettlementPerson: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			// Selected currency for settlement (can be object or string)
			selectedCurrency: null,

			// Settlement type flags
			isPartialSettlement: false,
			partialAmount: 0, // User-specified amount for partial settlement
			partialSettlementConfirmed: false, // Whether user confirmed partial amount

			// Custom amounts for each project in partial settlement
			// Structure: { [projectId]: amount }
			projectCustomAmounts: {},

			// Optional fields for each project
			// Structure: { [projectId]: { showOptionalFields: boolean, datetime: Date, paymentMode: object, comment: string } }
			projectOptionalFields: {},

			// Confirmation dialog state
			showConfirmationDialog: false,
			confirmationBreakdown: [], // Projects data for final confirmation
			confirmationTotalAmount: 0,

			// Settlement type selection (full or partial)
			settlementTypeOption: null,

			// UI state - whether to show collapsed summary instead of full config
			configurationCollapsed: false,

			// Loading state to prevent double-submissions
			isCreatingSettlement: false,
		}
	},
	computed: {
		// ============================================
		// Currency and Options Processing
		// ============================================

		// Get currencies that have an actual balance to settle (> 0.01 to avoid tiny amounts)
		availableCurrencies() {
			if (!this.currentSettlementPerson?.currencyBalances) {
				return []
			}
			return Object.keys(this.currentSettlementPerson.currencyBalances)
				.filter(currency => Math.abs(this.currentSettlementPerson.currencyBalances[currency].totalBalance) > 0.01)
		},

		// Format currencies for NcSelect component
		currencyOptions() {
			return this.availableCurrencies.map(currency => ({
				id: currency,
				label: currency,
			}))
		},

		// Get the currency code whether selectedCurrency is an object or string
		selectedCurrencyCode() {
			return typeof this.selectedCurrency === 'string' ? this.selectedCurrency : this.selectedCurrency?.id
		},

		// Options for settlement type dropdown
		settlementTypeOptions() {
			return [
				{ id: 'full', label: t('cospend', 'Full settlement') },
				{ id: 'partial', label: t('cospend', 'Partial settlement') },
			]
		},

		// Total amount owed/owing in the selected currency
		// Positive = user owes money, Negative = user is owed money
		settlementAmount() {
			if (!this.currentSettlementPerson || !this.selectedCurrencyCode) return 0
			return this.currentSettlementPerson.currencyBalances[this.selectedCurrencyCode]?.totalBalance || 0
		},

		canSettle() {
			if (!this.currentSettlementPerson || this.isCreatingSettlement) return false
			const amount = this.isPartialSettlement ? this.partialAmount : Math.abs(this.settlementAmount)
			if (amount <= 0 || !this.selectedCurrencyCode) return false

			// For confirmed partial settlements, require the split to match the target amount.
			if (this.partialSettlementConfirmed) {
				return this.totalCustomAmount > 0 && this.hasValidCustomAmounts
			}

			return true
		},

		// ============================================
		// Project Breakdown and Validation
		// ============================================

		/**
		 * Calculate how settlement amount should be distributed across projects
		 * Uses proportional distribution based on project balance amounts
		 * Returns array of project objects with calculated settlement amounts
		 */
		projectBreakdown() {
			if (!this.currentSettlementPerson || !this.selectedCurrencyCode) return []

			// Get projects for this currency
			const projects = this.currentSettlementPerson.projects
				.filter(p => p.currency === this.selectedCurrencyCode && Math.abs(p.balance) > 0.01)

			if (projects.length === 0) return []

			const totalAmount = this.isPartialSettlement ? this.partialAmount : Math.abs(this.settlementAmount)
			const totalProjectBalance = projects.reduce((sum, p) => sum + Math.abs(p.balance), 0)

			if (Math.abs(totalAmount - totalProjectBalance) < 0.01) {
				// Full settlement: use exact project balances
				return projects.map(project => ({
					id: project.projectId,
					name: project.projectName,
					originalBalance: Math.abs(project.balance),
					amount: Math.abs(project.balance),
					customAmount: this.projectCustomAmounts[project.projectId] || null,
				}))
			}

			// Partial settlement: distribute proportionally with whole numbers
			let remainingAmount = totalAmount
			const projectAmounts = projects.map((project, index) => {
				const proportion = Math.abs(project.balance) / totalProjectBalance
				let amount

				if (index === projects.length - 1) {
					// Last project gets remaining amount to ensure total matches
					amount = remainingAmount
				} else {
					// Round down to whole number for all other projects
					amount = Math.floor(proportion * totalAmount)
					remainingAmount -= amount
				}

				return {
					id: project.projectId,
					name: project.projectName,
					originalBalance: Math.abs(project.balance),
					amount: Math.max(0, amount), // Ensure non-negative
					customAmount: this.projectCustomAmounts[project.projectId] || null,
				}
			})

			return projectAmounts
		},

		// ============================================
		// Validation and State Checks
		// ============================================

		/**
		 * Check if partial amount can be confirmed
		 * Must be positive and not exceed total settlement amount
		 */
		canConfirmPartial() {
			if (!this.isPartialSettlement || this.partialSettlementConfirmed) return false
			return this.partialAmount > 0 && this.partialAmount <= Math.abs(this.settlementAmount)
		},

		/**
		 * Sum of all custom project amounts in partial settlement mode
		 * Used for validation and final confirmation
		 */
		totalCustomAmount() {
			if (!this.partialSettlementConfirmed) return 0
			return Object.values(this.projectCustomAmounts).reduce((sum, amount) => {
				return sum + (parseFloat(amount) || 0)
			}, 0)
		},

		/**
		 * Validate that custom amounts approximately match partial amount
		 * Allows small tolerance for rounding differences
		 */
		hasValidCustomAmounts() {
			if (!this.partialSettlementConfirmed) return true
			const tolerance = 0.01
			return Math.abs(this.totalCustomAmount - this.partialAmount) <= tolerance
		},

		/**
		 * Determine if input fields should be constrained when target is reached
		 * For better UX, we allow complete user control over settlement amounts
		 */
		shouldConstrainInputs() {
			return false // Let users do whatever they want
		},

		/**
		 * Calculate remaining debt for a project after settlement
		 */
		remainingDebt() {
			return (project) => {
				const settleAmount = this.projectCustomAmounts[project.id] || 0
				return Math.max(0, project.originalBalance - settleAmount)
			}
		},
	},

	watch: {
		// ============================================
		// Reactive State Management
		// ============================================

		/**
		 * Watch for person changes and initialize settlement defaults
		 * Sets up initial currency selection and settlement type when a new person is selected
		 */
		currentSettlementPerson: {
			handler(newPerson) {
				if (newPerson && this.availableCurrencies.length > 0) {
					// Reset all state first to ensure clean slate
					this.resetAllSettlementState()

					// Set default currency to the first available currency as an object for NcSelect
					const defaultCurrency = this.availableCurrencies[0]
					this.selectedCurrency = this.currencyOptions.find(option => option.id === defaultCurrency)
					// Initialize settlement type option to full settlement
					this.settlementTypeOption = this.settlementTypeOptions[0] // Full settlement
				}
			},
			immediate: true,
		},

		/**
		 * React to settlement type changes and switch modes accordingly
		 * Handles both string IDs and full option objects from NcSelect
		 */
		settlementTypeOption: {
			handler(newType) {
				const typeId = typeof newType === 'string' ? newType : newType?.id
				if (typeId === 'partial') {
					this.enablePartialSettlement()
				} else {
					this.disablePartialSettlement()
				}
			},
			immediate: true,
		},

		/**
		 * Force reactivity updates when custom amounts change
		 * Ensures computed properties recalculate when project amounts are modified
		 */
		projectCustomAmounts: {
			handler() {
				// Force computed properties to recalculate when amounts change
				this.$nextTick(() => {
					// This triggers reactivity for the totalCustomAmount computed property
				})
			},
			deep: true,
		},
	},

	methods: {
		// ============================================
		// Configuration Handlers
		// ============================================

		/**
		 * Handle currency selection from dropdown
		 * Resets settlement state when currency changes
		 * @param {string|object} currency - The selected currency from NcSelect
		 */
		onCurrencyChange(currency) {
			this.selectedCurrency = currency // Keep full object for NcSelect binding
			this.resetSettlementState()
		},

		/**
		 * Handle settlement type selection (full vs partial)
		 * Switches between different settlement modes
		 * @param {string|object} option - The settlement type option from NcSelect
		 */
		onSettlementTypeChange(option) {
			const optionId = typeof option === 'string' ? option : option?.id
			this.settlementTypeOption = option // Keep full object for NcSelect binding

			if (optionId === 'partial') {
				this.enablePartialSettlement()
			} else {
				this.disablePartialSettlement()
			}
		},

		// ============================================
		// Project Optional Fields Helpers
		// ============================================

		/**
		 * Initialize optional fields for a project if not already initialized
		 * @param {string} projectId - The project ID
		 */
		initProjectOptionalFields(projectId) {
			if (!this.projectOptionalFields[projectId]) {
				this.projectOptionalFields[projectId] = {
					showOptionalFields: false,
					datetime: new Date(),
					paymentMode: null,
					comment: '',
				}
			}
		},

		/**
		 * Toggle the optional fields visibility for a specific project
		 * @param {string} projectId - The project ID
		 */
		toggleProjectOptionalFields(projectId) {
			this.initProjectOptionalFields(projectId)
			this.projectOptionalFields[projectId].showOptionalFields = !this.projectOptionalFields[projectId].showOptionalFields
		},

		/**
		 * Get an optional field value for a project
		 * @param {string} projectId - The project ID
		 * @param {string} field - The field name (showOptionalFields, datetime, paymentMode, comment)
		 * @return {*} The field value or default
		 */
		getProjectOptionalField(projectId, field) {
			this.initProjectOptionalFields(projectId)
			return this.projectOptionalFields[projectId]?.[field]
		},

		/**
		 * Set an optional field value for a project
		 * @param {string} projectId - The project ID
		 * @param {string} field - The field name (datetime, paymentMode, comment)
		 * @param {*} value - The value to set
		 */
		setProjectOptionalField(projectId, field, value) {
			this.initProjectOptionalFields(projectId)
			this.projectOptionalFields[projectId][field] = value
		},

		/**
		 * Get payment mode options for a specific project
		 * @param {string} projectId - The project ID
		 * @return {Array} Array of payment mode options
		 */
		getPaymentModeOptionsForProject(projectId) {
			const projectPaymentModes = cospend.projects?.[projectId]?.paymentmodes || {}
			const options = Object.values(projectPaymentModes).map(pm => ({
				id: pm.id,
				label: `${pm.icon || ''} ${pm.name || ''}`.trim(),
				name: pm.name,
				icon: pm.icon,
				color: pm.color,
			}))

			return [
				{ id: null, label: t('cospend', 'No payment mode'), name: null, icon: '', color: '#000000' },
				...options,
			]
		},

		// ============================================
		// Partial Settlement Helpers
		// ============================================

		/**
		 * Calculate maximum input value for project amount inputs
		 * Allows reasonable overpayment while preventing extreme values
		 * This prevents users from entering extremely large amounts accidentally
		 * @param {object} project - The project object with originalBalance property
		 * @return {number} Maximum allowed input value (10x original balance)
		 */
		getProjectInputMax(project) {
			// Allow generous maximum to give users full control
			// If they want to overpay and create reverse debt, that's their choice
			return project.originalBalance * 10
		},
		// ============================================
		// Utility and Formatting Methods
		// ============================================

		/**
		 * Format currency amount for display
		 * Handles both string currency codes and NcSelect option objects
		 * Uses Intl.NumberFormat for locale-aware number formatting
		 * @param {number} amount The amount to format
		 * @param {string|object} currency The currency code or object
		 * @return {string} Formatted currency string
		 */
		formatCurrency(amount, currency) {
			if (amount === undefined || amount === null) return '0'
			// Handle both direct string and option object from NcSelect
			const currencyCode = typeof currency === 'string' ? currency : currency?.id || currency?.label || 'EUR'
			const formatted = new Intl.NumberFormat(navigator.language, {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(Math.abs(amount))
			return `${currencyCode} ${formatted}`
		},

		/**
		 * Format currency amount with payment direction for confirmation display
		 * Shows "You pay" or "You receive" along with the formatted amount
		 * Used in confirmation dialogs and settlement summaries
		 * @param {number} amount The amount to format
		 * @param {string|object} currency The currency code or object
		 * @param {boolean} isPayment True if this is a payment (user pays), false if receipt (user receives)
		 * @return {string} Formatted currency string with payment direction
		 */
		formatCurrencyWithDirection(amount, currency, isPayment) {
			if (amount === undefined || amount === null) return '0'
			const currencyCode = typeof currency === 'string' ? currency : currency?.id || currency?.label || 'EUR'
			const formatted = new Intl.NumberFormat(navigator.language, {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(Math.abs(amount))
			const direction = isPayment ? t('cospend', 'You pay') : t('cospend', 'You receive')
			return `${direction} ${currencyCode} ${formatted}`
		},

		// Direct formatters (memoization removed for simplicity)
		memoizedFormatCurrency(amount, currency) {
			return this.formatCurrency(amount, currency)
		},

		memoizedFormatCurrencyWithDirection(amount, currency, isPayment) {
			return this.formatCurrencyWithDirection(amount, currency, isPayment)
		},

		/**
		 * Normalize money-like numbers to two decimals to avoid floating point artifacts.
		 * @param {number|string} value Number-like input to normalize.
		 * @return {number}
		 */
		roundAmount(value) {
			const num = Number(value)
			if (!Number.isFinite(num)) {
				return 0
			}
			return Math.round((num + Number.EPSILON) * 100) / 100
		},

		/**
		 * Format a Date object for display in confirmation dialog
		 * @param {Date} date - The date to format
		 * @return {string} Formatted date string
		 */
		formatDateTime(date) {
			if (!date) return ''
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')
			const hours = String(date.getHours()).padStart(2, '0')
			const minutes = String(date.getMinutes()).padStart(2, '0')
			return `${year}-${month}-${day} ${hours}:${minutes}`
		},

		// ============================================
		// State Management and Reset Methods
		// ============================================

		/**
		 * Reset all settlement state to initial values
		 * Called when switching to a different person for settlement
		 */
		resetAllSettlementState() {
			this.isPartialSettlement = false
			this.partialAmount = 0
			this.partialSettlementConfirmed = false
			this.projectCustomAmounts = {}
			this.showConfirmationDialog = false
			this.confirmationBreakdown = []
			this.confirmationTotalAmount = 0
			this.configurationCollapsed = false
			this.isCreatingSettlement = false
			this.selectedCurrency = null
			this.settlementTypeOption = null

			// Reset to default options when available
			if (this.availableCurrencies.length > 0) {
				const defaultCurrency = this.availableCurrencies[0]
				this.selectedCurrency = this.currencyOptions.find(option => option.id === defaultCurrency)
			}
			if (this.settlementTypeOptions && this.settlementTypeOptions.length > 0) {
				this.settlementTypeOption = this.settlementTypeOptions[0] // Full settlement
			}
		},

		/**
		 * Reset settlement state when switching persons or currencies
		 * Clears all partial settlement data and custom amounts
		 * Returns component to default full settlement mode
		 */
		resetSettlementConfiguration() {
			this.isPartialSettlement = false
			this.partialAmount = 0
			this.partialSettlementConfirmed = false
			this.projectCustomAmounts = {}
			// Reset to full settlement if options are available
			if (this.settlementTypeOptions && this.settlementTypeOptions.length > 0) {
				this.settlementTypeOption = this.settlementTypeOptions[0] // Full settlement
			}
		},

		/**
		 * Enable partial settlement mode
		 * Sets default partial amount to the full settlement amount
		 * User can then adjust this amount and distribute it across projects
		 */
		enablePartialSettlement() {
			this.isPartialSettlement = true
			this.partialAmount = this.roundAmount(Math.abs(this.settlementAmount))
		},

		/**
		 * Disable partial settlement mode
		 * Returns to full settlement mode and clears all partial data
		 */
		disablePartialSettlement() {
			this.resetSettlementConfiguration()
		},

		/**
		 * Confirm partial settlement and enable custom amount editing
		 * Initializes project-specific amount inputs for detailed control
		 * User can then specify exactly how much to settle per project
		 */
		confirmPartialSettlement() {
			this.partialSettlementConfirmed = true
			// Initialize custom amounts to empty - let users decide the split
			// Create reactive object with all project IDs for better performance
			const initialAmounts = {}
			this.projectBreakdown.forEach(project => {
				initialAmounts[project.id] = 0
			})
			this.projectCustomAmounts = { ...initialAmounts }
		},

		/**
		 * Reset partial settlement to allow amount changes
		 */
		resetPartialSettlement() {
			this.partialSettlementConfirmed = false
			this.projectCustomAmounts = {}
		},

		/**
		 * Validate partial amount input
		 */
		validatePartialAmount() {
			const maxAmount = this.roundAmount(Math.abs(this.settlementAmount))
			this.partialAmount = this.roundAmount(this.partialAmount)
			if (this.partialAmount > maxAmount) {
				this.partialAmount = maxAmount
			} else if (this.partialAmount < 0) {
				this.partialAmount = 0
			}
		},

		/**
		 * Update custom amount for a specific project
		 * Handles input validation and Vue reactivity for dynamic properties
		 * @param {string} projectId The ID of the project to update
		 * @param {string} value The new amount value as a string from input
		 */
		updateCustomAmount(projectId, value) {
			const numValue = this.roundAmount(parseFloat(value) || 0)
			// Use Vue.set for reactivity with dynamic object properties
			this.projectCustomAmounts = {
				...this.projectCustomAmounts,
				[projectId]: numValue,
			}
		},

		// ============================================
		// Main Settlement Flow
		// ============================================

		/**
		 * Reset all settlement state to initial values
		 * Used after successful settlement completion
		 */
		resetSettlementState() {
			// Reset all UI state
			this.showConfirmationDialog = false
			this.configurationCollapsed = false
			this.isCreatingSettlement = false

			// Reset settlement type and amounts
			this.isPartialSettlement = false
			this.partialAmount = 0
			this.partialSettlementConfirmed = false
			this.projectCustomAmounts = {}

			// Reset confirmation data
			this.confirmationBreakdown = []
			this.confirmationTotalAmount = 0

			// Reset currency and type selections
			this.selectedCurrency = null
			this.settlementTypeOption = null
		},

		/**
		 * Cancel settlement and return to previous view
		 * Emits event to parent component to handle navigation
		 */
		cancelSettlement() {
			this.$emit('cancel-settlement')
		},

		/**
		 * Prepare and show confirmation dialog for settlement
		 * This is the first step in the two-step settlement process:
		 * 1. performSettlement() - shows confirmation with project breakdown
		 * 2. executeSettlement() - actually creates the settlement via API
		 *
		 * Calculates final amounts, prepares breakdown data, and shows confirmation UI
		 */
		async performSettlement() {
			// Collapse config UI to make room for confirmation details
			this.configurationCollapsed = true

			// Calculate the total amount that will be settled
			this.confirmationTotalAmount = this.isPartialSettlement ? this.totalCustomAmount : Math.abs(this.settlementAmount)

			// Prepare project-by-project breakdown for user review
			this.confirmationBreakdown = this.projectBreakdown.map(project => {
				let billAmount = project.amount

				// Use custom amounts if partial settlement was confirmed
				if (this.partialSettlementConfirmed && this.projectCustomAmounts[project.id] !== null) {
					billAmount = this.projectCustomAmounts[project.id] || 0
				}

				// Get optional fields for this project
				const optionalFields = this.projectOptionalFields[project.id] || {}

				return {
					id: project.id,
					name: project.name,
					originalBalance: project.originalBalance,
					billAmount,
					remainingDebt: Math.max(0, project.originalBalance - billAmount),
					// Include optional fields for display
					datetime: optionalFields.datetime || null,
					paymentMode: optionalFields.paymentMode || null,
					comment: optionalFields.comment || null,
				}
			}).filter(project => project.billAmount > 0) // Only show projects that will actually be settled

			this.showConfirmationDialog = true
		},

		/**
		 * Cancel the confirmation dialog and return to configuration
		 * Restores the configuration UI and clears confirmation data
		 */
		cancelConfirmation() {
			this.showConfirmationDialog = false
			this.confirmationBreakdown = []
			this.confirmationTotalAmount = 0
			this.configurationCollapsed = false // Show config sections again
		},

		/**
		 * Actually create the settlement via API call
		 * This is the final step that makes the API request to create settlement bills
		 *
		 * Process:
		 * 1. Validates settlement can proceed
		 * 2. Builds API request data structure
		 * 3. Calls createCrossProjectSettlement API
		 * 4. Shows success message and emits settlement-created event
		 * 5. Handles errors with user-friendly messages
		 */
		async executeSettlement() {
			if (!this.canSettle || this.isCreatingSettlement) return

			this.isCreatingSettlement = true

			try {
				// Calculate project breakdown
				const projectBreakdown = this.projectBreakdown.map(project => {
					let billAmount

					if (this.partialSettlementConfirmed && this.projectCustomAmounts[project.id] !== null) {
						// Use custom amount if specified
						billAmount = this.projectCustomAmounts[project.id] || 0
					} else if (this.isPartialSettlement) {
						// Use proportional amount for partial settlement
						billAmount = project.amount
					} else {
						// Use full amount for complete settlement
						billAmount = project.amount
					}

					// Get optional fields for this project
					const optionalFields = this.projectOptionalFields[project.id] || {}
					const projectData = {
						projectId: project.id,
						billAmount: Math.abs(billAmount),
					}

					// Add optional fields if they have been set
					if (optionalFields.datetime) {
						// Convert to Unix timestamp (seconds)
						projectData.timestamp = Math.floor(optionalFields.datetime.getTime() / 1000)
					}
					if (optionalFields.paymentMode && optionalFields.paymentMode.id !== null) {
						projectData.paymentModeId = optionalFields.paymentMode.id
					}
					if (optionalFields.comment && optionalFields.comment.trim()) {
						projectData.comment = optionalFields.comment.trim()
					}

					return projectData
				}).filter(project => project.billAmount >= 0.01)

				// Calculate actual total amount being settled
				const actualTotalAmount = projectBreakdown.reduce((sum, p) => sum + p.billAmount, 0)

				// Prepare settlement data for the API
				const settlementData = {
					targetUserId: String(this.currentSettlementPerson.member.userid || this.currentSettlementPerson.member.id),
					targetUserName: this.currentSettlementPerson.member.name,
					currency: this.selectedCurrencyCode,
					totalAmount: actualTotalAmount,
					isPayment: this.settlementAmount > 0, // true if current user owes money
					projectBreakdown,
				}

				// Use the dedicated cross-project settlement API
				await createCrossProjectSettlement(settlementData)

				showSuccess(
					this.partialSettlementConfirmed
						? t('cospend', 'Custom settlement of {amount} created successfully', {
							amount: this.formatCurrency(actualTotalAmount, this.selectedCurrencyCode),
						})
						: this.isPartialSettlement
							? t('cospend', 'Partial settlement of {amount} created successfully', {
								amount: this.formatCurrency(actualTotalAmount, this.selectedCurrencyCode),
							})
							: t('cospend', 'Settlement created successfully'),
				)

				// Reset all settlement state after successful completion
				this.resetSettlementState()

				// Extract affected project IDs for balance updates
				const affectedProjectIds = projectBreakdown.map(project => project.projectId)

				// Emit event to refresh balances with affected project IDs
				this.$emit('settlement-created', affectedProjectIds)
				this.cancelSettlement()

			} catch (error) {
				console.error('Settlement error:', error)
				console.error('Error response:', error.response?.data)
				console.error('Error status:', error.response?.status)
				console.error('Error config:', error.config)
				showError(t('cospend', 'Failed to create settlement'))

				// Close confirmation dialog even on error
				this.showConfirmationDialog = false
			} finally {
				this.isCreatingSettlement = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.cross-project-settlement {
	--cp-positive-text: var(--color-text-success, var(--color-success));
	--cp-negative-text: var(--color-text-error, var(--color-error));
	--cp-warning-bg: rgba(var(--color-warning-rgb, 252, 176, 64), 0.14);
	--cp-warning-border: var(--color-warning, var(--color-primary-element));
	--cp-warning-text: var(--color-main-text);
	height: 100%;
	padding: 16px;

	@media (max-width: 768px) {
		padding: 12px;
		height: auto;
		min-height: 100vh;
	}

	.settlement-content {
		.settlement-header {
			margin-bottom: 16px;
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			gap: 12px;
			position: relative;
			padding-top: 0;

			/* Desktop: Center the header content and keep close button positioned absolute */
			@media (min-width: 769px) {
				justify-content: center;
				margin-bottom: 16px;

				.header-content {
					text-align: center;
				}
			}

			.header-content {
				flex: 1;

				.settlement-title {
					margin: 0 0 8px 0;
					font-size: 1.5em;
					font-weight: 600;
					color: var(--color-main-text);
				}

				.settlement-subtitle {
					margin: 0;
					color: var(--color-text-maxcontrast);
					font-size: 0.9em;
				}
			}

			/* Hide close button on mobile - back button is used instead */
			.desktop-only-close {
				position: absolute !important;
				right: 50px !important; /* Moved further left to avoid minimize button */
				top: -8px !important;
				z-index: 100 !important;

				@media (max-width: 768px) {
					display: none !important;
				}
			}

			@media (max-width: 768px) {
				flex-direction: column;
				gap: 8px;
				margin-bottom: 16px;
				justify-content: center;

				.header-content {
					text-align: center;
					width: 100%;

					.settlement-title {
						margin: 0 0 8px 0;
						font-size: 1.3em;
						font-weight: 600;
						color: var(--color-main-text);
					}

					.settlement-subtitle {
						margin: 0;
						color: var(--color-text-maxcontrast);
						font-size: 0.85em;
					}
				}
			}

			h3 {
				margin: 0 0 4px 0;
				font-size: 18px;
				font-weight: 500;

				@media (max-width: 768px) {
					font-size: 16px;
				}
				}

			.settlement-subtitle {
				margin: 0;
				color: var(--color-text-maxcontrast);
				font-size: 14px;

				@media (max-width: 768px) {
					font-size: 13px;
				}
			}
		}

		/* Grid-based settlement configuration for better layout */
		.settlement-configuration {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
			align-items: end;
			margin-bottom: 16px;

			@media (max-width: 768px) {
				grid-template-columns: 1fr;
				gap: 12px;
			}

			.config-section {
				display: flex;
				flex-direction: column;
				gap: 8px;

				@media (max-width: 768px) {
					gap: 6px;
				}

				label {
					font-weight: 500;
					font-size: 14px;
					color: var(--color-main-text);

					@media (max-width: 768px) {
						font-size: 13px;
					}
				}
			}

		}

		/* Collapsed state for better UX when settlement is created */
		&.collapsed {
			.config-section {
				display: none;
			}
		}

		.collapsed-summary {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 12px 16px;
			background: var(--color-background-hover);
			border-radius: 6px;
			font-size: 14px;
			color: var(--color-text-maxcontrast);

			span {
				font-weight: 500;
			}

			.button-vue {
				font-size: 12px !important;
				padding: 4px 8px !important;
			}
		}

	/* Single configuration summary when collapsed */
	.configuration-summary {
		display: block;
		padding: 16px;
		background: var(--color-background-hover);
		border-radius: 8px;
		border: 1px solid var(--color-border);
		margin-bottom: 16px;

		.summary-content {
			flex: 1;

			h4 {
				margin: 0 0 8px 0;
				font-size: 16px;
				font-weight: 500;
				color: var(--color-main-text);
			}

			.summary-details {
				display: flex;
				flex-direction: column;
				gap: 4px;

				span {
					font-size: 14px;
					color: var(--color-text-maxcontrast);

					strong {
						color: var(--color-main-text);
						font-weight: 500;
					}

					/* Payment direction coloring in summary */
					.payment-amount {
						color: var(--cp-negative-text);
						font-weight: 500;
					}

					.receive-amount {
						color: var(--cp-positive-text);
						font-weight: 500;
					}

					.payment-direction {
						font-family: var(--font-face);
						font-size: 14px;
						font-weight: 600;
						font-variant-numeric: tabular-nums;
						font-style: italic;
						opacity: 0.8;
						margin-left: 4px;
					}
				}

				.project-summary {
					margin-top: 8px;
					padding-top: 8px;
					border-top: 1px solid var(--color-border);

					> span {
						font-size: 13px;
						line-height: 1.4;
						color: var(--color-text-lighter);
						display: block;
						margin-bottom: 8px;
					}

					.project-list-summary {
						display: flex;
						flex-direction: column;
						gap: 6px;

						.project-item-summary {
							display: flex;
							flex-direction: column;
							padding: 8px;
							background: var(--color-background-dark);
							border-radius: 4px;
							border-left: 3px solid var(--color-primary-element);
							gap: 6px;

							.project-item-header {
								display: flex;
								justify-content: space-between;
								align-items: center;

								.project-name {
									font-size: 13px;
									font-weight: 500;
									color: var(--color-main-text);
									flex: 1;
								}

								.project-amount {
									font-family: var(--font-face);
									font-size: 14px;
									font-weight: 600;
									font-variant-numeric: tabular-nums;
									margin-left: 8px;

									&.payment-amount {
										color: var(--cp-negative-text);
									}

									&.receive-amount {
										color: var(--cp-positive-text);
									}
								}
							}

							.project-optional-fields {
								display: flex;
								flex-direction: column;
								gap: 4px;
								padding-left: 8px;
								font-size: 12px;
								color: var(--color-text-maxcontrast);

								.optional-field-display {
									display: flex;
									align-items: center;
									gap: 6px;

									.comment-text {
										font-style: italic;
									}
								}
							}

							.payment-direction {
								font-family: var(--font-face);
								font-size: 14px;
								font-weight: 600;
								font-variant-numeric: tabular-nums;
								font-style: italic;
								opacity: 0.8;

								&.payment-label {
									color: var(--cp-negative-text);
								}

								&.receive-label {
									color: var(--cp-positive-text);
								}
							}
						}
					}
				}
			}
		}
	}

.currency-selection {
margin-bottom: 20px;

label {
display: block;
margin-bottom: 4px;
font-weight: 500;
font-size: 14px;
}
}

	/* Flex-based amount section for tighter spacing */
	.amount-section {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 12px;

		@media (max-width: 768px) {
			flex-wrap: wrap;
			gap: 4px;
			margin-bottom: 8px;
		}

		/* Direct label styling */
		> label {
			font-weight: 500;
			font-size: 14px;
			color: var(--color-main-text);
			white-space: nowrap;
			margin-right: 4px;

			@media (max-width: 768px) {
				font-size: 13px;
				flex-basis: 100%;
				margin-bottom: 4px;
				margin-right: 0;
			}

			/* Color labels based on payment direction - consistent with cumulative balances */
			&.payment-label {
				color: var(--cp-negative-text); /* Red for payments you need to make */
			}

			&.receive-label {
				color: var(--cp-positive-text); /* Green for money you will receive */
			}
		}

		.config-section {
			display: flex;
			align-items: center;

			.amount-input {
				width: 120px;
				padding: 8px 12px;
				border: 2px solid var(--color-border-dark);
				border-radius: var(--border-radius);
				font-size: 14px;
				font-weight: 500;
				transition: border-color 0.2s ease, box-shadow 0.2s ease;

				@media (max-width: 768px) {
					width: 100px;
				}

				&:focus {
					border-color: var(--color-primary-element);
					outline: none;
					box-shadow: 0 0 0 3px rgba(var(--color-primary-element-rgb), 0.2);
				}

				&:disabled {
					background-color: var(--color-background-dark);
					color: var(--color-text-maxcontrast);
					border-color: var(--color-border);
					cursor: not-allowed;
				}
			}

			.currency-label {
				font-size: 14px;
				color: var(--color-text-maxcontrast);
				font-weight: 600;
				white-space: nowrap;
			}

			/* Ensure buttons are properly aligned with input fields */
			button {
				align-self: center;
			}

			.amount-display {
				.amount-value {
					font-size: 18px;
					font-weight: 600;

					/* Consistent coloring with cumulative balances */
					&.payment {
						color: var(--cp-negative-text); /* Red for payments you need to make */
					}

					&.receive {
						color: var(--cp-positive-text); /* Green for money you will receive */
					}

					/* Fallback for any amounts without specific classes */
					&:not(.payment):not(.receive) {
						color: var(--color-main-text);
					}
				}
			}

			.button-vue {
				font-weight: 500;
				white-space: nowrap;

				@media (max-width: 768px) {
					grid-column: 1 / -1;
					width: 100%;
				}
			}
		}

		/* Collapsed state for better UX when settlement is created */
		&.collapsed {
			display: none;
		}

		.collapsed-summary {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 12px 16px;
			background: var(--color-background-hover);
			border-radius: 6px;
			font-size: 14px;
			color: var(--color-text-maxcontrast);

			span {
				font-weight: 500;
			}

			.button-vue {
				font-size: 12px !important;
				padding: 4px 8px !important;
			}
		}
	}

	.confirm-actions {
		margin-top: 8px;
		display: flex;
		gap: 8px;

		@media (max-width: 768px) {
			flex-direction: column;
			gap: 12px; /* Increased from 6px for better mobile spacing */

			button {
				width: 100%;
				min-height: 44px; /* Ensure proper touch target */
			}
		}
	}
}

.settlement-actions {
	margin-top: 12px;
	margin-bottom: 12px;

	.simple-confirmation {
		display: flex;
		justify-content: center;
		gap: 16px;
		margin-top: 12px;

		@media (max-width: 768px) {
			flex-direction: column;
			gap: 12px;
		}

		.button-vue {
			min-width: 140px;
			font-weight: 600;

			@media (max-width: 768px) {
				width: 100%;
			}
		}
	}
}

/* PROJECT BREAKDOWN PREVIEW - ENHANCED STYLING */
.project-breakdown-preview {
	margin-top: 12px;
	margin-bottom: 12px;
	padding: 16px;
	background: var(--color-background-hover);
	border-radius: 8px;
	border: 1px solid var(--color-border);
	transition: all 0.2s ease;

	&:hover {
		border-color: var(--color-primary);
		box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
	}

	h4 {
		margin: 0 0 16px 0;
		font-size: 16px;
		font-weight: 500;
		color: var(--color-main-text);
	}

	.project-list {
		display: flex;
		flex-direction: column;
		gap: 16px;

		.project-preview {
			border: 1px solid var(--color-border);
			border-radius: 8px;
			padding: 12px 16px;
			background: var(--color-main-background);
			transition: all 0.2s ease;

			&:hover {
				border-color: var(--color-primary);
				box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
			}

			@media (max-width: 768px) {
				padding: 12px;
			}

			.project-header {
				margin-bottom: 12px;
				padding-bottom: 8px;
				border-bottom: 1px solid var(--color-border);
				display: flex;
				justify-content: space-between;
				align-items: center;

				.project-name {
					font-weight: 600;
					font-size: 16px; /* Increased from 15px */
					color: var(--color-main-text);
					display: block;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
					flex: 1;
				}

				.debt-flow-header {
					display: flex;
					align-items: center;
					gap: 6px;
					font-size: 12px;
					white-space: nowrap;

					.current-debt-header {
						color: var(--color-text-lighter);
						font-weight: 500;

						&.payment-debt {
							color: var(--color-error-text);
						}

						&.receive-debt {
							color: var(--color-success-text);
						}
					}

					.arrow-header {
						color: var(--color-primary-element);
						font-weight: bold;
					}

					.remaining-debt-header {
						font-weight: 600;
						color: var(--color-main-text);

						&.settled {
							color: var(--color-primary-element);
							font-weight: 700;
						}

						&.reduced {
							font-weight: 500;

							&.payment-remaining {
								color: var(--cp-negative-text);
							}

							&.receive-remaining {
								color: var(--cp-positive-text);
							}
						}
					}
				}
			}

			.project-details {
				.amount-input-row {
					margin-bottom: 12px;

					/* NON-EDITABLE AMOUNT DISPLAY */
					.settlement-amount {
						display: inline-block;
						font-family: var(--font-face);
						font-size: 14px;
						font-weight: 600;
						font-variant-numeric: tabular-nums;
						padding: 8px 12px;
						border-radius: var(--border-radius);

						&.payment-amount {
							color: var(--cp-negative-text);
						}

						&.receive-amount {
							color: var(--cp-positive-text);
						}

						&:not(.payment-amount):not(.receive-amount) {
							color: var(--color-primary-element);
						}
					}

					/* EDITABLE PROJECT INPUT - PERFECT GRID ALIGNMENT */
					.project-input-container {
						display: grid;
						grid-template-columns: 80px 1fr;
						gap: 0px;
						align-items: center;

						@media (max-width: 768px) {
							grid-template-columns: 1fr;
							gap: 0px;
							text-align: left;
						}

						label {
							font-weight: 500;
							font-size: 14px;
							color: var(--color-main-text);
							margin: 0;
							text-align: left;

							&.payment-label {
								color: var(--cp-negative-text);
							}

							&.receive-label {
								color: var(--cp-positive-text);
							}
						}

						.input-and-debt-container {
							display: flex;
							align-items: center;
							gap: 8px;

							@media (max-width: 374px) {
								flex-direction: column;
								align-items: stretch;
								gap: 8px;
							}

							.overpayment-notice-inline {
								font-size: 11px;
								color: var(--color-warning);
								font-weight: 500;
								margin-left: 8px;
								white-space: nowrap;

								@media (max-width: 768px) {
									white-space: normal;
									font-size: 10px;
								}

								strong {
									color: var(--color-warning);
								}
							}
						}

						.project-input-controls {
							display: flex;
							align-items: center;
							gap: 8px;

							.project-amount-input {
								width: 120px;
								padding: 8px 12px;
								border: 2px solid var(--color-border-dark);
								border-radius: var(--border-radius);
								font-size: 14px;
								font-weight: 500;
								text-align: right;
								transition: border-color 0.2s ease, box-shadow 0.2s ease;

								@media (max-width: 320px) {
									width: 100%;
									max-width: 200px;
								}

								@media (min-width: 321px) and (max-width: 767px) {
									width: 100px;
									flex-shrink: 0;
								}

								&:focus {
									border-color: var(--color-primary-element);
									outline: none;
									box-shadow: 0 0 0 3px rgba(var(--color-primary-element-rgb), 0.2);
								}

								&:hover:not(:focus) {
									border-color: var(--color-border-maxcontrast);
								}

								&:invalid {
									border-color: var(--cp-negative-text);
									box-shadow: 0 0 0 2px rgba(var(--color-error-rgb), 0.2);
								}
							}
						}
					}

						.currency-label {
							font-size: 14px;
							color: var(--color-text-maxcontrast);
							font-weight: 600;
							text-align: left;
						}
					}

					/* DEBT IMPACT INFORMATION */
					.inline-debt-info {
						display: flex;
						align-items: center;
						gap: 8px;
						font-size: 12px;
						flex-shrink: 0;

						@media (max-width: 374px) {
							margin-top: 8px;
							justify-content: flex-start;
						}

						.current-debt {
							color: var(--color-text-lighter);
							font-weight: 500;

							&.payment-debt {
								color: var(--color-error-text);
							}

							&.receive-debt {
								color: var(--color-success-text);
							}
						}

						.arrow {
							color: var(--color-primary-element);
							font-weight: bold;
						}

						.remaining-debt {
							font-weight: 600;
							color: var(--color-main-text);

							&.settled {
								color: var(--color-primary-element);
								font-weight: 700;
							}

							&.reduced {
								font-weight: 500;

								&.payment-remaining {
									color: var(--cp-negative-text);
								}

								&.receive-remaining {
									color: var(--cp-positive-text);
								}
							}
						}
					}
				}

				/* DEBT IMPACT PREVIEW BOX */
				.project-impact {
					margin-top: 12px;
					padding: 12px;
					background: var(--color-background-hover);
					border-radius: var(--border-radius);
					border-left: 3px solid var(--color-primary-element);

					.impact-row {
						display: grid;
						grid-template-columns: 1fr 1fr;
						gap: 12px;
						align-items: center;

						.current-debt {
							font-size: 13px;
							color: var(--color-text-lighter);
							font-weight: 500;
						}

						.remaining-debt {
							font-size: 13px;
							font-weight: 600;
							text-align: right;

							&.settled {
								color: var(--color-primary-element);
							}

							&.reduced {
								color: var(--color-warning);
							}
						}
					}
				}

				.overpayment-notice {
					margin-top: 8px;
					padding: 8px 12px;
					background-color: var(--cp-warning-bg);
					border: 1px solid var(--cp-warning-border);
					border-radius: var(--border-radius);
					color: var(--cp-warning-text);
					font-size: 13px;
					font-weight: 500;
				}
			}

			// Project-specific optional fields
			.project-optional-fields {
				margin-top: 16px;
				padding-top: 16px;
				border-top: 1px solid var(--color-border);

				.optional-fields-toggle {
					width: 100%;
					margin-bottom: 12px;
					font-size: 13px;

					:deep(.button-vue__wrapper) {
						justify-content: flex-start;
					}
				}

				.optional-fields-content {
					display: flex;
					flex-direction: column;
					gap: 12px;
					padding: 12px;
					background: var(--color-background-hover);
					border-radius: var(--border-radius);

					.optional-fields-row {
						display: flex;
						gap: 12px;
						align-items: flex-end;

						@media (max-width: 768px) {
							flex-direction: column;
							align-items: stretch;
						}

						> .optional-field {
							flex: 1;
							min-width: 0;
						}
					}

					.optional-field {
						display: flex;
						flex-direction: column;
						gap: 6px;

						label {
							font-weight: 500;
							font-size: 13px;
							color: var(--color-main-text);
							display: flex;
							align-items: center;
							gap: 6px;
						}

						:deep(.vs__container),
						:deep(.vs__dropdown-toggle) {
							width: 100% !important;
						}

						:deep(.nc-datetime-picker),
						:deep(.nc-datetime-picker input) {
							width: 100% !important;
						}

						.comment-textarea {
							width: 100%;
							padding: 8px;
							border: 1px solid var(--color-border-dark);
							border-radius: var(--border-radius);
							font-family: var(--font-face);
							font-size: 14px;
							resize: vertical;

							&:focus {
								outline: 2px solid var(--color-primary);
								outline-offset: -2px;
							}
						}
					}
				}
			}
		}
	}

	.project-impact {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		margin-bottom: 4px;

		.impact-row {
			display: flex;
			flex-direction: column;
			gap: 2px;

			.remaining-debt {
				&.settled {
					color: var(--color-primary-element);
					font-weight: 500;
				}

				&.reduced {
					color: var(--color-warning);
				}
			}
		}
	}

	.overpayment-notice {
		background-color: var(--cp-warning-bg);
		border: 1px solid var(--cp-warning-border);
		border-radius: var(--border-radius);
		padding: 8px;
		font-size: 13px;
		margin-top: 4px;

		strong {
			color: var(--color-warning);
		}
	}

	.validation-section {
		margin-top: 0 !important;
		padding: 16px !important;
		border-top: 1px solid var(--color-border);
		background: var(--color-background-hover);
		border-radius: 8px;
		border: 1px solid var(--color-border);
		transition: all 0.2s ease;

		@media (max-width: 768px) {
			margin-top: 24px !important;
		}

		&:hover {
			border-color: var(--color-primary);
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
		}

		.validation-header {
			text-align: center !important;
			margin-bottom: 8px !important;

			h3 {
				margin: 0 !important;
				font-size: 1.3em !important;
				font-weight: 600 !important;
				color: var(--color-main-text) !important;
			}
		}

		.amount-summary {
			display: flex;
			gap: 32px;
			justify-content: center;

			@media (max-width: 768px) {
				flex-direction: column;
				gap: 16px;
				text-align: center;
			}

			.summary-item {
				display: flex;
				flex-direction: column;
				gap: 6px;
				align-items: center;
				position: relative;
				transition: all 0.3s ease;

				@media (max-width: 768px) {
					align-items: center;
				}

				&:hover {
					transform: translateY(-2px);
				}

				.label {
					font-size: 13px;
					color: var(--color-text-maxcontrast);
					font-weight: 600;
					text-transform: uppercase;
					letter-spacing: 1px;
					margin-bottom: 4px;
				}

				.amount {
					font-family: var(--font-face);
					font-size: 14px;
					font-weight: 600;
					font-variant-numeric: tabular-nums;
					color: var(--color-main-text);
					position: relative;
					transition: all 0.3s ease;
					padding: 4px 6px;
					border-radius: 8px;

					&.valid {
						color: var(--cp-positive-text);
					}

					&.invalid {
						color: var(--color-warning);
					}

					&.zero {
						color: var(--color-text-maxcontrast);
					}

					&.payment-amount {
						color: var(--cp-negative-text);
					}

					&.receive-amount {
						color: var(--cp-positive-text);
					}
				}
			}
		}
	}

	.settlement-placeholder {
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--color-text-maxcontrast);
		font-style: italic;
	}
}

.sr-only {
position: absolute;
width: 1px;
height: 1px;
padding: 0;
margin: -1px;
overflow: hidden;
clip-path: inset(50%);
white-space: nowrap;
border: 0;
}

.settlement-confirmation {
padding: 12px;

.confirmation-header {
text-align: center;
margin-bottom: 24px;

h2 {
margin: 0 0 8px 0;
font-size: 20px;
font-weight: 500;
}

p {
margin: 0;
color: var(--color-text-maxcontrast);
font-size: 14px;
}
}

.confirmation-content {
display: flex;
flex-direction: column;
gap: 20px;

.settlement-summary {
h3 {
margin: 0 0 12px 0;
font-size: 16px;
font-weight: 500;
}

.summary-row {
display: flex;
justify-content: space-between;
margin-bottom: 8px;
font-size: 14px;

.label {
color: var(--color-text-maxcontrast);
}

.value {
font-weight: 500;

&.total-amount {
color: var(--color-primary);
font-size: 16px;
}
}
}
}

.project-breakdown {
h4 {
margin: 0 0 12px 0;
font-size: 16px;
font-weight: 500;
}

.breakdown-list {
.breakdown-item {
display: flex;
justify-content: space-between;
margin-bottom: 8px;
padding: 8px;
background-color: var(--color-background-hover);
border-radius: var(--border-radius);
font-size: 14px;

.project-name {
font-weight: 500;
}

.project-amount {
font-family: var(--font-face);
font-size: 14px;
font-weight: 600;
font-variant-numeric: tabular-nums;
color: var(--color-primary);
}
}
}

.debt-change {
margin-top: 12px;
padding: 12px;
background-color: var(--color-background-dark);
border-radius: var(--border-radius);

h5 {
margin: 0 0 8px 0;
font-size: 14px;
font-weight: 500;
}

.change-list {
.change-item {
display: flex;
justify-content: space-between;
margin-bottom: 4px;
font-size: 13px;

.project-name {
color: var(--color-text-maxcontrast);
}

.debt-change {
display: flex;
align-items: center;
gap: 8px;

.current {
color: var(--color-text-maxcontrast);
}

.arrow {
color: var(--color-text-maxcontrast);
font-weight: bold;
}

.remaining {
				font-weight: 600;
				color: var(--color-warning);

				&.settled {
					color: var(--color-primary-element);
				}
}
}
}
}
}
}
}

.confirmation-actions {
display: flex;
justify-content: flex-end;
gap: 12px;
padding-top: 16px;
border-top: 1px solid var(--color-border);
}

/* PROJECT INPUT ALIGNMENT - CRITICAL GRID-BASED IMPROVEMENTS */
.project-breakdown-preview {
	.project-list {
		.project-preview {
			.project-details {
				.amount-input-row {
						/* EDITABLE PROJECT INPUT - ENHANCED GRID ALIGNMENT */
					.project-input-container {
						display: grid;
						grid-template-columns: 120px 140px 50px;
						gap: 12px;
						align-items: center;

						label {
							min-width: 80px;
							font-weight: 500;
							font-size: 14px;
							color: var(--color-main-text);
							margin-bottom: 0;
							text-align: left;

							&.payment-label {
								color: var(--cp-negative-text);
							}

							&.receive-label {
								color: var(--cp-positive-text);
							}
						}

						.project-input-controls {
							display: flex;
							align-items: center;
							gap: 8px;
							flex: 1;

							.project-amount-input {
								min-width: 100px;
								max-width: 120px;
								padding: 8px 12px;
								border: 2px solid var(--color-border-dark);
								border-radius: var(--border-radius);
								font-size: 14px;
								font-weight: 500;
								text-align: right;
								transition: all 0.2s ease;

								&:focus {
									border-color: var(--color-primary-element);
									outline: none;
									box-shadow: 0 0 0 2px rgba(var(--color-primary-element-rgb), 0.2);
								}

								&:invalid {
									border-color: var(--cp-negative-text);
									box-shadow: 0 0 0 2px rgba(var(--color-error-rgb), 0.2);
								}
							}

								.currency-label {
								font-size: 14px;
								color: var(--color-text-maxcontrast);
								font-weight: 600;
								min-width: 40px;
							}
						}
					}

					/* Enhanced project impact styling */
					.project-impact {
						margin-top: 8px;
						padding: 8px 12px;
						background-color: var(--color-background-hover);
						border-radius: var(--border-radius-small);
						border-left: 3px solid var(--color-primary-light);

						.impact-row {
							display: flex;
							flex-direction: column;
							gap: 4px;

							.current-debt, .remaining-debt {
								font-size: 13px;
								&.settled {
									color: var(--color-primary-element);
									font-weight: 600;
								}

								&.reduced {
									color: var(--color-warning);
									font-weight: 500;
								}
							}
						}
					}

					/* Enhanced overpayment notice */
					.overpayment-notice {
						margin-top: 8px;
						padding: 8px 12px;
						background-color: var(--cp-warning-bg);
						border: 1px solid var(--cp-warning-border);
						border-radius: var(--border-radius);
						font-size: 13px;
						animation: slideIn 0.3s ease;

						strong {
							color: var(--color-warning);
						}
					}
				}
			}
		}
	}

	/* SIMPLE CONFIRMATION SECTION - Restored Original Style */
	.inline-confirmation {
		margin-top: 24px;
		padding: 20px;
		background: var(--color-background-hover);
		border-radius: 8px;
		border: 1px solid var(--color-border);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);

		.confirmation-header {
			text-align: center;
			margin-bottom: 20px;

			h3 {
				margin: 0 0 8px 0;
				font-size: 18px;
				font-weight: 500;
				color: var(--color-main-text);
			}

			p {
				margin: 0;
				color: var(--color-text-maxcontrast);
				font-size: 14px;
			}
		}

		.confirmation-content {
			display: flex;
			flex-direction: column;
			gap: 20px;

			.settlement-summary {
				background: var(--color-background-dark);
				padding: 16px;
				border-radius: 6px;
				border: 1px solid var(--color-border);

				h4 {
					margin: 0 0 12px 0;
					font-size: 16px;
					font-weight: 500;
					color: var(--color-main-text);
					text-align: center;
				}

				.summary-row {
					display: flex;
					justify-content: space-between;
					margin-bottom: 8px;
					padding: 4px 0;

					.label {
						font-weight: 500;
						color: var(--color-text-maxcontrast);
						font-size: 14px;
					}

					.value {
						font-weight: 600;
						color: var(--color-main-text);
						font-size: 14px;

						&.total-amount {
							font-size: 16px;
							color: var(--color-primary-element);
						}
					}
				}
			}

			.project-breakdown-preview {
				background: var(--color-background-hover);
				padding: 20px;
				border-radius: 8px;
				border-left: 4px solid var(--color-success);

				h4 {
					margin: 0 0 16px 0;
					font-size: 1.1em;
					font-weight: 600;
					color: var(--color-main-text);
				}

				.breakdown-list {
					display: flex;
					flex-direction: column;
					gap: 12px;

					.breakdown-item {
						padding: 12px;
						background: var(--color-background-dark);
						border-radius: 6px;
						border: 1px solid var(--color-border);

						.project-info {
							display: flex;
							justify-content: space-between;
							align-items: center;
							margin-bottom: 8px;

							.project-name {
								font-weight: 600;
								color: var(--color-main-text);
							}

							.bill-amount {
								font-weight: 700;
								color: var(--color-primary-element);
								font-size: 1.1em;
							}
						}

						.debt-change {
							display: flex;
							align-items: center;
							gap: 8px;
							font-size: 0.9em;

							.current {
								color: var(--color-text-maxcontrast);
								font-weight: 500;
							}

							.arrow {
								color: var(--color-primary-element);
								font-weight: bold;
							}

							.remaining {
								font-weight: 600;
								color: var(--color-main-text);

								&.settled {
									color: var(--color-primary-element);
									font-weight: 700;
								}
							}
						}
					}
				}
			}
		}

		.confirmation-actions {
			display: flex;
			justify-content: center;
			gap: 16px;
			padding-top: 20px;
			border-top: 1px solid var(--color-border);

			.button-vue {
				min-width: 140px;
				font-weight: 600;
			}
		}
	}

	/* Enhanced settlement actions */
	.settlement-actions {
		.button-vue {
			font-weight: 500;
		}
	}

	/* Enhanced focus management */
	input:focus {
		outline: none;
		box-shadow: 0 0 0 2px var(--color-primary-light);
	}

	/* Smart auto-focus when partial settlement is selected */
	.partial-amount .amount-input:not(:disabled) {
		animation: focusHighlight 0.5s ease;
	}
	}
}

/* Enhanced animations */
@keyframes slideIn {
	from {
		opacity: 0;
		transform: translateX(-10px);
	}
	to {
		opacity: 1;
		transform: translateX(0);
	}
}

@keyframes pulse {
	0% { transform: scale(1); }
	50% { transform: scale(1.05); }
	100% { transform: scale(1); }
}

@keyframes focusHighlight {
	0% { box-shadow: 0 0 0 0 var(--color-primary-light); }
	50% { box-shadow: 0 0 0 4px var(--color-primary-light); }
	100% { box-shadow: 0 0 0 2px var(--color-primary-light); }
}

/* Animations */
@keyframes slideDown {
	0% {
		opacity: 0;
		transform: translateY(-30px);
		max-height: 0;
		padding-top: 0;
		padding-bottom: 0;
	}
	50% {
		opacity: 0.7;
		max-height: 400px;
	}
	100% {
		opacity: 1;
		transform: translateY(0);
		max-height: 800px;
		padding-top: 24px;
		padding-bottom: 24px;
	}
}
</style>
