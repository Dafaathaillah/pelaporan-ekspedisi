<script setup>
import Modal from "@/Components/Modal.vue";
defineProps({
    show: Boolean,
    title: String,
    message: String,
    confirmText: { type: String, default: "Lanjutkan" },
    danger: { type: Boolean, default: false },
});
defineEmits(["close", "confirm"]);
</script>
<template>
    <Modal :show="show" max-width="md" @close="$emit('close')"
        ><div class="p-6 sm:p-7">
            <div class="flex items-start gap-4">
                <div
                    class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl"
                    :class="
                        danger
                            ? 'bg-red-50 text-red-700'
                            : 'bg-amber-50 text-amber-700'
                    "
                >
                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M12 3 2 21h20z" />
                        <path d="M12 9v5M12 18h.01" />
                    </svg>
                </div>
                <div>
                    <p
                        class="text-[10px] font-black uppercase tracking-[.18em] text-red-600"
                    >
                        Konfirmasi tindakan
                    </p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">
                        {{ title }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        {{ message }}
                    </p>
                </div>
            </div>
            <div
                class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
            >
                <button type="button" class="btn-ghost" @click="$emit('close')">
                    Batal</button
                ><button
                    type="button"
                    class="btn"
                    :class="
                        danger
                            ? 'bg-red-700 hover:bg-red-800'
                            : 'bg-slate-900 hover:bg-slate-800'
                    "
                    @click="$emit('confirm')"
                >
                    {{ confirmText }}
                </button>
            </div>
        </div></Modal
    >
</template>
