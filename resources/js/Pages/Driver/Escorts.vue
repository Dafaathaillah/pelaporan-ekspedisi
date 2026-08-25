<script setup>
import ActionConfirm from "@/Components/ActionConfirm.vue";
import AppShell from "@/Components/AppShell.vue";
import Modal from "@/Components/Modal.vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { getPreciseLocation } from "@/lib/preciseLocation";
const props = defineProps({ items: Array });
const user = computed(() => usePage().props.auth.user),
    selectedClaim = ref(null),
    closing = ref(null),
    preview = ref(null),
    locating = ref(false),
    locationError = ref("");
const form = useForm({
    close_photo: null,
    latitude: "",
    longitude: "",
    accuracy: "",
    location_name: "",
    captured_at: "",
});
const waiting = computed(() =>
    props.items.filter((x) => x.escort_status === "WAITING"),
);
const mine = computed(() =>
    props.items.filter((x) => x.escort_driver_id === user.value.id),
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
const claim = () => {
    const x = selectedClaim.value;
    selectedClaim.value = null;
    if (x)
        router.patch(
            `/driver/escorts/${x.id}/claim`,
            {},
            { preserveScroll: true },
        );
};
const openClose = (x) => {
    closing.value = x;
    preview.value = null;
    locationError.value = "";
    form.reset();
    form.clearErrors();
};
const photoSelected = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.close_photo = file;
    preview.value = URL.createObjectURL(file);
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
                  "Koordinat belum diperoleh. Pastikan GPS aktif lalu ambil foto ulang.";
    } finally {
        locating.value = false;
    }
};
const submitClose = () =>
    form.post(`/driver/escorts/${closing.value.id}/close`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closing.value = null;
            preview.value = null;
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
                <strong>{{
                    mine.filter((x) => x.escort_status === "PROCESS").length
                }}</strong
                ><span>Unit yang sedang Anda proses</span>
            </article>
            <article class="metric-card">
                <p>Escort Selesai</p>
                <strong>{{
                    mine.filter((x) => x.escort_status === "CLOSED").length
                }}</strong
                ><span>Bukti close telah tersimpan</span>
            </article>
        </section>
        <section class="panel mb-5">
            <h2 class="panel-title">Escort aktif saya</h2>
            <p class="panel-copy">
                Tutup escort setelah kendaraan tiba di tujuan.
            </p>
            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                <article
                    v-for="x in mine.filter(
                        (i) => i.escort_status === 'PROCESS',
                    )"
                    :key="x.id"
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
                                {{ x.expedition_company }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ x.report_number }} ·
                                {{ dateTime(x.arrived_at) }}
                            </p>
                        </div>
                        <b class="plate-badge">{{ x.vehicle_plate }}</b>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500">
                                Driver kendaraan
                            </dt>
                            <dd class="font-semibold">{{ x.driver_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">
                                Vendor / Pengirim
                            </dt>
                            <dd class="font-semibold">{{ x.sender_vendor }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Kendaraan</dt>
                            <dd class="font-semibold">{{ x.vehicle_type }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Tujuan</dt>
                            <dd class="font-semibold">{{ x.destination }}</dd>
                        </div>
                    </dl>
                    <button class="btn mt-5 w-full" @click="openClose(x)">
                        Tutup Escort & Upload Bukti
                    </button>
                </article>
                <p
                    v-if="!mine.some((x) => x.escort_status === 'PROCESS')"
                    class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500"
                >
                    Belum ada unit ekspedisi yang sedang Anda escort.
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
                >
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
                    <button class="btn mt-5 w-full" @click="selectedClaim = x">
                        Pilih & Mulai Escort
                    </button>
                </article>
                <p
                    v-if="!waiting.length"
                    class="col-span-full rounded-2xl bg-slate-50 p-10 text-center text-sm text-slate-500"
                >
                    Tidak ada kendaraan ekspedisi yang menunggu driver escort.
                </p>
            </div>
        </section>
        <ActionConfirm
            :show="Boolean(selectedClaim)"
            title="Mulai escort kendaraan ini?"
            :message="`${selectedClaim?.expedition_company || ''} · ${selectedClaim?.vehicle_plate || ''} akan dikunci untuk Lambung ${user.hull_number}.`"
            confirm-text="Ya, Mulai Escort"
            @close="selectedClaim = null"
            @confirm="claim"
        />
        <Modal :show="Boolean(closing)" max-width="2xl" @close="closing = null"
            ><form class="p-6 sm:p-8" @submit.prevent="submitClose">
                <p
                    class="text-[11px] font-semibold uppercase tracking-[.15em] text-red-600"
                >
                    Bukti Close Escort Ekspedisi
                </p>
                <h2 class="mt-2 text-2xl font-semibold">
                    {{ closing?.expedition_company }} ·
                    {{ closing?.vehicle_plate }}
                </h2>
                <p class="mt-2 text-sm text-slate-500">
                    Ambil foto kendaraan di lokasi tujuan. Foto hanya dapat
                    disimpan ketika akurasi GPS maksimal ±5 meter.
                </p>
                <label
                    class="mt-6 block cursor-pointer rounded-2xl border-2 border-dashed border-red-200 bg-red-50/40 p-5 text-center"
                    ><input
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        @change="photoSelected"
                    /><img
                        v-if="preview"
                        :src="preview"
                        class="mx-auto max-h-64 rounded-xl object-contain"
                    /><template v-else
                        ><span
                            class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-red-600 text-2xl text-white"
                            >📷</span
                        ><b class="mt-3 block"
                            >Ambil Foto Close Escort</b
                        ></template
                    ></label
                >
                <div
                    v-if="locating"
                    class="mt-3 rounded-xl bg-blue-50 p-3 text-sm text-blue-700"
                >
                    Mencari GPS presisi maksimal ±5 meter. Tetap di area
                    terbuka...
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
                <p v-if="locationError" class="err">{{ locationError }}</p>
                <p class="err">
                    {{
                        form.errors.close_photo ||
                        form.errors.latitude ||
                        form.errors.accuracy
                    }}
                </p>
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
                            form.processing || locating || !form.latitude
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
