import { createAppConfig } from '@nextcloud/vite-config'
import { fileURLToPath } from 'url'

const shimPath = fileURLToPath(new URL('./src/vue-shim.mjs', import.meta.url))

export default createAppConfig({
	main: 'src/main.js',
}, {
	config: {
		resolve: {
			alias: {
				// @nextcloud/vue pre-built chunks use Vue 2-style default import.
				// This shim adds a synthetic default to the Vue 3 namespace.
				vue: shimPath,
			},
		},
		build: {
			sourcemap: true,
		},
	},
})
