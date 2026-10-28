import test from 'node:test';
import assert from 'node:assert/strict';
import { unitP2hStage } from '../../resources/js/lib/p2hFollowUp.js';

test('the next actor moves from expedition to vendor, then back to expedition', () => {
    assert.equal(unitP2hStage(null, null), 'AWAITING_P2H');
    assert.equal(unitP2hStage({ status: 'PENDING' }, null), 'AWAITING_VENDOR');
    assert.equal(unitP2hStage({ status: 'REJECTED' }, null), 'NEEDS_REVISION');
    assert.equal(unitP2hStage({ status: 'APPROVED' }, null), 'AWAITING_DEPARTURE');
    assert.equal(unitP2hStage({ status: 'APPROVED', departed_at: '2026-10-28T00:00:00Z' }, null), 'IN_TRANSIT');
    assert.equal(unitP2hStage({ status: 'APPROVED', departed_at: '2026-10-28T00:00:00Z' }, '2026-10-28T01:00:00Z'), 'ARRIVED');
});

test('a legacy arrival remains completed and cannot be sent back to P2H', () => {
    assert.equal(unitP2hStage(null, '2026-10-01T00:00:00Z'), 'ARRIVED');
});
