import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';

let server, Component;
before(async () => {
    server = await createServer({ configFile: false, plugins: [vue()], resolve: { alias: { '@': path.resolve('resources/js') } }, server: { middlewareMode: true, hmr: false }, appType: 'custom', logLevel: 'error' });
    Component = (await server.ssrLoadModule('/resources/js/Components/LazyJourneyDetails.vue')).default;
});
after(async () => { await server?.close(); });

test('closed details do not instantiate hidden countdowns or render their content', async () => {
    let mounts = 0;
    const Countdown = { setup() { mounts++; return () => h('span', 'countdown running'); } };
    const html = await renderToString(createSSRApp({ render: () => h(Component, { title: 'Informasi escort' }, { default: () => h(Countdown) }) }));
    assert.equal(mounts, 0);
    assert.match(html, /Informasi escort/);
    assert.doesNotMatch(html, /countdown running/);
});

test('opened details render their full content and remain a native disclosure', async () => {
    const html = await renderToString(createSSRApp({ render: () => h(Component, { title: 'Lokasi foto', initiallyOpen: true }, { default: () => h('a', { href: 'https://maps.google.com/' }, 'Buka Maps') }) }));
    assert.match(html, /<details[^>]*open/);
    assert.match(html, /<summary[^>]*>Lokasi foto<\/summary>/);
    assert.match(html, /Buka Maps/);
});
