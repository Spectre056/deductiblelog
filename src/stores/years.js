import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { currentYear } from '../utils/date.js'

/**
 * Tax years to offer in every year selector: every year that has data, the
 * current year, and the user's default year from Settings. Replaces the
 * hard-coded "last three years" that hid older records from the UI.
 */
export const useYearsStore = defineStore('years', () => {
	const years       = ref([currentYear()])
	const defaultYear = ref(currentYear())
	const loaded      = ref(false)
	let inflight      = null

	async function ensure() {
		if (loaded.value) return
		if (!inflight) {
			inflight = axios.get(generateUrl('/apps/deductiblelog/api/years'))
				.then(({ data }) => {
					years.value       = data.data.years?.length ? data.data.years : [currentYear()]
					defaultYear.value = data.data.default_year || currentYear()
					loaded.value      = true
				})
				.catch(() => { /* fall back to the current year */ })
				.finally(() => { inflight = null })
		}
		await inflight
	}

	/** Make sure a year exists in the list (a record was just saved into it). */
	function include(year) {
		const y = Number(year)
		if (y && !years.value.includes(y)) {
			years.value = [...years.value, y].sort((a, b) => b - a)
		}
	}

	/** Options for a form: the known years plus whatever year the form currently holds. */
	function withYear(year) {
		const y = Number(year)
		return y && !years.value.includes(y) ? [...years.value, y].sort((a, b) => b - a) : years.value
	}

	function invalidate() {
		loaded.value = false
	}

	return { years, defaultYear, loaded, ensure, include, withYear, invalidate }
})
