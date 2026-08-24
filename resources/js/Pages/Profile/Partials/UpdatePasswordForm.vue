<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const show = ref(false), passwordInput = ref(null), currentPasswordInput = ref(null);
const form = useForm({ current_password:'', password:'', password_confirmation:'' });
const strength = computed(() => { const value=form.password; let score=0; if(value.length>=8)score++;if(/[A-Z]/.test(value)&&/[a-z]/.test(value))score++;if(/\d/.test(value))score++;if(/[^A-Za-z0-9]/.test(value))score++;return score; });
const updatePassword = () => form.put(route('password.update'), { preserveScroll:true, onSuccess:()=>form.reset(), onError:()=>{ if(form.errors.password){form.reset('password','password_confirmation');passwordInput.value?.focus();} if(form.errors.current_password){form.reset('current_password');currentPasswordInput.value?.focus();} } });
</script>

<template><section><div class="flex items-start gap-4 border-b border-slate-100 pb-5"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-600"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span><div><p class="text-[10px] font-black uppercase tracking-[.2em] text-red-600">Keamanan Sistem</p><h2 class="mt-1 text-2xl font-black text-slate-900">Ganti Password</h2><p class="mt-1 text-sm text-slate-500">Gunakan minimal 8 karakter dengan kombinasi huruf besar, kecil, angka, dan simbol.</p></div></div>
<form class="mt-6 space-y-5" @submit.prevent="updatePassword">
    <label class="block text-sm font-bold text-slate-700">Password saat ini<div class="relative mt-2"><input ref="currentPasswordInput" v-model="form.current_password" :type="show?'text':'password'" class="control pr-20" autocomplete="current-password" placeholder="Masukkan password yang sedang digunakan"><button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-black text-red-600" @click="show=!show">{{show?'Sembunyikan':'Lihat'}}</button></div><span class="err">{{form.errors.current_password}}</span></label>
    <label class="block text-sm font-bold text-slate-700">Password baru<input ref="passwordInput" v-model="form.password" :type="show?'text':'password'" class="control mt-2" autocomplete="new-password" placeholder="Minimal 8 karakter"><span class="err">{{form.errors.password}}</span></label>
    <div v-if="form.password" class="grid grid-cols-4 gap-2"><span v-for="index in 4" :key="index" class="h-1.5 rounded-full" :class="index<=strength?(strength<3?'bg-amber-400':'bg-emerald-500'):'bg-slate-100'"></span></div>
    <label class="block text-sm font-bold text-slate-700">Konfirmasi password baru<input v-model="form.password_confirmation" :type="show?'text':'password'" class="control mt-2" autocomplete="new-password" placeholder="Ketik ulang password baru"><span class="err">{{form.errors.password_confirmation}}</span></label>
    <div v-if="form.recentlySuccessful" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">✓ Password berhasil diperbarui. Gunakan password baru pada login berikutnya.</div>
    <div class="rounded-2xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-500"><b class="block text-slate-700">Tips keamanan akun</b>Jangan menggunakan nama perusahaan, username, atau password yang sama dengan akun lain.</div>
    <button class="btn w-full sm:w-auto sm:min-w-56" :disabled="form.processing">{{form.processing?'Memperbarui Password...':'Perbarui Password'}}</button>
</form></section></template>
