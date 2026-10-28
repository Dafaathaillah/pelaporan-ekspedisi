import test from 'node:test';
import assert from 'node:assert/strict';
import { journeyDuration, journeyTime } from '../../resources/js/lib/p2hJourney.js';

test('tracks from P2H submission until arrival, then stops even as time advances', () => {
    const start = '2026-10-28T01:00:00Z';
    const arrival = '2026-10-28T04:15:30Z';
    assert.equal(journeyDuration(start, null, Date.parse('2026-10-28T03:00:00Z')), '02:00:00');
    assert.equal(journeyDuration(start, arrival, Date.parse('2026-10-29T00:00:00Z')), '03:15:30');
});

test('supports journeys longer than one day and rejects missing or inconsistent timestamps', () => {
    assert.equal(journeyDuration('2026-10-27T00:00:00Z', '2026-10-28T02:00:00Z'), '26:00:00');
    assert.equal(journeyDuration(null, null), null);
    assert.equal(journeyDuration('invalid', null), null);
    assert.equal(journeyDuration('2026-10-28T01:00:00Z', 'invalid'), null);
    assert.equal(journeyDuration('2026-10-28T02:00:00Z', '2026-10-28T01:00:00Z'), null);
});

test('displays timestamps in WITA regardless of the browser timezone', () => {
    assert.match(journeyTime('2026-10-28T01:15:30Z'), /09[.:]15[.:]30/);
    assert.match(journeyTime('2026-10-28T01:15:30Z'), /WITA/);
    assert.equal(journeyTime(null), '-');
    assert.equal(journeyTime('invalid'), '-');
});
