<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import { router } from "@inertiajs/vue3";
import { computed, reactive } from "vue";

const props = defineProps({ items: Object, filters: Object });
const query = reactive({
    search: props.filters.search || "",
    per_page: Number(props.filters.per_page || 10),
});
const drafts = reactive({});
const cards = computed(() => props.items.data.flatMap(submission => submission.planning_items.map(planning => ({ ...planning, submission }))));
const draft = (item) => {
    if (!(item.id in drafts)) drafts[item.id] = item.planned_date?.slice(0, 10) || "";
};
const setDraft = (item, value) => (drafts[item.id] = value);
const load = () =>
    router.get("/expedition/planning", query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const save = (item) =>
    router.patch(
        `/expedition/planning/${item.submission.id}`,
        { planning_item_id: item.id, planned_date: drafts[item.id] },
        { preserveScroll: true },
    );
const formatDate = (value) =>
    value
        ? new Date(`${value}T00:00:00`).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          })
        : "-";
</script>

<template>
    <AppShell
        title="Planning Izin Lintas"
        subtitle="Tentukan tanggal operasional untuk Izin Lintas yang sudah Released."
        eyebrow="Akun Ekspedisi"
    >
        <section class="panel overflow-hidden p-0">
            <div class="border-b border-slate-100 p-5">
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"
                >
                    <div>
                        <h2 class="panel-title">
                            Izin Lintas Siap Dijadwalkan
                        </h2>
                        <p class="panel-copy">
                            Tanggal tersinkron otomatis ke kalender koordinator
                            dan akun vendor.
                        </p>
                    </div>
                    <span class="text-xs font-bold text-slate-400"
                        >{{ items.total }} data Released</span
                    >
                </div>
                <form class="mt-4 flex gap-3" @submit.prevent="load">
                    <input
                        v-model="query.search"
                        class="control min-w-0 flex-1"
                        placeholder="Cari vendor, nomor pengajuan, atau nomor request..."
                    />
                    <button class="btn">Cari</button>
                </form>
            </div>

            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="item in cards"
                    :key="item.id"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="status-badge status-approved"
                                >Released</span
                            >
                            <h3
                                class="mt-2 break-words font-bold text-slate-900"
                            >
                                {{ item.submission.vendor_name }}
                            </h3>
                            <p class="mt-1 break-all text-xs text-slate-500">
                                {{ item.submission.submission_number }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-bold text-white"
                        >
                            1 unit
                        </span>
                    </div>

                    <dl
                        class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-xs"
                    >
                        <div>
                            <dt class="text-slate-500">Nomor Request</dt>
                            <dd class="mt-1 break-all font-bold">
                                {{
                                    item.submission.expedition_request?.request_number ||
                                    "-"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">PR / PO</dt>
                            <dd class="mt-1 break-words font-bold">
                                {{
                                    item.submission.expedition_request?.pr_number ||
                                    "Belum rilis"
                                }}
                                /
                                {{
                                    item.submission.expedition_request?.po_number ||
                                    "Belum rilis"
                                }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Nomor Polisi</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                <span
                                    class="rounded-md bg-white px-2 py-1 font-bold text-slate-800"
                                    >{{ item.unit?.plate_number || "Unit legacy" }} · {{ item.unit?.vehicle_type || "-" }}</span
                                >
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Planning saat ini</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                <span
                                    v-if="item.planned_date"
                                    class="rounded-md bg-red-50 px-2 py-1 font-semibold text-red-700"
                                    >{{ formatDate(item.planned_date.slice(0, 10)) }}</span
                                >
                                <span
                                    v-if="!item.planned_date"
                                    class="font-semibold text-amber-700"
                                >
                                    Belum dijadwalkan
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <form class="mt-4" @submit.prevent="save(item)">
                        <label
                            class="mb-1.5 block text-xs font-bold text-slate-700"
                            >Tanggal planning utama</label
                        >
                        <div class="flex gap-2">
                            <input
                                type="date"
                                class="control min-w-0 flex-1"
                                :value="draft(item)"
                                :min="new Date().toISOString().slice(0, 10)"
                                :max="item.submission.release_expired_at?.slice(0, 10)"
                                required
                                @input="setDraft(item, $event.target.value)"
                            />
                            <button class="btn">Simpan</button>
                        </div>
                        <p class="mt-2 text-[11px] text-slate-500">
                            Berlaku sampai
                            {{
                                formatDate(
                                    item.submission.release_expired_at?.slice(0, 10),
                                )
                            }}.
                        </p>
                    </form>
                </article>
                <div
                    v-if="!items.data.length"
                    class="col-span-full py-14 text-center text-slate-500"
                >
                    Belum ada Izin Lintas Released yang dapat dijadwalkan.
                </div>
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
