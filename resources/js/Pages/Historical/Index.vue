<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import { router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({ items: Object, stats: Object, filters: Object });
const form = reactive({ ...props.filters });
const load = () => router.get("/historical-management", form, { preserveState: true, preserveScroll: true, replace: true });
const reset = () => {
    const today = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Makassar", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
    Object.assign(form, { search: "", date_from: today, date_to: today, document_type: "all", status: "all", direction: "desc", per_page: 10 });
    load();
};
const changePerPage = (value) => { form.per_page = value; load(); };
const photoUrl = (item, kind) => `/historical-management/${item.id}/photos/${kind}`;
const dateTime = (value) => value ? new Date(value).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short", hour12: false }) : "-";
const activityAt = (item) => item.escort_started_at || item.blood_pressure_photo_captured_at || item.arrived_at;
</script>

<template>
    <AppShell title="Manajemen Historikal" subtitle="Histori pemeriksaan dan evidence operasional per site" eyebrow="Kontrol Dokumen Operasional">
        <template #hero-title>Historikal Tensi, P5M, dan P2H</template>
        <template #hero-copy>Pantau evidence yang terupload hari ini atau telusuri kembali berdasarkan rentang tanggal.</template>

        <section class="mb-5 grid gap-3 sm:grid-cols-3">
            <article class="metric-card"><p>Foto Tensi</p><strong>{{ stats.blood_pressure }}</strong><span>Pada rentang terpilih</span></article>
            <article class="metric-card"><p>Foto P5M</p><strong>{{ stats.p5m }}</strong><span>Pada rentang terpilih</span></article>
            <article class="metric-card"><p>Foto P2H</p><strong>{{ stats.p2h }}</strong><span>Pada rentang terpilih</span></article>
        </section>

        <form class="panel mb-5" @submit.prevent="load">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="panel-title">Filter Historikal</h2><p class="panel-copy">Default menampilkan dokumen yang terupload hari ini.</p></div><button type="button" class="btn-ghost" @click="reset">Reset Hari Ini</button></div>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700">Pencarian<input v-model="form.search" class="field" placeholder="Plat, driver, perusahaan, nomor..." /></label>
                <label class="text-sm font-semibold text-slate-700">Tanggal mulai<input v-model="form.date_from" type="date" class="field" required /></label>
                <label class="text-sm font-semibold text-slate-700">Tanggal selesai<input v-model="form.date_to" type="date" class="field" required /></label>
                <label class="text-sm font-semibold text-slate-700">Jenis dokumen<select v-model="form.document_type" class="field"><option value="all">Semua dokumen</option><option value="blood-pressure">Tensi darah</option><option value="p5m">P5M</option><option value="p2h">P2H</option></select></label>
                <label class="text-sm font-semibold text-slate-700">Kelengkapan<select v-model="form.status" class="field"><option value="all">Semua status</option><option value="complete">Lengkap</option><option value="incomplete">Belum lengkap</option></select></label>
                <label class="text-sm font-semibold text-slate-700">Urutan tanggal<select v-model="form.direction" class="field"><option value="desc">Terbaru</option><option value="asc">Terlama</option></select></label>
                <div class="flex items-end"><button class="btn w-full">Terapkan Filter</button></div>
            </div>
        </form>

        <section class="panel overflow-hidden p-0">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="panel-title">Riwayat Evidence</h2><p class="panel-copy">{{ items.total }} laporan ditemukan</p></div>
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[1080px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Tanggal aktivitas</th><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Driver / Perusahaan</th><th class="px-5 py-3">Tensi</th><th class="px-5 py-3">Evidence</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items.data" :key="item.id" class="align-top hover:bg-slate-50/70">
                            <td class="px-5 py-4"><b class="block text-slate-900">{{ dateTime(activityAt(item)) }}</b><span class="mt-1 block text-xs text-slate-500">{{ item.report_number }}</span></td>
                            <td class="px-5 py-4"><b class="plate-badge">{{ item.vehicle_plate }}</b><span class="mt-2 block text-xs text-slate-500">{{ item.vehicle_type }}</span></td>
                            <td class="px-5 py-4"><b class="block text-slate-900">{{ item.driver_name }}</b><span class="mt-1 block text-xs text-slate-500">{{ item.expedition_company }}</span></td>
                            <td class="px-5 py-4"><b v-if="item.blood_pressure_systolic">{{ item.blood_pressure_systolic }}/{{ item.blood_pressure_diastolic }} mmHg</b><span v-else>-</span><span v-if="item.health_status_label" class="mt-1 block text-xs text-slate-500">{{ item.health_status_label }}</span></td>
                            <td class="px-5 py-4"><div class="flex flex-wrap gap-2"><a v-if="item.blood_pressure_photo_path" :href="photoUrl(item, 'blood-pressure')" target="_blank" class="btn-ghost text-xs">Tensi</a><a v-if="item.p5m_photo_path" :href="photoUrl(item, 'p5m')" target="_blank" class="btn-ghost text-xs">P5M</a><a v-if="item.p2h_photo_path" :href="photoUrl(item, 'p2h')" target="_blank" class="btn-ghost text-xs">P2H</a><span v-if="!item.blood_pressure_photo_path && !item.p5m_photo_path && !item.p2h_photo_path" class="text-xs text-slate-400">Belum ada evidence</span></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="grid gap-3 p-4 lg:hidden">
                <article v-for="item in items.data" :key="item.id" class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3"><div><b class="text-slate-900">{{ item.driver_name }}</b><p class="mt-1 text-xs text-slate-500">{{ dateTime(activityAt(item)) }}</p></div><b class="plate-badge">{{ item.vehicle_plate }}</b></div>
                    <div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm"><p>{{ item.expedition_company }} · {{ item.report_number }}</p><p class="mt-1 font-semibold">Tensi: {{ item.blood_pressure_systolic ? `${item.blood_pressure_systolic}/${item.blood_pressure_diastolic} mmHg` : '-' }}</p></div>
                    <div class="mt-3 grid grid-cols-3 gap-2"><a v-if="item.blood_pressure_photo_path" :href="photoUrl(item, 'blood-pressure')" target="_blank" class="btn-ghost text-center text-xs">Tensi</a><a v-if="item.p5m_photo_path" :href="photoUrl(item, 'p5m')" target="_blank" class="btn-ghost text-center text-xs">P5M</a><a v-if="item.p2h_photo_path" :href="photoUrl(item, 'p2h')" target="_blank" class="btn-ghost text-center text-xs">P2H</a></div>
                </article>
            </div>
            <div v-if="!items.data.length" class="p-12 text-center text-sm text-slate-500">Tidak ada evidence pada filter yang dipilih.</div>
            <DataTableFooter :paginator="items" :per-page="form.per_page" @update:per-page="changePerPage" />
        </section>
    </AppShell>
</template>
