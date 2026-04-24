import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useCharitiesStore = defineStore('charities', () => {
	const charities = ref([])
	const loading   = ref(false)
	const error     = ref(null)

	async function fetchAll() {
		loading.value = true
		error.value   = null
		try {
			const { data } = await axios.get(generateUrl('/apps/deductiblelog/api/charities'))
			charities.value = data.data
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load charities'
		} finally {
			loading.value = false
		}
	}

	async function create(payload) {
		const { data } = await axios.post(generateUrl('/apps/deductiblelog/api/charities'), payload)
		charities.value.push(data.data)
		charities.value.sort((a, b) => a.name.localeCompare(b.name))
		return data.data
	}

	async function update(id, payload) {
		const { data } = await axios.put(generateUrl(`/apps/deductiblelog/api/charities/${id}`), payload)
		const idx = charities.value.findIndex(c => c.id === id)
		if (idx !== -1) charities.value[idx] = data.data
		charities.value.sort((a, b) => a.name.localeCompare(b.name))
		return data.data
	}

	async function remove(id) {
		await axios.delete(generateUrl(`/apps/deductiblelog/api/charities/${id}`))
		charities.value = charities.value.filter(c => c.id !== id)
	}

	return { charities, loading, error, fetchAll, create, update, remove }
})
