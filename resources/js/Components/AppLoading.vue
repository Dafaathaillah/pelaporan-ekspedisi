<script setup>
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const visible = ref(false);
const loadingDialog = ref(null);
const message = ref('Memuat data...');
let startedAt = 0; let hideTimer; const unsubscribers = [];

const destinationName = rawUrl => {
    const url = decodeURIComponent(String(rawUrl || '')).toUpperCase();
    if (url.includes('/COORDINATOR/SUBMISSIONS/PERMIT_VENDOR_NON_SIMPER')) return 'Permit Vendor Non Simper';
    if (url.includes('/COORDINATOR/SUBMISSIONS/IJIN_LINTAS')) return 'Ijin Lintas';
    if (url.includes('/COORDINATOR/SUBMISSIONS/VISITOR')) return 'Visitor';
    if (url.includes('/COORDINATOR/SUBMISSIONS/WASKAT')) return 'Waskat';
    if (url.includes('/COORDINATOR/USERS')) return 'Manajemen Pengguna';
    if (url.includes('/COORDINATOR/SUBMISSION/')) return 'Detail Pengajuan';
    if (url.includes('/COORDINATOR')) return 'Dashboard Ekspedisi';
    if (url.includes('/VENDOR/SUBMISSIONS/')) return 'Detail Pengajuan Vendor';
    if (url.includes('/VENDOR')) return 'Pengajuan Vendor';
    if (url.includes('/EXPEDITION')) return 'Laporan Kedatangan';
    if (url.includes('/PROFILE')) return 'Profil Pengguna';
    return 'Operasional';
};
const resolveMessage = visit => {
    const method = String(visit?.method || 'get').toLowerCase();
    const url = String(visit?.url || '');
    if (url.includes('logout')) return 'Mengakhiri sesi dengan aman...';
    if (url.includes('login')) return 'Memverifikasi akses Anda...';
    if (method === 'post') return 'Menyimpan data operasional...';
    if (['patch','put'].includes(method)) return 'Memperbarui data operasional...';
    if (method === 'delete') return 'Menghapus data dengan aman...';
    return `Menyiapkan data ${destinationName(url)}...`;
};
const show = event => { if (event.detail.visit?.showProgress === false) return; clearTimeout(hideTimer); startedAt=Date.now(); message.value=resolveMessage(event.detail.visit); visible.value=true; };
const hide = () => { const wait=Math.max(0,450-(Date.now()-startedAt)); hideTimer=setTimeout(()=>visible.value=false,wait); };
watch(visible, value => { if (value && !loadingDialog.value?.open) loadingDialog.value?.showModal(); if (!value && loadingDialog.value?.open) loadingDialog.value?.close(); });
onMounted(() => { unsubscribers.push(router.on('start',show),router.on('finish',hide),router.on('invalid',hide),router.on('exception',hide)); });
onUnmounted(() => { unsubscribers.forEach(stop=>stop()); clearTimeout(hideTimer); });
</script>

<template><dialog ref="loadingDialog" class="m-0 h-full max-h-none w-full max-w-none border-0 bg-transparent p-0 backdrop:bg-transparent"><Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-200" leave-to-class="opacity-0"><div v-if="visible" class="fixed inset-0 grid place-items-center bg-slate-950/45 p-5 backdrop-blur-sm" role="status" aria-live="polite"><div class="w-full max-w-sm overflow-hidden rounded-3xl border border-white/70 bg-white p-6 text-center shadow-[0_30px_100px_rgba(15,23,42,.35)]"><div class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-red-50 to-white shadow-inner"><div class="loading-truck text-red-600"><svg class="h-11 w-11" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13h24v21H5z"/><path d="M29 21h8l6 7v6H29z"/><circle cx="13" cy="37" r="4" fill="white"/><circle cx="37" cy="37" r="4" fill="white"/><path d="M9 19h13M9 25h9"/></svg></div></div><p class="mt-5 text-[10px] font-black uppercase tracking-[.24em] text-red-600">PPA · Site Borneo Indobara</p><h2 class="mt-2 text-lg font-black text-slate-950">{{message}}</h2><p class="mt-2 text-xs text-slate-500">Mohon tunggu, sistem sedang memproses permintaan Anda.</p><div class="mt-5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="loading-progress h-full w-1/3 rounded-full bg-gradient-to-r from-red-700 to-red-400"></div></div></div></div></Transition></dialog></template>

<style scoped>
.loading-truck{animation:truck-bounce .75s ease-in-out infinite alternate}.loading-progress{animation:progress-drive 1.15s ease-in-out infinite}@keyframes truck-bounce{to{transform:translateY(-5px) rotate(-1deg)}}@keyframes progress-drive{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
</style>
