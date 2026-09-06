<script setup>
import AppShell from "@/Components/AppShell.vue";
import DataTableFooter from "@/Components/DataTableFooter.vue";
import { router, useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";
import axios from "axios";
import { getPreciseLocation } from "@/lib/preciseLocation";

const props = defineProps({
    vehicleTypes: Array,
    expeditionCompany: String,
    reports: Object,
    permits: { type: Array, default: () => [] },
    reportBase: { type: String, default: "/expedition/reports" },
});
const arrivalInput = ref(null),
    pressureInput = ref(null),
    arrivalPreview = ref(""),
    pressurePreview = ref("");
const locating = ref({ arrival: false, pressure: false });
const locationError = ref({ arrival: "", pressure: "" });
const form = useForm({
    expedition_company: props.expeditionCompany,
    planning_item_id: "", vendor_submission_unit_id: "",
    driver_name: "",
    driver_phone: "",
    vehicle_plate: "",
    vehicle_type: "",
    sender_vendor: "",
    destination: "",
    blood_pressure_systolic: "",
    blood_pressure_diastolic: "",
    arrival_photo: null,
    blood_pressure_photo: null,
    arrival_latitude: "",
    arrival_longitude: "",
    arrival_location_accuracy: "",
    arrival_location_name: "",
    arrival_photo_captured_at: "",
    blood_pressure_latitude: "",
    blood_pressure_longitude: "",
    blood_pressure_location_accuracy: "",
    blood_pressure_location_name: "",
    blood_pressure_photo_captured_at: "",
});
const permitId = ref("");
const selectedPermit = computed(() => props.permits.find(p => p.id === permitId.value));
const selectedPlanning = computed(() => selectedPermit.value?.planning_items.find(p => p.id === form.planning_item_id));
const availableUnits = computed(() => (selectedPermit.value?.units || []).filter(u => !(selectedPlanning.value?.reports || []).some(r => r.vendor_submission_unit_id === u.id)));
const selectUnit = () => {
    const unit = availableUnits.value.find(u => u.id === form.vendor_submission_unit_id);
    Object.assign(form, { driver_name: unit?.driver_name || "", driver_phone: unit?.driver_phone || "", vehicle_plate: unit?.plate_number || "", vehicle_type: unit?.vehicle_type || "", sender_vendor: selectedPermit.value?.vendor_name || "", destination: selectedPermit.value?.destination || "" });
};
const resetSelection = () => { form.planning_item_id = ""; form.vendor_submission_unit_id = ""; selectUnit(); };
const fields = [
    ["driver_name", "Nama Driver"],
    ["driver_phone", "Nomor HP"],
    ["vehicle_plate", "Nomor Polisi"],
    ["destination", "Tujuan"],
];
const healthAssessment = computed(() => {
    const systolic = Number(form.blood_pressure_systolic);
    const diastolic = Number(form.blood_pressure_diastolic);
    if (!systolic || !diastolic) return null;
    if (systolic < 60 || systolic > 250)
        return {
            status: "INVALID",
            label: "Nilai Tensi Tidak Valid",
            reason: "Sistolik harus berada pada rentang 60–250 mmHg. Periksa kembali angka pada alat tensi.",
        };
    if (diastolic < 30 || diastolic > 150)
        return {
            status: "INVALID",
            label: "Nilai Tensi Tidak Valid",
            reason: "Diastolik harus berada pada rentang 30–150 mmHg. Periksa kembali angka pada alat tensi.",
        };
    if (diastolic >= systolic)
        return {
            status: "INVALID",
            label: "Nilai Tensi Tidak Valid",
            reason: "Tekanan diastolik harus lebih rendah daripada tekanan sistolik. Periksa kembali urutan atau hasil pengukuran.",
        };
    if (systolic >= 180 || diastolic >= 110)
        return {
            status: "UNFIT",
            label: "Unfit",
            reason: "Tekanan darah sangat tinggi dan memerlukan evaluasi medis segera.",
        };
    if (systolic >= 160 || diastolic >= 100)
        return {
            status: "UNFIT_PENDING",
            label: "Unfit / Pending",
            reason: "Tekanan darah cukup tinggi dan memerlukan evaluasi dokter.",
        };
    if (systolic >= 140 || diastolic >= 90)
        return {
            status: "PENDING",
            label: "Pending",
            reason: "Perlu pemeriksaan atau monitoring lebih lanjut.",
        };
    if (systolic >= 120 || diastolic >= 80)
        return {
            status: "FIT_WITH_NOTE",
            label: "Fit dengan Catatan Medis",
            reason: "Masih dapat bekerja, tetapi ada catatan atau rekomendasi kesehatan.",
        };
    return { status: "FIT", label: "Fit", reason: "Tekanan darah normal." };
});
const healthClass = (status) =>
    ({
        FIT: "border-emerald-200 bg-emerald-50 text-emerald-800",
        FIT_WITH_NOTE: "border-blue-200 bg-blue-50 text-blue-800",
        PENDING: "border-amber-200 bg-amber-50 text-amber-800",
        UNFIT_PENDING: "border-orange-200 bg-orange-50 text-orange-800",
        UNFIT: "border-red-200 bg-red-50 text-red-800",
        INVALID: "border-red-300 bg-red-50 text-red-800",
    })[status] || "border-slate-200 bg-slate-50 text-slate-700";
const pickPhoto = (kind) =>
    (kind === "arrival" ? arrivalInput.value : pressureInput.value)?.click();
const captureLocation = async (kind) => {
    locating.value[kind] = true;
    locationError.value[kind] = "";
    const prefix = kind === "arrival" ? "arrival" : "blood_pressure";
    try {
        const position = await getPreciseLocation();
        const { latitude, longitude, accuracy } = position.coords;
        form[prefix + "_latitude"] = latitude.toFixed(7);
        form[prefix + "_longitude"] = longitude.toFixed(7);
        form[prefix + "_location_accuracy"] = Math.round(accuracy);
        form[prefix + "_photo_captured_at"] = new Date().toISOString();
        try {
            const { data } = await axios.get(props.reportBase === "/vendor/arrivals" ? "/vendor/arrivals/reverse-geocode" : "/expedition/reverse-geocode", {
                params: { latitude, longitude },
            });
            form[prefix + "_location_name"] =
                data.location_name || "Nama daerah tidak ditemukan";
        } catch {
            form[prefix + "_location_name"] = "Nama daerah tidak ditemukan";
        }
    } catch (error) {
        form[prefix + "_latitude"] = "";
        form[prefix + "_longitude"] = "";
        form[prefix + "_location_accuracy"] = "";
        locationError.value[kind] =
            error?.code === 1
                ? "Izin lokasi ditolak. Aktifkan izin lokasi/GPS lalu ambil ulang foto."
                : error?.message ||
                  "Lokasi tidak dapat diperoleh. Pastikan GPS aktif dan coba lagi.";
    } finally {
        locating.value[kind] = false;
    }
};
const setPhoto = async (event, kind) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const preview = kind === "arrival" ? arrivalPreview : pressurePreview;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = URL.createObjectURL(file);
    form[kind === "arrival" ? "arrival_photo" : "blood_pressure_photo"] = file;
    await captureLocation(kind);
};
const clearPreviews = () => {
    [arrivalPreview, pressurePreview].forEach((preview) => {
        if (preview.value) URL.revokeObjectURL(preview.value);
        preview.value = "";
    });
    if (arrivalInput.value) arrivalInput.value.value = "";
    if (pressureInput.value) pressureInput.value.value = "";
    locating.value = { arrival: false, pressure: false };
    locationError.value = { arrival: "", pressure: "" };
};
const send = () => {
    if (!healthAssessment.value || healthAssessment.value.status === "INVALID")
        return;
    form.post(props.reportBase, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            permitId.value = "";
            form.expedition_company = props.expeditionCompany;
            clearPreviews();
        },
    });
};
const date = (value) =>
    new Date(value).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    });
const photoUrl = (row, kind) => `${props.reportBase}/${row.id}/photos/${kind}`;
onBeforeUnmount(clearPreviews);
</script>

<template>
    <AppShell
        title="Pelaporan Kedatangan"
        subtitle="Isi data setelah kendaraan tiba di Underpass."
        :eyebrow="reportBase === '/vendor/arrivals' ? 'Akun Vendor' : 'Akun Ekspedisi'"
    >
        <form
            class="panel p-5 sm:p-6"
            enctype="multipart/form-data"
            @submit.prevent="send"
        >
            <div class="mb-6 flex items-start gap-3">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-600"
                    ><svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M3 6h13v11H3zM16 10h3l2 3v4h-5z" />
                        <circle cx="7" cy="18" r="2" />
                        <circle cx="18" cy="18" r="2" /></svg
                ></span>
                <div>
                    <h2 class="font-black text-slate-900">
                        Laporan Kedatangan · WITA
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Lengkapi identitas kendaraan, pemeriksaan kesehatan, dan
                        bukti foto.
                    </p>
                </div>
            </div>
            <div class="mb-5 grid gap-4 md:grid-cols-3">
                <label class="text-sm font-semibold">Izin / Vendor<select v-model="permitId" class="field" required @change="resetSelection"><option value="">Pilih izin Released</option><option v-for="p in permits" :key="p.id" :value="p.id">{{ p.vendor_name }} &middot; {{ p.submission_number }}</option></select></label>
                <label class="text-sm font-semibold">Tanggal Planning<select v-model="form.planning_item_id" class="field" required @change="form.vendor_submission_unit_id = ''; selectUnit()"><option value="">Pilih planning</option><option v-for="p in selectedPermit?.planning_items || []" :key="p.id" :value="p.id">{{ p.planned_date }}</option></select><p class="err">{{ form.errors.planning_item_id }}</p></label>
                <label class="text-sm font-semibold">Unit / Driver<select v-model="form.vendor_submission_unit_id" class="field" required :disabled="!form.planning_item_id" @change="selectUnit"><option value="">Pilih unit yang belum datang</option><option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.plate_number }} &middot; {{ u.driver_name || 'Driver belum diisi' }}</option></select><p class="err">{{ form.errors.vendor_submission_unit_id }}</p></label>
                <p v-if="!permits.length" class="text-sm text-slate-500 md:col-span-3">Belum ada izin Released yang masih berlaku.</p>
                <p v-if="form.vendor_submission_unit_id && (!form.driver_name || !form.driver_phone || !form.vehicle_type || !form.destination)" class="text-sm text-amber-700 md:col-span-3">Data izin belum lengkap. Lengkapi driver, nomor HP, dan tipe kendaraan pada detail izin; tujuan diisi oleh reviewer. <a v-if="reportBase === '/vendor/arrivals' && selectedPermit" :href="`/vendor/submissions/${selectedPermit.id}`" class="font-semibold underline">Lengkapi detail unit</a></p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700"
                    >Perusahaan Ekspedisi <span class="text-red-600">*</span
                    ><input
                        v-model="form.expedition_company"
                        readonly
                        class="field bg-slate-50"
                    /><small class="text-slate-400"
                        >Otomatis dari akun login</small
                    ></label
                >
                <label class="text-sm font-semibold text-slate-700"
                    >Vendor/Pengirim <span class="text-red-600">*</span
                    ><input
                        v-model="form.sender_vendor" readonly
                        class="field"
                        placeholder="Otomatis dari izin terpilih"
                        autocomplete="organization"
                    />
                    <p class="err">{{ form.errors.sender_vendor }}</p></label
                >
                <label
                    v-for="field in fields"
                    :key="field[0]"
                    class="text-sm font-semibold text-slate-700"
                    >{{ field[1] }} <span class="text-red-600">*</span
                    ><input v-model="form[field[0]]" readonly class="field bg-slate-50" />
                    <p class="err">{{ form.errors[field[0]] }}</p></label
                >
                <label class="text-sm font-semibold text-slate-700"
                    >Jenis Kendaraan <span class="text-red-600">*</span
                    ><select
                        v-model="form.vehicle_type" disabled
                        class="field select-control"
                    >
                        <option value="">Pilih kendaraan</option>
                        <option v-for="type in vehicleTypes" :key="type">
                            {{ type }}
                        </option>
                    </select>
                    <p class="err">{{ form.errors.vehicle_type }}</p></label
                >
                <label class="text-sm font-semibold text-slate-700"
                    >Jam Kedatangan<input
                        value="Otomatis dicatat sistem saat dikirim"
                        readonly
                        class="field bg-slate-50"
                /></label>
            </div>

            <div class="my-7 border-t border-slate-100"></div>
            <div class="mb-4">
                <h3 class="font-black text-slate-900">
                    Pemeriksaan Kesehatan Driver
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Masukkan hasil pada alat tensi, lalu foto layar alat sebagai
                    bukti.
                </p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700"
                    >Sistolik / tekanan atas <span class="text-red-600">*</span>
                    <div class="relative">
                        <input
                            v-model="form.blood_pressure_systolic"
                            type="number"
                            min="60"
                            max="250"
                            inputmode="numeric"
                            class="field pr-16"
                            placeholder="Contoh: 120"
                        /><span
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400"
                            >mmHg</span
                        >
                    </div>
                    <p class="err">
                        {{ form.errors.blood_pressure_systolic }}
                    </p></label
                >
                <label class="text-sm font-semibold text-slate-700"
                    >Diastolik / tekanan bawah
                    <span class="text-red-600">*</span>
                    <div class="relative">
                        <input
                            v-model="form.blood_pressure_diastolic"
                            type="number"
                            min="30"
                            max="150"
                            inputmode="numeric"
                            class="field pr-16"
                            placeholder="Contoh: 80"
                        /><span
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400"
                            >mmHg</span
                        >
                    </div>
                    <p class="err">
                        {{ form.errors.blood_pressure_diastolic }}
                    </p></label
                >
            </div>

            <div
                v-if="healthAssessment"
                class="mt-4 rounded-2xl border p-4 transition-colors"
                :class="healthClass(healthAssessment.status)"
                aria-live="polite"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <p
                            class="text-[10px] font-bold uppercase tracking-[.16em] opacity-70"
                        >
                            Hasil Penilaian Tensi Otomatis
                        </p>
                        <p class="mt-1 text-lg font-bold">
                            {{ healthAssessment.label }}
                        </p>
                    </div>
                    <span
                        class="w-fit rounded-full bg-white/70 px-3 py-1 text-xs font-bold"
                        >{{ form.blood_pressure_systolic }}/{{
                            form.blood_pressure_diastolic
                        }}
                        mmHg</span
                    >
                </div>
                <p class="mt-2 text-sm font-medium leading-5">
                    {{ healthAssessment.reason }}
                </p>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">
                        Foto Kedatangan <span class="text-red-600">*</span>
                    </p>
                    <input
                        ref="arrivalInput"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        @change="setPhoto($event, 'arrival')"
                    />
                    <button
                        type="button"
                        class="group relative flex min-h-52 w-full overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 transition hover:border-red-300 hover:bg-red-50/40"
                        @click="pickPhoto('arrival')"
                    >
                        <img
                            v-if="arrivalPreview"
                            :src="arrivalPreview"
                            class="absolute inset-0 h-full w-full object-cover"
                            alt="Preview foto kedatangan"
                        /><span
                            v-if="arrivalPreview"
                            class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"
                        ></span>
                        <span
                            class="relative m-auto flex flex-col items-center px-5 text-center"
                            :class="
                                arrivalPreview
                                    ? 'self-end pb-4 text-white'
                                    : 'text-slate-600'
                            "
                            ><span
                                class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-red-600 shadow-sm"
                                ><svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M4 7h3l2-2h6l2 2h3v12H4z" />
                                    <circle cx="12" cy="13" r="3" /></svg></span
                            ><b class="mt-3">{{
                                arrivalPreview
                                    ? "Ambil Ulang Foto"
                                    : "Buka Kamera Kedatangan"
                            }}</b
                            ><small class="mt-1"
                                >Pastikan kendaraan terlihat jelas</small
                            ></span
                        >
                    </button>
                    <div
                        v-if="locating.arrival"
                        class="mt-2 rounded-xl bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700"
                    >
                        Mencari lokasi dengan akurasi maksimal ±100 meter...
                    </div>
                    <div
                        v-else-if="form.arrival_latitude"
                        class="mt-2 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800"
                    >
                        <b class="block"
                            >Lokasi foto tersimpan · Akurasi ±{{
                                form.arrival_location_accuracy
                            }}
                            m</b
                        ><span class="mt-1 block leading-relaxed">{{
                            form.arrival_location_name
                        }}</span
                        ><a
                            :href="`https://www.google.com/maps?q=${form.arrival_latitude},${form.arrival_longitude}`"
                            target="_blank"
                            rel="noopener"
                            class="mt-1 inline-block font-black underline"
                            >{{ form.arrival_latitude }},
                            {{ form.arrival_longitude }} · Buka peta</a
                        >
                    </div>
                    <p v-if="locationError.arrival" class="err mt-2">
                        {{ locationError.arrival }}
                    </p>
                    <p class="err">
                        {{
                            form.errors.arrival_photo ||
                            form.errors.arrival_latitude
                        }}
                    </p>
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">
                        Foto Hasil Tensi <span class="text-red-600">*</span>
                    </p>
                    <input
                        ref="pressureInput"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        @change="setPhoto($event, 'pressure')"
                    />
                    <button
                        type="button"
                        class="group relative flex min-h-52 w-full overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 transition hover:border-red-300 hover:bg-red-50/40"
                        @click="pickPhoto('pressure')"
                    >
                        <img
                            v-if="pressurePreview"
                            :src="pressurePreview"
                            class="absolute inset-0 h-full w-full object-cover"
                            alt="Preview foto hasil tensi"
                        /><span
                            v-if="pressurePreview"
                            class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"
                        ></span>
                        <span
                            class="relative m-auto flex flex-col items-center px-5 text-center"
                            :class="
                                pressurePreview
                                    ? 'self-end pb-4 text-white'
                                    : 'text-slate-600'
                            "
                            ><span
                                class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-red-600 shadow-sm"
                                ><svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M4 7h3l2-2h6l2 2h3v12H4z" />
                                    <circle cx="12" cy="13" r="3" /></svg></span
                            ><b class="mt-3">{{
                                pressurePreview
                                    ? "Ambil Ulang Foto"
                                    : "Buka Kamera Hasil Tensi"
                            }}</b
                            ><small class="mt-1"
                                >Pastikan angka pada layar dapat dibaca</small
                            ></span
                        >
                    </button>
                    <div
                        v-if="locating.pressure"
                        class="mt-2 rounded-xl bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700"
                    >
                        Mencari lokasi dengan akurasi maksimal ±100 meter...
                    </div>
                    <div
                        v-else-if="form.blood_pressure_latitude"
                        class="mt-2 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800"
                    >
                        <b class="block"
                            >Lokasi foto tersimpan · Akurasi ±{{
                                form.blood_pressure_location_accuracy
                            }}
                            m</b
                        ><span class="mt-1 block leading-relaxed">{{
                            form.blood_pressure_location_name
                        }}</span
                        ><a
                            :href="`https://www.google.com/maps?q=${form.blood_pressure_latitude},${form.blood_pressure_longitude}`"
                            target="_blank"
                            rel="noopener"
                            class="mt-1 inline-block font-black underline"
                            >{{ form.blood_pressure_latitude }},
                            {{ form.blood_pressure_longitude }} · Buka peta</a
                        >
                    </div>
                    <p v-if="locationError.pressure" class="err mt-2">
                        {{ locationError.pressure }}
                    </p>
                    <p class="err">
                        {{
                            form.errors.blood_pressure_photo ||
                            form.errors.blood_pressure_latitude
                        }}
                    </p>
                </div>
            </div>
            <p class="mt-5 text-center text-[11px] text-slate-400">
                Nama daerah menggunakan data © OpenStreetMap contributors.
                Koordinat GPS tetap tersimpan jika nama daerah tidak ditemukan.
            </p>
            <button
                :disabled="
                    form.processing ||
                    locating.arrival ||
                    locating.pressure ||
                    !healthAssessment ||
                    healthAssessment.status === 'INVALID'
                "
                class="btn mt-4 w-full"
            >
                {{
                    form.processing
                        ? "MENGIRIM LAPORAN..."
                        : locating.arrival || locating.pressure
                          ? "MENGAMBIL LOKASI..."
                          : healthAssessment?.status === "INVALID"
                            ? "PERIKSA KEMBALI NILAI TENSI"
                            : "KIRIM LAPORAN KEDATANGAN"
                }}
            </button>
        </form>

        <section class="panel mt-6 overflow-hidden p-0">
            <div class="border-b px-5 py-4">
                <h2 class="panel-title">Riwayat Laporan Saya</h2>
                <p class="panel-copy">
                    Hanya data yang dibuat oleh akun ekspedisi ini
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[1100px]">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Waktu</th>
                            <th>Driver</th>
                            <th>Nopol</th>
                            <th>Kendaraan</th>
                            <th>Tensi</th>
                            <th>Bukti Foto</th>
                            <th>Akurasi GPS</th>
                            <th>Vendor</th>
                            <th>Tujuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in reports.data" :key="row.id">
                            <td>{{ reports.from + index }}</td>
                            <td>{{ date(row.arrived_at) }}</td>
                            <td>{{ row.driver_name }}</td>
                            <td>
                                <b>{{ row.vehicle_plate }}</b>
                            </td>
                            <td>{{ row.vehicle_type }}</td>
                            <td>
                                <div
                                    v-if="row.blood_pressure_systolic"
                                    class="min-w-44"
                                >
                                    <b
                                        >{{ row.blood_pressure_systolic }}/{{
                                            row.blood_pressure_diastolic
                                        }}
                                        mmHg</b
                                    >
                                    <span
                                        class="mt-2 block w-fit rounded-full border px-2.5 py-1 text-[10px] font-bold"
                                        :class="healthClass(row.health_status)"
                                        >{{
                                            row.health_status_label ||
                                            "Belum dinilai"
                                        }}</span
                                    >
                                    <p
                                        v-if="row.health_status_reason"
                                        class="mt-2 max-w-56 whitespace-normal break-words text-[11px] leading-4 text-slate-500"
                                    >
                                        {{ row.health_status_reason }}
                                    </p>
                                </div>
                                <span v-else>-</span>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a
                                        v-if="row.arrival_photo_path"
                                        :href="photoUrl(row, 'arrival')"
                                        target="_blank"
                                        class="text-xs font-bold text-red-600 hover:underline"
                                        >Kedatangan</a
                                    ><a
                                        v-if="row.blood_pressure_photo_path"
                                        :href="photoUrl(row, 'blood-pressure')"
                                        target="_blank"
                                        class="text-xs font-bold text-red-600 hover:underline"
                                        >Tensi</a
                                    ><span
                                        v-if="
                                            !row.arrival_photo_path &&
                                            !row.blood_pressure_photo_path
                                        "
                                        >-</span
                                    >
                                </div>
                            </td>
                            <td>
                                <div class="min-w-36 space-y-1 text-xs">
                                    <p v-if="row.arrival_location_accuracy !== null">
                                        Kedatangan:
                                        <b>±{{ row.arrival_location_accuracy }} m</b>
                                    </p>
                                    <p
                                        v-if="
                                            row.blood_pressure_location_accuracy !==
                                            null
                                        "
                                    >
                                        Tensi:
                                        <b
                                            >±{{
                                                row.blood_pressure_location_accuracy
                                            }} m</b
                                        >
                                    </p>
                                    <span
                                        v-if="
                                            row.arrival_location_accuracy === null &&
                                            row.blood_pressure_location_accuracy ===
                                                null
                                        "
                                        >-</span
                                    >
                                </div>
                            </td>
                            <td>{{ row.sender_vendor }}</td>
                            <td>{{ row.destination }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <DataTableFooter
                :paginator="reports"
                :per-page="10"
                @update:per-page="
                    (value) =>
                        router.get(
                            '/expedition',
                            { per_page: value },
                            { preserveScroll: true },
                        )
                "
            />
        </section>
    </AppShell>
</template>
