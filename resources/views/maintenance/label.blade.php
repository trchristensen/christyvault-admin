<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><title>{{ $asset->asset_tag }} Maintenance Label</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; color:#111827; }
        .label { width:4in; min-height:5in; margin:.25in auto; padding:.24in; border:2px solid #111827; text-align:center; display:flex; flex-direction:column; align-items:center; }
        .eyebrow { color:#c25516; font-size:13px; letter-spacing:2px; font-weight:bold; text-transform:uppercase; }
        h1 { margin:8px 0 0; font-size:34px; } h2 { margin:3px 0 12px; font-size:22px; }
        svg { width:2.6in; height:2.6in; } p { font-size:16px; margin:8px; } .url { font-size:10px; overflow-wrap:anywhere; }
        button { margin:16px; padding:10px 16px; }
        @media print { button { display:none; } .label { margin:0; } }
    </style>
</head>
<body>
<button onclick="window.print()">Print label</button>
<div class="label">
    <div class="eyebrow">Maintenance · Scan to report</div>
    <h1>{{ $asset->asset_tag }}</h1><h2>{{ $asset->name }}</h2>
    {!! $asset->generateQrCode(900) !!}
    <p>Scan to report a problem, add photos, or record a meter reading.</p>
    <div class="url">{{ $asset->qr_url }}</div>
</div>
</body>
</html>
