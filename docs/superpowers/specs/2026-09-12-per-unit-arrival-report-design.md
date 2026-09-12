# Per-Unit Arrival Report Design

## Goal

Simplify the expedition/vendor arrival page so selecting one Released permit immediately exposes one independently submittable arrival card for every unit on that permit.

## Behavior

- Keep only the `Izin / Vendor` selector; remove the planning-date and unit selectors.
- Display every permit unit as `Unit 1`, `Unit 2`, and so on, including its driver, phone, plate, vehicle type, vendor, and destination.
- Resolve the unit's planning card on the server. The browser submits only the unit identifier and arrival evidence; it cannot choose or forge a planning identifier or identity fields.
- Each unit has a checkbox and independent blood-pressure fields, arrival photo, pressure photo, and (for expedition accounts) P2H photo.
- One batch action submits all checked units independently. Units not checked remain `Menunggu Driver`, allowing partial arrival reporting without hiding the rest of the permit.
- Save one report per checked unit. A successfully submitted card becomes read-only and shows `Kedatangan Sudah Dikirim` with its arrival time; failed cards retain their validation errors and other cards remain editable.
- A unit without complete identity data or without a bound scheduled planning card remains visible but cannot be submitted, with a clear explanation.
- Preserve existing report history, photo resizing, GPS requirements, authorization, status, and monitoring flows.

## Data and Security

`vendor_submission_unit_id` is the client identity key. The request finds the accessible Released and unexpired permit containing that unit, then finds the planning item bound to the unit. It merges the authoritative planning ID and identity data before validation. A database transaction locks the planning row and rejects duplicate reports for the unit.

## UI Structure

The page owns permit selection, checked unit IDs, batch orchestration, and report history. A focused `ExpeditionArrivalUnitCard` component owns one unit's form, camera previews, GPS acquisition, validation errors, and submit lifecycle. This isolation permits partial success and prevents one unit's input or errors from affecting another unit. Coordinator, purchasing admin, and developer monitoring uses the same per-unit progress contract and labels every planning card as `Sudah Datang` or `Menunggu Driver`.
