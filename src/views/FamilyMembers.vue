<template>
	<div class="dl-view">
		<div class="dl-view-header">
			<h2>Family Members</h2>
			<NcButton type="primary" @click="openAdd">
				<template #icon><PlusIcon :size="20" /></template>
				Add Member
			</NcButton>
		</div>

		<NcLoadingIcon v-if="store.loading" />

		<NcEmptyContent
			v-else-if="!store.loading && store.members.length === 0"
			name="No family members yet"
			description="Add your household members to tag expenses and donations."
		>
			<template #icon><AccountMultipleIcon :size="20" /></template>
		</NcEmptyContent>

		<table v-else class="dl-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Relationship</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="member in store.members" :key="member.id">
					<td>{{ member.name }}</td>
					<td>{{ RELATIONSHIP_LABELS[member.relationship] ?? member.relationship }}</td>
					<td class="dl-actions">
						<NcButton type="tertiary" @click="openEdit(member)" :aria-label="`Edit ${member.name}`">
							<template #icon><PencilIcon :size="18" /></template>
						</NcButton>
						<NcButton type="tertiary" @click="confirmDelete(member)" :aria-label="`Delete ${member.name}`">
							<template #icon><DeleteIcon :size="18" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Add / Edit dialog -->
		<NcDialog
			v-if="showDialog"
			:name="editTarget ? 'Edit Family Member' : 'Add Family Member'"
			:open="showDialog"
			@update:open="showDialog = $event"
			@closing="resetForm"
		>
			<div class="dl-form">
				<NcTextField
					v-model="form.name"
					label="Name *"
					placeholder="e.g. Michael"
					:error="!!formErrors.name"
					:helper-text="formErrors.name"
				/>
				<div class="dl-field-group">
					<label class="dl-label">Relationship *</label>
					<NcSelect
						v-model="form.relationship"
						:options="RELATIONSHIP_OPTIONS"
						label="label"
						track-by="value"
						:placeholder="'Select relationship'"
					/>
					<span v-if="formErrors.relationship" class="dl-error">{{ formErrors.relationship }}</span>
				</div>
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
			name="Remove Family Member"
			:open="!!deleteTarget"
			@update:open="v => { if (!v) deleteTarget = null }"
		>
			<p>Remove <strong>{{ deleteTarget?.name }}</strong>? This cannot be undone.</p>
			<template #actions>
				<NcButton @click="deleteTarget = null">Cancel</NcButton>
				<NcButton type="error" :disabled="deleting" @click="doDelete">
					{{ deleting ? 'Removing…' : 'Remove' }}
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
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import PlusIcon            from 'vue-material-design-icons/Plus.vue'
import PencilIcon          from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon          from 'vue-material-design-icons/Delete.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import { useFamilyMembersStore } from '../stores/familyMembers.js'

const RELATIONSHIP_OPTIONS = [
	{ value: 'self',      label: 'Self (primary taxpayer)' },
	{ value: 'spouse',    label: 'Spouse' },
	{ value: 'dependent', label: 'Dependent' },
]

const RELATIONSHIP_LABELS = {
	self:      'Self',
	spouse:    'Spouse',
	dependent: 'Dependent',
}

const store = useFamilyMembersStore()

onMounted(() => store.fetchAll())

const showDialog   = ref(false)
const editTarget   = ref(null)
const deleteTarget = ref(null)
const saving       = ref(false)
const deleting     = ref(false)

const emptyForm = () => ({ name: '', relationship: null })
const form       = reactive(emptyForm())
const formErrors = reactive({})

function openAdd() {
	Object.assign(form, emptyForm())
	editTarget.value = null
	showDialog.value = true
}

function openEdit(member) {
	Object.assign(form, {
		name:         member.name,
		relationship: RELATIONSHIP_OPTIONS.find(o => o.value === member.relationship) ?? null,
	})
	editTarget.value = member
	showDialog.value = true
}

function resetForm() {
	Object.assign(form, emptyForm())
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	editTarget.value = null
}

async function save() {
	Object.keys(formErrors).forEach(k => delete formErrors[k])

	if (!form.name.trim())    { formErrors.name = 'Name is required' }
	if (!form.relationship)   { formErrors.relationship = 'Relationship is required' }
	if (Object.keys(formErrors).length) return

	saving.value = true
	try {
		const payload = {
			name:         form.name.trim(),
			relationship: form.relationship.value,
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

function confirmDelete(member) {
	deleteTarget.value = member
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
	max-width: 700px;
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

.dl-field-group {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.dl-label {
	font-size: 0.875rem;
	font-weight: 500;
	color: var(--color-text-light);
}

.dl-error {
	font-size: 0.8rem;
	color: var(--color-error);
}
</style>
