<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ unit: Object, checklist: Array, reportBase: String, canSubmit: Boolean });
const latest = computed(() => props.unit.latest_arrival_p2h);
const expanded = ref(false);
const form = useForm({ items: props.checklist.map(() => ({ condition: '', note: '' })), tire_positions: [], decision: '', notes: '' });
const status = computed(() => ({ PENDING: 'Menunggu persetujuan Coordinator', APPROVED: 'Disetujui Coordinator — form kedatangan dapat diisi', REJECTED: 'Ditolak Coordinator — perbaiki dan kirim ulang' })[latest.value?.status] || 'P2H kedatangan belum diisi');
const open = () => {
    if (latest.value?.status === 'REJECTED') {
        form.items = props.checklist.map((_, index) => ({ condition: latest.value.items[index]?.condition || '', note: latest.value.items[index]?.note || '' }));
        form.tire_positions = [...latest.value.tire_positions];
        form.decision = latest.value.decision;
        form.notes = latest.value.notes || '';
    }
    expanded.value = !expanded.value;
};
const submit = () => form.post(`${props.reportBase === '/vendor/arrivals' ? '/vendor' : '/expedition'}/arrivals/${props.unit.id}/p2h`, { preserveScroll: true, onSuccess: () => { expanded.value = false; } });
</script>

<template>
    <section class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h4 class="font-black text-slate-900">P2H Kedatangan</h4><p class="mt-1 text-sm font-semibold" :class="latest?.status === 'APPROVED' ? 'text-emerald-700' : latest?.status === 'REJECTED' ? 'text-red-700' : 'text-amber-800'">{{ status }}</p><p v-if="latest" class="mt-1 text-xs text-slate-500">Revisi {{ latest.revision }}<span v-if="latest.reviewer"> · Diperiksa oleh {{ latest.reviewer.name }}</span></p><p v-if="latest?.rejection_reason" class="mt-2 text-sm text-red-700">Alasan: {{ latest.rejection_reason }}</p></div>
            <div class="flex flex-wrap gap-2"><a v-if="latest" :href="`/arrival-p2h/${latest.id}/download`" class="btn-ghost">Download P2H Kedatangan</a><button v-if="canSubmit && (!latest || latest.status === 'REJECTED')" type="button" class="btn" @click="open">{{ expanded ? 'Tutup Form P2H' : latest ? 'Perbaiki P2H Kedatangan' : 'Isi P2H Kedatangan' }}</button></div>
        </div>
        <p v-if="!canSubmit && !latest" class="mt-2 text-sm text-slate-600">P2H kedatangan dapat diisi setelah keberangkatan dikonfirmasi, identitas lengkap, dan unit memiliki planning.</p>
        <form v-if="expanded && canSubmit && (!latest || latest.status === 'REJECTED')" class="mt-5 border-t border-slate-200 pt-5" @submit.prevent="submit">
            <p class="mb-4 text-sm text-slate-600">Periksa kembali kondisi unit saat tiba. Kirim hasilnya ke Coordinator sebelum mengisi laporan kedatangan.</p>
            <div class="space-y-3"><div v-for="(label, index) in checklist" :key="index" class="grid gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-[minmax(0,1fr)_220px_220px]"><label :for="`arrival-p2h-${unit.id}-${index}`" class="text-sm font-semibold text-slate-800">{{ index + 1 }}. {{ label }}</label><select :id="`arrival-p2h-${unit.id}-${index}`" v-model="form.items[index].condition" class="field" required><option value="">Pilih kondisi</option><option>BAIK</option><option>RUSAK</option><option>NIHIL</option></select><input v-model="form.items[index].note" :aria-label="`Keterangan ${label}`" class="field" maxlength="500" placeholder="Keterangan (opsional)"><p v-if="form.errors[`items.${index}.condition`] || form.errors[`items.${index}.note`]" class="err md:col-span-3">{{ form.errors[`items.${index}.condition`] || form.errors[`items.${index}.note`] }}</p></div></div>
            <fieldset class="mt-5"><legend class="text-sm font-semibold">Nomor ban/track yang diperiksa *</legend><div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-5 md:grid-cols-10"><label v-for="number in 20" :key="number" class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs"><input v-model="form.tire_positions" type="checkbox" :value="number">{{ number }}</label></div></fieldset>
            <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Keputusan pemeriksaan *<select v-model="form.decision" class="field" required><option value="">Pilih hasil</option><option value="LAIK">Laik dikawal / memasuki area</option><option value="TIDAK_LAIK">Tidak laik dikawal / memasuki area</option></select></label><label class="text-sm font-semibold">Catatan pemeriksaan<textarea v-model="form.notes" class="field min-h-24" maxlength="2000"></textarea></label></div>
            <p v-for="(message, field) in form.errors" :key="field" class="err mt-2">{{ message }}</p>
            <div class="mt-5 flex justify-end"><button type="submit" class="btn" :disabled="form.processing || !form.tire_positions.length">{{ form.processing ? 'Mengirim...' : 'Kirim P2H Kedatangan ke Coordinator' }}</button></div>
        </form>
    </section>
</template>
