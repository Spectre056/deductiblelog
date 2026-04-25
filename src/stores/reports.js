import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useReportsStore = defineStore('reports', () => {
	const summary    = ref(null)
	const activeYear = ref(new Date().getFullYear())
	const loading    = ref(false)
	const error      = ref(null)

	async function fetchSummary(year) {
		loading.value    = true
		error.value      = null
		activeYear.value = year
		try {
			const { data } = await axios.get(
				generateUrl('/apps/deductiblelog/api/reports/summary'),
				{ params: { tax_year: year } },
			)
			summary.value = data.data
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load summary'
		} finally {
			loading.value = false
		}
	}

	function openHtml(year) {
		const url = generateUrl('/apps/deductiblelog/api/reports/html') + '?tax_year=' + year
		window.open(url, '_blank')
	}

	function downloadCsv(year) {
		const url = generateUrl('/apps/deductiblelog/api/reports/csv') + '?tax_year=' + year
		window.open(url, '_self')
	}

	function downloadTxf(year) {
		const url = generateUrl('/apps/deductiblelog/api/reports/txf') + '?tax_year=' + year
		window.open(url, '_self')
	}

	return { summary, activeYear, loading, error, fetchSummary, openHtml, downloadCsv, downloadTxf }
})
