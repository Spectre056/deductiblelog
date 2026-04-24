<template>
	<div class="dl-view">
		<!-- Header -->
		<div class="dl-view-header">
			<div class="dl-view-title">
				<h2>Medical Expenses</h2>
				<span v-if="!store.loading" class="dl-year-total">
					{{ store.totalFormatted }} in {{ store.activeYear }}
				</span>
			</div>
			<div class="dl-view-actions">
				<select v-model.number="selectedYear" class="dl-year-select" @change="store.fetchYear(selectedYear)">
					<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
				</select>
				<NcButton type="primary" @click="openAdd">
					<template #icon><PlusIcon :size="20" /></template>
					Add Expense
				</NcButton>
			</div>
		</div>

		<!-- Loading -->
		<NcLoadingIcon v-if="store.loading" />

		<!-- Empty state -->
		<NcEmptyContent
			v-else-if="store.expenses.length === 0"
			name="No medical expenses"
			:description="`No medical expenses recorded for ${store.activeYear}.`"
		>
			<template #icon><HeartPulseIcon :size="20" /></template>
		</NcEmptyContent>

		<!-- Table -->
		<table v-else class="dl-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Member</th>
					<th>Provider</th>
					<th>Category</th>
					<th class="dl-col-amount">Amount</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="e in store.expenses" :key="e.id">
					<td class="dl-col-date">{{ formatDate(e.date) }}</td>
					<td>{{ memberName(e.family_member_id) }}</td>
					<td>{{ e.provider || '—' }}</td>
					<td>{{ e.category || '—' }}</td>
					<td class="dl-col-amount">{{ formatAmount(e.amount) }}</td>
					<td class="dl-actions">
						<NcButton type="tertiary" @click="openEdit(e)" :aria-label="`Edit expense`">
							<template #icon><PencilIcon :size="18" /></template>
						</NcButton>
						<NcButton type="tertiary" @click="confirmDelete(e)" :aria-label="`Delete expense`">
							<template #icon><DeleteIcon :size="18" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr class="dl-total-row">
					<td colspan="4">Total</td>
					<td class="dl-col-amount">{{ store.totalFormatted }}</td>
					<td></td>
				</tr>
			</tfoot>
		</table>

		<!-- Add / Edit dialog -->
		<NcDialog
			v-if="showDialog"
			:name="editTarget ? 'Edit Medical Expense' : 'Add Medical Expense'"
			:open="showDialog"
			@update:open="showDialog = $event"
			@closing="resetForm"
		>
			<div class="dl-form">
				<!-- Family member -->
				<div class="dl-field-group">
					<label class="dl-label">Family Member *</label>
					<NcSelect
						v-model="form.member"
						:options="memberOptions"
						label="label"
						track-by="id"
						placeholder="Select family member…"
					/>
					<span v-if="formErrors.member" class="dl-error">{{ formErrors.member }}</span>
				</div>

				<!-- Date + Year -->
				<div class="dl-form-row">
					<div class="dl-field-group dl-field-grow">
						<label class="dl-label">Date *</label>
						<input
							v-model="form.date"
							type="date"
							class="dl-date-input"
							:max="todayISO"
							@change="syncYearFromDate"
						/>
						<span v-if="formErrors.date" class="dl-error">{{ formErrors.date }}</span>
					</div>
					<div class="dl-field-group" style="min-width: 90px">
						<label class="dl-label">Tax Year *</label>
						<select v-model.number="form.taxYear" class="dl-year-select">
							<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
						</select>
					</div>
				</div>

				<!-- Provider + Category -->
				<div class="dl-form-row">
					<div class="dl-field-group dl-field-grow">
						<NcTextField
							v-model="form.provider"
							label="Provider"
							placeholder="e.g. Dr. Smith, CVS Pharmacy"
						/>
					</div>
					<div class="dl-field-group dl-field-grow">
						<label class="dl-label">Category</label>
						<select v-model="form.category" class="dl-select">
							<option value="">— optional —</option>
							<option v-for="c in MEDICAL_CATEGORIES" :key="c" :value="c">{{ c }}</option>
						</select>
					</div>
				</div>

				<!-- Amount -->
				<NcTextField
					v-model="form.amount"
					label="Amount ($) *"
					placeholder="0.00"
					type="number"
					:min="0"
					step="0.01"
					:error="!!formErrors.amount"
					:helper-text="formErrors.amount"
				/>

				<!-- Notes -->
				<NcTextField v-model="form.notes" label="Notes" placeholder="Optional notes" />
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
			name="Delete Expense"
			:open="!!deleteTarget"
			@update:open="v => { if (!v) deleteTarget = null }"
		>
			<p>
				Delete the <strong>{{ formatAmount(deleteTarget?.amount) }}</strong>
				medical expense for <strong>{{ memberName(deleteTarget?.family_member_id) }}</strong>
				on <strong>{{ formatDate(deleteTarget?.date) }}</strong>?
				This cannot be undone.
			</p>
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
import { ref, reactive, computed, onMounted } from 'vue'
import {
	NcButton,
	NcDialog,
	NcEmptyContent,
	NcLoadingIcon,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import PlusIcon      from 'vue-material-design-icons/Plus.vue'
import PencilIcon    from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon    from 'vue-material-design-icons/Delete.vue'
import HeartPulseIcon from 'vue-material-design-icons/HeartPulse.vue'
import { useMedicalExpensesStore } from '../stores/medicalExpenses.js'
import { useFamilyMembersStore }   from '../stores/familyMembers.js'

const MEDICAL_CATEGORIES = [
	'Insurance Premium', 'Doctor', 'Dentist', 'Vision', 'Prescription',
	'Hospital', 'Lab / Imaging', 'Mental Health', 'Therapy', 'Other',
]

const CURRENT_YEAR   = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR]
const todayISO       = new Date().toISOString().split('T')[0]

const store       = useMedicalExpensesStore()
const memberStore = useFamilyMembersStore()

onMounted(async () => {
	await Promise.all([
		store.fetchYear(CURRENT_YEAR),
		memberStore.members.length === 0 ? memberStore.fetchAll() : Promise.resolve(),
	])
})

const selectedYear = ref(CURRENT_YEAR)

const memberOptions = computed(() =>
	memberStore.members.map(m => ({ id: m.id, label: m.name })),
)

function memberName(id) {
	if (!id) return '—'
	return memberStore.members.find(m => m.id === id)?.name ?? `#${id}`
}

function formatDate(iso) {
	if (!iso) return '—'
	const [y, m, d] = iso.split('-')
	return `${m}/${d}/${y}`
}

function formatAmount(val) {
	return parseFloat(val || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

// ── Form state ───────────────────────────────────────────────────────────────

const showDialog   = ref(false)
const editTarget   = ref(null)
const deleteTarget = ref(null)
const saving       = ref(false)
const deleting     = ref(false)

const emptyForm = () => ({
	member:   null,
	date:     todayISO,
	taxYear:  CURRENT_YEAR,
	provider: '',
	category: '',
	amount:   '',
	notes:    '',
})

const form       = reactive(emptyForm())
const formErrors = reactive({})

function syncYearFromDate() {
	if (form.date) form.taxYear = parseInt(form.date.substring(0, 4), 10)
}

function openAdd() {
	Object.assign(form, emptyForm())
	editTarget.value = null
	showDialog.value = true
}

function openEdit(expense) {
	Object.assign(form, {
		member:   memberOptions.value.find(m => m.id === expense.family_member_id) ?? null,
		date:     expense.date,
		taxYear:  expense.tax_year,
		provider: expense.provider ?? '',
		category: expense.category ?? '',
		amount:   expense.amount,
		notes:    expense.notes ?? '',
	})
	editTarget.value = expense
	showDialog.value = true
}

function resetForm() {
	Object.assign(form, emptyForm())
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	editTarget.value = null
}

async function save() {
	Object.keys(formErrors).forEach(k => delete formErrors[k])

	if (!form.member)                                   formErrors.member = 'Select a family member'
	if (!form.date)                                     formErrors.date   = 'Date is required'
	if (!form.amount || parseFloat(form.amount) <= 0)   formErrors.amount = 'Enter a valid amount'
	if (Object.keys(formErrors).length) return

	saving.value = true
	try {
		const payload = {
			family_member_id: form.member.id,
			date:             form.date,
			tax_year:         form.taxYear,
			provider:         form.provider.trim() || null,
			category:         form.category || null,
			amount:           parseFloat(form.amount).toFixed(2),
			notes:            form.notes.trim() || null,
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

function confirmDelete(expense) {
	deleteTarget.value = expense
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
	align-items: flex-start;
	justify-content: space-between;
	margin-bottom: 1.5rem;
	gap: 1rem;
	flex-wrap: wrap;
}

.dl-view-title h2 { margin: 0 0 0.2rem; }

.dl-year-total {
	font-size: 1.1rem;
	font-weight: 600;
	color: var(--color-success);
}

.dl-view-actions {
	display: flex;
	align-items: center;
	gap: 0.75rem;
}

.dl-year-select,
.dl-select {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0.375rem 0.5rem;
	font-size: 0.9rem;
	height: 36px;
}

.dl-table { width: 100%; border-collapse: collapse; }

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

.dl-col-date   { white-space: nowrap; }
.dl-col-amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

.dl-total-row td {
	font-weight: 700;
	border-top: 2px solid var(--color-border-dark);
	border-bottom: none;
}

.dl-actions { display: flex; gap: 0.25rem; justify-content: flex-end; }

.dl-form { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.5rem 0; }

.dl-form-row { display: flex; gap: 0.75rem; align-items: flex-end; }

.dl-field-grow { flex: 1; }

.dl-field-group { display: flex; flex-direction: column; gap: 0.25rem; }

.dl-label { font-size: 0.875rem; font-weight: 500; color: var(--color-text-light); }

.dl-date-input {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0.375rem 0.5rem;
	font-size: 0.9rem;
	height: 36px;
	width: 100%;
}

.dl-error { font-size: 0.8rem; color: var(--color-error); }
</style>
