/**
 * Dates in this app are calendar days with no time zone. Building "today" from
 * toISOString() gives the UTC day, which is tomorrow after 8 pm Eastern and
 * crosses into the next tax year on Dec 31. Use the local calendar instead.
 */
export function todayLocalISO(d = new Date()) {
	const y = d.getFullYear()
	const m = String(d.getMonth() + 1).padStart(2, '0')
	const day = String(d.getDate()).padStart(2, '0')
	return `${y}-${m}-${day}`
}

export function currentYear() {
	return new Date().getFullYear()
}

export function yearOf(isoDate) {
	return isoDate ? parseInt(String(isoDate).substring(0, 4), 10) : null
}

/** "2026-04-05" → "04/05/2026" without going through Date (no TZ shift). */
export function formatDate(iso) {
	if (!iso) return '—'
	const [y, m, d] = String(iso).substring(0, 10).split('-')
	return `${m}/${d}/${y}`
}

/** Server timestamps are "YYYY-MM-DD HH:MM:SS"; Safari will not parse that with a space. */
export function formatDateTime(ts) {
	if (!ts) return ''
	const d = new Date(String(ts).replace(' ', 'T'))
	return isNaN(d) ? String(ts) : d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
}
