# Automatic expedition company resolution

User requests automatic company linking without manual linking controls. Confirmed usernames: vendor `basnuaalamsemesta`, expedition `bas`. Master company abbreviation BAS is the stable match; vendor remains identified through its saved company relation, not its username.

## Approach

Match administrator-managed expedition username to one active master vendor abbreviation or full name, case/separator insensitive; allow anchored expedition/ekspedisi prefixes/suffixes. Only accept exactly one company match. Do not use self-editable display name, fuzzy matches, first company, or arbitrary historical photo/report data as authorization evidence. Never replace an existing company relation. Only resolve active expedition accounts with expedition.report permission.

Resolve existing sessions on authenticated web requests; resolve same-site expedition accounts when coordinator opens Released follow-ups. Save a previously null company under a row lock after rechecking identity, role, site and permissions. Resolve new/edited accounts from their managed username before validation. No manual connector or manual assignment endpoint. Retain normal company selection for vendor account creation; expedition company comes from username or its existing association.

## Tasks

- [x] RED: automatic bas/BAS permit and P2H access, general vendor matching, preserved existing relations, display-name spoof isolation, ambiguous/inactive/unknown identities, same-site monitoring. Manual controls/endpoints removed.
- [x] GREEN: resolver + authenticated middleware; account validation inference; monitoring integration; remove linking UI/route/action and replace messages with automatic-flow guidance. Preserve photo tensi/arrival workflow.
- [x] Verify: complete PHP/JS suites, real Vue SSR tests, Vite build, lint and independent authorization review. No production database operations or push/deploy without a new request.

Unrecognized or ambiguous usernames remain without company access and show an identity diagnostic. This is not a prompt to manually link; master/username identity must be correct. Username is not self-editable in ProfileUpdateRequest, whereas display name is.

## Verification

- Initial targeted tests reproduced missing automatic bas/BAS resolution and required manual company field on account creation.
- Full final PHP suite: `php -d extension=gd vendor/phpunit/phpunit/phpunit --no-progress` passed **169 tests / 1645 assertions**.
- JavaScript suite: `node --test tests/js/*.test.mjs` passed **12 tests**, including actual Vue SSR rendering proving there is no manual linking form and that P2H/vendor review remains visible.
- Vite production build passed; existing login-background.webp runtime-resolution warning remains. Pint and git diff --check passed.
- Independent authorization review found explicitly posted company IDs could bypass inference in account saves. Added a failing regression for conflicting IDs on create/update, then enforced server-derived/preserved association for all expedition saves. Reviewer confirmed fixed with no further findings.
- Automatic recognition tested across BAS and CKB, login redirect, existing sessions, generic supported username affixes, unknown/inactive/ambiguous companies, existing-company preservation, no permission and display-name spoof prevention, site-scoped monitoring, and P2H submission.
- No production DB, migration, commit, merge, push or deployment performed in this task. Existing master/username identities must match uniquely; unresolved identities are not granted another company's data.
