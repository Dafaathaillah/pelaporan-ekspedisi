<script setup>
const year = new Date().getFullYear();
</script>
<template>
    <footer class="app-footer" aria-label="Informasi aplikasi">
        <div class="app-footer-glow"></div>
        <div
            class="relative mx-auto flex w-full max-w-[1500px] flex-col items-start justify-between gap-4 px-4 py-5 sm:flex-row sm:items-center sm:px-6 lg:px-8"
        >
            <div class="flex min-w-0 items-center gap-3">
                <div class="footer-mark">
                    <span class="text-sm font-black">PPA</span>
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-bold uppercase leading-4 tracking-[.16em] text-slate-400"
                    >
                        Sinergi Operasional Terintegrasi
                    </p>
                    <p
                        class="mt-0.5 text-sm font-black leading-5 tracking-wide text-slate-700"
                    >
                        Kolaborasi
                        <span class="text-red-600">SCM · FAW · ICT</span>
                    </p>
                </div>
            </div>
            <div class="max-w-xl text-left sm:text-right">
                <p class="text-xs font-semibold leading-5 text-slate-500">
                    Bersama membangun proses kerja yang aman, cepat, dan
                    transparan.
                </p>
                <p class="mt-0.5 text-[10px] leading-4 text-slate-400">
                    Digital Report &amp; Vendor Permit · © {{ year }} PT Putra
                    Perkasa Abadi · Site BIB
                </p>
            </div>
        </div>
    </footer>
</template>
