# Driver Escort Batch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow a driver to claim and close any number of selected units as one atomic escort batch using one P5M photo and one close location.

**Architecture:** Add a nullable indexed UUID `escort_batch_id` to reports. Introduce batch claim/close controller actions that lock every member and update all-or-nothing, then group reports by batch in the driver UI and expose batch members in coordinator payloads.

**Tech Stack:** Laravel, PHPUnit, Inertia.js, Vue 3, Tailwind CSS, Vite.

**Spec:** `docs/superpowers/specs/2026-09-12-driver-escort-batch-design.md`

## Global Constraints

- Accept one or more unique report UUIDs without an upper limit.
- Store one private P5M path for every member of the batch.
- Reject the whole operation if any member is missing, belongs to another site, or has changed status.
- Treat a legacy report without `escort_batch_id` as a one-unit batch.
- Preserve the existing 100-meter GPS accuracy limit and photo authorization.
- Do not commit automatically.

---

### Task 1: Persist and Claim Escort Batches

**Files:**
- Create: `database/migrations/2026_09_12_000029_add_escort_batch_id_to_expedition_reports.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/DriverEscortController.php`
- Test: `tests/Feature/DriverEscortWorkflowTest.php`

**Interfaces:**
- Consumes: `POST /driver/escort-batches` with `report_ids[]` and `p5m_photo`.
- Produces: shared `escort_batch_id`, P5M path, driver ID, and start time on all selected reports.

- [ ] Add failing tests proving two reports receive the same non-null batch ID/path and one stored file.
- [ ] Add a failing conflict test proving no selected report changes when one is already claimed.
- [ ] Run `php artisan test tests/Feature/DriverEscortWorkflowTest.php` and confirm RED because the route/column do not exist.
- [ ] Add the nullable indexed UUID migration and the authenticated driver batch route.
- [ ] Implement validation: `report_ids` required array/min 1, each UUID distinct, P5M required image/max 8192.
- [ ] Lock reports in sorted ID order; require exact count, same site, `WAITING`, and null driver; store once and update all in one transaction. Delete the file on failure.
- [ ] Keep the existing single-report claim action for compatibility by delegating to the same batch service logic with one ID.
- [ ] Run the focused test and confirm GREEN.

### Task 2: Close Batches Atomically

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/DriverEscortController.php`
- Test: `tests/Feature/DriverEscortWorkflowTest.php`

**Interfaces:**
- Consumes: `POST /driver/escort-batches/{report}/close` with the existing GPS payload.
- Produces: identical close driver/time/location on every active member.

- [ ] Add a failing test that starts two reports as one batch, closes by one representative ID, and asserts both become `CLOSED`.
- [ ] Add a failing test where one member has changed status and assert the other member remains `PROCESS`.
- [ ] Confirm RED with the focused PHPUnit command.
- [ ] Resolve members by `escort_batch_id`, falling back to the representative report only when null; lock all and validate site/status before updating.
- [ ] Broadcast each refreshed member after commit so all dashboards refresh.
- [ ] Run the focused test and confirm GREEN.

### Task 3: Multi-Select and Grouped Driver UI

**Files:**
- Modify: `resources/js/Pages/Driver/Escorts.vue`
- Reuse: `resources/js/Components/P5mCameraCapture.vue`

**Interfaces:**
- Consumes: selected report IDs and the batch endpoints from Tasks 1–2.
- Produces: unlimited checkbox selection, one camera capture, grouped active cards, and one batch close action.

- [ ] Replace `selectedClaim` report state with a `selectedIds` array and computed `selectedReports`.
- [ ] Add accessible **Pilih Unit** checkboxes and selected-card states; remove IDs that disappear after reload.
- [ ] Add a sticky selection bar showing `<count> unit dipilih`, reset action, and **Ambil P5M & Mulai Escort**.
- [ ] Open the existing camera modal for the selection and submit `report_ids` plus one photo to `/driver/escort-batches`.
- [ ] Group active reports by `escort_batch_id || id`, render all plates/report numbers in the group, and call the batch-close endpoint with a representative report.
- [ ] Preserve camera cleanup, error states, responsive layout, and disabled states.
- [ ] Run `npm.cmd run build` and confirm success; confirm no `type="file"` exists in the driver camera flow.

### Task 4: Coordinator Batch Context

**Files:**
- Modify: `app/Http/Controllers/CoordinatorController.php`
- Modify: `resources/js/Pages/Coordinator/Index.vue`
- Test: `tests/Feature/DriverEscortWorkflowTest.php`

**Interfaces:**
- Produces: `escort_batch_members` containing ID, report number, plate, and type on each paginated report.

- [ ] Add a failing Inertia assertion that a batch report contains both members.
- [ ] Eager-load or append batch members without changing pagination or exports.
- [ ] In escort detail, show the batch identifier and a compact member list; retain one attributed P5M preview/download section.
- [ ] Run the focused PHPUnit test and Vite build.

### Task 5: Full Verification

**Files:** all modified and created files.

- [ ] Run `php artisan test` and require zero failures.
- [ ] Run `npm.cmd run build` and require exit code 0.
- [ ] Run `git diff --check` and inspect `git status --short`.
- [ ] Confirm migration up/down behavior, one/many selection, atomic conflict handling, one stored photo, grouped close, site isolation, legacy fallback, responsive UI, and camera track cleanup.
- [ ] Leave every change uncommitted in the worktree.
