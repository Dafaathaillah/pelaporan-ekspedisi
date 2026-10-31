# Monitoring cards and mobile implementation plan

Spec: ../specs/2026-10-31-monitoring-mobile-design.md

- [x] Tidy Coordinator metric/review/chart/filter spacing and empty chart state.
- [x] Contain responsive widths in AppShell main, P2H review cards, table wrappers, and lazy disclosures.
- [x] Reuse LazyJourneyDetails for arrived-unit history and vendor P2H revision history.
- [x] Run SSR disclosure regression tests, frontend build, relevant PHP tests, and browser viewport/interaction checks.

Work in development; no deployment or push requested.

## Evidence
- Build: npm.cmd run build passed; existing login-background.webp warning remains.
- JavaScript: 14 tests passed, including closed-detail non-mount and opened-detail content assertions.
- PHP: 175 tests, 1760 assertions passed on SQLite memory with GD enabled for the command.
- Chrome headless browser fixture: 24 combinations passed across widths 320, 360, 390, 768, 1440 and 1920 and dashboard/populated, dashboard/empty, arrival monitoring, vendor detail views.
- Browser checks include root/main width bounds, expanded P2H checklist containment, horizontal table movement without page drift, mobile vertical and horizontal touch gestures, native lazy content opening/closing, and no Vue runtime errors. Uses synthetic records and mock Inertia transport, with real screen components, AppShell and CSS; no database writes.
- Inspected screenshots at 360 and 1920 widths. Tests represent Chrome emulation, not every physical device/browser or production data shape.
- Independent read-only review found no actionable important regressions in the six UI diffs.
- Remaining primary tables already paginate; simple summary rows were kept eager rather than adding disclosure overhead. P2H checklist table already uses v-if and remains lazy.
