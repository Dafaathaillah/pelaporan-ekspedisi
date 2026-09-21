import test from "node:test";
import assert from "node:assert/strict";
import {
    formatEscortCountdown,
    isEscortReady,
    remainingEscortSeconds,
} from "../../resources/js/lib/escortReadiness.js";

test("counts down to the ready timestamp and floors at zero", () => {
    const readyAt = "2026-09-21T17:00:00.000Z";

    assert.equal(
        remainingEscortSeconds(
            readyAt,
            Date.parse("2026-09-21T10:00:01.000Z"),
        ),
        25199,
    );
    assert.equal(
        remainingEscortSeconds(
            readyAt,
            Date.parse("2026-09-21T17:00:01.000Z"),
        ),
        0,
    );
});

test("formats countdown with stable hours minutes and seconds", () => {
    assert.equal(formatEscortCountdown(25199), "06:59:59");
    assert.equal(formatEscortCountdown(0), "00:00:00");
});

test("becomes ready at the boundary and preserves internal exemption", () => {
    const now = Date.parse("2026-09-21T17:00:00.000Z");

    assert.equal(
        isEscortReady(
            {
                ready_escort_at: "2026-09-21T17:00:00.000Z",
                is_ready_for_escort: false,
            },
            now,
        ),
        true,
    );
    assert.equal(
        isEscortReady(
            { internal_ijin_lintas_id: "permit-id", is_ready_for_escort: true },
            now,
        ),
        true,
    );
});
