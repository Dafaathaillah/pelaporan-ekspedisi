# SCM Request, Escort Administration, and Session Design

## Scope and decisions

- Every SCM purchasing request has one requested vehicle type. Its vendor marks the request `READY` or `NOT_READY`, optionally with a reason for `NOT_READY`. The response is shown to SCM; the vendor can revise it while the request remains `OPEN`. Only `READY` requests can be selected for a new Ijin Lintas submission.
- All new Ijin Lintas submissions, including `NON_REGULAR`, must select an active `READY` request addressed to the vendor's company and site. Existing submissions and resubmissions retain their existing request association.
- The coordinator role has no Request Ekspedisi route, menu entry, or request permission. Purchasing creates and manages requests. Developer retains read and management access through an explicitly scoped route if needed.
- After escort status is `CLOSED`, the owning vendor uploads one PDF scan of the invoice and one PDF scan of the SPB for each report/unit. Both files are private. Until both exist the administration state is `BELUM_CLOSE`; after both exist it is `CLOSE`. Reupload replaces the corresponding scan. Only the new `finance` role may open or download an invoice. Vendor may view/download its SPB and see invoice upload status but cannot read the invoice bytes. Finance has a site-scoped administration page with report metadata and invoice/SPB download.
- The elapsed timer begins at `arrived_at`, updates every second while `escort_started_at` is null, and freezes at `escort_started_at`. It appears on coordinator, purchasing, vendor, and expedition arrival displays. The server supplies timestamps; browser display uses absolute timestamps and does not write timer state.
- A coordinator account has one active login session. A new successful coordinator login invalidates the previous session; middleware checks the stored session fingerprint on every web request. Logout clears only its own fingerprint. Other roles are unchanged.

## Data and authorization

Add readiness status, response note, responder and timestamp to `expedition_requests`; add private invoice/SPB paths and original filenames to `expedition_reports`; add a hashed active session identifier to `users`. Document upload and download endpoints verify site, ownership, role, escort state, and file existence. Invoice access is exclusive to role `finance`.

## Verification

Feature tests cover vendor request ownership and readiness updates, request requirement including Non-Reg, coordinator route denial, invoice privacy, upload prerequisites and administration state, timer stop calculation, and single coordinator session invalidation. Run complete PHP tests, frontend build, formatter, and local migrations.
