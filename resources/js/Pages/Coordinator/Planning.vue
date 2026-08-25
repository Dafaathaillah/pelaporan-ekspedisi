<script setup>
import AppShell from "@/Components/AppShell.vue";
import { router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
const props = defineProps({
    items: Array,
    dates: Array,
    range: Object,
    filters: Object,
});
const filter = reactive({
    search: "",
    type: "",
    status: "",
    sort: "submitted_at",
    direction: "desc",
    ...props.filters,
});
const dragging = ref(null),
    selected = ref(null),
    evidenceInput = ref(null),
    evidenceItem = ref(null);
const backlog = computed(() => props.items.filter((x) => !x.planned_date));
const normalizedDate = (value) => (value ? String(value).slice(0, 10) : null);
const byDate = (date) =>
    props.items.filter((x) => normalizedDate(x.planned_date) === date);
const load = () =>
    router.get("/coordinator/planning", filter, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const move = (id, date) =>
    router.patch(
        `/coordinator/planning/${id}`,
        { planned_date: date },
        { preserveScroll: true },
    );
const drop = (date) => {
    if (dragging.value) move(dragging.value, date);
    dragging.value = null;
};
const placeSelected = (date) => {
    if (selected.value) {
        move(selected.value, date);
        selected.value = null;
    }
};
const dateLabel = (value) =>
    new Date(value + "T00:00:00").toLocaleDateString("id-ID", {
        weekday: "short",
        day: "2-digit",
        month: "short",
    });
const typeLabel = (value) =>
    ({
        VISITOR: "Visitor",
        WASKAT: "Waskat",
        PERMIT_VENDOR_NON_SIMPER: "Permit Non Simper",
        IJIN_LINTAS: "Ijin Lintas",
    })[value] || value;
const subjects = (item) =>
    (item.submission.units || [])
        .map((unit) =>
            unit.subject_type === "UNIT" ? unit.plate_number : unit.person_name,
        )
        .filter(Boolean)
        .join(", ");
const uploadEvidence = (item) => {
    evidenceItem.value = item;
    evidenceInput.value?.click();
};
const sendEvidence = (event) => {
    const file = event.target.files?.[0];
    if (!file || !evidenceItem.value) return;
    const form = useForm({ evidence: file });
    form.post(`/coordinator/planning/${evidenceItem.value.id}/evidence`, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            event.target.value = "";
            evidenceItem.value = null;
        },
    });
};
</script>
<template>
    <AppShell
        title="Planning Escort"
        subtitle="Susun agenda sarana satu bulan ke depan"
        eyebrow="Koordinator Sarana · Planning"
    >
        <template #hero-title>Planning Escort Sarana Logistik</template
        ><template #hero-copy
            >Tarik kartu pengajuan ke tanggal tujuan. Periode aktif
            {{ dateLabel(range.start) }} sampai
            {{ dateLabel(range.end) }}.</template
        >
        <form class="panel mb-5" @submit.prevent="load">
            <div
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_220px_190px_190px_160px_auto]"
            >
                <input
                    v-model="filter.search"
                    class="control"
                    placeholder="Cari vendor, PIC, nomor pengajuan..."
                /><select v-model="filter.type" class="control">
                    <option value="">Semua pengajuan</option>
                    <option value="VISITOR">Visitor</option>
                    <option value="WASKAT">Waskat</option>
                    <option value="PERMIT_VENDOR_NON_SIMPER">
                        Permit Non Simper
                    </option>
                    <option value="IJIN_LINTAS">Ijin Lintas</option></select
                ><select v-model="filter.status" class="control">
                    <option value="">Semua status</option>
                    <option value="SUBMITTED">Diajukan</option>
                    <option value="APPROVED">Disetujui</option>
                    <option value="RELEASED">Released</option></select
                ><select v-model="filter.sort" class="control">
                    <option value="submitted_at">Waktu pengajuan</option>
                    <option value="vendor_name">Nama vendor</option>
                    <option value="type">Jenis pengajuan</option>
                    <option value="status">Status</option></select
                ><select v-model="filter.direction" class="control">
                    <option value="asc">A–Z / Terlama</option>
                    <option value="desc">Z–A / Terbaru</option></select
                ><button class="btn">Terapkan</button>
            </div>
        </form>
        <section
            class="panel mb-5 border-dashed border-red-200 bg-red-50/30"
            @dragover.prevent
            @drop.prevent="drop(null)"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="panel-title">Belum Terjadwal</h2>
                    <p class="panel-copy">
                        Tarik kartu ke kalender. Pada layar sentuh, ketuk kartu
                        lalu ketuk tanggal.
                    </p>
                </div>
                <span
                    class="rounded-full bg-red-600 px-3 py-1 text-xs font-black text-white"
                    >{{ backlog.length }}</span
                >
            </div>
            <div class="mt-4 flex min-h-24 gap-3 overflow-x-auto pb-2">
                <article
                    v-for="item in backlog"
                    :key="item.id"
                    draggable="true"
                    class="w-64 shrink-0 cursor-grab rounded-2xl border bg-white p-4 shadow-sm active:cursor-grabbing"
                    :class="
                        selected === item.id
                            ? 'border-red-500 ring-4 ring-red-100'
                            : 'border-slate-200'
                    "
                    @dragstart="dragging = item.id"
                    @click="selected = selected === item.id ? null : item.id"
                >
                    <b class="block truncate">{{
                        item.submission.vendor_name
                    }}</b
                    ><span class="mt-1 block text-xs text-slate-500"
                        >{{ typeLabel(item.submission.type) }} ·
                        {{ item.submission.units_count }} orang/unit</span
                    ><span
                        class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black"
                        >{{
                            item.submission.planning_frequency ||
                            item.submission.status
                        }}</span
                    >
                </article>
                <p v-if="!backlog.length" class="m-auto text-sm text-slate-400">
                    Semua item sudah memiliki jadwal.
                </p>
            </div>
        </section>
        <section class="panel overflow-hidden p-0">
            <div class="border-b px-5 py-4">
                <h2 class="panel-title">Kalender Satu Bulan ke Depan</h2>
                <p class="panel-copy">
                    Drag & drop aktif · klik evidence untuk melampirkan
                    screenshot/file WhatsApp.
                </p>
            </div>
            <div class="overflow-x-auto p-3 sm:p-4">
                <div class="grid min-w-[1050px] grid-cols-7 gap-3 pb-2">
                    <article
                        v-for="date in dates"
                        :key="date"
                        class="min-w-0 overflow-hidden rounded-2xl border bg-slate-50/60 p-2 transition hover:border-red-300 hover:bg-red-50/30"
                        :class="
                            selected ? 'border-red-200' : 'border-slate-200'
                        "
                        @dragover.prevent
                        @drop.prevent="drop(date)"
                        @click.self="placeSelected(date)"
                    >
                        <header
                            class="mb-2 flex min-w-0 items-center justify-between gap-1 border-b border-slate-200 px-1 pb-2"
                            @click="placeSelected(date)"
                        >
                            <b class="min-w-0 text-xs">{{ dateLabel(date) }}</b
                            ><span class="shrink-0 text-[10px] text-slate-400"
                                >{{ byDate(date).length }} agenda</span
                            >
                        </header>
                        <div class="space-y-2">
                            <article
                                v-for="item in byDate(date)"
                                :key="item.id"
                                draggable="true"
                                class="min-w-0 cursor-grab overflow-hidden rounded-xl border bg-white p-3 shadow-sm active:cursor-grabbing"
                                :class="
                                    selected === item.id
                                        ? 'border-red-500 ring-2 ring-red-100'
                                        : 'border-red-100'
                                "
                                @dragstart="dragging = item.id"
                                @click.stop="
                                    selected =
                                        selected === item.id ? null : item.id
                                "
                            >
                                <div
                                    class="flex min-w-0 items-start justify-between gap-2"
                                >
                                    <b
                                        class="line-clamp-2 min-w-0 flex-1 whitespace-normal break-all text-xs leading-relaxed"
                                        >{{ item.submission.vendor_name }}</b
                                    ><span
                                        class="shrink-0 rounded bg-red-50 px-1.5 py-1 text-[9px] font-black text-red-600"
                                        >{{
                                            item.submission.units_count
                                        }}
                                        unit</span
                                    >
                                </div>
                                <p
                                    class="mt-1 line-clamp-3 max-w-full whitespace-normal break-all text-[10px] leading-4 text-slate-500"
                                >
                                    <span class="font-semibold">{{
                                        typeLabel(item.submission.type)
                                    }}</span>
                                    · {{ item.submission.submission_number }}
                                </p>
                                <p
                                    class="mt-1 line-clamp-3 max-w-full whitespace-normal break-all text-[10px] leading-4 text-slate-600"
                                >
                                    {{
                                        subjects(item) ||
                                        "Detail orang/unit belum tersedia"
                                    }}
                                </p>
                                <div class="mt-2">
                                    <p
                                        v-if="item.driver"
                                        class="mt-1 line-clamp-2 max-w-full break-all text-[10px] font-semibold text-slate-700"
                                    >
                                        {{ item.driver.name }} · Lambung
                                        {{ item.driver.hull_number }}
                                    </p>
                                    <div
                                        v-if="false"
                                        class="mt-1 text-[10px] leading-4 text-slate-600"
                                    >
                                        <p class="break-words">
                                            {{
                                                item.escort_close_location_name ||
                                                "Lokasi tersimpan"
                                            }}
                                        </p>
                                        <a
                                            :href="`https://www.google.com/maps?q=${item.escort_close_latitude},${item.escort_close_longitude}`"
                                            target="_blank"
                                            class="font-semibold text-blue-600"
                                            @click.stop
                                            >Buka Maps</a
                                        >
                                        <span> · </span
                                        ><a
                                            :href="`/coordinator/planning/${item.id}/escort-photo`"
                                            target="_blank"
                                            class="font-semibold text-emerald-700"
                                            @click.stop
                                            >Lihat bukti close</a
                                        >
                                    </div>
                                </div>
                                <div
                                    class="mt-3 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-2"
                                >
                                    <button
                                        type="button"
                                        class="max-w-full whitespace-normal break-words text-left text-[10px] font-black leading-4 text-red-600"
                                        @click.stop="uploadEvidence(item)"
                                    >
                                        {{
                                            item.evidence_path
                                                ? "Ganti Evidence"
                                                : "Tambah Evidence"
                                        }}</button
                                    ><a
                                        v-if="item.evidence_path"
                                        :href="`/coordinator/planning/${item.id}/evidence`"
                                        target="_blank"
                                        class="text-[10px] font-bold text-emerald-600"
                                        @click.stop
                                        >Lihat</a
                                    >
                                </div>
                            </article>
                        </div>
                    </article>
                </div>
            </div>
        </section>
        <input
            ref="evidenceInput"
            type="file"
            accept="image/*,.pdf"
            class="hidden"
            @change="sendEvidence"
        />
    </AppShell>
</template>
