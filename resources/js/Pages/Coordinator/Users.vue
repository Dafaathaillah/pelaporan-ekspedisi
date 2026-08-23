<script setup>
import AppShell from '@/Components/AppShell.vue';
import DataTableFooter from '@/Components/DataTableFooter.vue';
import Modal from '@/Components/Modal.vue';
import SortButton from '@/Components/SortButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ users: Object, filters: Object, permissionOptions: Array });
const showModal = ref(false); const editing = ref(null);
const query = ref({ search: props.filters.search || '', role: props.filters.role || '', status: props.filters.status || '', sort:props.filters.sort||'created_at', direction:props.filters.direction||'desc', per_page: Number(props.filters.per_page || 10) });
const form = useForm({ name:'', username:'', email:'', password:'', role:'vendor', site:'BIB', is_active:true, permissions:[] });
const stats = computed(() => ({ total: props.users.total, active: props.users.data.filter(user => user.is_active).length, coordinator: props.users.data.filter(user => user.role === 'coordinator').length }));
const roleLabel = role => ({ coordinator:'Koordinator', vendor:'Vendor', expedition:'Ekspedisi' }[role] || role);
const applyFilters = () => router.get('/coordinator/users', query.value, { preserveState:true, preserveScroll:true, replace:true });
const resetFilters = () => router.get('/coordinator/users', {}, { preserveScroll:true, replace:true });
const sort = column => { query.value.direction=query.value.sort===column&&query.value.direction==='asc'?'desc':'asc'; query.value.sort=column; applyFilters(); };
const setPerPage = value => { query.value.per_page = value; applyFilters(); };
const openCreate = () => { editing.value=null; form.reset(); form.clearErrors(); Object.assign(form,{role:'vendor',site:'BIB',is_active:true,permissions:[]}); showModal.value=true; };
const openEdit = user => { editing.value=user; form.clearErrors(); Object.assign(form,{name:user.name,username:user.username,email:user.email||'',password:'',role:user.role,site:user.site,is_active:Boolean(user.is_active),permissions:user.permissions||[]}); showModal.value=true; };
const save = () => { const options={preserveScroll:true,onSuccess:()=>{showModal.value=false;form.reset();}}; editing.value ? form.patch(`/coordinator/users/${editing.value.id}`,options) : form.post('/coordinator/users',options); };
</script>

<template>
<AppShell title="Manajemen Pengguna" subtitle="Kelola akun dan akses modul" eyebrow="Koordinator Sarana · Administrasi Sistem">
    <template #hero-title>Manajemen Pengguna</template>
    <template #hero-copy>Atur akun, peran, status, dan ruang akses setiap pengguna secara terpusat.</template>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="metric-card"><p>Total pengguna</p><strong>{{stats.total}}</strong><span>Seluruh akun terdaftar</span></div>
        <div class="metric-card"><p>Aktif di halaman ini</p><strong>{{stats.active}}</strong><span>Akun yang dapat masuk</span></div>
        <div class="metric-card"><p>Koordinator</p><strong>{{stats.coordinator}}</strong><span>Pada halaman ini</span></div>
    </div>

    <section class="panel mb-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
            <label class="flex-1 text-xs font-bold text-slate-600">Cari pengguna<input v-model="query.search" class="control mt-2" placeholder="Nama, username, atau email..." @keyup.enter="applyFilters"></label>
            <label class="text-xs font-bold text-slate-600 lg:w-52">Role<select v-model="query.role" class="control mt-2"><option value="">Semua role</option><option value="coordinator">Koordinator</option><option value="vendor">Vendor</option><option value="expedition">Ekspedisi</option></select></label>
            <label class="text-xs font-bold text-slate-600 lg:w-48">Status<select v-model="query.status" class="control mt-2"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label>
            <button class="btn" type="button" @click="applyFilters">Terapkan</button><button class="btn-ghost" type="button" @click="resetFilters">Reset</button><button class="btn-ghost" type="button" @click="openCreate">+ Tambah Pengguna</button>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-5"><div><h2 class="panel-title">Daftar Pengguna</h2><p class="panel-copy">Data diproses server dan hak akses diterapkan pada route.</p></div><span class="text-xs text-slate-400">{{users.total}} akun</span></div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th><SortButton label="Pengguna" column="name" :sort="query.sort" :direction="query.direction" @sort="sort"/></th><th><SortButton label="Username" column="username" :sort="query.sort" :direction="query.direction" @sort="sort"/></th><th><SortButton label="Role" column="role" :sort="query.sort" :direction="query.direction" @sort="sort"/></th><th><SortButton label="Site" column="site" :sort="query.sort" :direction="query.direction" @sort="sort"/></th><th>Akses modul</th><th><SortButton label="Status" column="is_active" :sort="query.sort" :direction="query.direction" @sort="sort"/></th><th>Aksi</th></tr></thead><tbody>
            <tr v-for="user in users.data" :key="user.id"><td><b class="text-slate-900">{{user.name}}</b><small>{{user.email || 'Tanpa email'}}</small></td><td class="font-bold">{{user.username}}</td><td><span class="status-badge bg-slate-100 text-slate-700">{{roleLabel(user.role)}}</span></td><td>{{user.site}}</td><td class="table-wrap-cell"><div class="flex max-w-md flex-wrap gap-1.5"><span v-for="permission in (user.permissions||[])" :key="permission" class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700">{{permissionOptions.find(option=>option.value===permission)?.label || permission}}</span><span v-if="!(user.permissions||[]).length" class="text-slate-400">Tidak ada akses</span></div></td><td><span class="status-badge" :class="user.is_active?'status-approved':'status-rejected'">{{user.is_active?'Aktif':'Nonaktif'}}</span></td><td><button type="button" class="font-black text-red-600 hover:text-red-800" @click="openEdit(user)">Edit</button></td></tr>
            <tr v-if="!users.data.length"><td colspan="7" class="py-12 text-center text-slate-500">Tidak ada pengguna sesuai filter.</td></tr>
        </tbody></table></div>
        <DataTableFooter :paginator="users" :per-page="query.per_page" @update:per-page="setPerPage"/>
    </section>

    <Modal :show="showModal" max-width="3xl" @close="showModal=false"><form class="max-h-[calc(100vh-2rem)] overflow-y-auto" @submit.prevent="save">
        <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 px-6 py-5 backdrop-blur sm:px-8"><div class="flex items-center gap-4"><div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-red-50 font-black text-red-700">{{editing?'E':'+'}}</div><div><p class="text-[10px] font-black uppercase tracking-[.18em] text-red-600">Administrasi Akun</p><h2 class="mt-1 text-xl font-black sm:text-2xl">{{editing?'Edit pengguna':'Tambah pengguna'}}</h2></div></div><button type="button" aria-label="Tutup" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700" @click="showModal=false">✕</button></div>
        <div class="px-6 py-6 sm:px-8">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-xs font-bold">Nama lengkap<input v-model="form.name" class="control mt-2" placeholder="Nama pengguna"><span class="err">{{form.errors.name}}</span></label>
            <label class="text-xs font-bold">Username<input v-model="form.username" class="control mt-2" placeholder="Username login"><span class="err">{{form.errors.username}}</span></label>
            <label class="text-xs font-bold">Email <span class="font-normal text-slate-400">(opsional)</span><input v-model="form.email" class="control mt-2" type="email" placeholder="nama@perusahaan.com"><span class="err">{{form.errors.email}}</span></label>
            <label class="text-xs font-bold">Password <span v-if="editing" class="font-normal text-slate-400">(kosongkan jika tetap)</span><input v-model="form.password" class="control mt-2" type="password" placeholder="Minimal 8 karakter"><span class="err">{{form.errors.password}}</span></label>
            <label class="text-xs font-bold">Role<select v-model="form.role" class="control mt-2"><option value="coordinator">Koordinator</option><option value="vendor">Vendor</option><option value="expedition">Ekspedisi</option></select></label>
            <label class="text-xs font-bold">Site<input v-model="form.site" class="control mt-2" placeholder="BIB"></label>
        </div>
        <div v-if="form.role==='coordinator'" class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm font-black">Akses modul koordinator</p><p class="mb-3 mt-1 text-xs text-slate-500">Pilih halaman yang dapat dilihat dan dikelola akun ini.</p><label v-for="option in permissionOptions" :key="option.value" class="mb-2 flex cursor-pointer gap-3 rounded-xl bg-white p-3 last:mb-0"><input v-model="form.permissions" type="checkbox" :value="option.value" class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-400"><span><b class="block text-sm">{{option.label}}</b><small class="text-slate-500">{{option.description}}</small></span></label></div>
        <label class="mt-5 flex items-center gap-3 rounded-xl border border-slate-200 p-4"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-400"><span><b class="block text-sm">Akun aktif</b><small class="text-slate-500">Pengguna dapat masuk ke dalam sistem.</small></span></label>
        </div><div class="sticky bottom-0 flex flex-col-reverse gap-3 border-t border-slate-100 bg-white/95 px-6 py-4 backdrop-blur sm:flex-row sm:justify-end sm:px-8"><button type="button" class="btn-ghost" @click="showModal=false">Batal</button><button class="btn sm:min-w-44" :disabled="form.processing">{{form.processing?'Menyimpan...':'Simpan Pengguna'}}</button></div>
    </form></Modal>
</AppShell>
</template>
