<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<!--
  Renders a plugin's SVG icon using the same structure as the material design icons
  used by Cospend, so contexts that size those icons (action menus give them a whole
  clickable area, sidebar tabs keep them at 20px) treat plugin icons identically.
-->
<template>
	<span aria-hidden="true"
		role="img"
		class="material-design-icon plugin-icon"
		v-html="iconSvg" />
</template>

<script>
const FALLBACK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M13.5 5.5a2 2 0 1 1 4 0 2 2 0 0 1-4 0m-2.7 3.2 1.6.8-2 3.9a4 4 0 1 0 3.4 3.9h-2a2 2 0 1 1-3.9-.6l2.9-5.7c.3-.6 1-1 1.6-.7l3.4 1.7c.5.2.8.8.7 1.4l-.4 2.1h-1.7l.5-2.1-2.3-1.1-2.5 4.9a4 4 0 1 0 5 3.9h3.7l.6-3.2a3.8 3.8 0 0 0-2.1-4.2l-3.4-1.7.4-.9c.2-.4 0-.9-.4-1.1l-2.2-1.1a1 1 0 0 0-1.4.5l-.4 1a1 1 0 0 0-.5 1.4z"/></svg>'
const svgCache = {}

export default {
	name: 'PluginIcon',

	props: {
		src: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			iconSvg: FALLBACK_SVG,
		}
	},

	watch: {
		src: {
			immediate: true,
			handler(src) {
				this.loadSvg(src)
			},
		},
	},

	methods: {
		async loadSvg(src) {
			if (!src) {
				this.iconSvg = FALLBACK_SVG
				return
			}
			if (svgCache[src]) {
				this.iconSvg = svgCache[src]
				return
			}
			this.iconSvg = FALLBACK_SVG
			// inline SVG content and data URIs are used as-is (fetch would violate the CSP)
			if (src.startsWith('<svg')) {
				svgCache[src] = src
				this.iconSvg = src
				return
			}
			if (src.startsWith('data:')) {
				try {
					const content = src.includes('base64,')
						? atob(src.slice(src.indexOf('base64,') + 7))
						: decodeURIComponent(src.slice(src.indexOf(',') + 1))
					if (content.includes('<svg')) {
						svgCache[src] = content
						this.iconSvg = content
					}
				} catch (error) {
					console.warn('[cospend] Failed to decode plugin icon data URI', error)
				}
				return
			}
			try {
				const response = await fetch(src)
				const text = await response.text()
				if (text.includes('<svg')) {
					svgCache[src] = text
					this.iconSvg = text
				}
			} catch (error) {
				console.warn('[cospend] Failed to load plugin icon', src, error)
			}
		},
	},
}
</script>

<style scoped>
.plugin-icon {
	display: flex;
	align-self: center;
	justify-self: center;
	align-items: center;
	justify-content: center;
}

.plugin-icon :deep(svg) {
	width: 20px;
	height: 20px;
	fill: currentColor;
	vertical-align: middle;
}
</style>
