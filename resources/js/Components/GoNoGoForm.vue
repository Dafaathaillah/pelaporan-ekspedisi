<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import P5mCameraCapture from '@/Components/P5mCameraCapture.vue';

const props = defineProps({ units: Array, checklist: Array, initial: Object });
const emit = defineEmits(['submitted', 'cancel']);
const form = useForm({
    report_ids: props.units.map(unit => unit.id), escorter_name: props.initial?.escorter_name || '', nik: props.initial?.nik || '',
    driver_decision: props.initial?.driver_decision || '', notes: props.initial?.notes || '',
    items: props.checklist.map((_, index) => ({ condition: props.initial?.items[index]?.condition || '', evidence: props.initial?.items[index]?.evidence || '', note: props.initial?.items[index]?.note || '' })), p5m_photo: null,
});
const updateConditionNote = index => {
    const item = form.items[index];
    item.note = { SESUAI: 'sesuai siap escort', TIDAK_SESUAI: 'pengecekan kembali' }[item.condition] || '';
};
const plates = computed(() => props.units.map(unit => unit.vehicle_plate).join(', '));
const drivers = computed(() => props.units.map(unit => unit.driver_name).join(', '));
const today = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).format(new Date());
const submit = () => form.transform(data => ({ ...data, report_ids: props.units.map(unit => unit.id) })).post('/driver/go-no-go', { forceFormData: true, preserveScroll: true, onSuccess: () => emit('submitted') });
</script>

<template>
    <form class="max-h-[85dvh] overflow-y-auto overscroll-contain p-4 sm:p-6" @submit.prevent="submit">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4"><div><p class="text-xs font-bold uppercase tracking-wider text-lime-800">GO / NO GO · {{ units.length }} unit</p><h2 id="go-no-go-form-title" class="mt-1 text-xl font-bold text-slate-900">Checklist Kesiapan Pengawalan Unit</h2><p class="mt-2 text-sm text-slate-500">Isi pemeriksaan dan tunggu persetujuan Coordinator sebelum mulai escort.</p></div><button type="button" class="btn-ghost min-h-11 shrink-0" :disabled="form.processing" @click="emit('cancel')">Tutup</button></header>
        <h3 class="mt-5 rounded-lg bg-sky-100 px-3 py-2 text-sm font-bold text-sky-900">A. Identitas</h3>
        <div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2">
            <label class="text-sm font-semibold">Departemen<input value="SCM" readonly class="field bg-slate-50"></label>
            <label class="text-sm font-semibold">Hari / Tanggal<input :value="today" readonly class="field bg-slate-50"><span class="mt-1 block text-xs font-normal text-slate-500">Tanggal final dicatat otomatis saat submit (WITA).</span></label>
            <label class="text-sm font-semibold">Nama Escort Commander / Escorter *<input v-model="form.escorter_name" class="field" required maxlength="150" autocomplete="name"></label>
            <label class="text-sm font-semibold">NIK *<input v-model="form.nik" class="field" required maxlength="50"></label>
            <label class="text-sm font-semibold">Jabatan<input value="Driver LV" readonly class="field bg-slate-50"></label>
            <label class="text-sm font-semibold">Lokasi Tujuan<input value="PPA-BIB" readonly class="field bg-slate-50"></label>
            <label class="min-w-0 text-sm font-semibold">No Pol Unit Dikawal<textarea :value="plates" readonly rows="2" class="field resize-y bg-slate-50"></textarea></label>
            <label class="min-w-0 text-sm font-semibold">Nama Driver Dikawal<textarea :value="drivers" readonly rows="2" class="field resize-y bg-slate-50"></textarea></label>
        </div>
        <h3 class="mt-5 rounded-lg bg-sky-100 px-3 py-2 text-sm font-bold text-sky-900">B. Item yang Diperiksa</h3>
        <p class="mt-2 text-xs text-slate-500">Pilih kondisi setiap item secara manual. Keterangan terisi otomatis saat kondisi dipilih dan tetap dapat diubah. Bukti/referensi dapat diisi sesuai hasil pemeriksaan.</p>
        <div class="mt-3 space-y-3"><fieldset v-for="(label, index) in checklist" :key="index" class="min-w-0 rounded-xl border border-slate-200 p-3"><legend class="px-1 text-sm font-semibold text-slate-800">{{ index + 1 }}. {{ label }}</legend><div class="grid min-w-0 gap-3 sm:grid-cols-3"><label class="text-xs font-semibold text-slate-600">Kondisi *<select v-model="form.items[index].condition" class="field" required @change="updateConditionNote(index)"><option value="">Pilih kondisi</option><option value="SESUAI">Sesuai</option><option value="TIDAK_SESUAI">Tidak Sesuai</option><option value="NA">Nihil / NA</option></select></label><label class="text-xs font-semibold text-slate-600">Bukti / Referensi<input v-model="form.items[index].evidence" maxlength="300" class="field" placeholder="Nomor dokumen / bukti"></label><label class="text-xs font-semibold text-slate-600">Keterangan<input v-model="form.items[index].note" maxlength="500" class="field" placeholder="Catatan pemeriksaan"></label></div></fieldset></div>
        <label class="mt-5 block text-sm font-bold">Keputusan Kelayakan (Diisi Driver) *<select v-model="form.driver_decision" class="field" required><option value="">Pilih keputusan</option><option value="LAIK">Layak</option><option value="TIDAK_LAIK">Tidak Layak</option></select></label>
        <p v-if="form.driver_decision === 'TIDAK_LAIK'" class="mt-2 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800">Hasil tetap NO GO walaupun pemeriksaannya disetujui. Perbaiki kesiapan dan ajukan revisi sebelum mulai escort.</p>
        <label class="mt-4 block text-sm font-semibold">Catatan driver<textarea v-model="form.notes" rows="3" maxlength="2000" class="field"></textarea></label>
        <h3 class="mt-5 text-sm font-bold">Foto P5M Wajib *</h3>
        <P5mCameraCapture class="mt-3" :active="true" :error="form.errors.p5m_photo" @captured="file => form.p5m_photo = file" />
        <div v-if="Object.keys(form.errors).length" role="alert" class="mt-4 rounded-xl bg-red-50 p-3"><p v-for="(message, key) in form.errors" :key="key" class="text-sm text-red-800">{{ message }}</p></div>
        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" class="btn-ghost min-h-11" :disabled="form.processing" @click="emit('cancel')">Batal</button><button type="submit" class="btn min-h-11" :disabled="form.processing || !form.p5m_photo || !units.length || units.length > 100">{{ form.processing ? 'Mengirim...' : 'Kirim GO/NO GO ke Coordinator' }}</button></div>
    </form>
</template>
