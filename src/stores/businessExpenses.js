import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useBusinessExpensesStore = defineStore('businessExpenses', () => {
	const expenses   = ref([])
	const yearTotal  = ref('0.00')
	const activeYear = ref(new Date().getFullYear())
	const loading    = ref(false)
	const error      = ref(null)

	const totalFormatted = computed(() =>
		parseFloat(yearTotal.value).toLocaleString('en-US', { style: 'currency', currency: 'USD' }),
	)

	async function fetchYear(year) {
		loading.value    = true
		error.value      = null
		activeYear.value = year
		try {
			const { data } = await axios.get(
				generateUrl('/apps/deductiblelog/api/business-expenses'),
				{ params: { tax_year: year } },
			)
			expenses.value  = data.data
			yearTotal.value = data.total
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load business expenses'
		} finally {
			loading.value = false
		}
	}

	async function create(payload) {
		const { data } = await axios.post(
			generateUrl('/apps/deductiblelog/api/business-expenses'),
			payload,
		)
		if (data.data.tax_year === activeYear.value) {
			expenses.value.unshift(data.data)
			yearTotal.value = (parseFloat(yearTotal.value) + parseFloat(data.data.amount)).toFixed(2)
		}
		return data.data
	}

	async function update(id, payload) {
		const { data } = await axios.put(
			generateUrl(`/apps/deductiblelog/api/business-expenses/${id}`),
			payload,
		)
		await fetchYear(activeYear.value)
		return data.data
	}

	async function remove(id) {
		const expense = expenses.value.find(e => e.id === id)
		await axios.delete(generateUrl(`/apps/deductiblelog/api/business-expenses/${id}`))
		expenses.value = expenses.value.filter(e => e.id !== id)
		if (expense) {
			yearTotal.value = Math.max(0, parseFloat(yearTotal.value) - parseFloat(expense.amount)).toFixed(2)
		}
	}

	return { expenses, yearTotal, totalFormatted, activeYear, loading, error, fetchYear, create, update, remove }
})
