import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useFamilyMembersStore = defineStore('familyMembers', () => {
	const members = ref([])
	const loading = ref(false)
	const error   = ref(null)

	async function fetchAll() {
		loading.value = true
		error.value   = null
		try {
			const { data } = await axios.get(generateUrl('/apps/deductiblelog/api/family-members'))
			members.value = data.data
		} catch (e) {
			error.value = e?.response?.data?.message ?? 'Failed to load family members'
		} finally {
			loading.value = false
		}
	}

	async function create(payload) {
		const { data } = await axios.post(generateUrl('/apps/deductiblelog/api/family-members'), payload)
		members.value.push(data.data)
		return data.data
	}

	async function update(id, payload) {
		const { data } = await axios.put(generateUrl(`/apps/deductiblelog/api/family-members/${id}`), payload)
		const idx = members.value.findIndex(m => m.id === id)
		if (idx !== -1) members.value[idx] = data.data
		return data.data
	}

	async function remove(id) {
		await axios.delete(generateUrl(`/apps/deductiblelog/api/family-members/${id}`))
		members.value = members.value.filter(m => m.id !== id)
	}

	return { members, loading, error, fetchAll, create, update, remove }
})
