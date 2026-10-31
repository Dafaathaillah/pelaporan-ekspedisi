# Monitoring cards and mobile scrolling

User requests tidier dashboard cards, lazy rendering for suitable tables/details, and safe mobile scrolling. Continue the accepted compact monitoring design.

Put metrics before the P2H review area, add consistent section spacing, prevent filter cards from stretching to chart height, and present a compact chart empty state. Keep all filters, review actions, reports, permissions, and links intact. On mobile let content shrink using min-width: 0, wrap long labels/actions, and confine wide tables to horizontal containers without fixed-height vertical scrolling.

Apply the existing lazy disclosure only to optional content: arrived-unit history and vendor P2H revision history. Preserve already-lazy P2H inspection tables and modal evidence; do not delay primary status rows or editable form data. Verify native disclosure mounting/unmounting and mobile overflow with browser tooling where available, plus existing build/tests. Do not claim all devices bug-free from finite tests.
