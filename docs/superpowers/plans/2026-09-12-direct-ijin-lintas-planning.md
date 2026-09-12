# Direct Ijin Lintas Planning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add site-scoped multi-unit ijin lintas planning that does not require or alter vendor submissions.

**Architecture:** Store direct planning aggregates in dedicated header and unit tables. Merge an explicit direct-unit view model into the existing calendar response while keeping every submission-backed model, service, route, and mutation contract intact.

**Tech Stack:** Laravel 12, PHP 8.2+, MySQL/SQLite, Inertia.js, Vue 3 `<script setup>`, Tailwind CSS, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-12-direct-ijin-lintas-planning-design.md`

## Global Constraints

- Do not change existing submission-driven planning behavior, payloads, routes, or notifications.
- Only active coordinator, purchasing administrator, and developer users may mutate direct planning.
- Direct planning data and mutations are isolated by the authenticated user's site.
- Vendor selection uses active records from the global vendor master.
- A direct planning contains at least one unit; every unit may have an independent date and route.
- Locked direct planning cannot be edited or deleted until unlocked.
- All aggregate writes are transactional and deletion uses soft deletes.

---

### Task 1: Direct planning persistence and domain relationships

**Files:**
- Create: `database/migrations/2026_09_12_000030_create_direct_plannings.php`
- Create: `app/Models/DirectPlanning.php`
- Create: `app/Models/DirectPlanningUnit.php`
- Test: `tests/Feature/DirectPlanningTest.php`

**Interfaces:**
- Produces: `DirectPlanning::units()`, `DirectPlanning::vendorCompany()`, `DirectPlanningUnit::planning()` and soft-delete support.

- [ ] **Step 1: Write a failing persistence test**

Create a feature test that inserts a header with two unit rows, asserts UUID IDs and relationships, soft-deletes the header, and asserts it is excluded from normal queries.

- [ ] **Step 2: Verify RED**

Run `php artisan test tests/Feature/DirectPlanningTest.php --filter=persistence`; expect failure because the models and tables do not exist.

- [ ] **Step 3: Implement the migration and models**

Create the schema and focused Eloquent relationships described in the spec. Cast lock timestamps, booleans, dates, and use `HasUuids` and `SoftDeletes` where required.

- [ ] **Step 4: Verify GREEN**

Run the targeted persistence test and expect PASS.

### Task 2: Authorized transactional CRUD and lock operations

**Files:**
- Create: `app/Http/Controllers/DirectPlanningController.php`
- Create: `app/Http/Requests/SaveDirectPlanningRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DirectPlanningTest.php`

**Interfaces:**
- Consumes: direct planning models from Task 1.
- Produces: `store`, `update`, `lock`, and `destroy` endpoints under coordinator and purchasing prefixes.

- [ ] **Step 1: Write failing authorization and CRUD tests**

Cover multi-unit creation by every permitted role, invalid/inactive vendor, cross-site mutation, unsupported roles, empty units, out-of-range dates, equal normalized routes, duplicate normalized plates, atomic unit synchronization, locking, unlocking, and soft deletion.

- [ ] **Step 2: Verify RED**

Run `php artisan test tests/Feature/DirectPlanningTest.php`; expect endpoint-not-found or missing-controller failures.

- [ ] **Step 3: Implement request validation and controller operations**

Normalize strings in `prepareForValidation`, add after-validation checks for routes and duplicate plates, authorize roles/sites, and wrap store/update/delete aggregate operations in transactions. Return specific Indonesian flash messages.

- [ ] **Step 4: Register routes without changing existing routes**

Add coordinator direct-planning routes inside the coordinator role group and purchasing routes inside the purchasing role group, reusing the same controller.

- [ ] **Step 5: Verify GREEN**

Run the complete direct-planning feature test and expect PASS.

### Task 3: Calendar read model integration

**Files:**
- Modify: `app/Http/Controllers/PlanningController.php`
- Test: `tests/Feature/DirectPlanningTest.php`

**Interfaces:**
- Consumes: `DirectPlanning` aggregates.
- Produces: Inertia props `directItems`, `directPlannings`, `vendors`, `vehicleTypes`, and `directPlanningBase`; existing props remain unchanged.

- [ ] **Step 1: Write failing calendar response tests**

Assert direct unit cards are returned only for the current site and date window with `source=direct`, while an existing submission planning remains unchanged in `items`.

- [ ] **Step 2: Verify RED**

Run the calendar-specific test and expect missing-prop failures.

- [ ] **Step 3: Add the separate direct read query and serializer**

Load direct planning headers with vendor and units, serialize edit payloads once per aggregate, and provide active vendor and vehicle type options. Do not alter the existing `items` query or transformation.

- [ ] **Step 4: Verify GREEN and existing planning tests**

Run `php artisan test tests/Feature/DirectPlanningTest.php tests/Feature/OperationalWorkflowTest.php tests/Feature/ArrivalPlanningTest.php` and expect PASS.

### Task 4: Direct-planning modal and calendar cards

**Files:**
- Create: `resources/js/Components/DirectPlanningModal.vue`
- Modify: `resources/js/Pages/Coordinator/Planning.vue`

**Interfaces:**
- Consumes: direct-planning Inertia props and REST-style routes from Tasks 2-3.
- Produces: create/edit modal, repeatable unit form, direct cards, and edit/delete/lock controls.

- [ ] **Step 1: Add the focused modal component**

Use the existing `Modal`, `SearchableSelect`, `InputLabel`, `InputError`, and button patterns. Support create/edit initialization, repeatable unit blocks, nested Inertia validation errors, loading states, and responsive scrolling.

- [ ] **Step 2: Integrate direct cards without modifying existing card behavior**

Add the primary action, render `directItems` in their date columns with a visible `Planning Langsung` label, and route group-level edit/delete/lock actions through `directPlanningBase`.

- [ ] **Step 3: Verify the production build**

Run `npm.cmd run build`; expect a clean successful Vite build.

### Task 5: Full verification and formatting

**Files:**
- Modify only files reported by formatters when needed.

**Interfaces:**
- Consumes: all completed tasks.
- Produces: verified feature with no regression to current flow.

- [ ] **Step 1: Format changed PHP files**

Run `vendor/bin/pint --dirty`.

- [ ] **Step 2: Run the full backend suite**

Run `php artisan test`; expect all tests to pass.

- [ ] **Step 3: Re-run the frontend production build**

Run `npm.cmd run build`; expect success.

- [ ] **Step 4: Check migrations, routes, and patch hygiene**

Run `php artisan migrate:status`, `php artisan route:list --path=direct-plannings`, and `git diff --check`; confirm the new migration is known, routes are role-scoped, and there is no whitespace damage.

- [ ] **Step 5: Review compatibility contract**

Confirm no existing submission-backed endpoint, `PlanningService`, model relationship, or existing card action was removed or repurposed.
