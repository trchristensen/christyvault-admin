<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $asset->asset_tag }} · Maintenance</title>
    <style>
        :root { color-scheme: light; --ink:#172033; --muted:#64748b; --line:#dbe2ea; --brand:#c25516; --paper:#fff; --bg:#f4f6f8; --danger:#b42318; --success:#157f3b; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.45 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        main { width:min(720px,100%); margin:auto; padding:20px 14px 56px; }
        .brand { font-size:.78rem; font-weight:800; letter-spacing:.13em; text-transform:uppercase; color:var(--brand); margin-bottom:10px; }
        .card { background:var(--paper); border:1px solid var(--line); border-radius:18px; box-shadow:0 8px 30px rgba(23,32,51,.06); padding:22px; margin-bottom:16px; }
        h1 { font-size:1.75rem; line-height:1.15; margin:0 0 6px; }
        h2 { font-size:1.15rem; margin:0 0 18px; }
        .tag { font-weight:800; color:var(--muted); }
        .status { display:inline-flex; margin-top:14px; border-radius:999px; padding:6px 10px; font-size:.8rem; font-weight:750; background:#e7f6ec; color:var(--success); }
        .status.out_of_service { background:#feeceb; color:var(--danger); }
        .status.restricted,.status.scheduled_downtime { background:#fff4db; color:#875700; }
        .meta { color:var(--muted); font-size:.92rem; margin-top:8px; }
        label { display:block; font-weight:700; font-size:.9rem; margin:15px 0 6px; }
        input,select,textarea { width:100%; padding:12px 13px; border:1px solid #c7d0da; border-radius:10px; background:#fff; color:var(--ink); font:inherit; }
        input:focus,select:focus,textarea:focus { outline:3px solid rgba(194,85,22,.18); border-color:var(--brand); }
        textarea { min-height:120px; resize:vertical; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:0 14px; }
        .check { display:flex; gap:10px; align-items:flex-start; padding:14px 0 2px; }
        .check input { width:auto; margin-top:4px; }
        button { width:100%; border:0; border-radius:11px; margin-top:20px; padding:14px 18px; background:var(--brand); color:white; font:inherit; font-weight:800; cursor:pointer; }
        .alert { border-radius:12px; padding:13px 15px; margin-bottom:16px; background:#e7f6ec; color:var(--success); font-weight:700; }
        .errors { background:#feeceb; color:var(--danger); }
        .errors ul { margin:5px 0; padding-left:20px; }
        .help { color:var(--muted); font-size:.82rem; margin-top:5px; }
        footer { color:var(--muted); text-align:center; font-size:.78rem; }
        @media (max-width:560px) { .grid { grid-template-columns:1fr; } .card { padding:18px; border-radius:14px; } }
    </style>
</head>
<body>
<main>
    <div class="brand">Christy Vault Maintenance</div>
    @if (session('success')) <div class="alert">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert errors"><strong>Please correct the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="card">
        <div class="tag">{{ $asset->asset_tag }}</div>
        <h1>{{ $asset->name }}</h1>
        <div class="meta">
            {{ \App\Support\MaintenanceOptions::assetCategories()[$asset->category] ?? ucfirst($asset->category) }}
            @if ($asset->location) · {{ $asset->location->name }} @endif
            @if ($asset->manufacturer || $asset->model) · {{ trim($asset->manufacturer.' '.$asset->model) }} @endif
        </div>
        <span class="status {{ $asset->status }}">{{ \App\Support\MaintenanceOptions::assetStatuses()[$asset->status] ?? ucfirst($asset->status) }}</span>
        @if ($asset->meter_type && $asset->current_meter !== null)
            <div class="meta">Current {{ $asset->meter_type }}: <strong>{{ number_format((float) $asset->current_meter, 1) }}</strong></div>
        @endif
    </section>

    <form class="card" method="POST" action="{{ route('maintenance.assets.request', $asset->qr_token) }}" enctype="multipart/form-data">
        @csrf
        <h2>Report a problem</h2>
        <div class="grid">
            <div><label for="requester_name">Your name</label><input id="requester_name" name="requester_name" value="{{ old('requester_name') }}" required autocomplete="name"></div>
            <div><label for="requester_contact">Phone or email <span class="help">(optional)</span></label><input id="requester_contact" name="requester_contact" value="{{ old('requester_contact') }}"></div>
        </div>
        <label for="title">What is wrong?</label><input id="title" name="title" value="{{ old('title') }}" placeholder="Example: Brakes feel soft" required>
        <label for="description">Describe what you saw, heard, or felt</label><textarea id="description" name="description" required>{{ old('description') }}</textarea>
        <div class="grid">
            <div><label for="priority">How urgent is it?</label><select id="priority" name="priority">@foreach (\App\Support\MaintenanceOptions::priorities() as $value => $label)<option value="{{ $value }}" @selected(old('priority','normal') === $value)>{{ $label }}</option>@endforeach</select></div>
            @if ($asset->meter_type)<div><label for="meter_reading">Current {{ $asset->meter_type }}</label><input id="meter_reading" name="meter_reading" type="number" step="0.01" min="0" value="{{ old('meter_reading') }}"></div>@endif
        </div>
        <label for="photos">Photos <span class="help">(up to 4)</span></label><input id="photos" name="photos[]" type="file" accept="image/*" capture="environment" multiple>
        <label class="check"><input type="checkbox" name="safety_related" value="1" @checked(old('safety_related'))><span>This may be unsafe to operate. Stop using the equipment and notify a supervisor immediately.</span></label>
        <button type="submit">Submit maintenance request</button>
    </form>
    <footer>Asset {{ $asset->asset_tag }} · Scan this label again to report another issue</footer>
</main>
</body>
</html>
