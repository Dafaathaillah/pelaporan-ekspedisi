<script setup>
defineProps({ name: { type: String, required: true } });
</script>

<template>
<svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="name==='truck'"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></template>
    <template v-else-if="name==='visitor'"><circle cx="12" cy="8" r="3"/><path d="M6 20c0-4 2.5-7 6-7s6 3 6 7"/><path d="M18 5h3v5"/><path d="m21 5-4 4"/></template>
    <template v-else-if="name==='shield'"><path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6z"/><path d="m9 12 2 2 4-4"/></template>
    <template v-else-if="name==='document'"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></template>
    <template v-else-if="name==='route'"><circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18h3a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h7"/></template>
    <template v-else-if="name==='users'"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2.4-7 6-7s6 3 6 7"/><path d="M16 4a3 3 0 0 1 0 6M17 13c2.4.5 4 3 4 6"/></template>
    <template v-else-if="name==='building'"><path d="M4 21V5l8-3 8 3v16M8 8h1M8 12h1M8 16h1M15 8h1M15 12h1M15 16h1M10 21v-3h4v3"/></template>
    <template v-else-if="name==='calendar'"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></template>
    <template v-else-if="name==='upload'"><path d="M4 17v3h16v-3M12 16V4M8 8l4-4 4 4"/><path d="M7 12H5a2 2 0 0 0-2 2v1M17 12h2a2 2 0 0 1 2 2v1"/></template>
    <template v-else-if="name==='lock'"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></template>
    <template v-else><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/></template>
</svg>
</template>
