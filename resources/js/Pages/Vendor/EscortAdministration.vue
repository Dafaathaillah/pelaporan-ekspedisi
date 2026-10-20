<script setup>
import AppShell from '@/Components/AppShell.vue';
import DataTableFooter from '@/Components/DataTableFooter.vue';
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ reports: Object });
const forms = reactive({});
const formFor = (id, kind) => (forms[`${id}-${kind}`] ||= useForm({ scan: null }));
const upload = (report, kind) => formFor(report.id, kind).post(`/vendor/escort-administration/${report.id}/${kind}`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => formFor(report.id, kind).reset(),
});
</script>

<template>
    <AppShell title="Administrasi Escort" subtitle="Unggah scan invoice dan SPB setelah escort selesai." eyebrow="Akun Vendor">
        <section class="panel overflow-hidden p-0">
            <div class="border-b border-slate-100 p-5"><h2 class="panel-title">Dokumen Pasca-Escort</h2><p class="panel-copy">Administrasi belum close sampai invoice dan SPB lengkap. Isi invoice hanya dapat dibuka tim finance.</p></div>
            <div class="overflow-x-auto"><table class="data-table min-w-[950px]"><thead><tr><th>Laporan</th><th>Unit</th><th>Escort selesai</th><th>Status administrasi</th><th>Scan invoice</th><th>Scan SPB</th></tr></thead>
                <tbody><tr v-for="report in reports.data" :key="report.id">
                    <td><b>{{ report.report_number }}</b></td><td>{{ report.vehicle_plate }}</td><td>{{ report.escort_closed_at ? new Date(report.escort_closed_at).toLocaleString('id-ID') : '-' }}</td>
                    <td><span class="status-badge" :class="report.administration_status === 'CLOSE' ? 'status-approved' : 'status-revision'">{{ report.administration_status === 'CLOSE' ? 'Close' : 'Belum Close' }}</span></td>
                    <td><span class="block text-xs">{{ report.invoice_scan_path ? 'Sudah diunggah' : 'Belum diunggah' }}</span><input class="mt-2 max-w-[170px] text-xs" type="file" accept="application/pdf" @change="formFor(report.id, 'invoice').scan = $event.target.files[0]"/><button class="btn mt-2" type="button" :disabled="!formFor(report.id, 'invoice').scan || formFor(report.id, 'invoice').processing" @click="upload(report, 'invoice')">Unggah invoice</button><small v-if="formFor(report.id, 'invoice').errors.scan" class="text-red-600">{{ formFor(report.id, 'invoice').errors.scan }}</small></td>
                    <td><a v-if="report.spb_scan_path" :href="`/escort-administration/${report.id}/spb`" class="text-red-700 underline">Lihat SPB</a><span v-else class="block text-xs">Belum diunggah</span><input class="mt-2 max-w-[170px] text-xs" type="file" accept="application/pdf" @change="formFor(report.id, 'spb').scan = $event.target.files[0]"/><button class="btn mt-2" type="button" :disabled="!formFor(report.id, 'spb').scan || formFor(report.id, 'spb').processing" @click="upload(report, 'spb')">Unggah SPB</button><small v-if="formFor(report.id, 'spb').errors.scan" class="text-red-600">{{ formFor(report.id, 'spb').errors.scan }}</small></td>
                </tr><tr v-if="!reports.data.length"><td colspan="6" class="py-12 text-center text-slate-500">Belum ada escort selesai.</td></tr></tbody>
            </table></div><DataTableFooter :paginator="reports" :per-page="10" />
        </section>
    </AppShell>
</template>
