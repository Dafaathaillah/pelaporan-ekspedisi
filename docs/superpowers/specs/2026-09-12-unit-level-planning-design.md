# Unit-Level Planning Design

## Goal

Make every vehicle unit an independently draggable planning card, for both direct planning and submission-backed ijin lintas planning, without changing submission review, release, document, or notification workflows.

## Submission-backed planning

`planning_items` gains nullable `vendor_submission_unit_id`. For IJIN_LINTAS submissions with unit subjects, each planning item represents exactly one unit. The relationship is unique per submission and unit. Existing generic planning rows are migrated by assigning the first unit to the original row and cloning one row per remaining unit with the same planning date, position, lock metadata, notes, and evidence metadata.

`PlanningService` synchronizes one planning row per unit when an ijin lintas submission is registered or released. Legacy non-IJIN_LINTAS and submissions without unit rows retain their generic planning behavior.

Coordinator and purchasing calendar cards display the bound unit and move independently through the existing planning move endpoint. Lock remains per planning item. Arrival selection and progress are constrained to the bound unit; legacy generic rows continue using the existing all-unit behavior.

Vendor and expedition planning views consume the same per-unit relationship. Vendor date mutations target a unit planning row and no longer create arbitrary extra date rows for per-unit submissions.

## Direct planning

Each `direct_planning_unit` already owns its date. Add a dedicated PATCH move endpoint under coordinator and purchasing prefixes. It validates the same one-month range, authorizes the parent site, rejects locked parents, locks the parent row transactionally, and updates only the selected unit date.

Direct cards gain the same desktop drag and touch select-then-date interaction as existing cards. Moving one direct unit never changes sibling units.

## Compatibility and migration

The new foreign key is nullable to preserve legacy records. Migration is idempotent at the schema/data level and never modifies vendor submissions. Existing planning dates are preserved during splitting. Arrival reports remain linked to their existing planning row; when an original row is assigned to the first unit, any existing report-bound unit determines the assigned unit where possible.

## Tests

Tests cover direct-unit movement, lock and site authorization; per-unit planning creation and independent movement; legacy migration behavior; bound-unit arrival validation/progress; and all existing workflow suites.
