// Provides a synthetic default export plus vue-demi polyfills (set, del,
// isVue2, isVue3, install) for @nextcloud/vue pre-built chunks that were
// compiled against vue-demi but have their imports collapsed to "vue".
// The absolute path to vue.esm-bundler bypasses this alias to avoid circular imports.
export * from '../node_modules/vue/dist/vue.esm-bundler.js'
import * as _Vue from '../node_modules/vue/dist/vue.esm-bundler.js'
export default _Vue

// vue-demi Vue 3 polyfills expected by some @nextcloud/vue pre-built chunks
export const isVue2 = false
export const isVue3 = true
export const Vue2 = undefined
export function install() {}

export function set(target, key, val) {
	if (Array.isArray(target)) {
		target.length = Math.max(target.length, key)
		target.splice(key, 1, val)
		return val
	}
	target[key] = val
	return val
}

export function del(target, key) {
	if (Array.isArray(target)) {
		target.splice(key, 1)
		return
	}
	delete target[key]
}
