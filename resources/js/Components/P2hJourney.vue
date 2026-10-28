<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { journeyDuration, journeyTime } from '@/lib/p2hJourney';

const props = defineProps({ p2h: Object, arrivedAt: String });
const now = ref(Date.now());
let interval;
onMounted(() => { interval = window.setInterval(() => { if (!props.arrivedAt) now.value = Date.now(); }, 1000); });
onUnmounted(() => window.clearInterval(interval));
const duration = computed(() => journeyDuration(props.p2h?.created_at, props.arrivedAt, now.value));
const steps = computed(() => [
    ['P2H dikirim', props.p2h?.created_at],
    [props.p2h?.status === 'REJECTED' ? 'Ditolak vendor' : 'Disetujui vendor', props.p2h?.reviewed_at],
    ['Berangkat', props.p2h?.departed_at],
    ['Tiba di tujuan', props.arrivedAt],
]);
</script>

<template>
    <div v-if="p2h" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div v-for="[label, value] in steps" :key="label"><dt class="text-slate-500">{{ label }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ journeyTime(value) }}</dd></div>
        </dl>
        <p v-if="duration" class="mt-3 font-semibold text-slate-700">{{ arrivedAt ? 'Durasi P2H sampai tiba' : 'Waktu sejak P2H dikirim' }}: <span class="font-mono">{{ duration }}</span><span v-if="!arrivedAt"> · Berjalan</span></p>
    </div>
</template>
