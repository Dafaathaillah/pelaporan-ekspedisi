# Ijin Lintas Release and Planning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable direct Non-Reguler submissions, mandatory PDF attachments on release, persistent Vendor release notifications, and Vendor-managed multi-date planning with reviewer lock/unlock.

**Architecture:** Extend `vendor_submissions` with private release-document metadata and a Vendor-read timestamp, and extend each `planning_item` with independent locking metadata. Keep submission lifecycle actions in the existing reviewer controller, but isolate Vendor planning CRUD in a dedicated controller and expose reviewer planning actions through shared site-scoped authorization.

**Tech Stack:** Laravel 12, PHP 8, SQLite/MySQL-compatible migrations, Inertia.js, Vue 3 `<script setup>`, Tailwind CSS, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-04-ijin-lintas-release-planning-design.md`

## Global Constraints

- Reguler requires an active Incoming Request for the authenticated Vendor company.
- Non-Reguler accepts no Incoming Request, PR, or PO and must persist `expedition_request_id = null`.
- A transition to `RELEASED` for Ijin Lintas requires one private PDF no larger than 5 MB.
- Vendor planning is available only after release and before expiry.
- Each planning date is independently lockable; Vendor mutations must be rejected server-side while locked.
- Existing released records and existing planning rows remain readable.

---

### Task 1: Direct Non-Reguler Submission

**Files:**
- Modify: `tests/Feature/ExpeditionRequestWorkflowTest.php`
- Modify: `app/Http/Controllers/VendorController.php`
- Modify: `resources/js/Pages/Vendor/Index.vue`

**Interfaces:**
- Consumes: `StoreVendorSubmissionRequest`, `ExpeditionRequest`, `planning_frequency`.
- Produces: `VendorController::storeUnits()` behavior where `NON_REGULAR` does not require or persist an expedition request.

- [ ] **Step 1: Write failing feature tests**

Add one test posting `IJIN_LINTAS` with `planning_frequency=NON_REGULAR`, no `expedition_request_id`, a valid unit, and all required PDFs; assert success and a null relation. Add a second assertion that `WEEKLY` without a request returns an `expedition_request_id` validation error. Include a malicious request ID in the Non-Reguler payload and assert it is discarded.

- [ ] **Step 2: Verify RED**

Run `php artisan test tests/Feature/ExpeditionRequestWorkflowTest.php --filter=non_regular` and confirm the existing unconditional request lookup rejects the direct submission.

- [ ] **Step 3: Implement category-aware validation**

In `storeUnits()`, derive Non-Reguler from `planning_frequency === 'NON_REGULAR'`. Only load and validate `ExpeditionRequest` for Reguler values `DAILY` and `WEEKLY`. Always set `expedition_request_id` to null for Non-Reguler, even if the client sends one. Mark a request `USED` only when a Reguler submission actually references it.

- [ ] **Step 4: Update the Vendor form**

Show Incoming Request only when the selected frequency is `DAILY` or `WEEKLY`. Clear `form.expedition_request_id` when Non-Reguler is selected and show concise helper copy that direct submission does not require PR/PO.

- [ ] **Step 5: Verify GREEN**

Run the filtered feature test and `npm.cmd run build`; both must pass.

### Task 2: Mandatory Release PDF and Vendor Notification

**Files:**
- Create: `database/migrations/2026_09_04_000026_add_release_document_to_vendor_submissions.php`
- Modify: `tests/Feature/OperationalWorkflowTest.php`
- Modify: `tests/Feature/RealtimeWorkflowTest.php`
- Modify: `app/Http/Requests/UpdateSubmissionStatusRequest.php`
- Modify: `app/Http/Controllers/CoordinatorController.php`
- Modify: `app/Models/VendorSubmission.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `app/Events/VendorSubmissionStatusChanged.php`
- Modify: `app/Support/OperationalPushDispatcher.php`
- Modify: `app/Http/Controllers/DocumentController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Coordinator/Show.vue`
- Modify: `resources/js/Components/AppShell.vue`
- Modify: `resources/js/echo.js`

**Interfaces:**
- Produces columns `release_document_path`, `release_document_name`, `release_document_size`, and `vendor_release_opened_at` on `vendor_submissions`.
- Produces authenticated route `GET /submissions/{submission}/release-document`.
- Extends `UpdateSubmissionStatusRequest` with nullable `release_document`, conditionally required for Ijin Lintas release.

- [ ] **Step 1: Write failing release tests**

Test that `APPROVED -> RELEASED` for Ijin Lintas fails without `release_document`, rejects a non-PDF and a file over 5120 KB, then succeeds with `UploadedFile::fake()->create('izin-release.pdf', 100, 'application/pdf')`. Assert private storage metadata, status, release timestamps, download authorization, and that an unauthorized Vendor receives 403.

- [ ] **Step 2: Verify RED**

Run the targeted Operational workflow test and confirm release currently succeeds without a file or fails to persist the expected metadata.

- [ ] **Step 3: Add schema and transactional storage**

Add nullable metadata/read columns. Validate `release_document` as `file|mimes:pdf|mimetypes:application/pdf|max:5120` and require it only when the current Ijin Lintas submission is moving to `RELEASED`. Store the file under `private/released-ijin-lintas/{submission-id}` inside the release action, ensure status is updated only after storage succeeds, and delete the newly stored file if the database transaction fails.

- [ ] **Step 4: Add download and unread count**

Add a release-document action in `DocumentController` with the same owner/site/reviewer rules used for Vendor documents. In shared Inertia props, add `released_ijin_lintas` for the authenticated Vendor by counting owned `RELEASED` submissions with null `vendor_release_opened_at`.

- [ ] **Step 5: Add UI and event payload**

Submit the reviewer status form as FormData. When `RELEASED` is selected, render `FileDropInput` for the PDF and show validation beside it. Add a Vendor **Planning Lintas** menu with bell badge. Point release realtime/push messages to `/vendor/planning-lintas` and include release-document availability in the event payload.

- [ ] **Step 6: Verify GREEN**

Run the targeted Operational and Realtime tests, then run `npm.cmd run build`.

### Task 3: Vendor Multi-Date Planning

**Files:**
- Create: `app/Http/Controllers/VendorPlanningController.php`
- Create: `resources/js/Pages/Vendor/PlanningLintas.vue`
- Modify: `tests/Feature/ExpeditionRequestWorkflowTest.php`
- Modify: `app/Services/PlanningService.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Components/AppShell.vue`

**Interfaces:**
- Produces routes `GET /vendor/planning-lintas`, `POST /vendor/planning-lintas/{submission}`, `PATCH /vendor/planning-lintas/items/{planningItem}`, and `DELETE /vendor/planning-lintas/items/{planningItem}`.
- Produces controller methods `index`, `store`, `update`, and `destroy`.

- [ ] **Step 1: Write failing CRUD and authorization tests**

Create released, approved, expired, foreign-owner, and active Ijin Lintas fixtures. Assert the owner can add multiple valid dates to the released active submission, update and delete an unlocked item, and receives validation/authorization failures for the other fixtures and out-of-range dates.

- [ ] **Step 2: Verify RED**

Run `php artisan test tests/Feature/ExpeditionRequestWorkflowTest.php --filter=vendor_planning` and confirm routes are missing.

- [ ] **Step 3: Stop automatic date generation**

Change `PlanningService::syncRelease()` so it ensures at most a reusable null-date placeholder and does not generate Daily/Weekly dates. Preserve all existing dated rows.

- [ ] **Step 4: Implement Vendor planning controller**

Centralize ownership, `IJIN_LINTAS`, `RELEASED`, and expiry checks in a private authorization method. Validate dates with `after_or_equal:today` and `before_or_equal:{release_expired_at}`. Reuse a null placeholder for the first date, create another row for subsequent dates, and dispatch `PlanningUpdated` after each mutation. Reject update/delete when `is_locked` is true.

- [ ] **Step 5: Implement responsive Vendor page**

Render released permits as restrained panels with release download, validity, units, an add-date control, and agenda rows. Each row exposes edit/delete only while unlocked and presents a clear locked badge otherwise. Mark listed releases as read after authorizing ownership.

- [ ] **Step 6: Verify GREEN**

Run the targeted test and `npm.cmd run build`.

### Task 4: Reviewer Lock/Unlock and Purchasing Planning

**Files:**
- Create: `database/migrations/2026_09_04_000027_add_locking_to_planning_items.php`
- Modify: `tests/Feature/ExpeditionRequestWorkflowTest.php`
- Modify: `tests/Feature/OperationalWorkflowTest.php`
- Modify: `app/Models/PlanningItem.php`
- Modify: `app/Http/Controllers/PlanningController.php`
- Modify: `app/Events/PlanningUpdated.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Coordinator/Planning.vue`
- Modify: `resources/js/Components/AppShell.vue`

**Interfaces:**
- Produces `is_locked:boolean`, `locked_at:datetime`, and `locked_by:user-id|null` on each planning item.
- Produces reviewer endpoint `PATCH {review-base}/planning/{planningItem}/lock` accepting `{ locked: boolean }`.
- Extends `PlanningUpdated::broadcastWith()` with `is_locked`, `locked_at`, and `locked_by`.

- [ ] **Step 1: Write failing reviewer tests**

Assert Coordinator can lock one of two dates without affecting the other, Vendor can still edit the unlocked date but not the locked date, Coordinator can unlock it, and Purchasing can access/lock only Ijin Lintas planning within its site. Assert cross-site and non-Ijin access are forbidden.

- [ ] **Step 2: Verify RED**

Run the targeted planning tests and confirm the missing columns/routes cause the expected failure.

- [ ] **Step 3: Add lock schema and controller action**

Add lock columns with a null-on-delete foreign key. Cast lock fields in `PlanningItem`. Add `PlanningController::lock()` that authorizes reviewer scope, updates all three lock fields consistently, and dispatches the fresh planning event. Apply the same Purchasing type restriction used by submission review.

- [ ] **Step 4: Protect moves and expose Purchasing routes**

Reject date moves when an item is locked. Add Purchasing planning list/move/lock routes under the existing Purchasing permission middleware, return a dynamic endpoint/base prop, and restrict its query to Ijin Lintas.

- [ ] **Step 5: Update reviewer UI**

Use dynamic Coordinator/Purchasing URLs. Show lock ownership/status on cards, disable dragging for locked cards, and add accessible lock/unlock buttons that preserve scroll. Keep calendar overflow bounded on mobile.

- [ ] **Step 6: Verify GREEN and regression suite**

Run targeted planning tests, `vendor/bin/pint --dirty`, `php artisan test`, `npm.cmd run build`, `php artisan migrate --force`, `php artisan migrate:status`, and `git diff --check`. Every command must exit successfully.
