<script setup>
import AppShell from "@/Components/AppShell.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import { Head, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
const user = computed(() => usePage().props.auth.user);
const role = computed(
    () =>
        ({
            developer: "Developer Sistem",
            coordinator: "Koordinator Sarana",
            vendor: "Vendor",
            expedition: "Ekspedisi",
            driver: "Driver Escort",
        })[user.value.role] || user.value.role,
);
</script>

<template>
    <Head title="Keamanan Akun" /><AppShell
        title="Keamanan Akun"
        subtitle="Kelola password dan perlindungan akun Anda"
        eyebrow="PPA · Pelaporan Ekspedisi"
    >
        <template #hero-title>Profil & Keamanan Akun</template
        ><template #hero-copy
            >Pastikan password hanya diketahui oleh Anda dan diperbarui secara
            berkala.</template
        >
        <div class="grid gap-5 xl:grid-cols-[360px_1fr]">
            <aside class="panel h-fit overflow-hidden p-0">
                <div
                    class="bg-gradient-to-br from-red-600 to-red-700 p-6 text-white"
                >
                    <div
                        class="grid h-16 w-16 place-items-center rounded-2xl bg-white/15 text-2xl font-black ring-1 ring-white/20"
                    >
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <h2 class="mt-5 text-xl font-black">{{ user.name }}</h2>
                    <p class="mt-1 text-sm text-red-100">{{ role }}</p>
                </div>
                <dl class="space-y-4 p-6 text-sm">
                    <div>
                        <dt
                            class="text-xs font-bold uppercase tracking-wider text-slate-400"
                        >
                            Username
                        </dt>
                        <dd class="mt-1 font-black text-slate-900">
                            {{ user.username }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-xs font-bold uppercase tracking-wider text-slate-400"
                        >
                            Site
                        </dt>
                        <dd class="mt-1 font-black text-slate-900">
                            {{ user.site }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-xs font-bold uppercase tracking-wider text-slate-400"
                        >
                            Status Akun
                        </dt>
                        <dd class="mt-2">
                            <span class="status-badge status-approved"
                                >● Aktif & Terlindungi</span
                            >
                        </dd>
                    </div>
                </dl>
            </aside>
            <section class="panel"><UpdatePasswordForm /></section>
        </div>
    </AppShell>
</template>
