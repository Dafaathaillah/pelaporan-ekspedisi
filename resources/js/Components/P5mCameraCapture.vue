<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps({
    active: { type: Boolean, default: false },
    error: { type: String, default: "" },
});
const emit = defineEmits(["captured"]);

const video = ref(null);
const canvas = ref(null);
const stream = ref(null);
const previewUrl = ref("");
const cameraError = ref("");
const starting = ref(false);
const cameraRequest = ref(0);

const stopCamera = () => {
    cameraRequest.value += 1;
    stream.value?.getTracks().forEach((track) => track.stop());
    stream.value = null;
    if (video.value) video.value.srcObject = null;
};

const clearPreview = () => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = "";
};

const resetCapture = () => {
    clearPreview();
    emit("captured", null);
};

const cameraMessage = (error) => {
    if (error?.name === "NotAllowedError" || error?.name === "SecurityError") {
        return "Izin kamera ditolak. Aktifkan izin kamera browser lalu coba lagi.";
    }
    if (error?.name === "NotFoundError") {
        return "Kamera tidak ditemukan pada perangkat ini.";
    }
    if (error?.name === "NotReadableError") {
        return "Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.";
    }
    return "Kamera gagal dibuka. Pastikan perangkat berkamera dan akses dilakukan melalui HTTPS atau localhost.";
};

const startCamera = async () => {
    stopCamera();
    resetCapture();
    cameraError.value = "";

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value =
            "Kamera tidak tersedia. Gunakan perangkat berkamera melalui HTTPS atau localhost.";
        return;
    }

    const requestId = ++cameraRequest.value;
    starting.value = true;

    try {
        await nextTick();
        const mediaStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: { ideal: "environment" } },
        });

        if (requestId !== cameraRequest.value || !props.active) {
            mediaStream.getTracks().forEach((track) => track.stop());
            return;
        }

        stream.value = mediaStream;
        video.value.srcObject = mediaStream;
        await video.value.play();
    } catch (error) {
        if (requestId === cameraRequest.value && props.active) {
            cameraError.value = cameraMessage(error);
        }
    } finally {
        if (requestId === cameraRequest.value) starting.value = false;
    }
};

const capturePhoto = () => {
    const width = video.value?.videoWidth;
    const height = video.value?.videoHeight;

    if (!width || !height) {
        cameraError.value =
            "Kamera belum siap. Tunggu sebentar lalu coba kembali.";
        return;
    }

    canvas.value.width = width;
    canvas.value.height = height;
    canvas.value
        .getContext("2d")
        .drawImage(video.value, 0, 0, width, height);
    canvas.value.toBlob(
        (blob) => {
            if (!blob) {
                cameraError.value =
                    "Foto gagal diproses. Silakan ambil foto ulang.";
                return;
            }

            stopCamera();
            clearPreview();
            previewUrl.value = URL.createObjectURL(blob);
            emit(
                "captured",
                new File([blob], `p5m-${Date.now()}.jpg`, {
                    type: "image/jpeg",
                }),
            );
        },
        "image/jpeg",
        0.9,
    );
};

const retake = () => startCamera();

watch(
    () => props.active,
    (active) => {
        if (active) startCamera();
        else {
            stopCamera();
            resetCapture();
            cameraError.value = "";
            starting.value = false;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopCamera();
    clearPreview();
});
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950"
        aria-label="Kamera foto P5M"
    >
        <div
            v-if="starting"
            class="grid min-h-72 place-items-center px-6 text-center text-sm font-semibold text-white"
            aria-live="polite"
        >
            Menyiapkan kamera belakang...
        </div>

        <div
            v-else-if="cameraError"
            class="grid min-h-72 place-items-center px-6 py-10 text-center"
            role="alert"
        >
            <div class="max-w-sm">
                <div
                    class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-red-500/15 text-red-300"
                >
                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="M14.5 4 16 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3l1.5-3h5Z" />
                        <path d="m9 10 6 6m0-6-6 6" />
                    </svg>
                </div>
                <p class="mt-4 text-sm font-bold text-white">
                    Kamera belum dapat digunakan
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    {{ cameraError }}
                </p>
                <button
                    type="button"
                    class="mt-5 min-h-11 rounded-xl bg-white px-4 text-sm font-bold text-slate-900 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-400"
                    @click="startCamera"
                >
                    Coba Aktifkan Kamera Lagi
                </button>
            </div>
        </div>

        <div v-else-if="previewUrl" class="relative bg-slate-950">
            <img
                :src="previewUrl"
                alt="Preview foto P5M yang baru diambil"
                class="max-h-[62vh] min-h-72 w-full object-contain"
            />
            <div class="border-t border-white/10 bg-slate-900 p-3">
                <button
                    type="button"
                    class="min-h-11 w-full rounded-xl border border-white/20 px-4 text-sm font-bold text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-red-400"
                    @click="retake"
                >
                    Foto Ulang
                </button>
            </div>
        </div>

        <div v-else class="relative min-h-72 bg-black">
            <video
                ref="video"
                autoplay
                muted
                playsinline
                class="max-h-[62vh] min-h-72 w-full object-cover"
            ></video>
            <div
                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-4 pt-12"
            >
                <button
                    type="button"
                    class="min-h-12 w-full rounded-xl bg-red-600 px-4 text-sm font-bold text-white shadow-lg transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!stream"
                    @click="capturePhoto"
                >
                    Ambil Foto P5M
                </button>
            </div>
        </div>

        <canvas ref="canvas" class="hidden"></canvas>
        <p
            v-if="error"
            class="border-t border-red-400/30 bg-red-950 px-4 py-3 text-sm font-semibold text-red-200"
            role="alert"
        >
            {{ error }}
        </p>
    </section>
</template>
