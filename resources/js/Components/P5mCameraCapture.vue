<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
const props = defineProps({ active: { type: Boolean, default: false }, error: { type: String, default: "" } });
const emit = defineEmits(["captured"]);
const cameraInput = ref(null), galleryInput = ref(null), previewUrl = ref("");
const clearPreview = () => { if (previewUrl.value) URL.revokeObjectURL(previewUrl.value); previewUrl.value = ""; };
const resetCapture = () => { clearPreview(); emit("captured", null); };
const selectPhoto = event => {
    const file = event.target.files?.[0]; if (!file) return;
    clearPreview(); previewUrl.value = URL.createObjectURL(file); emit("captured", file); event.target.value = "";
};
watch(() => props.active, active => { if (!active) resetCapture(); });
onBeforeUnmount(clearPreview);
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950" aria-label="Foto P5M">
        <div v-if="previewUrl" class="bg-slate-950">
            <img :src="previewUrl" alt="Preview foto P5M" class="max-h-[56vh] min-h-64 w-full object-contain" />
            <p class="border-t border-white/10 px-4 py-3 text-center text-xs font-semibold text-emerald-300">Foto P5M siap digunakan</p>
        </div>
        <div v-else class="grid min-h-64 place-items-center px-6 py-10 text-center text-white">
            <div><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-red-500/15 text-red-300"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3"/></svg></span><p class="mt-4 font-bold">Tambahkan Foto P5M</p><p class="mt-2 text-sm text-slate-300">Ambil foto menggunakan kamera atau pilih foto yang sudah ada.</p></div>
        </div>
        <div class="grid gap-3 border-t border-white/10 bg-slate-900 p-4 sm:grid-cols-2">
            <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="selectPhoto" />
            <input ref="galleryInput" type="file" accept="image/*" class="hidden" @change="selectPhoto" />
            <button type="button" class="min-h-12 rounded-xl bg-red-600 px-4 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300" @click="cameraInput?.click()">Buka Kamera</button>
            <button type="button" class="min-h-12 rounded-xl border border-white/25 bg-white/10 px-4 text-sm font-bold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-red-400" @click="galleryInput?.click()">Pilih / Upload Foto</button>
        </div>
        <p v-if="error" class="border-t border-red-400/30 bg-red-950 px-4 py-3 text-sm font-semibold text-red-200" role="alert">{{ error }}</p>
    </section>
</template>
