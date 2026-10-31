<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ p2h: Object, checklist: Array });
const expanded = ref(false);
const reviewing = ref(false);
const form = useForm({ status: 'APPROVED', reason: '' });
const choose = (status) => { form.reset(); form.clearErrors(); form.status = status; reviewing.value = true; };
const submit = () => form.patch(`/coordinator/arrival-p2h/${props.p2h.id}/review`, { preserveScroll: true, onSuccess: () => { reviewing.value = false; } });
const date = (value) => value ? new Date(value).toLocaleString('id-ID', { timeZone: 'Asia/Makassar', dateStyle: 'medium', timeStyle: 'short' }) + ' WITA' : '-';
</script>

<template>
    <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 break-words"><h4 class="font-black text-slate-900">{{ p2h.vehicle_plate }} · {{ p2h.driver_name }}</h4><p class="text-sm text-slate-600">{{ p2h.unit?.submission?.vendor_name }} · {{ p2h.unit?.submission?.submission_number }} · Revisi {{ p2h.revision }}</p><p class="mt-1 text-xs text-slate-500">Dikirim {{ date(p2h.created_at) }}</p><p class="mt-2 text-sm font-bold" :class="p2h.status === 'APPROVED' ? 'text-emerald-700' : p2h.status === 'REJECTED' ? 'text-red-700' : 'text-amber-800'">{{ p2h.status === 'APPROVED' ? 'Disetujui' : p2h.status === 'REJECTED' ? 'Ditolak' : 'Menunggu Persetujuan' }}</p><p v-if="p2h.reviewed_at" class="mt-1 text-xs text-slate-500">{{ p2h.reviewer?.name }} · {{ date(p2h.reviewed_at) }}</p><p v-if="p2h.rejection_reason" class="mt-2 text-sm text-red-700">Alasan: {{ p2h.rejection_reason }}</p></div>
            <div class="grid w-full min-w-0 gap-2 sm:flex sm:w-auto sm:flex-wrap"><a :href="`/arrival-p2h/${p2h.id}/download`" class="btn-ghost">Download P2H Kedatangan</a><button type="button" class="btn-ghost" :aria-expanded="expanded" @click="expanded = !expanded">{{ expanded ? 'Tutup Pemeriksaan' : 'Lihat Pemeriksaan' }}</button></div>
        </div>
        <div v-if="expanded" class="mt-4">
            <div class="max-w-full overflow-x-auto" tabindex="0" role="region" aria-label="Checklist P2H, geser untuk melihat seluruh kolom"><table class="w-full min-w-[480px] text-left text-sm"><thead><tr class="border-b"><th class="p-2">Item Pemeriksaan</th><th class="p-2">Kondisi</th><th class="p-2">Keterangan</th></tr></thead><tbody><tr v-for="(label, index) in checklist" :key="index" class="border-b border-slate-100"><td class="p-2">{{ index + 1 }}. {{ label }}</td><td class="p-2 font-semibold">{{ p2h.items[index]?.condition || '-' }}</td><td class="p-2">{{ p2h.items[index]?.note || '-' }}</td></tr></tbody></table></div>
            <p class="mt-3 text-sm"><b>Ban/track:</b> {{ p2h.tire_positions.join(', ') }}</p><p class="mt-2 text-sm"><b>Hasil:</b> {{ p2h.decision === 'LAIK' ? 'LAIK' : 'TIDAK LAIK' }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm"><b>Catatan:</b> {{ p2h.notes || '-' }}</p>
            <div v-if="p2h.status === 'PENDING'" class="mt-4 flex flex-wrap gap-2"><button type="button" class="btn" @click="choose('APPROVED')">Setujui P2H Kedatangan</button><button type="button" class="btn-ghost text-red-700" @click="choose('REJECTED')">Tolak</button></div>
        </div>
        <form v-if="reviewing && p2h.status === 'PENDING'" class="mt-4 rounded-xl bg-slate-50 p-4" @submit.prevent="submit"><p class="text-sm font-bold">{{ form.status === 'APPROVED' ? 'Setujui P2H dan buka form kedatangan unit ini?' : 'Tolak P2H kedatangan unit ini?' }}</p><label v-if="form.status === 'REJECTED'" class="mt-3 block text-sm font-semibold">Alasan penolakan *<textarea v-model="form.reason" class="field" maxlength="1000" required></textarea></label><p v-for="(message, field) in form.errors" :key="field" class="err mt-2">{{ message }}</p><div class="mt-3 flex gap-2"><button class="btn" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Konfirmasi' }}</button><button type="button" class="btn-ghost" :disabled="form.processing" @click="reviewing = false">Batal</button></div></form>
    </article>
</template>
