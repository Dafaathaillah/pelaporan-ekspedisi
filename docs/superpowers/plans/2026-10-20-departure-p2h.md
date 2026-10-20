# Departure P2H Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require an approved digital P2H and confirmed departure for each released-permit unit before arrival.

**Architecture:** Store immutable P2H revisions per permit unit. Protect each transition in the Laravel controller and block arrivals in server validation. Render the same latest revision in expedition, vendor, and monitoring views, with a PDF download endpoint.

**Tech Stack:** Laravel 12, PHP 8.2, Vue 3, Inertia, dompdf, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-20-departure-p2h-design.md`

## Global Constraints

- Keep the existing required P2H document upload at permit application.
- Never trust driver identity or vendor ownership from the form payload.
- Keep existing user changes staged or unstaged as found.

---

### Task 1: Persist unit P2H revisions

**Files:** `database/migrations/2026_10_20_000036_create_departure_p2hs.php`, `app/Models/DepartureP2h.php`, `app/Models/VendorSubmissionUnit.php`, `app/Support/P2hChecklist.php`, `tests/Feature/DepartureP2hWorkflowTest.php`.

**Interfaces:** `VendorSubmissionUnit::latestDepartureP2h()` returns the highest revision. `DepartureP2h` casts inspection answers and timestamps.

- [x] Write tests for revision history, approval state, and identity source.
- [x] Run tests and observe failure before implementation.
- [x] Add migration, model, relation, and checklist.
- [x] Run the focused tests again.

### Task 2: Enforce lifecycle and access

**Files:** `app/Http/Controllers/DepartureP2hController.php`, `app/Http/Requests/StoreExpeditionReportRequest.php`, `app/Services/ArrivalPlanningService.php`, `routes/web.php`, `tests/Feature/DepartureP2hWorkflowTest.php`, `tests/Feature/ArrivalPlanningTest.php`.

**Interfaces:** `POST /expedition/departures/{unit}/p2h`, `PATCH /vendor/departures/{unit}/review`, `POST /expedition/departures/{unit}/confirm`.

- [x] Test blocked arrival, rejection, revision, and cross-vendor denial.
- [x] Observe failing tests.
- [x] Add server validation and transaction-safe transitions.
- [x] Run focused workflow tests.

### Task 3: Show form, review, and monitoring

**Files:** `resources/js/Components/DepartureP2hCard.vue`, `resources/js/Components/ExpeditionArrivalUnitCard.vue`, `resources/js/Pages/Expedition/Index.vue`, `resources/js/Pages/Vendor/Show.vue`, `resources/js/Pages/Vendor/Index.vue`, `resources/js/Pages/Expedition/ArrivalMonitoring.vue`, `app/Http/Controllers/ExpeditionController.php`, `app/Http/Controllers/VendorController.php`.

**Interfaces:** Existing Inertia permit units expose `latest_departure_p2h`; vendor detail exposes revision history.

- [x] Add per-unit expedition form and departure action.
- [x] Add vendor review controls and pending indicator.
- [x] Lock arrival card until departure and show status/download in monitoring.
- [x] Run Vite production build.

### Task 4: Generate and verify PDF

**Files:** `app/Services/P2hPdf.php`, `resources/views/pdf/p2h.blade.php`, `app/Http/Controllers/DepartureP2hController.php`, `composer.json`, `composer.lock`, `tests/Feature/DepartureP2hWorkflowTest.php`.

**Interfaces:** `GET /departure-p2h/{unit}/download` returns an attachment with `application/pdf` for authorized site users.

- [x] Test site and role restrictions and PDF response.
- [x] Observe missing response before implementation.
- [x] Render checklist and approval stamps with dompdf.
- [x] Run PDF feature test, complete PHP tests, and frontend build.
