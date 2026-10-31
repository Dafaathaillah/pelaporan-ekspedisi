import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';

let server, Component;
before(async () => {
    server = await createServer({ configFile: false, plugins: [vue()], resolve: { alias: { '@': path.resolve('resources/js') } }, server: { middlewareMode: true, hmr: false }, optimizeDeps: { noDiscovery: true }, appType: 'custom', logLevel: 'error' });
    Component = (await server.ssrLoadModule('/resources/js/Components/PermitP2hFollowUps.vue')).default;
});
after(async () => { await server?.close(); });

const render = (expeditionAccounts = [], units = []) => renderToString(createSSRApp(Component, {
    permits: [{ id: 'permit-1', submission_number: 'VND-TEST', vendor_name: 'Vendor Alpha', company: { id: 1, name: 'Vendor Alpha', is_active: true }, is_expired: false, expedition_accounts: expeditionAccounts, units }],
    reviewBase: '/coordinator',
}));

test('missing expedition accounts are flagged without a manual linking form', async () => {
    const html = await render();
    assert.match(html, /Akun ekspedisi belum tersedia/);
    assert.match(html, /Periksa izin \/ akun/);
    assert.doesNotMatch(html, /<form|legacy_account/);
});

test('resolved expedition accounts are visible without manual account controls', async () => {
    const html = await render([{ id: 1, name: 'Linked Expedition' }]);
    assert.match(html, /Linked Expedition/);
    assert.doesNotMatch(html, /<form|legacy_account/);
});

test('pending P2H still points to vendor review and the unit PDF', async () => {
    const html = await render([{ id: 1, name: 'Linked Expedition' }], [{ id: 'unit-1', plate_number: 'DA 1 AB', p2h: { status: 'PENDING' }, arrived_at: null }]);
    assert.match(html, /Menunggu approval vendor/);
    assert.match(html, /href="\/departure-p2h\/unit-1\/download"/);
    assert.doesNotMatch(html, /<form|legacy_account/);
});


test('large permit unit lists mount only the first ten rows and no journey timers', async () => {
    const units = Array.from({ length: 1000 }, (_, index) => ({ id: `unit-${index + 1}`, plate_number: `DA ${index + 1} AB`, p2h: { status: 'PENDING' } }));
    const html = await render([{ id: 1, name: 'Linked Expedition' }], units);
    assert.equal((html.match(/class="follow-up-row/g) || []).length, 10);
    assert.match(html, /DA 10 AB/);
    assert.doesNotMatch(html, /DA 11 AB/);
    assert.match(html, /1 \/ 100/);
    assert.doesNotMatch(html, /Waktu sejak P2H dikirim/);
});
