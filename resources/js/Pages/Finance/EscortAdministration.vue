<script setup>
import AppShell from '@/Components/AppShell.vue';
import DataTableFooter from '@/Components/DataTableFooter.vue';
defineProps({ reports: Object });
</script>

<template>
    <AppShell title="Administrasi Escort" subtitle="Pantau kelengkapan invoice dan SPB pasca-escort." eyebrow="Tim Finance">
        <section class="panel overflow-hidden p-0"><div class="border-b border-slate-100 p-5"><h2 class="panel-title">Invoice dan SPB</h2><p class="panel-copy">Hanya tim finance yang dapat membuka scan invoice.</p></div>
            <div class="overflow-x-auto"><table class="data-table min-w-[850px]"><thead><tr><th>Laporan</th><th>Vendor</th><th>Unit</th><th>Escort selesai</th><th>Status</th><th>Dokumen</th></tr></thead>
                <tbody><tr v-for="report in reports.data" :key="report.id"><td><b>{{ report.report_number }}</b></td><td>{{ report.sender_vendor }}</td><td>{{ report.vehicle_plate }}</td><td>{{ report.escort_closed_at ? new Date(report.escort_closed_at).toLocaleString('id-ID') : '-' }}</td><td><span class="status-badge" :class="report.administration_status === 'CLOSE' ? 'status-approved' : 'status-revision'">{{ report.administration_status === 'CLOSE' ? 'Close' : 'Belum Close' }}</span></td><td><a v-if="report.invoice_scan_path" :href="`/escort-administration/${report.id}/invoice`" class="mr-3 text-red-700 underline">Invoice</a><span v-else class="mr-3 text-slate-400">Invoice belum ada</span><a v-if="report.spb_scan_path" :href="`/escort-administration/${report.id}/spb`" class="text-red-700 underline">SPB</a><span v-else class="text-slate-400">SPB belum ada</span></td></tr><tr v-if="!reports.data.length"><td colspan="6" class="py-12 text-center text-slate-500">Belum ada escort selesai.</td></tr></tbody>
            </table></div><DataTableFooter :paginator="reports" :per-page="10" />
        </section>
    </AppShell>
</template>
