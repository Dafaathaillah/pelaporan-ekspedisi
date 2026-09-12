<script setup>
import AppShell from "@/Components/AppShell.vue";
import ActionConfirm from "@/Components/ActionConfirm.vue";
import DirectPlanningModal from "@/Components/DirectPlanningModal.vue";
import { router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
const props = defineProps({
    items: Array,
    dates: Array,
    range: Object,
    filters: Object,
    planningBase: { type: String, default: "/coordinator/planning" },
    pageRole: { type: String, default: "coordinator" },
    directItems: { type: Array, default: () => [] },
    directPlannings: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
    vehicleTypes: { type: Array, default: () => [] },
    directPlanningBase: { type: String, default: "/coordinator/direct-plannings" },
    internalItems: { type: Array, default: () => [] },
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
    evidenceItem = ref(null),
    directModalOpen = ref(false),
    editingDirect = ref(null),
    deletingDirect = ref(null);
const directDragging = ref(null), directSelected = ref(null), internalDragging = ref(null), internalSelected = ref(null);
const backlog = computed(() => props.items.filter((x) => !x.planned_date));
const normalizedDate = (value) => (value ? String(value).slice(0, 10) : null);
const byDate = (date) =>
    props.items.filter((x) => normalizedDate(x.planned_date) === date);
const directByDate = (date) => props.directItems.filter((x) => normalizedDate(x.planned_date) === date);
const internalByDate = (date) => props.internalItems.filter((x) => normalizedDate(x.escort_date) === date);
const internalBase = computed(() => props.planningBase.replace('/planning', '/internal-ijin-lintas'));
const moveInternal = (id, date) => router.patch(`${internalBase.value}/${id}/move`, { escort_date: date }, { preserveScroll: true });
const openDirect = (planning = null) => {
    editingDirect.value = planning;
    directModalOpen.value = true;
};
const closeDirect = () => {
    directModalOpen.value = false;
    editingDirect.value = null;
};
const directPlanning = (id) => props.directPlannings.find((item) => item.id === id);
const toggleDirectLock = (item) => router.patch(`${props.directPlanningBase}/${item.direct_planning_id}/lock`, { locked: !item.is_locked }, { preserveScroll: true });
const deleteDirect = () => {
    if (!deletingDirect.value) return;
    router.delete(`${props.directPlanningBase}/${deletingDirect.value.id}`, { preserveScroll: true, onFinish: () => (deletingDirect.value = null) });
};
const load = () =>
    router.get(props.planningBase, filter, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const move = (id, date) =>
    router.patch(
        `${props.planningBase}/${id}`,
        { planned_date: date },
        { preserveScroll: true },
    );
const drop = (date) => {
    if (internalDragging.value) moveInternal(internalDragging.value, date);
    else if (directDragging.value) router.patch(`${props.directPlanningBase.replace('/direct-plannings', '/direct-planning-units')}/${directDragging.value}/move`, { planned_date: date }, { preserveScroll: true });
    else if (dragging.value) move(dragging.value, date);
    dragging.value = null;
    directDragging.value = null;
    internalDragging.value = null;
};
const placeSelected = (date) => {
    if (internalSelected.value) {
        moveInternal(internalSelected.value, date);
        internalSelected.value = null;
    } else if (directSelected.value) {
        router.patch(`${props.directPlanningBase.replace('/direct-plannings', '/direct-planning-units')}/${directSelected.value}/move`, { planned_date: date }, { preserveScroll: true });
        directSelected.value = null;
    } else if (selected.value) {
        move(selected.value, date);
        selected.value = null;
    }
};
const toggleLock = (item) =>
    router.patch(
        `${props.planningBase}/${item.id}/lock`,
        { locked: !item.is_locked },
        { preserveScroll: true },
    );
const submittedTime = (value) => value ? new Date(value).toLocaleString("id-ID", { timeZone: "Asia/Makassar", dateStyle: "medium", timeStyle: "short" }) + " WITA" : "Belum diajukan";
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
    (item.unit ? [item.unit] : (item.submission.units || []))
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
        :title="pageRole === 'purchasing_admin' ? 'Planning Lintas' : 'Planning Escort'"
        subtitle="Susun agenda sarana satu bulan ke depan"
        :eyebrow="pageRole === 'purchasing_admin' ? 'Admin Purchasing · Planning' : 'Koordinator Sarana · Planning'"
    >
        <template #hero-title>Planning Escort Sarana Logistik</template
        ><template #hero-copy
            >Tarik kartu pengajuan ke tanggal tujuan. Periode aktif
            {{ dateLabel(range.start) }} sampai
            {{ dateLabel(range.end) }}.</template
        >
        <div class="mb-5 flex justify-end">
            <button type="button" class="btn" @click="openDirect()">Buat Planning Langsung</button>
        </div>
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
                    :draggable="!item.is_locked"
                    class="w-64 shrink-0 rounded-2xl border bg-white p-4 shadow-sm"
                    :class="[
                        item.is_locked ? 'cursor-not-allowed opacity-75' : 'cursor-grab active:cursor-grabbing',
                        selected === item.id ? 'border-red-500 ring-4 ring-red-100' : 'border-slate-200',
                    ]"
                    @dragstart="!item.is_locked && (dragging = item.id)"
                    @click="!item.is_locked && (selected = selected === item.id ? null : item.id)"
                >
                    <p class="mb-2 text-xs text-slate-500">Diajukan {{ submittedTime(item.submission.submitted_at) }}</p><b class="block truncate">{{
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
                                >{{ byDate(date).length + directByDate(date).length + internalByDate(date).length }} agenda</span
                            >
                        </header>
                        <div class="space-y-2">
                            <article
                                v-for="item in byDate(date)"
                                :key="item.id"
                                :draggable="!item.is_locked"
                                class="min-w-0 overflow-hidden rounded-xl border bg-white p-3 shadow-sm"
                                :class="[
                                    item.is_locked ? 'cursor-not-allowed border-amber-200 bg-amber-50/40' : 'cursor-grab border-red-100 active:cursor-grabbing',
                                    selected === item.id ? 'border-red-500 ring-2 ring-red-100' : '',
                                ]"
                                @dragstart="!item.is_locked && (dragging = item.id)"
                                @click.stop="
                                    !item.is_locked && (selected = selected === item.id ? null : item.id)
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
                                            item.unit ? 1 : item.submission.units_count
                                        }}
                                        unit</span
                                    >
                                </div>
                                <p class="mt-2 text-[10px] text-slate-500">Diajukan {{ submittedTime(item.submission.submitted_at) }}</p><p v-if="item.reschedule_reason" class="mt-2 break-words text-xs text-amber-800">Alasan reschedule: {{ item.reschedule_reason }}</p><div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="text-[10px] font-black" :class="item.is_locked ? 'text-amber-700' : 'text-emerald-700'">
                                        {{ item.is_locked ? "Terkunci" : "Dapat dipindah" }}
                                    </span>
                                    <button type="button" class="text-[10px] font-black text-red-700" @click.stop="toggleLock(item)">
                                        {{ item.is_locked ? "Buka Kunci" : "Kunci" }}
                                    </button>
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
                                        v-if="pageRole !== 'purchasing_admin'"
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
                                        v-if="pageRole !== 'purchasing_admin' && item.evidence_path"
                                        :href="`/coordinator/planning/${item.id}/evidence`"
                                        target="_blank"
                                        class="text-[10px] font-bold text-emerald-600"
                                        @click.stop
                                        >Lihat</a
                                    >
                                </div>
                            </article>
                            <article
                                v-for="item in internalByDate(date)"
                                :key="`internal-${item.id}`"
                                draggable="true"
                                class="min-w-0 overflow-hidden rounded-xl border border-violet-200 bg-violet-50/60 p-3 shadow-sm cursor-grab active:cursor-grabbing"
                                :class="internalSelected === item.id ? 'ring-2 ring-violet-300' : ''"
                                @dragstart="internalDragging = item.id"
                                @click.stop="internalSelected = internalSelected === item.id ? null : item.id"
                            >
                                <div class="flex items-start justify-between gap-2"><div class="min-w-0"><span class="inline-flex rounded-full bg-violet-700 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-white">Izin Lintas Internal</span><b class="mt-2 block break-words text-xs text-slate-900">{{ item.title }}</b></div><span class="shrink-0 text-[10px] font-black text-emerald-700">Aktif</span></div>
                                <p class="mt-2 text-xs font-bold text-slate-800">{{ item.unit_count }} unit</p>
                                <p class="mt-1 break-words text-[10px] leading-4 text-slate-600">{{ item.vehicle_plates.join(', ') }}</p>
                            </article>
                            <article
                                v-for="item in directByDate(date)"
                                :key="`direct-${item.id}`"
                                :draggable="!item.is_locked"
                                class="min-w-0 overflow-hidden rounded-xl border border-sky-200 bg-sky-50/50 p-3 shadow-sm"
                                :class="[!item.is_locked ? 'cursor-grab active:cursor-grabbing' : 'cursor-not-allowed', directSelected === item.id ? 'ring-2 ring-sky-300' : '']"
                                @dragstart="!item.is_locked && (directDragging = item.id)"
                                @click.stop="!item.is_locked && (directSelected = directSelected === item.id ? null : item.id)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="inline-flex rounded-full bg-sky-700 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-white">Planning Langsung</span>
                                        <b class="mt-2 block break-words text-xs text-slate-900">{{ item.vendor_name }}</b>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-black" :class="item.is_locked ? 'text-amber-700' : 'text-emerald-700'">{{ item.is_locked ? "Terkunci" : "Aktif" }}</span>
                                </div>
                                <p class="mt-2 text-xs font-bold text-slate-800">{{ item.vehicle_plate }} · {{ item.vehicle_type }}</p>
                                <p class="mt-1 break-words text-[10px] leading-4 text-slate-600">{{ item.route_from }} → {{ item.route_to }}</p>
                                <p v-if="item.notes" class="mt-1 line-clamp-2 break-words text-[10px] text-slate-500">{{ item.notes }}</p>
                                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-2 border-t border-sky-100 pt-2">
                                    <button type="button" class="text-[10px] font-black text-sky-700 disabled:cursor-not-allowed disabled:text-slate-300" :disabled="item.is_locked" @click.stop="openDirect(directPlanning(item.direct_planning_id))">Edit</button>
                                    <button type="button" class="text-[10px] font-black text-amber-700" @click.stop="toggleDirectLock(item)">{{ item.is_locked ? "Buka Kunci" : "Kunci" }}</button>
                                    <button type="button" class="text-[10px] font-black text-red-600 disabled:text-slate-300" :disabled="item.is_locked" @click.stop="deletingDirect = directPlanning(item.direct_planning_id)">Hapus</button>
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
        <DirectPlanningModal :show="directModalOpen" :planning="editingDirect" :vendors="vendors" :vehicle-types="vehicleTypes" :endpoint="directPlanningBase" :range="range" @close="closeDirect" />
        <ActionConfirm :show="Boolean(deletingDirect)" title="Hapus planning langsung?" message="Seluruh unit dalam kelompok planning ini akan dihapus dari kalender." confirm-text="Hapus Planning" danger @close="deletingDirect = null" @confirm="deleteDirect" />
    </AppShell>
</template>
