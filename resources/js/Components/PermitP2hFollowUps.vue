<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import P2hJourney from '@/Components/P2hJourney.vue';
import { buildFollowUpRows, filterFollowUpRows, followUpPage } from '@/lib/permitP2hRows';

const props = defineProps({ permits: { type: Array, default: () => [] }, reviewBase: String });
const search = ref(''), stage = ref(''), page = ref(1), selectedKey = ref(null);
const rows = computed(() => buildFollowUpRows(props.permits));
const filtered = computed(() => filterFollowUpRows(rows.value, search.value, stage.value));
const pagination = computed(() => followUpPage(filtered.value, page.value));
const selected = computed(() => rows.value.find(row => row.key === selectedKey.value));
watch([search, stage, () => props.permits], () => { page.value = 1; selectedKey.value = null; });
const detailUrl = permit => props.reviewBase === '/purchasing' ? `/purchasing/vendor-submission/${permit.id}` : `/coordinator/submission/${permit.id}`;
const accountLabel = permit => permit.expedition_accounts?.length ? permit.expedition_accounts.map(account => account.name).join(', ') : 'Akun ekspedisi belum tersedia';
const stages = {
    AWAITING_P2H: ['Menunggu P2H ekspedisi', 'Ekspedisi membuka P2H & Kedatangan, memilih izin ini, lalu klik Isi Form P2H.', 'bg-slate-100 text-slate-700'],
    AWAITING_VENDOR: ['Menunggu approval vendor', 'Vendor membuka Detail Pengajuan izin ini untuk Approve atau Reject P2H.', 'bg-amber-50 text-amber-800'],
    NEEDS_REVISION: ['P2H perlu diperbaiki', 'Ekspedisi membuka izin ini, klik Perbaiki P2H, lalu kirim ulang ke vendor.', 'bg-red-50 text-red-700'],
    AWAITING_DEPARTURE: ['Siap berangkat', 'Ekspedisi klik Konfirmasi Keberangkatan pada unit ini.', 'bg-blue-50 text-blue-700'],
    IN_TRANSIT: ['Dalam perjalanan', 'Setelah tiba, isi P2H kedatangan dan tunggu persetujuan Coordinator sebelum mengisi laporan kedatangan.', 'bg-indigo-50 text-indigo-700'],
    ARRIVED: ['Sudah tiba', 'Lanjutkan proses pengawalan pada monitoring kedatangan.', 'bg-emerald-50 text-emerald-700'],
    NO_UNIT: ['Belum ada unit', 'Izin belum memiliki unit kendaraan. Periksa detail izin.', 'bg-slate-100 text-slate-600'],
};
const counts = computed(() => ({ waiting: rows.value.filter(row => !['IN_TRANSIT', 'ARRIVED'].includes(row.stage)).length, transit: rows.value.filter(row => row.stage === 'IN_TRANSIT').length, arrived: rows.value.filter(row => row.stage === 'ARRIVED').length }));
</script>

<template>
    <section class="permit-follow-ups panel my-5 min-w-0 overflow-hidden p-0" aria-labelledby="p2h-follow-up-title">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
            <div class="min-w-0"><h2 id="p2h-follow-up-title" class="panel-title">Tindak Lanjut P2H Ekspedisi</h2><p class="panel-copy">Ringkasan unit pada izin Released. Buka detail untuk langkah berikutnya dan waktu perjalanan.</p></div>
            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ permits.length }} izin pada halaman pengajuan ini</span><nav v-if="pagination.pages > 1" class="flex items-center gap-2" aria-label="Navigasi cepat ringkasan P2H"><button type="button" class="row-action" :disabled="pagination.page <= 1" @click="page = pagination.page - 1">Sebelumnya</button><span class="text-xs text-slate-500">{{ pagination.page }} / {{ pagination.pages }}</span><button type="button" class="row-action" :disabled="pagination.page >= pagination.pages" @click="page = pagination.page + 1">Berikutnya</button></nav></div>
        </header>
        <div class="space-y-3 border-b border-slate-100 p-4 sm:px-5">
            <div class="flex flex-wrap gap-2 text-xs"><span class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">Perlu tindak lanjut <b>{{ counts.waiting }}</b></span><span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-700">Dalam perjalanan <b>{{ counts.transit }}</b></span><span class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">Sudah tiba <b>{{ counts.arrived }}</b></span></div>
            <div class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(180px,240px)]">
                <label class="block min-w-0 text-xs font-semibold text-slate-600">Cari pada ringkasan ini<input v-model="search" type="search" class="control mt-1 min-w-0" placeholder="Vendor, nomor izin, nopol, atau driver..."></label>
                <label class="block min-w-0 text-xs font-semibold text-slate-600">Status unit<select v-model="stage" class="control mt-1 min-w-0"><option value="">Semua status</option><option v-for="(value, key) in stages" :key="key" :value="key">{{ value[0] }}</option></select></label>
            </div>
            <p class="text-xs leading-5 text-slate-500">Maksimal 10 baris per halaman. Untuk mencari izin di seluruh data, gunakan Filter Pengajuan di atas dan pagination daftar utama.</p>
        </div>
        <div class="max-w-full overflow-x-auto" tabindex="0" role="region" aria-label="Ringkasan tindak lanjut unit P2H">
            <table class="follow-up-table w-full text-left text-sm">
                <caption class="sr-only">Status P2H per unit pada halaman pengajuan yang sedang dibuka.</caption>
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500"><tr><th scope="col">Vendor / Izin</th><th scope="col">Unit / Driver</th><th scope="col">Status Unit</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                    <tr v-for="row in pagination.rows" :key="row.key" class="follow-up-row border-b border-slate-100">
                        <td data-label="Vendor / Izin"><b class="block break-words text-slate-800">{{ row.permit.vendor_name }}</b><span class="mt-1 block break-words text-xs text-slate-500">{{ row.permit.submission_number }}</span><span class="mt-1 block text-xs text-slate-500">Akun: {{ accountLabel(row.permit) }}</span></td>
                        <td data-label="Unit / Driver"><b class="block text-slate-800">{{ row.unit?.plate_number || 'Belum ada unit' }}</b><span class="mt-1 block text-xs text-slate-500">{{ row.unit?.driver_name || 'Driver belum diisi' }}</span></td>
                        <td data-label="Status Unit"><span class="inline-flex rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="stages[row.stage][2]">{{ stages[row.stage][0] }}</span><span v-if="row.permit.is_expired || !row.permit.company?.is_active || !row.permit.expedition_accounts?.length" class="mt-2 block text-xs font-semibold text-amber-800">Periksa izin / akun</span></td>
                        <td data-label="Aksi"><div class="flex flex-wrap gap-2"><button type="button" class="row-action" :aria-label="`Lihat detail ${row.unit?.plate_number || row.permit.submission_number}`" @click="selectedKey = row.key">Lihat Detail</button><a v-if="row.unit?.p2h" :href="`/departure-p2h/${row.unit.id}/download`" class="row-action">PDF P2H</a><Link :href="detailUrl(row.permit)" class="row-action">Buka Izin</Link></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!filtered.length" class="p-6 text-center text-sm text-slate-500">{{ permits.length ? 'Tidak ada unit yang sesuai pencarian atau status.' : 'Tidak ada izin Released pada hasil daftar ini. Pilih status Released pada Filter Pengajuan.' }}</p>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 sm:px-5">
            <p class="text-xs text-slate-500" aria-live="polite">Baris {{ pagination.from }}-{{ pagination.to }} dari {{ filtered.length }} hasil pada halaman pengajuan ini</p>
            <nav class="flex items-center gap-2" aria-label="Halaman ringkasan P2H"><button type="button" class="row-action" :disabled="pagination.page <= 1" @click="page = pagination.page - 1">Sebelumnya</button><span class="text-xs font-semibold text-slate-600">{{ pagination.page }} / {{ pagination.pages }}</span><button type="button" class="row-action" :disabled="pagination.page >= pagination.pages" @click="page = pagination.page + 1">Berikutnya</button></nav>
        </footer>
        <Modal :show="Boolean(selected)" max-width="3xl" aria-labelledby="follow-up-detail-title" @close="selectedKey = null">
            <div v-if="selected" class="max-h-[85dvh] overflow-y-auto overscroll-contain p-4 sm:p-6">
                <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4"><div class="min-w-0"><h3 id="follow-up-detail-title" class="break-words text-lg font-bold text-slate-900">{{ selected.unit?.plate_number || selected.permit.submission_number }}</h3><p class="mt-1 break-words text-sm text-slate-500">{{ selected.permit.vendor_name }} / {{ selected.unit?.driver_name || 'Driver belum diisi' }}</p></div><button type="button" class="row-action shrink-0" @click="selectedKey = null">Tutup</button></header>
                <div class="mt-4 space-y-3 text-sm">
                    <p><b>{{ stages[selected.stage][0] }}</b></p><p class="text-slate-600">{{ stages[selected.stage][1] }}</p>
                    <p v-if="selected.permit.is_expired" class="rounded-xl bg-amber-50 p-3 text-amber-900">Masa berlaku izin belum diisi atau sudah berakhir. Periksa detail izin sebelum melanjutkan P2H.</p>
                    <p v-if="!selected.permit.company?.is_active" class="rounded-xl bg-amber-50 p-3 text-amber-900">Akun vendor belum terhubung ke perusahaan aktif. Perbaiki perusahaan akun vendor melalui Manajemen Pengguna.</p>
                    <div v-else-if="!selected.permit.expedition_accounts?.length" class="rounded-xl bg-amber-50 p-3 text-amber-900"><b>Belum ditemukan akun ekspedisi aktif untuk {{ selected.permit.company.name }} pada site ini.</b><p class="mt-1">Perusahaan ekspedisi dikenali otomatis dari username sesuai singkatan atau nama master vendor. Pastikan username sesuai, akun aktif, dan site sama.</p></div>
                    <p v-else class="text-slate-600">Akun ekspedisi terkait: <b>{{ accountLabel(selected.permit) }}</b>. Akun ini mengisi P2H dari menu P2H &amp; Kedatangan.</p>
                    <p v-if="selected.unit?.p2h?.rejection_reason" class="rounded-xl bg-red-50 p-3 text-red-700">Alasan penolakan: {{ selected.unit.p2h.rejection_reason }}</p>
                    <P2hJourney v-if="selected.unit?.p2h" :key="selected.key" :p2h="selected.unit.p2h" :arrived-at="selected.unit.arrived_at" />
                    <div class="flex flex-wrap gap-2 pt-2"><a v-if="selected.unit?.p2h" :href="`/departure-p2h/${selected.unit.id}/download`" class="row-action">Download PDF P2H</a><Link :href="detailUrl(selected.permit)" class="row-action">Detail Izin &amp; P2H</Link></div>
                </div>
            </div>
        </Modal>
    </section>
</template>

<style scoped>
.app-shell .permit-follow-ups { background: #fff; -webkit-backdrop-filter: none; backdrop-filter: none; }
.follow-up-table th, .follow-up-table td { padding: .875rem 1rem; vertical-align: top; overflow-wrap: anywhere; }
.follow-up-table th:first-child { width: 34%; }
.follow-up-table th:nth-child(2) { width: 20%; }
.follow-up-table th:nth-child(3) { width: 23%; }
.row-action { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; border: 1px solid #e2e8f0; border-radius: .625rem; padding: .5rem .75rem; background: #fff; color: #475569; font-size: 12px; font-weight: 600; }
.row-action:hover { border-color: #fecaca; color: #b91c1c; background: #fff1f2; }
.row-action:focus-visible { outline: 2px solid #dc2626; outline-offset: 2px; }
.row-action:disabled { opacity: .45; cursor: not-allowed; }
@media (max-width: 639px) {
    .follow-up-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
    .follow-up-table, .follow-up-table tbody, .follow-up-table tr, .follow-up-table td { display: block; width: 100%; }
    .follow-up-table tr { padding: .75rem 0; }
    .follow-up-table td { padding: .375rem 1rem; }
    .follow-up-table td::before { content: attr(data-label); display: block; margin-bottom: .25rem; color: #64748b; font-size: 10px; font-weight: 600; text-transform: uppercase; }
    .row-action { min-height: 44px; }
}
</style>
