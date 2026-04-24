<template>
	<NcContent app-name="deductiblelog">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem name="Dashboard"         :to="{ name: 'dashboard' }">
					<template #icon><HomeIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Cash Donations"    :to="{ name: 'cash-donations' }">
					<template #icon><CurrencyUsdIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Item Donations"    :to="{ name: 'item-donations' }">
					<template #icon><GiftIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Mileage"           :to="{ name: 'mileage' }">
					<template #icon><CarIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Medical"           :to="{ name: 'medical' }">
					<template #icon><HeartPulseIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Business Expenses" :to="{ name: 'business' }">
					<template #icon><BriefcaseIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Reports"           :to="{ name: 'reports' }">
					<template #icon><FileChartIcon :size="20" /></template>
				</NcAppNavigationItem>

				<NcAppNavigationSpacer />

				<NcAppNavigationItem name="Charities"      :to="{ name: 'charities' }">
					<template #icon><AccountGroupIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Family Members" :to="{ name: 'family-members' }">
					<template #icon><AccountMultipleIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Settings"       :to="{ name: 'settings' }">
					<template #icon><CogIcon :size="20" /></template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<RouterView />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import {
	NcContent,
	NcAppNavigation,
	NcAppNavigationItem,
	NcAppNavigationSpacer,
	NcAppContent,
} from '@nextcloud/vue'
import { RouterView } from 'vue-router'

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
</script>
