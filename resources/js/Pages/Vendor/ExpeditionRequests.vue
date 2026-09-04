<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import SortButton from "@/Components/SortButton.vue";
import { expeditionVehicleLabel } from "@/Utils/expeditionRequest";
import { router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({ items: Object, filters: Object });
const query = reactive({
    search: props.filters.search || "",
    status: props.filters.status || "",
    sort: props.filters.sort || "created_at",
    direction: props.filters.direction || "desc",
    per_page: Number(props.filters.per_page || 10),
});
const statusOptions = [
    { value: "", label: "Semua status" },
    { value: "OPEN", label: "Menunggu Diproses" },
    { value: "USED", label: "Sudah Diproses" },
    { value: "CLOSED", label: "Selesai" },
];
const statusLabel = (status) =>
    ({ OPEN: "Menunggu Diproses", USED: "Sudah Diproses", CLOSED: "Selesai" })[
        status
    ] || status;
const load = () =>
    router.get("/vendor/expedition-requests", query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const sort = (column) => {
    query.direction =
        query.sort === column && query.direction === "asc" ? "desc" : "asc";
    query.sort = column;
    load();
};
const date = (value) =>
    new Date(value).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    });
</script>

<template>
    <AppShell
        title="Incoming Request"
        subtitle="Pantau request masuk yang ditujukan khusus untuk perusahaan Anda."
        eyebrow="Akun Vendor"
    >
        <section class="panel overflow-hidden p-0">
            <div class="border-b border-slate-100 p-5">
                <div
                    class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"
                >
                    <div>
                        <h2 class="panel-title">
                            Incoming Request untuk Perusahaan Saya
                        </h2>
                        <p class="panel-copy">
                            Data otomatis dibatasi berdasarkan perusahaan pada
                            akun login.
                        </p>
                    </div>
                    <span class="text-xs font-bold text-slate-400"
                        >{{ items.total }} request</span
                    >
                </div>
                <form
                    class="mt-4 grid gap-3 sm:grid-cols-[1fr_220px_auto]"
                    @submit.prevent="load"
                >
                    <input
                        v-model="query.search"
                        class="control"
                        placeholder="Cari nomor request, kendaraan, atau rute..."
                    />
                    <SearchableSelect
                        v-model="query.status"
                        :options="statusOptions"
                        placeholder="Semua status"
                    />
                    <button class="btn">Terapkan</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[1180px]">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>
                                <SortButton
                                    label="Nomor Request"
                                    column="request_number"
                                    :sort="query.sort"
                                    :direction="query.direction"
                                    @sort="sort"
                                />
                            </th>
                            <th>Jenis Kendaraan</th>
                            <th>PR</th>
                            <th>PO</th>
                            <th>Rute</th>
                            <th>Status</th>
                            <th>Pengajuan</th>
                            <th>Tanggal Planning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in items.data" :key="item.id">
                            <td>{{ items.from + index }}</td>
                            <td>
                                <b>{{ item.request_number }}</b
                                ><small>{{ date(item.created_at) }}</small>
                            </td>
                            <td>
                                <span
                                    class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-bold text-slate-700"
                                    >{{
                                        expeditionVehicleLabel(
                                            item.expedition_type,
                                        )
                                    }}</span
                                >
                            </td>
                            <td class="table-wrap-cell">
                                <b>{{ item.pr_number || "Belum rilis" }}</b>
                            </td>
                            <td class="table-wrap-cell">
                                <b>{{ item.po_number || "Belum rilis" }}</b>
                            </td>
                            <td class="table-wrap-cell">
                                <b>{{ item.route_from }}</b
                                ><span class="mx-2 text-red-500">→</span
                                ><b>{{ item.route_to }}</b
                                ><small v-if="item.description">{{
                                    item.description
                                }}</small>
                            </td>
                            <td>
                                <span
                                    class="status-badge"
                                    :class="
                                        item.status === 'OPEN'
                                            ? 'status-revision'
                                            : 'status-approved'
                                    "
                                    >{{ statusLabel(item.status) }}</span
                                >
                            </td>
                            <td>
                                <b>{{ item.submissions_count }}</b
                                ><small>pengajuan Ijin Lintas</small>
                            </td>
                            <td class="table-wrap-cell">
                                <div
                                    class="flex max-w-[240px] flex-wrap gap-1.5"
                                >
                                    <template
                                        v-for="submission in item.submissions"
                                        :key="submission.id"
                                    >
                                        <span
                                            v-for="planning in submission.planning_items.filter(
                                                (x) => x.planned_date,
                                            )"
                                            :key="planning.id"
                                            class="rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"
                                            >{{
                                                formatDate(
                                                    planning.planned_date,
                                                )
                                            }}</span
                                        >
                                    </template>
                                    <span
                                        v-if="
                                            !item.submissions.some(
                                                (submission) =>
                                                    submission.planning_items.some(
                                                        (planning) =>
                                                            planning.planned_date,
                                                    ),
                                            )
                                        "
                                        class="text-xs font-semibold text-amber-700"
                                        >Belum dijadwalkan</span
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.data.length">
                            <td
                                colspan="9"
                                class="py-14 text-center text-slate-500"
                            >
                                Belum ada request ekspedisi untuk perusahaan
                                Anda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <DataTableFooter
                :paginator="items"
                :per-page="query.per_page"
                @update:per-page="
                    (value) => {
                        query.per_page = value;
                        load();
                    }
                "
            />
        </section>
    </AppShell>
</template>
