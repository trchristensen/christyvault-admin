<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $workOrder->number }} — Vendor Service Request</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; background: #eef1f5; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.35; }
        .toolbar { width: 8.5in; margin: 18px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { border: 0; border-radius: 5px; padding: 10px 18px; color: white; background: #c35a19; cursor: pointer; font-weight: bold; }
        .sheet { width: 8.5in; min-height: 11in; margin: 12px auto 30px; padding: .42in; background: white; box-shadow: 0 2px 14px rgba(0,0,0,.12); }
        .header { display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 4px solid #c35a19; padding-bottom: 14px; }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand img { width: 115px; max-height: 55px; object-fit: contain; }
        .brand-kicker { color: #c35a19; font-size: 11px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase; }
        h1 { margin: 2px 0 0; font-size: 23px; line-height: 1.1; }
        .wo-number { text-align: right; }
        .wo-number strong { display: block; font-size: 19px; }
        .wo-number span { color: #5f6878; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
        .card { border: 1px solid #cfd5de; border-radius: 5px; padding: 11px; break-inside: avoid; }
        .card.full { grid-column: 1 / -1; }
        .card h2 { margin: -11px -11px 9px; padding: 7px 10px; background: #f2f4f7; border-bottom: 1px solid #cfd5de; font-size: 12px; letter-spacing: .6px; text-transform: uppercase; }
        .details { display: grid; grid-template-columns: 115px 1fr; gap: 4px 9px; }
        .label { color: #5f6878; font-weight: bold; }
        .scope-title { font-size: 16px; font-weight: bold; margin-bottom: 6px; }
        .text { white-space: pre-wrap; }
        .safety { margin-top: 9px; padding: 8px; border: 2px solid #b42318; color: #8a1c13; background: #fff0ee; font-weight: bold; }
        ol { margin: 0; padding-left: 23px; }
        li { padding: 3px 0 5px; border-bottom: 1px dotted #ccd1d9; }
        .checkbox { display: inline-block; width: 12px; height: 12px; margin-right: 7px; border: 1px solid #566071; vertical-align: -2px; }
        .authorization { padding: 9px; background: #fff7e8; border: 1px solid #e3b45d; }
        .write-row { display: grid; grid-template-columns: 155px 1fr; gap: 8px; min-height: 27px; align-items: end; }
        .write-line { min-height: 23px; border-bottom: 1px solid #4c5667; }
        .notes-box { min-height: 80px; margin-top: 4px; border: 1px solid #7d8694; }
        .footer { display: flex; justify-content: space-between; margin-top: 15px; padding-top: 8px; border-top: 1px solid #cfd5de; color: #697283; font-size: 10px; }
        @page { size: letter; margin: .25in; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: .15in; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print service request</button>
</div>

<main class="sheet">
    <header class="header">
        <div class="brand">
            <img src="{{ asset('images/logo.svg') }}" alt="Christy Vault">
            <div>
                <div class="brand-kicker">Outside service request</div>
                <h1>Maintenance Work Order</h1>
            </div>
        </div>
        <div class="wo-number">
            <strong>{{ $workOrder->number }}</strong>
            <span>Issued {{ $workOrder->created_at?->format('M j, Y') }}</span>
        </div>
    </header>

    <div class="grid">
        <section class="card">
            <h2>Service provider</h2>
            <div class="details">
                <div class="label">Company</div><div>{{ $workOrder->service_provider ?: 'To be assigned' }}</div>
                <div class="label">Contact</div><div>{{ $workOrder->service_contact_name ?: '—' }}</div>
                <div class="label">Phone</div><div>{{ $workOrder->service_phone ?: '—' }}</div>
                <div class="label">Vendor reference</div><div>{{ $workOrder->vendor_reference ?: '—' }}</div>
                <div class="label">Purchase order</div><div>{{ $workOrder->purchase_order_number ?: '—' }}</div>
                <div class="label">Work type</div><div>{{ \App\Support\MaintenanceOptions::workOrderTypes()[$workOrder->type] ?? $workOrder->type }}</div>
            </div>
        </section>

        <section class="card">
            <h2>Schedule and location</h2>
            <div class="details">
                <div class="label">Scheduled</div><div>{{ $workOrder->scheduled_at?->format('M j, Y g:i A') ?: 'To be scheduled' }}</div>
                <div class="label">Requested by</div><div>{{ $workOrder->due_at?->format('M j, Y') ?: 'As scheduled' }}</div>
                <div class="label">Service location</div><div>{{ $workOrder->asset?->location?->name ?: 'Confirm with coordinator' }}</div>
                <div class="label">Address</div><div>{{ $workOrder->asset?->location?->full_address ?: '—' }}</div>
                <div class="label">Coordinator</div><div>{{ $workOrder->assignedTo?->name ?: 'Christy Vault maintenance' }}</div>
            </div>
        </section>

        <section class="card full">
            <h2>Equipment</h2>
            <div class="details">
                <div class="label">Asset number</div><div><strong>{{ $workOrder->asset?->asset_tag ?: '—' }}</strong></div>
                <div class="label">Equipment</div><div>{{ $workOrder->asset?->name ?: 'Unspecified equipment' }}</div>
                <div class="label">Year / make / model</div><div>{{ collect([$workOrder->asset?->year, $workOrder->asset?->manufacturer, $workOrder->asset?->model])->filter()->join(' ') ?: '—' }}</div>
                <div class="label">Serial / VIN</div><div>{{ $workOrder->asset?->serial_number ?: '—' }}</div>
                <div class="label">License plate</div><div>{{ $workOrder->asset?->license_plate ?: '—' }}</div>
                <div class="label">Current meter</div><div>{{ $workOrder->asset?->current_meter !== null ? number_format((float) $workOrder->asset->current_meter, 1).' '.($workOrder->asset->meter_type ?? '') : 'Record before service' }}</div>
            </div>
        </section>

        <section class="card full">
            <h2>Requested service</h2>
            <div class="scope-title">{{ $workOrder->title }}</div>
            <div class="text">{{ $workOrder->description ?: 'See the checklist and contact the coordinator with questions.' }}</div>
            @if ($workOrder->safety_related)
                <div class="safety">Safety-related work: contact Christy Vault before changing the requested scope.</div>
            @endif
        </section>

        @if (filled($workOrder->checklist))
            <section class="card full">
                <h2>Requested checklist</h2>
                <ol>
                    @foreach ($workOrder->checklist as $item)
                        <li><span class="checkbox"></span>{{ is_array($item) ? ($item['task'] ?? $item['label'] ?? '') : $item }}</li>
                    @endforeach
                </ol>
            </section>
        @endif

        <section class="card full authorization">
            <strong>Authorization:</strong>
            @if ($workOrder->authorization_limit !== null)
                Do not exceed ${{ number_format((float) $workOrder->authorization_limit, 2) }} without approval from Christy Vault.
            @else
                Obtain approval from Christy Vault before performing work outside the requested scope.
            @endif
        </section>

        <section class="card full">
            <h2>Vendor completion record</h2>
            <div class="write-row"><div class="label">Technician name</div><div class="write-line"></div></div>
            <div class="write-row"><div class="label">Vendor ticket number</div><div class="write-line"></div></div>
            <div class="write-row"><div class="label">Service meter reading</div><div class="write-line"></div></div>
            <div class="write-row"><div class="label">Completed date/time</div><div class="write-line"></div></div>
            <div class="label" style="margin-top: 10px;">Findings, recommendations, and additional work requested</div>
            <div class="notes-box"></div>
            <div class="write-row" style="margin-top: 10px;"><div class="label">Technician signature</div><div class="write-line"></div></div>
        </section>
    </div>

    <footer class="footer">
        <span>Christy Vault maintenance · {{ $workOrder->number }}</span>
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
    </footer>
</main>

@if ($autoPrint)
    <script>window.setTimeout(() => window.print(), 150);</script>
@endif
</body>
</html>
