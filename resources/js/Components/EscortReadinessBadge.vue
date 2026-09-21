<script setup>
import {
    formatEscortCountdown,
    isEscortReady,
    remainingEscortSeconds,
} from "@/lib/escortReadiness";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
    item: { type: Object, required: true },
    showReadyAt: { type: Boolean, default: true },
});

const now = ref(Date.now());
let timer;

const isInternal = computed(() =>
    Boolean(props.item.internal_ijin_lintas_id || props.item.internal_ijin_lintas),
);
const visible = computed(
    () => !isInternal.value && props.item.escort_status === "WAITING",
);
const ready = computed(() => isEscortReady(props.item, now.value));
const countdown = computed(() =>
    formatEscortCountdown(
        remainingEscortSeconds(props.item.ready_escort_at, now.value),
    ),
);
const readyLabel = computed(() => {
    if (!props.item.ready_escort_at) return "-";

    return new Intl.DateTimeFormat("id-ID", {
        timeZone: "Asia/Makassar",
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    }).format(new Date(props.item.ready_escort_at));
});

onMounted(() => {
    timer = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => window.clearInterval(timer));
</script>

<template>
    <span
        v-if="visible && ready"
        class="inline-flex w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-700"
    >
        Ready Escort
    </span>
    <div
        v-else-if="visible"
        class="w-fit rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800"
    >
        <span class="block text-[9px] font-black uppercase tracking-wide"
            >Ready Escort dalam</span
        >
        <b class="font-mono text-sm tabular-nums">{{ countdown }}</b>
        <small v-if="showReadyAt" class="block text-[10px] font-semibold"
            >{{ readyLabel }} WITA</small
        >
    </div>
</template>
