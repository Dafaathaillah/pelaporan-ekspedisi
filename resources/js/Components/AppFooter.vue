<script setup>
const year = new Date().getFullYear();
</script>
<template>
    <footer class="app-footer">
        <div class="app-footer-glow"></div>
        <div class="relative mx-auto flex max-w-[1500px] flex-col items-center justify-between gap-4 px-5 py-6 sm:flex-row">
            <div class="flex items-center gap-3">
                <div class="footer-mark"><span class="text-sm font-black">PPA</span></div>
                <div><p class="text-[10px] font-bold uppercase tracking-[.18em] text-slate-400">Sinergi Operasional Terintegrasi</p><p class="mt-1 text-sm font-black tracking-wide text-slate-700">Kolaborasi <span class="text-red-600">SCM · FAW · ICT</span></p></div>
            </div>
            <div class="text-center sm:text-right"><p class="text-xs font-semibold text-slate-500">Bersama membangun proses kerja yang aman, cepat, dan transparan.</p><p class="mt-1 text-[10px] text-slate-400">Digital Report & Vendor Permit · © {{year}} PT Putra Perkasa Abadi · Site BIB</p></div>
        </div>
    </footer>
</template>
