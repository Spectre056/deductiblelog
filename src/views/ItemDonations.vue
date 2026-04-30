<template>
	<div class="dl-view">
		<!-- Header -->
		<div class="dl-view-header">
			<div class="dl-view-title">
				<h2>Item Donations</h2>
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
			name="No item donations yet"
			:description="`No item donations recorded for ${store.activeYear}.`"
		>
			<template #icon><GiftIcon :size="20" /></template>
		</NcEmptyContent>

		<!-- Donations table -->
		<table v-else class="dl-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Charity</th>
					<th class="dl-col-center">Items</th>
					<th class="dl-col-amount">Total Value</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="d in store.donations" :key="d.id">
					<td class="dl-col-date">{{ formatDate(d.date) }}</td>
					<td>{{ charityName(d.charity_id) }}</td>
					<td class="dl-col-center">{{ d.lines?.length ?? 0 }}</td>
					<td class="dl-col-amount">{{ formatAmount(d.total_value) }}</td>
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
			:name="editTarget ? 'Edit Item Donation' : 'Add Item Donation'"
			:open="showDialog"
			size="large"
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

				<!-- Notes -->
				<NcTextField v-model="form.notes" label="Notes" placeholder="Optional notes" />

				<!-- Line items -->
				<div class="dl-lines-section">
					<div class="dl-lines-header">
						<span class="dl-label">Donated Items *</span>
						<NcButton type="secondary" @click="addLine">
							<template #icon><PlusIcon :size="16" /></template>
							Add Item
						</NcButton>
					</div>

					<span v-if="formErrors.lines" class="dl-error">{{ formErrors.lines }}</span>
					<p v-if="itemCategoriesError" class="dl-error">{{ itemCategoriesError }}</p>
					<p v-else-if="!itemCategoriesLoading && itemCategoryOptions.length === 0" class="dl-fmv-empty">
						The FMV item catalog is empty, so item names must be typed manually for now.
					</p>

					<div v-for="(line, idx) in formLines" :key="idx" class="dl-line-card">
						<div class="dl-line-top">
							<div class="dl-field-group dl-field-grow">
								<NcSelect
									v-model="line.itemOption"
									:options="itemCategoryOptions"
									:loading="itemCategoriesLoading"
									:taggable="true"
									:create-option="tag => ({ id: null, label: tag, min_value: '0.00', max_value: '0.00', unit: 'each' })"
									label="label"
									placeholder="Search or type item name…"
									@update:model-value="onItemSelect(line)"
								/>
							</div>
							<NcButton type="tertiary" @click="removeLine(idx)" :aria-label="`Remove item`">
								<template #icon><DeleteIcon :size="16" /></template>
							</NcButton>
						</div>
						<div class="dl-line-details">
							<div class="dl-field-group">
								<label class="dl-label-sm">Condition</label>
								<select v-model="line.condition" class="dl-select-sm" @change="applyFmv(line)">
									<option value="poor">Poor</option>
									<option value="good">Good</option>
									<option value="excellent">Excellent</option>
								</select>
							</div>
							<div class="dl-field-group">
								<label class="dl-label-sm">Qty</label>
								<input
									v-model.number="line.quantity"
									type="number"
									min="1"
									step="1"
									class="dl-input-sm dl-input-qty"
									@input="calcLineTotal(line)"
								/>
							</div>
							<div class="dl-field-group">
								<label class="dl-label-sm">Unit $</label>
								<input
									v-model="line.unitValue"
									type="number"
									min="0"
									step="0.01"
									class="dl-input-sm dl-input-money"
									@input="calcLineTotal(line)"
								/>
							</div>
							<div class="dl-field-group">
								<label class="dl-label-sm">Total</label>
								<span class="dl-line-total">{{ formatAmount(line.totalValue) }}</span>
							</div>
						</div>
						<div v-if="line.itemOption?.id" class="dl-fmv-hint">
							FMV range: {{ formatAmount(line.itemOption.min_value) }} – {{ formatAmount(line.itemOption.max_value) }}
							({{ line.itemOption.unit }})
						</div>
					</div>

					<div v-if="formLines.length > 0" class="dl-lines-total">
						<span>Donation Total:</span>
						<strong>{{ formTotalFormatted }}</strong>
					</div>
				</div>

				<!-- Receipts (edit mode only) -->
				<div v-if="editTarget" class="dl-receipts-section">
					<div class="dl-lines-header">
						<span class="dl-label">Receipts</span>
						<NcButton type="secondary" :disabled="uploadingReceipt" @click="triggerFileInput">
							<template #icon><PaperclipIcon :size="16" /></template>
							{{ uploadingReceipt ? 'Uploading…' : 'Attach File' }}
						</NcButton>
					</div>
					<input
						ref="fileInput"
						type="file"
						accept="image/*,.pdf"
						style="display: none"
						@change="uploadReceipt"
					/>
					<NcLoadingIcon v-if="receiptsLoading" :size="24" />
					<p v-else-if="receipts.length === 0" class="dl-receipts-empty">No receipts attached.</p>
					<ul v-else class="dl-receipts-list">
						<li v-for="r in receipts" :key="r.id" class="dl-receipt-item">
							<a :href="receiptDownloadUrl(r.id)" target="_blank" rel="noopener">
								<PaperclipIcon :size="14" />
								{{ r.original_filename }}
							</a>
							<NcButton type="tertiary" @click="deleteReceipt(r.id)" :aria-label="`Remove receipt`">
								<template #icon><DeleteIcon :size="14" /></template>
							</NcButton>
						</li>
					</ul>
				</div>
			</div>

			<template #actions>
				<NcButton @click="showDialog = false">Cancel</NcButton>
				<NcButton type="primary" :disabled="saving || formLines.length === 0" @click="save">
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
				Delete the item donation to
				<strong>{{ charityName(deleteTarget?.charity_id) }}</strong>
				on <strong>{{ formatDate(deleteTarget?.date) }}</strong>
				valued at <strong>{{ formatAmount(deleteTarget?.total_value) }}</strong>?
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
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import {
	NcButton,
	NcDialog,
	NcEmptyContent,
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import PlusIcon      from 'vue-material-design-icons/Plus.vue'
import PencilIcon    from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon    from 'vue-material-design-icons/Delete.vue'
import GiftIcon      from 'vue-material-design-icons/Gift.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import { useItemDonationsStore } from '../stores/itemDonations.js'
import { useCharitiesStore }     from '../stores/charities.js'

const CURRENT_YEAR   = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR]
const todayISO       = new Date().toISOString().split('T')[0]

const store        = useItemDonationsStore()
const charityStore = useCharitiesStore()
const itemCategoryOptions = ref([])
const itemCategoriesLoading = ref(false)
const itemCategoriesError = ref('')

onMounted(async () => {
	await Promise.all([
		store.fetchYear(CURRENT_YEAR),
		charityStore.charities.length === 0 ? charityStore.fetchAll() : Promise.resolve(),
		loadItemCategories(),
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
	charity: null,
	date:    todayISO,
	taxYear: CURRENT_YEAR,
	notes:   '',
})

const form       = reactive(emptyForm())
const formErrors = reactive({})
const formLines  = ref([])

const formTotalFormatted = computed(() => {
	const total = formLines.value.reduce((sum, l) => sum + parseFloat(l.totalValue || 0), 0)
	return total.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
})

function newLine() {
	return {
		itemOption: null,
		condition:  'good',
		quantity:   1,
		unitValue:  '',
		totalValue: '0.00',
	}
}

function addLine() {
	formLines.value.push(newLine())
}

function removeLine(idx) {
	formLines.value.splice(idx, 1)
}

function syncYearFromDate() {
	if (form.date) form.taxYear = parseInt(form.date.substring(0, 4), 10)
}

async function loadItemCategories() {
	if (itemCategoryOptions.value.length > 0 || itemCategoriesLoading.value) return

	itemCategoriesLoading.value = true
	itemCategoriesError.value = ''

	try {
		const { data } = await axios.get(generateUrl('/apps/deductiblelog/api/item-categories'))
		itemCategoryOptions.value = (data.data ?? []).map(category => ({
			id:        category.id,
			label:     category.name,
			min_value: category.min_value,
			max_value: category.max_value,
			unit:      category.unit,
		}))
	} catch (error) {
		itemCategoriesError.value = error?.response?.data?.message ?? 'Unable to load the FMV item catalog.'
		itemCategoryOptions.value = []
	} finally {
		itemCategoriesLoading.value = false
	}
}

function onItemSelect(line) {
	applyFmv(line)
}

function applyFmv(line) {
	const opt = line.itemOption
	if (!opt?.id) return
	const min = parseFloat(opt.min_value || 0)
	const max = parseFloat(opt.max_value || 0)
	if (line.condition === 'poor')           line.unitValue = min.toFixed(2)
	else if (line.condition === 'excellent') line.unitValue = max.toFixed(2)
	else                                     line.unitValue = ((min + max) / 2).toFixed(2)
	calcLineTotal(line)
}

function calcLineTotal(line) {
	const qty  = Math.max(1, parseInt(line.quantity, 10) || 1)
	const unit = parseFloat(line.unitValue) || 0
	line.totalValue = (qty * unit).toFixed(2)
}

// ── Dialog open/close ────────────────────────────────────────────────────────

function openAdd() {
	Object.assign(form, emptyForm())
	formLines.value = [newLine()]
	editTarget.value = null
	showDialog.value = true
}

function openEdit(donation) {
	Object.assign(form, {
		charity: charityOptions.value.find(c => c.id === donation.charity_id) ?? null,
		date:    donation.date,
		taxYear: donation.tax_year,
		notes:   donation.notes ?? '',
	})
	formLines.value = (donation.lines ?? []).map(l => ({
		itemOption: l.item_category_id
			? (itemCategoryOptions.value.find(option => option.id === l.item_category_id)
				?? { id: l.item_category_id, label: l.description, min_value: '0.00', max_value: '0.00', unit: 'each' })
			: { id: null, label: l.description, min_value: '0.00', max_value: '0.00', unit: 'each' },
		condition:  l.condition,
		quantity:   l.quantity,
		unitValue:  l.unit_value,
		totalValue: l.total_value,
	}))
	editTarget.value = donation
	showDialog.value = true
	loadReceipts(donation.id)
}

function resetForm() {
	Object.assign(form, emptyForm())
	Object.keys(formErrors).forEach(k => delete formErrors[k])
	formLines.value  = []
	editTarget.value = null
	receipts.value   = []
}

async function save() {
	Object.keys(formErrors).forEach(k => delete formErrors[k])

	if (!form.charity) formErrors.charity = 'Select a charity'
	if (!form.date)    formErrors.date    = 'Date is required'
	if (formLines.value.length === 0) formErrors.lines = 'Add at least one item'

	const invalidLine = formLines.value.find(l => !l.itemOption?.label || !l.unitValue || parseFloat(l.unitValue) <= 0)
	if (invalidLine) formErrors.lines = 'Each item needs a name and a value greater than $0'

	if (Object.keys(formErrors).length) return

	saving.value = true
	try {
		const payload = {
			charity_id: form.charity.id,
			date:       form.date,
			tax_year:   form.taxYear,
			notes:      form.notes.trim() || null,
			lines:      formLines.value.map(l => ({
				item_category_id: l.itemOption?.id ?? 0,
				description:      l.itemOption?.label ?? '',
				quantity:         Math.max(1, parseInt(l.quantity, 10) || 1),
				condition:        l.condition,
				unit_value:       parseFloat(l.unitValue || 0).toFixed(2),
			})),
		}

		let saved
		if (editTarget.value) {
			saved = await store.update(editTarget.value.id, payload)
		} else {
			saved = await store.create(payload)
			// Stay open in edit mode so user can attach receipts
			editTarget.value = saved
			formLines.value  = (saved.lines ?? []).map(l => ({
				itemOption: { id: l.item_category_id || null, label: l.description, min_value: '0.00', max_value: '0.00', unit: 'each' },
				condition:  l.condition,
				quantity:   l.quantity,
				unitValue:  l.unit_value,
				totalValue: l.total_value,
			}))
			return
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

// ── Receipts ─────────────────────────────────────────────────────────────────

const receipts        = ref([])
const receiptsLoading = ref(false)
const uploadingReceipt = ref(false)
const fileInput       = ref(null)

async function loadReceipts(donationId) {
	receiptsLoading.value = true
	try {
		const { data } = await axios.get(
			generateUrl('/apps/deductiblelog/api/receipts'),
			{ params: { entity_type: 'item_donation', entity_id: donationId } },
		)
		receipts.value = data.data ?? []
	} catch {
		receipts.value = []
	} finally {
		receiptsLoading.value = false
	}
}

function triggerFileInput() {
	fileInput.value?.click()
}

async function uploadReceipt(event) {
	const file = event.target.files?.[0]
	if (!file || !editTarget.value) return

	uploadingReceipt.value = true
	try {
		const fd = new FormData()
		fd.append('file', file)
		fd.append('entity_type', 'item_donation')
		fd.append('entity_id', editTarget.value.id)
		fd.append('tax_year', editTarget.value.tax_year)

		const { data } = await axios.post(
			generateUrl('/apps/deductiblelog/api/receipts'),
			fd,
		)
		receipts.value.push(data.data)
	} finally {
		uploadingReceipt.value = false
		event.target.value = ''
	}
}

async function deleteReceipt(id) {
	await axios.delete(generateUrl(`/apps/deductiblelog/api/receipts/${id}`))
	receipts.value = receipts.value.filter(r => r.id !== id)
}

function receiptDownloadUrl(id) {
	return generateUrl(`/apps/deductiblelog/api/receipts/${id}`)
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
.dl-col-center { text-align: center; }
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

/* Form */
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

.dl-label-sm {
	font-size: 0.78rem;
	font-weight: 500;
	color: var(--color-text-lighter);
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

/* Line items */
.dl-lines-section {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	border-top: 1px solid var(--color-border);
	padding-top: 0.75rem;
}

.dl-lines-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.dl-line-card {
	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.625rem 0.75rem;
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
}

.dl-line-top {
	display: flex;
	gap: 0.5rem;
	align-items: flex-start;
}

.dl-line-details {
	display: flex;
	gap: 0.75rem;
	align-items: flex-end;
	flex-wrap: wrap;
}

.dl-select-sm {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0.25rem 0.4rem;
	font-size: 0.85rem;
	height: 32px;
}

.dl-input-sm {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0.25rem 0.4rem;
	font-size: 0.85rem;
	height: 32px;
}

.dl-input-qty   { width: 60px; }
.dl-input-money { width: 80px; }

.dl-line-total {
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	font-size: 0.9rem;
	padding-bottom: 4px;
}

.dl-fmv-hint {
	font-size: 0.75rem;
	color: var(--color-text-lighter);
}

.dl-fmv-empty {
	margin: 0;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
}

.dl-lines-total {
	display: flex;
	justify-content: flex-end;
	gap: 0.75rem;
	font-size: 1rem;
	padding: 0.25rem 0;
	border-top: 1px solid var(--color-border);
}

/* Receipts */
.dl-receipts-section {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	border-top: 1px solid var(--color-border);
	padding-top: 0.75rem;
}

.dl-receipts-empty {
	font-size: 0.875rem;
	color: var(--color-text-lighter);
	margin: 0;
}

.dl-receipts-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.dl-receipt-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	font-size: 0.875rem;
	padding: 0.25rem 0;
}

.dl-receipt-item a {
	display: flex;
	align-items: center;
	gap: 0.3rem;
	color: var(--color-primary-element);
	text-decoration: none;
}

.dl-receipt-item a:hover {
	text-decoration: underline;
}
</style>
