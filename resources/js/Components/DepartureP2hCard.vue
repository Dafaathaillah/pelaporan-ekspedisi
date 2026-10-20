<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ unit: Object, permit: Object, checklist: Array });
const expanded = ref(false);
const latest = computed(() => props.unit.latest_departure_p2h);
const form = useForm({
    items: props.checklist.map(() => ({ condition: '', note: '' })),
    tire_positions: [], decision: '', notes: '',
});
const confirmForm = useForm({});
const submit = () => form.post(`/expedition/departures/${props.unit.id}/p2h`, { preserveScroll: true, onSuccess: () => { expanded.value = false; router.reload({ only: ['permits'] }); } });
const confirm = () => confirmForm.post(`/expedition/departures/${props.unit.id}/confirm`, { preserveScroll: true, onSuccess: () => router.reload({ only: ['permits'] }) });
const status = computed(() => latest.value?.departed_at ? 'Sudah Berangkat' : latest.value?.status === 'APPROVED' ? 'P2H Disetujui · Konfirmasi Keberangkatan' : latest.value?.status === 'PENDING' ? 'Menunggu Approval Vendor' : latest.value?.status === 'REJECTED' ? 'P2H Ditolak · Perbaiki Form' : 'P2H Belum Diisi');
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h4 class="font-black text-slate-900">{{ unit.plate_number }} · {{ unit.driver_name || 'Driver belum diisi' }}</h4><p class="text-sm text-slate-500">{{ unit.vehicle_type || '-' }} · {{ permit.vendor_name }}</p><p class="mt-2 text-sm font-semibold" :class="latest?.departed_at ? 'text-emerald-700' : latest?.status === 'REJECTED' ? 'text-red-700' : 'text-amber-700'">{{ status }}</p><p v-if="latest?.rejection_reason" class="mt-1 text-sm text-red-700">Alasan: {{ latest.rejection_reason }}</p></div>
            <div class="flex flex-wrap gap-2"><a v-if="latest" :href="`/departure-p2h/${unit.id}/download`" class="btn-ghost">Download P2H</a><button v-if="!latest || latest.status === 'REJECTED'" type="button" class="btn" @click="expanded = !expanded">{{ latest ? 'Perbaiki P2H' : 'Isi Form P2H' }}</button><button v-if="latest?.status === 'APPROVED' && !latest.departed_at" type="button" class="btn" :disabled="confirmForm.processing" @click="confirm">{{ confirmForm.processing ? 'Menyimpan...' : 'Konfirmasi Keberangkatan' }}</button></div>
        </div>
        <p v-if="confirmForm.errors.departure" class="err mt-2">{{ confirmForm.errors.departure }}</p>
        <form v-if="expanded" class="mt-5 border-t border-slate-200 pt-5" @submit.prevent="submit">
            <p class="mb-4 text-sm text-slate-600">Periksa kondisi unit sebelum berangkat. Nama driver dan identitas unit diambil dari izin lintas.</p>
            <div class="space-y-3"><div v-for="(label, index) in checklist" :key="index" class="grid gap-2 rounded-xl border border-slate-200 p-3 md:grid-cols-[minmax(0,1fr)_220px_220px]"><label class="text-sm font-semibold text-slate-800">{{ index + 1 }}. {{ label }}</label><select v-model="form.items[index].condition" class="field" required><option value="">Pilih kondisi</option><option>BAIK</option><option>RUSAK</option><option>NIHIL</option></select><input v-model="form.items[index].note" class="field" maxlength="500" placeholder="Keterangan (opsional)"><p v-if="form.errors[`items.${index}.condition`]" class="err md:col-span-3">{{ form.errors[`items.${index}.condition`] }}</p></div></div>
            <fieldset class="mt-5"><legend class="text-sm font-semibold">Nomor ban/track yang diperiksa *</legend><div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-5 md:grid-cols-10"><label v-for="number in 20" :key="number" class="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs"><input v-model="form.tire_positions" type="checkbox" :value="number">{{ number }}</label></div><p class="err">{{ form.errors.tire_positions }}</p></fieldset>
            <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Keputusan pemeriksaan *<select v-model="form.decision" class="field" required><option value="">Pilih hasil</option><option value="LAIK">Laik dikawal / memasuki area</option><option value="TIDAK_LAIK">Tidak laik dikawal / memasuki area</option></select><p class="err">{{ form.errors.decision }}</p></label><label class="text-sm font-semibold">Catatan pemeriksaan<textarea v-model="form.notes" class="field min-h-24" maxlength="2000"></textarea></label></div>
            <p v-if="form.errors.departure || form.errors.items" class="err mt-2">{{ form.errors.departure || form.errors.items }}</p>
            <div class="mt-5 flex justify-end"><button type="submit" class="btn" :disabled="form.processing || !form.tire_positions.length">{{ form.processing ? 'Mengirim...' : 'Kirim P2H ke Vendor' }}</button></div>
        </form>
    </article>
</template>
