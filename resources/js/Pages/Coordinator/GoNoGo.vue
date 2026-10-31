<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppShell from '@/Components/AppShell.vue';
import GoNoGoRecord from '@/Components/GoNoGoRecord.vue';
const props = defineProps({ forms: Object, checklist: Array, filters: Object });
const filter = reactive({ search: '', status: '', ...props.filters });
const load = () => router.get('/coordinator/go-no-go', filter, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <AppShell title="Approval GO NO GO" subtitle="Pemeriksaan kesiapan pengawalan unit sebelum mulai escort." eyebrow="Persetujuan Coordinator">
        <section class="mb-5 rounded-2xl border border-lime-300 bg-lime-100 p-4 text-lime-950"><b>GO hanya untuk Layak + Approved</b><p class="mt-1 text-sm">Driver menentukan kelayakan; Coordinator memeriksa dan menyetujui. Keputusan Tidak Layak tetap NO GO.</p></section>
        <form class="panel mb-5 grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto]" @submit.prevent="load"><label class="min-w-0 text-xs font-semibold">Cari pengajuan<input v-model="filter.search" class="control mt-1 min-w-0" placeholder="Escorter, NIK, nopol, atau driver..." maxlength="150"></label><label class="min-w-0 text-xs font-semibold">Status review<select v-model="filter.status" class="control mt-1 min-w-0"><option value="">Semua status</option><option value="PENDING">Menunggu persetujuan</option><option value="APPROVED">Disetujui</option><option value="REJECTED">Dikembalikan / ditolak</option><option value="CANCELLED">Dibatalkan</option></select></label><button class="btn self-end">Terapkan</button></form>
        <section class="panel min-w-0 space-y-4"><div class="flex flex-wrap items-center justify-between gap-2"><h2 class="panel-title">Pengajuan GO/NO GO</h2><span class="text-xs text-slate-500">{{ forms.total }} pengajuan</span></div><p v-if="!forms.data.length" class="text-sm text-slate-500">Tidak ada pengajuan sesuai filter.</p><GoNoGoRecord v-for="record in forms.data" :key="record.id" :record="record" :checklist="checklist" :review="true" /><nav v-if="forms.last_page > 1" class="flex flex-wrap gap-2" aria-label="Halaman pengajuan GO NO GO"><Link v-for="link in forms.links" :key="link.label" :href="link.url || ''" preserve-scroll preserve-state class="pagination" :class="{ 'pagination-active': link.active, 'pointer-events-none opacity-40': !link.url }" v-html="link.label" /></nav></section>
    </AppShell>
</template>
