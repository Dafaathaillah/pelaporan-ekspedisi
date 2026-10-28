import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';

let server, Component;
before(async () => {
    server = await createServer({ configFile: false, plugins: [vue()], resolve: { alias: { '@': path.resolve('resources/js') } }, server: { middlewareMode: true }, appType: 'custom', logLevel: 'error' });
    Component = (await server.ssrLoadModule('/resources/js/Components/PermitP2hFollowUps.vue')).default;
});
after(async () => { await server?.close(); });

const render = (expeditionAccounts = [], canLink = true) => renderToString(createSSRApp(Component, {
    permits: [{ id: 'permit-1', submission_number: 'VND-TEST', vendor_name: 'Vendor Alpha', company: { id: 1, name: 'Vendor Alpha', is_active: true }, is_expired: false, expedition_accounts: expeditionAccounts, units: [] }],
    accounts: [{ id: 2, name: 'Legacy Expedition', username: 'legacy_account' }], canLink, reviewBase: '/coordinator',
}));

test('user managers can select an unlinked account when the company has no expedition', async () => {
    assert.match(await render(), /<option value="2">Legacy Expedition \(legacy_account\)<\/option>/);
});

test('a second legacy expedition can be linked even when another account already works', async () => {
    const html = await render([{ id: 1, name: 'Linked Expedition' }]);
    assert.match(html, /Linked Expedition/);
    assert.match(html, /<option value="2">Legacy Expedition \(legacy_account\)<\/option>/);
});

test('read-only reviewers are not offered account-linking controls', async () => {
    const html = await render([], false);
    assert.doesNotMatch(html, /legacy_account/);
    assert.doesNotMatch(html, /<select/);
});
