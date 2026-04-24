import { createRouter, createWebHashHistory } from 'vue-router'

import Dashboard      from '../views/Dashboard.vue'
import Charities      from '../views/Charities.vue'
import FamilyMembers  from '../views/FamilyMembers.vue'
import CashDonations  from '../views/CashDonations.vue'
import ItemDonations  from '../views/ItemDonations.vue'
import Mileage        from '../views/Mileage.vue'
import Medical        from '../views/Medical.vue'
import Business       from '../views/Business.vue'
import Reports        from '../views/Reports.vue'
import Settings       from '../views/Settings.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/',                name: 'dashboard',      component: Dashboard },
		{ path: '/charities',       name: 'charities',      component: Charities },
		{ path: '/family-members',  name: 'family-members', component: FamilyMembers },
		{ path: '/cash-donations',  name: 'cash-donations', component: CashDonations },
		{ path: '/item-donations',  name: 'item-donations', component: ItemDonations },
		{ path: '/mileage',         name: 'mileage',        component: Mileage },
		{ path: '/medical',         name: 'medical',        component: Medical },
		{ path: '/business',        name: 'business',       component: Business },
		{ path: '/reports',         name: 'reports',        component: Reports },
		{ path: '/settings',        name: 'settings',       component: Settings },
	],
})
