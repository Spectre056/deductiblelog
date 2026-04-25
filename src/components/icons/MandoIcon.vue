<template>
	<svg
		:width="size"
		:height="size"
		viewBox="0 0 24 24"
		fill="currentColor"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
	>
		<!-- covert: Mandalorian helmet with T-visor cutout -->
		<path
			v-if="name === 'covert'"
			fill-rule="evenodd"
			d="M12 2C7 2 3.5 6 3.5 10.5V16c0 3 2 5 5.5 5.5V19c0-1.5 1-2.5 3-2.5s3 1 3 2.5v2.5C18 21 20.5 19 20.5 16V10.5C20.5 6 17 2 12 2ZM5.5 10H18.5V14H5.5ZM10 5.5H14V10.5H10Z"
		/>

		<!-- coffer: Beskar ingot stack (The Coffer / Cash) -->
		<g v-else-if="name === 'coffer'">
			<rect x="4" y="6" width="16" height="5" rx="1.5" fill="currentColor" />
			<rect x="3" y="11" width="18" height="7" rx="1.5" fill="currentColor" opacity="0.85" />
			<line x1="3" y1="14" x2="21" y2="14" stroke-width="1" stroke="var(--dl-bg,#1c2030)" fill="none" />
			<line x1="3" y1="16.5" x2="21" y2="16.5" stroke-width="1" stroke="var(--dl-bg,#1c2030)" fill="none" />
		</g>

		<!-- offering: supply crate (The Offering / Item Donations) -->
		<g v-else-if="name === 'offering'">
			<rect x="3" y="10" width="18" height="12" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5" />
			<rect x="2" y="6" width="20" height="5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5" />
			<line x1="12" y1="6" x2="12" y2="11" stroke="currentColor" stroke-width="1.5" />
			<line x1="3" y1="16" x2="21" y2="16" stroke="currentColor" stroke-width="1" />
			<rect x="9.5" y="9" width="5" height="3" rx="0.5" fill="currentColor" />
		</g>

		<!-- hunt: Razor Crest silhouette top-down (The Hunt / Mileage) -->
		<g v-else-if="name === 'hunt'">
			<ellipse cx="12" cy="13" rx="3.5" ry="7" fill="currentColor" />
			<path d="M8.5 10 L3 7.5 L3 16 L8.5 16Z" fill="currentColor" />
			<path d="M15.5 10 L21 7.5 L21 16 L15.5 16Z" fill="currentColor" />
			<ellipse cx="12" cy="5.5" rx="2.5" ry="2" fill="currentColor" opacity="0.6" />
		</g>

		<!-- medpac: medical pack with cross (Medpac Ledger / Medical) -->
		<g v-else-if="name === 'medpac'">
			<rect x="3.5" y="3.5" width="17" height="17" rx="4" fill="none" stroke="currentColor" stroke-width="1.5" />
			<rect x="7" y="11" width="10" height="2.5" rx="1" fill="currentColor" />
			<rect x="11" y="7" width="2.5" height="10" rx="1" fill="currentColor" />
		</g>

		<!-- armory: blaster pistol (The Armory / Business) -->
		<g v-else-if="name === 'armory'">
			<rect x="2" y="9.5" width="13" height="4" rx="1" fill="currentColor" />
			<rect x="12" y="8.5" width="8" height="7" rx="1.5" fill="currentColor" />
			<rect x="14" y="15" width="4.5" height="5.5" rx="1" fill="currentColor" />
			<rect x="2" y="10.5" width="4" height="2" rx="0.5" fill="currentColor" opacity="0.5" />
		</g>

		<!-- holorecords: holographic data rings (Holorecords / Reports) -->
		<g v-else-if="name === 'holorecords'">
			<ellipse cx="12" cy="17" rx="9" ry="3.5" fill="none" stroke="currentColor" stroke-width="1.5" />
			<ellipse cx="12" cy="12" rx="6.5" ry="2.5" fill="none" stroke="currentColor" stroke-width="1.5" />
			<ellipse cx="12" cy="8" rx="4" ry="1.5" fill="none" stroke="currentColor" stroke-width="1.5" />
			<line x1="3.2" y1="17" x2="8.2" y2="8" stroke="currentColor" stroke-width="0.8" opacity="0.45" />
			<line x1="20.8" y1="17" x2="15.8" y2="8" stroke="currentColor" stroke-width="0.8" opacity="0.45" />
		</g>

		<!-- forge: beskar anvil (Beskar Forge / Settings) -->
		<g v-else-if="name === 'forge'">
			<rect x="5" y="7.5" width="14" height="5" rx="2.5" fill="currentColor" />
			<path d="M5 9.5 L2.5 10 L2.5 12 L5 12Z" fill="currentColor" />
			<rect x="8.5" y="11.5" width="7" height="2" fill="currentColor" />
			<rect x="7" y="13" width="10" height="7" rx="1" fill="currentColor" />
		</g>
	</svg>
</template>

<script setup>
defineProps({
	name: { type: String, required: true },
	size: { type: Number, default: 20 },
})
</script>
