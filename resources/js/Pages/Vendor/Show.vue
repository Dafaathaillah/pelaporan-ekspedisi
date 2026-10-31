<script setup>
import LazyJourneyDetails from '@/Components/LazyJourneyDetails.vue';
import AppShell from '@/Components/AppShell.vue';
import P2hJourney from '@/Components/P2hJourney.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
const props = defineProps({ submission: Object });
const forms = reactive({});
const formFor = unit => forms[unit.id] ||= useForm({ driver_name: unit.driver_name || '', driver_phone: unit.driver_phone || '', vehicle_type: unit.vehicle_type || '' });
const save = unit => formFor(unit).patch(`/vendor/units/${unit.id}/details`, { preserveScroll: true });
const reviewForms = reactive({});
const reviewFor = unit => reviewForms[unit.id] ||= useForm({ status: '', reason: '' });
const review = (unit, status) => { const form = reviewFor(unit); form.status = status; form.patch(`/vendor/departures/${unit.id}/review`, { preserveScroll: true }); };
</script>
<template>
    <AppShell title="Detail Pengajuan" :subtitle="submission.submission_number" eyebrow="Akun Vendor">
        <div class="mb-4 flex flex-wrap justify-between gap-3"><Link href="/vendor" class="btn-ghost">Kembali</Link><a :href="`/submissions/${submission.id}/documents/download-all`" class="btn">Download Semua Dokumen</a></div>
        <section class="panel">
            <h2 class="text-2xl font-black">{{ submission.vendor_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ submission.applicant_name }} &middot; {{ submission.phone }}</p>
            <span class="status-badge mt-4" :class="'status-' + submission.status.toLowerCase()">{{ submission.status }}</span>
            <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{{ submission.coordinator_note || 'Belum ada catatan koordinator.' }}</p>
            <p class="mt-4 text-sm">Tujuan: {{ submission.destination || '-' }}</p>
            <div class="mt-4 space-y-4">
                <article v-for="unit in submission.units" :key="unit.id" class="rounded-xl border p-4">
                    <h3 class="font-semibold">{{ unit.plate_number || unit.person_name }}</h3>
                    <form v-if="unit.subject_type === 'UNIT' && !unit.latest_departure_p2h && !['CLOSED', 'REJECTED'].includes(submission.status)" class="mt-3 grid gap-3 sm:grid-cols-3" @submit.prevent="save(unit)">
                        <label class="text-sm font-semibold">Nama Driver<input v-model="formFor(unit).driver_name" class="field" required maxlength="150" /><p class="err">{{ formFor(unit).errors.driver_name }}</p></label>
                        <label class="text-sm font-semibold">Nomor HP Driver<input v-model="formFor(unit).driver_phone" class="field" required maxlength="30" /><p class="err">{{ formFor(unit).errors.driver_phone }}</p></label>
                        <label class="text-sm font-semibold">Tipe Kendaraan<select v-model="formFor(unit).vehicle_type" class="field" required><option value="">Pilih tipe</option><option v-for="type in ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya']" :key="type">{{ type }}</option></select><p class="err">{{ formFor(unit).errors.vehicle_type }}</p></label>
                        <button class="btn-ghost sm:col-span-3 sm:justify-self-start" :disabled="formFor(unit).processing">Simpan Detail Driver &amp; Unit</button>
                    </form>
                    <p v-else-if="unit.subject_type === 'UNIT'" class="mt-2 text-sm text-slate-600">{{ unit.driver_name || '-' }} &middot; {{ unit.driver_phone || '-' }} &middot; {{ unit.vehicle_type || '-' }}</p>
                    <div v-if="unit.latest_departure_p2h" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                        <b>P2H Keberangkatan · Revisi {{ unit.latest_departure_p2h.revision }}</b>
                        <p class="mt-1">Status: {{ unit.latest_departure_p2h.departed_at ? 'Sudah Berangkat' : unit.latest_departure_p2h.status }}</p>
                        <p>Hasil pemeriksaan: {{ unit.latest_departure_p2h.decision === 'LAIK' ? 'Laik' : 'Tidak Laik' }}</p>
                        <P2hJourney :p2h="unit.latest_departure_p2h" :arrived-at="unit.latest_arrival_report?.arrived_at" />
                        <p v-if="unit.latest_departure_p2h.rejection_reason" class="mt-1 text-red-700">Alasan penolakan: {{ unit.latest_departure_p2h.rejection_reason }}</p>
                        <a :href="`/departure-p2h/${unit.id}/download`" class="mt-2 inline-block font-semibold text-red-700 underline">Download hasil P2H</a>
                        <div v-if="unit.latest_departure_p2h.status === 'PENDING'" class="mt-3 space-y-2"><label class="block font-semibold">Alasan jika ditolak<textarea v-model="reviewFor(unit).reason" class="field" maxlength="1000" placeholder="Jelaskan perbaikan yang diperlukan"></textarea></label><p class="err">{{ reviewFor(unit).errors.reason || reviewFor(unit).errors.status }}</p><div class="flex flex-wrap gap-2"><button type="button" class="btn" :disabled="reviewFor(unit).processing" @click="review(unit, 'APPROVED')">Approve P2H</button><button type="button" class="btn-ghost" :disabled="reviewFor(unit).processing || !reviewFor(unit).reason.trim()" @click="review(unit, 'REJECTED')">Reject P2H</button></div></div>
                        <LazyJourneyDetails v-if="unit.departure_p2hs?.length > 1" :title="`Riwayat revisi (${unit.departure_p2hs.length})`" class="mt-3"><p v-for="version in unit.departure_p2hs" :key="version.id" class="mt-1">Revisi {{ version.revision }} · {{ version.status }} · {{ version.rejection_reason || '-' }}</p></LazyJourneyDetails>
                    </div>
                </article>
            </div>
            <div class="mt-6 grid gap-3 md:grid-cols-2"><a v-for="document in submission.documents" :key="document.id" :href="`/documents/${document.id}`" target="_blank" class="rounded-xl border border-slate-200 p-4 transition hover:border-red-200 hover:bg-red-50"><b>{{ document.document_name }}</b><p class="mt-1 text-xs text-slate-500">{{ (document.file_size / 1048576).toFixed(2) }} MB &middot; Lihat PDF</p></a></div>
        </section>
    </AppShell>
</template>
