<script setup>
import AppShell from '@/Components/AppShell.vue';
import Modal from '@/Components/Modal.vue';
import EscortReadinessBadge from '@/Components/EscortReadinessBadge.vue';
import ArrivalEscortTimer from '@/Components/ArrivalEscortTimer.vue';
import P2hJourney from '@/Components/P2hJourney.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { buildArrivalRows, filterArrivalRows } from '@/lib/arrivalMonitoringRows';
import { paginateRows } from '@/lib/pagination';
const props = defineProps({ items: { type: Array, default: () => [] }, isVendor: Boolean });
const onlyPending = ref(true), search = ref(''), page = ref(1), selectedKey = ref(null), rescheduling = ref(false);
const form = useForm({ planned_date: '', reschedule_reason: '' });
const rows = computed(() => buildArrivalRows(props.items));
const visible = computed(() => filterArrivalRows(rows.value, search.value, onlyPending.value));
const pagination = computed(() => paginateRows(visible.value, page.value));
const selected = computed(() => rows.value.find(row => row.key === selectedKey.value));
const totals = computed(() => ({ pending: rows.value.filter(row => row.state === 'PENDING').length, arrived: rows.value.filter(row => row.state === 'ARRIVED').length }));
const date = value => value ? new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value).toLocaleString('id-ID', { timeZone: 'Asia/Makassar', dateStyle: 'medium' }) : '-';
const plate = row => row.unit?.plate_number || row.report?.vehicle_plate || 'Belum ada unit';
const driver = row => row.unit?.driver_name || row.report?.driver_name || 'Driver belum diisi';
const p2hLabel = row => row.unit?.latest_departure_p2h?.departed_at ? 'Sudah berangkat' : ({ APPROVED: 'P2H disetujui', REJECTED: 'P2H ditolak', PENDING: 'Menunggu approval vendor' })[row.unit?.latest_departure_p2h?.status] || 'P2H belum diisi';
const close = () => { selectedKey.value = null; rescheduling.value = false; form.reset(); form.clearErrors(); };
watch([search, onlyPending], () => { page.value = 1; close(); });
// Inertia refreshes items after validation errors; keep the selected form intact.
watch(() => props.items, () => { page.value = paginateRows(visible.value, page.value).page; });
watch(() => selected.value?.key, (key, previous) => { if (previous && !key) close(); });
const open = row => { close(); selectedKey.value = row.key; };
const blocked = item => item.is_locked || !item.submission.release_expired_at || new Date(item.submission.release_expired_at) < new Date();
const reschedule = () => form.patch(`/vendor/planning-lintas/items/${selected.value.item.id}/reschedule`, { preserveScroll: true, onSuccess: close });
</script>

<template>
    <AppShell class="compact-arrival-monitoring" title="Monitoring Kedatangan Driver" subtitle="Status unit dan jadwal kedatangan dalam satu daftar ringkas." eyebrow="Planning dan Kedatangan">
        <section class="arrival-unit-list panel min-w-0 overflow-hidden p-0">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
                <div><h2 class="panel-title">Daftar Kedatangan Unit</h2><p class="panel-copy">Maksimal 10 baris per halaman. Timeline dan informasi lengkap tersedia di Lihat Detail.</p></div>
                <nav class="flex items-center gap-2" aria-label="Navigasi cepat kedatangan"><button class="unit-action" :disabled="pagination.page <= 1" @click="page = pagination.page - 1">Sebelumnya</button><span class="text-xs text-slate-500">{{ pagination.page }} / {{ pagination.pages }}</span><button class="unit-action" :disabled="pagination.page >= pagination.pages" @click="page = pagination.page + 1">Berikutnya</button></nav>
            </header>
            <div class="space-y-3 border-b border-slate-100 p-4 sm:px-5">
                <div class="flex flex-wrap gap-2 text-xs"><span class="rounded-lg bg-slate-100 px-3 py-2 text-slate-600">{{ items.length }} planning</span><span class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">Menunggu driver <b>{{ totals.pending }}</b></span><span class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">Sudah tiba <b>{{ totals.arrived }}</b></span></div>
                <label class="block min-w-0 text-xs font-semibold text-slate-600">Cari unit atau izin<input v-model="search" type="search" class="control mt-1 min-w-0" placeholder="Vendor, nomor izin, nopol, driver, atau tujuan..."></label>
                <label class="flex items-center gap-3 text-sm font-semibold text-slate-600"><input v-model="onlyPending" type="checkbox" class="rounded border-slate-300">Hanya tampilkan unit yang masih menunggu driver</label>
            </div>
            <div class="max-w-full overflow-x-auto" tabindex="0" role="region" aria-label="Daftar monitoring kedatangan unit">
                <table class="arrival-unit-table w-full text-left text-sm">
                    <caption class="sr-only">Planning, identitas unit, status kedatangan dan aksi.</caption>
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500"><tr><th scope="col">Planning / Vendor</th><th scope="col">Unit / Driver</th><th scope="col">Kedatangan</th><th scope="col">Aksi</th></tr></thead>
                    <tbody><tr v-for="row in pagination.rows" :key="row.key" class="arrival-unit-row border-b border-slate-100">
                        <td data-label="Planning / Vendor"><b class="block text-slate-800">{{ date(row.item.planned_date) }}</b><span class="mt-1 block font-semibold text-slate-700">{{ row.item.submission.vendor_name }}</span><span class="mt-1 block text-xs text-slate-500">{{ row.item.submission.submission_number }}</span><span class="mt-1 block text-xs text-slate-500">Tujuan: {{ row.item.submission.destination || '-' }}</span></td>
                        <td data-label="Unit / Driver"><b class="block text-slate-800">{{ plate(row) }}</b><span class="mt-1 block text-xs text-slate-500">{{ driver(row) }}</span><span class="mt-1 block text-xs text-slate-500">{{ row.unit?.vehicle_type || row.report?.vehicle_type || '-' }}</span></td>
                        <td data-label="Kedatangan"><span class="inline-flex rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="row.state === 'ARRIVED' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'">{{ row.state === 'ARRIVED' ? 'Sudah tiba' : row.state === 'NO_UNIT' ? 'Belum ada unit' : 'Menunggu driver' }}</span><span class="mt-2 block text-xs text-slate-500">Progres planning: {{ row.item.completed }}/{{ row.item.total }} unit tiba</span><span v-if="row.state === 'PENDING'" class="mt-1 block text-xs text-slate-600">{{ p2hLabel(row) }}</span><span v-if="row.item.reschedule_reason" class="mt-1 block text-xs text-blue-700">Jadwal pernah diubah</span></td>
                        <td data-label="Aksi"><div class="flex flex-wrap gap-2"><button class="unit-action" :aria-label="`Lihat detail ${plate(row)}`" @click="open(row)">Lihat Detail</button><a v-if="row.unit?.latest_departure_p2h || row.report?.vendor_submission_unit_id" :href="`/departure-p2h/${row.unit?.id || row.report.vendor_submission_unit_id}/download`" class="unit-action">PDF P2H</a></div></td>
                    </tr></tbody>
                </table>
            </div>
            <p v-if="!visible.length" class="p-6 text-center text-sm text-slate-500">Tidak ada unit yang sesuai filter. Ubah pencarian atau nonaktifkan filter menunggu driver.</p>
            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 sm:px-5"><p class="text-xs text-slate-500" aria-live="polite">Baris {{ pagination.from }}-{{ pagination.to }} dari {{ visible.length }} unit</p><nav class="flex items-center gap-2" aria-label="Halaman daftar kedatangan"><button class="unit-action" :disabled="pagination.page <= 1" @click="page = pagination.page - 1">Sebelumnya</button><span class="text-xs text-slate-500">{{ pagination.page }} / {{ pagination.pages }}</span><button class="unit-action" :disabled="pagination.page >= pagination.pages" @click="page = pagination.page + 1">Berikutnya</button></nav></footer>
        </section>
        <Modal :show="Boolean(selected)" max-width="3xl" aria-labelledby="arrival-unit-detail-title" @close="close">
            <div v-if="selected" class="max-h-[85dvh] overflow-y-auto overscroll-contain p-4 sm:p-6">
                <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4"><div class="min-w-0"><h3 id="arrival-unit-detail-title" class="break-words text-lg font-bold text-slate-900">{{ plate(selected) }}</h3><p class="mt-1 break-words text-sm text-slate-500">{{ selected.item.submission.vendor_name }} / {{ selected.item.submission.submission_number }}</p></div><button class="unit-action shrink-0" @click="close">Tutup</button></header>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Driver / Kontak</dt><dd class="mt-1 break-words font-semibold">{{ driver(selected) }} / {{ selected.unit?.driver_phone || '-' }}</dd></div><div><dt class="text-xs text-slate-500">Planning / Tujuan</dt><dd class="mt-1 break-words font-semibold">{{ date(selected.item.planned_date) }} / {{ selected.item.submission.destination || '-' }}</dd></div><div><dt class="text-xs text-slate-500">Masa Berlaku Izin</dt><dd class="mt-1 font-semibold">{{ date(selected.item.submission.release_expired_at) }}</dd></div><div><dt class="text-xs text-slate-500">Progres Planning</dt><dd class="mt-1 font-semibold">{{ selected.item.completed }}/{{ selected.item.total }} unit sudah tiba</dd></div></dl>
                <div v-if="selected.item.reschedule_reason" class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><p class="font-semibold">Reschedule dari {{ date(selected.item.previous_planned_date) }} ke {{ date(selected.item.planned_date) }}</p><p class="mt-1 whitespace-pre-wrap break-words">Alasan vendor: {{ selected.item.reschedule_reason }}</p></div>
                <p class="mt-4 text-sm font-semibold">{{ selected.report ? `Tiba: ${date(selected.report.arrived_at)}` : p2hLabel(selected) }}</p>
                <P2hJourney v-if="selected.unit?.latest_departure_p2h" :key="selected.key" :p2h="selected.unit.latest_departure_p2h" :arrived-at="selected.report?.arrived_at" />
                <template v-if="selected.report"><ArrivalEscortTimer :arrived-at="selected.report.arrived_at" :escort-started-at="selected.report.escort_started_at" class="mt-3" /><EscortReadinessBadge :item="selected.report" class="mt-3" /></template>
                <a v-if="selected.unit?.latest_departure_p2h || selected.report?.vendor_submission_unit_id" :href="`/departure-p2h/${selected.unit?.id || selected.report.vendor_submission_unit_id}/download`" class="unit-action mt-4">Download P2H Unit</a>
                <div v-if="isVendor && selected.item.pending_units.length" class="mt-5 border-t border-slate-100 pt-4">
                    <button type="button" class="unit-action" :disabled="blocked(selected.item)" @click="rescheduling = true">Reschedule Sisa Kedatangan</button>
                    <p v-if="selected.item.is_locked" class="mt-2 text-xs text-amber-800">Planning terkunci. Reviewer perlu membuka kunci sebelum reschedule.</p><p v-else-if="blocked(selected.item)" class="mt-2 text-xs text-amber-800">Periksa masa berlaku izin sebelum reschedule.</p>
                    <form v-if="rescheduling" class="mt-4 space-y-3" @submit.prevent="reschedule"><p class="text-xs text-slate-600">Perubahan jadwal berlaku untuk semua unit yang masih menunggu pada planning ini.</p><label class="block text-sm font-semibold">Tanggal baru<input v-model="form.planned_date" type="date" class="field" required :max="selected.item.submission.release_expired_at?.slice(0, 10)"><p class="err">{{ form.errors.planned_date }}</p></label><label class="block text-sm font-semibold">Alasan reschedule<textarea v-model="form.reschedule_reason" class="field" rows="3" required maxlength="2000"></textarea><p class="err">{{ form.errors.reschedule_reason }}</p></label><div class="flex flex-wrap gap-3"><button class="unit-action" :disabled="form.processing">Simpan Reschedule</button><button type="button" class="unit-action" @click="rescheduling = false">Batal</button></div></form>
                </div>
            </div>
        </Modal>
    </AppShell>
</template>

<style scoped>
.app-shell.compact-arrival-monitoring { --glass-surface: #fff; --glass-surface-strong: #fff; --glass-shadow: 0 4px 16px rgba(15, 23, 42, .05); }
.app-shell.compact-arrival-monitoring :deep(*) { -webkit-backdrop-filter: none; backdrop-filter: none; }
.arrival-unit-table th, .arrival-unit-table td { padding: .875rem 1rem; vertical-align: top; overflow-wrap: anywhere; }
.arrival-unit-table th:first-child { width: 34%; }
.arrival-unit-table th:nth-child(2) { width: 22%; }
.arrival-unit-table th:nth-child(3) { width: 26%; }
.unit-action { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; border: 1px solid #e2e8f0; border-radius: .625rem; padding: .5rem .75rem; background: #fff; color: #475569; font-size: 12px; font-weight: 600; }
.unit-action:hover { border-color: #fecaca; background: #fff1f2; color: #b91c1c; }
.unit-action:focus-visible { outline: 2px solid #dc2626; outline-offset: 2px; }
.unit-action:disabled { opacity: .45; cursor: not-allowed; }
@media (max-width: 639px) {
    .arrival-unit-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
    .arrival-unit-table, .arrival-unit-table tbody, .arrival-unit-table tr, .arrival-unit-table td { display: block; width: 100%; }
    .arrival-unit-table tr { padding: .75rem 0; }
    .arrival-unit-table td { padding: .375rem 1rem; }
    .arrival-unit-table td::before { content: attr(data-label); display: block; margin-bottom: .25rem; color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: 600; }
    .unit-action { min-height: 44px; }
}
</style>
