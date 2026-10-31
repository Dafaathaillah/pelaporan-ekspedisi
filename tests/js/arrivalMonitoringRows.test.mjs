import test from 'node:test';
import assert from 'node:assert/strict';
import { buildArrivalRows, filterArrivalRows } from '../../resources/js/lib/arrivalMonitoringRows.js';
import { paginateRows } from '../../resources/js/lib/pagination.js';

const units = Array.from({ length: 1000 }, (_, i) => ({ id: `u-${i + 1}`, plate_number: `DA ${i + 1} AB`, driver_name: `Driver ${i + 1}`, latest_departure_p2h: { status: 'APPROVED' } }));
const item = { id: 'planning-1', planned_date: '2026-11-01', total: 1000, completed: 500, submission: { vendor_name: 'Vendor Alpha', submission_number: 'VND-001', destination: 'Workshop', units }, pending_units: units.slice(0, 500), arrived_units: units.slice(500).map(unit => ({ id: `report-${unit.id}`, vendor_submission_unit_id: unit.id, vehicle_plate: unit.plate_number, driver_name: unit.driver_name })) };

test('1000 monitoring units retain planning progress and mount only ten rows per page', () => {
    const rows = buildArrivalRows([item]);
    assert.equal(rows.length, 1000);
    assert.equal(filterArrivalRows(rows, '', true).length, 500);
    assert.equal(paginateRows(rows, 1).rows.length, 10);
    assert.equal(paginateRows(rows, 100).rows.at(-1).unit.id, 'u-1000');
    assert.equal(rows.at(-1).item.completed, 500);
    assert.equal(rows.at(-1).unit.latest_departure_p2h.status, 'APPROVED');
});

test('search includes units, drivers, permit identity and destination while respecting pending filter', () => {
    const rows = buildArrivalRows([item]);
    assert.equal(filterArrivalRows(rows, ' da 1000 ab ', false).length, 1);
    assert.equal(filterArrivalRows(rows, 'da 1000 ab', true).length, 0);
    assert.equal(filterArrivalRows(rows, 'WORKSHOP', true).length, 500);
    assert.equal(filterArrivalRows(rows, 'VND-001', false).length, 1000);
});

test('legacy arrival reports without matching unit identity and empty planning remain visible', () => {
    const legacy = { ...item, id: 'legacy', pending_units: [], arrived_units: [{ id: 'legacy-report', vehicle_plate: 'LEGACY' }], submission: { ...item.submission, units: [] } };
    const rows = buildArrivalRows([legacy, { ...legacy, id: 'empty', arrived_units: [] }]);
    assert.equal(rows.length, 2);
    assert.equal(rows[0].report.vehicle_plate, 'LEGACY');
    assert.equal(rows[1].state, 'NO_UNIT');
    assert.equal(filterArrivalRows(rows, '', true).length, 0);
});
