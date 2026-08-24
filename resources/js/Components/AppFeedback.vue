<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
const page=usePage(); const notice=ref(null); let timer; let mutation=false; const stops=[];
const classes=computed(()=>notice.value?.type==='success'?'border-emerald-200 bg-emerald-50 text-emerald-800':'border-red-200 bg-red-50 text-red-800');
const show=(type,text)=>{clearTimeout(timer);notice.value={type,text};timer=setTimeout(()=>notice.value=null,5000);};
onMounted(()=>{stops.push(router.on('start',e=>mutation=String(e.detail.visit?.method||'get').toLowerCase()!=='get'),router.on('finish',()=>{if(!mutation)return;nextTick(()=>{const errors=page.props.errors||{};const success=page.props.flash?.success;if(success)show('success',success);else if(Object.keys(errors).length)show('error',Object.values(errors)[0]);mutation=false;});}));});
onUnmounted(()=>{stops.forEach(stop=>stop());clearTimeout(timer);});
</script>
<template><Transition enter-active-class="transition duration-300" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-200" leave-to-class="translate-y-4 opacity-0"><div v-if="notice" class="fixed bottom-24 right-4 z-[90] flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-2xl border p-4 shadow-2xl lg:bottom-6" :class="classes" role="alert"><div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/70 font-black">{{notice.type==='success'?'✓':'!'}}</div><div class="min-w-0 flex-1"><b class="text-sm">{{notice.type==='success'?'Berhasil diproses':'Data belum dapat disimpan'}}</b><p class="mt-1 text-xs leading-5">{{notice.text}}</p></div><button class="text-lg leading-none opacity-60" @click="notice=null">×</button></div></Transition></template>
