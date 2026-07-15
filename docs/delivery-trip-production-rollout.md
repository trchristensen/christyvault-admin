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
