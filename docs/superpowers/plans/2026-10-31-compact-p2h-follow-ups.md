# Compact P2H follow-ups implementation plan

Spec: ../specs/2026-10-31-compact-p2h-follow-ups-design.md

- [x] Write and observe failing tests for 1000-unit pagination and combined status/search filtering.
- [x] Implement bounded row projection/search/pagination helper and responsive component.
- [x] Move timeline and detailed guidance to a lazy modal; preserve PDF and permit links.
- [x] Verify SSR ten-row cap, browser paging/search/status/modal/mobile behavior with 1000 synthetic records, frontend build and relevant PHP regression tests.

Important scope: follow-ups contain only permits on the current server-paginated submissions page; local search does not claim to search the entire database. Existing primary table filters/pagination remain available for global traversal.

## Verification and decisions
- Existing main Filter Pengajuan moved above the follow-up panel so global search/filter actions are reachable before the ten-row summary.
- 18 JS tests passed, including 1000-unit projection/page/filter tests and SSR proof that only ten rows and no journey timers mount initially.
- 14 PHP follow-up and departure regression tests passed (282 assertions); service/API/permissions unchanged.
- Chrome fixture with 1000 synthetic units passed at widths 320, 360, 390, 768, 1440, 1920: ten-row DOM cap, next-page unit 11, exact search for unit 1000, combined status filtering, detail modal journey, Escape close, background lock/restored page scroll, no viewport overflow or Vue errors.
- Desktop1920 and mobile320 screenshots inspected. Browser fixture uses actual screen component/AppShell/CSS and mock Inertia; it does not benchmark production database scale.
- Independent read-only review found no actionable important regressions; warning/guidance/PDF/permit actions and modal timer teardown retained.
- No merge/push/deploy requested in this task.
