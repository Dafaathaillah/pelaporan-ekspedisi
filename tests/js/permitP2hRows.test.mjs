import test from 'node:test';
import assert from 'node:assert/strict';
import { buildFollowUpRows, filterFollowUpRows, followUpPage } from '../../resources/js/lib/permitP2hRows.js';

const permit = { id: 'permit-1', vendor_name: 'Vendor Alpha', submission_number: 'VND-001', units: Array.from({ length: 1000 }, (_, i) => ({ id: `unit-${i + 1}`, plate_number: `DA ${i + 1} AB`, driver_name: `Driver ${i + 1}`, p2h: i % 2 ? { status: 'PENDING' } : null })) };

test('1000 units produce bounded ten-row pages without losing records', () => {
    const rows = buildFollowUpRows([permit]);
    assert.equal(rows.length, 1000);
    assert.equal(followUpPage(rows, 1).rows.length, 10);
    assert.equal(followUpPage(rows, 1).pages, 100);
    assert.equal(followUpPage(rows, 100).rows.at(-1).unit.id, 'unit-1000');
    assert.equal(followUpPage(rows, 101).page, 100);
});

test('search and stage filter combine across every unit on the server page', () => {
    const rows = buildFollowUpRows([permit]);
    assert.equal(filterFollowUpRows(rows, ' da 1000 ab ', 'AWAITING_VENDOR').length, 1);
    assert.equal(filterFollowUpRows(rows, 'da 1000 ab', 'AWAITING_P2H').length, 0);
    assert.equal(filterFollowUpRows(rows, 'vendor alpha', 'AWAITING_VENDOR').length, 500);
    assert.equal(filterFollowUpRows(rows, 'vnd-001', '').length, 1000);
});

test('permits without vehicles remain visible and empty search pages remain valid', () => {
    const rows = buildFollowUpRows([{ ...permit, units: [] }]);
    assert.equal(rows.length, 1);
    assert.equal(rows[0].stage, 'NO_UNIT');
    assert.equal(rows[0].unit, null);
    assert.deepEqual(followUpPage([], 5), { page: 1, pages: 1, rows: [], from: 0, to: 0 });
});
