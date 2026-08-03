# Maintenance module

The maintenance module is a bounded CMMS inside `christyvault-admin`. It has its own Filament panel at `/maintenance`, while sharing users, locations, inventory items, suppliers, notifications, and authentication with the main application.

## Roles

- `maintenance-manager`: manages assets, triages requests, creates PM plans, assigns work, and verifies completion.
- `maintenance-technician`: views assets and performs assigned work, including timers, parts, findings, and completion.
- `admin` and `super-admin`: have access to the maintenance panel for administration.
- Other employees use the asset QR portal and do not need maintenance-panel access.

Create the maintenance roles with:

```bash
php artisan db:seed --class=Database\\Seeders\\MaintenanceRoleSeeder
```

Assign those roles through the existing Filament roles and permissions interface.

## Workflow

1. Create physical assets and print the QR label from the asset record.
2. Operators scan `/asset/{qr_token}` to report issues, attach photos, and optionally enter a meter reading.
3. A manager triages the request and converts it to a work order.
4. The technician starts the work order, records labor and parts, completes the checklist, and submits it for verification.
5. A manager verifies the repair and returns the asset to operational status.
6. Calendar and meter-based PM plans create work orders automatically.

## Fleet PM plans

Fleet PM plans dynamically match equipment by home plant, manufacturer, category, and meter type. Matching equipment is added automatically, while individual low-use units can be excluded from the plan's **Fleet assets and service baselines** section.

When any included asset reaches its next-due meter, the plan creates one grouped service run and a separate work order for every included asset. This preserves each asset's service history while allowing an outside provider to service the fleet together. No second run is generated while the current grouped service remains open. When each work order is verified, that asset's current meter becomes its new service baseline.

Create the initial Colma Hyster Service B plan after importing the asset register with:

```bash
php artisan db:seed --class='Database\Seeders\ColmaHysterServiceBPlanSeeder' --force
```

The setup includes all Colma Hyster forklifts automatically. Units 8, 9, 12, and 16 receive their readable July 22, 2026 service baselines from the handwritten record. Units 11 and 18 remain included but require a confirmed baseline in the plan before they can trigger service.

The hourly scheduler runs `maintenance:generate-work-orders`. It may also be run manually:

```bash
php artisan maintenance:generate-work-orders
```

## Files

Uploaded maintenance files default to the `public` disk. Set `MAINTENANCE_FILESYSTEM_DISK` to another configured filesystem disk when needed.

## Data boundaries

Maintenance tables use the `maintenance_` prefix. The module does not treat `VehicleConfiguration` as a physical asset; a configuration describes load-planning capacity, while a maintenance asset is a specific truck, trailer, forklift, crane, or plant component.

## Initial rollout

Start with a small asset set: one forklift, one truck, one crane, and the batch plant with its major child components. Confirm the request, triage, work, and verification flow with real users before bulk-importing every asset.
