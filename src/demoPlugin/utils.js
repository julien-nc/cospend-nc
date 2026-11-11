/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Format a balance/total like Cospend does: fixed number of decimals, using the
 * project precision (computed by Cospend to keep rounded balances summing to 0,
 * bounded by the user's max precision setting).
 *
 * @param {number|null|undefined} value the raw value
 * @param {number|null|undefined} precision the project precision, defaults to 2
 * @return {string} the formatted value
 */
export function formatBalance(value, precision) {
	return Number(value ?? 0).toFixed(precision ?? 2)
}

/**
 * Format a bill amount like Cospend's bill list does: always 2 decimals.
 *
 * @param {number|null|undefined} value the raw amount
 * @return {string} the formatted amount
 */
export function formatAmount(value) {
	return Number(value ?? 0).toFixed(2)
}
