<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, ref } from 'vue';
import { getPreciseLocation } from '@/lib/preciseLocation';

const props = defineProps({
    number: Number,
    permit: Object,
    unit: Object,
    planning: Object,
    completedReport: Object,
    expeditionCompany: String,
    reportBase: String,
    selected: Boolean,
});
const emit = defineEmits(['update:selected']);

const completeIdentity = computed(() => props.unit.driver_name && props.unit.driver_phone && props.unit.plate_number && props.unit.vehicle_type && props.permit.destination);
const blockedReason = computed(() => !props.planning ? 'Unit belum memiliki kartu planning yang terjadwal.' : !completeIdentity.value ? 'Data driver, nomor HP, kendaraan, atau tujuan pada izin belum lengkap.' : !props.unit.latest_departure_p2h?.departed_at ? 'P2H harus disetujui vendor dan keberangkatan dikonfirmasi terlebih dahulu.' : '');
const inputs = { arrival: ref(null), pressure: ref(null) };
const previews = { arrival: ref(''), pressure: ref('') };
const locating = ref({ arrival: false, pressure: false });
const locationError = ref({ arrival: '', pressure: '' });
const submitting = ref(false);
const form = useForm({
    vendor_submission_unit_id: props.unit.id,
    blood_pressure_systolic: '', blood_pressure_diastolic: '',
    arrival_photo: null, blood_pressure_photo: null,
    arrival_latitude: '', arrival_longitude: '', arrival_location_accuracy: '', arrival_location_name: '', arrival_photo_captured_at: '',
    blood_pressure_latitude: '', blood_pressure_longitude: '', blood_pressure_location_accuracy: '', blood_pressure_location_name: '', blood_pressure_photo_captured_at: '',
});
const assessment = computed(() => {
    const s = Number(form.blood_pressure_systolic), d = Number(form.blood_pressure_diastolic);
    if (!s || !d) return null;
    if (s < 60 || s > 250 || d < 30 || d > 150 || d >= s) return { status: 'INVALID', label: 'Nilai Tensi Tidak Valid', reason: 'Periksa kembali nilai sistolik dan diastolik.' };
    if (s >= 180 || d >= 110) return { status: 'UNFIT', label: 'Unfit', reason: 'Tekanan darah sangat tinggi dan memerlukan evaluasi medis segera.' };
    if (s >= 160 || d >= 100) return { status: 'UNFIT_PENDING', label: 'Unfit / Pending', reason: 'Tekanan darah cukup tinggi dan memerlukan evaluasi dokter.' };
    if (s >= 140 || d >= 90) return { status: 'PENDING', label: 'Pending', reason: 'Perlu pemeriksaan atau monitoring lebih lanjut.' };
    if (s >= 120 || d >= 80) return { status: 'FIT_WITH_NOTE', label: 'Fit dengan Catatan Medis', reason: 'Masih dapat bekerja, tetapi ada catatan kesehatan.' };
    return { status: 'FIT', label: 'Fit', reason: 'Tekanan darah normal.' };
});
const assessmentClass = computed(() => ({ FIT: 'border-emerald-200 bg-emerald-50 text-emerald-800', FIT_WITH_NOTE: 'border-blue-200 bg-blue-50 text-blue-800', PENDING: 'border-amber-200 bg-amber-50 text-amber-800', UNFIT_PENDING: 'border-orange-200 bg-orange-50 text-orange-800', UNFIT: 'border-red-200 bg-red-50 text-red-800', INVALID: 'border-red-300 bg-red-50 text-red-800' })[assessment.value?.status]);
const formattedDate = value => new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const captureLocation = async kind => {
    locating.value[kind] = true; locationError.value[kind] = '';
    const prefix = kind === 'arrival' ? 'arrival' : 'blood_pressure';
    try {
        const { latitude, longitude, accuracy } = (await getPreciseLocation()).coords;
        form[`${prefix}_latitude`] = latitude.toFixed(7); form[`${prefix}_longitude`] = longitude.toFixed(7);
        form[`${prefix}_location_accuracy`] = Math.round(accuracy); form[`${prefix}_photo_captured_at`] = new Date().toISOString();
        try {
            const endpoint = props.reportBase === '/vendor/arrivals' ? '/vendor/arrivals/reverse-geocode' : '/expedition/reverse-geocode';
            form[`${prefix}_location_name`] = (await axios.get(endpoint, { params: { latitude, longitude } })).data.location_name || 'Nama daerah tidak ditemukan';
        } catch { form[`${prefix}_location_name`] = 'Nama daerah tidak ditemukan'; }
    } catch (error) {
        form[`${prefix}_latitude`] = ''; form[`${prefix}_longitude`] = ''; form[`${prefix}_location_accuracy`] = '';
        locationError.value[kind] = error?.code === 1 ? 'Izin lokasi ditolak. Aktifkan GPS lalu ambil ulang foto.' : (error?.message || 'Lokasi tidak dapat diperoleh.');
    } finally { locating.value[kind] = false; }
};
const setPhoto = async (event, kind) => {
    const file = event.target.files?.[0]; if (!file) return;
    if (previews[kind].value) URL.revokeObjectURL(previews[kind].value);
    previews[kind].value = URL.createObjectURL(file);
    form[{ arrival: 'arrival_photo', pressure: 'blood_pressure_photo' }[kind]] = file;
    await captureLocation(kind);
};
const send = async () => {
    form.clearErrors();
    if (props.completedReport || blockedReason.value) throw new Error(blockedReason.value || 'Unit sudah dikirim.');
    if (!assessment.value || assessment.value.status === 'INVALID') {
        form.setError('blood_pressure_systolic', 'Isi nilai tensi yang valid untuk unit ini.');
        throw new Error('Nilai tensi belum valid.');
    }
    const payload = new FormData();
    Object.entries(form.data()).forEach(([key, value]) => {
        if (value !== null && value !== '') payload.append(key, value);
    });
    submitting.value = true;
    try {
        await axios.post(props.reportBase, payload, { headers: { Accept: 'application/json' } });
        return true;
    } catch (error) {
        const errors = error.response?.data?.errors || {};
        Object.entries(errors).forEach(([field, messages]) => form.setError(field, Array.isArray(messages) ? messages[0] : messages));
        throw error;
    } finally { submitting.value = false; }
};
defineExpose({ send });
onBeforeUnmount(() => Object.values(previews).forEach(preview => preview.value && URL.revokeObjectURL(preview.value)));
</script>

<template>
    <article class="overflow-hidden rounded-3xl border bg-white shadow-sm" :class="completedReport ? 'border-emerald-200' : 'border-slate-200'">
        <header class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
            <label class="flex cursor-pointer items-start gap-4" :class="(completedReport || blockedReason) ? 'cursor-not-allowed opacity-70' : ''"><input :checked="selected" type="checkbox" class="mt-1 h-5 w-5 rounded border-slate-300 text-red-600 focus:ring-red-500" :disabled="!!completedReport || !!blockedReason || submitting" @change="emit('update:selected', $event.target.checked)"><span><span class="text-xs font-black uppercase tracking-widest text-red-600">Unit {{ number }}</span><span class="mt-1 block text-xl font-black text-slate-900">{{ unit.plate_number || 'Nomor polisi belum diisi' }}</span><span class="mt-1 block text-sm text-slate-500">Planning: {{ planning?.planned_date || 'Belum terjadwal' }}</span></span></label>
            <div v-if="completedReport" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><b class="block">✓ Kedatangan Sudah Dikirim</b><span>{{ formattedDate(completedReport.arrived_at) }} WITA</span></div>
            <span v-else class="w-fit rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700">Menunggu kedatangan</span>
        </header>
        <div class="p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="([label, value]) in [['Nama Driver', unit.driver_name], ['Nomor HP', unit.driver_phone], ['Jenis Kendaraan', unit.vehicle_type], ['Vendor/Pengirim', permit.vendor_name], ['Tujuan', permit.destination]]" :key="label" class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ label }}</p><p class="mt-1 font-semibold text-slate-800">{{ value || '-' }}</p></div>
            </div>
            <p v-if="blockedReason && !completedReport" class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">{{ blockedReason }}</p>
            <div v-if="reportBase === '/vendor/arrivals' && !completedReport && !unit.latest_departure_p2h?.departed_at" class="mt-3 text-sm text-slate-600">
                <template v-if="unit.latest_departure_p2h?.status === 'PENDING'"><p>P2H sudah dikirim oleh ekspedisi dan menunggu keputusan vendor.</p><Link :href="`/vendor/submissions/${permit.id}`" class="mt-2 inline-block font-bold text-red-700 underline">Buka Detail Izin untuk Approve / Reject P2H</Link></template>
                <p v-else-if="unit.latest_departure_p2h?.status === 'APPROVED'">P2H sudah disetujui. Akun ekspedisi perlu mengonfirmasi keberangkatan pada menu P2H &amp; Kedatangan.</p>
                <p v-else>Form P2H diisi oleh akun ekspedisi dari menu P2H &amp; Kedatangan. Jika izin tidak muncul di akun tersebut, koordinator perlu menghubungkan perusahaan akun ekspedisi melalui monitoring Ijin Lintas atau Manajemen Pengguna.</p>
            </div>
            <form v-if="!completedReport && selected" class="mt-5" @submit.prevent>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Sistolik / tekanan atas *<input v-model="form.blood_pressure_systolic" type="number" min="60" max="250" class="field" placeholder="Contoh: 120"><p class="err">{{ form.errors.blood_pressure_systolic }}</p></label>
                    <label class="text-sm font-semibold">Diastolik / tekanan bawah *<input v-model="form.blood_pressure_diastolic" type="number" min="30" max="150" class="field" placeholder="Contoh: 80"><p class="err">{{ form.errors.blood_pressure_diastolic }}</p></label>
                </div>
                <div v-if="assessment" class="mt-4 rounded-2xl border p-4" :class="assessmentClass"><b>{{ assessment.label }}</b><p class="text-sm">{{ assessment.reason }}</p></div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div v-for="kind in ['arrival', 'pressure']" :key="kind">
                        <p class="mb-2 text-sm font-semibold">{{ kind === 'arrival' ? 'Foto Kedatangan' : 'Foto Hasil Tensi' }} *</p>
                        <input :ref="el => inputs[kind].value = el" type="file" accept="image/*" capture="environment" class="hidden" @change="setPhoto($event, kind)">
                        <button type="button" class="relative flex min-h-44 w-full overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50" @click="inputs[kind].value?.click()">
                            <img v-if="previews[kind].value" :src="previews[kind].value" class="absolute inset-0 h-full w-full object-cover" alt="Preview foto"><span class="relative m-auto rounded-xl bg-white/90 px-4 py-2 text-sm font-bold text-red-600">{{ previews[kind].value ? 'Ambil Ulang Foto' : 'Buka Kamera' }}</span>
                        </button>
                        <p v-if="kind !== 'p2h' && locating[kind]" class="mt-2 text-xs font-semibold text-blue-700">Mencari lokasi GPS...</p>
                        <p v-if="kind !== 'p2h' && form[(kind === 'arrival' ? 'arrival' : 'blood_pressure') + '_latitude']" class="mt-2 text-xs font-semibold text-emerald-700">Lokasi tersimpan · akurasi ±{{ form[(kind === 'arrival' ? 'arrival' : 'blood_pressure') + '_location_accuracy'] }} m</p>
                        <p v-if="locationError[kind]" class="err">{{ locationError[kind] }}</p>
                        <p class="err">{{ form.errors[{ arrival: 'arrival_photo', pressure: 'blood_pressure_photo', p2h: 'p2h_photo' }[kind]] }}</p>
                    </div>
                </div>
                <p v-if="form.errors.vendor_submission_unit_id" class="err text-center">{{ form.errors.vendor_submission_unit_id }}</p>
            </form>
            <p v-else-if="!completedReport && !blockedReason" class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm font-semibold text-slate-600">Centang unit ini jika driver dan kendaraannya sudah tiba.</p>
            <p v-if="submitting" class="mt-4 text-center text-sm font-bold text-red-600">Mengirim kedatangan Unit {{ number }}...</p>
        </div>
    </article>
</template>
