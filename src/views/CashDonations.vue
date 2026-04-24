<template>
	<div class="dl-view">
		<!-- Header -->
		<div class="dl-view-header">
			<div class="dl-view-title">
				<h2>Cash Donations</h2>
				<span v-if="!store.loading" class="dl-year-total">
					{{ store.totalFormatted }} in {{ store.activeYear }}
				</span>
			</div>
			<div class="dl-view-actions">
				<select v-model.number="selectedYear" class="dl-year-select" @change="store.fetchYear(selectedYear)">
					<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
				</select>
				<NcButton type="primary" @click="openAdd" :disabled="charityStore.charities.length === 0">
					<template #icon><PlusIcon :size="20" /></template>
					Add Donation
				</NcButton>
			</div>
		</div>

		<!-- No charities prompt -->
		<NcNoteCard v-if="charityStore.charities.length === 0 && !charityStore.loading" type="info">
			Add at least one charity under <strong>Charities</strong> before logging a donation.
		</NcNoteCard>

		<!-- Loading -->
		<NcLoadingIcon v-else-if="store.loading" />

		<!-- Empty state -->
		<NcEmptyContent
			v-else-if="store.donations.length === 0"
			name="No cash donations yet"
			:description="`No cash donations recorded for ${store.activeYear}.`"
		>
			<template #icon><CurrencyUsdIcon :size="20" /></template>
		</NcEmptyContent>

		<!-- Donation table -->
		<table v-else class="dl-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Charity</th>
					<th>Payment</th>
					<th class="dl-col-amount">Amount</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="d in store.donations" :key="d.id">
					<td class="dl-col-date">{{ formatDate(d.date) }}</td>
					<td>{{ charityName(d.charity_id) }}</td>
					<td>{{ d.payment_method || '—' }}</td>
					<td class="dl-col-amount">{{ formatAmount(d.amount) }}</td>
					<td class="dl-actions">
						<NcButton type="tertiary" @click="openEdit(d)" :aria-label="`Edit donation`">
							<template #icon><PencilIcon :size="18" /></template>
						</NcButton>
						<NcButton type="tertiary" @click="confirmDelete(d)" :aria-label="`Delete donation`">
							<template #icon><DeleteIcon :size="18" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr class="dl-total-row">
					<td colspan="3">Total</td>
					<td class="dl-col-amount">{{ store.totalFormatted }}</td>
					<td></td>
				</tr>
			</tfoot>
		</table>

		<!-- Add / Edit dialog -->
		<NcDialog
			v-if="showDialog"
			:name="editTarget ? 'Edit Cash Donation' : 'Add Cash Donation'"
			:open="showDialog"
			@update:open="showDialog = $event"
			@closing="resetForm"
		>
			<div class="dl-form">
				<!-- Charity -->
				<div class="dl-field-group">
					<label class="dl-label">Charity *</label>
					<NcSelect
						v-model="form.charity"
						:options="charityOptions"
						label="label"
						track-by="id"
						placeholder="Select charity…"
					/>
					<span v-if="formErrors.charity" class="dl-error">{{ formErrors.charity }}</span>
				</div>

				<!-- Date + Year row -->
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

				<!-- Amount + Payment row -->
				<div class="dl-form-row">
					<div class="dl-field-group dl-field-grow">
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
					</div>
					<div class="dl-field-group dl-field-grow">
						<label class="dl-label">Payment Method</label>
						<select v-model="form.paymentMethod" class="dl-year-select">
							<option value="">— optional —</option>
							<option v-for="m in PAYMENT_METHODS" :key="m" :value="m">{{ m }}</option>
						</select>
					</div>
				</div>

				<!-- Notes -->
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
			name="Delete Donation"
			:open="!!deleteTarget"
			@update:open="v => { if (!v) deleteTarget = null }"
		>
			<p>
				Delete the <strong>{{ formatAmount(deleteTarget?.amount) }}</strong> donation
				to <strong>{{ charityName(deleteTarget?.charity_id) }}</strong>
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
	NcNoteCard,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import PlusIcon        from 'vue-material-design-icons/Plus.vue'
import PencilIcon      from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon      from 'vue-material-design-icons/Delete.vue'
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd.vue'
import { useCashDonationsStore } from '../stores/cashDonations.js'
import { useCharitiesStore }     from '../stores/charities.js'

const PAYMENT_METHODS = ['Cash', 'Check', 'Credit Card', 'Bank Transfer', 'Payroll Deduction', 'Other']

const CURRENT_YEAR  = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR]
const todayISO       = new Date().toISOString().split('T')[0]

const store        = useCashDonationsStore()
const charityStore = useCharitiesStore()

onMounted(async () => {
	await Promise.all([
		store.fetchYear(CURRENT_YEAR),
		charityStore.charities.length === 0 ? charityStore.fetchAll() : Promise.resolve(),
	])
})

const selectedYear = ref(CURRENT_YEAR)

const charityOptions = computed(() =>
	charityStore.charities.map(c => ({ id: c.id, label: c.name })),
)

function charityName(id) {
	return charityStore.charities.find(c => c.id === id)?.name ?? `Charity #${id}`
}

function formatDate(iso) {
	if (!iso) return '—'
	const [y, m, d] = iso.split('-')
	return `${m}/${d}/${y}`
}

function formatAmount(val) {
	return parseFloat(val || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

// ── Form state ──────────────────────────────────────────────────────────────

const showDialog   = ref(false)
const editTarget   = ref(null)
const deleteTarget = ref(null)
const saving       = ref(false)
const deleting     = ref(false)

const emptyForm = () => ({
	charity:       null,
	date:          todayISO,
	taxYear:       CURRENT_YEAR,
	amount:        '',
	paymentMethod: '',
	notes:         '',
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

function openEdit(donation) {
	Object.assign(form, {
		charity:       charityOptions.value.find(c => c.id === donation.charity_id) ?? null,
		date:          donation.date,
		taxYear:       donation.tax_year,
		amount:        donation.amount,
		paymentMethod: donation.payment_method ?? '',
		notes:         donation.notes ?? '',
	})
	editTarget.value = donation
	showDialog.value = true
}

function resetForm() {
	Object.assign(form, emptyForm())
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	editTarget.value = null
}

async function save() {
	Object.keys(formErrors).forEach(k => delete formErrors[k])

	if (!form.charity)            formErrors.charity = 'Select a charity'
	if (!form.date)               formErrors.date    = 'Date is required'
	if (!form.amount || parseFloat(form.amount) <= 0) formErrors.amount = 'Enter a valid amount'
	if (Object.keys(formErrors).length) return

	saving.value = true
	try {
		const payload = {
			charity_id:     form.charity.id,
			date:           form.date,
			tax_year:       form.taxYear,
			amount:         parseFloat(form.amount).toFixed(2),
			payment_method: form.paymentMethod || null,
			notes:          form.notes.trim() || null,
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

function confirmDelete(donation) {
	deleteTarget.value = donation
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

.dl-view-title h2 {
	margin: 0 0 0.2rem;
}

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

.dl-year-select {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0.375rem 0.5rem;
	font-size: 0.9rem;
	height: 36px;
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

.dl-col-date   { white-space: nowrap; }
.dl-col-amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

.dl-total-row td {
	font-weight: 700;
	border-top: 2px solid var(--color-border-dark);
	border-bottom: none;
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
	gap: 0.75rem;
	align-items: flex-end;
}

.dl-field-grow { flex: 1; }

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

.dl-error {
	font-size: 0.8rem;
	color: var(--color-error);
}
</style>
