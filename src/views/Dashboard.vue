<template>
	<div class="dl-view">
		<!-- Header -->
		<div class="dl-view-header">
			<div class="dl-view-title">
				<h2>{{ householdName }}</h2>
				<span v-if="store.summary && !store.loading" class="dl-grand-total">
					{{ fmt(store.summary.grand_total) }} total deductions in {{ store.activeYear }}
				</span>
			</div>
			<div class="dl-view-actions">
				<select v-model.number="selectedYear" class="dl-year-select" @change="store.fetchSummary(selectedYear)">
					<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
				</select>
				<NcButton type="primary" @click="$router.push({ name: 'reports' })">
					<template #icon><FileChartIcon :size="20" /></template>
					{{ settingsStore.isMandoTheme ? 'Holorecords' : 'Reports' }}
				</NcButton>
			</div>
		</div>

		<!-- Loading -->
		<NcLoadingIcon v-if="store.loading" />

		<!-- Summary cards -->
		<div v-else-if="store.summary" class="dl-cards">
			<RouterLink :to="{ name: 'cash-donations' }" class="dl-card dl-card-cash">
				<div class="dl-card-icon">
					<MandoIcon v-if="settingsStore.isMandoTheme" name="coffer" :size="28" />
					<CurrencyUsdIcon v-else :size="28" />
				</div>
				<div class="dl-card-body">
					<div class="dl-card-label">Cash Donations</div>
					<div class="dl-card-amount">{{ fmt(store.summary.cash_donations) }}</div>
				</div>
				<ChevronRightIcon :size="20" class="dl-card-chevron" />
			</RouterLink>

			<RouterLink :to="{ name: 'item-donations' }" class="dl-card dl-card-item">
				<div class="dl-card-icon">
					<MandoIcon v-if="settingsStore.isMandoTheme" name="offering" :size="28" />
					<GiftIcon v-else :size="28" />
				</div>
				<div class="dl-card-body">
					<div class="dl-card-label">Item Donations</div>
					<div class="dl-card-amount">{{ fmt(store.summary.item_donations) }}</div>
				</div>
				<ChevronRightIcon :size="20" class="dl-card-chevron" />
			</RouterLink>

			<RouterLink :to="{ name: 'mileage' }" class="dl-card dl-card-mileage">
				<div class="dl-card-icon">
					<MandoIcon v-if="settingsStore.isMandoTheme" name="hunt" :size="28" />
					<CarIcon v-else :size="28" />
				</div>
				<div class="dl-card-body">
					<div class="dl-card-label">Mileage</div>
					<div class="dl-card-amount">{{ fmt(store.summary.mileage_deduction) }}</div>
					<div class="dl-card-sub">{{ fmtMiles(store.summary.mileage_miles) }} miles</div>
				</div>
				<ChevronRightIcon :size="20" class="dl-card-chevron" />
			</RouterLink>

			<RouterLink :to="{ name: 'medical' }" class="dl-card dl-card-medical">
				<div class="dl-card-icon">
					<MandoIcon v-if="settingsStore.isMandoTheme" name="medpac" :size="28" />
					<HeartPulseIcon v-else :size="28" />
				</div>
				<div class="dl-card-body">
					<div class="dl-card-label">Medical Expenses</div>
					<div class="dl-card-amount">{{ fmt(store.summary.medical_expenses) }}</div>
				</div>
				<ChevronRightIcon :size="20" class="dl-card-chevron" />
			</RouterLink>

			<RouterLink :to="{ name: 'business' }" class="dl-card dl-card-business">
				<div class="dl-card-icon">
					<MandoIcon v-if="settingsStore.isMandoTheme" name="armory" :size="28" />
					<BriefcaseIcon v-else :size="28" />
				</div>
				<div class="dl-card-body">
					<div class="dl-card-label">Business Expenses</div>
					<div class="dl-card-amount">{{ fmt(store.summary.business_expenses) }}</div>
				</div>
				<ChevronRightIcon :size="20" class="dl-card-chevron" />
			</RouterLink>
		</div>

		<!-- Grand total bar -->
		<div v-if="store.summary && !store.loading" class="dl-grand-bar">
			<div class="dl-grand-bar-label">{{ store.activeYear }} Grand Total</div>
			<div class="dl-grand-bar-amount">{{ fmt(store.summary.grand_total) }}</div>
		</div>

		<!-- Charitable subtotal note -->
		<p v-if="store.summary && !store.loading && parseFloat(store.summary.charitable_total) > 0" class="dl-charitable-note">
			Charitable deductions (Schedule A): {{ fmt(store.summary.charitable_total) }}
			&mdash; cash + item donations combined.
		</p>

		<!-- Empty / no data -->
		<NcEmptyContent
			v-else-if="!store.loading && !store.summary"
			:name="settingsStore.isMandoTheme ? 'You have a long way to go.' : 'No data yet'"
			:description="settingsStore.isMandoTheme ? '' : `Start adding deductions and they'll appear here.`"
		>
			<template #icon><HomeIcon :size="20" /></template>
		</NcEmptyContent>
	</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
	NcButton,
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import HomeIcon       from 'vue-material-design-icons/Home.vue'
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd.vue'
import GiftIcon       from 'vue-material-design-icons/Gift.vue'
import CarIcon        from 'vue-material-design-icons/Car.vue'
import HeartPulseIcon from 'vue-material-design-icons/HeartPulse.vue'
import BriefcaseIcon  from 'vue-material-design-icons/Briefcase.vue'
import FileChartIcon  from 'vue-material-design-icons/FileChart.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import MandoIcon      from '../components/icons/MandoIcon.vue'
import { useReportsStore }  from '../stores/reports.js'
import { useSettingsStore } from '../stores/settings.js'

const CURRENT_YEAR   = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR]

const store         = useReportsStore()
const settingsStore = useSettingsStore()
const selectedYear  = ref(CURRENT_YEAR)

const householdName = computed(() =>
	settingsStore.settings?.household_name || 'DeductibleLog',
)

onMounted(async () => {
	await Promise.all([
		store.fetchSummary(CURRENT_YEAR),
		settingsStore.settings && Object.keys(settingsStore.settings).length
			? Promise.resolve()
			: settingsStore.fetchSettings(),
	])
})

function fmt(val) {
	return parseFloat(val || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

function fmtMiles(val) {
	return parseFloat(val || 0).toLocaleString('en-US', { maximumFractionDigits: 1 })
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
	margin-bottom: 1.75rem;
	gap: 1rem;
	flex-wrap: wrap;
}

.dl-view-title h2 {
	margin: 0 0 0.2rem;
}

.dl-grand-total {
	font-size: 1rem;
	color: var(--color-text-lighter);
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

/* ── Cards ─────────────────────────────────────────────────────────────────── */

.dl-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
	gap: 0.875rem;
	margin-bottom: 1.25rem;
}

.dl-card {
	display: flex;
	align-items: center;
	gap: 0.875rem;
	padding: 1rem 1.125rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	text-decoration: none;
	color: var(--color-main-text);
	transition: box-shadow 0.15s, border-color 0.15s;
	cursor: pointer;
}

.dl-card:hover {
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
	border-color: var(--color-primary-element);
	text-decoration: none;
	color: var(--color-main-text);
}

.dl-card-icon {
	flex-shrink: 0;
	width: 44px;
	height: 44px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.85;
}

.dl-card-cash     .dl-card-icon { background: #d1fae5; color: #065f46; }
.dl-card-item     .dl-card-icon { background: #dbeafe; color: #1e40af; }
.dl-card-mileage  .dl-card-icon { background: #ffedd5; color: #92400e; }
.dl-card-medical  .dl-card-icon { background: #fce7f3; color: #9d174d; }
.dl-card-business .dl-card-icon { background: #ede9fe; color: #4c1d95; }

.dl-card-body {
	flex: 1;
	min-width: 0;
}

.dl-card-label {
	font-size: 0.8rem;
	color: var(--color-text-lighter);
	margin-bottom: 0.1rem;
}

.dl-card-amount {
	font-size: 1.2rem;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
}

.dl-card-sub {
	font-size: 0.78rem;
	color: var(--color-text-lighter);
	margin-top: 0.1rem;
}

.dl-card-chevron {
	flex-shrink: 0;
	color: var(--color-text-lighter);
}

/* ── Grand total bar ────────────────────────────────────────────────────────── */

.dl-grand-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0.875rem 1.25rem;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-radius: var(--border-radius-large);
	margin-bottom: 0.75rem;
}

.dl-grand-bar-label {
	font-size: 0.9rem;
	opacity: 0.9;
}

.dl-grand-bar-amount {
	font-size: 1.4rem;
	font-weight: 800;
	font-variant-numeric: tabular-nums;
}

.dl-charitable-note {
	font-size: 0.82rem;
	color: var(--color-text-lighter);
	margin: 0;
}
</style>
