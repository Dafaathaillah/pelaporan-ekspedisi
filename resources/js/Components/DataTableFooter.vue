<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ paginator: Object, perPage: [Number, String] });
defineEmits(['update:perPage']);
</script>

<template>
    <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span>Baris per halaman</span>
            <select :value="perPage" class="select-control rounded-lg py-1.5 pl-3 pr-9 text-xs" @change="$emit('update:perPage', Number($event.target.value))">
                <option v-for="size in [10,25,50,100]" :key="size" :value="size">{{size}}</option>
            </select>
            <span v-if="paginator.total">Menampilkan {{paginator.from}}–{{paginator.to}} dari {{paginator.total}}</span>
            <span v-else>Tidak ada data</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <Link v-for="link in paginator.links" :key="link.label" :href="link.url||''" preserve-scroll preserve-state class="pagination" :class="{'pagination-active':link.active,'pointer-events-none opacity-40':!link.url}" v-html="link.label"/>
        </div>
    </div>
</template>
