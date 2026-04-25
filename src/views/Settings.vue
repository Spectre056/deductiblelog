<template>
	<div class="dl-view">
		<h2>Settings</h2>

		<!-- Loading -->
		<NcLoadingIcon v-if="store.loading" />

		<template v-else>
			<!-- General Settings -->
			<section class="dl-section">
				<h3>General</h3>
				<div class="dl-form">
					<div class="dl-field-group">
						<label class="dl-label">Household Name</label>
						<input
							v-model="form.householdName"
							type="text"
							class="dl-text-input"
							placeholder="e.g. Eckard Family"
						/>
					</div>
					<div class="dl-field-group">
						<label class="dl-label">Default Tax Year</label>
						<select v-model.number="form.defaultTaxYear" class="dl-year-select">
							<option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
						</select>
					</div>
					<div>
						<NcButton type="primary" :disabled="saving" @click="save">
							{{ saving ? 'Saving…' : 'Save Settings' }}
						</NcButton>
					</div>
					<NcNoteCard v-if="saveSuccess" type="success">
						{{ store.isMandoTheme ? 'Foundlings are the future.' : 'Settings saved.' }}
					</NcNoteCard>
					<NcNoteCard v-if="saveError" type="error">{{ saveError }}</NcNoteCard>
				</div>
			</section>

			<!-- Appearance -->
			<section class="dl-section">
				<h3>Appearance</h3>
				<label class="dl-checkbox-label">
					<input v-model="form.mandoTheme" type="checkbox" />
					Enable Mandalorian theme
				</label>
				<p class="dl-hint">Beskar color palette, Star Jedi headings, and themed navigation names.</p>
			</section>

			<!-- IRS Mileage Rates -->
			<section class="dl-section">
				<h3>IRS Mileage Rates</h3>
				<p class="dl-hint">
					Current rates stored in the database.
					<span v-if="store.settings.last_update_check">
						Last checked: {{ formatDate(store.settings.last_update_check) }}.
					</span>
				</p>

				<NcLoadingIcon v-if="rateStore.loading" />
				<table v-else-if="Object.keys(rateStore.taxRates).length" class="dl-table">
					<thead>
						<tr>
							<th>Year</th>
							<th class="dl-col-amount">Charitable (¢/mi)</th>
							<th class="dl-col-amount">Medical (¢/mi)</th>
							<th class="dl-col-amount">Business (¢/mi)</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(rate, year) in rateStore.taxRates" :key="year">
							<td>{{ year }}</td>
							<td class="dl-col-amount">{{ rate.charitable }}¢</td>
							<td class="dl-col-amount">{{ rate.medical }}¢</td>
							<td class="dl-col-amount">{{ rate.business }}¢</td>
						</tr>
					</tbody>
				</table>

				<div class="dl-update-row">
					<NcButton :disabled="store.checking" @click="store.checkForUpdates()">
						<template #icon><RefreshIcon :size="18" /></template>
						{{ store.checking ? 'Checking…' : 'Check for Updates' }}
					</NcButton>
				</div>

				<!-- Check error -->
				<NcNoteCard v-if="store.checkError" type="error">{{ store.checkError }}</NcNoteCard>

				<!-- Updates available -->
				<template v-if="store.checkResult?.updates_available?.length">
					<NcNoteCard type="info">
						{{ store.isMandoTheme ? 'New intel from the Guild.' : `${store.checkResult.updates_available.length} rate update(s) available.` }}
					</NcNoteCard>
					<table class="dl-table dl-update-table">
						<thead>
							<tr><th>Year</th><th>Field</th><th>Current</th><th>New</th></tr>
						</thead>
						<tbody>
							<template v-for="upd in store.checkResult.updates_available" :key="upd.year">
								<tr v-if="upd.current === null || upd.current.charitable !== upd.new.charitable">
									<td>{{ upd.year }}</td><td>Charitable</td>
									<td>{{ upd.current?.charitable ?? '—' }}¢</td>
									<td class="dl-new-value">{{ upd.new.charitable }}¢</td>
								</tr>
								<tr v-if="upd.current === null || upd.current.medical !== upd.new.medical">
									<td>{{ upd.year }}</td><td>Medical</td>
									<td>{{ upd.current?.medical ?? '—' }}¢</td>
									<td class="dl-new-value">{{ upd.new.medical }}¢</td>
								</tr>
								<tr v-if="upd.current === null || upd.current.business !== upd.new.business">
									<td>{{ upd.year }}</td><td>Business</td>
									<td>{{ upd.current?.business ?? '—' }}¢</td>
									<td class="dl-new-value">{{ upd.new.business }}¢</td>
								</tr>
							</template>
						</tbody>
					</table>
					<NcButton
						type="primary"
						:disabled="store.applying"
						@click="applyUpdates"
					>
						{{ store.applying ? 'Applying…' : 'Apply Updates' }}
					</NcButton>
				</template>

				<!-- Up to date -->
				<NcNoteCard
					v-else-if="store.checkResult && !store.checkResult.updates_available?.length"
					type="success"
				>
					{{ store.isMandoTheme ? 'The asset is secure.' : 'Rates are up to date.' }}
				</NcNoteCard>
			</section>

			<!-- About -->
			<section class="dl-settings-section">
				<h3>{{ store.isMandoTheme ? 'The Codex' : 'About' }}</h3>
				<div class="dl-about">
					<p class="dl-about-name">DeductibleLog</p>
					<p class="dl-about-tagline">{{ store.isMandoTheme ? 'Track your beskar. Honor the Creed.' : 'A self-hosted tax deduction tracker for Nextcloud.' }}</p>
					<p class="dl-about-credits">
						Crafted by <strong>Michael Eckard</strong> (Spectre056)<br>
						with an AI co-pilot riding shotgun on the Razor Crest.
					</p>
					<p class="dl-about-version">v0.1.0 &mdash; This is the Way.</p>
				</div>
			</section>
		</template>
	</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
	NcButton,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'
import RefreshIcon         from 'vue-material-design-icons/Refresh.vue'
import { useSettingsStore } from '../stores/settings.js'
import { useMileageStore }  from '../stores/mileage.js'

const CURRENT_YEAR   = new Date().getFullYear()
const availableYears = [CURRENT_YEAR - 2, CURRENT_YEAR - 1, CURRENT_YEAR, CURRENT_YEAR + 1]

const store     = useSettingsStore()
const rateStore = useMileageStore()

const saving      = ref(false)
const saveSuccess = ref(false)
const saveError   = ref(null)

const form = reactive({
	householdName:   '',
	defaultTaxYear:  CURRENT_YEAR,
	mandoTheme:      false,
})

onMounted(async () => {
	await Promise.all([
		store.fetchSettings(),
		rateStore.taxRates && Object.keys(rateStore.taxRates).length
			? Promise.resolve()
			: rateStore.fetchRates(),
	])
	form.householdName  = store.settings.household_name ?? ''
	form.defaultTaxYear = parseInt(store.settings.default_tax_year ?? CURRENT_YEAR, 10)
	form.mandoTheme     = store.settings.mando_theme === '1'
})

async function save() {
	saving.value     = false
	saveSuccess.value = false
	saveError.value  = null
	saving.value     = true
	try {
		await store.saveSettings({
			household_name:   form.householdName.trim() || null,
			default_tax_year: form.defaultTaxYear,
			mando_theme:      form.mandoTheme ? '1' : '0',
		})
		saveSuccess.value = true
		setTimeout(() => { saveSuccess.value = false }, 3000)
	} catch (e) {
		saveError.value = e?.response?.data?.message ?? 'Save failed'
	} finally {
		saving.value = false
	}
}

async function applyUpdates() {
	await store.applyUpdates(store.checkResult.updates_available.map(u => ({
		year:        u.year,
		charitable:  u.new.charitable,
		medical:     u.new.medical,
		business:    u.new.business,
	})))
	await rateStore.fetchRates()
}

function formatDate(iso) {
	if (!iso) return ''
	return new Date(iso).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.dl-view {
	padding: 2rem;
	max-width: 700px;
}

.dl-view h2 {
	margin-bottom: 1.5rem;
}

.dl-section {
	margin-bottom: 2.5rem;
}

.dl-section h3 {
	font-size: 1rem;
	font-weight: 600;
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 0.4rem;
	margin: 0 0 1rem;
}

.dl-form {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	max-width: 400px;
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

.dl-text-input,
.dl-year-select {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0.375rem 0.5rem;
	font-size: 0.9rem;
	height: 36px;
	width: 100%;
}

.dl-hint {
	font-size: 0.85rem;
	color: var(--color-text-lighter);
	margin: 0 0 0.75rem;
}

.dl-table {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 0.75rem;
}

.dl-table th,
.dl-table td {
	text-align: left;
	padding: 0.4rem 0.6rem;
	border-bottom: 1px solid var(--color-border);
}

.dl-table th {
	color: var(--color-text-lighter);
	font-weight: 600;
	font-size: 0.82rem;
}

.dl-col-amount {
	text-align: right;
	font-variant-numeric: tabular-nums;
}

.dl-checkbox-label {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	cursor: pointer;
	font-size: 0.9rem;
}

.dl-update-row {
	margin-bottom: 0.75rem;
}

.dl-update-table {
	margin: 0.5rem 0 0.75rem;
	font-size: 0.9rem;
}

.dl-new-value {
	color: var(--color-success);
	font-weight: 600;
}

.dl-about {
	max-width: 480px;
	padding: 1rem 0;
	line-height: 1.6;
}

.dl-about-name {
	font-size: 1.15rem;
	font-weight: 700;
	margin: 0 0 0.15rem;
}

.dl-about-tagline {
	color: var(--color-text-lighter);
	margin: 0 0 0.75rem;
	font-size: 0.9rem;
}

.dl-about-credits {
	margin: 0 0 0.5rem;
	font-size: 0.9rem;
}

.dl-about-version {
	font-size: 0.8rem;
	color: var(--color-text-lighter);
	margin: 0;
}
</style>
