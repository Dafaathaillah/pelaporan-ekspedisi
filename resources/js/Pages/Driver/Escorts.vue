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
    locating = ref(false),
    locationError = ref("");
const form = useForm({
    latitude: "",
    longitude: "",
    accuracy: "",
    location_name: "",
    captured_at: "",
});
const waiting = computed(() =>
    props.items.filter((x) => x.escort_status === "WAITING"),
);
const active = computed(() =>
    props.items.filter((x) => x.escort_status === "PROCESS"),
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
    form.post(`/driver/escorts/${closing.value.id}/close`, {
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
                    v-for="x in active"
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
                        <div class="rounded-xl bg-white/80 p-3 sm:col-span-2">
                            <dt class="text-xs text-slate-500">
                                Escort dimulai oleh
                            </dt>
                            <dd class="font-semibold">
                                {{ x.escort_driver?.name || "Driver" }} ·
                                Lambung
                                {{ x.escort_driver?.hull_number || "-" }}
                            </dd>
                            <p
                                v-if="x.escort_driver_id !== user.id"
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
                        Tutup Escort sebagai Lambung {{ user.hull_number }}
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
                    Konfirmasi Close Escort Ekspedisi
                </p>
                <h2 class="mt-2 text-2xl font-semibold">
                    {{ closing?.expedition_company }} ·
                    {{ closing?.vehicle_plate }}
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
                        <span class="block text-slate-500">Dimulai oleh</span>
                        <b
                            >{{ closing?.escort_driver?.name }} · Lambung
                            {{ closing?.escort_driver?.hull_number }}</b
                        >
                    </p>
                    <p>
                        <span class="block text-slate-500"
                            >Akan ditutup oleh</span
                        >
                        <b>{{ user.name }} · Lambung {{ user.hull_number }}</b>
                    </p>
                </div>
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
