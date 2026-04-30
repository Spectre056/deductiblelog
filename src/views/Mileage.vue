<template>
	<div class="dl-view">
		<!-- Header -->
		<div class="dl-view-header">
			<div class="dl-view-title">
				<h2>Mileage</h2>
				<span v-if="!store.loading" class="dl-year-total">
					{{ store.totalFormatted }} deduction &middot; {{ store.milesFormatted }} in {{ store.activeYear }}
				</span>
			</div>
			<div class="dl-view-actions">
				<select v-model.number="selectedYear" class="dl-year-select" @change="store.fetchYear(selectedYear)">
					<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
				</select>
				<NcButton type="primary" @click="openAdd">
					<template #icon><PlusIcon :size="20" /></template>
					Log Trip
				</NcButton>
			</div>
		</div>

		<!-- Loading -->
		<NcLoadingIcon v-if="store.loading" />

		<!-- Empty state -->
		<NcEmptyContent
			v-else-if="store.logs.length === 0"
			name="No mileage logged"
			:description="`No mileage recorded for ${store.activeYear}.`"
		>
			<template #icon><CarIcon :size="20" /></template>
		</NcEmptyContent>

		<!-- Table -->
		<table v-else class="dl-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Purpose</th>
					<th>Member</th>
					<th>Description</th>
					<th class="dl-col-right">Miles</th>
					<th class="dl-col-right">Rate</th>
					<th class="dl-col-amount">Deduction</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="log in store.logs" :key="log.id">
					<td class="dl-col-date">{{ formatDate(log.date) }}</td>
					<td><span :class="`dl-badge dl-badge-${log.purpose_type}`">{{ purposeLabel(log.purpose_type) }}</span></td>
					<td>{{ memberName(log.family_member_id) }}</td>
					<td class="dl-col-desc">{{ log.description || '—' }}</td>
					<td class="dl-col-right">{{ parseFloat(log.miles).toLocaleString('en-US', { maximumFractionDigits: 1 }) }}</td>
					<td class="dl-col-right">{{ log.rate_cents }}¢</td>
					<td class="dl-col-amount">{{ formatAmount(log.deduction_amount) }}</td>
					<td class="dl-actions">
						<NcButton type="tertiary" @click="openEdit(log)" :aria-label="`Edit log`">
							<template #icon><PencilIcon :size="18" /></template>
						</NcButton>
						<NcButton type="tertiary" @click="confirmDelete(log)" :aria-label="`Delete log`">
							<template #icon><DeleteIcon :size="18" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr class="dl-total-row">
					<td colspan="4">Total</td>
					<td class="dl-col-right">{{ parseFloat(store.yearMiles).toLocaleString('en-US', { maximumFractionDigits: 1 }) }}</td>
					<td></td>
					<td class="dl-col-amount">{{ store.totalFormatted }}</td>
					<td></td>
				</tr>
			</tfoot>
		</table>

		<!-- Add / Edit dialog -->
		<NcDialog
			v-if="showDialog"
			:name="editTarget ? 'Edit Mileage Log' : 'Log Trip'"
			:open="showDialog"
			@update:open="showDialog = $event"
			@closing="resetForm"
		>
			<div class="dl-form">
				<!-- Purpose type -->
				<div class="dl-field-group">
					<label class="dl-label">Purpose *</label>
					<select v-model="form.purposeType" class="dl-select" @change="syncRate">
						<option value="charitable">Charitable</option>
						<option value="medical">Medical</option>
						<option value="business">Business</option>
					</select>
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
						<select v-model.number="form.taxYear" class="dl-year-select" @change="syncRate">
							<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
						</select>
					</div>
				</div>

				<!-- Family member -->
				<div class="dl-field-group">
					<label class="dl-label">Family Member</label>
					<NcSelect
						v-model="form.member"
						:options="memberOptions"
						label="label"
						track-by="id"
						placeholder="Optional — all members"
						:clearable="true"
					/>
				</div>

				<!-- Description -->
				<NcTextField
					v-model="form.description"
					label="Description"
					placeholder="e.g. Drove to food bank"
				/>

				<!-- Miles + Rate + Deduction -->
				<div class="dl-form-row">
					<div class="dl-field-group dl-field-grow">
						<NcTextField
							v-model="form.miles"
							label="Miles *"
							placeholder="0.0"
							type="number"
							:min="0"
							step="0.1"
							:error="!!formErrors.miles"
							:helper-text="formErrors.miles"
						/>
					</div>
					<div class="dl-field-group" style="min-width: 110px">
						<label class="dl-label">Rate (¢/mile)</label>
						<input
							v-model="form.rateCents"
							type="number"
							min="0"
							step="0.1"
							class="dl-date-input"
						/>
					</div>
					<div class="dl-field-group">
						<label class="dl-label">Deduction</label>
						<span class="dl-deduction-display">{{ deductionFormatted }}</span>
					</div>
				</div>

				<!-- Rate hint -->
				<p v-if="rateHint" class="dl-rate-hint">{{ rateHint }}</p>
				<p v-if="saveError" class="dl-error">{{ saveError }}</p>
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
			name="Delete Trip"
			:open="!!deleteTarget"
			@update:open="v => { if (!v) deleteTarget = null }"
		>
			<p>
				Delete the <strong>{{ purposeLabel(deleteTarget?.purpose_type) }}</strong> trip
				of <strong>{{ deleteTarget?.miles }} miles</strong>
				on <strong>{{ formatDate(deleteTarget?.date) }}</strong>
				(deduction: <strong>{{ formatAmount(deleteTarget?.deduction_amount) }}</strong>)?
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
import PlusIcon   from 'vue-material-design-icons/Plus.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CarIcon    from 'vue-material-design-icons/Car.vue'
import { useMileageStore }       from '../stores/mileage.js'
import { useFamilyMembersStore } from '../stores/familyMembers.js'

const CURRENT_YEAR   = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR]
const todayISO       = new Date().toISOString().split('T')[0]

const store       = useMileageStore()
const memberStore = useFamilyMembersStore()

onMounted(async () => {
	await Promise.all([
		store.fetchYear(CURRENT_YEAR),
		store.fetchRates(),
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

function purposeLabel(type) {
	return { charitable: 'Charitable', medical: 'Medical', business: 'Business' }[type] ?? type
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
const saveError    = ref('')

const emptyForm = () => ({
	purposeType: 'charitable',
	date:        todayISO,
	taxYear:     CURRENT_YEAR,
	member:      null,
	description: '',
	miles:       '',
	rateCents:   store.rateFor(CURRENT_YEAR, 'charitable'),
})

const form       = reactive(emptyForm())
const formErrors = reactive({})

const deductionFormatted = computed(() => {
	const miles = parseFloat(form.miles) || 0
	const rate  = parseFloat(form.rateCents) || 0
	return (miles * rate / 100).toLocaleString('en-US', { style: 'currency', currency: 'USD' })
})

const rateHint = computed(() => {
	const r = store.taxRates[form.taxYear]
	if (!r) return null
	return `${form.taxYear} IRS rates — Charitable: ${r.charitable}¢ · Medical: ${r.medical}¢ · Business: ${r.business}¢`
})

function syncYearFromDate() {
	if (form.date) {
		form.taxYear = parseInt(form.date.substring(0, 4), 10)
		syncRate()
	}
}

function syncRate() {
	form.rateCents = store.rateFor(form.taxYear, form.purposeType)
}

function openAdd() {
	Object.assign(form, emptyForm())
	form.rateCents   = store.rateFor(CURRENT_YEAR, 'charitable')
	editTarget.value = null
	saveError.value  = ''
	showDialog.value = true
}

function openEdit(log) {
	Object.assign(form, {
		purposeType: log.purpose_type,
		date:        log.date,
		taxYear:     log.tax_year,
		member:      log.family_member_id ? (memberOptions.value.find(m => m.id === log.family_member_id) ?? null) : null,
		description: log.description ?? '',
		miles:       log.miles,
		rateCents:   log.rate_cents,
	})
	editTarget.value = log
	saveError.value = ''
	showDialog.value = true
}

function resetForm() {
	Object.assign(form, emptyForm())
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	editTarget.value = null
	saveError.value = ''
}

async function save() {
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	saveError.value = ''

	if (!form.date)                                  formErrors.date  = 'Date is required'
	if (!form.miles || parseFloat(form.miles) <= 0)  formErrors.miles = 'Enter miles driven'
	if (Object.keys(formErrors).length) return

	saving.value = true
	try {
		const payload = {
			purpose_type:     form.purposeType,
			date:             form.date,
			tax_year:         form.taxYear,
			family_member_id: form.member?.id ?? null,
			description:      form.description.trim() || null,
			miles:            parseFloat(form.miles).toFixed(1),
			rate_cents:       parseFloat(form.rateCents || 0).toFixed(1),
		}

		if (editTarget.value) {
			await store.update(editTarget.value.id, payload)
		} else {
			await store.create(payload)
		}
		showDialog.value = false
	} catch (error) {
		saveError.value = error?.response?.data?.message ?? 'Unable to save this mileage entry.'
	} finally {
		saving.value = false
	}
}

function confirmDelete(log) {
	deleteTarget.value = log
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
	max-width: 960px;
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
	font-size: 1.05rem;
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
.dl-col-right  { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.dl-col-amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.dl-col-desc   { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.dl-total-row td {
	font-weight: 700;
	border-top: 2px solid var(--color-border-dark);
	border-bottom: none;
}

.dl-badge {
	display: inline-block;
	padding: 0.15rem 0.5rem;
	border-radius: 10px;
	font-size: 0.78rem;
	font-weight: 600;
}

.dl-badge-charitable { background: color-mix(in srgb, var(--color-success) 20%, transparent); color: var(--color-success); }
.dl-badge-medical    { background: color-mix(in srgb, var(--color-error) 15%, transparent); color: var(--color-error); }
.dl-badge-business   { background: color-mix(in srgb, var(--color-warning) 20%, transparent); color: var(--color-warning); }

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

.dl-error { font-size: 0.8rem; color: var(--color-error); }

.dl-deduction-display {
	font-size: 1.1rem;
	font-weight: 700;
	color: var(--color-success);
	padding-bottom: 4px;
	font-variant-numeric: tabular-nums;
}

.dl-rate-hint {
	font-size: 0.8rem;
	color: var(--color-text-lighter);
	margin: 0;
}
</style>
