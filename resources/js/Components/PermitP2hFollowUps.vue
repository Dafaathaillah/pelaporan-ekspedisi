<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import P2hJourney from '@/Components/P2hJourney.vue';
import { unitP2hStage } from '@/lib/p2hFollowUp';

const props = defineProps({ permits: Array, accounts: Array, canLink: Boolean, reviewBase: String });
const forms = reactive({});
const formFor = permit => forms[permit.id] ||= useForm({ expedition_user_id: '' });
const linkAccount = permit => formFor(permit).patch(`/coordinator/submission/${permit.id}/expedition-account`, { preserveScroll: true, onSuccess: () => formFor(permit).reset() });
const detailUrl = permit => props.reviewBase === '/purchasing' ? `/purchasing/vendor-submission/${permit.id}` : `/coordinator/submission/${permit.id}`;
const stages = {
    AWAITING_P2H: ['Menunggu P2H ekspedisi', 'Ekspedisi membuka P2H & Kedatangan, memilih izin ini, lalu klik Isi Form P2H.'],
    AWAITING_VENDOR: ['Menunggu approval vendor', 'Vendor membuka Pengajuan Vendor → Detail izin ini untuk Approve atau Reject P2H.'],
    NEEDS_REVISION: ['P2H perlu diperbaiki ekspedisi', 'Ekspedisi membuka izin ini, klik Perbaiki P2H, lalu kirim ulang ke vendor.'],
    AWAITING_DEPARTURE: ['P2H disetujui · Siap berangkat', 'Ekspedisi klik Konfirmasi Keberangkatan pada unit ini.'],
    IN_TRANSIT: ['Dalam perjalanan', 'Setelah tiba di Underpass, isi laporan kedatangan dan foto tensi sesuai alur yang tersedia.'],
    ARRIVED: ['Sudah tiba', 'Lanjutkan proses pengawalan pada monitoring kedatangan.'],
};
</script>

<template>
    <section class="panel my-5" aria-labelledby="p2h-follow-up-title">
        <h2 id="p2h-follow-up-title" class="panel-title">Tindak Lanjut Izin Released · P2H Ekspedisi</h2>
        <p class="panel-copy">Alur: izin Released → P2H oleh ekspedisi → approval vendor → konfirmasi berangkat → laporan kedatangan. Ditampilkan sesuai filter dan halaman daftar saat ini.</p>
        <p v-if="!permits.length" class="mt-4 text-sm text-slate-500">Tidak ada izin Released pada hasil daftar ini. Pilih status Released pada filter untuk memantau P2H.</p>
        <div class="mt-4 space-y-4">
            <article v-for="permit in permits" :key="permit.id" class="rounded-2xl border border-slate-200 p-4 sm:p-5">
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0"><h3 class="break-words font-black text-slate-900">{{ permit.vendor_name }}</h3><p class="mt-1 break-words text-xs text-slate-500">{{ permit.submission_number }}</p></div>
                    <Link :href="detailUrl(permit)" class="btn-ghost">Detail Izin &amp; P2H</Link>
                </header>
                <p v-if="permit.is_expired" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Masa berlaku izin belum diisi atau sudah berakhir. Reviewer perlu memeriksa detail izin sebelum ekspedisi dapat melanjutkan P2H.</p>
                <div v-if="!permit.company || !permit.company.is_active" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Akun vendor pemilik izin belum terhubung ke perusahaan aktif. Perbaiki perusahaan akun vendor melalui Manajemen Pengguna terlebih dahulu.</div>
                <div v-else-if="!permit.expedition_accounts.length" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-bold">Belum ada akun ekspedisi aktif yang terhubung ke {{ permit.company.name }} pada site ini.</p>
                    <p class="mt-1">Inilah penyebab izin tidak muncul di akun ekspedisi. Hubungkan akun yang benar, lalu muat ulang halaman P2H &amp; Kedatangan pada akun tersebut.</p>
                    <p v-if="canLink && !accounts.length" class="mt-2">Tidak ada akun ekspedisi aktif tanpa perusahaan pada site ini. Periksa atau buat akun melalui Manajemen Pengguna.</p>
                    <p v-if="!canLink" class="mt-2">Minta koordinator dengan akses Manajemen Pengguna menghubungkan akun ekspedisi.</p>
                </div>
                <p v-else class="mt-3 text-sm text-slate-600">Akun ekspedisi terkait: <b>{{ permit.expedition_accounts.map(account => account.name).join(', ') }}</b>. Akun ini mengisi P2H dari menu P2H &amp; Kedatangan.</p>
                <form v-if="canLink && permit.company?.is_active && accounts.length && !permit.is_expired" class="mt-3 rounded-xl bg-slate-50 p-3" @submit.prevent="linkAccount(permit)">
                    <p v-if="permit.expedition_accounts.length" class="mb-2 text-xs text-slate-600">Masih ada akun ekspedisi lama yang belum terhubung? Pilih akun yang benar untuk ditambahkan ke perusahaan ini.</p>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <label class="min-w-0 flex-1 text-xs font-bold">Pilih akun ekspedisi yang belum terhubung<select v-model="formFor(permit).expedition_user_id" class="field mt-1" required><option value="">Pilih akun yang sesuai dengan perusahaan ini</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }} ({{ account.username }})</option></select></label>
                        <button type="submit" class="btn shrink-0" :disabled="formFor(permit).processing || !formFor(permit).expedition_user_id">{{ formFor(permit).processing ? 'Menghubungkan...' : `Hubungkan ke ${permit.company.name}` }}</button>
                    </div>
                    <p v-if="forms[permit.id]?.errors.expedition_user_id" class="err mt-2">{{ forms[permit.id].errors.expedition_user_id }}</p>
                </form>
                <div class="mt-4 grid gap-3 xl:grid-cols-2">
                    <div v-for="unit in permit.units" :key="unit.id" class="rounded-xl border border-slate-200 p-3 text-sm">
                        <p class="font-bold text-slate-900">{{ unit.plate_number }} · {{ unit.driver_name || 'Driver belum diisi' }}</p>
                        <p class="mt-2 font-semibold" :class="unitP2hStage(unit.p2h, unit.arrived_at) === 'ARRIVED' ? 'text-emerald-700' : 'text-amber-800'">{{ stages[unitP2hStage(unit.p2h, unit.arrived_at)][0] }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ stages[unitP2hStage(unit.p2h, unit.arrived_at)][1] }}</p>
                        <p v-if="unit.p2h?.rejection_reason" class="mt-2 text-xs text-red-700">Alasan: {{ unit.p2h.rejection_reason }}</p>
                        <a v-if="unit.p2h" :href="`/departure-p2h/${unit.id}/download`" class="mt-2 inline-block text-xs font-bold text-red-700 underline">Download PDF P2H</a>
                        <P2hJourney :p2h="unit.p2h" :arrived-at="unit.arrived_at" />
                    </div>
                </div>
                <p v-if="!permit.units.length" class="mt-3 text-sm text-amber-800">Izin belum memiliki unit kendaraan. Periksa detail izin.</p>
            </article>
        </div>
    </section>
</template>
