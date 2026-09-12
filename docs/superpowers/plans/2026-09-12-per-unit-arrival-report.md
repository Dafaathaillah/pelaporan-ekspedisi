# Per-Unit Arrival Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace manual planning/unit selection with secure, independent per-unit arrival cards.

**Architecture:** Laravel resolves permit and planning ownership from a submitted unit UUID. Vue keeps permit selection in the page and delegates each independent arrival form to a reusable unit-card component.

**Tech Stack:** Laravel, PHPUnit, Inertia.js, Vue 3, Tailwind CSS, Vite.

**Spec:** `docs/superpowers/specs/2026-09-12-per-unit-arrival-report-design.md`

## Global Constraints

- Do not change existing permit release, planning, monitoring, photo resize, GPS, or report-history behavior.
- One unit may create at most one report on its bound planning item.
- Completed units remain visible and read-only.

---

### Task 1: Secure automatic planning resolution

**Files:**
- Modify: `tests/Feature/ArrivalPlanningTest.php`
- Modify: `app/Http/Requests/StoreExpeditionReportRequest.php`
- Modify: `app/Http/Controllers/ExpeditionController.php`

**Interfaces:**
- Consumes: `vendor_submission_unit_id: UUID` from a unit card.
- Produces: authoritative `planning_item_id` and identity values merged into the validated request.

- [ ] Add a feature test posting a unit without `planning_item_id`, asserting its bound planning ID and permit identity are saved.
- [ ] Run `php artisan test tests/Feature/ArrivalPlanningTest.php` and confirm the new assertion fails.
- [ ] Resolve accessible Released permit and unit-bound planning in `prepareForValidation()`; include report timestamps in page props.
- [ ] Run the focused test and confirm it passes, including duplicate and unauthorized attempts.

### Task 2: Independent unit cards

**Files:**
- Create: `resources/js/Components/ExpeditionArrivalUnitCard.vue`
- Modify: `resources/js/Pages/Expedition/Index.vue`

**Interfaces:**
- Consumes: `permit`, `unit`, `planning`, `completedReport`, `expeditionCompany`, `reportBase`.
- Produces: multipart POST containing `vendor_submission_unit_id`, pressure values, GPS evidence, and required photos.

- [ ] Move unit-specific form, pressure assessment, camera previews, GPS capture, and POST behavior into the component.
- [ ] Keep only permit selection in the page and render all units in stable permit order.
- [ ] Render completed units with a green timestamp badge and disable resubmission; render incomplete/unplanned units with an actionable warning.
- [ ] Run `npm run build` and fix all compile failures.

### Task 3: Regression verification

**Files:**
- Test: `tests/Feature/ArrivalPlanningTest.php`
- Test: existing application test suite.

**Interfaces:**
- Consumes: completed backend and frontend changes.
- Produces: verification evidence for release.

- [ ] Run `php artisan test tests/Feature/ArrivalPlanningTest.php`.
- [ ] Run `php artisan test`.
- [ ] Run `npm run build`.
- [ ] Inspect `git diff --check` and the final diff for unintended changes.
