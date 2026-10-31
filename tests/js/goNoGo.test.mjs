import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';

let server, Form, Record;
before(async () => {
    server = await createServer({ configFile: false, plugins: [vue()], resolve: { alias: { '@': path.resolve('resources/js') } }, optimizeDeps: { noDiscovery: true }, server: { middlewareMode: true, hmr: false }, appType: 'custom', logLevel: 'error' });
    Form = (await server.ssrLoadModule('/resources/js/Components/GoNoGoForm.vue')).default;
    Record = (await server.ssrLoadModule('/resources/js/Components/GoNoGoRecord.vue')).default;
});
after(async () => { await server?.close(); });

const units = Array.from({ length: 4 }, (_, i) => ({ id: 'u-' + i, vehicle_plate: 'DA ' + (i + 1) + ' AB', driver_name: 'Driver ' + (i + 1) }));
const checklist = Array.from({ length: 11 }, (_, i) => 'Pemeriksaan ' + (i + 1));
const record = { id: 'form-1', units, escorter_name: 'Escorting Manual', nik: '8123', department: 'SCM', position: 'Driver LV', destination: 'PPA-BIB', driver_decision: 'LAIK', status: 'PENDING', result: 'PENDING', items: [], revision: 1, submitted_at: '2026-10-31T01:00:00Z' };

test('form previews four comma-separated plates/drivers and fixed identity fields', async () => {
    const html = await renderToString(createSSRApp(Form, { units, checklist }));
    assert.match(html, /DA 1 AB, DA 2 AB, DA 3 AB, DA 4 AB/);
    assert.match(html, /Driver 1, Driver 2, Driver 3, Driver 4/);
    for (const value of ['SCM', 'Driver LV', 'PPA-BIB']) assert.ok(html.includes(value));
    assert.match(html, /Nama Escort/);
    assert.match(html, /NIK/);
    assert.match(html, /Foto P5M/);
    assert.match(html, /Keputusan Kelayakan/);
});

test('only approved fit records expose start and closed details do not mount checklist/photo', async () => {
    for (const data of [record, { ...record, status: 'REJECTED', result: 'NO_GO' }, { ...record, status: 'APPROVED', driver_decision: 'TIDAK_LAIK', result: 'NO_GO' }]) {
        const html = await renderToString(createSSRApp(Record, { record: data, checklist, driver: true }));
        assert.doesNotMatch(html, />Mulai Escort</);
        assert.doesNotMatch(html, /<img|Pemeriksaan 11/);
    }
    const html = await renderToString(createSSRApp(Record, { record: { ...record, status: 'APPROVED', result: 'GO' }, checklist, driver: true }));
    assert.match(html, />Mulai Escort</);
});
