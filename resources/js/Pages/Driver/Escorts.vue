<script setup>
import AppShell from "@/Components/AppShell.vue";
import EscortReadinessBadge from "@/Components/EscortReadinessBadge.vue";
import Modal from "@/Components/Modal.vue";
import P5mCameraCapture from "@/Components/P5mCameraCapture.vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { getPreciseLocation } from "@/lib/preciseLocation";
import { isEscortReady } from "@/lib/escortReadiness";
const props = defineProps({ items: Array });
const user = computed(() => usePage().props.auth.user),
    selectedClaim = ref(false),
    selectedIds = ref([]),
    closing = ref(null),
    locating = ref(false),
    locationError = ref("");
const form = useForm({
    latitude: "",
    longitude: "",
    accuracy: "",
    location_name: "",
    captured_at: "",
    escort_close_photo: null,
});
const siteToday = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Makassar", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
const now = ref(Date.now());
let readinessTimer;
onMounted(() => {
    readinessTimer = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});
onUnmounted(() => window.clearInterval(readinessTimer));
const p2hReady = (item) => !item.p2h_photo_path || item.p2h_status === "APPROVED";
const timeAndScheduleReady = (item) => item.internal_ijin_lintas
    ? item.internal_ijin_lintas.escort_date === siteToday
    : isEscortReady(item, now.value);
const canStart = (item) => p2hReady(item) && timeAndScheduleReady(item);
const availabilityLabel = (item) => {
    if (item.p2h_photo_path && item.p2h_status === "REJECTED") return "P2H ditolak · menunggu upload ulang";
    if (item.p2h_photo_path && item.p2h_status !== "APPROVED") return "Menunggu approval P2H";
    if (!timeAndScheduleReady(item)) return item.internal_ijin_lintas ? "Menunggu tanggal escort" : "Menunggu 7 jam";
    return "Siap di-escort";
};
const waiting = computed(() =>
    props.items.filter((x) => x.escort_status === "WAITING"),
);
const active = computed(() =>
    props.items.filter((x) => x.escort_status === "PROCESS"),
);
const selectedReports = computed(() =>
    waiting.value.filter((item) => selectedIds.value.includes(item.id)),
);
const activeBatches = computed(() =>
    Object.values(
        active.value.reduce((groups, item) => {
            const key = item.escort_batch_id || item.id;
            if (!groups[key]) groups[key] = { id: key, leader: item, members: [] };
            groups[key].members.push(item);
            return groups;
        }, {}),
    ),
);
const mine = computed(() =>
    props.items.filter(
        (x) =>
            x.escort_driver_id === user.value.id ||
            x.escort_closed_by === user.value.id,
    ),
);
const dateTime = (v) =>
    new Date(v).toLocaleString("id-ID", {
        weekday: "long",
        day: "2-digit",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    }) + " WITA";
const claimForm = useForm({ report_ids: [], p5m_photo: null });
const openClaim = () => {
    selectedIds.value = selectedReports.value.filter(canStart).map((item) => item.id);
    if (!selectedIds.value.length) return;
    claimForm.reset();
    claimForm.clearErrors();
    selectedClaim.value = true;
};
const closeClaim = () => {
    selectedClaim.value = false;
    claimForm.reset();
    claimForm.clearErrors();
};
const toggleSelection = (id) => {
    const item = waiting.value.find((candidate) => candidate.id === id);
    if (!item || !canStart(item)) return;
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((selectedId) => selectedId !== id)
        : [...selectedIds.value, id];
};
const setP5mPhoto = (file) => {
    claimForm.p5m_photo = file;
    if (file) claimForm.clearErrors("p5m_photo");
};
const setClosePhoto = (file) => {
    form.escort_close_photo = file;
    if (file) form.clearErrors("escort_close_photo");
};
const claim = () => {
    selectedIds.value = selectedReports.value.filter(canStart).map((item) => item.id);
    if (!selectedIds.value.length) return;
    claimForm.transform(data => ({ ...data, report_ids: selectedIds.value })).post('/driver/escort-batches', {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            closeClaim();
        },
    });
};
const openClose = (batch) => {
    closing.value = batch;
    locationError.value = "";
    form.reset();
    form.clearErrors();
    captureLocation();
};
const captureLocation = async () => {
    locating.value = true;
    locationError.value = "";
    try {
        const p = await getPreciseLocation();
        const { latitude, longitude, accuracy } = p.coords;
        Object.assign(form, {
            latitude: latitude.toFixed(7),
            longitude: longitude.toFixed(7),
            accuracy: Math.round(accuracy),
            captured_at: new Date().toISOString(),
        });
        const { data } = await axios.get("/driver/reverse-geocode", {
            params: { latitude, longitude },
        });
        form.location_name = data.display_name || "";
    } catch (error) {
        form.latitude = "";
        form.longitude = "";
        form.accuracy = "";
        locationError.value =
            error?.code === 1
                ? "Izin lokasi ditolak. Aktifkan GPS dan izin lokasi browser."
                : error?.message ||
                  "Koordinat belum diperoleh. Pastikan GPS aktif lalu coba kembali.";
    } finally {
        locating.value = false;
    }
};
const submitClose = () =>
    form.post(`/driver/escort-batches/${closing.value.leader.id}/close`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closing.value = null;
        },
    });
</script>

<template>
    <AppShell
        title="Escort Ekspedisi"
        subtitle="Proses kendaraan ekspedisi yang telah tiba"
        eyebrow="Operasional Driver"
    >
        <template #hero-title>Unit Ekspedisi untuk Di-escort</template
        ><template #hero-copy
            >Nomor lambung {{ user.hull_number }} · Hanya laporan kedatangan
            ekspedisi yang ditampilkan.</template
        >
        <section class="mb-5 grid gap-3 sm:grid-cols-3">
            <article class="metric-card">
                <p>Unit tersedia</p>
                <strong>{{ waiting.length }}</strong
                ><span>Kendaraan ekspedisi menunggu driver</span>
            </article>
            <article class="metric-card">
                <p>Sedang Di-escort</p>
                <strong>{{ active.length }}</strong
                ><span>Dapat ditutup oleh driver yang bertugas</span>
            </article>
            <article class="metric-card">
                <p>Escort Selesai</p>
                <strong>{{
                    mine.filter((x) => x.escort_status === "CLOSED").length
                }}</strong
                ><span>Lokasi dan petugas close tercatat</span>
            </article>
        </section>
        <section class="panel mb-5">
            <h2 class="panel-title">Escort Aktif Site BIB</h2>
            <p class="panel-copy">
                Driver yang memulai dan menutup escort boleh berbeda. Identitas
                keduanya tetap tercatat.
            </p>
            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                <article
                    v-for="batch in activeBatches"
                    :key="batch.id"
                    class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <span
                                class="status-badge bg-amber-100 text-amber-800"
                                >● SEDANG DI-ESCORT</span
                            >
                            <h3 class="mt-3 text-lg font-semibold">
                                Batch {{ batch.members.length }} Unit
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ batch.leader.expedition_company }} ·
                                {{ dateTime(batch.leader.escort_started_at) }}
                            </p>
                        </div>
                        <b class="plate-badge">{{ batch.members.length }} UNIT</b>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-xl bg-white/80 p-3 sm:col-span-2">
                            <dt class="text-xs text-slate-500">
                                Escort dimulai oleh
                            </dt>
                            <dd class="font-semibold">
                                {{ batch.leader.escort_driver?.name || "Driver" }} ·
                                Lambung
                                {{ batch.leader.escort_driver?.hull_number || "-" }}
                            </dd>
                            <p
                                v-if="batch.leader.escort_driver_id !== user.id"
                                class="mt-1 text-xs font-semibold text-amber-700"
                            >
                                Anda akan tercatat sebagai petugas penutup
                                escort.
                            </p>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">
                                Driver kendaraan
                            </dt>
                            <dd class="font-semibold">{{ batch.members.length }} unit</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">
                                Vendor / Pengirim
                            </dt>
                            <dd class="font-semibold">{{ batch.leader.sender_vendor }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Kendaraan</dt>
                            <dd class="font-semibold">{{ batch.leader.vehicle_type }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Tujuan</dt>
                            <dd class="font-semibold">{{ batch.leader.destination }}</dd>
                        </div>
                    </dl>
                    <ul class="mt-3 grid gap-2">
                        <li v-for="unit in batch.members" :key="unit.id" class="flex items-center justify-between gap-3 rounded-xl bg-white p-3">
                            <span class="min-w-0"><b class="block truncate text-sm">{{ unit.expedition_company }}</b><small class="block truncate text-slate-500">{{ unit.report_number }} · {{ unit.vehicle_type }}</small></span>
                            <b class="plate-badge shrink-0">{{ unit.vehicle_plate }}</b>
                        </li>
                    </ul>
                    <a v-if="batch.leader.p5m_photo_path" :href="`/driver/escorts/${batch.leader.id}/p5m-photo`" target="_blank" class="mt-3 block text-sm font-semibold text-blue-700">Lihat Foto P5M</a>
                    <button class="btn mt-5 w-full" @click="openClose(batch)">
                        Tutup {{ batch.members.length }} Unit Sekaligus
                    </button>
                </article>
                <p
                    v-if="!active.length"
                    class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500"
                >
                    Belum ada unit ekspedisi yang sedang dalam proses escort.
                </p>
            </div>
        </section>
        <section class="panel">
            <h2 class="panel-title">Kendaraan ekspedisi tersedia</h2>
            <p class="panel-copy">
                Pilih berdasarkan perusahaan, nomor polisi, vendor/pengirim, dan
                tujuan.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="x in waiting"
                    :key="x.id"
                    class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-red-200 hover:shadow-lg"
                    :class="selectedIds.includes(x.id) ? 'border-red-400 ring-2 ring-red-100' : ''"
                >
                    <label class="mb-4 flex min-h-11 cursor-pointer items-center gap-3 rounded-xl bg-slate-50 px-3 text-sm font-bold text-slate-700">
                        <input
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-300 text-red-600 focus:ring-red-500"
                            :checked="selectedIds.includes(x.id)"
                            :disabled="!canStart(x)"
                            @change="toggleSelection(x.id)"
                        />
                        Pilih Unit
                    </label>
                    <div v-if="x.internal_ijin_lintas" class="mb-4 rounded-xl border border-violet-200 bg-violet-50 p-3 text-xs text-violet-800">
                        <b class="block">Izin Lintas Internal · {{ x.internal_ijin_lintas.title }}</b>
                        <span>{{ canStart(x) ? "Jadwal hari ini · Siap di-escort" : `Belum dapat di-escort · Jadwal ${x.internal_ijin_lintas.escort_date}` }}</span>
                    </div>
                    <EscortReadinessBadge v-else :item="x" class="mb-4" />
                    <div v-if="x.p2h_photo_path && x.p2h_status !== 'APPROVED'" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <b class="block">Status P2H</b><span>{{ availabilityLabel(x) }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <span class="status-badge bg-blue-50 text-blue-700"
                            >MENUNGGU DRIVER</span
                        ><span class="text-right text-xs text-slate-500">{{
                            dateTime(x.arrived_at)
                        }}</span>
                    </div>
                    <div class="mt-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold">
                                {{ x.expedition_company }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ x.report_number }}
                            </p>
                        </div>
                        <b class="plate-badge">{{ x.vehicle_plate }}</b>
                    </div>
                    <dl
                        class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-sm"
                    >
                        <div>
                            <dt class="text-[11px] text-slate-500">
                                Driver kendaraan
                            </dt>
                            <dd class="font-semibold">
                                {{ x.driver_name }} · {{ x.driver_phone }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-slate-500">
                                Vendor → Tujuan
                            </dt>
                            <dd class="font-semibold">
                                {{ x.sender_vendor }} → {{ x.destination }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-slate-500">
                                Kendaraan
                            </dt>
                            <dd class="font-semibold">{{ x.vehicle_type }}</dd>
                        </div>
                    </dl>
                    <a v-if="x.p5m_photo_path && (x.escort_driver_id === user.id || x.escort_closed_by === user.id)" :href="`/driver/escorts/${x.id}/p5m-photo`" target="_blank" class="mt-3 block text-sm font-semibold text-blue-700">Lihat Foto P5M</a>
                    <button class="btn-ghost mt-5 w-full" :disabled="!canStart(x)" @click="toggleSelection(x.id)">
                        {{ !canStart(x) ? availabilityLabel(x) : selectedIds.includes(x.id) ? "Batalkan Pilihan" : "Pilih Unit Ini" }}
                    </button>
                </article>
                <p
                    v-if="!waiting.length"
                    class="col-span-full rounded-2xl bg-slate-50 p-10 text-center text-sm text-slate-500"
                >
                    Tidak ada kendaraan ekspedisi yang menunggu driver escort.
                </p>
            </div>
            <div
                v-if="selectedIds.length"
                class="sticky bottom-4 z-20 mt-5 flex flex-col gap-3 rounded-2xl border border-red-200 bg-white p-4 shadow-xl shadow-slate-200/70 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm font-bold text-slate-900">
                        {{ selectedIds.length }} unit dipilih
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Satu foto P5M berlaku untuk seluruh unit.
                    </p>
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <button type="button" class="btn-ghost" @click="selectedIds = []">
                        Reset Pilihan
                    </button>
                    <button type="button" class="btn" @click="openClaim">
                        Ambil P5M & Mulai Escort
                    </button>
                </div>
            </div>
        </section>
        <Modal :show="Boolean(selectedClaim)" max-width="lg" @close="closeClaim">
            <form class="p-6" @submit.prevent="claim">
                <p class="text-[10px] font-bold uppercase tracking-[.18em] text-red-600">
                    Foto P5M Wajib
                </p>
                <h2 class="mt-1 text-xl font-bold">
                    Mulai Escort {{ selectedIds.length }} Unit
                </h2>
                <p class="mt-2 text-sm text-slate-500">
                    Ambil foto P5M terbaru menggunakan kamera perangkat.
                </p>
                <P5mCameraCapture
                    class="mt-4"
                    :active="Boolean(selectedClaim)"
                    :error="claimForm.errors.p5m_photo"
                    @captured="setP5mPhoto"
                />
                <p v-if="claimForm.errors.ready_escort_at" class="err mt-3">
                    {{ claimForm.errors.ready_escort_at }}
                </p>
                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-ghost" @click="closeClaim">
                        Batal
                    </button>
                    <button
                        class="btn"
                        :disabled="claimForm.processing || !claimForm.p5m_photo"
                    >
                        {{
                            claimForm.processing
                                ? "Menyimpan..."
                                : "Gunakan Foto & Mulai Escort"
                        }}
                    </button>
                </div>
            </form>
        </Modal>
        <Modal :show="Boolean(closing)" max-width="2xl" @close="closing = null"
            ><form class="p-6 sm:p-8" @submit.prevent="submitClose">
                <p
                    class="text-[11px] font-semibold uppercase tracking-[.15em] text-red-600"
                >
                    Konfirmasi Close Escort Ekspedisi
                </p>
                <h2 class="mt-2 text-2xl font-semibold">
                    Tutup {{ closing?.members.length }} Unit Sekaligus
                </h2>
                <p class="mt-2 text-sm text-slate-500">
                    Pastikan Anda berada di lokasi tujuan. Escort hanya dapat
                    ditutup ketika akurasi GPS maksimal ±100 meter. Presisi
                    aktual tetap dicatat oleh sistem.
                </p>
                <div
                    class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 text-xs sm:grid-cols-2"
                >
                    <p>
                        <a v-if="closing?.leader.p5m_photo_path" :href="`/driver/escorts/${closing.leader.id}/p5m-photo`" target="_blank" class="block text-blue-700">Lihat Foto P5M</a><span class="block text-slate-500">Dimulai oleh</span>
                        <b
                            >{{ closing?.leader.escort_driver?.name }} · Lambung
                            {{ closing?.leader.escort_driver?.hull_number }}</b
                        >
                    </p>
                    <p>
                        <span class="block text-slate-500"
                            >Akan ditutup oleh</span
                        >
                        <b>{{ user.name }} · Lambung {{ user.hull_number }}</b>
                    </p>
                </div>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    <li v-for="unit in closing?.members" :key="unit.id" class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-sm">
                        <span>{{ unit.report_number }}</span><b>{{ unit.vehicle_plate }}</b>
                    </li>
                </ul>
                <div
                    v-if="locating"
                    class="mt-6 rounded-xl bg-blue-50 p-4 text-sm text-blue-700"
                >
                    Mencari lokasi dengan akurasi maksimal ±100 meter...
                </div>
                <div
                    v-else-if="form.latitude"
                    class="mt-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    <b>Lokasi terekam · Akurasi ±{{ form.accuracy }} meter</b>
                    <p class="mt-1 break-words text-xs">
                        {{ form.location_name }}
                    </p>
                    <a
                        :href="`https://www.google.com/maps?q=${form.latitude},${form.longitude}`"
                        target="_blank"
                        class="mt-2 inline-block font-semibold underline"
                        >Buka Maps</a
                    >
                </div>
                <div v-if="locationError" class="mt-4 rounded-xl bg-red-50 p-4">
                    <p class="err">{{ locationError }}</p>
                    <button type="button" class="btn-ghost mt-3" @click="captureLocation">
                        Coba Ambil Lokasi Lagi
                    </button>
                </div>
                <p class="err">
                    {{
                        form.errors.latitude ||
                        form.errors.accuracy
                    }}
                </p>
                <P5mCameraCapture
                    class="mt-4"
                    :active="Boolean(closing)"
                    :error="form.errors.escort_close_photo"
                    label="Foto Closing"
                    ready-label="Foto closing siap digunakan"
                    @captured="setClosePhoto"
                />
                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        class="btn-ghost"
                        @click="closing = null"
                    >
                        Batal</button
                    ><button
                        class="btn"
                        :disabled="
                            form.processing || locating || !form.latitude || !form.escort_close_photo
                        "
                    >
                        {{
                            form.processing
                                ? "Menyimpan..."
                                : "Konfirmasi Selesai Escort"
                        }}
                    </button>
                </div>
            </form></Modal
        >
    </AppShell>
</template>
