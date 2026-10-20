<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({ arrivedAt: String, escortStartedAt: String });
const now = ref(Date.now());
let interval;
onMounted(() => { if (!props.escortStartedAt) interval = window.setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => { if (interval) window.clearInterval(interval); });
const duration = computed(() => {
    if (!props.arrivedAt) return null;
    const start = new Date(props.arrivedAt).getTime();
    const end = props.escortStartedAt ? new Date(props.escortStartedAt).getTime() : now.value;
    if (!Number.isFinite(start) || !Number.isFinite(end)) return null;
    const seconds = Math.max(0, Math.floor((end - start) / 1000));
    return `${String(Math.floor(seconds / 3600)).padStart(2, '0')}:${String(Math.floor(seconds % 3600 / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
});
</script>

<template><span v-if="duration" class="inline-flex whitespace-nowrap rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700" :title="escortStartedAt ? 'Berhenti saat escort dimulai' : 'Berjalan sejak kedatangan'">{{ duration }}{{ escortStartedAt ? ' · Selesai' : ' · Berjalan' }}</span></template>
