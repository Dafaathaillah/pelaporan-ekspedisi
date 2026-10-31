<script setup>
import { ref } from 'vue';

const props = defineProps({ title: { type: String, required: true }, initiallyOpen: Boolean });
const expanded = ref(props.initiallyOpen);
const syncOpen = (event) => { expanded.value = event.currentTarget.open; };
</script>

<template>
    <details class="journey-details" :open="expanded" @toggle="syncOpen">
        <summary>{{ title }}</summary>
        <div v-if="expanded"><slot /></div>
    </details>
</template>

<style scoped>
.journey-details {
    border: 1px solid #e2e8f0;
    border-radius: .75rem;
    background: #f8fafc;
}
.journey-details > summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    min-height: 44px;
    padding: .625rem .75rem;
    list-style: none;
    cursor: pointer;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}
.journey-details > summary:hover {
    color: #b91c1c;
    background: #fff1f2;
    border-radius: .75rem;
}
.journey-details > summary:focus-visible {
    outline: 2px solid #dc2626;
    outline-offset: 2px;
    border-radius: .75rem;
}
.journey-details[open] {
    background: white;
}
.journey-details > div {
    margin-top: 0;
    padding: 0 .5rem .5rem;
}
.journey-details > summary::-webkit-details-marker {
    display: none;
}
.journey-details > summary::after {
    content: '';
    flex-shrink: 0;
    width: 7px;
    height: 7px;
    border-right: 1.5px solid currentColor;
    border-bottom: 1.5px solid currentColor;
    transform: rotate(45deg);
}
.journey-details[open] > summary::after {
    transform: rotate(225deg);
}
.journey-details[open] > summary {
    border-bottom: 1px solid #e2e8f0;
    border-radius: .75rem .75rem 0 0;
    color: #0f172a;
}
.journey-details[open] > div {
    padding-top: .5rem;
}
</style>
