// Local browser checks with synthetic records. No login, database, or API writes.
// Run: node tests/browser/monitoring-smoke.mjs
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const fixture = `
import { createApp, h, reactive } from 'vue';
import AppShell from '/resources/js/Components/AppShell.vue';
import PermitP2hFollowUps from '/resources/js/Components/PermitP2hFollowUps.vue';
import Coordinator from '/resources/js/Pages/Coordinator/Index.vue';
import ArrivalMonitoring from '/resources/js/Pages/Expedition/ArrivalMonitoring.vue';
import VendorShow from '/resources/js/Pages/Vendor/Show.vue';
import GoNoGo from '/resources/js/Pages/Coordinator/GoNoGo.vue';
import GoNoGoForm from '/resources/js/Components/GoNoGoForm.vue';
import '/resources/css/app.css';
window.__uiErrors = [];
const longName = 'Perusahaan Ekspedisi Dengan Nama Sangat Panjang Untuk Pemeriksaan Mobile';
const report = { id: 'report-1', report_number: 'EXP-LONG-1234567890', arrived_at: new Date().toISOString(), driver_name: 'Driver Pemeriksaan', driver_phone: '081234567890', vehicle_plate: 'DA 1234 ABC', vehicle_type: 'Fuso', expedition_company: longName, sender_vendor: longName, destination: longName, escort_status: 'WAITING', ready_escort_at: new Date(Date.now() + 3600000).toISOString(), health_status: 'FIT', health_status_label: 'Fit', blood_pressure_systolic: 110, blood_pressure_diastolic: 70, arrival_latitude: -3.5, arrival_longitude: 115.6, arrival_location_name: longName, arrival_location_accuracy: 10, arrival_photo_path: 'fixture.jpg', blood_pressure_photo_path: 'fixture.jpg' };
const inspection = { id: 'inspection-1', revision: 1, status: 'PENDING', driver_name: report.driver_name, vehicle_plate: report.vehicle_plate, vehicle_type: 'Fuso', created_at: report.arrived_at, items: Array.from({ length: 13 }, () => ({ condition: 'BAIK', note: longName })), tire_positions: [1, 2], decision: 'LAIK', notes: longName, unit: { submission: { vendor_name: longName, submission_number: 'IJIN-123' } } };
const unit = { id: 'unit-1', plate_number: report.vehicle_plate, subject_type: 'UNIT', driver_name: report.driver_name, driver_phone: report.driver_phone, vehicle_type: 'Fuso', latest_departure_p2h: null, departure_p2hs: [{ id: 'r1', revision: 1, status: 'REJECTED' }, { id: 'r2', revision: 2, status: 'PENDING' }] };
const submission = { id: 'permit-1', submission_number: 'IJIN-123', vendor_name: longName, applicant_name: 'PIC', phone: '081', status: 'RELEASED', units: [unit], documents: [], destination: longName, release_expired_at: '2030-12-31' };
const paginate = data => ({ data, total: data.length, from: 1, to: data.length, links: [], last_page: 1 });
const view = new URLSearchParams(location.search).get('view') || 'dashboard';
let component = Coordinator, props = { reports: paginate(Array.from({ length: 8 }, (_, i) => ({ ...report, id: 'report-' + i }))), summary: { today: 4, month: 10, total: 20, companies: 3, latest: { time: '12:30:00', company: longName } }, hourly: Array.from({ length: 24 }, (_, i) => ({ hour: String(i).padStart(2, '0'), total: i === 8 ? 4 : 0 })), filters: {}, options: { companies: [longName], vehicleTypes: ['Fuso'], vendors: [longName], destinations: [longName] }, arrivalP2hs: paginate([inspection]), p2hChecklist: Array.from({ length: 13 }, (_, i) => 'Pemeriksaan ' + (i + 1)) };
if (view === 'empty') { props.arrivalP2hs = paginate([]); props.hourly = props.hourly.map(x => ({ ...x, total: 0 })); }
if (view.startsWith('arrivals')) {
    const manyUnits = Array.from({ length: 1000 }, (_, i) => ({ ...unit, id: 'monitor-unit-' + (i + 1), plate_number: 'DA ' + (i + 1) + ' AB', driver_name: 'Driver ' + (i + 1), latest_departure_p2h: { ...inspection, status: 'APPROVED', departed_at: report.arrived_at } }));
    component = ArrivalMonitoring; props = { items: [{ id: 'plan-1', submission: { ...submission, units: manyUnits }, completed: 500, total: 1000, pending_units: manyUnits.slice(0, 500), arrived_units: manyUnits.slice(500).map(u => ({ ...report, id: 'report-' + u.id, vehicle_plate: u.plate_number, driver_name: u.driver_name, vendor_submission_unit_id: u.id })), planned_date: '2026-10-31', is_locked: view === 'arrivals-locked' }], isVendor: view !== 'arrivals-readonly' };
}
if (view === 'vendor') { component = VendorShow; unit.latest_departure_p2h = { ...inspection, departed_at: report.arrived_at }; props = { submission }; }
if (view === 'followups') {
    const followProps = { reviewBase: '/coordinator', permits: [{ ...submission, company: { name: longName, is_active: true }, expedition_accounts: [{ id: 1, name: longName }], units: Array.from({ length: 1000 }, (_, i) => ({ id: 'large-unit-' + (i + 1), plate_number: 'DA ' + (i + 1) + ' AB', driver_name: 'Driver ' + (i + 1), p2h: { ...inspection, status: i % 2 ? 'APPROVED' : 'PENDING', departed_at: i % 2 ? report.arrived_at : null }, arrived_at: null })) }] };
    component = { render: () => h(AppShell, { title: 'Ijin Lintas', eyebrow: 'Monitoring P2H' }, { default: () => h(PermitP2hFollowUps, followProps) }) }; props = {};
}
if (view.startsWith('arrivals')) {
    const arrivalProps = reactive(props);
    window.__refreshMonitoringItems = () => { arrivalProps.items = arrivalProps.items.map(item => ({ ...item })); };
    component = { render: () => h(ArrivalMonitoring, arrivalProps) }; props = {};
}
if (view === 'go-review') {
 component = GoNoGo; props = { filters: {}, checklist: Array.from({length:11}, (_, i) => 'Pemeriksaan ' + (i+1)), forms: paginate([{ id:'go-1', escorter_name:longName, nik:'812345', units:Array.from({length:4}, (_,i) => ({id:'u-'+i,vehicle_plate:'DA '+(i+1)+' AB',driver_name:longName})), items:Array.from({length:11},()=>({condition:'SESUAI',evidence:longName,note:longName})), status:'PENDING',result:'PENDING',driver_decision:'LAIK',submitted_at:report.arrived_at,revision:1 }]) };
}
if (view === 'go-form') {
 component = { render: () => h(AppShell, { title:'GO NO GO' }, {default:()=>h(GoNoGoForm, {units:Array.from({length:4},(_,i)=>({id:'u-'+i,vehicle_plate:'DA '+(i+1)+' AB',driver_name:longName})),checklist:Array.from({length:11},(_,i)=>'Pemeriksaan '+(i+1))})}) }; props = {};
}
const app = createApp(component, props);
app.config.errorHandler = error => window.__uiErrors.push(String(error));
app.mount('#app');
window.__uiReady = true;
`;
const inertia = `
import { reactive, h } from 'vue';
export const usePage = () => ({ url: '/coordinator', props: { auth: { user: { name: 'Browser fixture', site: 'BIB', role: 'developer' } }, notificationCounts: {} } });
export const router = { get() {}, post() {}, reload() {} };
export const Link = { props: ['href'], setup: (p, { slots }) => () => h('a', { href: p.href }, slots.default?.()) };
export const useForm = data => reactive({ ...structuredClone(data), errors: {}, processing: false, reset() {}, clearErrors() {}, post() {}, patch() { this.errors = { planned_date: 'Tanggal tidak valid' }; window.__refreshMonitoringItems?.(); }, setError() {}, data() { return data; } });
`;
const browser = process.env.BROWSER_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const artifactDir = await mkdtemp(path.join(os.tmpdir(), 'monitoring-browser-'));
let child, socket, server;
try {
    server = await createServer({ configFile: false, plugins: [{ name: 'isolated-monitoring-fixture', enforce: 'pre', resolveId(id) { if (id === '@inertiajs/vue3') return '/__mock_inertia.js'; if (id === '/__monitoring_fixture.js') return id; }, load(id) { if (id === '/__mock_inertia.js') return inertia; if (id === '/__monitoring_fixture.js') return fixture; }, configureServer(s) { s.middlewares.use('/__monitoring-smoke', (_req, res) => { res.setHeader('Content-Type', 'text/html'); res.end('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/__monitoring_fixture.js"></script></body></html>'); }); } }, vue()], resolve: { alias: { '@': path.resolve('resources/js') }, dedupe: ['vue'] }, server: { host: '127.0.0.1', port: 0, hmr: false }, optimizeDeps: { exclude: ['@inertiajs/vue3'] }, logLevel: 'error' });
    await server.listen();
    child = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--remote-allow-origins=*', '--user-data-dir=' + path.join(artifactDir, 'profile'), 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    child.on('error', error => { console.error(error.message); });
    let port;
    for (let i = 0; i < 100; i++) { try { port = (await readFile(path.join(artifactDir, 'profile', 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; } catch { await delay(100); } }
    assert.ok(port, 'Chrome DevTools port must become available');
    const targets = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
    socket = new WebSocket(targets.find(x => x.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = event => { const message = JSON.parse(event.data); if (message.id) { const callbacks = pending.get(message.id); pending.delete(message.id); message.error ? callbacks.reject(message.error) : callbacks.resolve(message.result); } };
    const send = (method, params = {}) => new Promise((resolve, reject) => { const id = ++sequence; pending.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params })); });
    const evaluate = async expression => { const value = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (value.exceptionDetails) throw new Error(JSON.stringify(value.exceptionDetails)); return value.result.value; };
    await send('Page.enable');
    for (const width of (process.env.UI_WIDTHS || '320,360,390,768,1440,1920').split(',').map(Number)) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
        await send('Emulation.setTouchEmulationEnabled', { enabled: width < 768 });
        for (const view of (process.env.UI_VIEWS || 'dashboard,empty,arrivals,vendor,followups').split(',')) {
            await send('Page.navigate', { url: 'http://127.0.0.1:' + server.httpServer.address().port + '/__monitoring-smoke?view=' + view });
            let ready = false;
            for (let i = 0; i < 120; i++) { if (await evaluate('Boolean(window.__uiReady)')) { ready = true; break; } await delay(100); }
            assert.ok(ready, view + ' must mount');
            await delay(150);
            assert.deepEqual(await evaluate('window.__uiErrors'), [], 'no Vue errors');
            const dimensions = await evaluate('({ viewport: innerWidth, document: document.documentElement.scrollWidth, main: document.querySelector("main").getBoundingClientRect().right })');
            assert.ok(dimensions.document <= width + 1 && dimensions.main <= width + 1, JSON.stringify({ width, view, dimensions }));
            if (view === 'dashboard') {
                await evaluate('Array.from(document.querySelectorAll("button")).find(button => button.textContent.includes("Lihat Pemeriksaan")).click()');
                await delay(100);
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), true, 'expanded inspection table stays inside mobile viewport');
                assert.ok(await evaluate('Array.from(document.querySelectorAll("table")).some(table => table.className.includes("min-w-[480px]"))'), 'inspection checklist mounts when opened');
                await evaluate('Array.from(document.querySelectorAll("button")).find(button => button.textContent.includes("Tutup Pemeriksaan")).click()');
                assert.equal(await evaluate('document.querySelectorAll(".journey-details[open]").length'), 0);
                await evaluate('document.querySelector(".arrival-table-scroll").scrollIntoView(); document.querySelector(".arrival-table-scroll").scrollLeft = 150');
                if (width < 1440) assert.ok(await evaluate('document.querySelector(".arrival-table-scroll").scrollLeft > 0'), 'table scrolls horizontally');
                assert.equal(await evaluate('window.scrollX'), 0);
                await evaluate('document.querySelector(".journey-details summary").click()');
                await delay(100);
                assert.ok(await evaluate('document.querySelector(".journey-details[open] a") !== null'), 'detail links mounted on open');
                await evaluate('document.querySelector(".journey-details summary").click()');
                await delay(100);
                assert.equal(await evaluate('document.querySelector(".journey-details > div") === null'), true);
                await evaluate('window.scrollTo(0, 0)');
                if (width < 768) {
                    await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: width / 2, y: 650 }] });
                    for (const y of [580, 510, 440, 370]) { await send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: width / 2, y }] }); await delay(25); }
                    await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
                    await delay(200);
                    assert.ok(await evaluate('window.scrollY > 0'), 'mobile vertical touch scroll works');
                    await evaluate('document.querySelector(".arrival-table-scroll").scrollIntoView(); document.querySelector(".arrival-table-scroll").scrollLeft = 0');
                    await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: width - 35, y: 250 }] });
                    for (const x of [width - 85, width - 135, 50]) { await send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y: 250 }] }); await delay(25); }
                    await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
                    await delay(200);
                    assert.ok(await evaluate('document.querySelector(".arrival-table-scroll").scrollLeft > 0'), 'mobile horizontal touch scroll works');
                    assert.equal(await evaluate('window.scrollX'), 0, 'horizontal swipe does not move whole page');
                }
                await evaluate('window.scrollTo(0, 0)');
                const image = await send('Page.captureScreenshot', { format: 'png' });
                await writeFile(path.join(artifactDir, 'dashboard-' + width + '.png'), Buffer.from(image.data, 'base64'));
            }
            if (view === 'followups') {
                assert.equal(await evaluate('document.querySelectorAll(".follow-up-row").length'), 10, '1000 units render only 10 rows');
                assert.equal(await evaluate('document.querySelectorAll(".permit-follow-ups dialog[open]").length'), 0);
                const image = await send('Page.captureScreenshot', { format: 'png' });
                await writeFile(path.join(artifactDir, 'followups-' + width + '.png'), Buffer.from(image.data, 'base64'));
                await evaluate('Array.from(document.querySelectorAll(".permit-follow-ups footer button")).find(button => button.textContent === "Berikutnya").click()');
                await delay(100);
                assert.ok(await evaluate('document.querySelector(".follow-up-row").textContent.includes("DA 11 AB")'), 'next page advances units');
                await evaluate('var field = document.querySelector(".permit-follow-ups input[type=search]"); field.value = "DA 1000 AB"; field.dispatchEvent(new Event("input", { bubbles: true }))');
                await delay(100);
                assert.equal(await evaluate('document.querySelectorAll(".follow-up-row").length'), 1, 'search finds last of 1000 units');
                await evaluate('var field = document.querySelector(".permit-follow-ups select"); field.value = "AWAITING_VENDOR"; field.dispatchEvent(new Event("change", { bubbles: true }))');
                await delay(100);
                assert.equal(await evaluate('document.querySelectorAll(".follow-up-row").length'), 0, 'stage filter combines with search');
                await evaluate('var field = document.querySelector(".permit-follow-ups select"); field.value = ""; field.dispatchEvent(new Event("change", { bubbles: true }))');
                await delay(100);
                await evaluate('document.querySelector(".follow-up-row button").click()');
                await delay(150);
                assert.ok(await evaluate('document.querySelector(".permit-follow-ups dialog[open]") !== null'), 'detail modal opens');
                assert.equal(await evaluate('document.body.style.overflow'), 'hidden', 'background scroll is locked');
                assert.ok(await evaluate('document.querySelector(".permit-follow-ups dialog[open]").textContent.includes("Waktu sejak P2H dikirim")'), 'journey mounts only in detail');
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), true);
                await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
                await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
                await delay(300);
                assert.equal(await evaluate('document.querySelector(".permit-follow-ups dialog[open]") === null'), true, 'Escape closes modal');
                assert.equal(await evaluate('document.body.style.overflow'), '', 'page scrolling restored');
            }
            if (view.startsWith('arrivals')) {
                assert.equal(await evaluate('document.querySelectorAll(".arrival-unit-row").length'), 10, 'monitoring renders only 10 units');
                await evaluate('Array.from(document.querySelectorAll(".arrival-unit-list footer button")).find(button => button.textContent === "Berikutnya").click()');
                await delay(100);
                assert.ok(await evaluate('document.querySelector(".arrival-unit-row").textContent.includes("DA 11 AB")'));
                await evaluate('var field = document.querySelector(".arrival-unit-list input[type=search]"); field.value = "DA 1000 AB"; field.dispatchEvent(new Event("input", { bubbles: true }))');
                await delay(100);
                assert.equal(await evaluate('document.querySelectorAll(".arrival-unit-row").length'), 0, 'pending filter excludes arrived unit');
                await evaluate('document.querySelector(".arrival-unit-list input[type=checkbox]").click()');
                await delay(100);
                assert.equal(await evaluate('document.querySelectorAll(".arrival-unit-row").length'), 1, 'all-unit search finds unit 1000');
                await evaluate('document.querySelector(".arrival-unit-row button").click()');
                await delay(150);
                assert.ok(await evaluate('document.querySelector("dialog[open]") !== null'));
                assert.ok(await evaluate('document.querySelector("dialog[open]").textContent.includes("Durasi P2H sampai tiba")'), 'arrived unit journey appears only in modal');
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), true, 'monitoring modal stays in viewport');
                const canReschedule = await evaluate('Array.from(document.querySelectorAll("dialog[open] button")).some(button => button.textContent === "Reschedule Sisa Kedatangan")');
                assert.equal(canReschedule, view !== 'arrivals-readonly');
                if (canReschedule) {
                    const disabled = await evaluate('Array.from(document.querySelectorAll("dialog[open] button")).find(button => button.textContent === "Reschedule Sisa Kedatangan").disabled');
                    assert.equal(disabled, view === 'arrivals-locked', 'locked planning cannot reschedule');
                    if (!disabled) {
                        await evaluate('Array.from(document.querySelectorAll("dialog[open] button")).find(button => button.textContent === "Reschedule Sisa Kedatangan").click()');
                        await delay(100);
                        assert.ok(await evaluate('document.querySelector("dialog[open] form input[type=date]") !== null'), 'reschedule fields available');
                        assert.ok(await evaluate('document.querySelector("dialog[open] form").textContent.includes("semua unit yang masih menunggu")'));
                        await evaluate('var field = document.querySelector("dialog[open] form input[type=date]"); field.value = "2026-12-12"; field.dispatchEvent(new Event("input", { bubbles: true })); var reason = document.querySelector("dialog[open] form textarea"); reason.value = "Menunggu pengiriman"; reason.dispatchEvent(new Event("input", { bubbles: true })); document.querySelector("dialog[open] form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))');
                        await delay(250);
                        assert.ok(await evaluate('document.querySelector("dialog[open] form") !== null'), 'validation response keeps reschedule form open');
                        assert.ok(await evaluate('document.querySelector("dialog[open] form").textContent.includes("Tanggal tidak valid")'), 'server validation message stays visible');
                        assert.equal(await evaluate('document.querySelector("dialog[open] form textarea").value'), 'Menunggu pengiriman', 'reschedule reason is retained after validation error');
                    }
                }
                await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
                await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
                await delay(300);
                assert.equal(await evaluate('document.body.style.overflow'), '', 'monitoring modal restores page scroll');
                await evaluate('var field = document.querySelector(".arrival-unit-list input[type=search]"); field.value = ""; field.dispatchEvent(new Event("input", { bubbles: true }))');
                await delay(100);
                await evaluate('window.scrollTo(0, 0)');
                const image = await send('Page.captureScreenshot', { format: 'png' });
                await writeFile(path.join(artifactDir, view + '-' + width + '.png'), Buffer.from(image.data, 'base64'));
            }
            if (view === 'vendor') {
                await evaluate('document.querySelector(".journey-details summary").click()');
                await delay(100);
                assert.ok(await evaluate('document.querySelector(".journey-details > div").textContent.length > 0'));
            }
            if (view === 'go-review') {
                assert.ok(await evaluate('document.querySelector(".go-no-go-menu") !== null'), 'Coordinator menu is available');
                await evaluate('Array.from(document.querySelectorAll("button")).find(button => button.textContent.includes("Lihat Detail")).click()');
                await delay(100);
                assert.ok(await evaluate('document.querySelectorAll("table tbody tr").length >= 11'), 'all checklist details mount when opened');
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), true, 'expanded GO checklist stays within viewport');
            }
            if (view.startsWith('go-')) {
                const screenshot = await send('Page.captureScreenshot', {format:'png'});
                await writeFile(path.join(artifactDir, view + '-' + width + '.png'), Buffer.from(screenshot.data, 'base64'));
            }
            console.log('PASS', width, view);
        }
    }
    console.log('Screenshots:', artifactDir);
    await send('Browser.close');
} finally {
    socket?.close();
    child?.kill();
    await server?.close();
}
