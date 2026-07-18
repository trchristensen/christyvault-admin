# Boom truck and local-run automation notes

This is a future extension of the delivery-trip and load-summary work. It is intentionally deferred until the rack-trailer planner has been verified with real trips.

## Goal

Automatically turn scheduled Colma and South San Francisco orders into suggested boom-truck runs without requiring the office manager to combine every order manually.

Customers should request a local or flex delivery. They should not need to understand trucks, trips, load positions, or Fill priorities. The office should review and approve the resulting runs before they become dispatch plans.

## Suggested workflow

1. Keep each scheduled local order in its automatically created one-stop trip.
2. Add a **Build local runs** action for a selected delivery date.
3. Find eligible Colma and South San Francisco orders and their one-stop trips.
4. Pack fixed quantities into one or more proposed boom-truck runs.
5. Allocate Fill products only after fixed products, using the existing Fill priority rules.
6. Show each proposed run with its stops, products, capacity, weight, and exceptions.
7. Let the office manager approve, adjust, or reject the suggestions.
8. On approval, merge the existing one-stop trips using the current history-preserving trip workflow.

The system should suggest runs rather than silently commit them. Delivery times, urgent orders, special instructions, driver knowledge, and product-handling exceptions may require a different grouping.

## Initial boom-truck capacity model

The working operational estimate is:

- Seven regular-size burial vault positions with no racks.
- Up to fourteen confirmed stackable boxes when two boxes can use one position.
- Mixed loads consume the corresponding fraction or whole position.
- Product weight remains a hard constraint once a confirmed boom-truck product-cargo limit is available.

These numbers need operational confirmation before automatic placement is considered authoritative. Oversized products, Triunes, pallets, covers, mixed-product stacking, and other special products also need explicit boom-truck rules.

## Planner architecture

Keep vehicle placement separate behind vehicle-specific planners:

- `RackTrailerPlanner` for rack spots and rack levels.
- `BoomTruckPlanner` for open-bed positions and confirmed stacking.

The future local-run builder should ask the selected vehicle planner whether a proposed collection of orders fits. That keeps trip grouping independent from the physical diagram and allows another vehicle type later.

## Customer portal behavior

A future customer portal should expose choices such as **Local delivery**, **Flex delivery**, and **Fill remaining capacity**. It should not expose raw Fill priority numbers.

When a customer joins a flex/local pool, the portal should show an estimated quantity or explain that no remaining capacity is currently available. Existing reservations normally retain priority. The office can override priorities before dispatch, and dispatch confirmation locks the final quantities.

## Recommended implementation order

1. Verify rack-trailer plans against real loads.
2. Confirm the boom truck's seven-position, stacking, and weight rules.
3. Implement the basic boom-truck capacity planner and diagram.
4. Add date-based local-run suggestions.
5. Add one-click approval that merges one-stop trips.
6. Reuse the same service later for customer flex-delivery availability.
