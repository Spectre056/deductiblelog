<template>
	<NcContent app-name="deductiblelog">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem :name="mando ? 'The Covert'     : 'Dashboard'"         :to="{ name: 'dashboard' }">
					<template #icon>
						<MandoIcon v-if="mando" name="covert" :size="20" />
						<HomeIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'The Coffer'     : 'Cash Donations'"    :to="{ name: 'cash-donations' }">
					<template #icon>
						<MandoIcon v-if="mando" name="coffer" :size="20" />
						<CurrencyUsdIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'The Offering'   : 'Item Donations'"    :to="{ name: 'item-donations' }">
					<template #icon>
						<MandoIcon v-if="mando" name="offering" :size="20" />
						<GiftIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'The Hunt'       : 'Mileage'"           :to="{ name: 'mileage' }">
					<template #icon>
						<MandoIcon v-if="mando" name="hunt" :size="20" />
						<CarIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'Medpac Ledger'  : 'Medical'"           :to="{ name: 'medical' }">
					<template #icon>
						<MandoIcon v-if="mando" name="medpac" :size="20" />
						<HeartPulseIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'The Armory'     : 'Business Expenses'" :to="{ name: 'business' }">
					<template #icon>
						<MandoIcon v-if="mando" name="armory" :size="20" />
						<BriefcaseIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'Holorecords'    : 'Reports'"           :to="{ name: 'reports' }">
					<template #icon>
						<MandoIcon v-if="mando" name="holorecords" :size="20" />
						<FileChartIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationSpacer />

				<NcAppNavigationItem name="Charities"      :to="{ name: 'charities' }">
					<template #icon><AccountGroupIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Family Members" :to="{ name: 'family-members' }">
					<template #icon><AccountMultipleIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="mando ? 'Beskar Forge' : 'Settings'" :to="{ name: 'settings' }">
					<template #icon>
						<MandoIcon v-if="mando" name="forge" :size="20" />
						<CogIcon v-else :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<RouterView />
		</NcAppContent>
	</NcContent>

	<Teleport to="body">
		<template v-if="mando">
			<div class="dl-page-watermark" :style="{ backgroundImage: `url(${appRoot}/img/jaing-head.svg)` }" />
			<div class="dl-nav-helmet"     :style="{ backgroundImage: `url(${appRoot}/img/helmet.png)` }" />
		</template>
	</Teleport>
</template>

<script setup>
import { watchEffect, computed } from 'vue'
import {
	NcContent,
	NcAppNavigation,
	NcAppNavigationItem,
	NcAppNavigationSpacer,
	NcAppContent,
} from '@nextcloud/vue'
import { RouterView } from 'vue-router'
import './assets/mando-theme.css'

import HomeIcon            from 'vue-material-design-icons/Home.vue'
import CurrencyUsdIcon     from 'vue-material-design-icons/CurrencyUsd.vue'
import GiftIcon            from 'vue-material-design-icons/Gift.vue'
import CarIcon             from 'vue-material-design-icons/Car.vue'
import HeartPulseIcon      from 'vue-material-design-icons/HeartPulse.vue'
import BriefcaseIcon       from 'vue-material-design-icons/Briefcase.vue'
import FileChartIcon       from 'vue-material-design-icons/FileChart.vue'
import AccountGroupIcon    from 'vue-material-design-icons/AccountGroup.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import CogIcon             from 'vue-material-design-icons/Cog.vue'
import MandoIcon           from './components/icons/MandoIcon.vue'

import { useSettingsStore } from './stores/settings.js'

const settingsStore = useSettingsStore()
settingsStore.fetchSettings()

const mando   = computed(() => settingsStore.isMandoTheme)
const appRoot = window.OC?.appswebroots?.['deductiblelog'] ?? '/custom_apps/deductiblelog'

watchEffect(() => {
	document.body.classList.toggle('mando-theme', settingsStore.isMandoTheme)
})
</script>
