<template>
	<div class="dl-view">
		<div class="dl-view-header">
			<h2>Charities</h2>
			<NcButton type="primary" @click="openAdd">
				<template #icon><PlusIcon :size="20" /></template>
				Add Charity
			</NcButton>
		</div>

		<NcLoadingIcon v-if="store.loading" />

		<NcEmptyContent
			v-else-if="!store.loading && store.charities.length === 0"
			name="No charities yet"
			description="Add a charity to get started tracking donations."
		>
			<template #icon><AccountGroupIcon :size="20" /></template>
		</NcEmptyContent>

		<table v-else class="dl-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>EIN</th>
					<th>City, State</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="charity in store.charities" :key="charity.id">
					<td>{{ charity.name }}</td>
					<td>{{ charity.ein || '—' }}</td>
					<td>{{ formatLocation(charity) }}</td>
					<td class="dl-actions">
						<NcButton type="tertiary" @click="openEdit(charity)" :aria-label="`Edit ${charity.name}`">
							<template #icon><PencilIcon :size="18" /></template>
						</NcButton>
						<NcButton type="tertiary" @click="confirmDelete(charity)" :aria-label="`Delete ${charity.name}`">
							<template #icon><DeleteIcon :size="18" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Add / Edit dialog -->
		<NcDialog
			v-if="showDialog"
			:name="editTarget ? 'Edit Charity' : 'Add Charity'"
			:open="showDialog"
			@update:open="showDialog = $event"
			@closing="resetForm"
		>
			<div class="dl-form">
				<NcTextField
					v-model="form.name"
					label="Name *"
					placeholder="Salvation Army"
					:error="!!formErrors.name"
					:helper-text="formErrors.name"
				/>
				<NcTextField
					v-model="form.ein"
					label="EIN"
					placeholder="12-3456789"
				/>
				<NcTextField
					v-model="form.address"
					label="Street Address"
					placeholder="123 Main St"
				/>
				<div class="dl-form-row">
					<NcTextField v-model="form.city"  label="City"  placeholder="Charleston" />
					<NcTextField v-model="form.state" label="State" placeholder="SC" style="max-width: 80px" />
					<NcTextField v-model="form.zip"   label="ZIP"   placeholder="29401" style="max-width: 100px" />
				</div>
				<NcTextField
					v-model="form.notes"
					label="Notes"
					placeholder="Optional notes"
				/>
			</div>
			<template #actions>
				<NcButton @click="showDialog = false">Cancel</NcButton>
				<NcButton type="primary" :disabled="saving" @click="save">
					{{ saving ? 'Saving…' : 'Save' }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- Delete confirmation -->
		<NcDialog
			v-if="deleteTarget"
			name="Delete Charity"
			:open="!!deleteTarget"
			@update:open="v => { if (!v) deleteTarget = null }"
		>
			<p>Delete <strong>{{ deleteTarget?.name }}</strong>? This cannot be undone.</p>
			<template #actions>
				<NcButton @click="deleteTarget = null">Cancel</NcButton>
				<NcButton type="error" :disabled="deleting" @click="doDelete">
					{{ deleting ? 'Deleting…' : 'Delete' }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
	NcButton,
	NcDialog,
	NcEmptyContent,
	NcLoadingIcon,
	NcTextField,
} from '@nextcloud/vue'
import PlusIcon         from 'vue-material-design-icons/Plus.vue'
import PencilIcon       from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon       from 'vue-material-design-icons/Delete.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import { useCharitiesStore } from '../stores/charities.js'

const store = useCharitiesStore()

onMounted(() => store.fetchAll())

const showDialog  = ref(false)
const editTarget  = ref(null)
const deleteTarget = ref(null)
const saving      = ref(false)
const deleting    = ref(false)

const emptyForm = () => ({ name: '', ein: '', address: '', city: '', state: '', zip: '', notes: '' })
const form       = reactive(emptyForm())
const formErrors = reactive({})

function formatLocation(charity) {
	const parts = [charity.city, charity.state].filter(Boolean)
	return parts.length ? parts.join(', ') : '—'
}

function openAdd() {
	Object.assign(form, emptyForm())
	editTarget.value = null
	showDialog.value = true
}

function openEdit(charity) {
	Object.assign(form, {
		name:    charity.name,
		ein:     charity.ein    ?? '',
		address: charity.address ?? '',
		city:    charity.city   ?? '',
		state:   charity.state  ?? '',
		zip:     charity.zip    ?? '',
		notes:   charity.notes  ?? '',
	})
	editTarget.value = charity
	showDialog.value = true
}

function resetForm() {
	Object.assign(form, emptyForm())
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	editTarget.value = null
}

async function save() {
	Object.keys(formErrors).forEach(k => delete formErrors[k])

	if (!form.name.trim()) {
		formErrors.name = 'Name is required'
		return
	}

	saving.value = true
	try {
		const payload = {
			name:    form.name.trim(),
			ein:     form.ein.trim()     || null,
			address: form.address.trim() || null,
			city:    form.city.trim()    || null,
			state:   form.state.trim()   || null,
			zip:     form.zip.trim()     || null,
			notes:   form.notes.trim()   || null,
		}

		if (editTarget.value) {
			await store.update(editTarget.value.id, payload)
		} else {
			await store.create(payload)
		}
		showDialog.value = false
	} finally {
		saving.value = false
	}
}

function confirmDelete(charity) {
	deleteTarget.value = charity
}

async function doDelete() {
	deleting.value = true
	try {
		await store.remove(deleteTarget.value.id)
		deleteTarget.value = null
	} finally {
		deleting.value = false
	}
}
</script>

<style scoped>
.dl-view {
	padding: 2rem;
	max-width: 900px;
}

.dl-view-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 1.5rem;
}

.dl-view-header h2 {
	margin: 0;
}

.dl-table {
	width: 100%;
	border-collapse: collapse;
}

.dl-table th,
.dl-table td {
	text-align: left;
	padding: 0.5rem 0.75rem;
	border-bottom: 1px solid var(--color-border);
}

.dl-table th {
	color: var(--color-text-lighter);
	font-weight: 600;
	font-size: 0.85rem;
}

.dl-actions {
	display: flex;
	gap: 0.25rem;
	justify-content: flex-end;
}

.dl-form {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	padding: 0.5rem 0;
}

.dl-form-row {
	display: flex;
	gap: 0.5rem;
}
</style>
