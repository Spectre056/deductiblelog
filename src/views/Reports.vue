<template>
	<div class="dl-view">
		<!-- Header -->
		<div class="dl-view-header">
			<div class="dl-view-title">
				<h2>Reports</h2>
				<span v-if="store.summary" class="dl-year-total">
					{{ grandTotal }} total deductions in {{ store.activeYear }}
				</span>
			</div>
			<div class="dl-view-actions">
				<select v-model.number="selectedYear" class="dl-year-select" @change="store.fetchSummary(selectedYear)">
					<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
				</select>
			</div>
		</div>

		<!-- Loading -->
		<NcLoadingIcon v-if="store.loading" />

		<!-- Error -->
		<NcNoteCard v-else-if="store.error" type="error">{{ store.error }}</NcNoteCard>

		<!-- Summary table -->
		<template v-else-if="store.summary">
			<table class="dl-table dl-summary-table">
				<thead>
					<tr><th>Category</th><th class="dl-col-amount">Amount</th><th class="dl-col-amount">% of Total</th></tr>
				</thead>
				<tbody>
					<tr>
						<td>Cash Donations</td>
						<td class="dl-col-amount">{{ fmt(store.summary.cash_donations) }}</td>
						<td class="dl-col-amount">{{ pct(store.summary.cash_donations) }}</td>
					</tr>
					<tr>
						<td>Item Donations</td>
						<td class="dl-col-amount">{{ fmt(store.summary.item_donations) }}</td>
						<td class="dl-col-amount">{{ pct(store.summary.item_donations) }}</td>
					</tr>
					<tr class="dl-subtotal-row">
						<td><em>Charitable Total</em></td>
						<td class="dl-col-amount"><em>{{ fmt(store.summary.charitable_total) }}</em></td>
						<td class="dl-col-amount"><em>{{ pct(store.summary.charitable_total) }}</em></td>
					</tr>
					<tr>
						<td>
							Mileage Deduction
							<span class="dl-mileage-hint">{{ fmtMiles(store.summary.mileage_miles) }} miles</span>
						</td>
						<td class="dl-col-amount">{{ fmt(store.summary.mileage_deduction) }}</td>
						<td class="dl-col-amount">{{ pct(store.summary.mileage_deduction) }}</td>
					</tr>
					<tr>
						<td>Medical Expenses</td>
						<td class="dl-col-amount">{{ fmt(store.summary.medical_expenses) }}</td>
						<td class="dl-col-amount">{{ pct(store.summary.medical_expenses) }}</td>
					</tr>
					<tr>
						<td>Business Expenses</td>
						<td class="dl-col-amount">{{ fmt(store.summary.business_expenses) }}</td>
						<td class="dl-col-amount">{{ pct(store.summary.business_expenses) }}</td>
					</tr>
				</tbody>
				<tfoot>
					<tr class="dl-total-row">
						<td>Grand Total</td>
						<td class="dl-col-amount">{{ fmt(store.summary.grand_total) }}</td>
						<td class="dl-col-amount">100%</td>
					</tr>
				</tfoot>
			</table>

			<!-- Export actions -->
			<div class="dl-export-section">
				<h3>Export</h3>
				<div class="dl-export-buttons">
					<NcButton type="primary" @click="doExport(() => store.openHtml(selectedYear))">
						<template #icon><FileDocumentOutlineIcon :size="20" /></template>
						HTML Report
					</NcButton>
					<NcButton @click="doExport(() => store.downloadCsv(selectedYear))">
						<template #icon><TableIcon :size="20" /></template>
						Download CSV
					</NcButton>
					<NcButton @click="doExport(() => store.downloadTxf(selectedYear))">
						<template #icon><FileExportOutlineIcon :size="20" /></template>
						Download TXF
					</NcButton>
				</div>
				<NcNoteCard v-if="exportFlash" type="success">
					{{ settingsStore.isMandoTheme ? 'I have spoken.' : 'Export started.' }}
				</NcNoteCard>
				<p class="dl-export-hint">
					<strong>HTML Report</strong> opens in a new tab — use your browser's Print function to save as PDF.<br>
					<strong>TXF</strong> can be imported into TurboTax Desktop for Schedule A charitable deductions.
				</p>
			</div>
		</template>

		<!-- Empty state -->
		<NcEmptyContent
			v-else
			name="No data for this year"
			:description="`No deductions recorded for ${store.activeYear}.`"
		>
			<template #icon><ChartBarIcon :size="20" /></template>
		</NcEmptyContent>
	</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
	NcButton,
	NcEmptyContent,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FileExportOutlineIcon   from 'vue-material-design-icons/FileExportOutline.vue'
import TableIcon               from 'vue-material-design-icons/Table.vue'
import ChartBarIcon            from 'vue-material-design-icons/ChartBar.vue'
import { useReportsStore }     from '../stores/reports.js'
import { useSettingsStore }    from '../stores/settings.js'

const CURRENT_YEAR   = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR]

const store         = useReportsStore()
const settingsStore = useSettingsStore()
const selectedYear  = ref(CURRENT_YEAR)
const exportFlash   = ref(false)

function doExport(fn) {
	fn()
	exportFlash.value = true
	setTimeout(() => { exportFlash.value = false }, 3000)
}

onMounted(() => store.fetchSummary(CURRENT_YEAR))

const grandTotal = computed(() => fmt(store.summary?.grand_total ?? '0'))

function fmt(val) {
	return parseFloat(val || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

function fmtMiles(val) {
	return parseFloat(val || 0).toLocaleString('en-US', { maximumFractionDigits: 1 })
}

function pct(val) {
	const grand = parseFloat(store.summary?.grand_total || '0')
	if (!grand) return '—'
	return (parseFloat(val || 0) / grand * 100).toFixed(1) + '%'
}
</script>

<style scoped>
.dl-view {
	padding: 2rem;
	max-width: 800px;
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

.dl-col-amount {
	text-align: right;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

.dl-subtotal-row td {
	color: var(--color-text-light);
	background: var(--color-background-hover);
}

.dl-total-row td {
	font-weight: 700;
	border-top: 2px solid var(--color-border-dark);
	border-bottom: none;
}

.dl-mileage-hint {
	font-size: 0.8rem;
	color: var(--color-text-lighter);
	margin-left: 0.5rem;
}

.dl-export-section {
	margin-top: 2rem;
}

.dl-export-section h3 {
	margin: 0 0 0.75rem;
	font-size: 1rem;
}

.dl-export-buttons {
	display: flex;
	gap: 0.75rem;
	flex-wrap: wrap;
	margin-bottom: 0.75rem;
}

.dl-export-hint {
	font-size: 0.85rem;
	color: var(--color-text-lighter);
	line-height: 1.6;
	margin: 0;
}
</style>
