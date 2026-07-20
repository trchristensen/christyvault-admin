# Delivery trip production rollout

This rollout is deliberately additive. The migrations create `trip_stops` and add nullable dispatch-confirmation fields to `trips`. They do not update or delete any existing order, trip, photo, signature, or activity record.

## Safety guarantees

- `orders.trip_id`, `orders.stop_number`, and `orders.driver_id` remain in place.
- Existing code can fall back to the legacy order relationship until backfill is complete.
- The backfill command is a dry run unless `--apply` is explicitly supplied.
- Re-running the command is safe and idempotent.
- Removed or merged route stops receive `removed_at`; their rows are retained.
- Merged trips use Laravel soft deletes. No trip is force-deleted.
- Bulk-creating one-stop trips for existing orders is a separate opt-in phase that requires an explicit starting date. New or deliberately edited scheduled deliveries create their one-stop trip automatically during normal application use.

## Recommended production sequence

1. Take a database snapshot or verified backup.
2. Deploy the code and run the additive migration:

   ```bash
   php artisan migrate --force
   ```

3. Inspect the production counts without changing data:

   ```bash
   php artisan delivery-trips:backfill
   ```

4. If—and only if—the dry run reports legacy trip orders missing an active `trip_stop`, backfill those existing trip relationships. If production reports zero, skip this step. The command does not change any order-to-trip assignment or restore archived trips:

   ```bash
   php artisan delivery-trips:backfill --apply
   ```

5. If step 4 was needed, run the dry run again. The count of legacy trip orders missing an active `trip_stop` should be zero:

   ```bash
   php artisan delivery-trips:backfill
   ```

6. Because production has not historically used trips, stop here. Do not bulk-convert historical standalone orders. Existing orders remain unchanged, while newly created or deliberately edited scheduled deliveries begin using one-stop trips automatically.

7. If you later choose to convert only upcoming scheduled deliveries, review a date-bounded dry run first:

   ```bash
   php artisan delivery-trips:backfill --include-single-stop-trips --from=YYYY-MM-DD
   ```

8. Only after reviewing that count, apply the same date-bounded conversion:

   ```bash
   php artisan delivery-trips:backfill --apply --include-single-stop-trips --from=YYYY-MM-DD
   ```

9. Run the same dry run one final time and retain its output with the deployment record.

## Application rollback

If the application code must be rolled back after the migration, leave the `trip_stops` table in place. Older code ignores it, and the retained legacy order fields continue to describe the same assignments. Do not run the migration `down()` method in production merely to roll back application code.

One-stop trips also remain compatible with the existing delivery calendar: they render as ordinary single-order deliveries until combined with another trip.

## Load-planning data rollout

The load-planning schema and confirmed equipment constraints are also additive. Product weights and loading-profile assignments are imported by normalized SKU so staging database IDs are never copied into production.

The rack-trailer configurations enforce a maximum **38,500 lb of product cargo**. Rack and piggyback-forklift weight are excluded from that value. The migration also creates the confirmed physical profiles for regular burial vaults, bottom-only Triunes, 3-high boxes, double garden crypts, pallets, and oversized single racks.

The trailer's two movable rack stops create exactly two supported physical layouts: 8 racks or 10 racks. A rack trailer must carry at least 8 physical racks even when fewer racks contain product. The normal forklift-onboard configuration therefore carries 8 racks, while the forklift-at-site configuration carries 10. Empty racks remain visible on the cheat sheet and are placed behind the loaded racks; they do not reduce the confirmed 38,500 lb product-cargo allowance because that allowance explicitly excludes rack weight.

The double-garden-crypt profile (`G3086-4` and `G3086-5`) uses a structured split placement on 2-high racks: each pair of racks carries two complete products on top and one additional product split across the paired bottom levels. Eight rack spots therefore carry the confirmed 12-product full load. This rule is deployed through migration `2026_07_17_030000_add_split_double_rack_placement.php`; no staging database IDs are copied to production.

Whole G3086-4/G3086-5 products are preferred on the top level, with compatible same-stop products placed underneath. The planner uses half-pairs only as a last-resort compaction when keeping every double whole would leave products off the truck. If both plans place the complete load, the unsplit plan wins even when it uses more rack spots.

Rack pairing also uses a preference hierarchy rather than a false hard incompatibility. Regular Wilbert burial vaults and Triunes are kept together when possible. Whole G3086-4/G3086-5 doubles prefer ordinary singles or liners underneath them, while Wilbert vaults avoid sharing those double racks. A Wilbert-under-double combination remains available only as a last resort when the preferred grouping would otherwise leave cargo off the truck.

Migration `2026_07_17_090000_allow_standard_three_high_boxes_in_two_high_racks.php` keeps 3-high racks preferred for the confirmed 22-unit G3086-6/V3086-1/L3086-4 profile while allowing those products to reuse 2-high openings. The pairing heuristic gives liners such as L3086-4 first preference beneath whole G4/G5 doubles, preserves Wilbert-to-Wilbert pairings, and uses other physically compatible combinations only when needed.

Migration `2026_07_18_000000_confirm_wilbert_urn_vault_pallet_racks.php` confirms that every Wilbert urn-vault SKU uses four units per pallet. Partial pallets consume a full pallet position unless a mixed-pallet compatibility group is later confirmed. The rack diagram places up to two pallets on each pallet-capable level: one such level in a 2-high rack and two in a 3-high rack. Pallets never use the non-pallet-capable top level.

Migration `2026_07_20_000000_add_flatbed_pallet_capacity_to_vehicle_configurations.php` adds a vehicle-specific fallback for strapped flatbed pallets. Eight-rack trailer configurations have four fallback pallet spots; 10-rack configurations remain at zero until their remaining deck space is physically confirmed. The planner always prefers protected pallet-capable rack positions. It evaluates flatbed placements only when they increase the amount of cargo placed, then selects the plan using the fewest flatbed spots. Flatbed cargo remains subject to the same 38,500 lb product-cargo limit, and partial pallets consume a complete flatbed spot.

Migration `2026_07_18_030000_add_boxed_urn_product_pallet_profiles.php` adds the lightweight boxed P-series rules. P300/P310-family products use 18 units per pallet; P400, P410, and P400WS use 9 units per pallet. Both profiles use the `boxed_urn_products` compatibility group, allowing compatible partial quantities to share a pallet. Rack capacity remains two pallets per pallet-capable level.

Regular Wilbert burial vaults, including `W3086-M`, use 2-high racks. G5 cover `2-3690G5` uses the dedicated `garden_crypt_cover_4_high` profile, with up to four covers bundled inside one level/position of a standard 2-high rack. The other level may hold another compatible product for the same stop. The initial configuration is created by migration `2026_07_17_040000_confirm_regular_vault_and_cover_racks.php` and corrected to position capacity by `2026_07_17_050000_correct_cover_rack_position_capacity.php`; the cover product itself is assigned by the version-controlled loading-profile importer.

Migration `2026_07_17_080000_confirm_wilbert_vault_rack_capacity.php` makes the burial-vault rack rule explicit: no more than two regular-size Wilbert burial vaults may occupy one rack. Both regular vaults and regular Triunes therefore use 2-high racks; Triunes retain the additional bottom-only rule. No per-rack pound rating is assumed—the confirmed 38,500 lb vehicle product-cargo limit remains authoritative until a rack weight rating is provided.

Ring liner `L2472-4` uses its dedicated `ring_liner_three_high` profile. The profile confirms a standard 3-high rack without inheriting the unrelated 22-unit full-load limit used by G3086-6/V3086-1/L3086-4. Migration `2026_07_17_060000_add_three_high_ring_liner_profile.php` creates the profile, and the version-controlled importer assigns the product by SKU.

Migration `2026_07_17_070000_add_compatible_rack_types_to_loading_profiles.php` adds explicit alternate rack compatibility. `L2472-4` prefers a new 3-high rack but may reuse open positions in either a standard 2-high or 3-high rack. The planner fills compatible openings for the same stop before opening another rack, reducing both wasted capacity and cargo weight at the piggyback end.

Rack diagrams use a forward-weight bias while preserving unload order. Later stops occupy the forward loaded positions and Stop 1 remains the rearmost loaded stop. Within each stop, racks fill bottom-to-top and are then ordered by their completed product weight, heaviest toward the tractor. Partial or unused rack positions remain toward the rear. The diagram displays each rack's calculated product weight so dispatch can review the ordering. This is a planning heuristic, not a legal axle-weight calculation; axle validation will require confirmed rack weights and vehicle geometry.

Stops remain on separate racks by default. If all rack spots are otherwise consumed, the planner may use one shared rack at the physical boundary between adjacent stops when every product is compatible with the resulting rack type. The earlier-delivery product occupies the accessible upper position and the later-stop product remains below. Mixed-stop racks are a last-resort capacity rule, not a general permission to scatter stops across racks.

Migration `2026_07_18_020000_add_fill_planning_to_order_product.php` adds Fill-load planning without changing existing order quantities. The planner loads every fixed quantity first, honors any manual Fill allocation next, and gives the remaining safe weight and rack capacity to automatic Fill lines in priority order. Lower `fill_priority` values win; blank priorities fall back to the earlier order request. The load summary shows the calculated quantity and whether it is automatic, manual, or locked. Confirming dispatch persists and locks those calculated quantities. Editing the trip, its stop order, vehicle, or an attached order clears automatic locks so the plan is recalculated; manual allocations remain reserved until changed by a user.

After deploying and running migrations, inspect catalog-weight matches without changing production:

```bash
php artisan products:import-catalog-weights
```

Review unmatched products, existing conflicts, and the catalog rows held for review. Then populate only blank weights:

```bash
php artisan products:import-catalog-weights --apply
```

Do not use `--overwrite` unless an existing production weight has been compared with the catalog and deliberately approved for replacement.

Next, inspect the version-controlled loading-profile assignments:

```bash
php artisan products:import-loading-profiles
```

If there are no unexpected conflicts, assign profiles to products that are still blank:

```bash
php artisan products:import-loading-profiles --apply
```

If production previously received an earlier version of these loading assignments, the dry run may preserve old profile conflicts. Review the listed SKUs individually before applying the confirmed refinements with:

```bash
php artisan products:import-loading-profiles --apply --overwrite
```

Finally, rerun both importers without `--apply`. Confirm that matched products report no pending changes, and retain the output with the deployment record.
