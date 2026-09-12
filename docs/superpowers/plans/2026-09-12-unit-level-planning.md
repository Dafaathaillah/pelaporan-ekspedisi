# Unit-Level Planning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make direct and submitted vehicle units independently draggable planning cards.

**Architecture:** Bind submission planning rows to vendor units with a nullable foreign key and move direct unit dates through a dedicated endpoint. Preserve generic legacy behavior when no unit binding exists.

**Tech Stack:** Laravel 12, Vue 3, Inertia, Tailwind, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-12-unit-level-planning-design.md`

## Global Constraints

- Preserve submission review, release, documents, and notifications.
- One vehicle unit maps to one independently scheduled card.
- Direct sibling unit dates remain independent.
- Preserve legacy generic planning when unit binding is unavailable.

### Task 1: Direct unit movement

**Files:** `tests/Feature/DirectPlanningTest.php`, `app/Http/Controllers/DirectPlanningController.php`, `routes/web.php`, `resources/js/Pages/Coordinator/Planning.vue`.

- [ ] Write and run failing move, lock, and site tests.
- [ ] Add transactional `moveUnit` endpoint and routes.
- [ ] Make direct cards draggable and touch-selectable.
- [ ] Run targeted tests and build.

### Task 2: Per-unit submission persistence

**Files:** new migration `2026_09_12_000031_add_unit_to_planning_items.php`, `app/Models/PlanningItem.php`, `app/Services/PlanningService.php`, workflow tests.

- [ ] Write failing tests proving one item per unit.
- [ ] Add nullable unit foreign key, unique constraint, relationships, and safe data migration.
- [ ] Synchronize per-unit items for IJIN_LINTAS while retaining generic fallback.
- [ ] Run targeted tests.

### Task 3: Per-unit calendar and arrival behavior

**Files:** `app/Http/Controllers/PlanningController.php`, `app/Services/ArrivalPlanningService.php`, `app/Http/Requests/StoreExpeditionReportRequest.php`, relevant Vue pages and feature tests.

- [ ] Write failing response and bound-unit arrival tests.
- [ ] Load and render the bound unit per card.
- [ ] Restrict progress and arrival validation to the bound unit.
- [ ] Update vendor/expedition planning consumers without changing release flow.
- [ ] Run targeted and regression tests.

### Task 4: Verification

- [ ] Run Pint, full PHP test suite, Vite production build, migration status, route list, and `git diff --check`.
- [ ] Commit all work on `development` and keep the branch in place.
