<script setup>
import AppShell from '@/Components/AppShell.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({ items: Array, isVendor: Boolean });
const onlyPending = ref(true);
const selected = ref(null);
const form = useForm({ planned_date: '', reschedule_reason: '' });
const visible = computed(() => props.items.filter(item => !onlyPending.value || item.completed < item.total));
const date = value => value ? new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value).toLocaleString('id-ID', { timeZone: 'Asia/Makassar', dateStyle: 'medium' }) : '-';
const open = item => { selected.value = item.id; form.reset(); form.clearErrors(); };
const reschedule = item => form.patch(`/vendor/planning-lintas/items/${item.id}/reschedule`, { preserveScroll: true, onSuccess: () => { selected.value = null; } });
</script>

<template>
    <AppShell title="Monitoring Kedatangan Driver" subtitle="Kedatangan unit dibandingkan dengan planning izin." eyebrow="Planning dan Kedatangan">
        <label class="panel mb-5 flex items-center gap-3 text-sm font-semibold"><input v-model="onlyPending" type="checkbox" class="rounded border-slate-300" />Hanya planning dengan unit belum datang</label>
        <div class="grid gap-5 xl:grid-cols-2">
            <article v-for="item in visible" :key="item.id" class="panel min-w-0">
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0"><h2 class="break-words text-lg font-bold">{{ item.submission.vendor_name }}</h2><p class="text-xs text-slate-500">{{ item.submission.submission_number }}</p></div>
                    <span class="status-badge" :class="item.completed === item.total ? 'status-approved' : 'status-revision'">Completed {{ item.completed }}/{{ item.total }}</span>
                </header>
                <p class="mt-4 text-sm">Planning {{ date(item.planned_date) }} · Tujuan {{ item.submission.destination || '-' }}</p>
                <p class="mt-1 text-xs text-slate-500">Izin berlaku sampai {{ date(item.submission.release_expired_at) }}</p>
                <div v-if="item.reschedule_reason" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">Reschedule dari {{ date(item.previous_planned_date) }} ke {{ date(item.planned_date) }}</p>
                    <p class="mt-1 whitespace-pre-wrap break-words">Alasan Vendor: {{ item.reschedule_reason }}</p>
                </div>
                <div class="mt-4 space-y-2">
                    <h3 class="text-sm font-semibold">Belum datang ({{ item.pending_units.length }})</h3>
                    <div v-for="unit in item.pending_units" :key="unit.id" class="rounded-xl border border-slate-200 p-3 text-sm">
                        <b>{{ unit.plate_number }}</b><p class="break-words text-slate-600">{{ unit.driver_name || 'Nama driver belum diisi' }} · {{ unit.driver_phone || '-' }} · {{ unit.vehicle_type || '-' }}</p>
                    </div>
                    <p v-if="!item.pending_units.length" class="text-sm text-emerald-700">Semua unit sudah datang.</p>
                </div>
                <details v-if="item.arrived_units.length" class="mt-4 text-sm"><summary class="cursor-pointer font-semibold">Detail sudah datang ({{ item.completed }})</summary><p v-for="report in item.arrived_units" :key="report.id" class="mt-2 break-words">{{ report.vehicle_plate }} · {{ report.driver_name }} · {{ date(report.arrived_at) }}</p></details>
                <template v-if="isVendor && item.pending_units.length">
                    <button class="btn-ghost mt-4" :disabled="item.is_locked || new Date(item.submission.release_expired_at) < new Date()" @click="open(item)">Reschedule Sisa Kedatangan</button>
                    <p v-if="item.is_locked" class="mt-2 text-xs text-amber-700">Planning terkunci. Reviewer perlu membuka kunci sebelum reschedule.</p>
                    <form v-if="selected === item.id" class="mt-4 space-y-3 border-t pt-4" @submit.prevent="reschedule(item)">
                        <label class="block text-sm font-semibold">Tanggal baru<input v-model="form.planned_date" type="date" class="field" required :max="item.submission.release_expired_at?.slice(0, 10)" /><p class="err">{{ form.errors.planned_date }}</p></label>
                        <label class="block text-sm font-semibold">Alasan reschedule<textarea v-model="form.reschedule_reason" class="field" rows="3" required maxlength="2000" /><p class="err">{{ form.errors.reschedule_reason }}</p></label>
                        <div class="flex flex-wrap gap-3"><button class="btn" :disabled="form.processing">Simpan Reschedule</button><button type="button" class="btn-ghost" @click="selected = null">Batal</button></div>
                    </form>
                </template>
            </article>
        </div>
        <p v-if="!visible.length" class="panel text-center text-sm text-slate-500">Tidak ada planning yang sesuai filter.</p>
    </AppShell>
</template>
