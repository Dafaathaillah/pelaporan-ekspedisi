<script setup>
import { ref } from "vue";

const props = defineProps({
    modelValue: File,
    label: String,
    required: Boolean,
    error: String,
    acceptTypes: { type: String, default: "application/pdf,.pdf" },
    maxMb: { type: Number, default: 3 },
    fileLabel: { type: String, default: "PDF" },
});
const emit = defineEmits(["update:modelValue"]);
const input = ref(null);
const zone = ref(null);
const dragging = ref(false);
const focused = ref(false);
const localError = ref("");
const readableSize = (bytes) =>
    bytes ? (bytes / 1024 / 1024).toFixed(2) + " MB" : "";
const allowed = (file) =>
    props.acceptTypes.split(",").some((raw) => {
        const type = raw.trim().toLowerCase();
        if (type.startsWith(".")) return file.name.toLowerCase().endsWith(type);
        if (type.endsWith("/*")) return file.type.startsWith(type.slice(0, -1));
        return file.type === type;
    });
const accept = (file) => {
    localError.value = "";
    if (!file) return;
    if (!allowed(file)) {
        localError.value = `File harus berupa ${props.fileLabel}.`;
        return;
    }
    if (file.size > props.maxMb * 1024 * 1024) {
        localError.value = `Ukuran maksimal file adalah ${props.maxMb} MB.`;
        return;
    }
    emit("update:modelValue", file);
};
const selected = (event) => accept(event.target.files?.[0]);
const dropped = (event) => {
    dragging.value = false;
    accept(event.dataTransfer.files?.[0]);
};
const pasted = (event) => {
    const file = [...(event.clipboardData?.files || [])][0];
    if (file) {
        event.preventDefault();
        accept(file);
    } else localError.value = `Clipboard tidak berisi ${props.fileLabel}.`;
};
const remove = (event) => {
    event.stopPropagation();
    emit("update:modelValue", null);
    if (input.value) input.value.value = "";
};
const activatePaste = () => zone.value?.focus();
const chooseFile = (event) => {
    event.stopPropagation();
    input.value?.click();
};
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between gap-3">
            <label class="text-sm font-black text-slate-800">{{ label }}</label>
            <span
                class="rounded-full px-2 py-1 text-[9px] font-black"
                :class="
                    required
                        ? 'bg-red-50 text-red-700'
                        : 'bg-slate-100 text-slate-500'
                "
                >{{ required ? "WAJIB" : "OPSIONAL" }}</span
            >
        </div>
        <div
            ref="zone"
            tabindex="0"
            role="group"
            :aria-label="
                'Area upload ' +
                label +
                '. Klik lalu paste file, drag and drop, atau gunakan tombol Choose File.'
            "
            class="file-drop-input"
            :class="{
                'file-drop-active': dragging || focused,
                'file-drop-filled': modelValue,
            }"
            @click="activatePaste"
            @focus="focused = true"
            @blur="focused = false"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="dropped"
            @paste="pasted"
        >
            <input
                ref="input"
                type="file"
                :accept="acceptTypes"
                class="hidden"
                @change="selected"
            />
            <div
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
                :class="
                    modelValue
                        ? 'bg-emerald-50 text-emerald-600'
                        : 'bg-red-50 text-red-600'
                "
            >
                {{ modelValue ? "✓" : "+" }}
            </div>
            <div class="min-w-0 flex-1">
                <template v-if="modelValue">
                    <p class="truncate text-sm font-bold text-slate-800">
                        {{ modelValue.name }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ readableSize(modelValue.size) }} ·
                        {{ fileLabel }} siap diunggah
                    </p>
                </template>
                <template v-else>
                    <p class="text-sm font-semibold text-slate-600">
                        <span class="font-black text-red-600"
                            >Klik area ini lalu tekan Ctrl+V</span
                        >
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        Atau drag & drop · {{ fileLabel }} maksimal
                        {{ maxMb }} MB
                    </p>
                </template>
            </div>
            <div class="flex shrink-0 flex-col gap-1.5 sm:flex-row">
                <button
                    type="button"
                    class="choose-file-button"
                    @click="chooseFile"
                >
                    {{ modelValue ? "Ganti File" : "Choose File" }}
                </button>
                <button
                    v-if="modelValue"
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-red-50 hover:text-red-700"
                    @click="remove"
                >
                    Hapus
                </button>
            </div>
        </div>
        <p v-if="localError || error" class="err">
            {{ localError || error }}
        </p>
    </div>
</template>
