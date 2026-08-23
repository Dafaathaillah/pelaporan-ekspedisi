<script setup>
defineProps({ label: String, column: String, sort: String, direction: String });
defineEmits(['sort']);
</script>
<template><button type="button" class="inline-flex items-center gap-1.5 whitespace-nowrap uppercase" @click="$emit('sort',column)"><span>{{label}}</span><span class="text-[9px]" :class="sort===column?'text-red-600':'text-slate-300'">{{sort===column?(direction==='asc'?'▲':'▼'):'↕'}}</span></button></template>
