<script setup>
import { onMounted, ref } from "vue";
import { getPushState, subscribeToPush } from "@/pushNotifications";

const state = ref({
    supported: false,
    permission: "default",
    subscribed: false,
});
const busy = ref(false);
const hidden = ref(false);
const error = ref("");

onMounted(async () => {
    try {
        state.value = await getPushState();
    } catch {
        /* prompt remains hidden on unsupported setup */
    }
});

const activate = async () => {
    busy.value = true;
    error.value = "";
    try {
        state.value = await subscribeToPush();
        hidden.value = true;
        window.dispatchEvent(
            new CustomEvent("ppa:feedback", {
                detail: {
                    type: "success",
                    message: "Notifikasi Chrome berhasil diaktifkan.",
                },
            }),
        );
    } catch (exception) {
        error.value =
            exception.response?.data?.message ||
            exception.message ||
            "Notifikasi gagal diaktifkan.";
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300"
        enter-from-class="translate-y-4 opacity-0"
        leave-active-class="transition duration-200"
        leave-to-class="translate-y-4 opacity-0"
    >
        <aside
            v-if="
                state.supported &&
                !state.subscribed &&
                !hidden &&
                state.permission !== 'denied'
            "
            class="fixed bottom-[calc(5.8rem+env(safe-area-inset-bottom))] right-3 z-[70] w-[calc(100%-1.5rem)] max-w-sm rounded-2xl border border-red-100 bg-white p-4 shadow-[0_20px_60px_rgba(15,23,42,.2)] lg:bottom-5 lg:right-5"
        >
            <div class="flex gap-3">
                <img
                    src="/images/push-icon-192.png"
                    alt=""
                    class="h-12 w-12 rounded-xl border border-red-100 object-contain p-1"
                />
                <div class="min-w-0 flex-1">
                    <p
                        class="text-[10px] font-black uppercase tracking-[.16em] text-red-600"
                    >
                        Notifikasi Ekspedisi
                    </p>
                    <h2 class="mt-1 text-sm font-black text-slate-950">
                        Tetap mendapat kabar saat web ditutup
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Aktifkan untuk menerima request, surat sehat, planning,
                        kedatangan, dan escort secara realtime.
                    </p>
                </div>
                <button
                    type="button"
                    class="h-7 w-7 shrink-0 rounded-lg text-slate-400 hover:bg-slate-100"
                    aria-label="Nanti saja"
                    @click="hidden = true"
                >
                    ×
                </button>
            </div>
            <p
                v-if="error"
                class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"
            >
                {{ error }}
            </p>
            <button
                type="button"
                :disabled="busy"
                class="mt-3 w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-red-200 transition hover:bg-red-700 disabled:opacity-60"
                @click="activate"
            >
                {{ busy ? "Mengaktifkan..." : "Aktifkan Notifikasi Chrome" }}
            </button>
        </aside>
    </Transition>
</template>
