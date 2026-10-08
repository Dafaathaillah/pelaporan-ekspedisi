import test from "node:test";
import assert from "node:assert/strict";
import * as expeditionRequest from "../../resources/js/Utils/expeditionRequest.js";

test("formats a planned date for display on expedition requests", () => {
    assert.equal(
        typeof expeditionRequest.formatPlannedDate,
        "function",
        "formatPlannedDate must be exported",
    );
    assert.equal(
        expeditionRequest.formatPlannedDate("2026-10-08"),
        "08 Okt 2026",
    );
    assert.equal(expeditionRequest.formatPlannedDate(null), "-");
});
