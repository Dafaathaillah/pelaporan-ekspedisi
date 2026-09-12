# Direct Ijin Lintas Planning Design

## Purpose

Allow coordinators, purchasing administrators, and developers to create ijin lintas planning without an existing vendor submission. The current submission-driven planning workflow must retain its existing database relationships, behavior, routes, notifications, and user interface interactions.

## Scope

The feature adds a separate direct-planning workflow to the existing planning calendar. A direct planning selects one active vendor from the vendor master and contains one or more vehicle units. Each unit can have its own vehicle type, plate number, origin, destination, and planning date.

The feature includes create, edit, lock or unlock, and soft-delete operations. It does not create a vendor submission, expedition request, permit document, approval status, or vendor notification.

## Architecture

Direct planning data will be stored separately from `vendor_submissions` and `planning_items`:

- `direct_plannings` is the aggregate header. It stores the selected vendor, site, optional notes, lock state, audit users and timestamps, and soft-deletion metadata.
- `direct_planning_units` stores the vehicle rows belonging to a direct planning. It stores vehicle type, plate number, origin, destination, planned date, and display position.

The existing `planning_items.vendor_submission_id` relationship remains required and unchanged. Existing submission registration, release handling, planning movement, evidence, and notifications remain unchanged.

`PlanningController::index` will continue loading existing planning items and will additionally load direct planning units for the same site and date window. The server will serialize direct units into a distinct, explicit view model. The Vue calendar will render both sources but will route actions according to the item's source. No direct item will be passed to an existing planning-item mutation endpoint.

## Data Model

### `direct_plannings`

- UUID primary key `id`.
- Foreign key `vendor_company_id` to `vendor_companies`, restricted on delete.
- String `site`, indexed with active date lookup as appropriate.
- Nullable text `notes`.
- Boolean `is_locked`, default `false`.
- Nullable timestamp `locked_at`.
- Nullable foreign key `locked_by` to `users`, null on user deletion.
- Foreign key `created_by` to `users`, restricted on delete.
- Nullable foreign key `updated_by` to `users`, null on user deletion.
- Standard timestamps and soft deletes.

### `direct_planning_units`

- UUID primary key `id`.
- Foreign UUID `direct_planning_id`, cascading when the parent is permanently deleted.
- String `vehicle_plate`.
- String `vehicle_type`.
- String `route_from`.
- String `route_to`.
- Date `planned_date`, indexed.
- Unsigned small integer `position`, default `0`.
- Standard timestamps.

Plate numbers must be unique within one direct planning after trimming and case normalization. A vendor may have the same plate in another planning.

## Authorization

The feature is available to active users with roles `coordinator`, `purchasing_admin`, and `developer`.

All direct-planning reads and mutations are site-scoped. The selected vendor must be active in the global vendor master; vendor master records do not carry a site field. A user cannot view or mutate a direct planning from another site. Cross-site access returns HTTP 403. Unsupported roles cannot access direct-planning routes.

All three permitted roles can create direct planning and can edit, lock, unlock, or soft-delete any direct planning within their site. Locked planning rejects edit and delete operations until it is unlocked.

## Routes and Operations

Routes will be added alongside each permitted role's existing planning prefix while using the same controller behavior:

- `POST /coordinator/direct-plannings`
- `PUT /coordinator/direct-plannings/{directPlanning}`
- `PATCH /coordinator/direct-plannings/{directPlanning}/lock`
- `DELETE /coordinator/direct-plannings/{directPlanning}`
- Equivalent endpoints under `/purchasing` for purchasing administrators.

Developer access continues through coordinator routes because the existing role middleware explicitly permits developers. Existing planning routes are not renamed or repurposed.

Create and update operations write the header and complete unit collection in a single database transaction. An update synchronizes the submitted unit collection: retained unit IDs are updated, new rows are created, and omitted rows are removed. A failed validation or database operation leaves the previous aggregate intact.

## Validation

Header validation:

- `vendor_company_id`: required integer; must identify an active record in the global vendor master.
- `notes`: optional string, maximum 2,000 characters.
- `units`: required array with at least one member.

Unit validation:

- `id`: optional UUID that must belong to the aggregate being edited.
- `vehicle_plate`: required trimmed string, maximum 30 characters.
- `vehicle_type`: required and limited to the vehicle types already supported by expedition reporting.
- `route_from`: required trimmed string, maximum 200 characters.
- `route_to`: required trimmed string, maximum 200 characters and different from `route_from` after case-insensitive normalization.
- `planned_date`: required date from today through one month from today, inclusive.

Duplicate normalized plate numbers within one submitted aggregate are rejected with a field-specific validation message. Server-side validation is authoritative even when equivalent client-side guidance is present.

## User Experience

The current planning calendar, filters, drag-and-drop behavior, submission cards, evidence actions, and lock behavior remain in place.

A `Buat Planning Langsung` primary action opens a focused modal:

- The header contains a searchable active-vendor selector and optional notes.
- Vehicle units appear as repeatable blocks containing plate number, vehicle type, origin, destination, and date.
- Users can add a unit or remove any row while retaining at least one row.
- Validation appears beside the relevant header or unit field.
- Save has loading and disabled states and preserves entered values on validation failure.

Each unit is rendered as its own calendar card on its planned date. Cards share the parent direct-planning identity and carry a visible `Planning Langsung` label. They show vendor, plate, vehicle type, and route. Edit opens the entire parent planning so every unit can be managed together. Delete confirms that the complete group and all its displayed unit cards will be removed. Lock and unlock apply to the complete group.

On mobile, unit fields reflow into vertical vehicle blocks and modal actions remain visible without page-level horizontal overflow. The existing calendar retains its bounded horizontal scrolling behavior.

## Errors and Feedback

Validation failures return Inertia form errors and do not close the modal. Locked mutations return HTTP 422 with a clear Indonesian message. Missing records return 404, and site violations return 403. Successful create, edit, lock, unlock, and delete operations return specific Indonesian flash messages.

Database writes use transactions. Unexpected errors are logged through Laravel's existing exception handling and do not partially synchronize units.

## Compatibility Contract

The following existing behavior must not change:

- Vendor submissions remain the only source for submission-driven planning items.
- `PlanningService` continues registering and synchronizing existing planning exactly as it does now.
- Existing planning movement, locking, evidence upload, arrival reporting, release, review, and notification endpoints keep their payloads and behavior.
- Existing planning cards retain their current actions and visual treatment.
- Direct planning does not affect submission/request counts, permit status, review queues, or vendor notifications.

## Testing

Feature tests will prove:

- Each permitted role can create a direct planning with multiple units using different dates and routes.
- Vendor selection is limited to active records in the global vendor master.
- Unsupported roles and cross-site access are rejected.
- Empty units, invalid dates, identical routes, and duplicate normalized plates are rejected.
- Editing atomically updates, adds, and removes unit rows.
- Locking blocks edit and deletion; unlocking restores those operations.
- Deletion soft-deletes the parent and removes it from the calendar response.
- Direct units and existing submission-driven items are both returned for the calendar with unambiguous source identifiers.
- Existing expedition request, vendor submission, planning, arrival, and developer-access test suites continue to pass unchanged.

Frontend verification will cover desktop and mobile modal layout, add/remove unit interactions, loading, validation, empty, success, locked, and long-content states. The production asset build must complete without warnings or errors.
