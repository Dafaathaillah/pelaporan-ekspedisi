# SCM Request and Escort Administration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add vendor readiness decisions, mandatory SCM requests, post-escort administration, arrival timers, and a single coordinator session.

**Architecture:** Extend existing request and report records with scoped status fields. Add a finance role and private document endpoints. Keep the timer derived from existing arrival and escort timestamps, and enforce coordinator session uniqueness in middleware.

**Tech Stack:** Laravel 12, PHP 8.2, Vue 3, Inertia, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-20-scm-escort-administration-design.md`

## Global Constraints

- Preserve existing P2H departure work and user changes in the worktree.
- Invoice bytes are readable only by finance users at the same site.
- Server validation enforces all workflow gates regardless of UI state.

---

### Task 1: Request readiness and mandatory request association

**Files:** `app/Http/Controllers/ExpeditionRequestController.php`, `app/Http/Controllers/VendorController.php`, `resources/js/Pages/Vendor/ExpeditionRequests.vue`, `resources/js/Pages/Vendor/Index.vue`, `resources/js/Pages/Purchasing/Requests.vue`, `routes/web.php`, request migration and feature tests.

**Interfaces:** `PATCH /vendor/expedition-requests/{expeditionRequest}/readiness` accepts `READY` or `NOT_READY` and a reason for `NOT_READY`.

- [ ] Write feature tests for vendor ownership, readiness, Non-Reg validation, and coordinator route denial.
- [ ] Run tests to verify failures.
- [ ] Add migration, endpoint, request validation, and UI actions/status.
- [ ] Run focused tests.

### Task 2: Escort administration and finance access

**Files:** `app/Enums/UserRole.php`, `app/Models/ExpeditionReport.php`, `app/Http/Controllers/EscortAdministrationController.php`, `app/Http/Controllers/UserManagementController.php`, `app/Http/Requests/SaveUserRequest.php`, `resources/js/Pages/Vendor/MonitoringSarana.vue`, finance page, report migration, route file, and feature tests.

**Interfaces:** Vendor uploads `invoice` or `spb` PDF by report ID. Finance lists site reports and downloads documents. Report exposes `administration_status`.

- [ ] Write tests for escort prerequisite, vendor ownership, PDF validation, automatic close, and finance-only invoice download.
- [ ] Run tests to verify failures.
- [ ] Add private storage endpoints, role management, finance page, and vendor upload controls.
- [ ] Run focused tests.

### Task 3: Arrival timer

**Files:** Shared Vue timer component, coordinator/vendor/expedition/purchasing arrival views, report model and feature/JS tests.

**Interfaces:** `ArrivalEscortTimer` receives `arrivedAt` and nullable `escortStartedAt` and displays elapsed time.

- [ ] Test elapsed calculation with waiting and started timestamps.
- [ ] Run test to verify failure.
- [ ] Add component and display it in each required view.
- [ ] Run JS test and frontend build.

### Task 4: Single coordinator session

**Files:** User migration/model, login/logout controller, coordinator session middleware, bootstrap middleware registration, and auth feature tests.

**Interfaces:** A successful coordinator login records a hash of the regenerated session ID; later coordinator requests must match it.

- [ ] Write tests for second login invalidating first session and other roles retaining parallel sessions.
- [ ] Run tests to verify failures.
- [ ] Add fingerprint update, middleware verification, and safe logout clearing.
- [ ] Run focused tests.

### Task 5: Final verification

- [ ] Run PHP feature and complete suites, JS tests, Vite build, Pint, migration status, and diff checks.
- [ ] Report exactly which migrations were applied and any remaining operational steps.
