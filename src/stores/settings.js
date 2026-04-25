import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useSettingsStore = defineStore('settings', () => {
	const settings        = ref({})
	const loading         = ref(false)
	const error           = ref(null)
	const checkResult     = ref(null)  // { updates_available: [...] } or null
	const checkError      = ref(null)
	const checking        = ref(false)
	const applying        = ref(false)

	async function fetchSettings() {
		loading.value = true
		error.value   = null
		try {
			const { data } = await axios.get(generateUrl('/apps/deductiblelog/api/settings'))
			settings.value = data.settings
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load settings'
		} finally {
			loading.value = false
		}
	}

	async function saveSettings(payload) {
		const { data } = await axios.put(generateUrl('/apps/deductiblelog/api/settings'), payload)
		settings.value = data.settings
		return data.settings
	}

	async function checkForUpdates() {
		checking.value  = true
		checkError.value = null
		checkResult.value = null
		try {
			const { data } = await axios.post(generateUrl('/apps/deductiblelog/api/settings/check-updates'))
			checkResult.value = data
			// refresh last_update_check
			await fetchSettings()
		} catch (e) {
			checkError.value = e?.response?.data?.message ?? 'Failed to check for updates'
		} finally {
			checking.value = false
		}
	}

	async function applyUpdates(updates) {
		applying.value = true
		try {
			await axios.post(generateUrl('/apps/deductiblelog/api/settings/apply-updates'), { updates })
			checkResult.value = null
		} finally {
			applying.value = false
		}
	}

	const isMandoTheme = computed(() => settings.value.mando_theme === '1')

	return {
		settings,
		loading,
		error,
		checkResult,
		checkError,
		checking,
		applying,
		isMandoTheme,
		fetchSettings,
		saveSettings,
		checkForUpdates,
		applyUpdates,
	}
})
