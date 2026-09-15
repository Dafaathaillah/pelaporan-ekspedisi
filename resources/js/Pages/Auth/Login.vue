<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const showPassword = ref(false);
const form = useForm({ username: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Portal Masuk" />
    <main class="auth-page relative isolate h-[100dvh] overflow-hidden text-white">
        <div class="auth-backdrop absolute inset-0 -z-20"></div>
        <div class="auth-overlay absolute inset-0 -z-10"></div>

        <div class="mx-auto flex h-full min-h-0 w-full max-w-[1600px] flex-col px-4 py-4 sm:px-7 sm:py-5 lg:px-10 xl:px-14">
            <header class="auth-enter flex items-center justify-between" aria-label="Identitas aplikasi">
                <div class="flex min-w-0 items-center gap-3.5 sm:gap-4">
                    <div class="grid h-12 w-[72px] shrink-0 place-items-center overflow-hidden rounded-2xl border border-white/40 bg-white/90 p-1 shadow-[0_10px_30px_rgba(15,23,42,.18)] sm:h-14 sm:w-[84px]">
                        <img src="/images/logo-ekspedisi-ppa.png" alt="Logo Ekspedisi PPA" class="h-full w-full object-contain" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[12px] font-bold tracking-[.08em] text-white sm:text-[14px]">PUTRA PERKASA ABADI</p>
                        <p class="mt-0.5 text-[9px] font-semibold tracking-[.2em] text-white/65 sm:text-[10px]">SITE BORNEO INDOBARA</p>
                    </div>
                </div>
                <div class="hidden items-center gap-2 rounded-full border border-white/15 bg-black/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-[.16em] text-white/70 backdrop-blur-md sm:flex">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Pelaporan Ekspedisi
                </div>
            </header>

            <div class="auth-layout grid min-h-0 flex-1 items-center gap-8 py-4 md:grid-cols-[minmax(0,1fr)_minmax(400px,480px)] md:py-5 lg:gap-14 xl:grid-cols-[minmax(0,1fr)_minmax(460px,520px)] xl:gap-24">
                <section class="auth-intro auth-enter hidden max-w-[690px] md:block" aria-labelledby="auth-intro-title">
                    <p class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[.24em] text-white/75"><span class="h-px w-8 bg-[#f25a61]"></span>Portal Operasional</p>
                    <h1 id="auth-intro-title" class="mt-5 max-w-[620px] text-[clamp(2.7rem,5vw,5rem)] font-semibold leading-[1.02] tracking-[-.055em] text-white">Satu akses untuk operasional yang lebih tertata.</h1>
                    <p class="mt-6 max-w-[610px] text-[15px] leading-7 text-white/75 lg:text-[17px] lg:leading-8">Pelaporan kedatangan ekspedisi, pengajuan dokumen vendor, serta monitoring persetujuan dalam satu sistem yang aman dan terintegrasi.</p>

                    <div class="auth-stats mt-8 grid max-w-[650px] grid-cols-3 gap-3 lg:mt-10 lg:gap-4">
                        <article class="mini-glass-card"><svg class="h-5 w-5 text-white/65" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 6h11v10H3zM14 9h3l4 4v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg><strong>01</strong><span>Laporan ekspedisi</span></article>
                        <article class="mini-glass-card"><svg class="h-5 w-5 text-white/65" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M7 3h7l4 4v14H7zM14 3v5h4M10 13h5m-5 4h5" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg><strong>04</strong><span>Jenis pengajuan</span></article>
                        <article class="mini-glass-card"><svg class="h-5 w-5 text-white/65" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3" stroke-width="1.7" stroke-linecap="round" /></svg><strong>24/7</strong><span>Monitoring data</span></article>
                    </div>
                </section>

                <section class="auth-card auth-card-enter mx-auto w-full max-w-[520px] rounded-[24px] border border-white/50 bg-white/[.76] p-5 text-slate-900 shadow-[0_28px_80px_rgba(38,6,9,.28)] backdrop-blur-[24px] sm:p-8 lg:p-9">
                    <form @submit.prevent="submit" novalidate>
                        <span class="inline-flex items-center gap-2 rounded-full border border-red-200/70 bg-red-50/80 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#a90f18]"><span class="h-1.5 w-1.5 rounded-full bg-[#e31e24]"></span>Portal Operasional</span>
                        <h2 class="mt-4 text-3xl font-semibold tracking-[-.045em] text-slate-950 sm:text-[2.15rem]">Selamat datang</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Masuk menggunakan akun yang telah diberikan administrator sistem.</p>

                        <div class="mt-7">
                            <label for="username" class="text-sm font-semibold text-slate-700">Username</label>
                            <div class="relative mt-2">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0m8-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke-width="1.7" stroke-linecap="round" /></svg>
                                <input id="username" v-model="form.username" name="username" type="text" autofocus autocomplete="username" placeholder="Masukkan username" :aria-invalid="Boolean(form.errors.username)" :aria-describedby="form.errors.username ? 'username-error' : undefined" class="auth-input" />
                            </div>
                            <p v-if="form.errors.username" id="username-error" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ form.errors.username }}</p>
                        </div>

                        <div class="mt-5">
                            <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                            <div class="relative mt-2">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" stroke-width="1.7" /><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-width="1.7" stroke-linecap="round" /></svg>
                                <input id="password" v-model="form.password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Masukkan password" :aria-invalid="Boolean(form.errors.password)" :aria-describedby="form.errors.password ? 'password-error' : undefined" class="auth-input pr-12" />
                                <button type="button" class="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                                    <svg v-if="!showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke-width="1.7" /><circle cx="12" cy="12" r="2.5" stroke-width="1.7" /></svg>
                                    <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m3 3 18 18M10.6 6.1A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.2 2.8M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.2 0 2.3-.2 3.3-.6M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke-width="1.7" stroke-linecap="round" /></svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" id="password-error" class="mt-1.5 text-xs font-medium text-red-700" role="alert">{{ form.errors.password }}</p>
                        </div>

                        <div class="mt-5 flex items-center justify-between gap-4">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600"><input v-model="form.remember" name="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500" />Ingat saya</label>
                            <Link :href="route('password.request')" class="rounded text-sm font-semibold text-[#b6121b] underline-offset-4 transition hover:text-[#7d0b12] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">Lupa password?</Link>
                        </div>

                        <button type="submit" :disabled="form.processing" class="mt-6 flex h-14 w-full items-center justify-center gap-2.5 rounded-[13px] bg-[#e31e24] px-5 text-sm font-bold tracking-[.05em] text-white shadow-[0_12px_28px_rgba(227,30,36,.25)] transition hover:bg-[#c91820] focus:outline-none focus-visible:ring-4 focus-visible:ring-red-200 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-65">
                            <svg v-if="form.processing" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-30" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                            <span>{{ form.processing ? 'MEMVERIFIKASI...' : 'MASUK KE SISTEM' }}</span>
                            <svg v-if="!form.processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </button>
                        <span class="sr-only" aria-live="polite">{{ form.processing ? 'Sedang memverifikasi akun' : '' }}</span>

                        <div class="mt-5 flex items-start gap-3 rounded-2xl border border-emerald-200/60 bg-emerald-50/65 p-3.5">
                            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 8.3 7 10 4.2-1.7 7-5.4 7-10V6l-7-3Z" stroke-width="1.7" stroke-linejoin="round" /><path d="m9 12 2 2 4-4" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg></div>
                            <div><p class="text-xs font-bold text-slate-800">Sistem terproteksi</p><p class="mt-1 text-[11px] leading-5 text-slate-600">Menu dan data ditampilkan sesuai role serta hak akses akun Anda.</p></div>
                        </div>
                    </form>
                </section>
            </div>

            <footer class="auth-footer auth-enter flex flex-col gap-2 border-t border-white/15 pt-3 text-[10px] leading-5 text-white/60 sm:flex-row sm:items-end sm:justify-between sm:text-[11px]">
                <div><p class="font-bold uppercase tracking-[.15em] text-white/80">Kolaborasi SCM <span class="text-[#ff8589]">•</span> FAW <span class="text-[#ff8589]">•</span> ICT</p><p>Sinergi Operasional Terintegrasi</p></div>
                <div class="sm:text-right"><p>© 2026 PT Putra Perkasa Abadi — Site BIB</p><p>Digital Report &amp; Vendor Permit</p></div>
            </footer>
        </div>
    </main>
</template>

<style scoped>
.auth-backdrop { background-image: url('/images/auth/login-background.webp'); background-position: center; background-size: cover; transform: scale(1.01); }
.auth-overlay { background: radial-gradient(circle at 72% 48%, rgba(46, 5, 9, .08), rgba(31, 5, 8, .56) 72%), linear-gradient(105deg, rgba(73, 4, 9, .92) 0%, rgba(125, 11, 18, .78) 42%, rgba(29, 7, 10, .46) 100%); }
.mini-glass-card { display: flex; min-width: 0; flex-direction: column; border: 1px solid rgba(255,255,255,.16); border-radius: 16px; background: rgba(255,255,255,.09); padding: 1rem; box-shadow: 0 14px 35px rgba(22,4,6,.1); backdrop-filter: blur(14px); transition: transform 180ms ease, background-color 180ms ease, border-color 180ms ease; }
.mini-glass-card:hover { transform: translateY(-2px); border-color: rgba(255,255,255,.25); background: rgba(255,255,255,.13); }
.mini-glass-card strong { margin-top: 1.1rem; font-size: 1.35rem; line-height: 1; }
.mini-glass-card span { margin-top: .55rem; overflow-wrap: anywhere; font-size: .72rem; line-height: 1.25rem; color: rgba(255,255,255,.68); }
.auth-input { height: 3.4rem; width: 100%; border: 1px solid rgba(148,163,184,.42); border-radius: 13px; background: rgba(255,255,255,.68); padding: 0 1rem 0 3rem; color: #0f172a; font-size: .875rem; outline: none; box-shadow: inset 0 1px 0 rgba(255,255,255,.55); transition: border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease; }
.auth-input:hover { border-color: rgba(227,30,36,.38); background: rgba(255,255,255,.82); }
.auth-input:focus { border-color: #e31e24; background: rgba(255,255,255,.96); box-shadow: 0 0 0 4px rgba(227,30,36,.1); }
.auth-input[aria-invalid='true'] { border-color: #dc2626; }
.auth-enter, .auth-card-enter { animation: auth-rise 480ms cubic-bezier(.22,1,.36,1) both; }
.auth-card-enter { animation-delay: 90ms; }
@keyframes auth-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@media (max-height: 820px) and (min-width: 768px) {
    .auth-layout { padding-top: .75rem; padding-bottom: .75rem; }
    .auth-intro h1 { margin-top: .75rem; font-size: clamp(2.4rem, 4.2vw, 4rem); }
    .auth-intro > p:last-of-type { margin-top: .75rem; }
    .auth-stats { margin-top: 1rem; }
    .mini-glass-card { padding: .75rem; }
    .mini-glass-card strong { margin-top: .65rem; }
    .auth-card { padding: 1.5rem; }
    .auth-card form > div:first-of-type { margin-top: 1rem; }
    .auth-card form > div { margin-top: .85rem; }
    .auth-card form > button { margin-top: 1rem; }
    .auth-card form > div:last-child { margin-top: 1rem; padding-top: .65rem; padding-bottom: .65rem; }
}
@media (max-width: 767px) { .auth-page { height: 100svh; } .auth-overlay { background: linear-gradient(180deg, rgba(71,5,10,.88) 0%, rgba(64,5,9,.68) 28%, rgba(28,6,9,.64) 100%); } }
@media (max-width: 767px) and (max-height: 760px) { .auth-footer { display: none; } .auth-layout { padding-top: .5rem; padding-bottom: 0; } .auth-card { padding: 1rem; } .auth-card form > div { margin-top: .75rem; } .auth-card form > button { margin-top: .85rem; } .auth-card form > div:last-child { margin-top: .75rem; padding: .65rem; } }
@media (prefers-reduced-motion: reduce) { .auth-enter, .auth-card-enter, .mini-glass-card { animation: none; transition: none; } }
</style>
