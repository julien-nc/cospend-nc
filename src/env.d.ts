/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Type declarations for TypeScript (globals, Vite asset imports, and modules).
 */

/// <reference types="vite/client" />

import { translate } from '@nextcloud/l10n'
import type { TranslationOptions } from '@nextcloud/l10n'

interface Capabilities {
	cospend?: {
		version?: string
	}
	[key: string]: unknown
}

declare global {
	const t: typeof translate

	const OCA: {
		Cospend: {
			sharingToken: string
			actionIgnoreLists: string[]
			state: Record<string, unknown>
		}
	}

	const OC: {
		getCapabilities: () => Capabilities
	}
}

declare module '*.svg?raw' {
	const content: string
	export default content
}

declare module '@vue/runtime-core' {
	interface ComponentCustomProperties {
		t: (app: string, text: string, vars?: Record<string, string>, count?: number, options?: TranslationOptions) => string
	}
}

export {}
