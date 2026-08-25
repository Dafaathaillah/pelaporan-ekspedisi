<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";

const props = defineProps({
    modelValue: { type: [String, Number, null], default: "" },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: "Pilih data" },
    searchPlaceholder: { type: String, default: "Cari pilihan..." },
    disabled: Boolean,
});
const emit = defineEmits(["update:modelValue", "change"]);
const root = ref(null);
const searchInput = ref(null);
const open = ref(false);
const search = ref("");
const activeIndex = ref(0);
const position = ref({});
const normalized = computed(() =>
    props.options.map((option) =>
        typeof option === "object"
            ? option
            : { value: option, label: String(option) },
    ),
);
const selected = computed(() =>
    normalized.value.find(
        (option) => String(option.value) === String(props.modelValue ?? ""),
    ),
);
const filtered = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase("id-ID");
    if (!needle) return normalized.value;
    return normalized.value.filter((option) =>
        `${option.label} ${option.keywords || ""}`
            .toLocaleLowerCase("id-ID")
            .includes(needle),
    );
});
const updatePosition = () => {
    if (!root.value || !open.value) return;
    const rect = root.value.getBoundingClientRect();
    const maxHeight = Math.min(288, window.innerHeight - 24);
    const spaceBelow = window.innerHeight - rect.bottom - 12;
    const above = spaceBelow < 220 && rect.top > spaceBelow;
    position.value = {
        left: `${Math.max(12, rect.left)}px`,
        width: `${Math.min(rect.width, window.innerWidth - 24)}px`,
        maxHeight: `${maxHeight}px`,
        ...(above
            ? { bottom: `${window.innerHeight - rect.top + 8}px` }
            : { top: `${rect.bottom + 8}px` }),
    };
};
const show = async () => {
    if (props.disabled) return;
    open.value = true;
    search.value = "";
    activeIndex.value = Math.max(
        0,
        filtered.value.findIndex(
            (option) => String(option.value) === String(props.modelValue ?? ""),
        ),
    );
    await nextTick();
    updatePosition();
    searchInput.value?.focus();
};
const close = () => {
    open.value = false;
    search.value = "";
};
const choose = (option) => {
    emit("update:modelValue", option.value);
    emit("change", option.value);
    close();
};
const keydown = (event) => {
    if (event.key === "ArrowDown") {
        event.preventDefault();
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            filtered.value.length - 1,
        );
    } else if (event.key === "ArrowUp") {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    } else if (event.key === "Enter" && filtered.value[activeIndex.value]) {
        event.preventDefault();
        choose(filtered.value[activeIndex.value]);
    } else if (event.key === "Escape") close();
};
const outside = (event) => {
    if (open.value && !root.value?.contains(event.target)) close();
};
onMounted(() => {
    document.addEventListener("pointerdown", outside);
    window.addEventListener("resize", updatePosition);
    window.addEventListener("scroll", updatePosition, true);
});
onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", outside);
    window.removeEventListener("resize", updatePosition);
    window.removeEventListener("scroll", updatePosition, true);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="control flex w-full items-center justify-between gap-3 text-left"
            :class="
                disabled
                    ? 'cursor-not-allowed bg-slate-100 text-slate-400'
                    : 'bg-white'
            "
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open ? close() : show()"
            @keydown="keydown"
        >
            <span
                class="min-w-0 flex-1 truncate"
                :class="selected ? 'text-slate-900' : 'text-slate-400'"
                >{{ selected?.label || placeholder }}</span
            >
            <svg
                class="h-4 w-4 shrink-0 text-slate-400 transition"
                :class="{ 'rotate-180': open }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed z-[120] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_22px_60px_rgba(15,23,42,.22)]"
                :style="position"
                @pointerdown.stop
            >
                <div class="border-b border-slate-100 p-2.5">
                    <div class="relative">
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" />
                        </svg>
                        <input
                            ref="searchInput"
                            v-model="search"
                            class="control h-10 pl-9"
                            :placeholder="searchPlaceholder"
                            autocomplete="off"
                            @keydown="keydown"
                        />
                    </div>
                </div>
                <div
                    class="max-h-56 overflow-y-auto overscroll-contain p-2"
                    role="listbox"
                >
                    <button
                        v-for="(option, index) in filtered"
                        :key="String(option.value)"
                        type="button"
                        class="flex w-full items-start justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition"
                        :class="[
                            index === activeIndex
                                ? 'bg-red-50 text-red-700'
                                : 'text-slate-700 hover:bg-slate-50',
                            String(option.value) === String(modelValue ?? '')
                                ? 'font-bold'
                                : '',
                        ]"
                        role="option"
                        @mouseenter="activeIndex = index"
                        @click="choose(option)"
                    >
                        <span class="whitespace-normal break-words leading-5">{{
                            option.label
                        }}</span>
                        <span
                            v-if="
                                String(option.value) ===
                                String(modelValue ?? '')
                            "
                            class="shrink-0 text-red-600"
                            >✓</span
                        >
                    </button>
                    <p
                        v-if="!filtered.length"
                        class="px-3 py-8 text-center text-sm text-slate-400"
                    >
                        Pilihan tidak ditemukan.
                    </p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
