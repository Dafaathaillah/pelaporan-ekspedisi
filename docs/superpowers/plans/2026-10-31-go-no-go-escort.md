# GO/NO GO Escort Implementation Plan

> For agentic workers: use superpowers:executing-plans inline with meaningful regression tests and final independent review.

Goal: require driver-submitted checklist and Coordinator approval before any new escort starts.
Architecture: immutable group form with unit snapshots and revision group key; current reservation FK on reports. Report locks precede form locks for submit/review/start/cancel. Existing escort APIs reuse the approved form photo and retain readiness checks.
Tech Stack: Laravel, Inertia/Vue, Dompdf, GD, SQLite tests, local Chrome fixture.
Spec: ../specs/2026-10-31-go-no-go-escort-design.md (approved).

## Constraints and review focus
- GO = driver LAIK + review APPROVED; approved TIDAK_LAIK remains NO GO.
- Current reservations enforce same owner/exact report set; race and stale-review requests cannot start different units.
- Driver/manual fields are validated; automatic fields and snapshot identities are server-derived. Max 100 units/form as payload bound, pagination for browsing.
- No production deployment/migration. Legacy PROCESS/CLOSED reports can still close normally.
- Database/photo rollback must leave no orphan files; already-owned approved P5M files must never be deleted by failed start requests.

## Task 1: submission/review/reservations
Files: GoNoGoChecklist.php, EscortGoNoGo.php, additive migration, EscortGoNoGoService.php, EscortGoNoGoController.php, routes/web.php, tests/Feature/GoNoGoWorkflowTest.php.
Interfaces: POST /driver/go-no-go; PATCH /coordinator/go-no-go/{goNoGo}/review; POST /driver/go-no-go/{goNoGo}/cancel; GET /go-no-go/{goNoGo}/download and /photo. Coordinator GET /coordinator/go-no-go with search/status pagination.
- [x] Write tests for four-unit snapshot, required/manual checklist, scope/role, pending/approved-unfit gates, revision/cancel/stale review.
- [x] Observe RED missing endpoints, then implement model/schema/service/controller and GREEN.

## Task 2: start gate
Files: ExpeditionReport.php, DriverEscortController.php, related driver/approval regression fixtures.
Interfaces: report.go_no_go_id reservation; service approvedForStart(user, reports, expectedId) returns approved exact-set form. Eligibility reused for submit and claim. Form.started_at set in same transaction as report PROCESS fields.
- [x] Assert single/batch direct start denied without GO; exact owner/set required; NO GO/photo rollback/readiness checks.
- [x] Use approved form P5M for start and update old happy-path fixtures through explicit approved-form setup.

## Task 3: UI
Files: GoNoGoForm.vue, GoNoGoRecord.vue, Coordinator/GoNoGo.vue, Driver/Escorts.vue, AppShell.vue, HandleInertiaRequests.php.
- [x] Driver selection opens identity/checklist/P5M form; show paginated own forms, status/PDF/cancel/revise/start.
- [x] Coordinator menu highlighted lime on desktop/mobile, pending count, paginated review page with details and decisions.
- [x] Preserve form state/error visibility and no DOM-heavy closed details.

## Task 4: PDF and final verification
Files: GoNoGoPdf.php, pdf/go-no-go.blade.php, PDF tests, tests/browser/monitoring-smoke.mjs.
- [x] Match reference blue section/table A/B/C, 11 checklist items, comma-separated plate/driver snapshots, submission/review dates and conditional seal; NO GO prominently visible.
- [x] Run focused and full PHP tests, JS tests, build, formatter and browser mobile/desktop role/workflow checks.
- [x] Independent review, fix important findings, record evidence and remaining limitations.

## Ledger
User approved full design and asked immediate implementation; execute inline in existing development workspace. No additional merge/push/deploy is authorized in this turn.


## Verification evidence (2026-10-31)
- Backend regression suite: final full run passed 187 tests / 1922 assertions, including the additional PDF test.
- Standard four-unit PDF test passed (10 assertions): one A4 page, comma-separated plates, separate submission/review dates, conditional seal, approved unfit remains NO GO.
- JavaScript: 23 tests passed. Production Vite build passed; existing login-background asset resolution warning remains.
- Chrome fixture checks: GO form and expanded Coordinator checklist at widths 320, 390, 768, 1440; no Vue errors or document horizontal overflow. Actual local database-backed browser flow was not exercised.
- Independent read-only review completed, no important findings. Single-claim optional form ID now validates UUID before invoking the gate.
- Pint and git diff --check passed.
- Local MySQL was subsequently started; missing local database pelaporan_ekpedisi was created and all migrations, including GO/NO GO, ran successfully. No production database migration performed.
- Working branch remains development. No commit, merge, or push requested for this implementation turn.

User subsequently authorized committing, merging and pushing to main/production, then returning to development. Integration verification reruns PHP/JS tests and production build.
