import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useMileageStore = defineStore('mileage', () => {
	const logs        = ref([])
	const yearTotal   = ref('0.00')
	const yearMiles   = ref('0.0')
	const activeYear  = ref(new Date().getFullYear())
	const loading     = ref(false)
	const error       = ref(null)
	const taxRates    = ref({})

	const totalFormatted = computed(() =>
		parseFloat(yearTotal.value).toLocaleString('en-US', { style: 'currency', currency: 'USD' }),
	)

	const milesFormatted = computed(() =>
		parseFloat(yearMiles.value).toLocaleString('en-US', { maximumFractionDigits: 1 }) + ' mi',
	)

	async function fetchRates() {
		if (Object.keys(taxRates.value).length > 0) return
		try {
			const { data } = await axios.get(generateUrl('/apps/deductiblelog/api/mileage/rates'))
			taxRates.value = data.data
		} catch {
			// rates unavailable — form will show 0
		}
	}

	function rateFor(year, purposeType) {
		const row = taxRates.value[year]
		if (!row) return '0.0'
		return row[purposeType] ?? '0.0'
	}

	async function fetchYear(year) {
		loading.value    = true
		error.value      = null
		activeYear.value = year
		try {
			const { data } = await axios.get(
				generateUrl('/apps/deductiblelog/api/mileage'),
				{ params: { tax_year: year } },
			)
			logs.value      = data.data
			yearTotal.value = data.total
			yearMiles.value = data.miles
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load mileage logs'
		} finally {
			loading.value = false
		}
	}

	async function create(payload) {
		const { data } = await axios.post(
			generateUrl('/apps/deductiblelog/api/mileage'),
			payload,
		)
		if (Number(data.data.tax_year) === Number(activeYear.value)) {
			await fetchYear(activeYear.value)
		}
		return data.data
	}

	async function update(id, payload) {
		const { data } = await axios.put(
			generateUrl(`/apps/deductiblelog/api/mileage/${id}`),
			payload,
		)
		await fetchYear(activeYear.value)
		return data.data
	}

	async function remove(id) {
		const log = logs.value.find(l => l.id === id)
		await axios.delete(generateUrl(`/apps/deductiblelog/api/mileage/${id}`))
		logs.value = logs.value.filter(l => l.id !== id)
		if (log) {
			yearTotal.value = Math.max(0, parseFloat(yearTotal.value) - parseFloat(log.deduction_amount)).toFixed(2)
			yearMiles.value = Math.max(0, parseFloat(yearMiles.value) - parseFloat(log.miles)).toFixed(1)
		}
	}

	return { logs, yearTotal, yearMiles, totalFormatted, milesFormatted, activeYear, loading, error, taxRates, fetchRates, rateFor, fetchYear, create, update, remove }
})
