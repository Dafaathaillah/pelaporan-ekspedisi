<script setup>
import AppShell from '@/Components/AppShell.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
const props = defineProps({ submission: Object });
const forms = reactive({});
const formFor = unit => forms[unit.id] ||= useForm({ driver_name: unit.driver_name || '', driver_phone: unit.driver_phone || '', vehicle_type: unit.vehicle_type || '' });
const save = unit => formFor(unit).patch(`/vendor/units/${unit.id}/details`, { preserveScroll: true });
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
                    <form v-if="unit.subject_type === 'UNIT' && !['CLOSED', 'REJECTED'].includes(submission.status)" class="mt-3 grid gap-3 sm:grid-cols-3" @submit.prevent="save(unit)">
                        <label class="text-sm font-semibold">Nama Driver<input v-model="formFor(unit).driver_name" class="field" required maxlength="150" /><p class="err">{{ formFor(unit).errors.driver_name }}</p></label>
                        <label class="text-sm font-semibold">Nomor HP Driver<input v-model="formFor(unit).driver_phone" class="field" required maxlength="30" /><p class="err">{{ formFor(unit).errors.driver_phone }}</p></label>
                        <label class="text-sm font-semibold">Tipe Kendaraan<select v-model="formFor(unit).vehicle_type" class="field" required><option value="">Pilih tipe</option><option v-for="type in ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya']" :key="type">{{ type }}</option></select><p class="err">{{ formFor(unit).errors.vehicle_type }}</p></label>
                        <button class="btn-ghost sm:col-span-3 sm:justify-self-start" :disabled="formFor(unit).processing">Simpan Detail Driver &amp; Unit</button>
                    </form>
                    <p v-else-if="unit.subject_type === 'UNIT'" class="mt-2 text-sm text-slate-600">{{ unit.driver_name || '-' }} &middot; {{ unit.driver_phone || '-' }} &middot; {{ unit.vehicle_type || '-' }}</p>
                </article>
            </div>
            <div class="mt-6 grid gap-3 md:grid-cols-2"><a v-for="document in submission.documents" :key="document.id" :href="`/documents/${document.id}`" target="_blank" class="rounded-xl border border-slate-200 p-4 transition hover:border-red-200 hover:bg-red-50"><b>{{ document.document_name }}</b><p class="mt-1 text-xs text-slate-500">{{ (document.file_size / 1048576).toFixed(2) }} MB &middot; Lihat PDF</p></a></div>
        </section>
    </AppShell>
</template>
