<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import AppFooter from "@/Components/AppFooter.vue";
import NavIcon from "@/Components/NavIcon.vue";
import ActionConfirm from "@/Components/ActionConfirm.vue";

defineProps({
    title: String,
    subtitle: String,
    eyebrow: { type: String, default: "" },
});
const page = usePage();
const user = computed(() => page.props.auth.user);
const notificationCounts = computed(() => page.props.notificationCounts || {});
const collapsed = ref(false);
const moreOpen = ref(false);
const logoutConfirm = ref(false);
const realtimeState = ref("connecting");
const setRealtimeState = (event) => (realtimeState.value = event.detail.state);
onMounted(() => {
    collapsed.value = localStorage.getItem("ppa-sidebar-collapsed") === "true";
    realtimeState.value = window.__ppaRealtimeState || "connecting";
    window.addEventListener("ppa:reverb-status", setRealtimeState);
});
onUnmounted(() =>
    window.removeEventListener("ppa:reverb-status", setRealtimeState),
);
watch(collapsed, (value) =>
    localStorage.setItem("ppa-sidebar-collapsed", String(value)),
);
const can = (permission) => {
    if (user.value.role === "developer") return true;
    if (Array.isArray(user.value.permissions))
        return user.value.permissions.includes(permission);
    if (user.value.role === "coordinator") return true;
    return (
        permission ===
        {
            vendor: "vendor.submission",
            driver: "driver.escort",
            expedition: "expedition.report",
            purchasing_admin: "purchasing.requests",
        }[user.value.role]
    );
};
const menus = computed(() => {
    if (user.value.role === "coordinator" || user.value.role === "developer")
        return [
            {
                label: "Ekspedisi",
                short: "Ekspedisi",
                icon: "truck",
                href: "/coordinator",
                exact: true,
                permission: "coordinator.expedition",
            },
            {
                label: "Request Ekspedisi",
                short: "Request",
                icon: "route",
                href: "/coordinator/expedition-requests",
                permission: "coordinator.requests",
            },
            {
                label: "Visitor",
                short: "Visitor",
                icon: "visitor",
                href: "/coordinator/submissions/VISITOR",
                permission: "coordinator.vendor",
                badge: notificationCounts.value.VISITOR,
            },
            {
                label: "Waskat",
                short: "Waskat",
                icon: "shield",
                href: "/coordinator/submissions/WASKAT",
                permission: "coordinator.vendor",
                badge: notificationCounts.value.WASKAT,
            },
            {
                label: "Permit Non Simper",
                short: "Permit",
                icon: "document",
                href: "/coordinator/submissions/PERMIT_VENDOR_NON_SIMPER",
                permission: "coordinator.vendor",
                badge: notificationCounts.value.PERMIT_VENDOR_NON_SIMPER,
            },
            {
                label: "Ijin Lintas",
                short: "Lintas",
                icon: "route",
                href: "/coordinator/submissions/IJIN_LINTAS",
                permission: "coordinator.vendor",
                badge: notificationCounts.value.IJIN_LINTAS,
            },
            {
                label: "Monitoring Kedatangan", short: "Kedatangan", icon: "truck", href: "/coordinator/arrival-monitoring", permission: "coordinator.planning",
            },
            {
                label: "Planning Escort",
                short: "Planning",
                icon: "calendar",
                href: "/coordinator/planning",
                permission: "coordinator.planning",
            },
            {
                label: "Manajemen User",
                short: "Pengguna",
                icon: "users",
                href: "/coordinator/users",
                permission: "users.manage",
            },
            {
                label: "Manajemen Vendor",
                short: "Vendor",
                icon: "building",
                href: "/coordinator/vendors",
                permission: "vendors.manage",
            },
            {
                label: "Ganti Password",
                short: "Password",
                icon: "lock",
                href: "/profile",
            },
        ].filter((item) => !item.permission || can(item.permission));
    if (user.value.role === "vendor")
        return [
            {
                label: "Incoming Request",
                short: "Incoming",
                icon: "route",
                href: "/vendor/expedition-requests",
            },
            {
                label: "Pengajuan Vendor",
                short: "Pengajuan",
                icon: "upload",
                href: "/vendor",
                exact: true,
                badge: notificationCounts.value.vendor_revisions,
            },
            {
                label: "Laporan Kedatangan Driver", short: "Kedatangan", icon: "truck", href: "/vendor/arrivals",
            },
            { label: "Monitoring Kedatangan / Reschedule", short: "Reschedule", icon: "calendar", href: "/vendor/arrival-monitoring",
            },
            {
                label: "Monitoring Sarana",
                short: "Sarana",
                icon: "truck",
                href: "/vendor/monitoring-sarana",
            },
            {
                label: "Planning Lintas",
                short: "Planning",
                icon: "calendar",
                href: "/vendor/planning-lintas",
                badge: notificationCounts.value.released_ijin_lintas,
            },
        ];
    if (user.value.role === "driver")
        return [
            {
                label: "Agenda Escort",
                short: "Escort",
                icon: "steering",
                href: "/driver/escorts",
                exact: true,
            },
        ];
    if (user.value.role === "purchasing_admin")
        return [
            {
                label: "Request Ekspedisi",
                short: "Request",
                icon: "route",
                href: "/purchasing/requests",
                exact: true,
            },
            {
                label: "Review Ijin Lintas",
                short: "Review",
                icon: "bell",
                href: "/purchasing/vendor-submissions/IJIN_LINTAS",
                badge: notificationCounts.value.IJIN_LINTAS,
            },
            {
                label: "Monitoring Kedatangan", short: "Kedatangan", icon: "truck", href: "/purchasing/arrival-monitoring",
            },
            { label: "Planning Lintas", short: "Planning", icon: "calendar", href: "/purchasing/planning",
            },
            {
                label: "Ganti Password",
                short: "Password",
                icon: "lock",
                href: "/profile",
            },
        ];
    return [
        {
            label: "Laporan Kedatangan",
            short: "Laporan",
            icon: "truck",
            href: "/expedition",
            exact: true,
        },
    ];
});
const active = (item) =>
    item.exact ? page.url === item.href : page.url.startsWith(item.href);
const primaryMenus = computed(() => menus.value.slice(0, 3));
const additionalMenus = computed(() => menus.value.slice(3));
const additionalActive = computed(() => additionalMenus.value.some(active));
watch(
    () => page.url,
    () => (moreOpen.value = false),
);
const logout = () => {
    logoutConfirm.value = false;
    router.post("/logout");
};
</script>

<template>
    <div class="app-shell min-h-screen text-[#101b35] lg:flex">
        <div class="app-shell-backdrop" aria-hidden="true"></div>
        <aside class="sidebar-shell" :class="collapsed ? 'w-[88px]' : 'w-72'">
            <button
                type="button"
                class="sidebar-toggle"
                :class="{ 'sidebar-toggle-collapsed': collapsed }"
                :aria-label="
                    collapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar'
                "
                :title="collapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar'"
                @click="collapsed = !collapsed"
            >
                <svg
                    class="sidebar-toggle-icon"
                    :class="{ 'rotate-180': collapsed }"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path d="m14.5 6.5-5 5.5 5 5.5" />
                </svg>
                {{ collapsed ? "›" : "‹" }}
            </button>
            <div class="border-b border-slate-100 px-5 py-7">
                <div
                    class="flex items-center"
                    :class="collapsed ? 'justify-center' : 'gap-3'"
                >
                    <div
                        class="flex h-14 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-red-100 bg-white p-1 shadow-[0_8px_24px_rgba(220,38,38,.12)] transition-all duration-500"
                        :class="collapsed ? 'w-16' : 'w-[72px]'"
                    >
                        <img
                            src="/images/logo-ekspedisi-ppa.png"
                            alt="Logo Ekspedisi PPA Department SCM"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <div
                        class="sidebar-reveal min-w-0"
                        :class="{ 'sidebar-reveal-hidden': collapsed }"
                    >
                        <p class="font-black leading-tight text-slate-950">
                            PUTRA PERKASA ABADI
                        </p>
                        <p
                            class="mt-1 whitespace-nowrap text-[9px] font-bold tracking-[.22em] text-slate-500"
                        >
                            SITE BORNEO INDOBARA
                        </p>
                    </div>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-3 py-5">
                <p
                    class="sidebar-reveal px-3 text-[10px] font-black uppercase tracking-[.18em] text-slate-400"
                    :class="{ 'sidebar-reveal-hidden': collapsed }"
                >
                    Menu Utama
                </p>
                <nav class="mt-3 space-y-1.5">
                    <Link
                        v-for="item in menus"
                        :key="item.href"
                        :href="item.href"
                        :title="collapsed ? item.label : null"
                        class="side-link relative"
                        :class="[
                            { 'side-link-active': active(item) },
                            collapsed ? 'justify-center px-2' : '',
                        ]"
                        ><span class="side-icon"
                            ><NavIcon :name="item.icon" /></span
                        ><span
                            class="sidebar-link-label whitespace-nowrap"
                            :class="{ 'sidebar-link-label-hidden': collapsed }"
                            >{{ item.label }}</span
                        ><span
                            v-if="item.badge"
                            class="ml-auto grid min-h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white"
                            >{{ item.badge > 99 ? "99+" : item.badge }}</span
                        ></Link
                    >
                </nav>
            </div>
            <div class="shrink-0 border-t border-slate-100 bg-white p-3">
                <div
                    class="mb-3 rounded-2xl bg-slate-50"
                    :class="collapsed ? 'p-2' : 'p-4'"
                >
                    <div
                        class="flex items-center"
                        :class="collapsed ? 'justify-center' : 'gap-3'"
                    >
                        <div
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-red-100 font-black text-red-700"
                        >
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div
                            class="sidebar-reveal min-w-0"
                            :class="{ 'sidebar-reveal-hidden': collapsed }"
                        >
                            <p class="truncate text-sm font-black">
                                {{ user.name }}
                            </p>
                            <p
                                class="whitespace-nowrap text-[10px] font-semibold uppercase text-slate-500"
                            >
                                {{ user.role }} · Site {{ user.site }}
                            </p>
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    :title="collapsed ? 'Keluar dari Sistem' : null"
                    class="group flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-gradient-to-r from-red-50 to-white px-3 py-3 text-sm font-black text-red-700 shadow-sm transition hover:border-red-300 hover:from-red-600 hover:to-red-700 hover:text-white hover:shadow-lg hover:shadow-red-100"
                    @click="logoutConfirm = true"
                >
                    <svg
                        class="h-5 w-5 transition group-hover:translate-x-0.5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M10 17l5-5-5-5M15 12H3" />
                        <path
                            d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"
                        /></svg
                    ><span
                        class="sidebar-link-label whitespace-nowrap"
                        :class="{ 'sidebar-link-label-hidden': collapsed }"
                        >Keluar dari Sistem</span
                    >
                </button>
            </div>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur lg:hidden"
            >
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex h-11 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-red-100 bg-white p-1 shadow-sm"
                        >
                            <img
                                src="/images/logo-ekspedisi-ppa.png"
                                alt="Logo Ekspedisi PPA Department SCM"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="truncate text-[15px] font-semibold tracking-tight"
                            >
                                {{ title }}
                            </p>
                            <p
                                class="truncate text-[11px] font-medium text-slate-500"
                            >
                                Site {{ user.site }} · {{ user.name }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-xl border border-red-100 bg-red-50 text-red-700 shadow-sm"
                        aria-label="Keluar dari sistem"
                        @click="logoutConfirm = true"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M10 17l5-5-5-5M15 12H3" />
                            <path
                                d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"
                            />
                        </svg>
                    </button>
                </div>
            </header>
            <header class="hidden border-b border-slate-200 bg-white lg:block">
                <div class="flex items-center justify-between px-8 py-5">
                    <div>
                        <p
                            class="text-[20px] font-semibold tracking-[-.025em] text-slate-950"
                        >
                            {{ title }}
                        </p>
                        <p
                            class="mt-0.5 text-[12px] font-medium tracking-[.01em] text-slate-500"
                        >
                            {{ subtitle }}
                        </p>
                    </div>
                    <div
                        class="rounded-full px-3 py-1.5 text-[11px] font-semibold"
                        :class="
                            realtimeState === 'connected'
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-amber-50 text-amber-700'
                        "
                    >
                        ●
                        {{
                            realtimeState === "connected"
                                ? "Realtime terhubung"
                                : "Menghubungkan realtime"
                        }}
                    </div>
                </div>
            </header>
            <main
                class="mx-auto w-full max-w-[1500px] flex-1 px-4 pb-28 pt-5 sm:px-6 lg:px-8 lg:pb-8"
            >
                <section
                    v-if="eyebrow"
                    class="mb-5 rounded-2xl border border-slate-100 bg-white px-6 py-6 shadow-soft lg:px-7 lg:py-7"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[.16em] text-red-600"
                    >
                        {{ eyebrow }}
                    </p>
                    <h1
                        class="mt-2 text-[28px] font-semibold leading-tight tracking-[-.035em] sm:text-[34px] lg:text-[40px]"
                    >
                        <slot name="hero-title">{{ title }}</slot>
                    </h1>
                    <p class="mt-2 text-[14px] leading-6 text-slate-500">
                        <slot name="hero-copy">{{ subtitle }}</slot>
                    </p>
                </section>
                <slot />
            </main>
            <AppFooter />
        </div>
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
            ><button
                v-if="moreOpen"
                type="button"
                aria-label="Tutup menu lainnya"
                class="fixed inset-0 z-40 bg-slate-950/35 backdrop-blur-[2px] lg:hidden"
                @click="moreOpen = false"
            ></button
        ></Transition>
        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-y-full opacity-0"
            leave-active-class="transition duration-180 ease-in"
            leave-to-class="translate-y-full opacity-0"
            ><section
                v-if="moreOpen"
                class="fixed inset-x-3 bottom-[calc(5.4rem+env(safe-area-inset-bottom))] z-50 rounded-3xl border border-slate-200 bg-white p-4 shadow-[0_24px_70px_rgba(15,23,42,.25)] lg:hidden sm:left-1/2 sm:w-[min(640px,calc(100%-2rem))] sm:-translate-x-1/2"
            >
                <div class="mb-3 flex items-center justify-between px-1">
                    <div>
                        <p
                            class="text-[10px] font-black uppercase tracking-[.18em] text-red-600"
                        >
                            Menu lainnya
                        </p>
                        <p class="mt-1 text-sm font-black text-slate-900">
                            Pilih halaman tujuan
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup"
                        class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500"
                        @click="moreOpen = false"
                    >
                        ✕
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <Link
                        v-for="item in additionalMenus"
                        :key="item.href"
                        :href="item.href"
                        class="flex min-w-0 items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 text-sm font-bold text-slate-700"
                        :class="{
                            'border-red-200 bg-red-50 text-red-700':
                                active(item),
                        }"
                        ><span class="side-icon"
                            ><NavIcon :name="item.icon" /></span
                        ><span class="truncate">{{ item.label }}</span></Link
                    >
                </div>
                <div
                    class="mt-3 flex items-center gap-3 rounded-2xl border border-slate-100 p-3"
                >
                    <div
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-red-100 font-black text-red-700"
                    >
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-black">
                            {{ user.name }}
                        </p>
                        <p
                            class="text-[10px] font-semibold uppercase text-slate-500"
                        >
                            {{ user.role }} · Site {{ user.site }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-red-50 px-3 py-2 text-xs font-black text-red-700"
                        @click="
                            moreOpen = false;
                            logoutConfirm = true;
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M10 17l5-5-5-5M15 12H3" />
                            <path
                                d="M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"
                            /></svg
                        >Keluar
                    </button>
                </div>
            </section></Transition
        >
        <nav class="mobile-bottom-nav lg:hidden">
            <Link
                v-for="item in primaryMenus"
                :key="item.href"
                :href="item.href"
                class="bottom-link"
                :class="{ 'bottom-link-active': active(item) }"
                ><NavIcon :name="item.icon" /><span>{{
                    item.short
                }}</span></Link
            ><button
                v-if="additionalMenus.length"
                type="button"
                class="bottom-link"
                :class="{ 'bottom-link-active': additionalActive || moreOpen }"
                aria-label="Buka menu lainnya"
                :aria-expanded="moreOpen"
                @click="moreOpen = !moreOpen"
            >
                <svg
                    class="h-[18px] w-[18px]"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <circle cx="5" cy="12" r="2" />
                    <circle cx="12" cy="12" r="2" />
                    <circle cx="19" cy="12" r="2" /></svg
                ><span>Lainnya</span>
            </button>
        </nav>
        <ActionConfirm
            :show="logoutConfirm"
            title="Keluar dari sistem?"
            message="Sesi Anda akan diakhiri dengan aman. Pastikan seluruh pekerjaan telah tersimpan."
            confirm-text="Ya, Keluar"
            danger
            @close="logoutConfirm = false"
            @confirm="logout"
        />
    </div>
</template>
