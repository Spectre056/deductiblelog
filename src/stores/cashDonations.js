import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useCashDonationsStore = defineStore('cashDonations', () => {
	const donations  = ref([])
	const yearTotal  = ref('0.00')
	const activeYear = ref(new Date().getFullYear())
	const loading    = ref(false)
	const error      = ref(null)

	const totalFormatted = computed(() =>
		parseFloat(yearTotal.value).toLocaleString('en-US', { style: 'currency', currency: 'USD' }),
	)

	async function fetchYear(year) {
		loading.value = true
		error.value   = null
		activeYear.value = year
		try {
			const { data } = await axios.get(
				generateUrl('/apps/deductiblelog/api/cash-donations'),
				{ params: { tax_year: year } },
			)
			donations.value = data.data
			yearTotal.value = data.total
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load donations'
		} finally {
			loading.value = false
		}
	}

	async function create(payload) {
		const { data } = await axios.post(
			generateUrl('/apps/deductiblelog/api/cash-donations'),
			payload,
		)
		if (data.data.tax_year === activeYear.value) {
			donations.value.unshift(data.data)
			yearTotal.value = (parseFloat(yearTotal.value) + parseFloat(data.data.amount)).toFixed(2)
		}
		return data.data
	}

	async function update(id, payload) {
		const { data } = await axios.put(
			generateUrl(`/apps/deductiblelog/api/cash-donations/${id}`),
			payload,
		)
		// Refetch the year to get accurate total (year may have changed)
		await fetchYear(activeYear.value)
		return data.data
	}

	async function remove(id) {
		const donation = donations.value.find(d => d.id === id)
		await axios.delete(generateUrl(`/apps/deductiblelog/api/cash-donations/${id}`))
		donations.value = donations.value.filter(d => d.id !== id)
		if (donation) {
			yearTotal.value = Math.max(
				0,
				parseFloat(yearTotal.value) - parseFloat(donation.amount),
			).toFixed(2)
		}
	}

	return { donations, yearTotal, totalFormatted, activeYear, loading, error, fetchYear, create, update, remove }
})
