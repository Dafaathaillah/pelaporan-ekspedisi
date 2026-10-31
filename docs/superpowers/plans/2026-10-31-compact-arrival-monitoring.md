# Compact arrival monitoring implementation

Spec: ../specs/2026-10-31-compact-arrival-monitoring-design.md

- [x] Write failing row-projection/search/pagination tests for 1000 pending/arrived units.
- [x] Extract shared pagination and implement compact monitoring unit rows plus selected-unit modal.
- [x] Preserve pending-only filter, planning progress, vendor reschedule scope/constraints, PDF and journey actions.
- [x] Verify JS/PHP/build and Chrome1000-unit pagination, checkbox/search, reschedule visibility/lock and modal scroll at 320-1920 px.

Changes remain in development. This is local rendering pagination over authorized loaded monitoring records; no production database scalability benchmark or server pagination change is claimed.

## Verification evidence
- 21 JavaScript tests passed: 1000-unit page cap, full-field search/pending filter, legacy reports, and retained permit P2H pagination tests.
- 21 related PHP tests passed (414 assertions): arrival planning, departure workflow and permit follow-up access/scope.
- Build succeeded; pre-existing login-background.webp runtime-resolution warning unchanged.
- Chrome with 1000 synthetic units passed 18 combinations (320/360/390/768/1440/1920 px, vendor/readonly/locked planning), checking 10-row cap, next page, pending/all toggles, search for unit1000, lazy journey details, reschedule control visibility/locked state, modal Escape and restored page scroll.
- Independent review identified that resetting the form whenever items refresh would hide Inertia validation errors. Reproduced as a failing browser test, then split filter-change reset from data-refresh pagination clamp. Verified refreshed props + simulated server error retain the open form, error message and reason in every vendor viewport.
- Inspected desktop1920 and mobile320 screenshots. Fixtures use real Vue component/AppShell/CSS with mock transport and do not write application data.
- The server monitoring endpoint remains unchanged; this bounds rendered rows and active timelines, not response size for arbitrarily large databases.
