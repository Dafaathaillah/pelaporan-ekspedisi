<script setup>
import AppFooter from '@/Components/AppFooter.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const showPassword = ref(false);
const form = useForm({ username: '', password: '' });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
<Head title="Portal Masuk" />
<div class="flex min-h-screen flex-col bg-[#fffafa] text-[#101b35]">
    <main class="flex flex-1 items-stretch p-3 sm:p-5 lg:p-7">
        <div class="mx-auto grid w-full max-w-[1480px] overflow-hidden rounded-[28px] border border-red-100 bg-white shadow-[0_24px_80px_rgba(127,29,29,.12)] lg:grid-cols-[1.2fr_.8fr]">
            <section class="relative flex min-h-[270px] overflow-hidden bg-gradient-to-br from-[#8f1018] via-[#c81e2a] to-[#ef4444] p-7 text-white sm:p-10 lg:min-h-[680px] lg:p-14">
                <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full border-[55px] border-white/10"></div><div class="absolute -bottom-36 left-1/4 h-96 w-96 rounded-full border-[70px] border-black/5"></div><div class="absolute right-16 top-1/2 hidden h-24 w-24 rotate-12 rounded-3xl bg-white/5 lg:block"></div>
                <div class="relative z-10 flex w-full flex-col">
                    <div class="flex items-center gap-4"><div class="grid h-14 w-14 place-items-center rounded-2xl border border-white/30 bg-white text-sm font-black text-red-700 shadow-xl">PPA</div><div><p class="font-black tracking-wide">PUTRA PERKASA ABADI</p><p class="mt-1 text-[10px] font-bold tracking-[.24em] text-red-100">SITE BORNEO INDOBARA</p></div></div>
                    <div class="my-auto max-w-2xl py-9 lg:py-14"><p class="text-[11px] font-semibold uppercase tracking-[.24em] text-red-100">Digital Report & Vendor Permit</p><h1 class="mt-4 text-3xl font-semibold leading-[1.08] tracking-[-.04em] text-white sm:text-4xl lg:text-6xl">Satu akses untuk operasional yang lebih tertata.</h1><p class="mt-5 max-w-xl text-[15px] leading-7 text-white/90 lg:text-[17px]">Pelaporan kedatangan ekspedisi, pengajuan dokumen vendor, serta monitoring persetujuan dalam satu sistem yang aman dan terintegrasi.</p>
                        <div class="mt-8 hidden grid-cols-3 gap-3 sm:grid"><div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"><b class="text-xl">01</b><p class="mt-2 text-xs text-red-50">Laporan ekspedisi</p></div><div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"><b class="text-xl">04</b><p class="mt-2 text-xs text-red-50">Jenis pengajuan</p></div><div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"><b class="text-xl">24/7</b><p class="mt-2 text-xs text-red-50">Monitoring data</p></div></div>
                    </div>
                    <div class="hidden items-center gap-2 text-xs text-red-100 lg:flex"><span class="grid h-7 w-7 place-items-center rounded-full bg-white/10">✓</span> Akses terlindungi sesuai role dan kewenangan pengguna</div>
                </div>
            </section>
            <section class="flex items-center bg-white px-6 py-9 sm:px-10 lg:px-14">
                <form class="mx-auto w-full max-w-md" @submit.prevent="submit">
                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[.14em] text-red-700">Portal Operasional</span><h2 class="mt-5 text-3xl font-semibold tracking-[-.035em] sm:text-4xl">Selamat datang</h2><p class="mt-3 text-[15px] leading-6 text-slate-500">Masuk menggunakan akun yang telah diberikan administrator sistem.</p>
                    <label class="mt-8 block text-sm font-black text-slate-700">Username<div class="relative mt-2"><span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">@</span><input v-model="form.username" autofocus autocomplete="username" class="w-full rounded-2xl border-slate-200 bg-slate-50 py-4 pl-11 pr-4 text-sm font-semibold placeholder:font-normal focus:border-red-400 focus:bg-white focus:ring-red-100" placeholder="Masukkan username"></div></label><p class="err">{{form.errors.username}}</p>
                    <label class="mt-5 block text-sm font-black text-slate-700">Password<div class="relative mt-2"><span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">●</span><input v-model="form.password" :type="showPassword?'text':'password'" autocomplete="current-password" class="w-full rounded-2xl border-slate-200 bg-slate-50 py-4 pl-11 pr-20 text-sm font-semibold placeholder:font-normal focus:border-red-400 focus:bg-white focus:ring-red-100" placeholder="Masukkan password"><button type="button" class="absolute inset-y-0 right-4 text-xs font-black text-red-600" @click="showPassword=!showPassword">{{showPassword?'Sembunyikan':'Lihat'}}</button></div></label><p class="err">{{form.errors.password}}</p>
                    <button :disabled="form.processing" class="btn mt-7 flex w-full items-center justify-center gap-3 rounded-2xl py-4">{{form.processing?'MEMVERIFIKASI...':'MASUK KE SISTEM'}}<span v-if="!form.processing">→</span></button>
                    <div class="mt-7 flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600">✓</span><div><b class="text-xs text-slate-700">Sistem terproteksi</b><p class="mt-1 text-[11px] leading-5 text-slate-500">Menu dan data ditampilkan sesuai role serta hak akses akun Anda.</p></div></div>
                </form>
            </section>
        </div>
    </main>
    <AppFooter/>
</div>
</template>
